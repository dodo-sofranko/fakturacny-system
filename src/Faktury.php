<?php

/**
 * Vytváranie a čítanie faktúr pre API (webový formulár má vlastný controller faktury/store).
 */
class Faktury
{
    /**
     * Overí a pripraví dáta novej faktúry. Vráti [faktura, polozky] alebo hodí InvalidArgumentException.
     * Vstup: odberatel_id | odberatel_ico, polozky[{nazov, mnozstvo=1, jednotka=ks, jednotkova_cena}],
     *        voliteľne cislo_faktury, datum_vystavenia (dnes), datum_dodania (= vystavenia),
     *        datum_splatnosti | splatnost_dni (14), variabilny_symbol (= číslo), poznamka, zaloha{suma, datum, cislo}.
     */
    public static function prepare(array $in): array
    {
        $err = [];

        // Odberateľ
        $odb = null;
        if (!empty($in['odberatel_id'])) {
            $odb = DB::fetch("SELECT id, nazov FROM odberatelia WHERE id = ?", [(int)$in['odberatel_id']]);
        } elseif (!empty($in['odberatel_ico'])) {
            $ico = preg_replace('/\D/', '', (string)$in['odberatel_ico']);
            foreach (DB::fetchAll("SELECT id, nazov, ico FROM odberatelia") as $o) {
                if ($ico !== '' && preg_replace('/\D/', '', (string)$o['ico']) === $ico) { $odb = $o; break; }
            }
        }
        if (!$odb) $err['odberatel'] = 'Odberateľ nenájdený (pošli odberatel_id alebo odberatel_ico; zoznam: GET /api/v1/odberatelia).';

        // Dátumy
        $datumV = self::date($in['datum_vystavenia'] ?? null) ?? date('Y-m-d');
        $datumD = self::date($in['datum_dodania'] ?? null) ?? $datumV;
        $dni = isset($in['splatnost_dni']) ? (int)$in['splatnost_dni'] : 14;
        $datumS = self::date($in['datum_splatnosti'] ?? null) ?? date('Y-m-d', strtotime("$datumV +$dni days"));
        foreach (['datum_vystavenia', 'datum_dodania', 'datum_splatnosti'] as $k) {
            if (!empty($in[$k]) && !self::date($in[$k])) $err[$k] = 'Dátum musí byť v tvare RRRR-MM-DD.';
        }
        if ($datumS < $datumV) $err['datum_splatnosti'] = 'Splatnosť je pred dátumom vystavenia.';

        // Číslo
        $cislo = trim((string)($in['cislo_faktury'] ?? '')) ?: nextInvoiceNumber();
        if (DB::fetch("SELECT id FROM faktury WHERE cislo_faktury = ?", [$cislo])) {
            $err['cislo_faktury'] = "Faktúra $cislo už existuje.";
        }

        // Položky
        $polozky = [];
        $suma = 0.0;
        foreach ((array)($in['polozky'] ?? []) as $i => $p) {
            $nazov = trim((string)($p['nazov'] ?? ''));
            if ($nazov === '') { $err["polozky.$i.nazov"] = 'Chýba názov položky.'; continue; }
            if (!isset($p['jednotkova_cena']) || !is_numeric(str_replace(',', '.', (string)$p['jednotkova_cena']))) {
                $err["polozky.$i.jednotkova_cena"] = 'Chýba alebo je neplatná cena.'; continue;
            }
            $mnozstvo = (float)str_replace(',', '.', (string)($p['mnozstvo'] ?? 1));
            $jcena = round((float)str_replace(',', '.', (string)$p['jednotkova_cena']), 2);
            $spolu = round($mnozstvo * $jcena, 2);
            $suma += $spolu;
            $polozky[] = [
                'nazov'           => mb_substr($nazov, 0, 500),
                'mnozstvo'        => round($mnozstvo, 3),
                'jednotka'        => mb_substr(trim((string)($p['jednotka'] ?? 'ks')) ?: 'ks', 0, 20),
                'jednotkova_cena' => $jcena,
                'spolu'           => $spolu,
            ];
        }
        if (!$polozky && empty(array_filter(array_keys($err), fn($k) => str_starts_with($k, 'polozky.')))) {
            $err['polozky'] = 'Faktúra musí mať aspoň jednu položku.';
        }

        // Záloha (vyúčtovacia faktúra)
        $z = (array)($in['zaloha'] ?? []);
        $zSuma = isset($z['suma']) ? round((float)str_replace(',', '.', (string)$z['suma']), 2) : 0.0;

        if ($err) throw new InvalidArgumentException(json_encode($err, JSON_UNESCAPED_UNICODE));

        return [[
            'cislo_faktury'     => $cislo,
            'odberatel_id'      => (int)$odb['id'],
            'datum_vystavenia'  => $datumV,
            'datum_dodania'     => $datumD,
            'datum_splatnosti'  => $datumS,
            'variabilny_symbol' => trim((string)($in['variabilny_symbol'] ?? '')) ?: $cislo,
            'poznamka'          => trim((string)($in['poznamka'] ?? '')) ?: null,
            'celkova_suma'      => round($suma, 2),
            'zaloha_suma'       => $zSuma > 0 ? $zSuma : null,
            'zaloha_datum'      => $zSuma > 0 ? self::date($z['datum'] ?? null) : null,
            'zaloha_cislo'      => $zSuma > 0 && !empty($z['cislo']) ? (string)$z['cislo'] : null,
        ], $polozky, $odb['nazov']];
    }

    /** Uloží faktúru + položky, aktualizuje návrhy a vygeneruje PDF. Vráti ID. */
    public static function create(array $faktura, array $polozky): int
    {
        $pdo = DB::getInstance();
        $pdo->beginTransaction();
        try {
            $id = DB::insert('faktury', $faktura);
            foreach ($polozky as $i => $p) {
                DB::insert('polozky', ['faktura_id' => $id, 'poradie' => $i + 1] + $p);
                DB::query(
                    "INSERT INTO polozky_suggestions (nazov, posledna_cena, pouziti_count) VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE posledna_cena = VALUES(posledna_cena), pouziti_count = pouziti_count + 1, updated_at = NOW()",
                    [$p['nazov'], $p['jednotkova_cena']]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        require_once __DIR__ . '/PdfGenerator.php';
        PdfGenerator::generateAndSave($id);
        return $id;
    }

    /** Faktúra pre API výstup (bez binárnych dát). */
    public static function detail(int $id): ?array
    {
        $f = DB::fetch(
            "SELECT f.id, f.cislo_faktury, f.odberatel_id, o.nazov AS odberatel, f.datum_vystavenia, f.datum_dodania,
                    f.datum_splatnosti, f.variabilny_symbol, f.poznamka, f.celkova_suma, f.zaloha_suma, f.zaloha_datum,
                    f.zaloha_cislo, f.peppol_status, f.peppol_sent_at, f.peppol_detail, f.created_at
             FROM faktury f JOIN odberatelia o ON o.id = f.odberatel_id WHERE f.id = ?",
            [$id]
        );
        if (!$f) return null;
        $f['polozky'] = DB::fetchAll(
            "SELECT nazov, mnozstvo, jednotka, jednotkova_cena, spolu FROM polozky WHERE faktura_id = ? ORDER BY poradie", [$id]
        );
        return self::cast($f);
    }

    public static function cast(array $f): array
    {
        foreach (['id', 'odberatel_id'] as $k) if (isset($f[$k])) $f[$k] = (int)$f[$k];
        foreach (['celkova_suma', 'zaloha_suma'] as $k) if (isset($f[$k])) $f[$k] = (float)$f[$k];
        if (array_key_exists('celkova_suma', $f)) $f['na_uhradu'] = round((float)$f['celkova_suma'] - (float)($f['zaloha_suma'] ?? 0), 2);
        foreach ($f['polozky'] ?? [] as $i => $p) {
            foreach (['mnozstvo', 'jednotkova_cena', 'spolu'] as $k) $f['polozky'][$i][$k] = (float)$p[$k];
        }
        $f['pdf_url'] = APP_URL . BASE_PATH . '/api/v1/faktury/' . $f['id'] . '/pdf';
        return $f;
    }

    private static function date(mixed $d): ?string
    {
        if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
        [$y, $m, $dd] = array_map('intval', explode('-', $d));
        return checkdate($m, $dd, $y) ? $d : null;
    }
}
