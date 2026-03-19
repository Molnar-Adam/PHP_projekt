<?php
session_start();
ob_start();

header("Location: login/index.php");
exit;