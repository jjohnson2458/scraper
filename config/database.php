<?php

/**
 * Database Connection Manager
 *
 * Provides a singleton PDO connection using configuration
 * from the app config.
 *
 * @package    ClaudeScraper
 * @subpackage Config
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */

class Database
{
    /** @var PDO|null Singleton PDO instance */
    private static ?PDO $instance = null;

    /**
     * Get the PDO database connection.
     *
     * @return PDO
     * @throws PDOException If connection fails.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/app.php';
            $db = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['database'],
                $db['charset']
            );

            self::$instance = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$instance;
    }

    /**
     * Close the database connection.
     *
     * @return void
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
