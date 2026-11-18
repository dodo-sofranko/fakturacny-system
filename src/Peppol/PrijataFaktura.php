<?php

/**
 * Prijaté e-faktúry: stiahnutie od poštára, vytiahnutie základných údajov z UBL a uloženie do DB.
 * Originál XML sa ukladá celý (archivácia 10 rokov), údaje sú len na prehľad v zozname.
 */
class PrijataFaktura
{
    /** Stiahne čakajúce doklady, uloží ich a potvrdí poštárovi. Vráti počet nových. */
    public static function sync(SapiClient $client): int
    {
        $nove = 0;
        foreach ($client->pendingDocuments() as $meta) {
            $docId = (string)($meta['documentId'] ?? '');
            if ($docId === '') continue;

            $exists = DB::fetch("SELECT id FROM prijate_efaktury WHERE document_id = ?", [$docId]);
            if (!$exists) {
                $doc = $client->receivedDocument($docId);
                $m = $doc['metadata'] + $meta;
                $data = self::parse($doc['xml']);
                DB::insert('prijate_efaktury', $data + [
                    'document_id'    => $docId,
                    'odosielatel_id' => SapiClient::shortParticipantId($m['senderParticipantId'] ?? ''),
                    'prijate_at'     => self::dateTime($m['creationDateTime'] ?? null),
                    'xml'            => $doc['xml'],
                ]);
                $nove++;
            }
            // potvrdiť až po uložení – inak by sa doklad pri chybe stratil
            $client->acknowledge($docId);
        }
        return $nove;
    }

    /** Základné údaje z UBL Invoice / CreditNote. */
    public static function parse(string $xml): array
    {
        $out = ['typ' => 'faktura', 'cislo' => null, 'dodavatel' => null, 'dodavatel_ico' => null,
                'datum_vystavenia' => null, 'datum_splatnosti' => null, 'suma' => null, 'mena' => null];
        if (trim($xml) === '') return $out;

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (!$ok) return $out;

        $x = new DOMXPath($dom);
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $v = fn(string $q) => ($n = $x->query($q)) && $n->length ? trim($n->item(0)->textContent) : null;

        $root = $dom->documentElement->localName;
        $out['typ'] = $root === 'CreditNote' ? 'dobropis' : 'faktura';
        $out['cislo'] = $v('/*/cbc:ID');
        $out['datum_vystavenia'] = self::date($v('/*/cbc:IssueDate'));
        $out['datum_splatnosti'] = self::date($v('/*/cbc:DueDate') ?? $v('/*/cac:PaymentMeans/cbc:PaymentDueDate'));
        $sp = '/*/cac:AccountingSupplierParty/cac:Party';
        $out['dodavatel'] = $v("$sp/cac:PartyLegalEntity/cbc:RegistrationName") ?? $v("$sp/cac:PartyName/cbc:Name");
        $out['dodavatel_ico'] = $v("$sp/cac:PartyLegalEntity/cbc:CompanyID");
        $suma = $v('/*/cac:LegalMonetaryTotal/cbc:PayableAmount') ?? $v('/*/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');
        $out['suma'] = $suma !== null && is_numeric($suma) ? (float)$suma : null;
        $out['mena'] = $v('/*/cbc:DocumentCurrencyCode');

        foreach (['cislo' => 100, 'dodavatel' => 255, 'dodavatel_ico' => 50] as $k => $len) {
            if ($out[$k] !== null) $out[$k] = mb_substr($out[$k], 0, $len);
        }
        if ($out['mena'] !== null) $out['mena'] = substr($out['mena'], 0, 3);
        return $out;
    }

    private static function date(?string $d): ?string
    {
        return $d && preg_match('/^\d{4}-\d{2}-\d{2}/', $d) ? substr($d, 0, 10) : null;
    }

    private static function dateTime(?string $d): string
    {
        $t = $d ? strtotime($d) : false;
        return date('Y-m-d H:i:s', $t ?: time());
    }
}
