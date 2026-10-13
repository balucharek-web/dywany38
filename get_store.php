<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

$numer = trim($_GET['numer'] ?? '');
if ($numer === '') {
    echo json_encode(['success' => false, 'error' => 'Brak numeru sklepu']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, numer, nazwa FROM sklepy WHERE numer = ? OR id = ?");
    $stmt->execute([$numer, $numer]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'success' => true,
            'id' => (int)$row['id'],
            'numer' => $row['numer'],
            'name' => $row['nazwa']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'Nie znaleziono sklepu']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Błąd serwera: '.$e->getMessage()]);
}
