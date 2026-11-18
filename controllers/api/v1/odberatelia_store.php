<?php
// POST /api/v1/odberatelia  {nazov*, ulica, mesto, psc, stat, ico, dic, ic_dph, email, telefon}
$in = Api::input();
$data = ['nazov' => '', 'ulica' => '', 'mesto' => '', 'psc' => '', 'stat' => 'Slovenská republika', 'ico' => '', 'dic' => '', 'ic_dph' => '', 'email' => '', 'telefon' => ''];
foreach ($data as $k => $def) $data[$k] = trim((string)($in[$k] ?? $def));
if ($data['nazov'] === '') Api::error(422, 'Názov odberateľa je povinný.');

$ico = preg_replace('/\D/', '', $data['ico']);
if ($ico !== '') {
    foreach (DB::fetchAll("SELECT id, nazov, ico FROM odberatelia") as $o) {
        if (preg_replace('/\D/', '', (string)$o['ico']) === $ico) Api::error(409, 'Odberateľ s týmto IČO už existuje.', ['id' => (int)$o['id'], 'nazov' => $o['nazov']]);
    }
}
$id = DB::insert('odberatelia', $data);
Api::json(['id' => $id] + $data + ['peppol_id' => UblInvoice::participantId($data['dic'], $data['ic_dph'])], 201);
