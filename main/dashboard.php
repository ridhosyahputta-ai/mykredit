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

$user_id = $_SESSION['id_pengguna'];

// 4. Ambil Data User 
$query_user = mysqli_query($conn, "SELECT * FROM pengguna WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($query_user);
$nama_user = $user['username'] ?? 'Nasabah';
// Gunakan foto dari database jika ada, jika tidak pakai UI Avatars dengan gaya clean
$foto_profil = !empty($user['foto']) ? $user['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($nama_user) . '&background=f1f5f9&color=0f172a&bold=true';

// 5. Query Statistik Berjalan (Gunakan @ agar tidak error jika tabel kosong)
$q_total = @mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_pengguna = '$user_id'");
$total_pengajuan = $q_total ? mysqli_fetch_assoc($q_total)['total'] : 0;

$q_menunggu = @mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_pengguna = '$user_id' AND (status_pengajuan = 'Menunggu' OR status_pengajuan = 'Pending')");
$kredit_menunggu = $q_menunggu ? mysqli_fetch_assoc($q_menunggu)['total'] : 0;

$q_setuju = @mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_pengguna = '$user_id' AND status_pengajuan = 'Disetujui'");
$kredit_disetujui = $q_setuju ? mysqli_fetch_assoc($q_setuju)['total'] : 0;

$q_cicilan = @mysqli_query($conn, "SELECT COUNT(*) as total FROM pembayaran p JOIN transaksi t ON p.id_transaksi = t.id WHERE t.id_pengguna = '$user_id' AND p.status_pembayaran = 'Lunas'");
$total_cicilan = $q_cicilan ? mysqli_fetch_assoc($q_cicilan)['total'] : 0;

// Kalkulasi Progress (Misal 1 transaksi = 12 bulan cicilan)
$target_cicilan = $kredit_disetujui * 12; 
$progress_persen = ($target_cicilan > 0) ? min(100, round(($total_cicilan / $target_cicilan) * 100)) : 0;

// 6. Tanggal Hari Ini
$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$tanggal_hari_ini = $hari[date("w")] . ", " . date("d") . " " . $bulan[date("n")] . " " . date("Y");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MyKredit</title>
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

                <a href="dashboard.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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

        <div class="p-5 md:p-8 lg:p-12 max-w-6xl mx-auto w-full">

            <header class="flex flex-col md:flex-row md:items-end justify-between gap-5 mb-10">
                <div>
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2">
                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i> <?= $tanggal_hari_ini ?>
                    </p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Halo, <?= htmlspecialchars($nama_user) ?></h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink">Nasabah Reguler</span>
                    <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <div class="stat-grid surface grid grid-cols-2 lg:grid-cols-4 mb-8">

                <div class="px-6 py-5 flex items-center gap-4">
                    <i data-lucide="bar-chart" class="w-5 h-5 text-inkfaint shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Pengajuan</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $total_pengajuan ?></h3>
                    </div>
                </div>

                <div class="px-6 py-5 flex items-center gap-4">
                    <i data-lucide="hourglass" class="w-5 h-5 text-inkfaint shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Menunggu</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $kredit_menunggu ?></h3>
                    </div>
                </div>

                <div class="px-6 py-5 flex items-center gap-4">
                    <i data-lucide="badge-check" class="w-5 h-5 text-accent shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Disetujui</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $kredit_disetujui ?></h3>
                    </div>
                </div>

                <div class="px-6 py-5 flex items-center gap-4">
                    <i data-lucide="receipt" class="w-5 h-5 text-inkfaint shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Cicilan</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $total_cicilan ?></h3>
                    </div>
                </div>

            </div>

            <div class="flex items-start gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm">
                <i data-lucide="lightbulb" class="w-4 h-4 text-accentdark mt-0.5 shrink-0"></i>
                <p class="text-sm text-ink leading-relaxed"><span class="font-semibold">Tips Skor Kredit —</span> Bayar cicilan maksimal H-3 dari tanggal jatuh tempo untuk mendapatkan penawaran bunga lebih rendah pada pengajuan berikutnya.</p>
            </div>

            <div class="bg-charcoal rounded-lg p-9 md:p-12 mb-8 relative overflow-hidden">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 rounded-full border border-white/[.06]"></div>

                <div class="relative z-10 max-w-xl">
                    <span class="inline-block text-[11px] font-bold uppercase tracking-[0.12em] text-accent mb-4">Status Kredit</span>
                    <h2 class="font-serif text-3xl md:text-4xl text-sidebarink font-semibold tracking-tight mb-8">Progress Pelunasan Kendaraan</h2>

                    <div class="flex items-end justify-between mb-3">
                        <span class="text-sm font-medium text-sidebarmuted">Selesai bayar</span>
                        <span class="tabular font-serif text-4xl font-semibold text-accent"><?= $progress_persen ?>%</span>
                    </div>
                    <div class="w-full bg-white/10 h-[3px] rounded-full mb-9">
                        <div class="bg-accent h-[3px] rounded-full" style="width: <?= $progress_persen ?>%"></div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="pembayaran_cicilan.php" class="btn btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">
                            <i data-lucide="credit-card" class="w-4 h-4"></i> Bayar Cicilan
                        </a>
                        <a href="riwayat_transaksi.php" class="btn btn-dark px-6 py-3 text-sm inline-flex items-center gap-2">
                            <i data-lucide="file-text" class="w-4 h-4"></i> Lihat Rincian
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-10">

                <div class="lg:col-span-2 surface p-7 sm:p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-serif text-xl font-semibold text-ink">Unit Tersedia</h3>
                        <a href="pengajuan_kredit.php" class="text-accentdark font-semibold text-sm hover:text-accent inline-flex items-center gap-1">Lihat Semua <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                    </div>

                    <div class="divide-y divide-line">
                        <?php
                        $q_kendaraan = @mysqli_query($conn, "SELECT * FROM kendaraan LIMIT 3");
                        if ($q_kendaraan && mysqli_num_rows($q_kendaraan) > 0) {
                            while ($m = mysqli_fetch_assoc($q_kendaraan)) {
                                // Default icon jika tidak ada foto
                                $ikon_kendaraan = "bike";
                                if (strpos(strtolower($m['tipe'] ?? ''), 'mobil') !== false) { $ikon_kendaraan = "car"; }
                                ?>
                                <div class="flex items-center py-4 group cursor-pointer">
                                    <div class="w-12 h-12 bg-paperalt rounded-sm flex items-center justify-center text-inksoft group-hover:text-accent group-hover:bg-accentsoft transition-colors mr-4 shrink-0">
                                        <i data-lucide="<?= $ikon_kendaraan ?>" class="w-5 h-5"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-ink truncate"><?= htmlspecialchars($m['merk'] ?? 'Merk') ?> <?= htmlspecialchars($m['tipe'] ?? '') ?></h4>
                                        <p class="text-xs font-medium text-accentdark mt-0.5">Tersedia</p>
                                    </div>
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-inkfaint group-hover:text-accent transition-colors shrink-0"></i>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="text-center py-8 text-inkfaint"><p class="text-sm font-medium">Belum ada data kendaraan.</p></div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="surface p-7 sm:p-8">
                    <h3 class="font-serif text-xl font-semibold text-ink mb-6">Aktivitas Terbaru</h3>

                    <div class="space-y-5">
                        <?php
                        $q_notif = @mysqli_query($conn, "SELECT * FROM notifikasi WHERE id_pengguna = '$user_id' ORDER BY created_at DESC LIMIT 3");
                        if ($q_notif && mysqli_num_rows($q_notif) > 0) {
                            while ($notif = mysqli_fetch_assoc($q_notif)) {
                                $waktu = date("d M, H:i", strtotime($notif['created_at']));
                                ?>
                                <div class="relative pl-5 border-l-2 border-line">
                                    <span class="absolute -left-[5px] top-1 w-2 h-2 rounded-full bg-accent"></span>
                                    <p class="text-sm font-medium text-ink leading-snug"><?= htmlspecialchars($notif['pesan']) ?></p>
                                    <span class="text-[11px] font-semibold text-inkfaint mt-1 block uppercase tracking-wider"><?= $waktu ?></span>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="py-4 text-inkfaint text-sm font-medium">Tidak ada notifikasi baru.</div>';
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        const btn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        let isOpen = false;

        function toggleMenu() {
            isOpen = !isOpen;
            if(isOpen) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                btn.innerHTML = '<i data-lucide="x" class="w-6 h-6"></i>';
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
                btn.innerHTML = '<i data-lucide="menu" class="w-6 h-6"></i>';
            }
            lucide.createIcons();
        }

        btn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>