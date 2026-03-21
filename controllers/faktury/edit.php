<?php
$id = (int)($_GET['id'] ?? 0);
$faktura = DB::fetch("SELECT * FROM faktury WHERE id = ?", [$id]);
if (!$faktura) redirect('/faktury', 'Faktúra nenájdená.', 'error');

$polozky = DB::fetchAll("SELECT * FROM polozky WHERE faktura_id = ? ORDER BY poradie", [$id]);
$dodavatel = DB::fetch("SELECT * FROM dodavatel WHERE id = 1");
$odberatelia = DB::fetchAll("SELECT id, nazov FROM odberatelia ORDER BY nazov");

view('faktury/form', [
    'faktura'     => $faktura,
    'polozky'     => $polozky,
    'dodavatel'   => $dodavatel,
    'odberatelia' => $odberatelia,
    'nextNumber'  => $faktura['cislo_faktury'],
    'today'       => $faktura['datum_vystavenia'],
    'splatnost'   => $faktura['datum_splatnosti'],
    'pageTitle'   => 'Upraviť faktúru ' . $faktura['cislo_faktury'] . ' — ' . APP_NAME,
]);
