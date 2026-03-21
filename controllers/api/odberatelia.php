<?php
header('Content-Type: application/json');
$odberatelia = DB::fetchAll("SELECT id, nazov FROM odberatelia ORDER BY nazov");
echo json_encode($odberatelia, JSON_UNESCAPED_UNICODE);
