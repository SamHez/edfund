<?php
declare(strict_types=1);
namespace EdFund;
final class Database
{
    public static function connect(array $db): \PDO
    {
        $pdo = new \PDO($db['dsn'], $db['user'], $db['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}
