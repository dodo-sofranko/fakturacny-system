<?php

$id = (int)($_GET['id'] ?? 0);
$faktura = DB::fetch("SELECT * FROM faktury WHERE id = ?", [$id]);
if (!$faktura) {
    redirect('/faktury', 'Faktúra nenájdená.', 'error');
}

$polozky = DB::fetchAll("SELECT * FROM polozky WHERE faktura_id = ? ORDER BY poradie", [$id]);
$dodavatel = DB::fetch("SELECT * FROM dodavatel WHERE id = 1");
$odberatelia = DB::fetchAll("SELECT id, nazov FROM odberatelia ORDER BY nazov");

// Nové číslo faktúry, zachovávame ostatné hodnoty
$nextNumber = nextInvoiceNumber();
$today = date('Y-m-d');
$splatnost = date('Y-m-d', strtotime('+14 days'));

// Predvyplníme faktúru hodnotami z originálu, len číslo faktúry bude nové
$fakturaCopy = $faktura;
$fakturaCopy['cislo_faktury'] = $nextNumber;
$fakturaCopy['datum_vystavenia'] = $today;
$fakturaCopy['datum_dodania'] = $today;
$fakturaCopy['datum_splatnosti'] = $splatnost;
$fakturaCopy['variabilny_symbol'] = $nextNumber;
$fakturaCopy['id'] = null; // nie je uložená

view('faktury/form', [
    'faktura'     => $fakturaCopy,
    'polozky'     => $polozky,
    'dodavatel'   => $dodavatel,
    'odberatelia' => $odberatelia,
    'nextNumber'  => $nextNumber,
    'today'       => $today,
    'splatnost'   => $splatnost,
    'isCopy'      => true,
    'pageTitle'   => 'Kópia faktúry — ' . APP_NAME,
]);
