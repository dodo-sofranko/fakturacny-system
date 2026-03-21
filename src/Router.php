<?php

class Router
{
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, string $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, string $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($this->basePath && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }

        $uri = '/' . trim($uri, '/');
        if ($uri === '/') $uri = '/';

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) continue;
            $params = $this->match($routePath, $uri);
            if ($params !== null) {
                foreach ($params as $key => $value) {
                    $_GET[$key] = $value;
                }
                $this->load($handler);
                return;
            }
        }

        http_response_code(404);
        $this->load('404');
    }

    private function match(string $routePath, string $uri): ?array
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) return null;
        return array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
    }

    private function load(string $handler): void
    {
        $file = __DIR__ . '/../controllers/' . $handler . '.php';
        if (!file_exists($file)) {
            $viewFile = __DIR__ . '/../views/' . $handler . '.php';
            if (file_exists($viewFile)) {
                self::render($viewFile);
                return;
            }
            http_response_code(404);
            self::render(__DIR__ . '/../views/404.php');
            return;
        }
        require $file;
    }

    public static function render(string $viewFile, array $vars = []): void
    {
        extract($vars);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require __DIR__ . '/../views/layout.php';
    }
}

// ─── Cookie flash helpers ─────────────────────────────────────────────────────

function _cookie_sign(string $value): string
{
    return hash_hmac('sha256', $value, APP_SECRET) . '|' . $value;
}

function _cookie_verify(string $signed): ?string
{
    $pos = strpos($signed, '|');
    if ($pos === false) return null;
    $mac = substr($signed, 0, $pos);
    $value = substr($signed, $pos + 1);
    if (!hash_equals(hash_hmac('sha256', $value, APP_SECRET), $mac)) return null;
    return $value;
}

function _set_flash(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $signed = _cookie_sign($json);
    setcookie('fakt_flash', $signed, [
        'expires'  => time() + 60,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function _get_flash(): ?array
{
    $signed = $_COOKIE['fakt_flash'] ?? null;
    if (!$signed) return null;
    $json = _cookie_verify($signed);
    if (!$json) return null;
    setcookie('fakt_flash', '', ['expires' => time() - 3600, 'path' => '/']);
    return json_decode($json, true);
}

function _set_old(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $signed = _cookie_sign($json);
    setcookie('fakt_old', $signed, [
        'expires'  => time() + 300,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function _get_old(): array
{
    $signed = $_COOKIE['fakt_old'] ?? null;
    if (!$signed) return [];
    $json = _cookie_verify($signed);
    if (!$json) return [];
    return json_decode($json, true) ?? [];
}

// ─── Public helpers ───────────────────────────────────────────────────────────

function view(string $viewPath, array $vars = []): void
{
    $file = __DIR__ . '/../views/' . $viewPath . '.php';
    Router::render($file, $vars);
}

function redirect(string $path, ?string $flash = null, string $flashType = 'success'): never
{
    if ($flash) {
        _set_flash(['msg' => $flash, 'type' => $flashType]);
    }
    header('Location: ' . APP_URL . BASE_PATH . $path);
    exit;
}

function old(string $key, mixed $default = ''): mixed
{
    return _get_old()[$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function flash(): ?array
{
    return _get_flash();
}

function formatMoney(float $amount): string
{
    return number_format($amount, 2, ',', ' ');
}

function nextInvoiceNumber(): string
{
    $year = date('Y');
    $last = DB::fetch(
        "SELECT cislo_faktury FROM faktury WHERE cislo_faktury LIKE ? ORDER BY cislo_faktury DESC LIMIT 1",
        [$year . '%']
    );
    if ($last) {
        $seq = (int) substr($last['cislo_faktury'], 4) + 1;
    } else {
        $seq = 1;
    }
    return $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
}
