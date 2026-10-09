<?php
final class Database
{
    private static ?PDO $connection = null;

    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = self::environmentValue('SSIS_DB_HOST', '127.0.0.1');
        $port = self::environmentValue('SSIS_DB_PORT', '3306');
        $name = self::environmentValue('SSIS_DB_NAME', 'ssis_db');
        $user = self::environmentValue('SSIS_DB_USER', 'root');
        $password = self::environmentValue('SSIS_DB_PASSWORD', '');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        self::$connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }

    private static function environmentValue(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}
