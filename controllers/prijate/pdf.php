<?php
// Prijatá e-faktúra ako PDF: pribalené PDF dodávateľa, inak náhľad vytvorený z XML
$id = (int)($_GET['id'] ?? 0);
$f = DB::fetch("SELECT cislo, document_id, xml FROM prijate_efaktury WHERE id = ?", [$id]);
if (!$f) {
    http_response_code(404);
    die('E-faktúra nenájdená.');
}

try {
    $r = PrijataPdf::render($f['xml']);
} catch (RuntimeException $e) {
    redirect('/prijate', $e->getMessage(), 'error');
}

$name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($f['cislo'] ?: $f['document_id'])) ?: 'efaktura';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Prijata_' . $name . '.pdf"');
header('Content-Length: ' . strlen($r['pdf']));
header('Cache-Control: no-store');
echo $r['pdf'];
exit;
