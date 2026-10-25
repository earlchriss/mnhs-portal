<?php

/**
 * Shared MySQL connection (PDO) for the whole project.
 *
 * Usage:
 *     require_once __DIR__ . "/../functions/db.php";   // path depends on the caller
 *     $pdo = db_connect();
 *
 * Edit the DB_* values below (or set them as environment variables).
 * The same connection is reused for the whole request.
 */

const DB_DEFAULT_HOST = "localhost";
const DB_DEFAULT_PORT = "3306";
const DB_DEFAULT_NAME = "new_mnhs";  // <- change this
const DB_DEFAULT_USER = "root";           // <- change this
const DB_DEFAULT_PASS = "";               // <- change this

function db_connect(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv("DB_HOST") ?: DB_DEFAULT_HOST;
    $port = getenv("DB_PORT") ?: DB_DEFAULT_PORT;
    $name = getenv("DB_NAME") ?: DB_DEFAULT_NAME;
    $user = getenv("DB_USER") ?: DB_DEFAULT_USER;
    $pass = getenv("DB_PASS") !== false ? getenv("DB_PASS") : DB_DEFAULT_PASS;

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
