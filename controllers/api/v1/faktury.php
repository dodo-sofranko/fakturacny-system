<?php
// GET /api/v1/faktury?rok=2026&odberatel_id=1
$where = []; $params = [];
if (!empty($_GET['rok']))          { $where[] = 'YEAR(f.datum_vystavenia) = ?'; $params[] = (int)$_GET['rok']; }
if (!empty($_GET['odberatel_id'])) { $where[] = 'f.odberatel_id = ?';          $params[] = (int)$_GET['odberatel_id']; }
$rows = DB::fetchAll(
    "SELECT f.id, f.cislo_faktury, f.odberatel_id, o.nazov AS odberatel, f.datum_vystavenia, f.datum_splatnosti,
            f.celkova_suma, f.zaloha_suma, f.peppol_status
     FROM faktury f JOIN odberatelia o ON o.id = f.odberatel_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
    " ORDER BY f.datum_vystavenia DESC, f.cislo_faktury DESC",
    $params
);
Api::json(['faktury' => array_map([Faktury::class, 'cast'], $rows)]);
