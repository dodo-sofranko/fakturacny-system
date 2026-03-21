<?php
$odberatelia = DB::fetchAll("SELECT * FROM odberatelia ORDER BY nazov");
view('odberatelia/index', ['odberatelia' => $odberatelia, 'pageTitle' => 'Odberatelia — ' . APP_NAME]);
