<?php
$id = (int)($_GET['id'] ?? 0);
$data = [
    'nazov'  => trim($_POST['nazov'] ?? ''),
    'ulica'  => trim($_POST['ulica'] ?? ''),
    'mesto'  => trim($_POST['mesto'] ?? ''),
    'psc'    => trim($_POST['psc'] ?? ''),
    'stat'   => trim($_POST['stat'] ?? 'Slovenská republika'),
    'ico'    => trim($_POST['ico'] ?? ''),
    'dic'    => trim($_POST['dic'] ?? ''),
    'ic_dph' => trim($_POST['ic_dph'] ?? ''),
    'email'  => trim($_POST['email'] ?? ''),
    'telefon'=> trim($_POST['telefon'] ?? ''),
];
DB::update('odberatelia', $data, 'id = ?', [$id]);
redirect('/odberatelia', 'Odberateľ bol uložený.');
