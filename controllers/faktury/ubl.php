<?php
// Stiahnutie e-faktúry (UBL 2.1, Peppol BIS 3.0) – na kontrolu alebo ručné nahratie u poštára
$id = (int)($_GET['id'] ?? 0);

$sent = DB::fetch("SELECT cislo_faktury, peppol_xml FROM faktury WHERE id = ?", [$id]);
if (!empty($sent['peppol_xml'])) {
    $u = ['xml' => $sent['peppol_xml'], 'cislo' => $sent['cislo_faktury']]; // odoslaný originál
} else {
    try {
        $u = UblInvoice::build($id);
    } catch (RuntimeException $e) {
        redirect('/faktury', $e->getMessage(), 'error');
    }
}

$filename = 'Faktura_' . preg_replace('/[^a-zA-Z0-9]/', '', $u['cislo']) . '.xml';
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $u['xml'];
exit;
