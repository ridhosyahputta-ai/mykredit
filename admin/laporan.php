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
// PERIODE — hanya menyaring "Cicilan Lunas" (nilai & kolom periode di
// tabel breakdown), karena itu satu-satunya angka yang benar-benar
// merupakan ALIRAN dalam rentang waktu. Total Transaksi, Kredit Berjalan,
// dan Tunggakan adalah SALDO saat ini (snapshot hari ini), jadi tetap
// sama di semua pilihan periode — menyaringnya justru akan menyesatkan.
// ==========================================
$periode = $_GET['periode'] ?? 'semua';
$tgl_awal = null;
$tgl_akhir = null;
$label_periode = 'Sepanjang Waktu';

switch ($periode) {
    case 'bulan_ini':
        $tgl_awal = date('Y-m-01');
        $tgl_akhir = date('Y-m-t');
        $label_periode = 'Bulan Ini';
        break;
    case 'bulan_lalu':
        $tgl_awal = date('Y-m-01', strtotime('first day of last month'));
        $tgl_akhir = date('Y-m-t', strtotime('last day of last month'));
        $label_periode = 'Bulan Lalu';
        break;
    case '3bulan':
        $tgl_awal = date('Y-m-01', strtotime('-2 months'));
        $tgl_akhir = date('Y-m-t');
        $label_periode = '3 Bulan Terakhir';
        break;
    default:
        $periode = 'semua';
}

$periode_sql = ($tgl_awal !== null) ? "AND tanggal_bayar BETWEEN '$tgl_awal' AND '$tgl_akhir'" : "";

// ==========================================
// STATISTIK UTAMA
// ==========================================

// 1. Total transaksi yang disetujui (snapshot)
$q_total_trx = mysqli_query($conn, "SELECT COUNT(*) as c FROM transaksi WHERE status_pengajuan = 'disetujui'");
$total_trx_disetujui = $q_total_trx ? (mysqli_fetch_assoc($q_total_trx)['c'] ?? 0) : 0;

// 2. Total nilai kredit BERJALAN — didefinisikan sebagai sisa cicilan yang
//    masih belum dibayar (status_pembayaran = 'belum_bayar') dari seluruh
//    kredit yang sudah disetujui. Baris di tabel `pembayaran` hanya pernah
//    dibuat saat admin menyetujui transaksi (lihat transaksi.php), jadi
//    ini otomatis sudah terbatas pada kredit yang disetujui. Dipilih
//    dibanding SUM(total_bayar) transaksi disetujui karena itu adalah nilai
//    kontrak historis (termasuk yang sudah lunas) — bukan eksposur kredit
//    yang "sedang berjalan" saat ini.
$q_kredit_berjalan = mysqli_query($conn, "SELECT COALESCE(SUM(total_bayar), 0) as total FROM pembayaran WHERE status_pembayaran = 'belum_bayar'");
$kredit_berjalan = $q_kredit_berjalan ? (mysqli_fetch_assoc($q_kredit_berjalan)['total'] ?? 0) : 0;

// 3. Total cicilan lunas — dalam periode terpilih (lihat catatan periode di atas)
$q_lunas = mysqli_query($conn, "SELECT COALESCE(SUM(total_bayar), 0) as total, COUNT(*) as c FROM pembayaran WHERE status_pembayaran = 'lunas' $periode_sql");
$row_lunas = $q_lunas ? mysqli_fetch_assoc($q_lunas) : ['total' => 0, 'c' => 0];
$total_lunas_nominal = $row_lunas['total'] ?? 0;
$total_lunas_jumlah = $row_lunas['c'] ?? 0;

// 4. Total tunggakan — cicilan yang sudah lewat jatuh tempo dan masih belum dibayar (snapshot hari ini)
$q_tunggakan = mysqli_query($conn, "SELECT COALESCE(SUM(total_bayar), 0) as total, COUNT(*) as c FROM pembayaran WHERE status_pembayaran = 'belum_bayar' AND tanggal_jatuh_tempo < CURDATE()");
$row_tunggakan = $q_tunggakan ? mysqli_fetch_assoc($q_tunggakan) : ['total' => 0, 'c' => 0];
$total_tunggakan_nominal = $row_tunggakan['total'] ?? 0;
$total_tunggakan_jumlah = $row_tunggakan['c'] ?? 0;

// ==========================================
// BREAKDOWN PER NASABAH
// Setiap subquery pra-agregasi per id_pengguna SEBELUM di-join, supaya
// SUM/COUNT tidak membengkak akibat fan-out dari relasi transaksi -> pembayaran.
// ==========================================
$search = $_GET['search'] ?? '';
$search_sql = '';
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $search_sql = "AND (u.username LIKE '%$s%' OR u.email LIKE '%$s%')";
}

$query_breakdown = mysqli_query($conn, "
    SELECT
        u.id, u.username, u.email,
        tx.jumlah_transaksi, tx.total_kredit,
        COALESCE(bayar.total_dibayar_periode, 0) as total_dibayar_periode,
        COALESCE(tunggak.jumlah_tunggakan, 0) as jumlah_tunggakan,
        COALESCE(belum.jumlah_belum_bayar, 0) as jumlah_belum_bayar
    FROM pengguna u
    JOIN (
        SELECT id_pengguna, COUNT(*) as jumlah_transaksi, SUM(total_bayar) as total_kredit
        FROM transaksi WHERE status_pengajuan = 'disetujui' GROUP BY id_pengguna
    ) tx ON tx.id_pengguna = u.id
    LEFT JOIN (
        SELECT id_pengguna, SUM(total_bayar) as total_dibayar_periode
        FROM pembayaran WHERE status_pembayaran = 'lunas' $periode_sql GROUP BY id_pengguna
    ) bayar ON bayar.id_pengguna = u.id
    LEFT JOIN (
        SELECT id_pengguna, COUNT(*) as jumlah_tunggakan
        FROM pembayaran WHERE status_pembayaran = 'belum_bayar' AND tanggal_jatuh_tempo < CURDATE() GROUP BY id_pengguna
    ) tunggak ON tunggak.id_pengguna = u.id
    LEFT JOIN (
        SELECT id_pengguna, COUNT(*) as jumlah_belum_bayar
        FROM pembayaran WHERE status_pembayaran = 'belum_bayar' GROUP BY id_pengguna
    ) belum ON belum.id_pengguna = u.id
    WHERE 1=1 $search_sql
    ORDER BY tx.total_kredit DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembayaran - Admin MyKredit</title>
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
                <a href="verifikasi_pembayaran.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="shield-check" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Verifikasi Pembayaran</span>
                </a>
                <a href="laporan.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                    <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= number_format($total_trx_disetujui) ?> Transaksi Disetujui</p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Laporan Pembayaran</h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($nama_admin) ?></span>
                    <img src="<?= htmlspecialchars($foto_admin) ?>" alt="Admin" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <!-- Kredit Berjalan berdiri sendiri sebagai angka utama halaman ini —
                 satu-satunya figur yang diberi sentuhan depth (stat-priority + raised type). -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 mb-3">
                <div class="surface stat-priority lg:col-span-1 px-6 py-5 flex items-center gap-4">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Kredit Berjalan</p>
                        <h3 class="figure-raised tabular font-serif text-2xl font-semibold text-ink">Rp <?= number_format($kredit_berjalan, 0, ',', '.') ?></h3>
                    </div>
                </div>

                <div class="stat-grid surface lg:col-span-3 grid grid-cols-3">
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M6 2h9l4 4v16H6z"/><path d="M15 2v4h4"/><path d="M9 13h6M9 17h6"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Transaksi Disetujui</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($total_trx_disetujui) ?></h3>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Cicilan Lunas (<?= htmlspecialchars($label_periode) ?>)</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink">Rp <?= number_format($total_lunas_nominal, 0, ',', '.') ?></h3>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Tunggakan</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink">Rp <?= number_format($total_tunggakan_nominal, 0, ',', '.') ?></h3>
                            <p class="text-[11px] text-inkfaint mt-0.5"><?= number_format($total_tunggakan_jumlah) ?> cicilan telat</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-xs text-inkfaint mb-8">Kredit Berjalan &amp; Tunggakan adalah saldo per hari ini — tidak dipengaruhi filter periode di bawah. Hanya "Cicilan Lunas" dan kolom "Dibayar" pada tabel yang mengikuti periode.</p>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="surface inline-flex items-center gap-1 p-1 w-max overflow-x-auto no-scrollbar">
                    <a href="?periode=semua&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $periode == 'semua' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Semua Waktu</a>
                    <a href="?periode=bulan_ini&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $periode == 'bulan_ini' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Bulan Ini</a>
                    <a href="?periode=bulan_lalu&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $periode == 'bulan_lalu' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Bulan Lalu</a>
                    <a href="?periode=3bulan&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $periode == '3bulan' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">3 Bulan Terakhir</a>
                </div>

                <form action="" method="GET" class="relative w-full md:w-72">
                    <input type="hidden" name="periode" value="<?= htmlspecialchars($periode) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-inkfaint"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nasabah atau email..."
                           class="field w-full pl-11 pr-4 py-2.5 text-sm font-medium">
                </form>
            </div>

            <div class="surface overflow-hidden mb-10">
                <div class="px-7 sm:px-8 py-6 border-b border-line">
                    <h2 class="font-serif text-lg font-semibold text-ink">Ringkasan per Nasabah</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-7 sm:px-8 py-4">Nasabah</th>
                                <th class="px-7 sm:px-8 py-4">Jumlah Transaksi</th>
                                <th class="px-7 sm:px-8 py-4">Total Kredit</th>
                                <th class="px-7 sm:px-8 py-4">Dibayar (<?= htmlspecialchars($label_periode) ?>)</th>
                                <th class="px-7 sm:px-8 py-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <?php if ($query_breakdown && mysqli_num_rows($query_breakdown) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($query_breakdown)):
                                    if ($row['jumlah_tunggakan'] > 0) {
                                        $status_label = 'Ada Tunggakan'; $status_badge = 'badge-rejected';
                                    } elseif ($row['jumlah_belum_bayar'] > 0) {
                                        $status_label = 'Berjalan Normal'; $status_badge = 'badge-pending';
                                    } else {
                                        $status_label = 'Lunas Semua'; $status_badge = 'badge-approved';
                                    }
                                ?>
                                <tr class="hover:bg-paperalt/40 transition-colors">
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
                                        <p class="tabular font-semibold text-ink"><?= number_format($row['jumlah_transaksi']) ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['total_kredit'],0,',','.') ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['total_dibayar_periode'],0,',','.') ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center justify-end">
                                            <span class="badge <?= $status_badge ?>"><span class="badge-dot"></span> <?= $status_label ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-7 py-16 text-center">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-inkfaint"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        <h3 class="font-serif text-lg font-semibold text-ink mb-1">Data tidak ditemukan</h3>
                                        <p class="text-inksoft text-sm">Belum ada nasabah dengan transaksi disetujui yang sesuai pencarian.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

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
    </script>
</body>
</html>
