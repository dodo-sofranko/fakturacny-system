<?php
header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}
$results = DB::fetchAll(
    "SELECT nazov, posledna_cena FROM polozky_suggestions WHERE nazov LIKE ? ORDER BY pouziti_count DESC LIMIT 10",
    ['%' . $q . '%']
);
echo json_encode($results, JSON_UNESCAPED_UNICODE);
