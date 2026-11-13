<?php
/* Acceso a la base de datos (MySQL en producción, SQLite para probar en local). */

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = cfg('db', []);
    $driver = isset($c['driver']) ? $c['driver'] : 'mysql';
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if ($driver === 'sqlite') {
        $file = isset($c['file']) ? $c['file'] : PANEL_ROOT . '/storage/panel.sqlite';
        $pdo = new PDO('sqlite:' . $file, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $opts[PDO::ATTR_EMULATE_PREPARES] = false;
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    }
    return $pdo;
}

function db_driver()
{
    return cfg('db.driver', 'mysql');
}

function db_all($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_one($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_val($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Instrucciones SQL para crear las tablas, según el motor. */
function schema_statements($driver)
{
    if ($driver === 'sqlite') {
        return [
            'CREATE TABLE IF NOT EXISTS brands (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                password_encrypted TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS agency_admins (
                id TEXT PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS content_items (
                id TEXT PRIMARY KEY,
                brand_id TEXT NOT NULL,
                title TEXT NOT NULL,
                notes TEXT NULL,
                creative_url TEXT NULL,
                platform TEXT NOT NULL,
                content_type TEXT NOT NULL,
                date_time TEXT NOT NULL,
                status TEXT NOT NULL,
                sort_pos INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_items_board ON content_items (brand_id, status, sort_pos)',
            'CREATE INDEX IF NOT EXISTS idx_items_date ON content_items (brand_id, date_time)',
            'CREATE TABLE IF NOT EXISTS photos (
                id TEXT PRIMARY KEY,
                item_id TEXT NOT NULL,
                brand_id TEXT NOT NULL,
                file TEXT NOT NULL,
                thumb TEXT NULL,
                mime TEXT NOT NULL,
                width INTEGER NOT NULL DEFAULT 0,
                height INTEGER NOT NULL DEFAULT 0,
                bytes INTEGER NOT NULL DEFAULT 0,
                original_name TEXT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                expired_at TEXT NULL,
                FOREIGN KEY (item_id) REFERENCES content_items(id) ON DELETE CASCADE
            )',
            'CREATE INDEX IF NOT EXISTS idx_photos_item ON photos (item_id)',
            'CREATE INDEX IF NOT EXISTS idx_photos_expiry ON photos (expired_at, expires_at)',
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id TEXT PRIMARY KEY,
                ip TEXT NOT NULL,
                username TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts (ip, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_attempts_user ON login_attempts (username, created_at)',
            'CREATE TABLE IF NOT EXISTS settings (
                k TEXT PRIMARY KEY,
                v TEXT NULL
            )',
        ];
    }

    $tail = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS brands (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            slug VARCHAR(60) NOT NULL,
            name VARCHAR(120) NOT NULL,
            password_encrypted TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY brands_slug_unique (slug)
        ) $tail",
        "CREATE TABLE IF NOT EXISTS agency_admins (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            username VARCHAR(80) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY agency_username_unique (username)
        ) $tail",
        "CREATE TABLE IF NOT EXISTS content_items (
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
        ) $tail",
        "CREATE TABLE IF NOT EXISTS photos (
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
        ) $tail",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_attempts_ip (ip, created_at),
            KEY idx_attempts_user (username, created_at)
        ) $tail",
        "CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(40) NOT NULL PRIMARY KEY,
            v TEXT NULL
        ) $tail",
    ];
}

function ensure_schema()
{
    foreach (schema_statements(db_driver()) as $sql) {
        db()->exec($sql);
    }
}

function setting_get($key, $default = null)
{
    $v = db_val('SELECT v FROM settings WHERE k = ?', [$key]);
    return $v === null ? $default : $v;
}

function setting_set($key, $value)
{
    $exists = db_val('SELECT COUNT(*) FROM settings WHERE k = ?', [$key]);
    if ((int) $exists > 0) {
        db_exec('UPDATE settings SET v = ? WHERE k = ?', [(string) $value, $key]);
    } else {
        db_exec('INSERT INTO settings (k, v) VALUES (?, ?)', [$key, (string) $value]);
    }
}
