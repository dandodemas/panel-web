-- Panel de contenido · Dando de Más© · esquema mysql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS brands (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    slug VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    password_encrypted TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY brands_slug_unique (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_admins (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY agency_username_unique (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_items (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    brand_id VARCHAR(32) NOT NULL,
    title VARCHAR(200) NOT NULL,
    notes TEXT NULL,
    creative_url VARCHAR(2000) NULL,
    platform VARCHAR(60) NOT NULL,
    content_type VARCHAR(12) NOT NULL,
    date_time DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    sort_pos INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_items_board (brand_id, status, sort_pos),
    KEY idx_items_date (brand_id, date_time),
    CONSTRAINT fk_items_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    item_id VARCHAR(32) NOT NULL,
    brand_id VARCHAR(32) NOT NULL,
    file VARCHAR(120) NOT NULL,
    thumb VARCHAR(120) NULL,
    mime VARCHAR(30) NOT NULL,
    width INT NOT NULL DEFAULT 0,
    height INT NOT NULL DEFAULT 0,
    bytes INT NOT NULL DEFAULT 0,
    original_name VARCHAR(200) NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    expired_at DATETIME NULL,
    KEY idx_photos_item (item_id),
    KEY idx_photos_expiry (expired_at, expires_at),
    CONSTRAINT fk_photos_item FOREIGN KEY (item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    username VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_attempts_ip (ip, created_at),
    KEY idx_attempts_user (username, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    k VARCHAR(40) NOT NULL PRIMARY KEY,
    v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

