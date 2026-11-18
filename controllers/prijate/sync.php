<?php
// Stiahne nové e-faktúry od poštára, uloží ich a potvrdí prevzatie
$d = DB::fetch("SELECT dic FROM dodavatel WHERE id = 1");
$participant = UblInvoice::participantId($d['dic'] ?? '');
if (!$participant) redirect('/prijate', 'V „Môj účet“ chýba platné DIČ.', 'error');

try {
    $nove = PrijataFaktura::sync(new SapiClient($participant));
} catch (RuntimeException $e) {
    redirect('/prijate', 'Sťahovanie zlyhalo: ' . $e->getMessage(), 'error');
}

redirect('/prijate', $nove ? "Stiahnuté nové e-faktúry: $nove." : 'Žiadne nové e-faktúry.');
