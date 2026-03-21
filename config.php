<?php

// Načítame .env súbor
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die('.env súbor neexistuje. Skopíruj .env.example do .env a nastav hodnoty.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

// --- Databáza ---
define('DB_HOST',    env('DB_HOST', '127.0.0.1'));
define('DB_PORT',    (int) env('DB_PORT', 3306));
define('DB_NAME',    env('DB_NAME', 'fakturacia'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// --- Aplikácia ---
define('APP_NAME',   env('APP_NAME', 'Fakturácia'));
define('APP_URL',    rtrim(env('APP_URL', ''), '/'));
define('BASE_PATH',  env('BASE_PATH', ''));
define('APP_DEBUG',  filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('APP_SECRET', env('APP_SECRET', ''));
