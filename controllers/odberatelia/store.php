<?php
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

if (empty($data['nazov'])) {
    redirect('/odberatelia/create', 'Názov odberateľa je povinný.', 'error');
}

DB::insert('odberatelia', $data);
redirect('/odberatelia', 'Odberateľ bol pridaný.');
