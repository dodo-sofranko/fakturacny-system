<?php
/**
 * Zostaví balík na nahratie na hosting (fakturacia.nakodim.sk) + dump databázy.
 *
 *   AUTH_PASS=heslo php deploy/package.php   (heslo treba len pri prvom builde, potom sa .env zachová)
 *
 * Výstup:
 *   build/fakturacia-deploy/   – obsah nahrať cez FTP do sub/fakturacia/ (vrátane .htaccess a .env)
 *   build/fakturacia-db.sql    – importovať do DB na hostingu (phpMyAdmin)
 *
 * .env v balíku má prázdne DB údaje – doplniť pred nahratím (alebo na serveri).
 * Existujúci build/fakturacia-deploy/.env sa pri ďalšom behu zachová.
 */
declare(strict_types=1);

$root  = dirname(__DIR__);
$build = $root . '/build';
$out   = $build . '/fakturacia-deploy';

function run(string $cmd): void
{
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Zlyhalo: $cmd\n");
        exit(1);
    }
}

function copyDir(string $src, string $dst): void
{
    @mkdir($dst, 0755, true);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        if ($f->getFilename() === '.DS_Store') continue;
        $target = $dst . '/' . $it->getSubPathname();
        $f->isDir() ? @mkdir($target, 0755, true) : copy($f->getPathname(), $target);
    }
}

// Zachovať už vyplnený .env z predchádzajúceho buildu
$oldEnv = is_file("$out/.env") ? file_get_contents("$out/.env") : null;
if (is_dir($out)) run('rm -rf ' . escapeshellarg($out));
@mkdir($out, 0755, true);

// 1) CSS build
run('cd ' . escapeshellarg($root) . ' && npm run build --silent');

// 2) Zdrojáky
foreach (['index.php', 'config.php'] as $f) copy("$root/$f", "$out/$f");
foreach (['src', 'controllers', 'views', 'public'] as $d) copyDir("$root/$d", "$out/$d");
copy("$root/deploy/.htaccess", "$out/.htaccess");
@mkdir("$out/storage", 0755, true);
file_put_contents("$out/storage/.htaccess", "Require all denied\n");

// 3) Vendor len s tým, čo appka reálne používa (bez mpdf/tcpdf → ~12 MB namiesto 139 MB)
$composer = json_decode(file_get_contents("$root/composer.json"), true);
$composer['require'] = array_intersect_key($composer['require'], array_flip(['dompdf/dompdf', 'endroid/qr-code']));
$composer['config']['platform']['php'] = '8.2.0';
file_put_contents("$out/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
run('cd ' . escapeshellarg($out) . ' && composer update --no-dev --optimize-autoloader --no-interaction --quiet');
unlink("$out/composer.json");
unlink("$out/composer.lock");

// 4) .env
if ($oldEnv !== null) {
    file_put_contents("$out/.env", $oldEnv);
} else {
    $pass = getenv('AUTH_PASS');
    if (!$pass) {
        fwrite(STDERR, "Prvý build: zadaj heslo do appky cez AUTH_PASS=… php deploy/package.php\n");
        exit(1);
    }
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    file_put_contents("$out/.env", "# Databáza – doplniť údaje z Websupport admina (Databázy)
DB_HOST=
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASS=
DB_CHARSET=utf8mb4

# Aplikácia
APP_NAME=Fakturácia
APP_URL=https://fakturacia.nakodim.sk
BASE_PATH=
APP_DEBUG=false
APP_SECRET=" . bin2hex(random_bytes(32)) . "

# Prihlásenie (basic auth) – heslo je bcrypt hash
AUTH_USER=dodo
AUTH_PASS_HASH=$hash
");
}

// 5) Dump lokálnej DB (bez CREATE DATABASE/USE – na hostingu má DB iný názov)
require "$root/config.php";
$sql = "$build/fakturacia-db.sql";
putenv('MYSQL_PWD=' . DB_PASS);
run(sprintf(
    'mysqldump -h %s -P %d -u %s --single-transaction --hex-blob --no-tablespaces --skip-add-locks --default-character-set=utf8mb4 %s > %s',
    escapeshellarg(DB_HOST), DB_PORT, escapeshellarg(DB_USER), escapeshellarg(DB_NAME), escapeshellarg($sql)
));
putenv('MYSQL_PWD');

$size = trim(shell_exec('du -sh ' . escapeshellarg($out) . ' | cut -f1'));
$files = (int)trim(shell_exec('find ' . escapeshellarg($out) . ' -type f | wc -l'));
echo "Balík: $out ($size, $files súborov)\nDB dump: $sql (" . round(filesize($sql) / 1024) . " kB)\n";
