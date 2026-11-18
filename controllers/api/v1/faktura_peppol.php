<?php
// POST /api/v1/faktury/{id}/peppol – odoslanie e-faktúry cez poštára (stojí 0,10 €)
$id = (int)($_GET['id'] ?? 0);
if (!DB::fetch("SELECT id FROM faktury WHERE id = ?", [$id])) Api::error(404, 'Faktúra nenájdená.');
if (!SapiClient::isConfigured()) Api::error(503, 'Poštár nie je nastavený (PEPPOL_* v .env).');
$r = Odoslanie::odoslat($id);
Api::json($r, $r['status'] === 'ACCEPTED' ? 200 : ($r['status'] === 'UNDELIVERABLE' ? 202 : 422));
