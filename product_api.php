<?php
// ✅ product_api.php (wersja ostateczna, naprawiona)
// ✔ działa z plikami CSV (separator , lub ;)
// ✔ aktualizuje istniejące rekordy (bez błędów duplikatów)
// ✔ dodaje nowe produkty automatycznie
// ✔ pełne logowanie procesu importu

if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['store_id'])) {
    echo json_encode(['success' => false, 'error' => 'Brak sesji. Zaloguj się ponownie.']);
    exit;
}

$store_id = (int)$_SESSION['store_id'];

$hasCreatedAt = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM products LIKE 'created_at'")->fetch(PDO::FETCH_ASSOC);
    if ($col) $hasCreatedAt = true;
} catch (Throwable $e) {
    $hasCreatedAt = false;
}

function respond($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================
// 📦 IMPORT CSV
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'error' => 'Nie udało się wczytać pliku CSV.']);
    }

    $file = $_FILES['file']['tmp_name'];
    $name = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext !== 'csv') {
        respond(['success' => false, 'error' => 'Dozwolony format pliku to CSV.']);
    }

    $content = file_get_contents($file);
    if ($content === false) respond(['success' => false, 'error' => 'Nie można odczytać pliku.']);

    // Usuń BOM i nadmiarowe białe znaki
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split('/\r\n|\n|\r/', trim($content));

    if (count($lines) < 2) {
        respond(['success' => false, 'error' => 'Plik CSV nie zawiera danych.']);
    }

    // Wykryj separator
    $delimiter = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';

    // Nagłówki
    $header = str_getcsv($lines[0], $delimiter);
    $map = [];
    foreach ($header as $i => $col) {
        $col = strtolower(trim(str_replace(['"', "'"], '', $col)));
        $map[$col] = $i;
    }

    if (!isset($map['location']) || !isset($map['sub']) || 
        (!isset($map['nazwa']) && !isset($map['name']))) {
        respond(['success' => false, 'error' => 'Brakuje wymaganych kolumn: location, sub, nazwa.']);
    }

    $added = 0;
    $updated = 0;
    $skipped = 0;
    $seen = [];

    // 📘 Przygotuj jedno bezpieczne zapytanie z ON DUPLICATE KEY UPDATE
    if ($hasCreatedAt) {
        $stmt = $pdo->prepare("
            INSERT INTO products (store_id, location, sub, name, reference, ean, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                reference = VALUES(reference),
                ean = VALUES(ean),
                created_at = IF(created_at IS NULL, NOW(), created_at)
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO products (store_id, location, sub, name, reference, ean)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                reference = VALUES(reference),
                ean = VALUES(ean)
        ");
    }

    // Log debug (opcjonalny)
    $debugFile = __DIR__ . '/import_debug.log';
    file_put_contents($debugFile, "=== NOWY IMPORT ".date('Y-m-d H:i:s')." ===\n", FILE_APPEND);

    foreach (array_slice($lines, 1) as $lineNum => $line) {
        if (trim($line) === '') continue;
        $row = str_getcsv($line, $delimiter);

        $location_raw = trim($row[$map['location']] ?? '');
        $sub_raw = trim($row[$map['sub']] ?? '');
        $name_raw = isset($map['nazwa']) ? trim($row[$map['nazwa']] ?? '') : trim($row[$map['name']] ?? '');
        $reference_raw = isset($map['referencja']) ? trim($row[$map['referencja']] ?? '') : (isset($map['reference']) ? trim($row[$map['reference']] ?? '') : '');
        $ean_raw = isset($map['ean']) ? trim($row[$map['ean']] ?? '') : '';

        // Walidacja
        if ($location_raw === '' || $sub_raw === '' || $name_raw === '') {
            $skipped++;
            continue;
        }

        // Normalizacja
        $location = (int)preg_replace('/\D/', '', $location_raw);
        $sub = strtoupper(substr(trim($sub_raw), 0, 1));
        $name = $name_raw;
        $reference = $reference_raw;
        $ean = preg_replace('/\s+/', '', $ean_raw);

        if (!$location || !in_array($sub, ['A', 'B'])) {
            $skipped++;
            continue;
        }

        // Pomijaj duplikaty w tym samym pliku
        $key = "{$location}-{$sub}";
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        // LOG
        file_put_contents($debugFile, "IMPORT: {$location}{$sub} | {$name} | {$reference} | {$ean}\n", FILE_APPEND);

        try {
            if ($hasCreatedAt) {
                $stmt->execute([$store_id, $location, $sub, $name, $reference, $ean]);
            } else {
                $stmt->execute([$store_id, $location, $sub, $name, $reference, $ean]);
            }
            $affected = $stmt->rowCount();

            // rowCount zwraca 1 przy insert, 2 przy update (w MySQL)
            if ($affected === 1) $added++;
            elseif ($affected === 2) $updated++;
        } catch (PDOException $e) {
            $skipped++;
            file_put_contents($debugFile, "❌ PDO ERROR (wiersz $lineNum): ".$e->getMessage()."\n", FILE_APPEND);
            continue;
        }
    }

    respond([
        'success' => true,
        'message' => "✅ Dodano: $added | 🔁 Zaktualizowano: $updated | ⏭️ Pomięto: $skipped"
    ]);
}

// =============================================================
// 🧩 JSON API (add / edit / delete)
// =============================================================

$raw = @file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input) {
    respond(['success' => false, 'error' => 'Brak danych JSON.']);
}

$action = $input['action'] ?? '';

if ($action === 'add') {
    $locStr = trim($input['location'] ?? '');
    if (!preg_match('/^(\d{1,3})([A-B])$/i', $locStr, $m)) {
        respond(['success' => false, 'error' => 'Niepoprawny format miejsca (np. 1A).']);
    }
    $loc = (int)$m[1];
    $sub = strtoupper($m[2]);

    if ($hasCreatedAt) {
        $check = $pdo->prepare("SELECT id, name, reference, ean FROM products WHERE store_id=? AND location=? AND sub=? LIMIT 1");
        $check->execute([$store_id, $loc, $sub]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        $isPlaceholder = false;
        if ($existing) {
            $nameLower = strtolower(trim((string)($existing['name'] ?? '')));
            $refLower = strtolower(trim((string)($existing['reference'] ?? '')));
            $eanLower = strtolower(trim((string)($existing['ean'] ?? '')));
            $isPlaceholder = ($nameLower === 'brak produktu' && $refLower === 'brak produktu' && $eanLower === 'brak produktu') ||
                             ($nameLower === 'brak' && $refLower === 'brak' && $eanLower === 'brak');
        }

        if ($existing && $isPlaceholder) {
            $upd = $pdo->prepare("UPDATE products SET name=?, reference=?, ean=?, created_at=NOW() WHERE id=? AND store_id=?");
            $upd->execute([$input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '', $existing['id'], $store_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products (store_id, location, sub, name, reference, ean, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    reference = VALUES(reference),
                    ean = VALUES(ean)
            ");
            $stmt->execute([$store_id, $loc, $sub, $input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '']);
        }
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO products (store_id, location, sub, name, reference, ean)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                reference = VALUES(reference),
                ean = VALUES(ean)
        ");
        $stmt->execute([$store_id, $loc, $sub, $input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '']);
    }

    respond(['success' => true]);
}

if ($action === 'edit') {
    $stmt = $pdo->prepare("UPDATE products SET name=?, reference=?, ean=? WHERE id=? AND store_id=?");
    $stmt->execute([
        $input['name'] ?? '',
        $input['reference'] ?? '',
        $input['ean'] ?? '',
        $input['id'] ?? 0,
        $store_id
    ]);
    respond(['success' => true]);
}

if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=? AND store_id=?");
    $stmt->execute([$input['id'] ?? 0, $store_id]);
    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Nieznana akcja.']);
?>
