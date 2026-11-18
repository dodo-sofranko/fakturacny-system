<?php
// GET /api/v1/faktury/{id}/pdf
$id = (int)($_GET['id'] ?? 0);
$f = DB::fetch("SELECT cislo_faktury, pdf_data FROM faktury WHERE id = ?", [$id]);
if (!$f) Api::error(404, 'Faktúra nenájdená.');
if (!$f['pdf_data']) {
    require_once __DIR__ . '/../../../src/PdfGenerator.php';
    PdfGenerator::generateAndSave($id);
    $f = DB::fetch("SELECT cislo_faktury, pdf_data FROM faktury WHERE id = ?", [$id]);
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Faktura_' . preg_replace('/[^a-zA-Z0-9]/', '', $f['cislo_faktury']) . '.pdf"');
header('Content-Length: ' . strlen($f['pdf_data']));
echo $f['pdf_data'];
exit;
