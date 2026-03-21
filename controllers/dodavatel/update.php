<?php
$data = [
    'nazov'       => trim($_POST['nazov'] ?? ''),
    'ulica'       => trim($_POST['ulica'] ?? ''),
    'mesto'       => trim($_POST['mesto'] ?? ''),
    'psc'         => trim($_POST['psc'] ?? ''),
    'ico'         => trim($_POST['ico'] ?? ''),
    'dic'         => trim($_POST['dic'] ?? ''),
    'ic_dph'      => trim($_POST['ic_dph'] ?? ''),
    'dph_platca'  => isset($_POST['dph_platca']) ? 1 : 0,
    'iban'        => trim($_POST['iban'] ?? ''),
    'swift'       => trim($_POST['swift'] ?? ''),
    'banka'       => trim($_POST['banka'] ?? ''),
    'email'       => trim($_POST['email'] ?? ''),
    'telefon'     => trim($_POST['telefon'] ?? ''),
    'podpis_text' => trim($_POST['podpis_text'] ?? ''),
];

// Spracovanie uploadu podpisu
$file = $_FILES['podpis_png'] ?? null;
if ($file && $file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
    $allowed = ['image/png', 'image/jpeg', 'image/webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (in_array($mime, $allowed)) {
        $data['podpis_png'] = file_get_contents($file['tmp_name']);
    }
}

DB::update('dodavatel', $data, 'id = 1');
redirect('/dodavatel', 'Údaje dodávateľa boli uložené.');
