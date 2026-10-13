<?php
// export_products_xlsx.php
// Eksport bazy produktów do XLSX (bez Composera, z szerokościami kolumn — działa w Excelu i Google Sheets)

if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';

if (!isset($_SESSION['store_id'])) {
    die('Brak sesji. Zaloguj się.');
}

$store_id = (int)$_SESSION['store_id'];

// pobierz dane
$stmt = $pdo->prepare("SELECT location, sub, name, reference, ean FROM products WHERE store_id = ? ORDER BY location+0, sub");
$stmt->execute([$store_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// nagłówki
$headers = ['location','sub','nazwa','reference','ean'];

// utwórz XML arkusza z kolumnami i danymi
$xml = '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="12" customWidth="1"/>
    <col min="2" max="2" width="8" customWidth="1"/>
    <col min="3" max="3" width="70" customWidth="1"/>
    <col min="4" max="4" width="15" customWidth="1"/>
    <col min="5" max="5" width="20" customWidth="1"/>
  </cols>
  <sheetData>';

// nagłówki kolumn
$xml .= '<row r="1">';
$col = 1;
foreach ($headers as $h) {
    $xml .= '<c r="'.chr(64+$col).'1" t="inlineStr"><is><t>'.htmlspecialchars($h).'</t></is></c>';
    $col++;
}
$xml .= '</row>';

// dane
$rowNum = 2;
foreach ($rows as $r) {
    $xml .= '<row r="'.$rowNum.'">';
    $values = [
        $r['location'],
        strtoupper($r['sub']),
        $r['name'],
        $r['reference'],
        $r['ean']
    ];
    $col = 1;
    foreach ($values as $v) {
        $xml .= '<c r="'.chr(64+$col).$rowNum.'" t="inlineStr"><is><t>'.htmlspecialchars($v).'</t></is></c>';
        $col++;
    }
    $xml .= '</row>';
    $rowNum++;
}

$xml .= '</sheetData></worksheet>';

// przygotuj plik XLSX (ZIP z kilkoma XML)
$tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Magazyn" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

$zip->addFromString('xl/worksheets/sheet1.xml', $xml);

$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></styleSheet>');

$zip->close();

// 🆕 NAZWA PLIKU: sklep + data eksportu
$store_numer = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $_SESSION['store_numer'] ?? 'Sklep');
$date = date('Y-m-d');
$filename = "{$store_numer}_{$date}_magazyn.xlsx";

// wyślij plik do przeglądarki
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'. $filename .'"');
readfile($tmp);
unlink($tmp);
exit;
