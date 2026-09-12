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

$alert = '';

// Data Admin untuk Header
$admin_id = $_SESSION['id_pengguna'] ?? 0;
$q_admin = mysqli_query($conn, "SELECT username, foto FROM pengguna WHERE id = '$admin_id'");
$admin = ($q_admin && mysqli_num_rows($q_admin) > 0) ? mysqli_fetch_assoc($q_admin) : null;
$nama_admin = $admin['username'] ?? 'Administrator';
$foto_admin = !empty($admin['foto']) ? $admin['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($nama_admin) . '&background=0f172a&color=10b981&bold=true';

$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$tanggal_sekarang = $hari[date("w")] . ", " . date("d") . " " . $bulan[date("n")] . " " . date("Y");

// ==========================================
// PROSES AKSI ADMIN (SETUJUI / TOLAK)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id_transaksi = (int)$_POST['id_transaksi'];
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');
    
    // Ambil data transaksi beserta stok kendaraan
    $q_trx = mysqli_query($conn, "
        SELECT t.*, k.stok 
        FROM transaksi t 
        JOIN kendaraan k ON t.id_kendaraan = k.id 
        WHERE t.id = '$id_transaksi'
    ");
    
    if ($q_trx && mysqli_num_rows($q_trx) > 0) {
        $trx = mysqli_fetch_assoc($q_trx);
        $status_saat_ini = strtolower($trx['status_pengajuan']);
        
        // Hanya proses jika status masih menunggu
        if ($status_saat_ini === 'menunggu') {
            
            if ($action === 'approve') {
                if ($trx['stok'] <= 0) {
                    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Gagal menyetujui: Stok kendaraan habis!</p></div>";
                } else {
                    mysqli_begin_transaction($conn);
                    try {
                        // 1. Update Status & Catatan
                        $stmt1 = mysqli_prepare($conn, "UPDATE transaksi SET status_pengajuan = 'disetujui', catatan_admin = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt1, "si", $catatan_admin, $id_transaksi);
                        mysqli_stmt_execute($stmt1);
                        
                        // 2. Kurangi Stok Kendaraan
                        $stmt2 = mysqli_prepare($conn, "UPDATE kendaraan SET stok = stok - 1 WHERE id = ?");
                        mysqli_stmt_bind_param($stmt2, "i", $trx['id_kendaraan']);
                        mysqli_stmt_execute($stmt2);
                        
                        // 3. Generate Cicilan jika belum ada
                        $q_check_cicilan = mysqli_query($conn, "SELECT id FROM pembayaran WHERE id_transaksi = '$id_transaksi'");
                        if (mysqli_num_rows($q_check_cicilan) == 0) {
                            $tenor = (int)$trx['tenor'];
                            $cicilan_perbulan = (float)$trx['cicilan_perbulan'];
                            $id_pengguna = (int)$trx['id_pengguna'];
                            $tgl_trx = $trx['tanggal_transaksi'] ?? date('Y-m-d H:i:s');
                            
                            $stmt3 = mysqli_prepare($conn, "INSERT INTO pembayaran (id_transaksi, id_pengguna, cicilan_ke, tanggal_jatuh_tempo, cicilan_perbulan, denda, total_bayar, status_pembayaran, status_verifikasi) VALUES (?, ?, ?, ?, ?, 0, ?, 'belum_bayar', 'belum_kirim')");
                            
                            for ($i = 1; $i <= $tenor; $i++) {
                                $jatuh_tempo = date('Y-m-d', strtotime("+$i months", strtotime($tgl_trx)));
                                mysqli_stmt_bind_param($stmt3, "iiisdd", $id_transaksi, $id_pengguna, $i, $jatuh_tempo, $cicilan_perbulan, $cicilan_perbulan);
                                mysqli_stmt_execute($stmt3);
                            }
                        }
                        
                        // 4. Notifikasi
                        @mysqli_query($conn, "INSERT INTO notifikasi (id_pengguna, judul, pesan) VALUES ('{$trx['id_pengguna']}', 'Pengajuan Disetujui', 'Pengajuan kredit kendaraan disetujui! Silakan cek rincian pembayaran Anda.')");
                        
                        mysqli_commit($conn);
                        $_SESSION['alert_success'] = "Pengajuan berhasil disetujui dan cicilan telah dibuat.";
                        header("Location: transaksi.php"); exit();
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Terjadi kesalahan sistem saat menyetujui.</p></div>";
                    }
                }
            } elseif ($action === 'reject') {
                $stmt = mysqli_prepare($conn, "UPDATE transaksi SET status_pengajuan = 'ditolak', catatan_admin = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $catatan_admin, $id_transaksi);
                if (mysqli_stmt_execute($stmt)) {
                    @mysqli_query($conn, "INSERT INTO notifikasi (id_pengguna, judul, pesan) VALUES ('{$trx['id_pengguna']}', 'Pengajuan Ditolak', 'Maaf, pengajuan kredit kendaraan Anda ditolak. Cek catatan admin untuk detail.')");
                    $_SESSION['alert_success'] = "Pengajuan berhasil ditolak.";
                    header("Location: transaksi.php"); exit();
                } else {
                    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Gagal menolak pengajuan.</p></div>";
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
$q_stat_total = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi"))['c'] ?? 0;
$q_stat_tunggu = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE LOWER(status_pengajuan) = 'menunggu'"))['c'] ?? 0;
$q_stat_setuju = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE LOWER(status_pengajuan) = 'disetujui'"))['c'] ?? 0;
$q_stat_tolak = @mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE LOWER(status_pengajuan) = 'ditolak'"))['c'] ?? 0;

$search = $_GET['search'] ?? '';
$filter_status = strtolower($_GET['status'] ?? 'semua');

$where_clauses = [];
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(p.username LIKE '%$s%' OR p.email LIKE '%$s%' OR k.merk LIKE '%$s%' OR k.model LIKE '%$s%')";
}
if ($filter_status !== 'semua') {
    $stat = mysqli_real_escape_string($conn, $filter_status);
    $where_clauses[] = "LOWER(t.status_pengajuan) = '$stat'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Query Tabel Utama
$query_table = mysqli_query($conn, "
    SELECT t.*, p.username, p.email, p.no_hp, k.merk, k.model, k.harga, k.gambar 
    FROM transaksi t 
    JOIN pengguna p ON t.id_pengguna = p.id 
    JOIN kendaraan k ON t.id_kendaraan = k.id 
    $where_sql 
    ORDER BY t.tanggal_transaksi DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Kredit - Admin MyKredit</title>
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
                <a href="transaksi.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                    <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= $q_stat_tunggu ?> Menunggu, <?= $q_stat_setuju ?> Disetujui, <?= $q_stat_tolak ?> Ditolak</p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Kelola Transaksi</h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($nama_admin) ?></span>
                    <img src="<?= htmlspecialchars($foto_admin) ?>" alt="Admin" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <?= $alert ?>

            <div class="stat-grid surface grid grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M6 2h9l4 4v16H6z"/><path d="M15 2v4h4"/><path d="M9 13h6M9 17h6"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Pengajuan</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_total) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Menunggu</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_tunggu) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M8 12.5l2.5 2.5L16 9.5"/><circle cx="12" cy="12" r="9"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Disetujui</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_setuju) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><line x1="8" y1="8" x2="16" y2="16"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Ditolak</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($q_stat_tolak) ?></h3>
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="surface inline-flex items-center gap-1 p-1 w-max overflow-x-auto no-scrollbar">
                    <a href="?status=semua&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'semua' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Semua</a>
                    <a href="?status=menunggu&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'menunggu' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Menunggu</a>
                    <a href="?status=disetujui&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter_status == 'disetujui' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Disetujui</a>
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
                                <th class="px-7 sm:px-8 py-4">Rincian Kredit</th>
                                <th class="px-7 sm:px-8 py-4">Tanggal</th>
                                <th class="px-7 sm:px-8 py-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <?php if ($query_table && mysqli_num_rows($query_table) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($query_table)):
                                    $status = strtolower($row['status_pengajuan']);
                                    $badge_class = "badge-pending";
                                    if ($status == 'disetujui') { $badge_class = "badge-approved"; }
                                    elseif ($status == 'ditolak') { $badge_class = "badge-rejected"; }

                                    // Serialize row for JS Modal (Safe JSON)
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
                                        <p class="text-xs text-inksoft mt-0.5">OTR Rp <?= number_format($row['harga'],0,',','.') ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['cicilan_perbulan'],0,',','.') ?> <span class="text-xs font-sans font-medium text-inkfaint">/bln</span></p>
                                        <p class="text-xs text-inksoft mt-0.5">DP Rp <?= number_format($row['dp'],0,',','.') ?> <span class="mx-1">•</span> Tenor <?= $row['tenor'] ?> Bln</p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4 text-inksoft text-xs font-semibold">
                                        <?= date("d M Y", strtotime($row['tanggal_transaksi'])) ?><br>
                                        <span class="text-[10px] text-inkfaint font-normal"><?= date("H:i", strtotime($row['tanggal_transaksi'])) ?> WIB</span>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <span class="badge <?= $badge_class ?>"><span class="badge-dot"></span> <?= ucwords($status) ?></span>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><polyline points="9 6 15 12 9 18"/></svg>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-7 py-16 text-center">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-inkfaint"><path d="M6 2h9l4 4v16H6z"/><path d="M15 2v4h4"/><path d="M9 13h6M9 17h6"/></svg>
                                        <h3 class="font-serif text-lg font-semibold text-ink mb-1">Data tidak ditemukan</h3>
                                        <p class="text-inksoft text-sm">Belum ada transaksi kredit yang sesuai dengan filter.</p>
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
                    <h3 class="font-serif text-xl font-semibold text-ink">Rincian Pengajuan</h3>
                    <p class="text-xs text-inksoft" id="m_tgl_trx"></p>
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
                                    <p class="text-sm text-inksoft flex items-center gap-1.5 mt-0.5"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M22 6l-10 7L2 6"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg> <span id="m_email"></span></p>
                                    <p class="text-sm text-inksoft flex items-center gap-1.5 mt-0.5"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.68 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.32 1.85.55 2.81.68A2 2 0 0 1 22 16.92z"/></svg> <span id="m_nohp"></span></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-3 border-b border-line pb-2">Informasi Kendaraan</p>
                            <div class="flex gap-4">
                                <div class="w-24 h-16 bg-paperalt rounded-sm overflow-hidden border border-line shrink-0">
                                    <img src="" id="m_gambar" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <p class="font-serif font-semibold text-ink" id="m_kendaraan"></p>
                                    <p class="text-xs font-semibold text-accentdark mb-1.5" id="m_harga"></p>
                                    <span id="m_status_badge" class="badge"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 bg-paperalt rounded-sm p-6 border border-line">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-4">Simulasi Perhitungan</p>

                        <div class="space-y-3 mb-5">
                            <div class="flex justify-between items-end border-b border-line pb-2">
                                <span class="text-xs font-semibold text-inksoft">Uang Muka (DP)</span>
                                <span class="tabular font-serif font-semibold text-ink" id="m_dp"></span>
                            </div>
                            <div class="flex justify-between items-end border-b border-line pb-2">
                                <span class="text-xs font-semibold text-inksoft">Tenor Kredit</span>
                                <span class="tabular font-serif font-semibold text-ink" id="m_tenor"></span>
                            </div>
                            <div class="flex justify-between items-end border-b border-line pb-2">
                                <span class="text-xs font-semibold text-inksoft">Total Bunga</span>
                                <span class="tabular font-serif font-semibold text-ink" id="m_bunga"></span>
                            </div>
                            <div class="flex justify-between items-end border-b border-line pb-2">
                                <span class="text-xs font-semibold text-inksoft">Total Kewajiban</span>
                                <span class="tabular font-serif font-semibold text-ink" id="m_total"></span>
                            </div>
                        </div>

                        <div class="bg-accentsoft border border-[rgba(62,107,143,.25)] rounded-sm p-4 text-center">
                            <p class="text-[10px] font-bold text-accentdark uppercase tracking-widest mb-1">Cicilan Bulanan</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-accentsoftink" id="m_cicilan"></h3>
                        </div>
                    </div>
                </div>

                <form action="" method="POST" id="formAksi" class="surface p-6">
                    <input type="hidden" name="id_transaksi" id="form_id_trx">

                    <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Catatan Admin (Wajib diisi jika menolak)</label>
                    <textarea name="catatan_admin" id="m_catatan" rows="2" placeholder="Tambahkan alasan atau pesan untuk nasabah..." class="field w-full px-4 py-3 text-sm font-medium mb-4 resize-none"></textarea>

                    <div id="action_buttons" class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="submit" name="action" value="reject" onclick="return confirm('Yakin ingin MENOLAK pengajuan ini?')" class="btn btn-ghost flex-1 py-3.5 inline-flex justify-center items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="8" y1="8" x2="16" y2="16"/></svg> Tolak Pengajuan
                        </button>
                        <button type="submit" name="action" value="approve" onclick="return confirm('Yakin ingin MENYETUJUI pengajuan dan membuat tagihan cicilan otomatis?')" class="btn btn-primary flex-1 py-3.5 inline-flex justify-center items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l6 6L20 6"/></svg> Setujui &amp; Buat Tagihan
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
            // Set Nasabah
            document.getElementById('m_inisial').innerText = data.username.charAt(0).toUpperCase();
            document.getElementById('m_nama').innerText = data.username;
            document.getElementById('m_email').innerText = data.email;
            document.getElementById('m_nohp').innerText = data.no_hp || '-';
            
            // Set Kendaraan
            document.getElementById('m_kendaraan').innerText = data.merk + ' ' + data.model;
            document.getElementById('m_harga').innerText = 'Harga OTR: Rp ' + formatRp(data.harga);
            const imgSrc = data.gambar ? '../uploads/kendaraan/' + data.gambar : 'https://placehold.co/200x150/E8ECEF/97A1AB?text=Foto';
            document.getElementById('m_gambar').src = imgSrc;

            // Set Rincian Kredit
            document.getElementById('m_dp').innerText = 'Rp ' + formatRp(data.dp);
            document.getElementById('m_tenor').innerText = data.tenor + ' Bulan';
            document.getElementById('m_bunga').innerText = 'Rp ' + formatRp(data.bunga);
            document.getElementById('m_total').innerText = 'Rp ' + formatRp(data.total_bayar);
            document.getElementById('m_cicilan').innerText = 'Rp ' + formatRp(data.cicilan_perbulan);
            
            // Set Tanggal
            document.getElementById('m_tgl_trx').innerText = 'Tanggal Pengajuan: ' + data.tanggal_transaksi;

            // Set Status & Form
            document.getElementById('form_id_trx').value = data.id;
            document.getElementById('m_catatan').value = data.catatan_admin || '';
            
            const status = data.status_pengajuan.toLowerCase();
            const badgeEl = document.getElementById('m_status_badge');
            const actionBtns = document.getElementById('action_buttons');
            const statusAlert = document.getElementById('status_alert');
            const catatanInput = document.getElementById('m_catatan');

            if(status === 'menunggu') {
                badgeEl.className = 'badge badge-pending';
                badgeEl.innerText = 'Menunggu';

                actionBtns.classList.remove('hidden');
                statusAlert.classList.add('hidden');
                catatanInput.readOnly = false;
            } else {
                actionBtns.classList.add('hidden');
                statusAlert.classList.remove('hidden');
                catatanInput.readOnly = true;

                if(status === 'disetujui') {
                    badgeEl.className = 'badge badge-approved';
                    badgeEl.innerText = 'Disetujui';
                    statusAlert.className = 'block text-center p-4 rounded-sm font-semibold text-sm border-l-2 border-accent bg-accentsoft text-ink';
                    statusAlert.innerHTML = 'Pengajuan ini sudah disetujui.';
                } else if(status === 'ditolak') {
                    badgeEl.className = 'badge badge-rejected';
                    badgeEl.innerText = 'Ditolak';
                    statusAlert.className = 'block text-center p-4 rounded-sm font-semibold text-sm border-l-2 border-linestrong bg-paperalt text-inksoft';
                    statusAlert.innerHTML = 'Pengajuan ini telah ditolak.';
                }
            }

            // Tampilkan Modal
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