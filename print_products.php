<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'db.php';
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['store_id'])) {
    header('Location: index.php');
    exit;
}

$store_id = (int)$_SESSION['store_id'];
$store_numer = $_SESSION['store_numer'] ?? '';
$store_name = $_SESSION['store_name'] ?? '';

$stmt = $pdo->prepare('SELECT location, sub, name, reference, ean FROM products WHERE store_id=? ORDER BY location+0, sub');
$stmt->execute([$store_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byLocSub = [];
$maxLoc = 0;
foreach ($rows as $r) {
    $loc = (int)($r['location'] ?? 0);
    $sub = strtoupper((string)($r['sub'] ?? ''));
    if ($loc > $maxLoc) $maxLoc = $loc;
    if ($loc <= 0 || ($sub !== 'A' && $sub !== 'B')) continue;
    $byLocSub[$loc][$sub] = $r;
}

function is_placeholder_row(array $r): bool {
    $name = strtolower(trim((string)($r['name'] ?? '')));
    $ref = strtolower(trim((string)($r['reference'] ?? '')));
    $ean = strtolower(trim((string)($r['ean'] ?? '')));
    return ($name === 'brak produktu' && $ref === 'brak produktu' && $ean === 'brak produktu') ||
           ($name === 'brak' && $ref === 'brak' && $ean === 'brak');
}

function clean_ean(?string $ean): string {
    $digits = preg_replace('/\D+/', '', (string)$ean);
    return $digits ?? '';
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Drukuj listę produktów — Sklep <?=htmlspecialchars($store_numer)?></title>
  <style>
    :root{--border:#d1d5db;--muted:#6b7280;--text:#111827}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;margin:24px;color:var(--text)}
    header{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:14px}
    h1{font-size:18px;margin:0}
    .meta{font-size:12px;color:var(--muted);text-align:right;white-space:nowrap}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    th,td{border:1px solid var(--border);padding:6px 8px;vertical-align:middle}
    th{background:#f3f4f6;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
    td{font-size:12px}
    .col-loc{width:60px;white-space:nowrap;font-weight:700}
    .col-name{width:26%}
    .col-ref{width:100px;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
    .col-ean{width:110px;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
    .col-barcode{width:170px;overflow:hidden}
    .col-notes{white-space:normal}
    .muted{color:var(--muted)}
    .notes{height:84px}
    .barcode{display:block;width:100%;max-width:170px;height:40px;overflow:hidden}

    @media print {
      @page {
        size: A4 landscape;
        margin: 10mm 10mm 14mm 10mm;
        @bottom-center {
          content: "Strona " counter(page) " / " counter(pages);
          font-size: 10px;
          color: #6b7280;
        }
      }
      body{margin:0}
      header{margin-bottom:8mm}
      a,button{display:none !important}
      thead{display:table-header-group}
      tfoot{display:table-footer-group}
      tr{page-break-inside:avoid}
      .pagedjs_blank_page{display:none !important}
    }
  </style>
</head>
<body>
  <header>
    <div>
      <h1>Lista produktów — Sklep <?=htmlspecialchars($store_numer)?><?= $store_name ? ' — '.htmlspecialchars($store_name) : '' ?></h1>
      <div class="meta">Wygenerowano: <?=htmlspecialchars(date('Y-m-d H:i'))?></div>
    </div>
  </header>

  <?php if ($maxLoc <= 0): ?>
    <div class="muted">Brak danych do wydruku (brak rekordów dla sklepu).</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th class="col-loc">Miejsce</th>
          <th class="col-name">Nazwa</th>
          <th class="col-ref">Ref.</th>
          <th class="col-ean">EAN</th>
          <th class="col-barcode">Kod kreskowy</th>
          <th class="col-notes">Notatki</th>
        </tr>
      </thead>
      <tbody>
        <?php for ($loc = 1; $loc <= $maxLoc; $loc++): ?>
          <?php foreach (['A','B'] as $sub): ?>
            <?php
              $r = $byLocSub[$loc][$sub] ?? null;
              $isEmpty = true;
              $name = '';
              $ref = '';
              $ean = '';
              if ($r) {
                  $isEmpty = is_placeholder_row($r);
                  $name = (string)($r['name'] ?? '');
                  $ref = (string)($r['reference'] ?? '');
                  $ean = (string)($r['ean'] ?? '');
              }
              $eanDigits = clean_ean($ean);
              $eanOk = (strlen($eanDigits) === 13 || strlen($eanDigits) === 8);
            ?>
            <tr>
              <td class="col-loc"><?=htmlspecialchars((string)$loc . $sub)?></td>
              <td class="col-name"><?= $isEmpty ? '<span class="muted">Brak produktu</span>' : htmlspecialchars($name) ?></td>
              <td class="col-ref"><?= $isEmpty ? '' : htmlspecialchars($ref) ?></td>
              <td class="col-ean"><?= $isEmpty ? '' : htmlspecialchars($eanDigits) ?></td>
              <td class="col-barcode">
                <?php if (!$isEmpty && $eanOk): ?>
                  <svg class="barcode" data-ean="<?=htmlspecialchars($eanDigits, ENT_QUOTES)?>"></svg>
                <?php endif; ?>
              </td>
              <td class="col-notes"><div class="notes"></div></td>
            </tr>
          <?php endforeach; ?>
        <?php endfor; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <script>
    function renderBarcodes(){
      const svgs = document.querySelectorAll('svg.barcode[data-ean]');
      svgs.forEach(svg => {
        const ean = svg.getAttribute('data-ean') || '';
        const fmt = (ean.length === 8) ? 'EAN8' : 'EAN13';
        try {
          JsBarcode(svg, ean, {
            format: fmt,
            displayValue: false,
            margin: 0,
            height: 40,
            width: 1.6,
            flat: true
          });
        } catch (e) {
          // ignore invalid EAN
        }
      });
    }

    window.addEventListener('load', () => {
      const autoPrint = new URLSearchParams(location.search).get('autoprint') === '1';

      const finish = () => {
        renderBarcodes();
        if (autoPrint) setTimeout(() => window.print(), 300);
      };

      if (window.PagedPolyfill) {
        // Paged.js jest załadowany — czekaj aż skończy przebudowę DOM
        document.addEventListener('pagedjs:rendered', finish);
      } else {
        // Brak Paged.js — renderuj od razu
        finish();
      }
    });
  </script>
</body>
</html>
