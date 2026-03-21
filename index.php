<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/DB.php';
require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/PayBySquare.php';
require_once __DIR__ . '/vendor/autoload.php';

$router = new Router(BASE_PATH);

// Faktúry
$router->get('/',                        'faktury/index');
$router->get('/faktury',                 'faktury/index');
$router->get('/faktury/create',          'faktury/create');
$router->post('/faktury/store',          'faktury/store');
$router->get('/faktury/{id}/edit',       'faktury/edit');
$router->post('/faktury/{id}/update',    'faktury/update');
$router->post('/faktury/{id}/delete',    'faktury/delete');
$router->get('/faktury/{id}/pdf',        'faktury/pdf');
$router->get('/faktury/{id}/copy',       'faktury/copy');

// Odberatelia
$router->get('/odberatelia',              'odberatelia/index');
$router->get('/odberatelia/create',       'odberatelia/create');
$router->post('/odberatelia/store',       'odberatelia/store');
$router->get('/odberatelia/{id}/edit',    'odberatelia/edit');
$router->post('/odberatelia/{id}/update', 'odberatelia/update');
$router->post('/odberatelia/{id}/delete', 'odberatelia/delete');

// Dodávateľ
$router->get('/dodavatel',               'dodavatel/edit');
$router->post('/dodavatel/update',       'dodavatel/update');
$router->get('/dodavatel/podpis-img',    'dodavatel/podpis_img');

// API
$router->get('/api/suggestions',         'api/suggestions');
$router->get('/api/odberatelia',         'api/odberatelia');

$router->dispatch();
