<?php
// GET /api/v1/faktury/{id}
$f = Faktury::detail((int)($_GET['id'] ?? 0));
$f ? Api::json($f) : Api::error(404, 'Faktúra nenájdená.');
