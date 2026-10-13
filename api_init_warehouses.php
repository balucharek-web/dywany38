<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

function respond_json(array $arr): void {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['store_id'])) {
    respond_json(['success' => false, 'error' => 'Brak sesji. Zaloguj się ponownie.']);
}

$store_id = (int)$_SESSION['store_id'];

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    respond_json(['success' => false, 'error' => 'Nieprawidłowe dane wejściowe']);
}

$count = (int)($input['count'] ?? 0);
if ($count < 1 || $count > 300) {
    respond_json(['success' => false, 'error' => 'Nieprawidłowa liczba magazynów']);
}

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM products WHERE store_id = ?');
    $stmt->execute([$store_id]);
    $existing = (int)($stmt->fetch()['c'] ?? 0);

    if ($existing > 0) {
        respond_json(['success' => false, 'error' => 'Magazyn ma już produkty.']);
    }

    $pdo->beginTransaction();

    $ins = $pdo->prepare('INSERT INTO products (store_id, location, sub, name, reference, ean) VALUES (?,?,?,?,?,?)');

    $inserted = 0;

    for ($loc = 1; $loc <= $count; $loc++) {
        foreach (['A', 'B'] as $sub) {
            $ins->execute([$store_id, $loc, $sub, 'Brak', 'Brak', 'Brak']);
            $inserted++;
        }
    }

    $pdo->commit();
    respond_json(['success' => true, 'store_id' => $store_id, 'warehouses' => $count, 'inserted' => $inserted]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond_json(['success' => false, 'error' => 'Błąd serwera: '.$e->getMessage()]);
}
