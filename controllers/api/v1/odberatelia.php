<?php
// GET /api/v1/odberatelia
$rows = DB::fetchAll("SELECT id, nazov, ulica, mesto, psc, stat, ico, dic, ic_dph, email, telefon FROM odberatelia ORDER BY nazov");
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['peppol_id'] = UblInvoice::participantId($r['dic'], $r['ic_dph']);
}
Api::json(['odberatelia' => $rows]);
