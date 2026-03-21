<?php
$id = (int)($_GET['id'] ?? 0);
// Skontrolujeme či má faktúry
$count = DB::fetch("SELECT COUNT(*) as cnt FROM faktury WHERE odberatel_id = ?", [$id]);
if ($count && $count['cnt'] > 0) {
    redirect('/odberatelia', 'Odberateľa nie je možné vymazať — má ' . $count['cnt'] . ' faktúr.', 'error');
}
DB::delete('odberatelia', 'id = ?', [$id]);
redirect('/odberatelia', 'Odberateľ bol vymazaný.');
