<?php
// Odoslanie faktúry cez digitálneho poštára (Peppol, SAPI-SK)
$id = (int)($_GET['id'] ?? 0);

try {
    $u = UblInvoice::build($id);
    $client = new SapiClient($u['sender']);
    // Rovnaký obsah = rovnaký kľúč → opakované kliknutie faktúru nepošle dvakrát; po úprave faktúry vznikne nový kľúč
    $key = 'fakturacia-' . $id . '-' . substr(hash('sha256', $u['xml']), 0, 16);
    $res = $client->sendInvoice($u['xml'], $u['cislo'], $u['receiver'], $key);
} catch (RuntimeException $e) {
    DB::update('faktury', ['peppol_status' => 'ERROR', 'peppol_detail' => mb_substr($e->getMessage(), 0, 2000)], 'id = ?', [$id]);
    redirect('/faktury', 'E-faktúra neodoslaná: ' . $e->getMessage(), 'error');
}

$status = ($res['status'] ?? '') === 'ACCEPTED' ? (!empty($res['undeliverable']) ? 'UNDELIVERABLE' : 'ACCEPTED') : 'REJECTED';

DB::update('faktury', [
    'peppol_status'      => $status,
    'peppol_document_id' => $res['providerDocumentId'] ?? null,
    'peppol_sent_at'     => date('Y-m-d H:i:s'),
    'peppol_xml'         => $status === 'REJECTED' ? null : $u['xml'], // originál e-faktúry na archiváciu
    'peppol_detail'      => isset($res['detail']) ? mb_substr((string)$res['detail'], 0, 2000) : null,
], 'id = ?', [$id]);

match ($status) {
    'ACCEPTED'      => redirect('/faktury', 'E-faktúra ' . $u['cislo'] . ' odoslaná cez Peppol (' . $u['receiver'] . ').'),
    'UNDELIVERABLE' => redirect('/faktury', 'Poštár faktúru ' . $u['cislo'] . ' prevzal, ale odberateľ ' . $u['receiver'] . ' ešte nie je v sieti Peppol – nedoručí sa, pošli mu PDF.', 'error'),
    default         => redirect('/faktury', 'Poštár faktúru odmietol: ' . ($res['detail'] ?? 'bez dôvodu'), 'error'),
};
