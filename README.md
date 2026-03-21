# Fakturácia

Jednoduchá fakturačná aplikácia pre živnostníkov. PHP bez frameworku, MySQL, Tailwind CSS v4, Alpine.js.

## Požiadavky

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.4+
- Composer
- Node.js 18+ (na build CSS)
- Webserver s podporou rewrite (Apache / Caddy / Nginx)

## Inštalácia

### 1. Závislosti

```bash
composer install
npm install
```

### 2. Databáza

```bash
mysql -u root -p < database/schema.sql
```

### 3. Konfigurácia

Skopíruj ukážkový env a uprav podľa svojho prostredia:

```bash
cp .env.example .env
```

Uprav `.env`:

```
DB_HOST=127.0.0.1
DB_NAME=fakturacia
DB_USER=root
DB_PASS=heslo

APP_URL=http://fakturacia.localhost
APP_SECRET=zmen-na-dlhy-nahodny-retazec-aspon-32-znakov
```

### 4. Build CSS

```bash
# jednorazový build
npm run build

# alebo watch počas vývoja
npm run dev
```

### 5. Webserver

**Caddy** — príklad pre lokálny vývoj:

```
fakturacia.localhost {
    root * /cesta/k/fakturacia
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
    try_files {path} /index.php
}
```

**Apache** — `.htaccess` musí presmerovať všetky requesty na `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

## Štruktúra projektu

```
├── controllers/        # Route handlery (faktury, odberatelia, dodavatel, api)
├── database/
│   └── schema.sql      # Štruktúra databázy
├── public/
│   └── css/app.css     # Buildnutý CSS (generovaný, nie v gite)
├── src/
│   ├── css/app.css     # Zdrojový CSS (Tailwind)
│   ├── DB.php          # PDO databázová vrstva
│   ├── Router.php      # Router + helper funkcie
│   ├── PdfGenerator.php
│   └── PayBySquare.php
├── storage/            # Dočasné súbory (prázdny, v gite len .gitkeep)
├── views/              # PHP šablóny
├── config.php          # Konfigurácia (nie je v gite)
└── index.php           # Vstupný bod
```

## Vývoj

Po každej zmene CSS treba spustiť build (alebo mať bežiaci `npm run dev`):

```bash
npm run build
```
