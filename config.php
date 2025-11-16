<?php
declare(strict_types=1);

// Basic configuration for the tracking system.
// Change the DB_* values to match your MariaDB credentials on Plesk.
const DB_HOST = 'localhost';
const DB_NAME = 'your_database_name';
const DB_USER = 'your_database_user';
const DB_PASS = 'your_database_password';

// Public base URL of the site (no trailing slash).
const BASE_URL = 'https://dailysokalersomoy.online';

// Application timezone.
const APP_TIMEZONE = 'UTC';

date_default_timezone_set(APP_TIMEZONE);
