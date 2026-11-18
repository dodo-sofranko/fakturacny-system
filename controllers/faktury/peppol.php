<?php
// Odoslanie faktúry cez digitálneho poštára (Peppol, SAPI-SK)
$r = Odoslanie::odoslat((int)($_GET['id'] ?? 0));
redirect('/faktury', $r['message'], $r['status'] === 'ACCEPTED' ? 'success' : 'error');
