<?php
$odberatelId = isset($_GET['odberatel_id']) ? (int)$_GET['odberatel_id'] : null;

$where = '';
$params = [];
if ($odberatelId) {
    $where = 'WHERE f.odberatel_id = ?';
    $params[] = $odberatelId;
}

$faktury = DB::fetchAll(
    "SELECT f.*, o.nazov AS odberatel_nazov
     FROM faktury f
     JOIN odberatelia o ON o.id = f.odberatel_id
     $where
     ORDER BY f.datum_vystavenia DESC, f.cislo_faktury DESC",
    $params
);

// Zoradenie podľa roka
$podlaRoku = [];
foreach ($faktury as $f) {
    $rok = substr($f['datum_vystavenia'], 0, 4);
    $podlaRoku[$rok][] = $f;
}

$odberatelia = DB::fetchAll("SELECT id, nazov FROM odberatelia ORDER BY nazov");

view('faktury/index', [
    'podlaRoku'   => $podlaRoku,
    'odberatelia' => $odberatelia,
    'filterOd'    => $odberatelId,
    'pageTitle'   => 'Faktúry — ' . APP_NAME,
]);
