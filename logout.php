<?php
session_start();
session_unset();
session_destroy();

/* WYŁĄCZENIE CACHE */
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/* PRZEKIEROWANIE Z PEŁNYM ODŚWIEŻENIEM */
header("Location: index_public.html");
exit;
?>
