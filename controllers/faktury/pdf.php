<?php
$id = (int)($_GET['id'] ?? 0);
$faktura = DB::fetch("SELECT cislo_faktury, pdf_data, pdf_generated_at FROM faktury WHERE id = ?", [$id]);

if (!$faktura) {
    http_response_code(404);
    die('Faktúra nenájdená.');
}

// Ak PDF nie je vygenerované alebo je vyžiadaná regenerácia
if (!$faktura['pdf_data'] || isset($_GET['regenerate'])) {
    require_once __DIR__ . '/../../src/PdfGenerator.php';
    PdfGenerator::generateAndSave($id);
    $faktura = DB::fetch("SELECT cislo_faktury, pdf_data FROM faktury WHERE id = ?", [$id]);
}

$filename = 'Faktura_' . preg_replace('/[^a-zA-Z0-9]/', '', $faktura['cislo_faktury']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($faktura['pdf_data']));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $faktura['pdf_data'];
exit;
