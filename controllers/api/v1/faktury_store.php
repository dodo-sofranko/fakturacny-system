<?php
// POST /api/v1/faktury  – nová faktúra; {"dry_run": true} len overí a vráti náhľad bez uloženia
$in = Api::input();
try {
    [$faktura, $polozky, $odberatel] = Faktury::prepare($in);
} catch (InvalidArgumentException $e) {
    Api::error(422, 'Neplatné údaje faktúry.', ['chyby' => json_decode($e->getMessage(), true)]);
}

if (!empty($in['dry_run'])) {
    Api::json(['dry_run' => true, 'odberatel' => $odberatel, 'polozky' => $polozky] + $faktura
        + ['na_uhradu' => round($faktura['celkova_suma'] - (float)($faktura['zaloha_suma'] ?? 0), 2)]);
}

$id = Faktury::create($faktura, $polozky);
Api::json(Faktury::detail($id), 201);
