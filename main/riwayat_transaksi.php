<?php
session_start();

// 1. Keamanan Session & Role Wajib
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
    exit();
}

// 2. Koneksi Database
include '../conn.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['id_pengguna'];

// Ambil data user untuk sidebar
$q_user = mysqli_query($conn, "SELECT username, email FROM pengguna WHERE id = '$user_id'");
$user = $q_user ? mysqli_fetch_assoc($q_user) : null;
$nama_user = $user['username'] ?? 'Nasabah';

// ==========================================
// STATISTIK RINGKAS
// ==========================================
$stats = ['total' => 0, 'menunggu' => 0, 'disetujui' => 0, 'ditolak' => 0];
$q_stats = mysqli_query($conn, "
    SELECT LOWER(status_pengajuan) as stat, COUNT(*) as cnt 
    FROM transaksi 
    WHERE id_pengguna = '$user_id' 
    GROUP BY LOWER(status_pengajuan)
");

if ($q_stats) {
    while ($r = mysqli_fetch_assoc($q_stats)) {
        $stat = $r['stat'];
        $stats[$stat] = $r['cnt'];
        $stats['total'] += $r['cnt'];
    }
}

// ==========================================
// FILTER & SEARCH
// ==========================================
$search = $_GET['search'] ?? '';
$filter_status = strtolower($_GET['status'] ?? 'semua');

$where_clauses = ["t.id_pengguna = '$user_id'"];

if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(k.merk LIKE '%$s%' OR k.model LIKE '%$s%')";
}
if ($filter_status !== 'semua') {
    $stat = mysqli_real_escape_string($conn, $filter_status);
    $where_clauses[] = "LOWER(t.status_pengajuan) = '$stat'";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// ==========================================
// QUERY RIWAYAT TRANSAKSI
// ==========================================
$query_table = mysqli_query($conn, "
    SELECT t.*, k.merk, k.model, k.tahun, k.harga, k.gambar 
    FROM transaksi t 
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
    <title>Riwayat Transaksi - MyKredit</title>
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
                <a href="riwayat_transaksi.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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

            <header class="mb-10">
                <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= number_format($stats['total']) ?> Transaksi Tercatat</p>
                <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Riwayat Transaksi</h1>
                <p class="text-sm text-inksoft mt-2">Lihat seluruh pengajuan kredit yang pernah Anda lakukan.</p>
            </header>

            <div class="stat-grid surface grid grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Pengajuan</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($stats['total']) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M6 3h12M6 21h12M7 3c0 6 10 6 10 9s-10 3-10 9M17 3c0 6-10 6-10 9s10 3 10 9"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Menunggu</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($stats['menunggu']) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Disetujui</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($stats['disetujui']) ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><line x1="8" y1="8" x2="16" y2="16"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Ditolak</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($stats['ditolak']) ?></h3>
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
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari merk atau model..."
                           class="field w-full pl-11 pr-4 py-2.5 text-sm font-medium">
                </form>
            </div>

            <?php if ($query_table && mysqli_num_rows($query_table) > 0): ?>
                <div class="surface overflow-hidden mb-10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-7 sm:px-8 py-4">Kendaraan</th>
                                    <th class="px-7 sm:px-8 py-4">Tanggal Pengajuan</th>
                                    <th class="px-7 sm:px-8 py-4">Nominal</th>
                                    <th class="px-7 sm:px-8 py-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <?php while ($row = mysqli_fetch_assoc($query_table)):
                                    $img = !empty($row['gambar']) ? '../uploads/kendaraan/' . $row['gambar'] : 'https://placehold.co/100x100/F1EDE4/A79E8E?text=Img';

                                    $status = strtolower($row['status_pengajuan'] ?? 'menunggu');
                                    $badge = "badge-pending";
                                    if ($status == 'disetujui') { $badge = "badge-approved"; }
                                    elseif ($status == 'ditolak') { $badge = "badge-rejected"; }

                                    $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr onclick="openModal(<?= $json_data ?>)" class="cursor-pointer hover:bg-paperalt/40 transition-colors">
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-sm overflow-hidden bg-paperalt border border-line shrink-0">
                                                <img src="<?= $img ?>" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <p class="font-semibold text-ink"><?= htmlspecialchars($row['merk']) ?> <?= htmlspecialchars($row['model']) ?></p>
                                                <p class="text-[11px] font-semibold text-inkfaint uppercase mt-0.5"><?= htmlspecialchars($row['tahun']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="font-medium text-inksoft"><?= date("d M Y", strtotime($row['tanggal_transaksi'])) ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['cicilan_perbulan'], 0, ',', '.') ?> <span class="text-xs font-sans font-medium text-inkfaint">/bln</span></p>
                                        <p class="text-xs text-inksoft mt-1">DP Rp <?= number_format($row['dp'], 0, ',', '.') ?> <span class="mx-1">•</span> Tenor <?= $row['tenor'] ?> Bln</p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <span class="badge <?= $badge ?>"><span class="badge-dot"></span> <?= ucwords($status) ?></span>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><polyline points="9 6 15 12 9 18"/></svg>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="surface p-12 flex flex-col items-center justify-center text-center mb-10">
                    <div class="w-16 h-16 mb-6 bg-paperalt rounded-full flex items-center justify-center text-inkfaint">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    </div>
                    <h3 class="font-serif text-xl font-semibold text-ink mb-2">Belum ada riwayat transaksi</h3>
                    <p class="text-sm text-inksoft mb-8 max-w-sm">Anda belum pernah mengajukan kredit kendaraan, atau data tidak ditemukan sesuai filter.</p>

                    <a href="pengajuan_kredit.php" class="btn btn-primary px-6 py-3 text-sm inline-flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17a2 2 0 1 0 0 .01M18 17a2 2 0 1 0 0 .01M5 17l1.5-6h9L18 17M7 11l2-4h5"/></svg>
                        Ajukan Kredit Sekarang
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <div id="modal-backdrop" class="overlay-scrim fixed inset-0 z-[60] hidden opacity-0 transition-opacity duration-300"></div>

    <div id="detailModal" class="fixed inset-0 z-[70] hidden items-center justify-center px-4 opacity-0 transition-all duration-300 transform scale-95">
        <div class="bg-paper w-full max-w-5xl rounded-lg overflow-hidden flex flex-col md:flex-row max-h-[90vh]">

            <div class="w-full md:w-3/5 p-6 md:p-10 overflow-y-auto no-scrollbar border-r border-line">
                <div class="flex justify-between items-center mb-8 border-b border-line pb-4">
                    <h3 class="font-serif text-2xl font-semibold text-ink">Detail Pengajuan</h3>
                    <button onclick="closeModal()" class="md:hidden w-8 h-8 flex items-center justify-center text-inksoft border border-line rounded-full">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row gap-5 mb-8 bg-paperalt p-5 rounded-sm border border-line">
                    <div class="w-full sm:w-32 h-24 rounded-sm overflow-hidden bg-paper border border-line shrink-0">
                        <img id="m_gambar" src="" class="w-full h-full object-cover">
                    </div>
                    <div class="flex flex-col justify-center">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-1" id="m_tahun"></p>
                        <h4 class="font-serif font-semibold text-ink text-xl leading-tight mb-2" id="m_kendaraan"></h4>
                        <span class="inline-block px-2.5 py-1 bg-paper border border-line text-inksoft text-[10px] font-bold rounded-sm w-max" id="m_harga"></span>
                    </div>
                </div>

                <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-4">Rincian Kredit</p>
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div class="border border-line rounded-sm p-4 bg-paper">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-wider mb-1">Uang Muka (DP)</p>
                        <p class="tabular font-serif font-semibold text-ink text-lg" id="m_dp"></p>
                    </div>
                    <div class="border border-line rounded-sm p-4 bg-paper">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-wider mb-1">Tenor / Waktu</p>
                        <p class="tabular font-serif font-semibold text-ink text-lg" id="m_tenor"></p>
                    </div>
                    <div class="border border-line rounded-sm p-4 bg-paper">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-wider mb-1">Total Kewajiban</p>
                        <p class="tabular font-serif font-semibold text-ink text-lg" id="m_total_bayar"></p>
                    </div>
                    <div class="border border-[rgba(181,101,44,.3)] rounded-sm p-4 bg-accentsoft">
                        <p class="text-[10px] font-bold text-accentdark uppercase tracking-wider mb-1">Cicilan Bulanan</p>
                        <p class="tabular font-serif font-semibold text-accentsoftink text-lg" id="m_cicilan"></p>
                    </div>
                </div>

                <div id="m_catatan_container" class="hidden bg-paperalt border border-line rounded-sm p-5 relative overflow-hidden">
                    <p class="text-[10px] font-bold text-inksoft uppercase tracking-widest mb-2 flex items-center gap-1.5">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                        Pesan dari Admin
                    </p>
                    <p class="text-sm font-medium text-ink" id="m_catatan"></p>
                </div>
            </div>

            <div class="w-full md:w-2/5 bg-charcoal p-6 md:p-10 flex flex-col relative overflow-hidden">
                <div class="absolute -right-16 -bottom-16 w-56 h-56 rounded-full border border-white/[.06]"></div>
                <button onclick="closeModal()" class="hidden md:flex absolute top-6 right-6 w-9 h-9 items-center justify-center text-sidebarmuted hover:text-sidebarink border border-white/[.12] rounded-full transition-colors z-10">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>

                <div class="relative z-10 flex-1 flex flex-col justify-center">
                    <p class="text-[11px] font-bold text-sidebarmuted uppercase tracking-widest mb-8">Status Timeline</p>

                    <div class="space-y-6" id="m_timeline">
                        </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();

        const btn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        let isOpen = false;

        function toggleSidebar() {
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
        btn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        function formatRp(angka) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(angka);
        }

        const backdrop = document.getElementById('modal-backdrop');
        const modal = document.getElementById('detailModal');

        function openModal(data) {
            // Set Details
            const imgSrc = data.gambar ? '../uploads/kendaraan/' + data.gambar : 'https://placehold.co/400x300/e2e8f0/64748b?text=Img';
            document.getElementById('m_gambar').src = imgSrc;
            document.getElementById('m_kendaraan').innerText = data.merk + ' ' + data.model;
            document.getElementById('m_tahun').innerText = 'Tahun ' + data.tahun;
            document.getElementById('m_harga').innerText = 'Harga OTR: Rp ' + formatRp(data.harga);
            
            document.getElementById('m_dp').innerText = 'Rp ' + formatRp(data.dp);
            document.getElementById('m_tenor').innerText = data.tenor + ' Bulan';
            document.getElementById('m_total_bayar').innerText = 'Rp ' + formatRp(data.total_bayar);
            document.getElementById('m_cicilan').innerText = 'Rp ' + formatRp(data.cicilan_perbulan);

            // Handle Catatan
            const catContainer = document.getElementById('m_catatan_container');
            if (data.catatan_admin && data.catatan_admin.trim() !== '') {
                document.getElementById('m_catatan').innerText = data.catatan_admin;
                catContainer.classList.remove('hidden');
            } else {
                catContainer.classList.add('hidden');
            }

            // Build Timeline
            const status = data.status_pengajuan ? data.status_pengajuan.toLowerCase() : 'menunggu';
            const timelineContainer = document.getElementById('m_timeline');
            let tglFormat = new Date(data.tanggal_transaksi).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
            
            let timelineHTML = '';

            // Step 1: Pengajuan Dikirim (Selalu Ada)
            timelineHTML += `
                <div class="flex gap-4 relative timeline-item">
                    <div class="timeline-line"></div>
                    <div class="w-6 h-6 rounded-full bg-white/10 border border-white/20 flex items-center justify-center text-sidebarink text-[10px] z-10 shrink-0 mt-0.5">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sidebarink font-semibold text-sm">Pengajuan Dikirim</h4>
                        <p class="text-xs text-sidebarmuted mt-1">${tglFormat}</p>
                    </div>
                </div>
            `;

            if (status === 'menunggu') {
                timelineHTML += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="w-6 h-6 rounded-full bg-white/5 border border-white/15 flex items-center justify-center text-sidebarmuted text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sidebarink font-semibold text-sm">Menunggu Persetujuan</h4>
                            <p class="text-xs text-sidebarmuted mt-1">Tim admin sedang mereviu data Anda.</p>
                        </div>
                    </div>
                `;
            } else if (status === 'disetujui') {
                timelineHTML += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="timeline-line"></div>
                        <div class="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-white text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sidebarink font-semibold text-sm">Disetujui Admin</h4>
                            <p class="text-xs text-sidebarmuted mt-1">Pengajuan telah disahkan.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 relative timeline-item">
                        <div class="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-white text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l6 6L20 6"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sidebarink font-semibold text-sm">Siap Pembayaran Cicilan</h4>
                            <p class="text-xs text-sidebarmuted mt-1">Tagihan cicilan telah dibuat di sistem.</p>
                        </div>
                    </div>
                `;
            } else if (status === 'ditolak') {
                timelineHTML += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="w-6 h-6 rounded-full bg-white/5 border border-white/15 flex items-center justify-center text-sidebarmuted text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sidebarink font-semibold text-sm">Pengajuan Ditolak</h4>
                            <p class="text-xs text-sidebarmuted mt-1">Maaf, pengajuan Anda belum dapat disetujui.</p>
                        </div>
                    </div>
                `;
            }

            timelineContainer.innerHTML = timelineHTML;

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