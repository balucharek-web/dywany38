<?php
// set_store_password.php
// Umieść obok db.php, uruchom w przeglądarce tylko jednorazowo, potem usuń plik.
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_id = (int)($_POST['store_id'] ?? 0);
    $new_pass = trim($_POST['new_pass'] ?? '');

    if ($store_id <= 0 || $new_pass === '') {
        $msg = "Podaj poprawne ID sklepu i nowe hasło.";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE sklepy SET haslo = ? WHERE id = ?");
        $stmt->execute([$hash, $store_id]);
        $msg = "Zaktualizowano hasło dla sklepu ID={$store_id}. Usuń teraz ten plik z serwera.";
    }
}
?>
<!doctype html>
<html lang="pl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ustaw hasło sklepu</title></head>
<body style="font-family:Arial, sans-serif;max-width:600px;margin:30px auto">
  <h2>Ustaw nowe hasło dla sklepu (jednorazowo)</h2>
  <?php if (!empty($msg)): ?>
    <div style="padding:8px;background:#eef;border:1px solid #ccd; margin-bottom:10px;"><?=htmlspecialchars($msg)?></div>
  <?php endif; ?>
  <form method="post">
    <label>ID sklepu (np. 38)<br><input name="store_id" required style="width:100%;padding:8px"></label><br><br>
    <label>Nowe hasło<br><input name="new_pass" type="password" required style="width:100%;padding:8px"></label><br><br>
    <button type="submit" style="padding:10px 14px">Ustaw hasło</button>
  </form>
  <p style="color:#666;margin-top:14px">Po udanej zmianie usuń plik <code>set_store_password.php</code> z serwera.</p>
</body>
</html>
