<?php

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

class PdfGenerator
{
    public static function generateAndSave(int $fakturaId): void
    {
        $pdf = self::generate($fakturaId);
        DB::query(
            "UPDATE faktury SET pdf_data = ?, pdf_generated_at = NOW() WHERE id = ?",
            [$pdf, $fakturaId]
        );
    }

    public static function generate(int $fakturaId): string
    {
        $faktura = DB::fetch(
            "SELECT f.*, o.nazov AS o_nazov, o.ulica AS o_ulica, o.mesto AS o_mesto,
                    o.psc AS o_psc, o.stat AS o_stat, o.ico AS o_ico, o.dic AS o_dic, o.ic_dph AS o_ic_dph
             FROM faktury f
             JOIN odberatelia o ON o.id = f.odberatel_id
             WHERE f.id = ?",
            [$fakturaId]
        );

        $dodavatel = DB::fetch("SELECT * FROM dodavatel WHERE id = 1");
        $polozky   = DB::fetchAll("SELECT * FROM polozky WHERE faktura_id = ? ORDER BY poradie", [$fakturaId]);

        $qrBase64 = self::generateQrCode(
            $dodavatel['iban'] ?? '',
            (float)$faktura['celkova_suma'],
            $faktura['variabilny_symbol'] ?? $faktura['cislo_faktury'],
            $faktura['datum_splatnosti'],
            $dodavatel['nazov'] ?? ''
        );

        $html     = self::buildHtml($faktura, $dodavatel, $polozky, $qrBase64);
        $isdocXml = self::buildIsdoc($faktura, $dodavatel, $polozky);
        $isdocName = 'Faktura_' . preg_replace('/[^a-zA-Z0-9]/', '', $faktura['cislo_faktury']) . '.isdoc';

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Vložiť ISDOC XML ako embedded attachment cez CPDF
        $tmpFile = tempnam(sys_get_temp_dir(), 'isdoc_') . '.isdoc';
        file_put_contents($tmpFile, $isdocXml);

        $cpdf = $dompdf->getCanvas()->get_cpdf();
        $cpdf->addEmbeddedFile($tmpFile, $isdocName, 'ISDOC elektronická faktúra', 'text/xml');

        $output = $dompdf->output();
        unlink($tmpFile);

        return $output;
    }

    private static function removeDiacritics(string $s): string
    {
        $from = ['á','ä','č','ď','é','í','ľ','ĺ','ň','ó','ô','ŕ','š','ť','ú','ý','ž',
                 'Á','Ä','Č','Ď','É','Í','Ľ','Ĺ','Ň','Ó','Ô','Ŕ','Š','Ť','Ú','Ý','Ž'];
        $to   = ['a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z',
                 'A','A','C','D','E','I','L','L','N','O','O','R','S','T','U','Y','Z'];
        return str_replace($from, $to, $s);
    }

    private static function generateQrCode(string $iban, float $amount, string $vs, string $dueDate, string $recipient = ''): string
    {
        $recipientAscii = self::removeDiacritics($recipient);

        // PAY by Square QR
        try {
            $qrString = PayBySquare::generate($iban, $amount, 'EUR', $vs, $dueDate, $recipientAscii);
            if (!$qrString) throw new \Exception('empty');

            $qrCode = new QrCode(
                data: $qrString,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 220,
                margin: 4,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255),
            );
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            return base64_encode($result->getString());
        } catch (\Exception $e) {
            // Fallback: EPC QR (SEPA credit transfer)
            try {
                $fallback = "BCD\n002\n1\nSCT\n\n{$recipientAscii}\n{$iban}\nEUR" . number_format($amount, 2, '.', '') . "\n\n{$vs}\n";
                $qrCode = new QrCode(
                    data: $fallback,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: 220,
                    margin: 4,
                );
                $writer = new PngWriter();
                $result = $writer->write($qrCode);
                return base64_encode($result->getString());
            } catch (\Exception $e2) {
                return '';
            }
        }
    }

    private static function buildIsdoc(array $faktura, array $dodavatel, array $polozky): string
    {
        $x = fn(mixed $v): string => htmlspecialchars((string)($v ?? ''), ENT_XML1, 'UTF-8');

        $uuid      = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $datumV    = date('Y-m-d', strtotime($faktura['datum_vystavenia']));
        $datumD    = date('Y-m-d', strtotime($faktura['datum_dodania']));
        $datumS    = date('Y-m-d', strtotime($faktura['datum_splatnosti']));
        $suma      = number_format((float)$faktura['celkova_suma'], 2, '.', '');
        $vs        = $x($faktura['variabilny_symbol'] ?? $faktura['cislo_faktury']);
        $cislo     = $x($faktura['cislo_faktury']);

        // Dodávateľ
        $dNazov  = $x($dodavatel['nazov'] ?? '');
        $dUlica  = $x($dodavatel['ulica'] ?? '');
        $dMesto  = $x($dodavatel['mesto'] ?? '');
        $dPsc    = $x($dodavatel['psc'] ?? '');
        $dIco    = $x($dodavatel['ico'] ?? '');
        $dDic    = $x($dodavatel['dic'] ?? '');
        $dIcDph  = $x($dodavatel['ic_dph'] ?? '');
        $dIban   = $x($dodavatel['iban'] ?? '');
        $dSwift  = $x($dodavatel['swift'] ?? '');

        // Odberateľ
        $oNazov  = $x($faktura['o_nazov'] ?? '');
        $oUlica  = $x($faktura['o_ulica'] ?? '');
        $oMesto  = $x($faktura['o_mesto'] ?? '');
        $oPsc    = $x($faktura['o_psc'] ?? '');
        $oStat   = $x($faktura['o_stat'] ?? 'SK');
        $oIco    = $x($faktura['o_ico'] ?? '');
        $oDic    = $x($faktura['o_dic'] ?? '');
        $oIcDph  = $x($faktura['o_ic_dph'] ?? '');

        // Položky
        $linesXml   = '';
        $lineNum    = 1;
        foreach ($polozky as $p) {
            $nazov  = $x($p['nazov']);
            $mnoz   = number_format((float)$p['mnozstvo'], 4, '.', '');
            $jcena  = number_format((float)$p['jednotkova_cena'], 4, '.', '');
            $spolu  = number_format((float)$p['spolu'], 2, '.', '');
            $jed    = $x($p['jednotka'] ?? 'ks');
            $linesXml .= "
    <InvoiceLine>
      <ID>{$lineNum}</ID>
      <InvoicedQuantity unitCode=\"{$jed}\">{$mnoz}</InvoicedQuantity>
      <LineExtensionAmount currencyID=\"EUR\">{$spolu}</LineExtensionAmount>
      <Item>
        <Name>{$nazov}</Name>
      </Item>
      <Price>
        <PriceAmount currencyID=\"EUR\">{$jcena}</PriceAmount>
        <BaseQuantity unitCode=\"{$jed}\">1</BaseQuantity>
      </Price>
    </InvoiceLine>";
            $lineNum++;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="http://isdoc.cz/namespace/2013"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://isdoc.cz/namespace/2013 http://isdoc.cz/namespace/2013/isdoc-invoice-6.0.2.xsd"
         version="6.0.2">
  <DocumentType>1</DocumentType>
  <ID>' . $cislo . '</ID>
  <UUID>' . $uuid . '</UUID>
  <IssueDate>' . $datumV . '</IssueDate>
  <TaxPointDate>' . $datumD . '</TaxPointDate>
  <Note>' . $x($faktura['poznamka'] ?? '') . '</Note>
  <DocumentCurrencyCode>EUR</DocumentCurrencyCode>
  <AccountingSupplierParty>
    <Party>
      <PartyName><Name>' . $dNazov . '</Name></PartyName>
      <PostalAddress>
        <StreetName>' . $dUlica . '</StreetName>
        <CityName>' . $dMesto . '</CityName>
        <PostalZone>' . $dPsc . '</PostalZone>
        <Country><IdentificationCode>SK</IdentificationCode></Country>
      </PostalAddress>
      <PartyTaxScheme>
        <CompanyID>' . $dIcDph . '</CompanyID>
        <TaxScheme>VAT</TaxScheme>
      </PartyTaxScheme>
      <PartyLegalEntity>
        <RegistrationName>' . $dNazov . '</RegistrationName>
        <CompanyID>' . $dIco . '</CompanyID>
      </PartyLegalEntity>
    </Party>
  </AccountingSupplierParty>
  <AccountingCustomerParty>
    <Party>
      <PartyName><Name>' . $oNazov . '</Name></PartyName>
      <PostalAddress>
        <StreetName>' . $oUlica . '</StreetName>
        <CityName>' . $oMesto . '</CityName>
        <PostalZone>' . $oPsc . '</PostalZone>
        <Country><IdentificationCode>' . ($oStat ?: 'SK') . '</IdentificationCode></Country>
      </PostalAddress>
      <PartyTaxScheme>
        <CompanyID>' . $oIcDph . '</CompanyID>
        <TaxScheme>VAT</TaxScheme>
      </PartyTaxScheme>
      <PartyLegalEntity>
        <RegistrationName>' . $oNazov . '</RegistrationName>
        <CompanyID>' . $oIco . '</CompanyID>
      </PartyLegalEntity>
    </Party>
  </AccountingCustomerParty>
  <PaymentMeans>
    <PaymentMeansCode>42</PaymentMeansCode>
    <PaymentDueDate>' . $datumS . '</PaymentDueDate>
    <PaymentID>' . $vs . '</PaymentID>
    <PayeeFinancialAccount>
      <ID>' . $dIban . '</ID>
      <FinancialInstitutionBranch>
        <FinancialInstitution><ID>' . $dSwift . '</ID></FinancialInstitution>
      </FinancialInstitutionBranch>
    </PayeeFinancialAccount>
  </PaymentMeans>
  <LegalMonetaryTotal>
    <TaxExclusiveAmount currencyID="EUR">' . $suma . '</TaxExclusiveAmount>
    <TaxInclusiveAmount currencyID="EUR">' . $suma . '</TaxInclusiveAmount>
    <PayableAmount currencyID="EUR">' . $suma . '</PayableAmount>
  </LegalMonetaryTotal>
' . $linesXml . '
</Invoice>';
    }

    private static function h(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private static function buildHtml(array $faktura, array $dodavatel, array $polozky, string $qrBase64): string
    {
        $cislo  = self::h($faktura['cislo_faktury']);
        $datumV = date('d.m.Y', strtotime($faktura['datum_vystavenia']));
        $datumD = date('d.m.Y', strtotime($faktura['datum_dodania']));
        $datumS = date('d.m.Y', strtotime($faktura['datum_splatnosti']));
        $suma   = number_format((float)$faktura['celkova_suma'], 2, ',', ' ');
        $vs     = self::h($faktura['variabilny_symbol'] ?? $faktura['cislo_faktury']);
        $poz    = self::h($faktura['poznamka'] ?? '');

        $dNazov  = self::h($dodavatel['nazov'] ?? '');
        $dUlica  = self::h($dodavatel['ulica'] ?? '');
        $dPsc    = self::h($dodavatel['psc'] ?? '');
        $dMesto  = self::h($dodavatel['mesto'] ?? '');
        $dIco    = self::h($dodavatel['ico'] ?? '');
        $dDic    = self::h($dodavatel['dic'] ?? '');
        $dIcDph  = self::h($dodavatel['ic_dph'] ?? '');
        $dDph    = empty($dodavatel['dph_platca']) ? 'Nie je platiteľ DPH.' : '';
        $dIban   = self::h($dodavatel['iban'] ?? '');
        $dSwift  = self::h($dodavatel['swift'] ?? '');
        $dPodpis = self::h($dodavatel['podpis_text'] ?? '');

        $oNazov   = self::h($faktura['o_nazov'] ?? '');
        $oUlica   = self::h($faktura['o_ulica'] ?? '');
        $oPsc     = self::h($faktura['o_psc'] ?? '');
        $oMesto   = self::h($faktura['o_mesto'] ?? '');
        $oStat    = self::h($faktura['o_stat'] ?? 'Slovenská republika');
        $oIco     = self::h($faktura['o_ico'] ?? '');
        $oDic     = self::h($faktura['o_dic'] ?? '');
        $oIcDph   = self::h($faktura['o_ic_dph'] ?? '');

        // Dodávateľ riadky
        $dAdresa    = trim($dPsc . ' ' . $dMesto);
        $dIcoDic    = ($dIco ? 'IČO: ' . $dIco : '') . ($dDic ? '&nbsp;&nbsp;DIČ: ' . $dDic : '');
        $dIcDphLine = $dIcDph ? '<div>IČ DPH: ' . $dIcDph . '</div>' : '';
        $dDphLine   = $dDph   ? '<div style="color:#888">' . $dDph . '</div>' : '';
        $dSwiftLine = $dSwift ? '<div>SWIFT:&nbsp; <strong>' . $dSwift . '</strong></div>' : '';

        // Odberateľ riadky
        $oAdresa    = trim($oPsc . ' ' . $oMesto);
        $oIcoDic    = ($oIco ? 'IČO: ' . $oIco : '') . ($oDic ? '&nbsp;&nbsp;DIČ: ' . $oDic : '');
        $oIcDphLine = $oIcDph ? '<div>IČ DPH: ' . $oIcDph . '</div>' : '';

        // Podpis text (register záznam)
        $dPodpisLine = $dPodpis
            ? '<div style="font-size:8pt;color:#666;line-height:1.5">' . $dPodpis . '</div>'
            : '';

        // Poznámka nad tabuľkou
        $poznamkaHtml = $poz
            ? '<div style="font-size:9.5pt;color:#333;margin-bottom:6px">' . $poz . '</div>'
            : '';

        // QR
        $qrImg = $qrBase64
            ? '<img src="data:image/png;base64,' . $qrBase64 . '" width="100" height="100" alt="QR">'
            : '';

        // Obrázok podpisu
        $podpisImg = '';
        if (!empty($dodavatel['podpis_png'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->buffer($dodavatel['podpis_png']) ?: 'image/png';
            $podpisImg = '<img src="data:' . $mime . ';base64,' . base64_encode($dodavatel['podpis_png']) . '" style="width:200px" alt="podpis">';
        }

        // Položky
        $polozkyHtml = '';
        foreach ($polozky as $i => $p) {
            $n     = $i + 1;
            $nazov = self::h($p['nazov']);
            // Množstvo: ak je celé číslo, zobrazíme bez desatinných miest; inak max 2 des. miesta + jednotka
            $mnozRaw = (float)$p['mnozstvo'];
            $jednotka = self::h($p['jednotka'] ?? 'ks');
            if ($mnozRaw == floor($mnozRaw)) {
                $mnoz = number_format($mnozRaw, 0, ',', ' ') . ',00 ' . $jednotka;
            } else {
                $mnoz = rtrim(rtrim(number_format($mnozRaw, 2, ',', ' '), '0'), ',') . ' ' . $jednotka;
            }
            $jcena = number_format((float)$p['jednotkova_cena'], 2, ',', ' ');
            $spolu = number_format((float)$p['spolu'], 2, ',', ' ');

            $bg = ($i % 2 === 0) ? '#fff' : '#f7f7f7';
            $polozkyHtml .= '<tr style="background:' . $bg . '">'
                . '<td style="padding:5px 8px;color:#555">' . $n . '.</td>'
                . '<td style="padding:5px 8px;font-weight:bold">' . $nazov . '</td>'
                . '<td style="padding:5px 8px;text-align:right">' . $mnoz . '</td>'
                . '<td style="padding:5px 8px;text-align:right">' . $jcena . '</td>'
                . '<td style="padding:5px 8px;text-align:right;font-weight:bold">' . $spolu . '</td>'
                . '</tr>';
        }

        $S = 'font-family:DejaVu Sans,Arial,sans-serif;font-size:10pt;color:#222;line-height:1.3;';
        $LBL = 'font-size:9pt;font-weight:bold;color:#333;text-transform:uppercase;';

        // Dodávateľ — jednoduché riadky ako vo vzore
        $dAdresaLine = $dUlica . ', ' . $dPsc . ' ' . $dMesto . ', Slovensko';
        $dIcoDic     = ($dIco ? 'IČO: ' . $dIco : '')
                     . ($dDic ? '&nbsp;&nbsp; DIČ: ' . $dDic : '');

        return '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8"><style>b,strong{font-weight:600}td,div,p,span{font-weight:inherit}</style></head>
<body style="' . $S . 'margin:0;padding:0">

<!-- NADPIS vpravo hore -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0">
<tr>
  <td style="text-align:right;vertical-align:top">
    <span style="display:inline-block;background:#ccc;color:#fff;font-size:7pt;font-weight:bold;padding:2px 6px;border-radius:3px;letter-spacing:0.5px;margin-right:6px;vertical-align:top;margin-top:3px">ISDOC</span><span style="font-size:16pt;font-weight:bold;vertical-align:top">FAKTÚRA ' . $cislo . '</span>
  </td>
</tr>
</table>

<!-- SEKCIA 1: Dodávateľ (vľavo) | Odberateľ (vpravo) -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px">
<tr valign="top">
  <td width="48%">
    <div style="' . $LBL . 'margin:0 0 4px 0">Dodávateľ</div>
    <div>' . $dNazov . '</div>
    <div>' . $dAdresaLine . '</div>
    ' . ($dIcoDic ? '<div>' . $dIcoDic . '</div>' : '') . '
    ' . ($dIcDph ? '<div>IČ DPH: ' . $dIcDph . '</div>' : '') . '
    ' . ($dDph ? '<div style="color:#888">' . $dDph . '</div>' : '') . '
  </td>
  <td></td>
  <td width="40%">
    <div style="' . $LBL . 'margin:0 0 4px 0">Odberateľ</div>
    <div>' . $oNazov . '</div>
    <div>' . $oUlica . '</div>
    <div>' . $oPsc . ' ' . $oMesto . '</div>
    <div>' . $oStat . '</div>
    ' . ($oIcoDic ? '<div>' . $oIcoDic . '</div>' : '') . '
    ' . ($oIcDph ? '<div>IČ DPH: ' . $oIcDph . '</div>' : '') . '
  </td>
</tr>
</table>

<!-- SEKCIA 2: Dátumy (vľavo) | IČO odberateľa + Platobný box + QR (vpravo) -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
<tr valign="top">
  <td width="30%">
    <table cellpadding="0" cellspacing="0">
      <tr>
        <td style="padding-right:8px;padding-bottom:4px;vertical-align:top;white-space:nowrap">Dátum vystavenia:</td>
        <td style="padding-bottom:4px;vertical-align:top">' . $datumV . '</td>
      </tr>
      <tr>
        <td style="padding-right:8px;padding-bottom:4px;vertical-align:top;white-space:nowrap">Dátum dodania:</td>
        <td style="padding-bottom:4px;vertical-align:top">' . $datumD . '</td>
      </tr>
      <tr>
        <td style="padding-right:8px;vertical-align:top;white-space:nowrap">Splatnosť:</td>
        <td style="vertical-align:top">' . $datumS . '</td>
      </tr>
    </table>
  </td>
  <td width="4%"></td>
  <td width="68%" valign="top">
    <div style="border:0.5pt solid #bbb;background:#f7f7f7;padding:9px 12px">
      <table width="100%" cellpadding="0" cellspacing="0">
      <tr valign="middle">
        <td>
          <p style="margin:0">Spôsob úhrady:&nbsp; <strong>Bankový prevod</strong></p>
          <p style="margin:3px 0 0 0">Suma:&nbsp; <strong style="font-size:12pt">' . $suma . ' EUR</strong></p>
          <p style="margin:2px 0 0 0">Variabilný symbol:&nbsp; <strong>' . $vs . '</strong></p>
          <p style="margin:4px 0 0 0">IBAN:&nbsp; <strong>' . $dIban . '</strong></p>
          ' . ($dSwift ? '<p style="margin:1px 0 0 0">SWIFT:&nbsp; <strong>' . $dSwift . '</strong></p>' : '') . '
        </td>
        <td width="1%"></td>
        <td width="22%" valign="top" align="right">' . $qrImg . '</td>
      </tr>
      </table>
    </div>
  </td>
</tr>
</table>

' . $poznamkaHtml . '

<!-- TABUĽKA POLOŽIEK — inline štýly, žiadne CSS triedy -->
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:0;font-size:9pt">
  <thead>
    <tr>
      <td width="5%"  style="' . $LBL . 'padding:5px 6px;background:#eee">Č.</td>
      <td width="49%" style="' . $LBL . 'padding:5px 6px;background:#eee">Názov</td>
      <td width="14%" style="' . $LBL . 'padding:5px 6px;background:#eee;text-align:right">Množstvo</td>
      <td width="14%" style="' . $LBL . 'padding:5px 6px;background:#eee;text-align:right">Jedn. cena</td>
      <td width="14%" style="' . $LBL . 'padding:5px 6px;background:#eee;text-align:right">Spolu</td>
    </tr>
  </thead>
  <tbody>' . $polozkyHtml . '</tbody>
</table>

<!-- SPOLU riadok -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:30px">
<tr>
  <td width="50%"></td>
  <td width="50%" style="background:#f7f7f7;padding:4px 12px">
    <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td style="font-weight:bold;font-size:12pt">Spolu</td>
      <td style="font-weight:bold;font-size:12pt;text-align:right">' . $suma . ' EUR</td>
    </tr>
    </table>
  </td>
</tr>
</table>

<!-- PODPIS — vycentrovaný v pravej polovici -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:5px">
<tr>
  <td width="70%"></td>
  <td width="30%" align="center">
    <div style="text-align:center">
      ' . ($podpisImg ? $podpisImg . '<br>' : '') . '
    </div>
  </td>
</tr>
</table>

<!-- Register text — fixne na spodku stránky, vycentrovaný -->
' . ($dPodpis ? '<div style="position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:7.5pt;color:#777">' . $dPodpis . '</div>' : '') . '

</body></html>';
    }
}
