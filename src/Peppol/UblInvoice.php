<?php

/**
 * Faktúra → UBL 2.1 podľa Peppol BIS Billing 3.0 (EN 16931).
 *
 * Appka je pre neplatiteľa DPH, preto celá faktúra ide v kategórii DPH „O“ (mimo predmetu dane):
 * bez sadzby, bez IČ DPH dodávateľa aj odberateľa (pravidlo BR-O-02), s dôvodom oslobodenia.
 * Peppol ID na Slovensku = 0245:DIČ (10 číslic bez SK).
 */
class UblInvoice
{
    private const NS_INV = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';
    public const PROFILE_ID       = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
    public const DOCUMENT_TYPE_ID = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##'
        . self::CUSTOMIZATION_ID . '::2.1';

    // Jednotky z appky → UN/ECE Rec 20
    private const UNITS = ['h' => 'HUR', 'hod' => 'HUR', 'ks' => 'H87', 'km' => 'KMT', 'deň' => 'DAY', 'mes' => 'MON', 'm' => 'MTR', 'kg' => 'KGM'];

    private DOMDocument $doc;

    /** Slovenské Peppol ID z DIČ (odstráni medzery, SK predponu aj neviditeľné znaky). */
    public static function participantId(?string $dic, ?string $icDph = null): ?string
    {
        $digits = preg_replace('/\D/', '', (string)$dic);
        if (strlen($digits) === 10) return '0245:' . $digits;
        // slovenské IČ DPH = SK + DIČ
        if (preg_match('/^SK(\d{10})$/', strtoupper(preg_replace('/\s+/', '', (string)$icDph)), $m)) return '0245:' . $m[1];
        return null;
    }

    /** @return array{xml: string, sender: string, receiver: string, cislo: string} */
    public static function build(int $fakturaId): array
    {
        $f = DB::fetch(
            "SELECT f.*, o.nazov AS o_nazov, o.ulica AS o_ulica, o.mesto AS o_mesto, o.psc AS o_psc,
                    o.stat AS o_stat, o.ico AS o_ico, o.dic AS o_dic, o.ic_dph AS o_ic_dph, o.email AS o_email
             FROM faktury f JOIN odberatelia o ON o.id = f.odberatel_id WHERE f.id = ?",
            [$fakturaId]
        );
        if (!$f) throw new RuntimeException('Faktúra nenájdená.');
        $d = DB::fetch("SELECT * FROM dodavatel WHERE id = 1");
        $polozky = DB::fetchAll("SELECT * FROM polozky WHERE faktura_id = ? ORDER BY poradie", [$fakturaId]);

        if (!empty($d['dph_platca'])) {
            throw new RuntimeException('E-faktúra je zatiaľ len pre neplatiteľa DPH (appka nepočíta DPH na položkách).');
        }
        $sender = self::participantId($d['dic'] ?? '');
        $receiver = self::participantId($f['o_dic'] ?? '', $f['o_ic_dph'] ?? '');
        if (!$sender) throw new RuntimeException('Dodávateľ nemá platné DIČ (10 číslic).');
        if (!$receiver) throw new RuntimeException('Odberateľ „' . $f['o_nazov'] . '“ nemá platné DIČ (10 číslic).');
        if (!$polozky) throw new RuntimeException('Faktúra nemá položky.');

        $self = new self();
        $xml = $self->render($f, $d, $polozky, $sender, $receiver);
        return ['xml' => $xml, 'sender' => $sender, 'receiver' => $receiver, 'cislo' => $f['cislo_faktury']];
    }

    private function render(array $f, array $d, array $polozky, string $sender, string $receiver): string
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $inv = $this->doc->createElementNS(self::NS_INV, 'Invoice');
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $inv->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $this->doc->appendChild($inv);

        $suma    = round((float)$f['celkova_suma'], 2);
        $zaloha  = round((float)($f['zaloha_suma'] ?? 0), 2);
        $naUhradu = round($suma - $zaloha, 2);

        $this->cbc($inv, 'CustomizationID', self::CUSTOMIZATION_ID);
        $this->cbc($inv, 'ProfileID', self::PROFILE_ID);
        $this->cbc($inv, 'ID', $f['cislo_faktury']);
        $this->cbc($inv, 'IssueDate', $f['datum_vystavenia']);
        $this->cbc($inv, 'DueDate', $f['datum_splatnosti']);
        $this->cbc($inv, 'InvoiceTypeCode', '380');

        $poznamky = [];
        if (trim((string)$f['poznamka']) !== '') $poznamky[] = trim($f['poznamka']);
        if ($zaloha > 0) {
            $poznamky[] = 'Odpočet zálohy' . ($f['zaloha_cislo'] ? ' ' . $f['zaloha_cislo'] : '')
                . ($f['zaloha_datum'] ? ' uhradenej ' . date('j.n.Y', strtotime($f['zaloha_datum'])) : '')
                . ': ' . number_format($zaloha, 2, ',', ' ') . ' EUR';
        }
        $poznamky[] = 'Dodávateľ nie je platiteľ DPH.';
        $this->cbc($inv, 'Note', implode("\n", $poznamky));

        $this->cbc($inv, 'DocumentCurrencyCode', 'EUR');
        // BT-10: Peppol vyžaduje buyer reference alebo číslo objednávky
        $this->cbc($inv, 'BuyerReference', $f['variabilny_symbol'] ?: $f['cislo_faktury']);

        // Dodávateľ
        $sp = $this->cac($this->cac($inv, 'AccountingSupplierParty'), 'Party');
        $this->endpoint($sp, $sender);
        $this->cbc($this->cac($sp, 'PartyName'), 'Name', $d['nazov']);
        $this->address($sp, $d['ulica'] ?? '', $d['mesto'] ?? '', $d['psc'] ?? '', 'SK');
        $le = $this->cac($sp, 'PartyLegalEntity');
        $this->cbc($le, 'RegistrationName', $d['nazov']);
        if ($ico = preg_replace('/\D/', '', (string)$d['ico'])) $this->cbc($le, 'CompanyID', $ico);
        if (!empty($d['email']) || !empty($d['telefon'])) {
            $c = $this->cac($sp, 'Contact');
            if (!empty($d['telefon'])) $this->cbc($c, 'Telephone', $d['telefon']);
            if (!empty($d['email'])) $this->cbc($c, 'ElectronicMail', $d['email']);
        }

        // Odberateľ
        $cp = $this->cac($this->cac($inv, 'AccountingCustomerParty'), 'Party');
        $this->endpoint($cp, $receiver);
        $this->cbc($this->cac($cp, 'PartyName'), 'Name', $f['o_nazov']);
        $this->address($cp, $f['o_ulica'] ?? '', $f['o_mesto'] ?? '', $f['o_psc'] ?? '', 'SK');
        $le = $this->cac($cp, 'PartyLegalEntity');
        $this->cbc($le, 'RegistrationName', $f['o_nazov']);
        $oIco = preg_replace('/\D/', '', (string)$f['o_ico']);
        if (strlen($oIco) === 8) $this->cbc($le, 'CompanyID', $oIco);
        if (!empty($f['o_email'])) $this->cbc($this->cac($cp, 'Contact'), 'ElectronicMail', $f['o_email']);

        // Dátum dodania
        $this->cbc($this->cac($inv, 'Delivery'), 'ActualDeliveryDate', $f['datum_dodania']);

        // Platba – SEPA prevod
        if (!empty($d['iban'])) {
            $pm = $this->cac($inv, 'PaymentMeans');
            $this->cbc($pm, 'PaymentMeansCode', '58');
            $this->cbc($pm, 'PaymentID', $f['variabilny_symbol'] ?: $f['cislo_faktury']);
            $acc = $this->cac($pm, 'PayeeFinancialAccount');
            $this->cbc($acc, 'ID', preg_replace('/\s+/', '', $d['iban']));
            $this->cbc($acc, 'Name', $d['nazov']);
            if (!empty($d['swift'])) $this->cbc($this->cac($acc, 'FinancialInstitutionBranch'), 'ID', trim($d['swift']));
        }

        // DPH – kategória O
        $tt = $this->cac($inv, 'TaxTotal');
        $this->amount($tt, 'TaxAmount', 0);
        $st = $this->cac($tt, 'TaxSubtotal');
        $this->amount($st, 'TaxableAmount', $suma);
        $this->amount($st, 'TaxAmount', 0);
        $cat = $this->cac($st, 'TaxCategory');
        $this->cbc($cat, 'ID', 'O');
        $this->cbc($cat, 'TaxExemptionReasonCode', 'VATEX-EU-O');
        $this->cbc($cat, 'TaxExemptionReason', 'Dodávateľ nie je platiteľ DPH');
        $this->cbc($this->cac($cat, 'TaxScheme'), 'ID', 'VAT');

        // Súčty
        $mt = $this->cac($inv, 'LegalMonetaryTotal');
        $this->amount($mt, 'LineExtensionAmount', $suma);
        $this->amount($mt, 'TaxExclusiveAmount', $suma);
        $this->amount($mt, 'TaxInclusiveAmount', $suma);
        if ($zaloha > 0) $this->amount($mt, 'PrepaidAmount', $zaloha);
        $this->amount($mt, 'PayableAmount', $naUhradu);

        // Položky
        foreach ($polozky as $i => $p) {
            $line = $this->cac($inv, 'InvoiceLine');
            $this->cbc($line, 'ID', (string)($i + 1));
            $q = $this->cbc($line, 'InvoicedQuantity', self::num((float)$p['mnozstvo'], 3));
            $q->setAttribute('unitCode', self::UNITS[mb_strtolower(trim((string)$p['jednotka']))] ?? 'C62');
            $this->amount($line, 'LineExtensionAmount', (float)$p['spolu']);
            $item = $this->cac($line, 'Item');
            $this->cbc($item, 'Name', mb_substr($p['nazov'], 0, 500));
            $ct = $this->cac($item, 'ClassifiedTaxCategory');
            $this->cbc($ct, 'ID', 'O');
            $this->cbc($this->cac($ct, 'TaxScheme'), 'ID', 'VAT');
            $this->amount($this->cac($line, 'Price'), 'PriceAmount', (float)$p['jednotkova_cena']);
        }

        return $this->doc->saveXML();
    }

    private function cac(DOMElement $parent, string $name): DOMElement
    {
        return $parent->appendChild($this->doc->createElementNS(self::NS_CAC, 'cac:' . $name));
    }

    private function cbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $el = $this->doc->createElementNS(self::NS_CBC, 'cbc:' . $name);
        $el->appendChild($this->doc->createTextNode($value));
        return $parent->appendChild($el);
    }

    private function amount(DOMElement $parent, string $name, float $value): void
    {
        $this->cbc($parent, $name, self::num($value, 2))->setAttribute('currencyID', 'EUR');
    }

    private function endpoint(DOMElement $party, string $participantId): void
    {
        [$scheme, $id] = explode(':', $participantId, 2);
        $this->cbc($party, 'EndpointID', $id)->setAttribute('schemeID', $scheme);
    }

    private function address(DOMElement $party, string $ulica, string $mesto, string $psc, string $krajina): void
    {
        $a = $this->cac($party, 'PostalAddress');
        if (trim($ulica) !== '') $this->cbc($a, 'StreetName', trim($ulica));
        if (trim($mesto) !== '') $this->cbc($a, 'CityName', trim($mesto));
        if (trim($psc) !== '') $this->cbc($a, 'PostalZone', trim($psc));
        $this->cbc($this->cac($a, 'Country'), 'IdentificationCode', $krajina);
    }

    private static function num(float $v, int $maxDec): string
    {
        $s = number_format($v, $maxDec, '.', '');
        // množstvo bez zbytočných núl (1.500 → 1.5), sumy vždy na 2 miesta
        return $maxDec > 2 ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}
