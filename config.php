<?php
// Configuration base de donnees compatible Railway / local

function env_or_default(string $name, $default = null)
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

define('APP_TIMEZONE', (string) env_or_default('APP_TIMEZONE', 'Europe/Paris'));
date_default_timezone_set(APP_TIMEZONE);

define('DB_HOST', (string) env_or_default('MYSQLHOST', env_or_default('DB_HOST', '127.0.0.1')));
define('DB_PORT', (int) env_or_default('MYSQLPORT', env_or_default('DB_PORT', 3306)));
define('DB_NAME', (string) env_or_default('MYSQLDATABASE', env_or_default('DB_NAME', 'justintime')));
define('DB_USER', (string) env_or_default('MYSQLUSER', env_or_default('DB_USER', 'root')));
define('DB_PASS', (string) env_or_default('MYSQLPASSWORD', env_or_default('DB_PASS', '')));
