<?php
session_start();

$_SESSION = [];
session_unset();
session_destroy();

// força o navegador a não reutilizar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header("Location: login.php");
exit;