<?php
$id = (int)($_GET['id'] ?? 0);
$f = DB::fetch("SELECT cislo, document_id, xml FROM prijate_efaktury WHERE id = ?", [$id]);
if (!$f) {
    http_response_code(404);
    die('E-faktúra nenájdená.');
}

$name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($f['cislo'] ?: $f['document_id'])) ?: 'efaktura';
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="Prijata_' . $name . '.xml"');
header('Cache-Control: no-store');
echo $f['xml'];
exit;
