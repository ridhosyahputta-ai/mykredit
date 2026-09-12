<?php
session_start();

// 1. Pengecekan Session Wajib
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. Lempar Admin ke habitatnya
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
    exit();
}

// 3. Include Koneksi
include '../conn.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['id_pengguna'];
$alert = '';

// 4. Ambil Data User 
$query_user = mysqli_query($conn, "SELECT * FROM pengguna WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query_user);
$nama_user = $user['username'] ?? 'Nasabah';

// 5. Proses Update Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $notif_email = isset($_POST['notif_email']) ? 1 : 0;
    $notif_sms = isset($_POST['notif_sms']) ? 1 : 0;
    $notif_whatsapp = isset($_POST['notif_whatsapp']) ? 1 : 0;

    // Simpan ke database
    $query_update = "UPDATE pengguna SET notif_email = '$notif_email', notif_sms = '$notif_sms' WHERE id = '$user_id'";
    
    if (mysqli_query($conn, $query_update)) {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>Pengaturan berhasil disimpan!</p></div>";

        // Refresh data user
        $query_user = mysqli_query($conn, "SELECT * FROM pengguna WHERE id = '$user_id'");
        $user = mysqli_fetch_assoc($query_user);
    } else {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-linestrong bg-paperalt rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-inkfaint shrink-0'><circle cx='12' cy='12' r='9'/><path d='M12 8v4M12 16h.01'/></svg><p class='text-sm font-medium text-inksoft'>Gagal menyimpan pengaturan.</p></div>";
    }
}

// 6. Proses Ubah Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    // Ambil password dari database
    $query_pwd = mysqli_query($conn, "SELECT password FROM pengguna WHERE id = '$user_id'");
    $data_pwd = mysqli_fetch_assoc($query_pwd);

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-linestrong bg-paperalt rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-inkfaint shrink-0'><circle cx='12' cy='12' r='9'/><path d='M12 8v4M12 16h.01'/></svg><p class='text-sm font-medium text-inksoft'>Semua field harus diisi.</p></div>";
    } elseif (!password_verify($password_lama, $data_pwd['password'])) {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-linestrong bg-paperalt rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-inkfaint shrink-0'><circle cx='12' cy='12' r='9'/><path d='M12 8v4M12 16h.01'/></svg><p class='text-sm font-medium text-inksoft'>Password lama tidak sesuai.</p></div>";
    } elseif ($password_baru !== $konfirmasi_password) {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-linestrong bg-paperalt rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-inkfaint shrink-0'><circle cx='12' cy='12' r='9'/><path d='M12 8v4M12 16h.01'/></svg><p class='text-sm font-medium text-inksoft'>Password baru dan konfirmasi tidak cocok.</p></div>";
    } elseif (strlen($password_baru) < 6) {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-linestrong bg-paperalt rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-inkfaint shrink-0'><circle cx='12' cy='12' r='9'/><path d='M12 8v4M12 16h.01'/></svg><p class='text-sm font-medium text-inksoft'>Password minimal 6 karakter.</p></div>";
    } else {
        $hashed_pwd = password_hash($password_baru, PASSWORD_DEFAULT);
        $query_update_pwd = "UPDATE pengguna SET password = '$hashed_pwd' WHERE id = '$user_id'";

        if (mysqli_query($conn, $query_update_pwd)) {
            $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>Password berhasil diubah!</p></div>";
        } else {
            $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-linestrong bg-paperalt rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-inkfaint shrink-0'><circle cx='12' cy='12' r='9'/><path d='M12 8v4M12 16h.01'/></svg><p class='text-sm font-medium text-inksoft'>Gagal mengubah password.</p></div>";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MyKredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Public Sans', 'sans-serif'], serif: ['Fraunces', 'serif'] },
                    colors: {
                        cream: 'var(--cream)', paper: 'var(--paper)', paperalt: 'var(--paper-alt)',
                        charcoal: 'var(--charcoal)', ink: 'var(--ink)', inksoft: 'var(--ink-soft)', inkfaint: 'var(--ink-faint)',
                        line: 'var(--line)', linestrong: 'var(--line-strong)',
                        sidebarink: 'var(--sidebar-ink)', sidebarmuted: 'var(--sidebar-muted)',
                        accent: 'var(--accent)', accentdark: 'var(--accent-dark)', accentsoft: 'var(--accent-soft)', accentsoftink: 'var(--accent-soft-ink)',
                        danger: 'var(--danger)', dangersoft: 'var(--danger-soft)'
                    },
                    borderRadius: { DEFAULT: '14px', sm: '8px', lg: '18px' }
                }
            }
        }
    </script>
</head>
<body class="bg-cream text-ink font-sans h-screen flex overflow-hidden">

    <div class="lg:hidden fixed top-0 w-full bg-charcoal z-50 px-5 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="logo-mark w-8 h-8 flex items-center justify-center text-sm font-bold rounded-sm">M</div>
            <span class="font-serif text-lg font-semibold text-sidebarink">MyKredit</span>
        </div>
        <button id="mobile-menu-btn" class="text-sidebarmuted hover:text-sidebarink focus:outline-none transition-colors">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
    </div>

    <aside id="sidebar" class="app-sidebar w-72 fixed inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-out z-40 lg:static flex flex-col justify-between">

        <div>
            <div class="hidden lg:flex items-center gap-3 px-8 pt-9 pb-8">
                <div class="logo-mark w-9 h-9 flex items-center justify-center text-base font-bold rounded-sm">M</div>
                <span class="font-serif text-xl font-semibold text-sidebarink tracking-tight">MyKredit</span>
            </div>

            <nav class="px-5 mt-16 lg:mt-2 space-y-0.5">
                <p class="px-3 text-[10px] font-bold text-sidebarmuted uppercase tracking-[0.14em] mb-3">Menu Utama</p>

                <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="layout-dashboard" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Dashboard Utama</span>
                </a>
                <a href="pengajuan_kredit.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="file-text" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Pengajuan Kredit</span>
                </a>
                <a href="riwayat_transaksi.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="history" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Riwayat Transaksi</span>
                </a>
                <a href="pembayaran_cicilan.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="banknote" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Pembayaran Cicilan</span>
                </a>

                <p class="px-3 text-[10px] font-bold text-sidebarmuted uppercase tracking-[0.14em] mb-3 mt-8">Pengaturan</p>

                <a href="profil_akun.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="user" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Profil</span>
                </a>
                <a href="settings.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="settings" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Settings</span>
                </a>
            </nav>
        </div>

        <div class="p-5 border-t border-white/[.06]">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-3 rounded-sm text-[#C97D6B] hover:bg-white/[.04] transition-colors">
                <i data-lucide="log-out" class="w-[18px] h-[18px]"></i>
                <span class="text-sm font-semibold">Keluar Aplikasi</span>
            </a>
        </div>
    </aside>

    <div id="sidebar-overlay" class="overlay-scrim fixed inset-0 z-30 hidden lg:hidden transition-opacity opacity-0"></div>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto no-scrollbar pt-20 lg:pt-0">

        <div class="p-5 md:p-8 lg:p-12 max-w-3xl mx-auto w-full">

            <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2">Pengaturan Akun</p>
            <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight mb-10">Settings</h1>

            <?= $alert ?>

            <!-- Notifikasi -->
            <section class="mb-12">
                <h2 class="font-serif text-xl font-semibold text-ink mb-1">Notifikasi</h2>
                <p class="text-sm text-inksoft mb-6">Pilih cara Anda ingin menerima pemberitahuan penting.</p>

                <form method="POST">
                    <div class="divide-y divide-line border-t border-b border-line mb-6">
                        <div class="flex items-center justify-between py-5">
                            <div>
                                <p class="font-semibold text-ink text-sm">Notifikasi Email</p>
                                <p class="text-sm text-inksoft mt-0.5">Terima notifikasi penting melalui email</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notif_email" value="1" <?= ($user['notif_email'] ?? 0) ? 'checked' : '' ?>>
                                <span class="track"></span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between py-5">
                            <div>
                                <p class="font-semibold text-ink text-sm">Notifikasi SMS</p>
                                <p class="text-sm text-inksoft mt-0.5">Terima notifikasi penting melalui SMS</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notif_sms" value="1" <?= ($user['notif_sms'] ?? 0) ? 'checked' : '' ?>>
                                <span class="track"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="update_settings" class="btn btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Simpan
                    </button>
                </form>
            </section>

            <div class="border-t border-line mb-12"></div>

            <!-- Keamanan -->
            <section class="mb-10">
                <h2 class="font-serif text-xl font-semibold text-ink mb-1">Ubah Password</h2>
                <p class="text-sm text-inksoft mb-6">Gunakan kombinasi huruf, angka, dan simbol untuk password yang kuat.</p>

                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-7 mb-7">
                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Password Lama</label>
                            <input type="password" name="password_lama" required placeholder="Masukkan password lama"
                                   class="field-line w-full text-sm font-semibold text-ink">
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Password Baru</label>
                            <input type="password" name="password_baru" required placeholder="Minimal 6 karakter"
                                   class="field-line w-full text-sm font-semibold text-ink">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" required placeholder="Ketik ulang password baru"
                                   class="field-line w-full text-sm font-semibold text-ink">
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Ubah Password
                    </button>
                </form>
            </section>

        </div>
    </main>

    <script>
        lucide.createIcons();

        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        let isOpen = false;

        function toggleSidebar() {
            isOpen = !isOpen;
            if (isOpen) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                mobileMenuBtn.innerHTML = '<i data-lucide="x" class="w-6 h-6"></i>';
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
                mobileMenuBtn.innerHTML = '<i data-lucide="menu" class="w-6 h-6"></i>';
            }
            lucide.createIcons();
        }
        mobileMenuBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
    </script>

</body>
</html>
