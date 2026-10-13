<?php
$hash = '';
$plain = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['haslo'])) {
    $plain = $_POST['haslo'];
    $hash = password_hash($plain, PASSWORD_DEFAULT);
}
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Generator hash hasła</title>
<style>
body{
    font-family:system-ui;
    background:#f4f4f4;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}
.card{
    background:#fff;
    padding:20px;
    border-radius:10px;
    width:400px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);
}
input, textarea, button{
    width:100%;
    padding:10px;
    margin-top:10px;
}
textarea{
    font-family:monospace;
    height:80px;
}
button{
    background:#1f6feb;
    color:#fff;
    border:0;
    border-radius:6px;
    cursor:pointer;
}
</style>
</head>
<body>

<div class="card">
    <h3>Hashowanie hasła (PHP)</h3>

    <form method="post">
        <label>Hasło tekstowe:</label>
        <input type="text" name="haslo" value="<?=htmlspecialchars($plain)?>" autofocus>

        <button type="submit">Zahashuj</button>
    </form>

    <?php if ($hash): ?>
        <label>Wygenerowany hash:</label>
        <textarea readonly><?=htmlspecialchars($hash)?></textarea>
    <?php endif; ?>
</div>

</body>
</html>
