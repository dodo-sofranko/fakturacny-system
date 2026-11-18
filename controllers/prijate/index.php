<?php
$faktury = DB::fetchAll(
    "SELECT id, document_id, odosielatel_id, typ, cislo, dodavatel, dodavatel_ico, datum_vystavenia,
            datum_splatnosti, suma, mena, prijate_at
     FROM prijate_efaktury ORDER BY prijate_at DESC, id DESC"
);

view('prijate/index', [
    'faktury'   => $faktury,
    'peppolOn'  => SapiClient::isConfigured(),
    'pageTitle' => 'Prijaté e-faktúry — ' . APP_NAME,
]);
