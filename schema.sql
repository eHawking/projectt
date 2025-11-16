-- MariaDB schema for tracking system.
-- Import this file in Plesk > Databases > phpMyAdmin.

CREATE TABLE IF NOT EXISTS visits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  country VARCHAR(100) DEFAULT NULL,
  region VARCHAR(100) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  isp VARCHAR(150) DEFAULT NULL,
  user_agent TEXT,
  browser_name VARCHAR(100) DEFAULT NULL,
  browser_version VARCHAR(50) DEFAULT NULL,
  os_name VARCHAR(100) DEFAULT NULL,
  os_version VARCHAR(50) DEFAULT NULL,
  device_type VARCHAR(50) DEFAULT NULL,
  referer TEXT,
  url TEXT,
  language VARCHAR(20) DEFAULT NULL,
  screen_width INT DEFAULT NULL,
  screen_height INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_visits_created_at ON visits (created_at);
CREATE INDEX idx_visits_country ON visits (country);
CREATE INDEX idx_visits_device_type ON visits (device_type);
