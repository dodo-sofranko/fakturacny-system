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

## Nasadenie na hosting (fakturacia.nakodim.sk, Websupport)

```bash
AUTH_PASS=heslo php deploy/package.php   # heslo len pri prvom builde, .env sa potom zachová
```

- `build/fakturacia-deploy/` → nahrať cez FTP do `sub/fakturacia/` (aj skryté `.htaccess` a `.env`).
- `build/fakturacia-db.sql` → importovať do DB na hostingu (phpMyAdmin), DB údaje doplniť do `.env`.
- Prihlásenie (basic auth) robí `index.php` podľa `AUTH_USER` / `AUTH_PASS_HASH` v `.env`; `deploy/.htaccess` posiela hlavičku Authorization do PHP a blokuje neverejné priečinky.
- Do balíka ide len dompdf + endroid/qr-code (mpdf/tcpdf appka nepoužíva). PHP na hostingu 8.2+.
- QR „PAY by square" potrebuje na serveri `xz` cez `shell_exec`; bez neho sa vygeneruje náhradný EPC QR.

## E-faktúry cez Peppol (od 1.1.2027)

- Pri každej faktúre je ikona `code` = stiahnutie e-faktúry (UBL 2.1, Peppol BIS Billing 3.0, kategória DPH „O“ – neplatiteľ).
- Keď sú v `.env` vyplnené `PEPPOL_API_URL`, `PEPPOL_CLIENT_ID`, `PEPPOL_CLIENT_SECRET`, pribudne ikona `send` = odoslanie cez digitálneho poštára (štandard SAPI-SK, funguje s každým poštárom). Stav sa ukladá do `faktury.peppol_*`.
- Odoslaná e-faktúra sa ukladá do `faktury.peppol_xml` (originál na archiváciu 10 rokov); ikona `code` potom vracia presne tento originál.
- Do odosielanej e-faktúry sa pribalí PDF faktúry (AdditionalDocumentReference) – odberateľ vidí náš vzhľad aj QR.
- Menu **Prijaté** → „Stiahnuť nové“: stiahne čakajúce e-faktúry od poštára do `prijate_efaktury` (celé XML + údaje na prehľad) a potvrdí im prevzatie. Klik na číslo = PDF: pribalené PDF dodávateľa, inak náhľad vytvorený z XML (`src/Peppol/PrijataPdf.php`).
- Ostrý poštár: ePošťák, `PEPPOL_API_URL=https://epostak.sk/sapi/v1`; prístup je vo vaulte `fakturacia/epostak-api` vo formáte `client_id:secret`.
- Peppol ID = `0245:` + DIČ (10 číslic). Odberateľ musí mať DIČ alebo slovenské IČ DPH.
- Test bez registrácie: `PEPPOL_API_URL=https://peppol.verteco.digital/sapi`, ID aj secret `sandbox` (len validácia, nič sa nedoručí).
- Na hostingu pred nasadením spustiť migráciu z konca `database/schema.sql`.

## JSON API (`/api/v1`)

Prístup tokenom (`Authorization: Bearer <token>` alebo `X-Api-Key`), basic auth webu sa naň nevzťahuje. Na serveri je v `.env` len `API_TOKEN_HASH` (sha256 hex tokenu); bez neho API vracia 503. Token je vo vaulte `fakturacia/api-token`.

| Metóda | Cesta | Popis |
|---|---|---|
| GET | `/api/v1/odberatelia` | zoznam odberateľov (aj `peppol_id`) |
| POST | `/api/v1/odberatelia` | nový odberateľ `{nazov*, ulica, mesto, psc, ico, dic, ic_dph, email, …}` (409 pri rovnakom IČO) |
| GET | `/api/v1/faktury?rok=&odberatel_id=` | zoznam faktúr |
| GET | `/api/v1/faktury/dalsie-cislo` | ďalšie voľné číslo |
| GET | `/api/v1/faktury/{id}` | detail s položkami |
| POST | `/api/v1/faktury` | nová faktúra (nižšie); `"dry_run": true` = len kontrola a náhľad |
| GET | `/api/v1/faktury/{id}/pdf` | PDF |
| POST | `/api/v1/faktury/{id}/peppol` | odoslanie e-faktúry cez poštára (0,10 €) |

```json
{
  "odberatel_ico": "55246125",            // alebo "odberatel_id": 2
  "polozky": [{"nazov": "Tvorba webu", "mnozstvo": 2.5, "jednotka": "h", "jednotkova_cena": 20}],
  "datum_vystavenia": "2026-11-30",       // voliteľné, default dnes
  "datum_dodania": "2026-11-30",          // default = vystavenie
  "splatnost_dni": 14,                    // alebo "datum_splatnosti"
  "poznamka": "…",                        // voliteľné; cislo_faktury a variabilny_symbol sa dopočítajú
  "zaloha": {"suma": 100, "datum": "2026-11-01", "cislo": "ZF2026001"}   // voliteľné
}
```
Chyby vstupu: HTTP 422 `{"error", "chyby": {pole: text}}`.
