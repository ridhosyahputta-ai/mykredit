<?php
session_start();
include '../conn.php';

if (isset($_SESSION['status_login']) && $_SESSION['status_login'] === true) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "../admin/dashboard.php" : "../main/dashboard.php"));
    exit();
}

$error = '';
$success = isset($_SESSION['register_success']) ? $_SESSION['register_success'] : '';
unset($_SESSION['register_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];

    $query = "SELECT id, username, password, role FROM pengguna WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['status_login'] = true;
            $_SESSION['id_pengguna'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['user_id'] = $row['id'];
            header("Location: " . ($row['role'] === 'admin' ? "../admin/dashboard.php" : "../main/dashboard.php"));
            exit();
        } else { $error = "Password salah!"; }
    } else { $error = "Email tidak terdaftar!"; }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MyKredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .blob { position: absolute; width: 400px; height: 400px; background: linear-gradient(180deg, rgba(16, 185, 129, 0.4) 0%, rgba(5, 150, 105, 0.1) 100%); filter: blur(80px); border-radius: 50%; z-index: -1; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6 relative overflow-hidden">
    
    <div class="blob -top-20 -left-20"></div>
    <div class="blob bottom-0 -right-20" style="background: linear-gradient(180deg, rgba(30, 41, 59, 0.1) 0%, rgba(16, 185, 129, 0.2) 100%);"></div>

    <div class="w-full max-w-md relative">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-600 text-white rounded-2xl shadow-lg mb-4">
                <i class="fa-solid fa-wallet text-3xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">MyKredit</h1>
            <p class="text-slate-500 font-medium">Solusi Kendaraan Impian Anda</p>
        </div>

        <div class="glass p-8 rounded-3xl shadow-2xl">
            <h2 class="text-xl font-bold text-slate-800 mb-6">Selamat Datang Kembali</h2>
            
            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-xl mb-4 text-sm flex items-center gap-2 border border-red-100">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl mb-4 text-sm flex items-center gap-2 border border-emerald-100">
                    <i class="fa-solid fa-circle-check"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">Email Nasabah</label>
                    <div class="relative mt-1">
                        <i class="fa-regular fa-envelope absolute left-4 top-4 text-slate-400"></i>
                        <input type="email" name="email" placeholder="contoh@mail.com" required
                        class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all font-medium text-slate-700">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between">
                        <label class="text-xs font-bold text-slate-400 uppercase ml-1">Password</label>
                        <span class="text-xs font-bold text-slate-300 cursor-not-allowed" title="Fitur belum tersedia">Lupa?</span>
                    </div>
                    <div class="relative mt-1">
                        <i class="fa-solid fa-lock absolute left-4 top-4 text-slate-400"></i>
                        <input type="password" name="password" placeholder="••••••••" required
                        class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all font-medium text-slate-700">
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-emerald-200 transition-all transform hover:-translate-y-1 active:scale-95">
                    Masuk ke MyKredit
                </button>
            </form>

            <p class="text-center mt-8 text-sm font-medium text-slate-500">
                Belum punya akun? <a href="register.php" class="text-emerald-600 font-bold hover:underline underline-offset-4">Daftar Sekarang</a>
            </p>
        </div>
    </div>
</body>
</html>