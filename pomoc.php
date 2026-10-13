<?php
/**
 * pomoc.php — Strona pomocy responsywna z wieloma screenshotami per sekcja.
 */

$screenshots = [
    'install'   => [
        'images/instal.png',
        'images/instal2.png',
        'images/instal3.png',
        'images/instal4.png',
        'images/instal5.png',
   
    ],
    'generate'  => [
        'images/szuk.png',
        'images/szuk1.png',
        
    ],
    'panel'     => [
        'images/fisz.png',
        'images/fisz1.pmg',
        
    ],
];

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>

<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <meta name="description" content="Pomoc — instrukcje instalacji, skanowania i obsługi panelu aplikacji">
  <link rel="icon" type="image/png" href="images/ico.png" width="80" height="80">
  <title>Pomoc — Expo Dywany</title>
  <style>
    :root{
      --bg:#ffffff; --fg:#111827; --muted:#6b7280; --accent:#0ea5a4; --card:#f9fafb;
      --radius:12px; --gap:16px; --max-width:1100px;
      --container-padding:18px;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }
    @media (prefers-color-scheme: dark){
      :root{ --bg:#0b1220; --fg:#e6eef6; --muted:#94a3b8; --accent:#34d399; --card:#081025; }
    }
    *{box-sizing:border-box;max-width:100%}
    html,body{height:100%;overflow-x:hidden}
    body{margin:0;background:var(--bg);color:var(--fg);line-height:1.45}

    .shell{max-width:var(--max-width);margin:28px auto;padding:var(--container-padding)}
    header{display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}
    .brand{flex:1}
    .brand h1{margin:0;font-size:1.25rem}
    .brand p{margin:0;color:var(--muted);font-size:0.9rem}

    .layout{display:grid;grid-template-columns:280px 1fr;gap:20px}
    @media (max-width:880px){.layout{grid-template-columns:1fr}}

    nav.card{background:var(--card);padding:12px;border-radius:12px}
    .nav-list{list-style:none;padding:0;margin:0}
    .nav-list li{margin:6px 0}
    .nav-list a{display:block;padding:8px;border-radius:8px;color:var(--fg)}
    .nav-list a:hover{background:rgba(0,0,0,0.04)}

    .mobile-toggle{display:none}
    @media (max-width:880px){
      .mobile-toggle{display:inline-flex;margin-bottom:10px}
      nav.card{display:none}
      nav.card.open{display:block}
    }

    section.card{background:var(--card);padding:18px;border-radius:12px;margin-bottom:16px}
    h2{margin-top:0}

    .steps{counter-reset:step;list-style:none;padding:0;margin:0}
    .steps li{display:flex;gap:12px;padding:12px;border-radius:8px;margin-bottom:10px;background:linear-gradient(180deg,rgba(0,0,0,0.02),transparent);align-items:flex-start}
    .steps li::before{counter-increment:step;content:counter(step);min-width:36px;height:36px;border-radius:999px;background:var(--accent);color:white;display:inline-flex;align-items:center;justify-content:center;font-weight:600}

    .screenshot{max-width:100%;height:auto;border-radius:8px;border:1px solid rgba(0,0,0,0.06)}
    .screenshot-wrap{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px;margin-top:12px}

    footer{margin-top:18px;color:var(--muted);font-size:0.9rem}
  </style>
</head>
<body>
<div class="shell">
  <header>
    <div style="width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#06b6d4);display:flex;align-items:center;justify-content:center;color:white;font-weight:700">A</div>
    <div class="brand">
      <h1>Pomoc — Aplikacja</h1>
      <p class="muted">Instrukcje instalacji, skanowanie i oznaczanie produktów</p>
    </div>
    <div style="margin-left:auto">
      <button class="mobile-toggle" id="mobileMenuBtn">☰ Menu</button>
    </div>
  </header>

  <div class="layout">
    <nav id="sideNav" class="card">
      <ul class="nav-list">
        <li><a href="#installation">Instalacja aplikacji</a></li>
        <li><a href="#generation">Skanowanie Produktów</a></li>
        <li><a href="#panel">Generowanie fisz produktowych</a></li>
        <li><a href="#faq">Kontakt</a></li>
      </ul>
    </nav>

    <main>
      <section id="installation" class="card">
        <h2>Instalacja aplikacji (urządzenia mobilne)</h2>
        <ol class="steps">
          <li>Otwórz na telefonie poniższy link<a href="https://uslugiszycia.cba.pl/dywany2/index_public.html"> EXPO DYWANY</a></li>
          <li>Kliknij dodaj do ekranu głównego w wyskakującym oknie</li>
          <li>Kliknij Zainstaluj</li>
          <li>Kliknij Zainstaluj ponownie</li>
          <li>Aplikacja pojawi się na ekranie głównym i będzie gotowa do użycia</li>
        </ol>
        <div class="screenshot-wrap">
          <?php foreach($screenshots['install'] as $path): ?>
            <img src="<?= esc($path) ?>" class="screenshot" alt="Zrzut instalacji">
          <?php endforeach; ?>
        </div>
      </section>

      <section id="generation" class="card">
        <h2>Wyszukiwanie i skanowanie produktów</h2>
        <ol class="steps">
          <li>Po załadowaniu aplikacji na samym początku wpisujemy numer swojego sklepu może być w formie 0** lub ** np. 038 lub 38</li>
          <li>Po zapisaniu w pole wyszukiwania możemy wpisać numer miejsca magazynowego w formie 1a lub 24b itp, lub wpisać referencję lub ean produktu</li>
          <li>Możemy użyc opcji skanuj w celu zeskanowania produktu</li>
          <li>GRUPA LOGISTYCZNA PO ZESKANOWANIU KODU TOWARU, OKLEJA GO ŻÓŁTĄ NAKLEJKĄ Z NUMEREM MIEJSCA I ODKŁADA NA REGAŁ</li>
          
        </ol>
        <div class="screenshot-wrap">
          <?php foreach($screenshots['generate'] as $path): ?>
            <img src="<?= esc($path) ?>" class="screenshot" alt="Zrzut generowania">
          <?php endforeach; ?>
        </div>
      </section>

      <section id="panel" class="card">
        <h2>Generowanie fisz produktowych(dla Doradców Klienta)</h2>
        <ul>
          <li>Klikamy w opcję Generuj fiszke</li>
          <li>Wpisujemy numer miejsca magazynowego,refencję lub ean produktu i klikamy dodaj. Możemy też zeskanować kod produktu</li>
          <li>Pojawi nam sie lista dodanych produktów gdy wpiszemy wszystkie, zostanie skopiowana do schowka gotowa formuła która wkleimy do Kakemono i w ciągu 3 sekund nastąpi do niego przekierowanie</li>
        </ul>
        <div class="screenshot-wrap">
          <?php foreach($screenshots['panel'] as $path): ?>
            <img src="<?= esc($path) ?>" class="screenshot" alt="Zrzut panelu">
          <?php endforeach; ?>
        </div>
      </section>

      <section id="faq" class="card">
        <h2>Kontakt</h2>
        <ul>
          <li>W razie problemów wyślij email pod abaluch@leroymerlin.pl lub zadzwoń pod numer <br>512 - 489 -469</li>
        </ul>
      </section>

      <footer>
        <div class="muted">Wersja dokumentu: 1.3</div>
      </footer>
    </main>
  </div>
</div>

<script>
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const sideNav = document.getElementById('sideNav');
  mobileBtn.addEventListener('click', ()=>{
    sideNav.classList.toggle('open');
  });
</script>
</body>
</html>		