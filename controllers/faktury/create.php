<?php
$dodavatel = DB::fetch("SELECT * FROM dodavatel WHERE id = 1");
$odberatelia = DB::fetchAll("SELECT id, nazov FROM odberatelia ORDER BY nazov");
$nextNumber = nextInvoiceNumber();

$today = date('Y-m-d');
$splatnost = date('Y-m-d', strtotime('+14 days'));

view('faktury/form', [
    'faktura'     => null,
    'polozky'     => [],
    'dodavatel'   => $dodavatel,
    'odberatelia' => $odberatelia,
    'nextNumber'  => $nextNumber,
    'today'       => $today,
    'splatnost'   => $splatnost,
    'pageTitle'   => 'Nová faktúra — ' . APP_NAME,
]);
