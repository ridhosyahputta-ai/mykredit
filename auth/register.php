<?php
session_start();
include '../conn.php';

// Jika sudah login, arahkan ke dashboard
if (isset($_SESSION['status_login']) && $_SESSION['status_login'] === true) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "../admin/dashboard.php" : "../main/dashboard.php"));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    $no_hp = mysqli_real_escape_string($conn, trim($_POST['no_hp']));

    // Validasi input
    if (empty($username) || empty($email) || empty($password) || empty($konfirmasi_password) || empty($no_hp)) {
        $error = "Semua kolom wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak sesuai.";
    } elseif ($password !== $konfirmasi_password) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        // Cek email duplikat
        $query_cek = "SELECT id FROM pengguna WHERE email = ?";
        $stmt_cek = mysqli_prepare($conn, $query_cek);
        mysqli_stmt_bind_param($stmt_cek, "s", $email);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);

        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $error = "Email sudah terdaftar. Silakan gunakan email lain.";
        } else {
            // Proses Insert
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
            $default_role = 'user';

            $query_insert = "INSERT INTO pengguna (username, email, password, role, no_hp) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt_insert, "sssss", $username, $email, $password_hashed, $default_role, $no_hp);

            if (mysqli_stmt_execute($stmt_insert)) {
                $_SESSION['register_success'] = "Pendaftaran berhasil! Silakan login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Terjadi kesalahan pada sistem. Silakan coba lagi nanti.";
            }
            mysqli_stmt_close($stmt_insert);
        }
        mysqli_stmt_close($stmt_cek);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - MyKredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Efek Kaca Premium */
        .glass-panel { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255, 255, 255, 0.4); 
        }
        /* Lingkaran Gradasi Latar Belakang */
        .ambient-blob { 
            position: absolute; 
            width: 450px; 
            height: 450px; 
            filter: blur(90px); 
            border-radius: 50%; 
            z-index: -1; 
            opacity: 0.7;
        }
        .blob-1 {
            top: -10%; left: -5%;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.4), rgba(5, 150, 105, 0.1));
        }
        .blob-2 {
            bottom: -15%; right: -5%;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.15), rgba(16, 185, 129, 0.3));
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans">
    
    <div class="ambient-blob blob-1"></div>
    <div class="ambient-blob blob-2"></div>

    <div class="w-full max-w-2xl relative z-10 my-8">
        <div class="glass-panel p-8 sm:p-10 rounded-[2.5rem] shadow-2xl">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 pb-6 border-b border-slate-200/60">
                <div class="flex items-center gap-4">
                    <div class="bg-emerald-600 w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg shadow-emerald-200">
                        <i class="fa-solid fa-user-plus text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-800 tracking-tight">Buat Akun Baru</h2>
                        <p class="text-slate-500 font-medium text-sm mt-0.5">Mulai perjalanan kredit Anda bersama kami</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl mb-6 text-sm flex items-center gap-3 border border-red-100 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1 mb-1.5">Nama Lengkap</label>
                        <div class="relative">
                            <i class="fa-regular fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="username" placeholder="Masukkan nama Anda" required
                                   class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1 mb-1.5">Email Aktif</label>
                        <div class="relative">
                            <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="email" name="email" placeholder="contoh@email.com" required
                                   class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1 mb-1.5">Nomor Telepon</label>
                        <div class="relative">
                            <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="no_hp" placeholder="08123456xxxx" required
                                   class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1 mb-1.5">Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" name="password" placeholder="Minimal 8 karakter" required
                                   class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider ml-1 mb-1.5">Ulangi Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-shield-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="password" name="konfirmasi_password" placeholder="Ketik ulang password" required
                                   class="w-full pl-11 pr-4 py-3.5 bg-white/50 border border-slate-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                    </div>

                    <div class="sm:col-span-2 mt-4 pt-2">
                        <button type="submit" 
                                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-2xl shadow-xl shadow-slate-200 transition-all transform hover:-translate-y-1 active:scale-[0.98]">
                            Daftar Sekarang
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm font-medium text-slate-500">
                    Sudah memiliki akun? 
                    <a href="login.php" class="text-emerald-600 font-bold hover:text-emerald-700 hover:underline underline-offset-4 ml-1 transition-all">
                        Masuk di sini
                    </a>
                </p>
            </div>

        </div>
    </div>

</body>
</html>