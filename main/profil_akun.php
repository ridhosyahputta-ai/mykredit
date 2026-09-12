<?php
session_start();

// 1. Pengecekan Session Wajib
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
    exit();
}

include '../conn.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['id_pengguna'];
$alert = '';

// 2. Konfigurasi Folder Upload Aman
$upload_dir = '../uploads/profil/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 3. Proses Update Profil & Foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $no_telp = mysqli_real_escape_string($conn, trim($_POST['no_telp'] ?? ''));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));
    
    $del_avatar = $_POST['delete_avatar'] ?? '0';
    $del_sampul = $_POST['delete_sampul'] ?? '0';

    if (empty($username) || empty($email)) {
        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Username dan email wajib diisi.</p></div>";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE pengguna SET username = ?, email = ?, no_hp = ?, alamat = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $no_telp, $alamat, $user_id);
        $update_text = mysqli_stmt_execute($stmt);

        if ($del_avatar === '1') {
            array_map('unlink', glob($upload_dir . "avatar_" . $user_id . ".*"));
            $stmt_del_foto = mysqli_prepare($conn, "UPDATE pengguna SET foto = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del_foto, "i", $user_id);
            mysqli_stmt_execute($stmt_del_foto);
        }
        if ($del_sampul === '1') {
            array_map('unlink', glob($upload_dir . "cover_" . $user_id . ".*"));
            $stmt_del_cover = mysqli_prepare($conn, "UPDATE pengguna SET cover = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del_cover, "i", $user_id);
            mysqli_stmt_execute($stmt_del_cover);
        }

        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                array_map('unlink', glob($upload_dir . "avatar_" . $user_id . ".*"));
                $foto_path = $upload_dir . "avatar_" . $user_id . "." . $ext;
                move_uploaded_file($_FILES['avatar_file']['tmp_name'], $foto_path);
                $stmt_foto = mysqli_prepare($conn, "UPDATE pengguna SET foto = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_foto, "si", $foto_path, $user_id);
                mysqli_stmt_execute($stmt_foto);
            }
        }

        if (isset($_FILES['sampul_file']) && $_FILES['sampul_file']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['sampul_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                array_map('unlink', glob($upload_dir . "cover_" . $user_id . ".*"));
                $cover_path = $upload_dir . "cover_" . $user_id . "." . $ext;
                move_uploaded_file($_FILES['sampul_file']['tmp_name'], $cover_path);
                $stmt_cover = mysqli_prepare($conn, "UPDATE pengguna SET cover = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_cover, "si", $cover_path, $user_id);
                mysqli_stmt_execute($stmt_cover);
            }
        }

        if ($update_text) {
            $_SESSION['alert_success'] = "Profil Anda berhasil diperbarui!";
            header("Location: profil_akun.php");
            exit();
        }
    }
}

if (isset($_SESSION['alert_success'])) {
    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>" . $_SESSION['alert_success'] . "</p></div>";
    unset($_SESSION['alert_success']);
}

$query_user = mysqli_query($conn, "SELECT * FROM pengguna WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query_user);
$nama_user = $user['username'] ?? 'Nasabah';

$v_time = time();
$avatar_search = glob($upload_dir . "avatar_" . $user_id . ".*");
$url_avatar = !empty($avatar_search) ? $avatar_search[0] . "?v=" . $v_time : "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=10b981&color=fff&size=256";

$sampul_search = glob($upload_dir . "cover_" . $user_id . ".*");
$url_sampul = !empty($sampul_search) ? $sampul_search[0] . "?v=" . $v_time : "https://images.unsplash.com/photo-1579546929518-9e396f3cc809?auto=format&fit=crop&w=1000&q=80";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Akun - MyKredit</title>
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

                <a href="profil_akun.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="user" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Profil</span>
                </a>
                <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
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
        <div class="p-5 md:p-8 lg:p-12 max-w-4xl mx-auto w-full">

            <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2">Pengaturan Akun</p>
            <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight mb-10">Profil &amp; Tampilan</h1>

            <?= $alert ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="delete_avatar" id="delete_avatar" value="0">
                <input type="hidden" name="delete_sampul" id="delete_sampul" value="0">

                <input type="file" name="sampul_file" id="sampul_file" accept=".jpg,.jpeg,.png,.webp" class="hidden" onchange="previewSampul(this)">
                <input type="file" name="avatar_file" id="avatar_file" accept=".jpg,.jpeg,.png,.webp" class="hidden" onchange="previewAvatar(this)">

                <div class="relative mb-16">
                    <div class="relative h-48 sm:h-56 bg-paperalt group overflow-hidden rounded-lg">
                        <img id="img_sampul" src="<?= $url_sampul ?>" class="w-full h-full object-cover">
                        <div class="overlay-scrim absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                            <button type="button" onclick="document.getElementById('sampul_file').click()" class="btn btn-primary w-11 h-11 rounded-full flex items-center justify-center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V6a2 2 0 0 1 2-2h2l2-2h4l2 2h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="11" r="3.5"/></svg>
                            </button>
                            <button type="button" onclick="hapusSampul()" class="w-11 h-11 rounded-full flex items-center justify-center border border-white/25 text-white/80 hover:text-white hover:border-white/50 transition-colors">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="absolute -bottom-14 left-6 sm:left-10 w-28 h-28 sm:w-32 sm:h-32 rounded-full border-4 border-cream bg-paperalt group/avatar z-10">
                        <div class="relative w-full h-full rounded-full overflow-hidden">
                            <img id="img_avatar" src="<?= $url_avatar ?>" class="w-full h-full object-cover">
                            <div class="overlay-scrim absolute inset-0 opacity-0 group-hover/avatar:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                <button type="button" onclick="document.getElementById('avatar_file').click()" class="btn btn-primary w-8 h-8 rounded-full flex items-center justify-center">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V6a2 2 0 0 1 2-2h2l2-2h4l2 2h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="11" r="3.5"/></svg>
                                </button>
                                <button type="button" onclick="hapusAvatar()" class="w-8 h-8 rounded-full flex items-center justify-center border border-white/25 text-white/80 hover:text-white hover:border-white/50 transition-colors">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pl-2 sm:pl-4 mb-10">
                    <h2 class="font-serif text-2xl sm:text-3xl font-semibold text-ink"><?= htmlspecialchars($user['username'] ?? 'Nasabah') ?></h2>
                    <p class="text-sm text-inksoft mt-1"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                    <span class="badge badge-approved mt-3"><span class="badge-dot"></span> Nasabah Reguler</span>
                </div>

                <div class="surface p-7 sm:p-10 mb-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-7 mb-8">
                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Username</label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required
                                   class="field-line w-full text-sm font-semibold text-ink">
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Email Aktif</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                                   class="field-line w-full text-sm font-semibold text-ink">
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Nomor Telepon / WA</label>
                            <input type="tel" name="no_telp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="Cth: 081234567890"
                                   class="field-line w-full text-sm font-semibold text-ink">
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1">Alamat Domisili</label>
                        <textarea name="alamat" rows="2" placeholder="Masukkan alamat lengkap domisili Anda"
                                  class="field-line w-full text-sm font-semibold text-ink resize-none"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="pt-7 border-t border-line flex flex-col sm:flex-row gap-3">
                        <button type="submit" name="update_profil" class="btn btn-primary px-6 py-3 text-sm inline-flex items-center justify-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Simpan Perubahan
                        </button>
                        <button type="button" onclick="window.location.reload();" class="btn btn-ghost px-6 py-3 text-sm inline-flex items-center justify-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                            Reset Form
                        </button>
                    </div>
                </div>
            </form>
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

        const defaultAvatar = "https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=10b981&color=fff&size=256";
        const defaultSampul = "https://images.unsplash.com/photo-1579546929518-9e396f3cc809?auto=format&fit=crop&w=1000&q=80";

        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('img_avatar').src = e.target.result;
                    document.getElementById('delete_avatar').value = '0';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewSampul(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('img_sampul').src = e.target.result;
                    document.getElementById('delete_sampul').value = '0';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function hapusAvatar() {
            document.getElementById('img_avatar').src = defaultAvatar;
            document.getElementById('avatar_file').value = '';
            document.getElementById('delete_avatar').value = '1';
        }

        function hapusSampul() {
            document.getElementById('img_sampul').src = defaultSampul;
            document.getElementById('sampul_file').value = '';
            document.getElementById('delete_sampul').value = '1';
        }
    </script>
</body>
</html>