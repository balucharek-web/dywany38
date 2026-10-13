<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';

$store_id = 38; // na razie tylko sklep 38
$store_number = str_pad($store_id, 3, '0', STR_PAD_LEFT); // np. 038
$to = "GU_SKLEPY_{$store_number}-12@leroymerlin.pl";
$subject = "Baza produktów sklepu {$store_number}";
$message = "W załączniku przesyłamy aktualną bazę produktów sklepu {$store_number}.";

// Pobierz dane sklepu
$stmt = $pdo->prepare("SELECT location, sub, name, reference, ean FROM products WHERE store_id=? ORDER BY location+0, sub");
$stmt->execute([$store_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generuj CSV w pamięci
$csvFile = tempnam(sys_get_temp_dir(), 'csv');
$fp = fopen($csvFile, 'w');
fputcsv($fp, ['location','sub','name','reference','ean']); // nagłówek
foreach ($rows as $r) {
    fputcsv($fp, [$r['location'],$r['sub'],$r['name'],$r['reference'],$r['ean']]);
}
fclose($fp);

// Wyślij mail z załącznikiem
$separator = md5(time());
$eol = PHP_EOL;
$filename = "baza_sklep_{$store_number}.csv";
$attachment = chunk_split(base64_encode(file_get_contents($csvFile)));

$headers  = "From: noreply@twojadomena.pl".$eol;
$headers .= "MIME-Version: 1.0".$eol;
$headers .= "Content-Type: multipart/mixed; boundary=\"".$separator."\"".$eol.$eol;

$body = "--".$separator.$eol;
$body .= "Content-Type: text/plain; charset=\"utf-8\"".$eol;
$body .= "Content-Transfer-Encoding: 7bit".$eol.$eol;
$body .= $message.$eol.$eol;

$body .= "--".$separator.$eol;
$body .= "Content-Type: text/csv; name=\"".$filename."\"".$eol;
$body .= "Content-Transfer-Encoding: base64".$eol;
$body .= "Content-Disposition: attachment; filename=\"".$filename."\"".$eol.$eol;
$body .= $attachment.$eol.$eol;
$body .= "--".$separator."--";

// Wysyłka maila
if(mail($to, $subject, $body, $headers)){
    echo "✅ Mail wysłany na adres: $to";
} else {
    echo "❌ Wystąpił błąd przy wysyłce maila.";
}

// Usuń plik tymczasowy
unlink($csvFile);
?>
