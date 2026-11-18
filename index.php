<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/DB.php';
require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/PayBySquare.php';
require_once __DIR__ . '/vendor/autoload.php';

// Basic auth (na hostingu) — zapne sa, keď je v .env AUTH_USER a AUTH_PASS_HASH (password_hash)
if (env('AUTH_USER') && env('AUTH_PASS_HASH')) {
    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
    // PHP-FPM hlavičku nedáva do PHP_AUTH_*, .htaccess ju posiela ako HTTP_AUTHORIZATION
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($user === null && stripos($header, 'Basic ') === 0) {
        [$user, $pass] = array_pad(explode(':', (string)base64_decode(substr($header, 6)), 2), 2, '');
    }
    if (!$user || !hash_equals(env('AUTH_USER'), $user) || !password_verify((string)$pass, env('AUTH_PASS_HASH'))) {
        header('WWW-Authenticate: Basic realm="Fakturacia", charset="UTF-8"');
        http_response_code(401);
        exit('Prístup zamietnutý.');
    }
}

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
