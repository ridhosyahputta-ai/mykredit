<?php

// Copy this file to conn.php and fill in your local database credentials.
// conn.php is gitignored so real credentials never get committed.

$host = "localhost";
$user = "root";
$pass = "";
$db   = "kreditku_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

date_default_timezone_set('Asia/Jakarta');

?>