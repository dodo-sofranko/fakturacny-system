<?php
$row = DB::fetch("SELECT podpis_png FROM dodavatel WHERE id = 1");
if (empty($row['podpis_png'])) {
    http_response_code(404);
    exit;
}
// Zistíme MIME typ
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($row['podpis_png']) ?: 'image/png';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=60');
echo $row['podpis_png'];
exit;
