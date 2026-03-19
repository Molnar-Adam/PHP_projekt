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
?>