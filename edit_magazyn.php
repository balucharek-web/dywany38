<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
header('Content-Type: text/html; charset=utf-8');
if (!isset($_SESSION['store_id'])) { header('Location: index.php'); exit; }
$store_id = (int)$_SESSION['store_id'];
$store_numer = $_SESSION['store_numer'] ?? '';

$hasCreatedAt = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM products LIKE 'created_at'")->fetch(PDO::FETCH_ASSOC);
    if ($col) $hasCreatedAt = true;
} catch (Throwable $e) {
    $hasCreatedAt = false;
}

function respond_json($arr) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

/* =========================
   ODCZYT XLSX (bez zewnętrznych bibliotek)
   ========================= */
function xlsx_col_to_index($col) {
    $col = strtoupper($col);
    $idx = 0;
    for ($i = 0; $i < strlen($col); $i++) { $idx = $idx * 26 + (ord($col[$i]) - 64); }
    return $idx - 1; // 0-based
}

function read_xlsx_first_sheet($path) {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Rozszerzenie PHP "zip" nie jest dostępne na serwerze');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Nie można otworzyć pliku XLSX (uszkodzony plik?)');

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = new SimpleXMLElement($ssXml);
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) { $text .= (string)$r->t; }
                $sharedStrings[] = $text;
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) { $sheetXml = $zip->getFromName($name); break; }
        }
    }
    if ($sheetXml === false) { $zip->close(); throw new RuntimeException('Nie znaleziono arkusza w pliku XLSX'); }

    $sheet = new SimpleXMLElement($sheetXml);
    $rows = [];
    if (isset($sheet->sheetData->row)) {
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                preg_match('/^([A-Z]+)\d+$/', $ref, $m);
                $colIdx = isset($m[1]) ? xlsx_col_to_index($m[1]) : count($rowData);
                $type = (string)$c['t'];
                $value = '';
                if ($type === 's') {
                    $i = (int)((string)$c->v);
                    $value = $sharedStrings[$i] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($c->is->t) ? (string)$c->is->t : '';
                } elseif (isset($c->v)) {
                    $value = (string)$c->v;
                }
                $rowData[$colIdx] = $value;
            }
            if ($rowData) $rows[] = $rowData;
        }
    }
    $zip->close();
    return $rows;
}

$raw = @file_get_contents('php://input');
if ($raw && json_decode($raw, true)) {
    $input = json_decode($raw, true);
    $action = $input['action'] ?? '';
    if ($action === 'init') {
        $slots = (int)($input['slots'] ?? 0);
        if ($slots < 1 || $slots > 500) respond_json(['success'=>false,'error'=>'Niepoprawna ilość miejsc (1-500)']);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id=?");
        $cntStmt->execute([$store_id]);
        $cnt = (int)$cntStmt->fetchColumn();
        if ($cnt > 0) respond_json(['success'=>false,'error'=>'Magazyn jest już zainicjalizowany']);

        $name = 'Brak produktu';
        $reference = 'Brak produktu';
        $ean = 'Brak produktu';

        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO products (store_id, location, sub, name, reference, ean) VALUES (?,?,?,?,?,?)");
            for ($loc = 1; $loc <= $slots; $loc++) {
                foreach (['A','B'] as $sub) {
                    $ins->execute([$store_id, $loc, $sub, $name, $reference, $ean]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond_json(['success'=>false,'error'=>'Błąd inicjalizacji magazynu']);
        }

        respond_json(['success'=>true]);
    }
    if ($action === 'add') {
        if (!preg_match('/^(\d{1,4})([A-B])$/i', $input['location'] ?? '', $m)) { respond_json(['success'=>false,'error'=>'Niepoprawny format miejsca (np. 1A)']); }
        $loc = (int)$m[1]; $sub = strtoupper($m[2]);
        $check = $pdo->prepare("SELECT id, name, reference, ean FROM products WHERE store_id=? AND location=? AND sub=? LIMIT 1");
        $check->execute([$store_id, $loc, $sub]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $isPlaceholder =
                strtolower(trim((string)($existing['name'] ?? ''))) === 'brak produktu' &&
                strtolower(trim((string)($existing['reference'] ?? ''))) === 'brak produktu' &&
                strtolower(trim((string)($existing['ean'] ?? ''))) === 'brak produktu';
            if (!$isPlaceholder) respond_json(['success'=>false,'error'=>"Podmagazyn {$loc}{$sub} jest już zajęty"]);

            if ($hasCreatedAt) {
                $upd = $pdo->prepare("UPDATE products SET name=?, reference=?, ean=?, created_at=NOW() WHERE id=? AND store_id=?");
                $upd->execute([$input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '', $existing['id'], $store_id]);
            } else {
                $upd = $pdo->prepare("UPDATE products SET name=?, reference=?, ean=? WHERE id=? AND store_id=?");
                $upd->execute([$input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '', $existing['id'], $store_id]);
            }
            respond_json(['success'=>true]);
        }

        if ($hasCreatedAt) {
            $ins = $pdo->prepare("INSERT INTO products (store_id, location, sub, name, reference, ean, created_at) VALUES (?,?,?,?,?,?,NOW())");
            $ins->execute([$store_id, $loc, $sub, $input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '']);
        } else {
            $ins = $pdo->prepare("INSERT INTO products (store_id, location, sub, name, reference, ean) VALUES (?,?,?,?,?,?)");
            $ins->execute([$store_id, $loc, $sub, $input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '']);
        }
        respond_json(['success'=>true]);
    }
    if ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE products SET name=?, reference=?, ean=? WHERE id=? AND store_id=?");
        $stmt->execute([$input['name'] ?? '', $input['reference'] ?? '', $input['ean'] ?? '', $input['id'] ?? 0, $store_id]);
        respond_json(['success'=>true]);
    }
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id=? AND store_id=?");
        $stmt->execute([$input['id'] ?? 0, $store_id]);
        respond_json(['success'=>true]);
    }
    respond_json(['success'=>false,'error'=>'Nieznana akcja']);
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lookup'])) {
    $q = trim($_GET['lookup']);
    if ($q === '') respond_json(['success'=>false]);

    // najpierw dokładne dopasowanie
    $stmt = $pdo->prepare("SELECT * FROM products WHERE store_id=? AND (ean=? OR reference=?)");
    $stmt->execute([$store_id, $q, $q]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) respond_json(['success'=>true,'data'=>$rows]);

    // dopasowanie częściowe
    $like = "%$q%";
    $stmt = $pdo->prepare("SELECT * FROM products WHERE store_id=? AND (reference LIKE ? OR ean LIKE ? OR name LIKE ?)");
    $stmt->execute([$store_id, $like, $like, $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) respond_json(['success'=>true,'data'=>$rows]);

    respond_json(['success'=>false]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    if (($handle = fopen($file, "r")) !== FALSE) {
        $added = 0;
        $errors = [];
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 5) continue;
            list($loc, $sub, $name, $reference, $ean) = $data;
            $loc = (int)$loc;
            $sub = strtoupper($sub);
            if (!in_array($sub, ['A','B'])) { $errors[] = "Niepoprawny podmagazyn: $loc$sub"; continue; }
            $check = $pdo->prepare("SELECT id FROM products WHERE store_id=? AND location=? AND sub=? LIMIT 1");
            $check->execute([$store_id, $loc, $sub]);
            if ($check->fetch()) continue;
            if ($hasCreatedAt) {
                $ins = $pdo->prepare("INSERT INTO products (store_id, location, sub, name, reference, ean, created_at) VALUES (?,?,?,?,?,?,NOW())");
                $ins->execute([$store_id, $loc, $sub, $name, $reference, $ean]);
            } else {
                $ins = $pdo->prepare("INSERT INTO products (store_id, location, sub, name, reference, ean) VALUES (?,?,?,?,?,?)");
                $ins->execute([$store_id, $loc, $sub, $name, $reference, $ean]);
            }
            $added++;
        }
        fclose($handle);
        respond_json(['success'=>true,'message'=>"Dodano $added rekordów",'errors'=>$errors]);
    } else {
        respond_json(['success'=>false,'error'=>"Nie można otworzyć pliku CSV"]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['compare_file'])) {
    $origName = $_FILES['compare_file']['name'] ?? '';
    if (!preg_match('/\.xlsx$/i', $origName)) respond_json(['success'=>false,'error'=>'Wymagany plik w formacie .xlsx']);

    try {
        $rows = read_xlsx_first_sheet($_FILES['compare_file']['tmp_name']);
    } catch (Throwable $e) {
        respond_json(['success'=>false,'error'=>'Nie udało się odczytać pliku XLSX: '.$e->getMessage()]);
    }
    if (!$rows) respond_json(['success'=>false,'error'=>'Plik jest pusty lub ma nieobsługiwany format']);

    // Pierwszy wiersz traktujemy jako nagłówki — szukamy kolumn po nazwie (niezależnie od ich kolejności)
    $header = $rows[0];
    $colRef = null; $colOpis = null; $colStock = null;
    foreach ($header as $idx => $label) {
        $l = strtolower(trim((string)$label));
        if ($colRef === null && strpos($l, 'ref') !== false) $colRef = $idx;
        if ($colOpis === null && (strpos($l, 'opis') !== false || strpos($l, 'nazwa') !== false)) $colOpis = $idx;
        if ($colStock === null && (strpos($l, 'stock') !== false || strpos($l, 'ilo') !== false)) $colStock = $idx;
    }
    if ($colRef === null) respond_json(['success'=>false,'error'=>'Nie znaleziono w pliku kolumny z referencją (np. "ref LM")']);

    // Produkty aktualnie w magazynie tego sklepu, zindeksowane po referencji (placeholdery wykluczone)
    $stmt = $pdo->prepare("SELECT location, sub, reference, name FROM products WHERE store_id=?");
    $stmt->execute([$store_id]);
    $byRef = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $refKey = trim((string)$p['reference']);
        $nameLower = strtolower(trim((string)$p['name']));
        if ($refKey === '' || $nameLower === 'brak produktu' || $nameLower === 'brak') continue;
        $byRef[$refKey][] = $p['location'].$p['sub'];
    }

    $found = [];
    $notFound = [];
    foreach (array_slice($rows, 1) as $r) {
        $rawRef = trim((string)($r[$colRef] ?? ''));
        if ($rawRef === '' || !is_numeric($rawRef)) continue; // pomija wiersze "Total" / opis filtrów / inne śmieci na końcu eksportu

        $refKey = sprintf('%.0f', (float)$rawRef); // normalizacja zapisu naukowego (np. 8.896E7 -> 88960779)
        $stockRaw = $colStock !== null ? ($r[$colStock] ?? null) : null;

        $item = [
            'ref'   => $refKey,
            'opis'  => $colOpis !== null ? trim((string)($r[$colOpis] ?? '')) : '',
            'stock' => is_numeric($stockRaw) ? (int)$stockRaw : null,
        ];

        if (isset($byRef[$refKey])) {
            $item['lokalizacje'] = $byRef[$refKey];
            $found[] = $item;
        } else {
            $notFound[] = $item;
        }
    }

    respond_json([
        'success'   => true,
        'found'     => $found,
        'not_found' => $notFound,
        'total'     => count($found) + count($notFound),
    ]);
}
$stmt = $pdo->prepare("SELECT * FROM products WHERE store_id=? ORDER BY location+0, sub");
$stmt->execute([$store_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasProducts = !empty($rows);
$magazyny = [];
$maxMag = 0;
foreach ($rows as $r) { $m = (int)$r['location']; $magazyny[$m][strtoupper($r['sub'])] = $r; if ($m > $maxMag) $maxMag = $m; }
if (!$hasProducts) $maxMag = 0;
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#1f6feb" />
<link rel="manifest" href="manifest_html.json" />
<link rel="icon" href="icon-192.png" type="image/png" />
<link rel="apple-touch-icon" href="icon-512.png" />
<title>Panel magazynowy — Sklep <?=htmlspecialchars($store_numer)?></title>
<style>

*, *::before, *::after {
    box-sizing: border-box;
}

.container {
    padding: 18px;
    max-width: 1200px;
    margin: auto;

    display: flex;
    flex-direction: column;

    overflow-x: hidden;
}
:root{--accent:#1f6feb;--bg:#f4f6fb;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,0.08);--radius:14px}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:var(--bg);color:#111}
.top{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;background:#fff;box-shadow:var(--shadow);flex-wrap:wrap;gap:10px}
.top .actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
.btn{border:0;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:700;flex:1 1 auto;min-width:120px;text-align:center;text-decoration:none}
.btn.add{background:var(--accent);color:#fff}
.btn.edit{background:#facc15;color:#111}
.btn.del{background:#ef4444;color:#fff}
.btn.gray{background:#e5e7eb;color:#111}
@media(max-width:700px){.top{flex-direction:column;align-items:stretch}.top .actions{flex-direction:column;width:100%}.top .actions .btn{font-size:1rem;padding:12px}}

.magazyn{background:var(--card);box-shadow:var(--shadow);border-radius:var(--radius);margin-bottom:20px;padding:14px}
.magazyn h3{margin:0 0 8px;color:var(--accent)}
.podmagazyny{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.pod{background:#fbfdff;border:1px solid #eef3ff;border-radius:12px;padding:12px;display:flex;flex-direction:column;justify-content:space-between;transition:box-shadow .15s ease,border-color .15s ease,background-color .15s ease}
.pod[data-is-new="1"]{border-color:#eef3ff;background:#fbfdff;box-shadow:none}
.pod-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.badge-new{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:#dcfce7;border:1px solid #bbf7d0;color:#166534;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;line-height:1}
.badge-new::before{content:"";width:7px;height:7px;border-radius:999px;background:#22c55e;box-shadow:0 0 0 2px rgba(34,197,94,0.18)}
.pod-header{font-weight:800;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;gap:8px}
.pod-date{font-weight:700;font-size:11px;color:#6b7280;white-space:nowrap}
.pod-empty{color:#777;font-size:.95rem;margin-bottom:8px}
.filters{margin-bottom:12px;display:flex;flex-direction:column;gap:10px;align-items:stretch}
.filters .filters-search input[type=text]{width:100%;height:40px;padding:8px 10px;border-radius:6px;border:1px solid #ddd;background:#fff;font-weight:400}
.filters .filters-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.filters .filters-row select{height:40px;padding:0 12px;border-radius:999px;border:1px solid #e3e8f5;background:#fff;font-weight:700}
.filters .filter-chip{height:40px;display:inline-flex;align-items:center;gap:8px;padding:0 12px;border-radius:999px;background:#fff;border:1px solid #e3e8f5;cursor:pointer;user-select:none;font-weight:800;white-space:nowrap}
.filters .filter-chip input{margin:0;accent-color:var(--accent)}
.filters .filter-date{height:40px;display:inline-flex;align-items:center;gap:10px;padding:0 12px;border-radius:999px;background:#fff;border:1px solid #e3e8f5;white-space:nowrap}
.filters .filter-date span{color:#6b7280;font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.06em}
.filters #newDate{height:100%;border:0;background:transparent;padding:0;font-weight:800}
.filters #newDate:focus{outline:none}
@media(min-width:900px){.filters .filters-row{flex-wrap:nowrap}}
.modal{position:fixed;inset:0;background:rgba(0,0,0,0.6);display:none;align-items:center;justify-content:center;z-index:1200;padding:12px}
.sheet{background:white;padding:18px;border-radius:12px;width:100%;max-width:520px;box-shadow:var(--shadow)}
label{display:block;font-weight:600}
input[type=text]{width:96%;padding:10px;border-radius:8px;border:1px solid #ddd;margin-top:6px}
</style>
</head>
<body>
<div class="top">
<div><strong>Panel magazynowy sklepu <?=htmlspecialchars($store_numer)?></strong></div>
<div class="actions">
<button id="openImportBtn" class="btn edit">📂 Importuj bazę</button>
<button id="openCompareBtn" class="btn edit">🧾 Sprawdź stock expo</button>
<a href="export_products.php" class="btn add">📤 Eksportuj bazę</a>
<a href="export_products_xlsx.php" class="btn add">📘 Eksportuj XLSX</a>
<button type="button" id="printListBtn" class="btn add">🖨️ Drukuj listę</button>
<a href="logout.php" class="btn del">Wyloguj</a>
<a href="pomocsklep.php" target="_blank" class="btn gray">❓ Pomoc</a>
</div>
</div>
<div class="container">
<?php if($maxMag > 0): ?>
<div class="filters">
  <div class="filters-search">
    <input type="text" id="filterInput" placeholder="Szukaj magazynu / produktu...">
  </div>
  <div class="filters-row">
    <label class="filter-chip">
    <input type="checkbox" id="emptyOnly"> Tylko puste
    </label>
    <label class="filter-chip">
    <input type="checkbox" id="newOnly"> Tylko nowe
    </label>
    <div id="newDateWrap" class="filter-date" style="display:none">
      <span>Data</span>
      <input type="date" id="newDate">
    </div>
    <select id="sortSelect">
    <option value="asc">Sortuj rosnąco</option>
    <option value="desc">Sortuj malejąco</option>
    </select>
  </div>
</div>
<?php for($i=1;$i<=$maxMag;$i++): ?>
<div class="magazyn" data-magazyn="<?=$i?>">

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:14px">
        <h3>Magazyn <?=$i?></h3>
        <button class="btn add" onclick="openAdd(<?=$i?>)">➕ Dodaj produkt</button>
    </div>

    <div class="podmagazyny">
        <?php foreach(['A','B'] as $sub): ?>
        <?php
            $p = $magazyny[$i][$sub] ?? null;
            $isNew = false;
            $createdDateIso = '';
            $createdDatePretty = '';
            if ($p) {
                $nameLower = strtolower(trim((string)($p['name'] ?? '')));
                $refLower = strtolower(trim((string)($p['reference'] ?? '')));
                $eanLower = strtolower(trim((string)($p['ean'] ?? '')));
                $isPlaceholder = ($nameLower === 'brak produktu' && $refLower === 'brak produktu' && $eanLower === 'brak produktu') ||
                                 ($nameLower === 'brak' && $refLower === 'brak' && $eanLower === 'brak');
                if (!$isPlaceholder) {
                    $createdAtRaw = (string)($p['created_at'] ?? '');
                    if ($createdAtRaw !== '') {
                        $ts = strtotime($createdAtRaw);
                        if ($ts !== false) {
                            $createdDateIso = date('Y-m-d', $ts);
                            $createdDatePretty = date('d.m.Y', $ts);
                            if ((time() - $ts) <= (7 * 24 * 60 * 60)) {
                                $isNew = true;
                            }
                        }
                    }
                }
            }
        ?>
        <div class="pod" data-is-new="<?= $isNew ? '1' : '0' ?>" data-created-date="<?= htmlspecialchars($createdDateIso) ?>">
            <?php if($p): ?>
                <div>
                    <div class="pod-header"><span>Podmagazyn <?=$sub?></span><?php if($createdDatePretty !== ''): ?><span class="pod-date"><?=htmlspecialchars($createdDatePretty)?></span><?php endif; ?></div>
                    <div class="pod-title"><strong><?=htmlspecialchars($p['name'])?></strong><?php if($isNew): ?><span class="badge-new">NOWY</span><?php endif; ?></div>
                    <div class="small">Ref: <?=htmlspecialchars($p['reference'])?></div>
                    <div class="small">EAN: <?=htmlspecialchars($p['ean'])?></div>
                </div>
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn edit"
        data-id="<?=$p['id']?>"
        data-loc="<?=$p['location']?>"
        data-sub="<?=$p['sub']?>"
        data-name="<?=htmlspecialchars($p['name'],ENT_QUOTES)?>"
        data-ref="<?=htmlspecialchars($p['reference'],ENT_QUOTES)?>"
        data-ean="<?=htmlspecialchars($p['ean'],ENT_QUOTES)?>"
        onclick="openEditFromButton(this)">✏️ Edytuj</button>
                    <button class="btn del" onclick="deleteProduct(<?=$p['id']?>)">🗑️ Usuń</button>
                </div>
            <?php else: ?>
                <div>
                    <div class="pod-header">Podmagazyn <?=$sub?></div>
                    <div class="pod-empty">Brak produktu</div>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

</div> <!-- ✅ TO ZAMYKA .magazyn -->
<?php endfor; ?>
<?php endif; ?>
<div class="modal" id="modal">
<div class="sheet fadeIn">
<form id="formModal">
<h3 id="modalTitle">Dodaj produkt</h3>
<input type="hidden" id="id">
<input type="hidden" id="action" value="add">
<input type="hidden" id="selectedSub">
<label>Magazyn<input type="text" id="location" readonly></label>
<div style="margin-top:10px"><b>Podmagazyn

:</b><br>
<div id="subButtons" style="display:flex;gap:10px;margin-top:8px">
<button type="button" class="btn gray" id="btnA" onclick="selectSub('A')">A</button>
<button type="button" class="btn gray" id="btnB" onclick="selectSub('B')">B</button>
</div>
</div>
<label>Nazwa<input type="text" id="name" required></label>

<label>Referencja<input type="text" id="reference" required></label>
<label>EAN
<div style="position:relative;margin-top:6px">
<input type="text" id="ean" required style="width:100%;padding:10px 40px 10px 10px;border-radius:8px;border:1px solid #ddd;box-sizing:border-box">
<button type="button" id="scanEanBtn" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--accent);cursor:pointer;padding:4px;font-size:18px;display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:4px;">📷</button>
</div>
</label>
<div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
<button type="button" class="btn gray" onclick="closeModal()">Anuluj</button>
<button type="submit" class="btn add">Zapisz</button>
</div>
</form>
</div>
</div>

<div class="modal" id="initModal">
<div class="sheet fadeIn">
<form id="initForm">
<h3>Inicjalizacja magazynu</h3>
<label>Ilość miejsc magazynowych
<input type="text" id="initSlots" required>
</label>
<div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
<button type="submit" class="btn add">Zatwierdź</button>
</div>
</form>
</div>
</div>
<div class="modal" id="importModal">
<div class="sheet fadeIn">
<form id="importForm" enctype="multipart/form-data">
<h3>Importuj bazę (CSV)</h3>
<label>📂 Wybierz plik CSV
<input type="file" name="file" accept=".csv" required>
</label>
<div id="importStatus" class="small" style="margin-top:10px;min-height:20px"></div>
<div style="text-align:right;margin-top:14px;display:flex;gap:8px;justify-content:flex-end;">
<button type="button" class="btn gray" id="cancelImportBtn">Anuluj</button>
<button type="submit" class="btn add">Importuj</button>
</div>
</form>
</div>
</div>

<div class="modal" id="compareModal">
<div class="sheet fadeIn">
<form id="compareForm" enctype="multipart/form-data">
<h3>Wgraj plik xlsx z raportu REP0212 - Stock expo</h3>
<label>📊 Wybierz plik XLSX
<input type="file" name="compare_file" accept=".xlsx" required>
</label>
<div id="compareStatus" class="small" style="margin-top:10px;min-height:20px"></div>
<div style="text-align:right;margin-top:14px;display:flex;gap:8px;justify-content:flex-end;">
<button type="button" class="btn gray" id="cancelCompareBtn">Anuluj</button>
<button type="submit" class="btn add">Porównaj</button>
</div>
</form>
</div>
</div>

<div class="modal" id="compareResultModal">
<div class="sheet fadeIn" style="max-width:720px;max-height:85vh;padding:0;display:flex;flex-direction:column;overflow:hidden">
<div style="padding:18px 18px 0">
<h3 style="margin-top:0">Wynik porównania</h3>
<div id="compareSummary" style="font-weight:700;margin-bottom:14px"></div>
</div>
<div style="flex:1 1 auto;overflow-y:auto;padding:0 18px">
<div style="display:flex;gap:18px;flex-wrap:wrap;padding-bottom:12px">
  <div style="flex:1;min-width:260px">
    <h4 style="color:#16a34a;margin:0 0 8px">✅ W magazynie</h4>
    <div id="compareFoundList"></div>
  </div>
  <div style="flex:1;min-width:260px">
    <h4 style="color:#dc2626;margin:0 0 8px">❌ Brak w magazynie</h4>
    <div id="compareNotFoundList"></div>
  </div>
</div>
</div>
<div style="text-align:right;flex:0 0 auto;padding:12px 18px 18px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;border-top:1px solid #eee;background:#fff">
<button type="button" class="btn add" id="printCompareBtn">🖨️ Drukuj raport</button>
<button type="button" class="btn gray" id="closeCompareResultBtn">Zamknij</button>
</div>
</div>
</div>

<!-- SKANNER MODAL -->
<div id="scannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:2000;">
<div style="position:relative;width:100%;height:100%;">
<div id="scannerContainer" style="position:absolute;inset:20px;">
<div id="scannerVideo" style="position:absolute;inset:0;width:100%;height:100%;overflow:hidden;background:black;border-radius:12px;"></div>
<div class="overlay" style="position:absolute;inset:0;z-index:5;display:flex;justify-content:center;align-items:center;pointer-events:none;">
<div class="scanner-frame" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:85%;max-width:380px;aspect-ratio:1/1;border-radius:14px;border:3px solid rgba(0,255,0,0.9);z-index:10;pointer-events:none;animation:pulse 2.4s infinite;"></div>
<div class="scanner-frame::before" style="content:'';position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(min(85vw,380px) + 12px);height:calc(min(85vw,380px) + 12px);border-radius:15px;box-shadow:0 0 0 200vmax rgba(0,0,0,0.66);z-index:9;pointer-events:none;"></div>
</div>
<button id="closeScannerBtn" style="position:absolute;top:18px;right:18px;background:rgba(0,0,0,0.6);color:white;border:none;border-radius:50%;width:44px;height:44px;font-size:22px;cursor:pointer;z-index:20;">✕</button>
<button id="photoScanBtn" style="position:absolute;left:50%;bottom:40px;transform:translateX(-50%);background:var(--accent);color:white;border:none;padding:14px 22px;border-radius:12px;font-size:1rem;font-weight:600;cursor:pointer;z-index:25;display:none;box-shadow:0 4px 16px rgba(0,0,0,0.4);">📸 Skan z zdjęcia</button>
</div>
</div>
</div>

<div id="customPopup" style="display:none;position:fixed;top:20px;left:50%;transform:translateX(-50%);background:white;color:var(--accent);padding:16px 24px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.2);font-weight:600;z-index:10000;text-align:center;max-width:90%;font-size:1rem;transition: opacity 0.3s ease;opacity:0;"></div>

<!-- FULLSCREEN SCANNER -->
<div id="fullscreen-scanner" aria-hidden="true">
<div id="scanner"></div>
<div class="overlay" aria-hidden="true">
<div class="scanner-frame" id="scanner-frame" aria-hidden="true"></div>
</div>
<button id="close-scanner" title="Zamknij skaner">✕</button>

<!-- SKAN ZE ZDJĘCIA -->
<button id="photoScanBtn" style="position:absolute; left:50%; bottom:40px; transform:translateX(-50%); background:var(--accent); color:white; border:none; padding:14px 22px; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; z-index:25; display:none; box-shadow:0 4px 16px rgba(0,0,0,0.4);">
📸 Skan z zdjęcia
</button>
</div>

<script type="module">
import * as ZXing from 'https://cdn.jsdelivr.net/npm/@zxing/library@latest/+esm';

// -----------------------------
// SKANNER CONFIG
// -----------------------------
const HINTS = new Map();
HINTS.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.EAN_13, ZXing.BarcodeFormat.EAN_8]);
HINTS.set(ZXing.DecodeHintType.TRY_HARDER, true);

let codeReader = null;
function ensureCodeReader(){ if(!codeReader) codeReader = new ZXing.BrowserMultiFormatReader(HINTS); }

const MIN_THROTTLE = 80;
const TIMEOUT_MS = 30000;

// -----------------------------
// SKANNER STATE
// -----------------------------
let activeStream = null;
let activeVideo = null;
let scanLoopId = null;
let scanned = false;
let lastDecode = 0;
let timeoutHandle = null;
let globalCanvas = null;
let globalCtx = null;

// -----------------------------
// SKANNER HELPERS
// -----------------------------
function showPopup(msg){ const p=document.getElementById('customPopup'); p.textContent=msg; p.style.display='block'; p.style.opacity='1'; setTimeout(()=>{ p.style.opacity='0'; setTimeout(()=>{ p.style.display='none'; },300); }, 2500); }

function startTimeout(){ clearTimeoutScanner(); timeoutHandle = setTimeout(()=>{ if(!scanned){ showPopup('Brak odczytu — spróbuj bliżej, stabilniej.'); stopScanner(); } }, TIMEOUT_MS); }
function clearTimeoutScanner(){ if(timeoutHandle){ clearTimeout(timeoutHandle); timeoutHandle=null; } }

// -----------------------------
// VIDEO FUNCTIONS
// -----------------------------
async function getVideo(){
  return await navigator.mediaDevices.getUserMedia({ video:{ facingMode:{ideal:"environment"}, width:{ideal:1920}, height:{ideal:1080}, frameRate:{ideal:30,max:60} } });
}

async function toggleTorch(stream, enable){ try{ const track = stream.getVideoTracks()[0]; const caps = track.getCapabilities(); if(!caps.torch) return false; await track.applyConstraints({advanced:[{torch:enable}]}); return true;}catch(e){return false;} }

// -----------------------------
// CANVAS FUNCTIONS
// -----------------------------
function ensureGlobalCanvas(){ if(globalCanvas) return; globalCanvas = document.createElement('canvas'); globalCanvas.style.display='none'; document.body.appendChild(globalCanvas); globalCtx = globalCanvas.getContext('2d'); }

function roiCanvas(video){ try{
  const vf = video.getBoundingClientRect();
  const fr = document.getElementById('scanner-frame').getBoundingClientRect();
  const vw = video.videoWidth; const vh = video.videoHeight; if(!vw||!vh) return null;
  const sx = (fr.left - vf.left) * (vw/vf.width);
  const sy = (fr.top  - vf.top ) * (vh/vf.height);
  const sw = fr.width  * (vw/vf.width);
  const sh = fr.height * (vh/vf.height);

  // limit width to reasonable size — balance quality/speed
  const outW = Math.max(800, Math.min(1400, Math.round(sw)));
  const outH = Math.round(outW * (sh/sw));

  ensureGlobalCanvas();
  if(globalCanvas.width !== outW || globalCanvas.height !== outH){ globalCanvas.width = outW; globalCanvas.height = outH; }
  globalCtx.imageSmoothingEnabled = true;
  globalCtx.drawImage(video, sx, sy, sw, sh, 0,0, globalCanvas.width, globalCanvas.height);
  return globalCanvas;
 }catch(e){ return null; }
}

// -----------------------------
// PREPROCESS
// -----------------------------
function preprocess(cvs){ try{
  const ctx = cvs.getContext('2d');
  const img = ctx.getImageData(0,0,cvs.width,cvs.height);
  const d = img.data;
  let min=255,max=0;
  for(let i=0;i<d.length;i+=4){ const g = Math.round(d[i]*0.3 + d[i+1]*0.59 + d[i+2]*0.11); d[i]=d[i+1]=d[i+2]=g; if(g<min)min=g; if(g>max)max=g; }
  const span = Math.max(1,max-min);
  const mult = 255/span;
  for(let i=0;i<d.length;i+=4){ const v=d[i]; const nv = Math.round((v-min)*mult); d[i]=d[i+1]=d[i+2]=nv; }
  ctx.putImageData(img,0,0);

  const tmp = document.createElement('canvas'); tmp.width=cvs.width; tmp.height=cvs.height; const tctx = tmp.getContext('2d');
  tctx.drawImage(cvs,0,0);
  tctx.globalAlpha = 0.5;
  for(let i=0;i<2;i++) tctx.drawImage(tmp, -1, 0), tctx.drawImage(tmp, 1, 0), tctx.drawImage(tmp, 0, -1), tctx.drawImage(tmp, 0, 1);
  tctx.globalAlpha = 1;

  const base = ctx.getImageData(0,0,cvs.width,cvs.height);
  const blur = tctx.getImageData(0,0,cvs.width,cvs.height);
  for(let i=0;i<base.data.length;i+=4){ const sharp = base.data[i] - (blur.data[i]*0.6 - 0); const v = Math.min(255, Math.max(0, Math.round(base.data[i] + sharp*0.6))); base.data[i]=base.data[i+1]=base.data[i+2]=v; }
  ctx.putImageData(base,0,0);
  return cvs;
 }catch(e){ return cvs; }
}

// -----------------------------
// DECODERS
// -----------------------------
function decodeHybrid(cvs){ try{
  const lum = new ZXing.HTMLCanvasElementLuminanceSource(cvs);
  const bin = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(lum));
  const reader = new ZXing.MultiFormatReader();
  reader.setHints(HINTS);
  const res = reader.decode(bin);
  return res?.text || null;
 }catch(e){ return null; }
}

async function decodeLegacy(cvs){ try{ ensureCodeReader(); return await codeReader.decodeFromImage(cvs); }catch(e){ return null; }}

async function snapshotFallback(){ return new Promise(resolve=>{ const inp=document.createElement('input'); inp.type='file'; inp.accept='image/*'; inp.capture='environment'; inp.style.display='none'; document.body.appendChild(inp);
  inp.onchange=()=>{ const f=inp.files[0]; if(!f){ document.body.removeChild(inp); resolve(null); return; } const url=URL.createObjectURL(f); const img=new Image(); img.onload=async()=>{ ensureGlobalCanvas(); globalCanvas.width=img.width; globalCanvas.height=img.height; globalCtx.drawImage(img,0,0); const h = decodeHybrid(preprocess(globalCanvas)); URL.revokeObjectURL(url); document.body.removeChild(inp); resolve(h); }; img.src=url; }; inp.click(); }); }

// -----------------------------
// BEEP
// -----------------------------
async function beep(){ try{ const ctx=new AudioContext(); const osc=ctx.createOscillator(); const g=ctx.createGain(); osc.frequency.value=1100; osc.connect(g); g.connect(ctx.destination); osc.start(); g.gain.setValueAtTime(0.3,ctx.currentTime); g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.2); osc.stop(ctx.currentTime+0.21); }catch(e){} }

// -----------------------------
// SKANNER LOOP
// -----------------------------
function startScanner(){ ensureCodeReader(); scanned=false; lastDecode=0; document.getElementById('fullscreen-scanner').style.display='block';
  try{ const ps = document.getElementById('photoScanBtn'); if(ps) ps.style.display='block'; }catch(e){}
  document.getElementById('scanner').innerHTML=`<video id="cam" autoplay playsinline muted></video>`;
  const video = document.getElementById('cam');
  (async()=>{
    try{
      activeStream = await getVideo(); video.srcObject = activeStream; await video.play(); try{ await toggleTorch(activeStream,false); }catch(e){}
      activeVideo = video; startTimeout(); let fails=0;
      async function loop(){
        if(!activeVideo) return;
        const now = performance.now(); if(now-lastDecode < MIN_THROTTLE){ scanLoopId=requestAnimationFrame(loop); return; }
        lastDecode = now;
        const cvs = roiCanvas(activeVideo);
        if(!cvs){ scanLoopId=requestAnimationFrame(loop); return; }

        let text = decodeHybrid(cvs);
        if(!text) text = decodeHybrid(preprocess(cvs));
        if(!text) text = await decodeLegacy(cvs);

        if (text) {
    const frame = document.getElementById('scanner-frame');
    if (frame) {
        frame.classList.add('detect');
        setTimeout(() => frame.classList.remove('detect'), 400);
    }

    const clean = text.replace(/\D/g, '');

    if (clean.length === 13 || clean.length === 8) {
        if (frame) {
            frame.classList.add('found');
            setTimeout(() => frame.classList.remove('found'), 700);
        }

        scanned = true;
        await beep();

        // Wstaw skanowany kod do pola EAN
        document.getElementById('ean').value = clean;
        stopScanner();
        return;
    }
} else {
    fails++;
}

        if(false){ /* automatic snapshot disabled — user must click 📸 Skan z zdjęcia */ }
        scanLoopId=requestAnimationFrame(loop);
      }
      scanLoopId=requestAnimationFrame(loop);
    }catch(err){ showPopup('Błąd kamery: '+err.message); stopScanner(); }
  })();
}

function stopScanner(){ document.getElementById('fullscreen-scanner').style.display='none';
  try{ const ps = document.getElementById('photoScanBtn'); if(ps) ps.style.display='none'; }catch(e){}
  clearTimeoutScanner(); if(scanLoopId){ cancelAnimationFrame(scanLoopId); scanLoopId=null; }
  if(activeStream){ activeStream.getTracks().forEach(t=>t.stop()); activeStream=null; }
  if(activeVideo){ try{ activeVideo.pause(); activeVideo.srcObject=null; }catch(e){} activeVideo=null; }
  if(codeReader){ try{ codeReader.reset(); }catch(e){} }
}

// -----------------------------
// SKANNER EVENTS
// -----------------------------
document.addEventListener('DOMContentLoaded', ()=>{
  const scanBtn = document.getElementById('scanEanBtn');
  if (scanBtn) scanBtn.addEventListener('click', startScanner);
  const closeBtn = document.getElementById('close-scanner');
  if (closeBtn) closeBtn.addEventListener('click', stopScanner);
  
  const ps = document.getElementById('photoScanBtn');
  if(ps){
    ps.addEventListener('click', async ()=>{
      ps.disabled = true;
      try{
        const code = await snapshotFallback();
        if(!code){ showPopup('Nie wykryto kodu na zdjęciu'); ps.disabled=false; return; }
        const clean = code.replace(/\D/g,'');
        if(clean.length===13||clean.length===8){
          document.getElementById('ean').value = clean;
          stopScanner();
        } else { showPopup('Brak wykrytego kodu na zdjęciu'); }
      }catch(e){ showPopup('Błąd przy przetwarzaniu zdjęcia'); }
      ps.style.display='none';
      ps.disabled = false;
    });
  }
});

// -----------------------------
// STYLES FOR SCANNER (z index_public.html)
// -----------------------------
const scannerStyles = `
#fullscreen-scanner { display:none; position:fixed; inset:0; z-index:9999; }
#scanner { position:fixed; inset:0; width:100vw; height:100vh; overflow:hidden; z-index:1; background: black; }
#scanner video { position:fixed !important; top:0; left:0; width:100vw; height:100vh; object-fit:cover; z-index:1; }
.overlay { position:fixed; inset:0; z-index:5; display:flex; justify-content:center; align-items:center; pointer-events:none; }
.scanner-frame {position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:85%;max-width:380px;aspect-ratio:1 / 1;border-radius:14px;border:3px solid rgba(0,255,0,0.9);z-index:10;pointer-events:none;animation: pulse 2.4s infinite;}
.scanner-frame::before {content:"";position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(min(85vw,380px) + 12px);height:calc(min(85vw,380px) + 12px);border-radius:15px;box-shadow: 0 0 0 200vmax rgba(0,0,0,0.66);z-index:9;pointer-events:none;}
@media (orientation: landscape) {.scanner-frame {width:auto;height:70vh;max-height:380px;}.scanner-frame::before {width:calc(min(70vh,380px) + 12px);height:calc(min(70vh,380px) + 12px);}}
@keyframes pulse {0%,100%{transform:translate(-50%,-50%) scale(1);}50%{transform:translate(-50%,-50%) scale(1.03);}}
#close-scanner { position:fixed; top:18px; right:18px; background:rgba(0,0,0,0.6); color:white; border:none; border-radius:50%; width:44px; height:44px; font-size:22px; cursor:pointer; z-index:20; }
.scanner-frame.detect {
border-color: rgba(255,215,0,0.95) !important;
box-shadow: 0 0 24px rgba(255,215,0,0.18) !important;
}
.scanner-frame.found {
border-color: rgba(0,200,0,1) !important;
box-shadow: 0 0 30px rgba(0,255,0,0.5) !important;
transform: translate(-50%,-50%) scale(1.03) !important;
}
`;
const styleSheet = document.createElement('style');
styleSheet.textContent = scannerStyles;
document.head.appendChild(styleSheet);

</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectedShop = "<?= htmlspecialchars($store_numer) ?>";
    if (selectedShop) {
        localStorage.setItem("selectedShop", selectedShop);
    }
});

/* =========================
   FILTROWANIE / SORTOWANIE
   ========================= */

const filterInput = document.getElementById('filterInput');
const emptyOnly = document.getElementById('emptyOnly');
const newOnly = document.getElementById('newOnly');
const newDateWrap = document.getElementById('newDateWrap');
const newDate = document.getElementById('newDate');
const sortSelect = document.getElementById('sortSelect');

if (filterInput && emptyOnly && newOnly && newDateWrap && newDate && sortSelect) {
    const magazynyEls = Array.from(document.querySelectorAll('.magazyn'));
    const originalOrder = [...magazynyEls];

    function applyFilter() {
        const term = filterInput.value.trim().toLowerCase();
        const showEmptyOnly = emptyOnly.checked;
        const showNewOnly = newOnly.checked;
        const selectedDate = (newDate.value || '').trim();
        const sortOrder = sortSelect.value;

        let filtered = originalOrder.filter(m => {
            const termUpper = term.toUpperCase();
            const isOnlyDigits = /^\d+$/.test(term);
            const isLocSub = /^\d+\s*[A-B]$/i.test(termUpper);

            // Zasada:
            // - same cyfry < 5 znaków => numer magazynu (np. 12, 43)
            // - same cyfry >= 5 znaków => referencja / EAN
            // - format 12A / 12B => konkretny podmagazyn
            let mode = 'text'; // text | warehouse | ref_ean | locsub
            if (isLocSub) {
                mode = 'locsub';
            } else if (isOnlyDigits) {
                mode = term.length >= 5 ? 'ref_ean' : 'warehouse';
            } else if (term) {
                mode = 'text';
            }

            if (mode === 'warehouse') {
                if (parseInt(m.dataset.magazyn, 10) !== parseInt(term, 10)) return false;
            } else if (mode === 'locsub') {
                const locSub = termUpper.replace(/\s+/g, '');
                const targetMag = parseInt(locSub, 10);
                if (parseInt(m.dataset.magazyn, 10) !== targetMag) return false;
                const targetSub = locSub.slice(String(targetMag).length); // "A" lub "B"
                const pods = Array.from(m.querySelectorAll('.pod'));
                const hasSub = pods.some(p => {
                    const hdr = (p.querySelector('.pod-header')?.innerText || '').toUpperCase().trim();
                    return hdr.startsWith('PODMAGAZYN ' + targetSub);
                });
                if (!hasSub) return false;
            } else if (mode === 'ref_ean') {
                const pods = m.querySelectorAll('.pod');
                let found = false;

                pods.forEach(p => {
                    const name = p.querySelector('strong')?.innerText.toLowerCase() || '';
                    const smalls = p.querySelectorAll('.small');
                    let ref = '', ean = '';

                    smalls.forEach(s => {
                        const text = s.innerText.toLowerCase().trim();
                        if (text.startsWith('ref:')) ref = text.replace(/^ref:\s*/i,'');
                        if (text.startsWith('ean:')) ean = text.replace(/^ean:\s*/i,'');
                    });

                    if (name.includes(term) || ref.includes(term) || ean.includes(term)) {
                        found = true;
                    }
                });

                if (!found) return false;
            } else if (term) {
                const pods = m.querySelectorAll('.pod');
                let found = false;

                pods.forEach(p => {
                    const name = p.querySelector('strong')?.innerText.toLowerCase() || '';
                    const smalls = p.querySelectorAll('.small');
                    let ref = '', ean = '';

                    smalls.forEach(s => {
                        const text = s.innerText.toLowerCase().trim();
                        if (text.startsWith('ref:')) ref = text.replace(/^ref:\s*/i,'');
                        if (text.startsWith('ean:')) ean = text.replace(/^ean:\s*/i,'');
                    });

                    if (name.includes(term) || ref.includes(term) || ean.includes(term)) {
                        found = true;
                    }
                });

                if (!found) return false;
            }

            // tylko puste
            if (showEmptyOnly) {
                const hasEmpty = [...m.querySelectorAll('.pod')]
                    .some(p => p.querySelector('.pod-empty'));
                if (!hasEmpty) return false;
            }

            // tylko nowe
            if (showNewOnly) {
                const hasNew = [...m.querySelectorAll('.pod')].some(p => {
                    const isNew = (p.getAttribute('data-is-new') || '0') === '1';
                    if (!isNew) return false;
                    if (!selectedDate) return true;
                    return (p.getAttribute('data-created-date') || '') === selectedDate;
                });
                if (!hasNew) return false;
            }

            return true;
        });

        // sortowanie
        filtered.sort((a, b) => {
            const na = parseInt(a.dataset.magazyn);
            const nb = parseInt(b.dataset.magazyn);
            return sortOrder === 'asc' ? na - nb : nb - na;
        });

        // ukryj wszystkie
        originalOrder.forEach(m => {
            m.style.display = "none";
            m.style.order = 0;
        });

        // pokaż tylko pasujące
        filtered.forEach((m, idx) => {
            m.style.display = "block";
            m.style.order = idx;

            // Zachowaj standardowy układ w magazynie: nie ukrywaj podmagazynów.
            [...m.querySelectorAll('.pod')].forEach(p => {
                p.style.display = "flex";
            });
        });
    }

    filterInput.addEventListener('input', applyFilter);
    emptyOnly.addEventListener('change', applyFilter);
    newOnly.addEventListener('change', applyFilter);
    newDate.addEventListener('change', applyFilter);
    sortSelect.addEventListener('change', applyFilter);

    emptyOnly.addEventListener('change', () => {
        if (emptyOnly.checked) {
            newOnly.checked = false;
            newDate.value = '';
            newDateWrap.style.display = 'none';
        }
    });
    newOnly.addEventListener('change', () => {
        if (newOnly.checked) {
            emptyOnly.checked = false;
            newDateWrap.style.display = 'inline-flex';
        } else {
            newDate.value = '';
            newDateWrap.style.display = 'none';
        }
    });
}

/* =========================
   MODALE / CRUD
   ========================= */

function openAdd(m) {
    document.getElementById('modalTitle').textContent = 'Dodaj produkt do magazynu ' + m;
    document.getElementById('action').value = 'add';
    document.getElementById('id').value = '';
    document.getElementById('location').value = m;
    document.getElementById('name').value = '';
    document.getElementById('reference').value = '';
    document.getElementById('ean').value = '';
    document.getElementById('selectedSub').value = '';
    resetSubButtons();

    document.getElementById('modal').style.display = 'flex';
}

function openEditFromButton(b) {
    document.getElementById('modalTitle').textContent = 'Edytuj produkt';
    document.getElementById('action').value = 'edit';
    document.getElementById('id').value = b.dataset.id;
    document.getElementById('location').value = b.dataset.loc;
    document.getElementById('name').value = b.dataset.name;
    document.getElementById('reference').value = b.dataset.ref;
    document.getElementById('ean').value = b.dataset.ean;
    selectSub(b.dataset.sub);

    document.getElementById('modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function selectSub(s) {
    document.getElementById('selectedSub').value = s;
    ['A', 'B'].forEach(x => {
        const b = document.getElementById('btn' + x);
        b.classList.toggle('add', x === s);
        b.classList.toggle('gray', x !== s);
    });
}

function resetSubButtons() {
    ['A', 'B'].forEach(x => {
        const b = document.getElementById('btn' + x);
        b.classList.remove('add');
        b.classList.add('gray');
    });
}

const formModalEl = document.getElementById('formModal');
if (formModalEl) formModalEl.addEventListener('submit', async e => {
    e.preventDefault();

    const subEl = document.getElementById('selectedSub');
    const sub = subEl ? subEl.value : '';
    if (!sub) return alert('Wybierz podmagazyn (A lub B)');

    const actionEl = document.getElementById('action');
    const idEl = document.getElementById('id');
    const locationEl = document.getElementById('location');
    const nameEl = document.getElementById('name');
    const referenceEl = document.getElementById('reference');
    const eanEl = document.getElementById('ean');

    const payload = {
        action: actionEl ? actionEl.value : '',
        id: idEl ? idEl.value : '',
        location: (locationEl ? locationEl.value : '') + sub,
        name: nameEl ? nameEl.value : '',
        reference: referenceEl ? referenceEl.value : '',
        ean: eanEl ? eanEl.value : ''
    };

    const res = await fetch(location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const j = await res.json();
    if (j.success) location.reload();
    else alert(j.error || 'Błąd');
});

async function deleteProduct(id) {
    if (!confirm('Usunąć produkt?')) return;

    const res = await fetch(location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id })
    });

    const j = await res.json();
    if (j.success) location.reload();
    else alert(j.error || 'Błąd');
}

/* =========================
   IMPORT CSV
   ========================= */

const openImportBtn = document.getElementById('openImportBtn');
if (openImportBtn) openImportBtn.onclick = () => {
    const importModalEl = document.getElementById('importModal');
    if (importModalEl) importModalEl.style.display = 'flex';
};

const cancelImportBtn = document.getElementById('cancelImportBtn');
if (cancelImportBtn) cancelImportBtn.onclick = () => {
    const importModalEl = document.getElementById('importModal');
    if (importModalEl) importModalEl.style.display = 'none';
};

const importFormEl = document.getElementById('importForm');
if (importFormEl) importFormEl.addEventListener('submit', async e => {
    e.preventDefault();

    const fileInput = document.querySelector('#importForm input[type=file]');
    if (!fileInput || !fileInput.files.length) return alert('Wybierz plik CSV');

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    const statusDiv = document.getElementById('importStatus');
    if (statusDiv) statusDiv.textContent = 'Import...';

    const res = await fetch(location.pathname, { method: 'POST', body: formData });
    const j = await res.json();

    if (j.success) {
        if (statusDiv) statusDiv.textContent = j.message;
        setTimeout(() => location.reload(), 1000);
    } else {
        if (statusDiv) statusDiv.textContent = j.error || 'Błąd';
    }
});

/* =========================
   PORÓWNANIE Z PLIKIEM XLSX
   ========================= */

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

let lastCompareResult = null;
const COMPARE_STORE_NUMER = <?= json_encode((string)$store_numer, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

const openCompareBtn = document.getElementById('openCompareBtn');
if (openCompareBtn) openCompareBtn.onclick = () => {
    const m = document.getElementById('compareModal');
    if (m) m.style.display = 'flex';
};

const cancelCompareBtn = document.getElementById('cancelCompareBtn');
if (cancelCompareBtn) cancelCompareBtn.onclick = () => {
    const m = document.getElementById('compareModal');
    if (m) m.style.display = 'none';
};

const closeCompareResultBtn = document.getElementById('closeCompareResultBtn');
if (closeCompareResultBtn) closeCompareResultBtn.onclick = () => {
    const m = document.getElementById('compareResultModal');
    if (m) m.style.display = 'none';
};

const compareFormEl = document.getElementById('compareForm');
if (compareFormEl) compareFormEl.addEventListener('submit', async e => {
    e.preventDefault();

    const fileInput = document.querySelector('#compareForm input[type=file]');
    if (!fileInput || !fileInput.files.length) return alert('Wybierz plik XLSX');

    const formData = new FormData();
    formData.append('compare_file', fileInput.files[0]);

    const statusDiv = document.getElementById('compareStatus');
    if (statusDiv) statusDiv.textContent = 'Porównywanie...';

    let j;
    try {
        const res = await fetch(location.pathname, { method: 'POST', body: formData });
        j = await res.json();
    } catch (err) {
        if (statusDiv) statusDiv.textContent = 'Błąd połączenia';
        return;
    }

    if (!j.success) {
        if (statusDiv) statusDiv.textContent = j.error || 'Błąd';
        return;
    }

    if (statusDiv) statusDiv.textContent = '';
    fileInput.value = '';
    document.getElementById('compareModal').style.display = 'none';
    showCompareResult(j);
});

function showCompareResult(j) {
    lastCompareResult = j;
    const summary = document.getElementById('compareSummary');
    if (summary) summary.textContent = `Znaleziono w magazynie: ${j.found.length} z ${j.total} produktów z pliku (brak: ${j.not_found.length}).`;

    const foundList = document.getElementById('compareFoundList');
    if (foundList) {
        foundList.innerHTML = j.found.length ? j.found.map(it =>
            `<div style="padding:8px 0;border-bottom:1px solid #eee">
                <div><strong>${escapeHtml(it.ref)}</strong> — ${escapeHtml(it.opis)}</div>
                <div class="small" style="color:#6b7280">Miejsce: ${escapeHtml((it.lokalizacje || []).join(', ') || '—')}${it.stock !== null ? ' • stock w pliku: ' + escapeHtml(it.stock) : ''}</div>
            </div>`
        ).join('') : '<div class="small">Brak.</div>';
    }

    const notFoundList = document.getElementById('compareNotFoundList');
    if (notFoundList) {
        notFoundList.innerHTML = j.not_found.length ? j.not_found.map(it =>
            `<div style="padding:8px 0;border-bottom:1px solid #eee">
                <div><strong>${escapeHtml(it.ref)}</strong> — ${escapeHtml(it.opis)}</div>
                ${it.stock !== null ? `<div class="small" style="color:#6b7280">stock w pliku: ${escapeHtml(it.stock)}</div>` : ''}
            </div>`
        ).join('') : '<div class="small">Brak.</div>';
    }

    document.getElementById('compareResultModal').style.display = 'flex';
}

function buildCompareReportHtml(j) {
    const dateStr = new Date().toLocaleString('pl-PL');

    const rowsHtml = (list, withLoc) => list.length
        ? list.map(it => `<tr>
            <td>${escapeHtml(it.ref)}</td>
            <td>${escapeHtml(it.opis)}</td>
            <td>${withLoc ? escapeHtml((it.lokalizacje || []).join(', ') || '—') : (it.stock !== null ? escapeHtml(it.stock) : '—')}</td>
        </tr>`).join('')
        : '<tr><td colspan="3" style="text-align:center;color:#777">Brak</td></tr>';

    return `<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Raport porównania — Sklep ${escapeHtml(COMPARE_STORE_NUMER)}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:24px;}
  h1{font-size:18px;margin-bottom:4px;}
  .meta{color:#555;font-size:12px;margin-bottom:18px;}
  .summary{font-weight:700;margin-bottom:18px;font-size:14px;}
  h2{font-size:14px;margin:22px 0 8px;border-bottom:2px solid #111;padding-bottom:4px;}
  table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:10px;}
  th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;word-break:break-word;}
  th{background:#f0f0f0;}
  .found h2{color:#16a34a;border-color:#16a34a;}
  .notfound h2{color:#dc2626;border-color:#dc2626;}
  tr{break-inside:avoid;}
  @media print { body{margin:10mm;} }
</style>
</head>
<body>
  <h1>Raport porównania magazynu z plikiem</h1>
  <div class="meta">Sklep ${escapeHtml(COMPARE_STORE_NUMER)} • wygenerowano ${escapeHtml(dateStr)}</div>
  <div class="summary">Znaleziono w magazynie: ${j.found.length} z ${j.total} produktów z pliku (brak: ${j.not_found.length}).</div>

  <div class="found">
    <h2>W magazynie (${j.found.length})</h2>
    <table>
      <thead><tr><th>Referencja</th><th>Opis</th><th>Lokalizacja</th></tr></thead>
      <tbody>${rowsHtml(j.found, true)}</tbody>
    </table>
  </div>

  <div class="notfound">
    <h2>Brak w magazynie (${j.not_found.length})</h2>
    <table>
      <thead><tr><th>Referencja</th><th>Opis</th><th>Stock w pliku</th></tr></thead>
      <tbody>${rowsHtml(j.not_found, false)}</tbody>
    </table>
  </div>
</body>
</html>`;
}

function printCompareReport() {
    if (!lastCompareResult) return;

    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.opacity = '0';
    iframe.setAttribute('aria-hidden', 'true');
    document.body.appendChild(iframe);

    const cleanup = () => { try { iframe.remove(); } catch (e) {} };

    iframe.onload = () => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(cleanup, 15000);
        } catch (e) {
            cleanup();
            alert('Nie udało się uruchomić druku.');
        }
    };

    iframe.srcdoc = buildCompareReportHtml(lastCompareResult);
}

const printCompareBtn = document.getElementById('printCompareBtn');
if (printCompareBtn) printCompareBtn.addEventListener('click', printCompareReport);

const needsInit = <?= $hasProducts ? 'false' : 'true' ?>;
if (needsInit) {
    const initModalEl = document.getElementById('initModal');
    if (initModalEl) initModalEl.style.display = 'flex';
}

const initFormEl = document.getElementById('initForm');
if (initFormEl) initFormEl.addEventListener('submit', async e => {
    e.preventDefault();
    const initSlotsEl = document.getElementById('initSlots');
    const raw = (initSlotsEl ? initSlotsEl.value : '').trim();
    const slots = parseInt(raw, 10);
    if (!Number.isFinite(slots) || slots < 1 || slots > 500) return alert('Podaj poprawną ilość (1-500)');

    const res = await fetch(location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'init', slots })
    });

    const j = await res.json();
    if (j.success) location.reload();
    else alert(j.error || 'Błąd');
});

const printListBtn = document.getElementById('printListBtn');
if (printListBtn) printListBtn.addEventListener('click', () => {
    const url = 'print_products.php?autoprint=1&ts=' + Date.now();
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.opacity = '0';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.src = url;
    document.body.appendChild(iframe);

    const cleanup = () => {
        try { iframe.remove(); } catch (e) {}
    };

    iframe.onload = () => {
        try {
            iframe.contentWindow.focus();
            setTimeout(cleanup, 15000);
        } catch (e) {
            cleanup();
            alert('Nie udało się uruchomić druku.');
        }
    };
});


</script>
</body>
</html>
