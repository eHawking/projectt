<?php

declare(strict_types=1);

// Database configuration - CHANGE THESE FOR YOUR PLESK/MARIADB SETUP
const DB_HOST = 'localhost';          // usually localhost on Plesk
const DB_NAME = 'your_database_name'; // e.g. plesk_dbname
const DB_USER = 'your_db_user';       // e.g. plesk_dbuser
const DB_PASS = 'your_db_password';   // your db user password

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    return $pdo;
}

function ensureTrackingTableExists(): void
{
    $db = getDb();

    $sql = "CREATE TABLE IF NOT EXISTS tracking_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        clicked_at DATETIME NOT NULL,
        ip VARCHAR(45) NOT NULL,
        country VARCHAR(100) NULL,
        region VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        latitude DECIMAL(10,6) NULL,
        longitude DECIMAL(10,6) NULL,
        device_type VARCHAR(20) NULL,
        os VARCHAR(100) NULL,
        browser VARCHAR(100) NULL,
        user_agent TEXT NOT NULL,
        referrer TEXT NULL,
        accept_language VARCHAR(255) NULL,
        target_url TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);
}

function ensureSettingsTableExists(): void
{
    $db = getDb();

    $sql = "CREATE TABLE IF NOT EXISTS tracker_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        preview_image_url TEXT NULL,
        preview_title VARCHAR(255) NULL,
        preview_description VARCHAR(255) NULL,
        target_url TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);

    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM tracker_settings WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    $count = (int)($row['c'] ?? 0);
    if ($count === 0) {
        $insert = $db->prepare('INSERT INTO tracker_settings (id, preview_image_url, preview_title, preview_description, target_url) VALUES (1, NULL, NULL, NULL, NULL)');
        $insert->execute();
    }
}

function getTrackerSettings(): array
{
    $db = getDb();

    $stmt = $db->prepare('SELECT preview_image_url, preview_title, preview_description, target_url FROM tracker_settings WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return [
            'preview_image_url'   => null,
            'preview_title'       => null,
            'preview_description' => null,
            'target_url'          => null,
        ];
    }

    return $row;
}
