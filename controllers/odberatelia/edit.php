<?php
$id = (int)($_GET['id'] ?? 0);
$odberatel = DB::fetch("SELECT * FROM odberatelia WHERE id = ?", [$id]);
if (!$odberatel) redirect('/odberatelia', 'Odberateľ nenájdený.', 'error');
view('odberatelia/form', ['odberatel' => $odberatel, 'pageTitle' => 'Upraviť odberateľa — ' . APP_NAME]);
