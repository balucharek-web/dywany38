<?php
// db.php
// Ustaw tutaj swoje dane dostępu do MySQL na serwerze cba.pl
$db_host = 'localhost';
$db_name = 'dekoracje';        // upewnij się, nazwa bazy
$db_user = 'abaluch';
$db_pass = 'Dekoracje1';
$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("Błąd połączenia z bazą: " . $e->getMessage());
}
session_start();
