<?php
// api_search.php — wersja zwracająca wszystkie lokalizacje produktu

declare(strict_types=1);
error_reporting(0);
ob_clean();                     // usuń ewentualny stary bufor
header('Content-Type: application/json; charset=utf-8');

require 'db.php';               // połączenie z bazą ($pdo)

$hasCreatedAt = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM products LIKE 'created_at'")->fetch(PDO::FETCH_ASSOC);
    if ($col) $hasCreatedAt = true;
} catch (Throwable $e) {
    $hasCreatedAt = false;
}

// wczytaj dane JSON z fetch()
$input_raw = file_get_contents('php://input');
$input = json_decode($input_raw, true);

$q = isset($input['q']) ? trim($input['q']) : '';
$store_id = isset($input['store_id']) ? (int)$input['store_id'] : 0;

$refsByDate = isset($input['refs_by_date']) ? trim((string)$input['refs_by_date']) : '';

if ($store_id !== 0 && $refsByDate !== '') {
    if (!$hasCreatedAt) {
        echo json_encode(['success' => false, 'error' => 'Brak kolumny created_at w bazie'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $refsByDate)) {
        echo json_encode(['success' => false, 'error' => 'Niepoprawna data'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT DISTINCT reference FROM products WHERE store_id=? AND created_at IS NOT NULL AND DATE(created_at)=? AND reference IS NOT NULL AND TRIM(reference)<>'' AND LOWER(TRIM(reference)) NOT IN ('brak produktu','brak') ORDER BY reference");
    $stmt->execute([$store_id, $refsByDate]);
    $refs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'data' => $refs], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($q === '' || $store_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Brak zapytania lub sklepu']);
    exit;
}

$selectCols = $hasCreatedAt
    ? 'name, reference, ean, location, sub, created_at'
    : 'name, reference, ean, location, sub, NULL as created_at';

// (1) wyszukiwanie po miejscu magazynowym typu 12A / 110B (zwraca pojedynczy wynik)
if (preg_match('/^(\d+)\s*([AaBb])$/', $q, $m)) {
    $loc = (int)$m[1];
    $sub = strtoupper($m[2]);
    $stmt = $pdo->prepare("
        SELECT $selectCols 
        FROM products 
        WHERE store_id = ? AND location = ? AND sub = ? 
        LIMIT 1
    ");
    $stmt->execute([$store_id, $loc, $sub]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => (bool)$row, 'data' => $row ?: null]);
    exit;
}

// (2) wyszukiwanie dokładne po reference lub ean - zwraca WSZYSTKIE lokalizacje
$stmt = $pdo->prepare("
    SELECT $selectCols 
    FROM products 
    WHERE store_id = ? AND (reference = ? OR ean = ?) 
    ORDER BY location+0, sub
");
$stmt->execute([$store_id, $q, $q]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// (3) wyszukiwanie częściowe (LIKE) - zwraca WSZYSTKIE pasujące lokalizacje
$like = "%$q%";
$stmt = $pdo->prepare("
    SELECT $selectCols 
    FROM products 
    WHERE store_id = ? 
      AND (reference LIKE ? OR ean LIKE ? OR name LIKE ?) 
    ORDER BY location+0, sub
");
$stmt->execute([$store_id, $like, $like, $like]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows) {
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// (4) brak wyników
echo json_encode(['success' => false, 'data' => null]);
exit;
