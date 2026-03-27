<?php
session_start();
session_destroy();
header("Location: /PHP_projekt/login/index.php");
exit;
?>
