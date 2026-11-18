<?php

/**
 * PDF z prijatej e-faktúry (UBL Invoice / CreditNote).
 * Ak dodávateľ do XML pribalil vlastné PDF (AdditionalDocumentReference), vráti sa to – je to jeho originál vzhľad.
 * Inak sa PDF vyrobí z údajov v XML (dompdf).
 */
class PrijataPdf
{
    private DOMXPath $x;

    private const JEDNOTKY = ['H87' => 'ks', 'C62' => 'ks', 'EA' => 'ks', 'XPP' => 'ks', 'HUR' => 'h', 'MIN' => 'min', 'DAY' => 'deň',
        'MON' => 'mes.', 'ANN' => 'rok', 'KGM' => 'kg', 'GRM' => 'g', 'LTR' => 'l', 'MTR' => 'm', 'MTK' => 'm²', 'MTQ' => 'm³',
        'KMT' => 'km', 'KWH' => 'kWh', 'SET' => 'sada', 'PR' => 'pár', 'XBX' => 'bal.', 'XPK' => 'bal.', 'LS' => 'paušál'];

    // Kategórie DPH bez sadzby (UNCL5305)
    private const DPH_KAT = ['O' => 'mimo DPH', 'E' => 'oslob.', 'AE' => 'prenes.', 'K' => 'EÚ', 'G' => 'vývoz', 'Z' => '0 %'];

    /** @return array{pdf: string, priloha: bool} */
    public static function render(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (!$ok) throw new RuntimeException('XML e-faktúry sa nedá prečítať.');

        $self = new self();
        $self->x = new DOMXPath($dom);
        $self->x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $self->x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        if ($pdf = $self->embeddedPdf()) return ['pdf' => $pdf, 'priloha' => true];

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($self->html($dom->documentElement->localName === 'CreditNote'), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return ['pdf' => $dompdf->output(), 'priloha' => false];
    }

    private function embeddedPdf(): ?string
    {
        foreach ($this->x->query('/*/cac:AdditionalDocumentReference/cac:Attachment/cbc:EmbeddedDocumentBinaryObject') as $n) {
            if (stripos($n->getAttribute('mimeCode'), 'pdf') === false) continue;
            $bin = base64_decode(preg_replace('/\s+/', '', $n->textContent), true);
            if ($bin !== false && str_starts_with($bin, '%PDF')) return $bin;
        }
        return null;
    }

    private function v(string $q, ?DOMNode $ctx = null): string
    {
        $n = $this->x->query($q, $ctx);
        return $n && $n->length ? trim($n->item(0)->textContent) : '';
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function money(string $v, string $mena): string
    {
        return $v === '' ? '' : number_format((float)$v, 2, ',', ' ') . ' ' . self::h($mena);
    }

    private static function qty(string $v): string
    {
        $v = trim($v);
        return str_contains($v, '.') ? str_replace('.', ',', rtrim(rtrim($v, '0'), '.')) : $v;
    }

    private static function datum(string $d): string
    {
        return $d && strtotime($d) ? date('d.m.Y', strtotime($d)) : '';
    }

    private function party(string $path): string
    {
        $p = $this->x->query($path)->item(0);
        if (!$p) return '';
        $r = [];
        $nazov = $this->v('cac:PartyLegalEntity/cbc:RegistrationName', $p) ?: $this->v('cac:PartyName/cbc:Name', $p);
        $r[] = '<div style="font-weight:bold;font-size:11pt">' . self::h($nazov) . '</div>';
        foreach (['cbc:StreetName', 'cbc:AdditionalStreetName'] as $f) {
            if ($s = $this->v("cac:PostalAddress/$f", $p)) $r[] = self::h($s);
        }
        $mesto = trim($this->v('cac:PostalAddress/cbc:PostalZone', $p) . ' ' . $this->v('cac:PostalAddress/cbc:CityName', $p));
        if ($mesto) $r[] = self::h($mesto);
        if ($k = $this->v('cac:PostalAddress/cac:Country/cbc:IdentificationCode', $p)) $r[] = self::h($k);
        $r[] = '';
        if ($ico = $this->v('cac:PartyLegalEntity/cbc:CompanyID', $p)) $r[] = 'IČO: ' . self::h($ico);
        foreach ($this->x->query('cac:PartyTaxScheme', $p) as $ts) {
            $id = $this->v('cbc:CompanyID', $ts);
            if ($id) $r[] = (str_starts_with(strtoupper($id), 'SK') || $this->v('cac:TaxScheme/cbc:ID', $ts) === 'VAT' ? 'IČ DPH: ' : 'DIČ: ') . self::h($id);
        }
        if ($e = $this->v('cbc:EndpointID', $p)) $r[] = '<span style="color:#888">Peppol: ' . self::h($this->x->query('cbc:EndpointID', $p)->item(0)->getAttribute('schemeID') . ':' . $e) . '</span>';
        if ($m = $this->v('cac:Contact/cbc:ElectronicMail', $p)) $r[] = self::h($m);
        return implode('<br>', $r);
    }

    private function html(bool $dobropis): string
    {
        $mena = $this->v('/*/cbc:DocumentCurrencyCode') ?: 'EUR';
        $cislo = $this->v('/*/cbc:ID');
        $lineTag = $dobropis ? 'cac:CreditNoteLine' : 'cac:InvoiceLine';
        $qtyTag = $dobropis ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity';

        // Položky
        $riadky = '';
        foreach ($this->x->query("/*/$lineTag") as $l) {
            $q = $this->x->query($qtyTag, $l)->item(0);
            $dph = $this->v('cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', $l);
            $popis = $this->v('cac:Item/cbc:Description', $l);
            $riadky .= '<tr>'
                . '<td>' . self::h($this->v('cac:Item/cbc:Name', $l)) . ($popis ? '<div style="color:#888;font-size:8pt">' . self::h($popis) . '</div>' : '') . '</td>'
                . '<td class="r">' . ($q ? self::h(self::qty($q->textContent) . ' ' . (self::JEDNOTKY[$q->getAttribute('unitCode')] ?? $q->getAttribute('unitCode'))) : '') . '</td>'
                . '<td class="r">' . self::money($this->v('cac:Price/cbc:PriceAmount', $l), $mena) . '</td>'
                . '<td class="r">' . ($dph !== '' ? self::h((float)$dph . ' %') : self::h(self::DPH_KAT[$this->v('cac:Item/cac:ClassifiedTaxCategory/cbc:ID', $l)] ?? $this->v('cac:Item/cac:ClassifiedTaxCategory/cbc:ID', $l))) . '</td>'
                . '<td class="r">' . self::money($this->v('cbc:LineExtensionAmount', $l), $mena) . '</td>'
                . '</tr>';
        }

        // Rekapitulácia DPH
        $dphRiadky = '';
        foreach ($this->x->query('/*/cac:TaxTotal/cac:TaxSubtotal') as $s) {
            $pct = $this->v('cac:TaxCategory/cbc:Percent', $s);
            $kat = $this->v('cac:TaxCategory/cbc:ID', $s);
            $dovod = $this->v('cac:TaxCategory/cbc:TaxExemptionReason', $s);
            $dphRiadky .= '<tr><td>' . ($pct !== '' && $kat !== 'O' && $kat !== 'E' ? self::h((float)$pct . ' %') : self::h(self::DPH_KAT[$kat] ?? $kat)) . ($dovod ? ' – ' . self::h($dovod) : '') . '</td>'
                . '<td class="r">' . self::money($this->v('cbc:TaxableAmount', $s), $mena) . '</td>'
                . '<td class="r">' . self::money($this->v('cbc:TaxAmount', $s), $mena) . '</td></tr>';
        }

        $t = '/*/cac:LegalMonetaryTotal/';
        $sumy = [
            ['Základ', $this->v($t . 'cbc:TaxExclusiveAmount')],
            ['Spolu s DPH', $this->v($t . 'cbc:TaxInclusiveAmount')],
            ['Uhradené zálohy', $this->v($t . 'cbc:PrepaidAmount')],
        ];
        $sumyHtml = '';
        foreach ($sumy as [$label, $val]) {
            if ($val !== '') $sumyHtml .= '<tr><td>' . $label . '</td><td class="r">' . self::money($val, $mena) . '</td></tr>';
        }

        // Platba
        $platba = [];
        if ($iban = $this->v('/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID')) $platba[] = 'IBAN: <b>' . self::h($iban) . '</b>';
        if ($bic = $this->v('/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID')) $platba[] = 'BIC: ' . self::h($bic);
        if ($vs = $this->v('/*/cac:PaymentMeans/cbc:PaymentID')) $platba[] = 'Variabilný symbol / ref.: <b>' . self::h($vs) . '</b>';
        if ($pt = $this->v('/*/cac:PaymentTerms/cbc:Note')) $platba[] = self::h($pt);

        $poznamky = [];
        foreach ($this->x->query('/*/cbc:Note') as $n) $poznamky[] = nl2br(self::h(trim($n->textContent)));

        $datumy = [
            'Dátum vystavenia' => self::datum($this->v('/*/cbc:IssueDate')),
            'Dátum dodania'    => self::datum($this->v('/*/cac:Delivery/cbc:ActualDeliveryDate') ?: $this->v('/*/cbc:TaxPointDate')),
            'Splatnosť'        => self::datum($this->v('/*/cbc:DueDate') ?: $this->v('/*/cac:PaymentMeans/cbc:PaymentDueDate')),
            'Objednávka'       => self::h($this->v('/*/cac:OrderReference/cbc:ID')),
            'K faktúre'        => self::h($this->v('/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID')),
        ];
        $datumyHtml = '';
        foreach ($datumy as $k => $v) if ($v !== '') $datumyHtml .= "<tr><td style=\"color:#666\">$k</td><td class=\"r\">$v</td></tr>";

        $title = ($dobropis ? 'Dobropis' : 'Faktúra') . ' ' . self::h($cislo);

        return '<!doctype html><html><head><meta charset="utf-8"><style>
            @page { margin: 14mm 14mm 16mm 14mm; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; line-height: 1.35; }
            h1 { font-size: 16pt; margin: 0 0 2mm; }
            .muted { color: #888; font-size: 8pt; }
            table { border-collapse: collapse; width: 100%; }
            .r { text-align: right; white-space: nowrap; }
            .box { border: 1px solid #ddd; padding: 3mm; vertical-align: top; }
            .lbl { font-size: 7.5pt; text-transform: uppercase; color: #888; margin-bottom: 1mm; }
            .items th { background: #f3f4f6; font-size: 8pt; text-align: left; padding: 1.5mm 2mm; border-bottom: 1px solid #ddd; }
            .items td { padding: 1.5mm 2mm; border-bottom: 1px solid #eee; vertical-align: top; }
            .sum td { padding: 1mm 2mm; }
            .total td { font-size: 12pt; font-weight: bold; border-top: 2px solid #222; padding-top: 2mm; }
        </style></head><body>
            <table><tr>
                <td><h1>' . $title . '</h1><div class="muted">Elektronická faktúra prijatá cez Peppol – náhľad vytvorený z XML</div></td>
                <td class="r" style="width:45%"><table>' . $datumyHtml . '</table></td>
            </tr></table>
            <table style="margin-top:5mm"><tr>
                <td class="box" style="width:50%"><div class="lbl">Dodávateľ</div>' . $this->party('/*/cac:AccountingSupplierParty/cac:Party') . '</td>
                <td class="box" style="width:50%"><div class="lbl">Odberateľ</div>' . $this->party('/*/cac:AccountingCustomerParty/cac:Party') . '</td>
            </tr></table>
            <table class="items" style="margin-top:5mm">
                <tr><th>Položka</th><th class="r">Množstvo</th><th class="r">Cena/j.</th><th class="r">DPH</th><th class="r">Spolu bez DPH</th></tr>
                ' . $riadky . '
            </table>
            <table style="margin-top:4mm"><tr>
                <td style="width:55%;vertical-align:top">'
                    . ($dphRiadky ? '<div class="lbl">Rekapitulácia DPH</div><table class="items"><tr><th>Sadzba</th><th class="r">Základ</th><th class="r">DPH</th></tr>' . $dphRiadky . '</table>' : '')
                    . ($platba ? '<div class="lbl" style="margin-top:4mm">Platba</div>' . implode('<br>', $platba) : '')
                . '</td>
                <td style="vertical-align:top;padding-left:6mm"><table class="sum">' . $sumyHtml . '
                    <tr class="total"><td>' . ($dobropis ? 'Dobropis' : 'Na úhradu') . '</td><td class="r">' . self::money($this->v($t . 'cbc:PayableAmount'), $mena) . '</td></tr>
                </table></td>
            </tr></table>
            ' . ($poznamky ? '<div class="lbl" style="margin-top:6mm">Poznámka</div>' . implode('<br>', $poznamky) : '') . '
        </body></html>';
    }
}
