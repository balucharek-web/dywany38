<?php
// generate_placeholder_products.php
require 'db.php'; // zakłada, że db.php ustawia $pdo (PDO)

$stmt = $pdo->query("SELECT id, numer, nazwa FROM sklepy");
$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$insert = $pdo->prepare("INSERT INTO products (store_id, location, sub, name, reference, ean) VALUES (?, ?, ?, ?, ?, ?)");

foreach ($stores as $s) {
    $store_id = (int)$s['id'];
    for ($loc = 1; $loc <= 20; $loc++) {
        foreach (['A','B'] as $sub) {
            // przykładowe placeholdery: możesz zmienić generację według potrzeby
            $ref = sprintf("S%03d-L%02d%s", $store_id, $loc, $sub);
            $ean = str_pad((string)($store_id * 100000 + $loc * 10 + ($sub === 'A' ? 1 : 2)), 13, '0', STR_PAD_LEFT);
            $name = "Produkt {$s['numer']} #{$loc}{$sub}";

            try {
                $insert->execute([$store_id, $loc, $sub, $name, $ref, $ean]);
            } catch (Exception $e) {
                // ignoruj duplikaty przy powtórnym uruchomieniu
            }
        }
    }
}
echo "Wstawianie produktów zakończone.\n";
