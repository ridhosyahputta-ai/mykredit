<?php
session_start();

// Hapus semua data yang tersimpan di dalam session
$_SESSION = array();

// Hancurkan cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session secara permanen
session_destroy();

// Alihkan halaman ke login.php sesuai instruksi
header("Location: login.php");
exit();
?>