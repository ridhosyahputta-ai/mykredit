<?php
session_start();

// 1. Keamanan Session & Role Wajib
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../main/dashboard.php");
    exit();
}

// 2. Koneksi Database
include '../conn.php';
date_default_timezone_set('Asia/Jakarta');

// 3. Ambil Data Admin (Safe check)
$admin_id = $_SESSION['id_pengguna'] ?? 0;
$q_admin = mysqli_query($conn, "SELECT username, foto FROM pengguna WHERE id = '$admin_id'");
$admin = ($q_admin && mysqli_num_rows($q_admin) > 0) ? mysqli_fetch_assoc($q_admin) : null;
$nama_admin = $admin['username'] ?? 'Administrator';
$foto_admin = !empty($admin['foto']) ? $admin['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($nama_admin) . '&background=0f172a&color=10b981&bold=true';

// 4. Query Statistik Utama
$q_pengguna = mysqli_query($conn, "SELECT COUNT(*) as c FROM pengguna WHERE role = 'user'");
$t_pengguna = $q_pengguna ? mysqli_fetch_assoc($q_pengguna)['c'] : 0;

$q_kendaraan = mysqli_query($conn, "SELECT COUNT(*) as c FROM kendaraan");
$t_kendaraan = $q_kendaraan ? mysqli_fetch_assoc($q_kendaraan)['c'] : 0;

$q_transaksi = mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi");
$t_transaksi = $q_transaksi ? mysqli_fetch_assoc($q_transaksi)['c'] : 0;

$q_pembayaran = mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran");
$t_pembayaran = $q_pembayaran ? mysqli_fetch_assoc($q_pembayaran)['c'] : 0;

// 5. Query Statistik Pengajuan
$q_trx_menunggu = mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE status_pengajuan IN ('Menunggu', 'Pending')");
$trx_menunggu = $q_trx_menunggu ? mysqli_fetch_assoc($q_trx_menunggu)['c'] : 0;

$q_trx_setuju = mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE status_pengajuan = 'Disetujui'");
$trx_setuju = $q_trx_setuju ? mysqli_fetch_assoc($q_trx_setuju)['c'] : 0;

$q_trx_tolak = mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE status_pengajuan = 'Ditolak'");
$trx_tolak = $q_trx_tolak ? mysqli_fetch_assoc($q_trx_tolak)['c'] : 0;

// 6. Query Statistik Pembayaran
$q_pay_menunggu = mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi = 'menunggu'");
$pay_menunggu = $q_pay_menunggu ? mysqli_fetch_assoc($q_pay_menunggu)['c'] : 0;

$q_pay_terima = mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi = 'diterima'");
$pay_terima = $q_pay_terima ? mysqli_fetch_assoc($q_pay_terima)['c'] : 0;

$q_pay_tolak = mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi = 'ditolak'");
$pay_tolak = $q_pay_tolak ? mysqli_fetch_assoc($q_pay_tolak)['c'] : 0;

// 7. Format Tanggal Hari Ini
$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$tanggal_sekarang = $hari[date("w")] . ", " . date("d") . " " . $bulan[date("n")] . " " . date("Y");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - MyKredit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/admin-theme.css">
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
            <div class="hidden lg:flex items-center gap-3 px-8 pt-9 pb-1">
                <div class="logo-mark w-9 h-9 flex items-center justify-center text-base font-bold rounded-sm">M</div>
                <span class="font-serif text-xl font-semibold text-sidebarink tracking-tight">MyKredit</span>
            </div>
            <p class="hidden lg:block px-8 pb-7 text-[10px] font-bold text-sidebarmuted uppercase tracking-[0.14em]">Administrator</p>

            <nav class="px-5 mt-16 lg:mt-2 space-y-0.5">
                <p class="px-3 text-[10px] font-bold text-sidebarmuted uppercase tracking-[0.14em] mb-3">Menu Sistem</p>

                <a href="dashboard.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="layout-dashboard" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Dashboard Admin</span>
                </a>
                <a href="kendaraan.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="car" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Kelola Kendaraan</span>
                </a>
                <a href="transaksi.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="file-check-2" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Kelola Transaksi</span>
                </a>
                <a href="verifikasi_pembayaran.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="shield-check" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Verifikasi Pembayaran</span>
                </a>
                <a href="laporan.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="printer" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Laporan Pembayaran</span>
                </a>

                <p class="px-3 text-[10px] font-bold text-sidebarmuted uppercase tracking-[0.14em] mb-3 mt-8">Manajemen Akses</p>

                <a href="pengguna.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="users" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Pengguna Terdaftar</span>
                </a>
            </nav>
        </div>

        <div class="p-5 border-t border-white/[.06]">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-3 rounded-sm text-[#B5726B] hover:bg-white/[.04] transition-colors">
                <i data-lucide="log-out" class="w-[18px] h-[18px]"></i>
                <span class="text-sm font-semibold">Logout System</span>
            </a>
        </div>
    </aside>

    <div id="sidebar-overlay" class="overlay-scrim fixed inset-0 z-30 hidden lg:hidden transition-opacity opacity-0"></div>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto no-scrollbar pt-20 lg:pt-0">
        <div class="p-5 md:p-8 lg:p-12 max-w-6xl mx-auto w-full">

            <header class="flex flex-col md:flex-row md:items-end justify-between gap-5 mb-10">
                <div>
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                        <?= $tanggal_sekarang ?>
                    </p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Ikhtisar Panel Kontrol</h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($nama_admin) ?></span>
                    <img src="<?= htmlspecialchars($foto_admin) ?>" alt="Admin" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <div class="stat-grid surface grid grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Pengguna</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($t_pengguna) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M3 16l1-5a2 2 0 0 1 2-1.5h12A2 2 0 0 1 20 11l1 5"/><rect x="2" y="16" width="20" height="3" rx="1"/><circle cx="7" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Data Kendaraan</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($t_kendaraan) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><path d="M6 2h9l4 4v16H6z"/><path d="M15 2v4h4"/><path d="M9 13h6M9 17h6"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Transaksi</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($t_transaksi) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Pembayaran</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($t_pembayaran) ?></h3>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="surface p-7">
                    <h3 class="font-serif text-lg font-semibold text-ink mb-6">Rasio Pengajuan Kredit</h3>
                    <div class="space-y-5">
                        <div>
                            <div class="flex justify-between items-baseline mb-2"><span class="text-sm font-medium text-inksoft">Menunggu Reviu</span><span class="tabular font-serif font-semibold text-ink"><?= $trx_menunggu ?></span></div>
                            <div class="w-full bg-paperalt rounded-full h-[3px]"><div class="bg-accent h-[3px] rounded-full" style="width: <?= ($t_transaksi > 0) ? ($trx_menunggu/$t_transaksi)*100 : 0 ?>%"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between items-baseline mb-2"><span class="text-sm font-medium text-inksoft">Disetujui</span><span class="tabular font-serif font-semibold text-ink"><?= $trx_setuju ?></span></div>
                            <div class="w-full bg-paperalt rounded-full h-[3px]"><div class="bg-ink h-[3px] rounded-full" style="width: <?= ($t_transaksi > 0) ? ($trx_setuju/$t_transaksi)*100 : 0 ?>%"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between items-baseline mb-2"><span class="text-sm font-medium text-inksoft">Ditolak</span><span class="tabular font-serif font-semibold text-ink"><?= $trx_tolak ?></span></div>
                            <div class="w-full bg-paperalt rounded-full h-[3px]"><div class="bg-inkfaint h-[3px] rounded-full" style="width: <?= ($t_transaksi > 0) ? ($trx_tolak/$t_transaksi)*100 : 0 ?>%"></div></div>
                        </div>
                    </div>
                </div>

                <div class="surface p-7">
                    <h3 class="font-serif text-lg font-semibold text-ink mb-6">Verifikasi Cicilan Pembayaran</h3>
                    <div class="space-y-5">
                        <div>
                            <div class="flex justify-between items-baseline mb-2"><span class="text-sm font-medium text-inksoft">Menunggu Verifikasi</span><span class="tabular font-serif font-semibold text-ink"><?= $pay_menunggu ?></span></div>
                            <div class="w-full bg-paperalt rounded-full h-[3px]"><div class="bg-accent h-[3px] rounded-full" style="width: <?= ($t_pembayaran > 0) ? ($pay_menunggu/$t_pembayaran)*100 : 0 ?>%"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between items-baseline mb-2"><span class="text-sm font-medium text-inksoft">Diterima / Valid</span><span class="tabular font-serif font-semibold text-ink"><?= $pay_terima ?></span></div>
                            <div class="w-full bg-paperalt rounded-full h-[3px]"><div class="bg-ink h-[3px] rounded-full" style="width: <?= ($t_pembayaran > 0) ? ($pay_terima/$t_pembayaran)*100 : 0 ?>%"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between items-baseline mb-2"><span class="text-sm font-medium text-inksoft">Ditolak / Invalid</span><span class="tabular font-serif font-semibold text-ink"><?= $pay_tolak ?></span></div>
                            <div class="w-full bg-paperalt rounded-full h-[3px]"><div class="bg-inkfaint h-[3px] rounded-full" style="width: <?= ($t_pembayaran > 0) ? ($pay_tolak/$t_pembayaran)*100 : 0 ?>%"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-10">
                <div class="lg:col-span-2 surface overflow-hidden">
                    <div class="px-7 py-6 border-b border-line flex justify-between items-center">
                        <h3 class="font-serif text-lg font-semibold text-ink">Aktivitas Transaksi Terbaru</h3>
                        <a href="transaksi.php" class="text-accentdark font-semibold text-sm hover:text-accent inline-flex items-center gap-1">Lihat Semua <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-7 py-4">Nasabah</th>
                                    <th class="px-7 py-4">Status Transaksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <?php
                                $q_recent = mysqli_query($conn, "SELECT * FROM transaksi ORDER BY id DESC LIMIT 5");
                                if ($q_recent && mysqli_num_rows($q_recent) > 0) {
                                    while ($r = mysqli_fetch_assoc($q_recent)) {
                                        $status = $r['status_pengajuan'] ?? '-';
                                        $badge_class = "badge-neutral";
                                        if(strtolower($status) == 'disetujui') $badge_class = "badge-approved";
                                        if(strtolower($status) == 'ditolak') $badge_class = "badge-rejected";
                                        if(in_array(strtolower($status), ['menunggu', 'pending'])) $badge_class = "badge-pending";
                                        ?>
                                        <tr class="hover:bg-paperalt/40 transition-colors">
                                            <td class="px-7 py-4 flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-paperalt flex items-center justify-center text-inksoft text-xs font-bold border border-line">
                                                    ID
                                                </div>
                                                <span class="text-ink font-semibold">Nasabah ID: <?= htmlspecialchars($r['id_pengguna'] ?? 'Unknown') ?></span>
                                            </td>
                                            <td class="px-7 py-4">
                                                <span class="badge <?= $badge_class ?>"><span class="badge-dot"></span> <?= htmlspecialchars($status) ?></span>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="2" class="px-7 py-10 text-center text-inkfaint font-medium">Belum ada aktivitas pengajuan masuk.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-charcoal rounded-lg p-7 flex flex-col relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full border border-white/[.06] pointer-events-none"></div>
                    <h3 class="text-[11px] font-bold text-sidebarmuted uppercase tracking-widest mb-6 pb-4 border-b border-white/10">Sistem Alert Log</h3>

                    <div class="flex-1 space-y-5 overflow-y-auto max-h-[260px] no-scrollbar">
                        <?php
                        $q_notif_admin = mysqli_query($conn, "SELECT pesan, created_at FROM notifikasi ORDER BY created_at DESC LIMIT 4");
                        if ($q_notif_admin && mysqli_num_rows($q_notif_admin) > 0) {
                            while ($n = mysqli_fetch_assoc($q_notif_admin)) {
                                $waktu_n = date("d/m H:i", strtotime($n['created_at']));
                                ?>
                                <div class="flex gap-3 items-start">
                                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-accent shrink-0"></span>
                                    <div>
                                        <p class="text-xs font-medium text-sidebarink leading-relaxed"><?= htmlspecialchars($n['pesan']) ?></p>
                                        <span class="text-[10px] text-sidebarmuted font-bold mt-1 block tracking-wide"><?= $waktu_n ?></span>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="text-sidebarmuted text-xs font-medium italic py-2">Sistem beroperasi normal tanpa kendala.</div>';
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

        function toggleAdminMenu() {
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

        btn.addEventListener('click', toggleAdminMenu);
        overlay.addEventListener('click', toggleAdminMenu);
    </script>
</body>
</html>