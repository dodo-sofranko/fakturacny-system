CREATE DATABASE IF NOT EXISTS fakturacia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fakturacia;

-- Dodávateľ (vaše vlastné údaje - jeden riadok)
CREATE TABLE IF NOT EXISTS dodavatel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nazov VARCHAR(255) NOT NULL DEFAULT '',
    ulica VARCHAR(255) DEFAULT '',
    mesto VARCHAR(100) DEFAULT '',
    psc VARCHAR(10) DEFAULT '',
    ico VARCHAR(20) DEFAULT '',
    dic VARCHAR(20) DEFAULT '',
    ic_dph VARCHAR(20) DEFAULT '',
    dph_platca TINYINT(1) DEFAULT 0,
    iban VARCHAR(34) DEFAULT '',
    swift VARCHAR(11) DEFAULT '',
    banka VARCHAR(100) DEFAULT '',
    email VARCHAR(150) DEFAULT '',
    telefon VARCHAR(30) DEFAULT '',
    podpis_text VARCHAR(255) DEFAULT '',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vložíme počiatočný riadok ak neexistuje
INSERT INTO dodavatel (id, nazov) VALUES (1, 'Moje meno')
ON DUPLICATE KEY UPDATE id = id;

-- Odberatelia (klienti)
CREATE TABLE IF NOT EXISTS odberatelia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nazov VARCHAR(255) NOT NULL,
    ulica VARCHAR(255) DEFAULT '',
    mesto VARCHAR(100) DEFAULT '',
    psc VARCHAR(10) DEFAULT '',
    stat VARCHAR(100) DEFAULT 'Slovenská republika',
    ico VARCHAR(20) DEFAULT '',
    dic VARCHAR(20) DEFAULT '',
    ic_dph VARCHAR(20) DEFAULT '',
    email VARCHAR(150) DEFAULT '',
    telefon VARCHAR(30) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Faktúry
CREATE TABLE IF NOT EXISTS faktury (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cislo_faktury VARCHAR(20) NOT NULL UNIQUE,
    odberatel_id INT NOT NULL,
    datum_vystavenia DATE NOT NULL,
    datum_dodania DATE NOT NULL,
    datum_splatnosti DATE NOT NULL,
    variabilny_symbol VARCHAR(20) DEFAULT '',
    poznamka TEXT,
    celkova_suma DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    zaloha_suma DECIMAL(10,2) NULL,      -- vyúčtovacia faktúra: odpočet zaplatenej zálohy
    zaloha_datum DATE NULL,              -- dátum úhrady zálohy
    zaloha_cislo VARCHAR(20) NULL,       -- číslo zálohovej faktúry
    pdf_data LONGBLOB,
    pdf_generated_at DATETIME,
    peppol_status VARCHAR(20) NULL,       -- ACCEPTED | UNDELIVERABLE | REJECTED | ERROR
    peppol_document_id VARCHAR(100) NULL, -- providerDocumentId od poštára
    peppol_sent_at DATETIME NULL,
    peppol_detail TEXT NULL,
    peppol_xml MEDIUMTEXT NULL,           -- odoslaná e-faktúra (originál, archivácia 10 rokov)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (odberatel_id) REFERENCES odberatelia(id),
    INDEX idx_rok (datum_vystavenia),
    INDEX idx_odberatel (odberatel_id)
);

-- Položky faktúry
CREATE TABLE IF NOT EXISTS polozky (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faktura_id INT NOT NULL,
    poradie INT NOT NULL DEFAULT 1,
    nazov VARCHAR(500) NOT NULL,
    mnozstvo DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    jednotka VARCHAR(20) DEFAULT 'ks',
    jednotkova_cena DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    spolu DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (faktura_id) REFERENCES faktury(id) ON DELETE CASCADE
);

-- Prijaté e-faktúry (Peppol) – originál XML + údaje na prehľad
CREATE TABLE IF NOT EXISTS prijate_efaktury (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id VARCHAR(100) NOT NULL UNIQUE,   -- ID dokladu u poštára
    odosielatel_id VARCHAR(100) DEFAULT NULL,   -- Peppol ID odosielateľa (0245:DIČ)
    typ VARCHAR(20) NOT NULL DEFAULT 'faktura', -- faktura | dobropis
    cislo VARCHAR(100) DEFAULT NULL,
    dodavatel VARCHAR(255) DEFAULT NULL,
    dodavatel_ico VARCHAR(50) DEFAULT NULL,
    datum_vystavenia DATE DEFAULT NULL,
    datum_splatnosti DATE DEFAULT NULL,
    suma DECIMAL(12,2) DEFAULT NULL,
    mena CHAR(3) DEFAULT NULL,
    xml MEDIUMTEXT NOT NULL,
    prijate_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prijate (prijate_at)
);

-- Autocomplete návrhy pre názvy položiek
CREATE TABLE IF NOT EXISTS polozky_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nazov VARCHAR(500) NOT NULL UNIQUE,
    posledna_cena DECIMAL(10,2) DEFAULT NULL,
    pouziti_count INT DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Migrácia pre existujúce DB (odpočet zálohy):
-- ALTER TABLE faktury ADD COLUMN zaloha_suma DECIMAL(10,2) NULL AFTER celkova_suma,
--   ADD COLUMN zaloha_datum DATE NULL AFTER zaloha_suma, ADD COLUMN zaloha_cislo VARCHAR(20) NULL AFTER zaloha_datum;

-- Migrácia pre existujúce DB (e-faktúry cez Peppol):
-- ALTER TABLE faktury ADD COLUMN peppol_status VARCHAR(20) NULL AFTER pdf_generated_at,
--   ADD COLUMN peppol_document_id VARCHAR(100) NULL AFTER peppol_status,
--   ADD COLUMN peppol_sent_at DATETIME NULL AFTER peppol_document_id, ADD COLUMN peppol_detail TEXT NULL AFTER peppol_sent_at,
--   ADD COLUMN peppol_xml MEDIUMTEXT NULL AFTER peppol_detail;
-- + CREATE TABLE prijate_efaktury vyššie
