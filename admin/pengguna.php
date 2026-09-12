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

// ==========================================
// STATISTIK
// ==========================================
$q_total_pengguna = mysqli_query($conn, "SELECT COUNT(*) as c FROM pengguna WHERE role = 'user'");
$total_pengguna = $q_total_pengguna ? (mysqli_fetch_assoc($q_total_pengguna)['c'] ?? 0) : 0;

$q_pengguna_aktif = mysqli_query($conn, "SELECT COUNT(*) as c FROM pengguna WHERE role = 'user' AND id IN (SELECT DISTINCT id_pengguna FROM transaksi)");
$pengguna_aktif = $q_pengguna_aktif ? (mysqli_fetch_assoc($q_pengguna_aktif)['c'] ?? 0) : 0;

$q_pengguna_baru = mysqli_query($conn, "SELECT COUNT(*) as c FROM pengguna WHERE role = 'user' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$pengguna_baru = $q_pengguna_baru ? (mysqli_fetch_assoc($q_pengguna_baru)['c'] ?? 0) : 0;

// ==========================================
// SEARCH & DATA UTAMA
// ==========================================
$search = $_GET['search'] ?? '';
$search_sql = '';
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $search_sql = "AND (u.username LIKE '%$s%' OR u.email LIKE '%$s%')";
}

$query_pengguna = mysqli_query($conn, "
    SELECT u.id, u.username, u.email, u.no_hp, u.created_at,
           COUNT(t.id) as jumlah_transaksi
    FROM pengguna u
    LEFT JOIN transaksi t ON t.id_pengguna = u.id
    WHERE u.role = 'user' $search_sql
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengguna Terdaftar - Admin MyKredit</title>
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
                <a href="laporan.php" class="nav-link flex items-center gap-3 px-3 py-3 rounded-sm">
                    <i data-lucide="printer" class="w-[18px] h-[18px]"></i>
                    <span class="text-sm font-medium">Laporan Pembayaran</span>
                </a>

                <p class="px-3 text-[10px] font-bold text-sidebarmuted uppercase tracking-[0.14em] mb-3 mt-8">Manajemen Akses</p>

                <a href="pengguna.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                    <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= number_format($pengguna_aktif) ?> Punya Transaksi, <?= number_format($pengguna_baru) ?> Baru Bulan Ini</p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Pengguna Terdaftar</h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($nama_admin) ?></span>
                    <img src="<?= htmlspecialchars($foto_admin) ?>" alt="Admin" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 mb-8">
                <div class="surface stat-priority lg:col-span-1 px-6 py-5 flex items-center gap-4">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Pengguna Terdaftar</p>
                        <h3 class="figure-raised tabular font-serif text-2xl font-semibold text-ink"><?= number_format($total_pengguna) ?></h3>
                    </div>
                </div>

                <div class="stat-grid surface lg:col-span-3 grid grid-cols-2">
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M6 2h9l4 4v16H6z"/><path d="M15 2v4h4"/><path d="M9 13h6M9 17h6"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Pengguna dengan Transaksi</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($pengguna_aktif) ?></h3>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex items-center gap-4">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Pengguna Baru Bulan Ini</p>
                            <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= number_format($pengguna_baru) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mb-8">
                <form action="" method="GET" class="relative w-full md:w-80">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-inkfaint"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama atau email..."
                           class="field w-full pl-11 pr-4 py-2.5 text-sm font-medium">
                </form>
            </div>

            <div class="surface overflow-hidden mb-10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-7 sm:px-8 py-4">Nama</th>
                                <th class="px-7 sm:px-8 py-4">No. HP</th>
                                <th class="px-7 sm:px-8 py-4">Tanggal Daftar</th>
                                <th class="px-7 sm:px-8 py-4 text-right">Jumlah Transaksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <?php if ($query_pengguna && mysqli_num_rows($query_pengguna) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($query_pengguna)):
                                    // Riwayat transaksi + ringkasan cicilan user ini, untuk modal detail
                                    $q_riwayat = mysqli_query($conn, "
                                        SELECT t.id, t.status_pengajuan, t.total_bayar, t.tanggal_transaksi,
                                               k.merk, k.model,
                                               COUNT(p.id) as cicilan_total,
                                               SUM(CASE WHEN p.status_pembayaran = 'lunas' THEN 1 ELSE 0 END) as cicilan_lunas
                                        FROM transaksi t
                                        JOIN kendaraan k ON t.id_kendaraan = k.id
                                        LEFT JOIN pembayaran p ON p.id_transaksi = t.id
                                        WHERE t.id_pengguna = '{$row['id']}'
                                        GROUP BY t.id
                                        ORDER BY t.tanggal_transaksi DESC
                                    ");
                                    $riwayat = [];
                                    if ($q_riwayat) {
                                        while ($rw = mysqli_fetch_assoc($q_riwayat)) { $riwayat[] = $rw; }
                                    }
                                    $row['riwayat'] = $riwayat;
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
                                    <td class="px-7 sm:px-8 py-4 text-inksoft font-medium"><?= htmlspecialchars($row['no_hp'] ?? '-') ?></td>
                                    <td class="px-7 sm:px-8 py-4 text-inksoft text-xs font-semibold"><?= date("d M Y", strtotime($row['created_at'])) ?></td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <p class="tabular font-serif font-semibold text-ink"><?= number_format($row['jumlah_transaksi']) ?></p>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><polyline points="9 6 15 12 9 18"/></svg>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-7 py-16 text-center">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-inkfaint"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <h3 class="font-serif text-lg font-semibold text-ink mb-1">Data tidak ditemukan</h3>
                                        <p class="text-inksoft text-sm">Belum ada pengguna yang sesuai dengan pencarian.</p>
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
        <div class="bg-paper w-full max-w-3xl rounded-lg overflow-hidden flex flex-col max-h-[90vh]">

            <div class="px-8 py-5 border-b border-line flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-paperalt flex items-center justify-center text-inksoft font-bold text-lg border border-line" id="m_inisial"></div>
                    <div>
                        <p class="font-serif font-semibold text-ink text-lg" id="m_nama"></p>
                        <p class="text-xs text-inksoft" id="m_email"></p>
                    </div>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center text-inksoft border border-line rounded-full hover:border-linestrong transition-colors shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="overflow-y-auto no-scrollbar p-8">
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div class="border border-line rounded-sm p-4">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-wider mb-1">No. HP</p>
                        <p class="font-semibold text-ink text-sm" id="m_nohp"></p>
                    </div>
                    <div class="border border-line rounded-sm p-4">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-wider mb-1">Tanggal Daftar</p>
                        <p class="font-semibold text-ink text-sm" id="m_tgl_daftar"></p>
                    </div>
                </div>

                <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-3 border-b border-line pb-2">Riwayat Transaksi</p>
                <div id="m_riwayat" class="divide-y divide-line"></div>
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

            const tglDaftar = new Date(data.created_at);
            document.getElementById('m_tgl_daftar').innerText = tglDaftar.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });

            const riwayatContainer = document.getElementById('m_riwayat');
            riwayatContainer.innerHTML = '';

            if (!data.riwayat || data.riwayat.length === 0) {
                riwayatContainer.innerHTML = '<div class="py-8 text-center text-inkfaint text-sm font-medium">Belum pernah mengajukan kredit.</div>';
            } else {
                data.riwayat.forEach(function (r) {
                    const status = (r.status_pengajuan || 'menunggu').toLowerCase();
                    let badgeClass = 'badge-pending';
                    if (status === 'disetujui') badgeClass = 'badge-approved';
                    else if (status === 'ditolak') badgeClass = 'badge-rejected';

                    const tglTrx = new Date(r.tanggal_transaksi).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                    const cicilanInfo = r.cicilan_total > 0
                        ? `${r.cicilan_lunas} dari ${r.cicilan_total} cicilan lunas`
                        : 'Belum ada tagihan cicilan';

                    const row = document.createElement('div');
                    row.className = 'flex items-center justify-between py-4 gap-4';
                    row.innerHTML = `
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">${r.merk} ${r.model}</p>
                            <p class="text-xs text-inksoft mt-0.5">${tglTrx} &bull; ${cicilanInfo}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="tabular font-serif font-semibold text-ink mb-1">Rp ${formatRp(r.total_bayar)}</p>
                            <span class="badge ${badgeClass}"><span class="badge-dot"></span> ${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                        </div>
                    `;
                    riwayatContainer.appendChild(row);
                });
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
