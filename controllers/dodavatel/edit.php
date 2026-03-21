<?php
$dodavatel = DB::fetch("SELECT * FROM dodavatel WHERE id = 1");
view('dodavatel/form', ['dodavatel' => $dodavatel, 'pageTitle' => 'Môj účet — ' . APP_NAME]);
