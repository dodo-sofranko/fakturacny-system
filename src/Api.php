<?php

/**
 * JSON API (/api/v1/*) – prístup tokenom namiesto basic auth.
 * Na serveri je v .env len hash tokenu: API_TOKEN_HASH=sha256(token) v hex.
 * Token sa posiela ako `Authorization: Bearer <token>` alebo `X-Api-Key: <token>`.
 */
class Api
{
    public static function isApiRequest(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        return str_starts_with($path, BASE_PATH . '/api/v1/');
    }

    public static function authenticate(): void
    {
        $hash = (string)env('API_TOKEN_HASH', '');
        if ($hash === '') self::error(503, 'API nie je zapnuté (chýba API_TOKEN_HASH v .env).');

        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = stripos($h, 'Bearer ') === 0 ? trim(substr($h, 7)) : trim($_SERVER['HTTP_X_API_KEY'] ?? '');
        if ($token === '' || !hash_equals(strtolower($hash), hash('sha256', $token))) {
            self::error(401, 'Neplatný alebo chýbajúci API token.');
        }
    }

    /** JSON telo požiadavky. */
    public static function input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) return [];
        $data = json_decode($raw, true);
        if (!is_array($data)) self::error(400, 'Telo musí byť JSON objekt.');
        return $data;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(int $code, string $message, array $extra = []): never
    {
        self::json(['error' => $message] + $extra, $code);
    }
}
