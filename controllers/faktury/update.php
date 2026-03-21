<?php

$id         = (int)($_GET['id'] ?? 0);
$cislo      = trim($_POST['cislo_faktury'] ?? '');
$odberatelId = (int)($_POST['odberatel_id'] ?? 0);
$datumV     = trim($_POST['datum_vystavenia'] ?? '');
$datumD     = trim($_POST['datum_dodania'] ?? '');
$datumS     = trim($_POST['datum_splatnosti'] ?? '');
$vs         = trim($_POST['variabilny_symbol'] ?? $cislo);
$poznamka   = trim($_POST['poznamka'] ?? '');

if (!$id || !$cislo || !$odberatelId || !$datumV || !$datumD || !$datumS) {
    redirect("/faktury/$id/edit", 'Vyplňte všetky povinné polia.', 'error');
}

$polozkyNazvy    = $_POST['polozka_nazov'] ?? [];
$polozkyMnozstvo = $_POST['polozka_mnozstvo'] ?? [];
$polozkyJednotka = $_POST['polozka_jednotka'] ?? [];
$polozkyJCena    = $_POST['polozka_jcena'] ?? [];

$celkovaSuma = 0.0;
$polozkyData = [];

foreach ($polozkyNazvy as $i => $nazov) {
    $nazov = trim($nazov);
    if (!$nazov) continue;
    $mnozstvo = (float)str_replace(',', '.', $polozkyMnozstvo[$i] ?? 1);
    $jcena    = (float)str_replace(',', '.', $polozkyJCena[$i] ?? 0);
    $spolu    = round($mnozstvo * $jcena, 2);
    $celkovaSuma += $spolu;
    $polozkyData[] = [
        'nazov'           => $nazov,
        'mnozstvo'        => $mnozstvo,
        'jednotka'        => trim($polozkyJednotka[$i] ?? 'ks'),
        'jednotkova_cena' => $jcena,
        'spolu'           => $spolu,
    ];
}

DB::update('faktury', [
    'cislo_faktury'    => $cislo,
    'odberatel_id'     => $odberatelId,
    'datum_vystavenia' => $datumV,
    'datum_dodania'    => $datumD,
    'datum_splatnosti' => $datumS,
    'variabilny_symbol'=> $vs,
    'poznamka'         => $poznamka ?: null,
    'celkova_suma'     => $celkovaSuma,
    'pdf_data'         => null,
    'pdf_generated_at' => null,
], 'id = ?', [$id]);

// Zmažeme staré položky a vložíme nové
DB::delete('polozky', 'faktura_id = ?', [$id]);
foreach ($polozkyData as $i => $p) {
    DB::insert('polozky', array_merge(['faktura_id' => $id, 'poradie' => $i + 1], $p));

    DB::query(
        "INSERT INTO polozky_suggestions (nazov, posledna_cena, pouziti_count)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE posledna_cena = VALUES(posledna_cena), pouziti_count = pouziti_count + 1, updated_at = NOW()",
        [$p['nazov'], $p['jednotkova_cena']]
    );
}

require_once __DIR__ . '/../../src/PdfGenerator.php';
PdfGenerator::generateAndSave($id);

redirect('/faktury', 'Faktúra ' . $cislo . ' bola uložená.');
