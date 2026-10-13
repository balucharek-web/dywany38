<?php
// index.php
require 'db.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass = $_POST['pass'] ?? '';

    if ($login === '' || $pass === '') {
        $err = "Wypełnij wszystkie pola.";
    } else {
        // próbujemy znaleźć sklep po numerze (numer kolumna) lub id
        $stmt = $pdo->prepare("SELECT * FROM sklepy WHERE numer = ? OR id = ?");
        $stmt->execute([$login, $login]);
        $sk = $stmt->fetch();

        if ($sk && $sk['haslo'] !== '') {
            if (password_verify($pass, $sk['haslo'])) {
                $_SESSION['store_id'] = (int)$sk['id'];
                $_SESSION['store_numer'] = $sk['numer'];
                $_SESSION['store_name'] = $sk['nazwa'];
                header('Location: edit_magazyn.php');
                exit;
            } else {
                $err = "Nieprawidłowe hasło.";
            }
        } elseif ($sk && $sk['haslo'] === '') {
            // brak hasła w tabeli — zezwalamy na logowanie bez hasła (opcjonalne)
            $_SESSION['store_id'] = (int)$sk['id'];
            $_SESSION['store_numer'] = $sk['numer'];
            $_SESSION['store_name'] = $sk['nazwa'];
            header('Location: edit_magazyn.php');
            exit;
        } else {
            $err = "Nie znaleziono sklepu.";
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#1f6feb">
<link rel="manifest" href="manifest_php.json">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="icon-512.png">
<title>Logowanie sklepu</title>

<style>
  body{
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
    margin:0;
    background:#f6f7fb;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;    /* PC = środek */
    min-height:100vh;
}

/* TELEFON – podnieś cały moduł logowania wyżej */
@media (max-width: 600px) {
    body {
        justify-content:flex-start;  /* już nie środek */
        padding-top: 60px;           /* wysokość jaką chcesz – można zmienić */
    }
}
  .logo-container{
    text-align:center;
    margin-bottom:20px;
  }
  .logo-container img{
    width:100px;
    height:auto;
  }
  .logo-container h1{
    font-size:20px;
    margin-top:10px;
    color:#222;
    font-weight:600;
  }
  .card{
    background:white;
    padding:24px;
    border-radius:12px;
    box-shadow:0 6px 18px rgba(0,0,0,0.08);
    width:320px;
    max-width:90%;
  }
  input{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ddd;
    border-radius:8px;
  }
  button{
    width:100%;
    padding:10px;
    border-radius:8px;
    border:0;
    background:#1f6feb;
    color:white;
    font-weight:600;
    cursor:pointer;
  }
  button:hover{background:#155dc1;}
  .err{
    color:#b00020;
    margin:6px 0;
  }
  header{
    font-weight:700;
    margin-bottom:8px;
  }
  small{
    color:#666;
  }


 input, button {
  box-sizing: border-box;
}

</style>
</head>
<body>
  <div class="logo-container">
    <img src="logolm.png" alt="Leroy Merlin logo">
    <h1>System Ekspozycji Dywanów</h1>
  </div>

  <div class="card">
    <header>Logowanie — Multiboard</header>
    <form method="post" autocomplete="off">
      <label>Numer sklepu (np. 038) lub ID</label>
      <input name="login" placeholder="038 lub 38" value="<?=htmlspecialchars($_POST['login'] ?? '')?>">
      <label>Hasło</label>
      <input name="pass" type="password" placeholder="Hasło (jeśli ustawione)">
      <?php if ($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>
      <button type="submit">Zaloguj</button>
      <p style="text-align:center;margin-top:10px"><small>Jeśli w bazie sklep nie ma hasła, wpisz numer i kliknij Zaloguj.</small></p>
    </form>
  </div>


<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js')
  .then(() => console.log('✅ Service Worker registered'))
  .catch(err => console.error('❌ SW error:', err));
}
</script>



</body>
</html>
