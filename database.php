<?php
$servername = "localhost";
$port = "3306";
$username = "root";
$password = "";
$dbname = "php_projekt";

// kapcsolódás
$conn = mysqli_connect($servername, $username, $password, $dbname, $port);

// checkoljuk létrejött-e a kapcsolat
if (!$conn) {
    die("Sikertelen kapcsolódás: " . mysqli_connect_error());
}

// karakterkódolás
mysqli_set_charset($conn, "utf8mb4");

$cleanupLockFile = __DIR__ . '/uploads/.cleanup_lock';
$cleanupInterval = 60; 

if (!@file_exists($cleanupLockFile) || (time() - @filemtime($cleanupLockFile)) >= $cleanupInterval) {
    @touch($cleanupLockFile);
    mysqli_query($conn, "DELETE FROM esemenyek WHERE time_end < NOW()");
}
?>