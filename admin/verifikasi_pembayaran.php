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

include '../conn.php';
date_default_timezone_set('Asia/Jakarta');

$alert = '';

// Data Admin untuk Header
$admin_id = $_SESSION['id_pengguna'] ?? 0;
$q_admin = mysqli_query($conn, "SELECT username, foto FROM pengguna WHERE id = '$admin_id'");
$admin = ($q_admin && mysqli_num_rows($q_admin) > 0) ? mysqli_fetch_assoc($q_admin) : null;
$nama_admin = $admin['username'] ?? 'Administrator';
$foto_admin = !empty($admin['foto']) ? $admin['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($nama_admin) . '&background=191B1D&color=E7E9EB&bold=true';

$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$tanggal_sekarang = $hari[date("w")] . ", " . date("d") . " " . $bulan[date("n")] . " " . date("Y");

// ==========================================
// PROSES AKSI ADMIN (TERIMA / TOLAK)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id_pembayaran = (int)$_POST['id_pembayaran'];
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');

    $q_bayar = mysqli_query($conn, "SELECT * FROM pembayaran WHERE id = '$id_pembayaran'");

    if ($q_bayar && mysqli_num_rows($q_bayar) > 0) {
        $bayar = mysqli_fetch_assoc($q_bayar);

        // Hanya proses jika status masih menunggu verifikasi
        if ($bayar['status_verifikasi'] === 'menunggu') {

            if ($action === 'terima') {
                $stmt = mysqli_prepare($conn, "UPDATE pembayaran SET status_verifikasi = 'diterima', status_pembayaran = 'lunas', catatan_admin = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $catatan_admin, $id_pembayaran);
                if (mysqli_stmt_execute($stmt)) {
                    @mysqli_query($conn, "INSERT INTO notifikasi (id_pengguna, judul, pesan) VALUES ('{$bayar['id_pengguna']}', 'Pembayaran Diterima', 'Bukti pembayaran cicilan ke-{$bayar['cicilan_ke']} Anda telah diverifikasi dan dinyatakan lunas.')");
                    $_SESSION['alert_success'] = "Pembayaran berhasil diterima dan cicilan dinyatakan lunas.";
                    header("Location: verifikasi_pembayaran.php"); exit();
                } else {
                    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Terjadi kesalahan sistem saat menyimpan.</p></div>";
                }
            } elseif ($action === 'tolak') {
                if (empty($catatan_admin)) {
                    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Catatan admin wajib diisi saat menolak bukti pembayaran.</p></div>";
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE pembayaran SET status_verifikasi = 'ditolak', catatan_admin = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $catatan_admin, $id_pembayaran);
                    if (mysqli_stmt_execute($stmt)) {
                        @mysqli_query($conn, "INSERT INTO notifikasi (id_pengguna, judul, pesan) VALUES ('{$bayar['id_pengguna']}', 'Pembayaran Ditolak', 'Maaf, bukti pembayaran cicilan ke-{$bayar['cicilan_ke']} Anda ditolak. Cek catatan admin untuk detail dan unggah ulang.')");
                        $_SESSION['alert_success'] = "Bukti pembayaran berhasil ditolak.";
                        header("Location: verifikasi_pembayaran.php"); exit();
                    } else {
                        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Terjadi kesalahan sistem saat menyimpan.</p></div>";
                    }
                }
            }
        }
    }
}

if (isset($_SESSION['alert_success'])) {
    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>{$_SESSION['alert_success']}</p></div>";
    unset($_SESSION['alert_success']);
}

// ==========================================
// STATISTIK & FILTER
// ==========================================
$q_stat_menunggu = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi = 'menunggu'"))['c'] ?? 0;
$q_stat_total    = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi != 'belum_kirim'"))['c'] ?? 0;
$q_stat_diterima = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi = 'diterima'"))['c'] ?? 0;
$q_stat_ditolak  = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM pembayaran WHERE status_verifikasi = 'ditolak'"))['c'] ?? 0;

$search = $_GET['search'] ?? '';
$filter_status = strtolower($_GET['status'] ?? 'semua');

$where_clauses = ["p.status_verifikasi != 'belum_kirim'"];
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(u.username LIKE '%$s%' OR u.email LIKE '%$s%' OR k.merk LIKE '%$s%' OR k.model LIKE '%$s%')";
}
if ($filter_status !== 'semua') {
    $stat = mysqli_real_escape_string($conn, $filter_status);
    $where_clauses[] = "p.status_verifikasi = '$stat'";
}
$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Prioritaskan yang masih menunggu verifikasi, lalu yang paling lama menunggu lebih dulu
$query_table = mysqli_query($conn, "
    SELECT p.*, u.username, u.email, u.no_hp, k.merk, k.model, k.gambar
    FROM pembayaran p
    JOIN transaksi t ON p.id_transaksi = t.id
    JOIN kendaraan k ON t.id_kendaraan = k.id
    JOIN pengguna u ON p.id_pengguna = u.id
    $where_sql
    ORDER BY (p.status_verifikasi = 'menunggu') DESC, p.tanggal_bayar ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran - Admin MyKredit</title>
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

                <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
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
                <a href="verifikasi_pembayaran.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                    <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= $q_stat_menunggu ?> Menunggu, <?= $q_stat_diterima ?> Diterima, <?= $q_stat_ditolak ?> Ditolak</p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Verifikasi Pembayaran</h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($nama_admin) ?></span>
                    <img src="<?= htmlspecialchars($foto_admin) ?>" alt="Admin" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <?= $alert ?>

            <!-- Priority stat stands apart from the flat strip beside it — the one
                 number on this page that needs attention first. -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 mb-8">
                <div class="surface stat-priority lg:col-span-1 px-6 py-5 flex items-center gap-4">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Menunggu Verifikasi</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_menunggu) ?></h3>
                    </div>
                </div>

                <div class="stat-grid surface lg:col-span-3 grid grid-cols-3">
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Diajukan</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_total) ?></h3>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M8 12.5l2.5 2.5L16 9.5"/><circle cx="12" cy="12" r="9"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Diterima</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_diterima) ?></h3>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><line x1="8" y1="8" x2="16" y2="16"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Ditolak</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_ditolak) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="surface inline-flex items-center gap-1 p-1 w-max overflow-x-auto no-scrollbar">
                    <a href="?status=semua&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'semua' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Semua</a>
                    <a href="?status=menunggu&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'menunggu' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Menunggu</a>
                    <a href="?status=diterima&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'diterima' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Diterima</a>
                    <a href="?status=ditolak&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'ditolak' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Ditolak</a>
                </div>

                <form action="" method="GET" class="relative w-full md:w-72">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-inkfaint"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nasabah, email, merk, model..."
                           class="field w-full pl-11 pr-4 py-2.5 text-sm font-medium">
                </form>
            </div>

            <div class="surface overflow-hidden mb-10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-7 sm:px-8 py-4">Nasabah</th>
                                <th class="px-7 sm:px-8 py-4">Kendaraan</th>
                                <th class="px-7 sm:px-8 py-4">Cicilan</th>
                                <th class="px-7 sm:px-8 py-4">Tanggal Kirim</th>
                                <th class="px-7 sm:px-8 py-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <?php if ($query_table && mysqli_num_rows($query_table) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($query_table)):
                                    $sv = $row['status_verifikasi'];
                                    $badge_class = "badge-pending";
                                    if ($sv == 'diterima') { $badge_class = "badge-approved"; }
                                    elseif ($sv == 'ditolak') { $badge_class = "badge-rejected"; }

                                    $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr onclick="openDetailModal(<?= $json_data ?>)" class="cursor-pointer hover:bg-paperalt/40 transition-colors">
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-paperalt flex items-center justify-center text-inksoft font-bold text-sm uppercase border border-line">
                                                <?= substr($row['username'], 0, 1) ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-ink"><?= htmlspecialchars($row['username']) ?></p>
                                                <p class="text-xs text-inkfaint"><?= htmlspecialchars($row['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="font-semibold text-ink"><?= htmlspecialchars($row['merk']) ?> <?= htmlspecialchars($row['model']) ?></p>
                                        <p class="text-xs text-inksoft mt-0.5">via <?= htmlspecialchars($row['metode_bank'] ?? '-') ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['total_bayar'],0,',','.') ?></p>
                                        <p class="text-xs text-inksoft mt-0.5">Cicilan Ke-<?= $row['cicilan_ke'] ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4 text-inksoft text-xs font-semibold">
                                        <?= !empty($row['tanggal_bayar']) ? date("d M Y", strtotime($row['tanggal_bayar'])) : '-' ?>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <span class="badge <?= $badge_class ?>"><span class="badge-dot"></span> <?= ucwords($sv) ?></span>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><polyline points="9 6 15 12 9 18"/></svg>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-7 py-16 text-center">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-inkfaint"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                                        <h3 class="font-serif text-lg font-semibold text-ink mb-1">Data tidak ditemukan</h3>
                                        <p class="text-inksoft text-sm">Belum ada bukti pembayaran yang sesuai dengan filter.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <div id="modal-backdrop" class="overlay-scrim fixed inset-0 z-[60] hidden opacity-0 transition-opacity duration-300"></div>

    <div id="detailModal" class="fixed inset-0 z-[70] hidden items-center justify-center px-4 opacity-0 transition-all duration-300 transform scale-95">
        <div class="bg-paper w-full max-w-4xl rounded-lg overflow-hidden flex flex-col max-h-[90vh]">

            <div class="px-8 py-5 border-b border-line flex justify-between items-center">
                <div>
                    <h3 class="font-serif text-xl font-semibold text-ink">Rincian Bukti Pembayaran</h3>
                    <p class="text-xs text-inksoft" id="m_tgl_kirim"></p>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center text-inksoft border border-line rounded-full hover:border-linestrong transition-colors">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="overflow-y-auto no-scrollbar p-8">
                <div class="flex flex-col md:flex-row gap-8 mb-8">
                    <div class="flex-1 space-y-6">
                        <div>
                            <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-3 border-b border-line pb-2">Informasi Nasabah</p>
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-paperalt flex items-center justify-center text-inksoft font-bold text-lg border border-line" id="m_inisial"></div>
                                <div>
                                    <p class="font-serif font-semibold text-ink text-lg" id="m_nama"></p>
                                    <p class="text-sm text-inksoft mt-0.5" id="m_email"></p>
                                    <p class="text-sm text-inksoft mt-0.5" id="m_nohp"></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-3 border-b border-line pb-2">Rincian Cicilan</p>
                            <div class="flex gap-4">
                                <div class="w-24 h-16 bg-paperalt rounded-sm overflow-hidden border border-line shrink-0">
                                    <img src="" id="m_gambar" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <p class="font-serif font-semibold text-ink" id="m_kendaraan"></p>
                                    <p class="text-xs font-semibold text-accentdark mb-1.5" id="m_cicilan_ke"></p>
                                    <span id="m_status_badge" class="badge"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 bg-paperalt rounded-sm p-6 border border-line flex flex-col">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-4">Bukti Transfer</p>
                        <div class="bg-paper rounded-sm overflow-hidden border border-line mb-4 flex-1 flex items-center justify-center">
                            <img src="" id="m_bukti" class="max-h-56 w-full object-contain">
                        </div>
                        <div class="flex justify-between items-end border-b border-line pb-2 mb-2">
                            <span class="text-xs font-semibold text-inksoft">Metode Bank</span>
                            <span class="font-semibold text-ink text-sm" id="m_bank"></span>
                        </div>
                        <div class="flex justify-between items-end">
                            <span class="text-xs font-semibold text-inksoft">Total Dibayar</span>
                            <span class="tabular font-serif font-semibold text-ink" id="m_total"></span>
                        </div>
                    </div>
                </div>

                <form action="" method="POST" id="formAksi" class="surface p-6">
                    <input type="hidden" name="id_pembayaran" id="form_id_bayar">

                    <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Catatan Admin (Wajib diisi jika menolak)</label>
                    <textarea name="catatan_admin" id="m_catatan" rows="2" placeholder="Tambahkan alasan atau pesan untuk nasabah..." class="field w-full px-4 py-3 text-sm font-medium mb-4 resize-none"></textarea>

                    <div id="action_buttons" class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="submit" name="action" value="tolak" onclick="return confirm('Yakin ingin MENOLAK bukti pembayaran ini?')" class="btn btn-ghost btn-pressable flex-1 py-3.5 inline-flex justify-center items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="8" y1="8" x2="16" y2="16"/></svg> Tolak
                        </button>
                        <button type="submit" name="action" value="terima" onclick="return confirm('Yakin ingin MENERIMA pembayaran ini dan menandainya lunas?')" class="btn btn-primary btn-pressable flex-1 py-3.5 inline-flex justify-center items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l6 6L20 6"/></svg> Terima &amp; Tandai Lunas
                        </button>
                    </div>

                    <div id="status_alert" class="hidden text-center p-4 rounded-sm font-semibold text-sm"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const btn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        let isSidebarOpen = false;

        function toggleSidebar() {
            isSidebarOpen = !isSidebarOpen;
            if(isSidebarOpen) {
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
        btn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        function formatRp(angka) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(angka);
        }

        const backdrop = document.getElementById('modal-backdrop');
        const modal = document.getElementById('detailModal');

        function openDetailModal(data) {
            document.getElementById('m_inisial').innerText = data.username.charAt(0).toUpperCase();
            document.getElementById('m_nama').innerText = data.username;
            document.getElementById('m_email').innerText = data.email;
            document.getElementById('m_nohp').innerText = data.no_hp || '-';

            document.getElementById('m_kendaraan').innerText = data.merk + ' ' + data.model;
            document.getElementById('m_cicilan_ke').innerText = 'Cicilan Ke-' + data.cicilan_ke;
            const imgSrc = data.gambar ? '../uploads/kendaraan/' + data.gambar : 'https://placehold.co/200x150/E8ECEF/97A1AB?text=Foto';
            document.getElementById('m_gambar').src = imgSrc;

            document.getElementById('m_bukti').src = data.bukti_pembayaran ? '../uploads/bukti/' + data.bukti_pembayaran : 'https://placehold.co/400x300/E8ECEF/97A1AB?text=Tidak+Ada+Bukti';
            document.getElementById('m_bank').innerText = data.metode_bank || '-';
            document.getElementById('m_total').innerText = 'Rp ' + formatRp(data.total_bayar);

            document.getElementById('m_tgl_kirim').innerText = 'Dikirim: ' + (data.tanggal_bayar || '-');

            document.getElementById('form_id_bayar').value = data.id;
            document.getElementById('m_catatan').value = data.catatan_admin || '';

            const status = data.status_verifikasi;
            const badgeEl = document.getElementById('m_status_badge');
            const actionBtns = document.getElementById('action_buttons');
            const statusAlert = document.getElementById('status_alert');
            const catatanInput = document.getElementById('m_catatan');

            if (status === 'menunggu') {
                badgeEl.className = 'badge badge-pending';
                badgeEl.innerText = 'Menunggu';

                actionBtns.classList.remove('hidden');
                statusAlert.classList.add('hidden');
                catatanInput.readOnly = false;
            } else {
                actionBtns.classList.add('hidden');
                statusAlert.classList.remove('hidden');
                catatanInput.readOnly = true;

                if (status === 'diterima') {
                    badgeEl.className = 'badge badge-approved';
                    badgeEl.innerText = 'Diterima';
                    statusAlert.className = 'block text-center p-4 rounded-sm font-semibold text-sm border-l-2 border-accent bg-accentsoft text-ink';
                    statusAlert.innerHTML = 'Pembayaran ini sudah diterima dan dinyatakan lunas.';
                } else if (status === 'ditolak') {
                    badgeEl.className = 'badge badge-rejected';
                    badgeEl.innerText = 'Ditolak';
                    statusAlert.className = 'block text-center p-4 rounded-sm font-semibold text-sm border-l-2 border-linestrong bg-paperalt text-inksoft';
                    statusAlert.innerHTML = 'Bukti pembayaran ini telah ditolak.';
                }
            }

            backdrop.classList.remove('hidden');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                modal.classList.remove('opacity-0', 'scale-95');
            }, 10);
        }

        function closeModal() {
            backdrop.classList.add('opacity-0');
            modal.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                backdrop.classList.add('hidden');
                modal.classList.add('hidden');
                modal.style.display = 'none';
            }, 300);
        }
    </script>
</body>
</html>
