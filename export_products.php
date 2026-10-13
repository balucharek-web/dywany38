<?php
// export_products.php
// Eksport bazy produktów do CSV — kompatybilny z importem

if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';

if (!isset($_SESSION['store_id'])) {
    die('Brak sesji. Zaloguj się ponownie.');
}

$store_id = (int)$_SESSION['store_id'];

// pobierz dane magazynu
$stmt = $pdo->prepare("SELECT location, sub, name, reference, ean FROM products WHERE store_id = ? ORDER BY location+0, sub");
$stmt->execute([$store_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// nagłówki CSV zgodne z importerem
$header = ['location','sub','nazwa','reference','ean'];

// ustawienia odpowiedzi HTTP — żeby przeglądarka pobrała plik
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="magazyn_export.csv"');

// otwórz strumień do wyjścia
$out = fopen('php://output', 'w');

// nagłówki kolumn
fputcsv($out, $header, ',');

// dane
foreach ($rows as $r) {
    fputcsv($out, [
        $r['location'],
        strtoupper($r['sub']),
        $r['name'],
        $r['reference'],
        $r['ean']
    ], ',');
}

fclose($out);
exit;
