<?php
declare(strict_types=1);


/**
 * MySQL PDO adapter
 * - Create PDO instance
 * - Return PDO instance
 * - NO class
 * - NO namespace
 */

$host    = '127.0.0.1';
$dbname  = 'cfarm_app_raw';
$user    = 'cfarm_user';
$pass    = 'cfarm_pass';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Fail fast – let bootstrap handle fatal error
    throw new RuntimeException(
        'Database connection error: ' . $e->getMessage()
    );
}

/**
 * ❗❗❗ BẮT BUỘC RETURN PDO ❗❗❗
 */
return $pdo;