<?php
$id = (int)($_GET['id'] ?? 0);
DB::delete('faktury', 'id = ?', [$id]);
redirect('/faktury', 'Faktúra bola vymazaná.');
