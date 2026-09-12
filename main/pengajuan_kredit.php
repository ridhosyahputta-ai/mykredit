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

// Ambil data user untuk sidebar (Hanya memanggil kolom yang ada)
$q_user = mysqli_query($conn, "SELECT username FROM pengguna WHERE id = '$user_id'");
$user = $q_user ? mysqli_fetch_assoc($q_user) : null;
$nama_user = $user['username'] ?? 'Nasabah';

// 2. Proses Submit Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan_kredit'])) {
    $id_kendaraan = (int)$_POST['id_kendaraan'];
    $dp = (float)$_POST['dp'];
    $tenor = (int)$_POST['tenor'];

    $q_kend = mysqli_query($conn, "SELECT harga FROM kendaraan WHERE id = '$id_kendaraan'");
    if ($q_kend && mysqli_num_rows($q_kend) > 0) {
        $kend = mysqli_fetch_assoc($q_kend);
        $harga = (float)$kend['harga'];
        
        $sisa_kredit = $harga - $dp;
        $bunga_tahunan = 0.08;
        $bunga_nominal = $sisa_kredit * $bunga_tahunan * ($tenor / 12);
        
        $total_utang = $sisa_kredit + $bunga_nominal;
        $cicilan_perbulan = $total_utang / $tenor;
        $total_bayar = $dp + $total_utang;
        $tanggal_sekarang = date('Y-m-d H:i:s');

        // Query Insert 100% disesuaikan dengan struktur tabel
        $query_insert = "INSERT INTO transaksi (id_pengguna, id_kendaraan, dp, tenor, bunga, cicilan_perbulan, total_bayar, status_pengajuan, tanggal_transaksi) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'Menunggu', ?)";
        
        $stmt = mysqli_prepare($conn, $query_insert);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iididdds", $user_id, $id_kendaraan, $dp, $tenor, $bunga_nominal, $cicilan_perbulan, $total_bayar, $tanggal_sekarang);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['alert'] = "Berhasil";
                header("Location: pengajuan_kredit.php");
                exit();
            } else {
                $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Gagal menyimpan pengajuan ke database.</p></div>";
            }
        } else {
            $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Kesalahan pada struktur query database.</p></div>";
        }
    }
}

// Tangkap notifikasi dari session
if (isset($_SESSION['alert']) && $_SESSION['alert'] == 'Berhasil') {
    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><div><h4 class='font-semibold text-sm text-ink'>Pengajuan Berhasil Dikirim!</h4><p class='text-xs text-inksoft mt-0.5'>Tim kami akan segera mereviu pengajuan Anda.</p></div></div>";
    unset($_SESSION['alert']);
}

// 3. Fitur Pencarian
$search = $_GET['search'] ?? '';
$where_clause = "WHERE status = 'Tersedia' AND stok > 0";
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where_clause .= " AND (merk LIKE '%$s%' OR model LIKE '%$s%')";
}

// Query Katalog (Safe Check)
$q_katalog = mysqli_query($conn, "SELECT * FROM kendaraan $where_clause ORDER BY id DESC");

// Query Riwayat Pengajuan User (Telah diperbaiki sesuai dengan nama kolom yang pasti ada)
$q_riwayat = mysqli_query($conn, "
    SELECT t.*, k.merk, k.model, k.gambar 
    FROM transaksi t 
    JOIN kendaraan k ON t.id_kendaraan = k.id 
    WHERE t.id_pengguna = '$user_id' 
    ORDER BY t.tanggal_transaksi DESC
");

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Kredit - MyKredit</title>
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
                <a href="pengajuan_kredit.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                    <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2">Marketplace</p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Katalog Kendaraan</h1>
                    <p class="text-sm text-inksoft mt-2">Pilih kendaraan impian dan ajukan kredit dengan mudah.</p>
                </div>

                <form action="" method="GET" class="w-full md:w-80 relative">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-inkfaint"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari merk atau model..."
                           class="field w-full pl-11 pr-4 py-3 text-sm font-medium">
                </form>
            </header>

            <?= $alert ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 mb-12">
                <?php if ($q_katalog && mysqli_num_rows($q_katalog) > 0): ?>
                    <?php while ($kend = mysqli_fetch_assoc($q_katalog)):
                        $img_path = !empty($kend['gambar']) ? '../uploads/kendaraan/' . $kend['gambar'] : 'https://placehold.co/600x400/F1EDE4/A79E8E?text=No+Image';
                        $harga_rp = number_format($kend['harga'], 0, ',', '.');
                    ?>
                    <div class="surface p-3 flex flex-col">
                        <div class="relative h-44 rounded-sm overflow-hidden bg-paperalt mb-4">
                            <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($kend['merk']) ?>" class="w-full h-full object-cover">
                            <div class="badge badge-neutral absolute top-3 left-3">
                                <span class="badge-dot"></span> Stok <?= $kend['stok'] ?>
                            </div>
                        </div>

                        <div class="px-2 pb-2 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-1.5 text-[10px] font-bold text-inkfaint uppercase tracking-wider">
                                    <span><?= htmlspecialchars($kend['tahun']) ?></span>
                                    <span>•</span>
                                    <span><?= htmlspecialchars($kend['tipe']) ?></span>
                                </div>
                                <h3 class="font-serif font-semibold text-ink text-lg leading-tight mb-3"><?= htmlspecialchars($kend['merk']) ?> <?= htmlspecialchars($kend['model']) ?></h3>
                            </div>

                            <div>
                                <p class="text-[10px] font-bold text-accentdark uppercase tracking-wider mb-0.5">Harga OTR</p>
                                <p class="tabular font-serif font-semibold text-ink text-xl mb-4">Rp <?= $harga_rp ?></p>

                                <button onclick='openKreditModal(<?= json_encode($kend) ?>, "<?= $img_path ?>")'
                                        class="btn btn-primary w-full py-3 text-sm">
                                    Ajukan Kredit
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-16 text-center surface" style="border-style:dashed">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-inkfaint"><path d="M5 17h14M6 17a2 2 0 1 0 0 .01M18 17a2 2 0 1 0 0 .01M5 17l1.5-6h9L18 17M7 11l2-4h5"/></svg>
                        <h3 class="font-serif text-lg font-semibold text-ink">Tidak ada kendaraan</h3>
                        <p class="text-sm text-inksoft mt-1">Kendaraan yang Anda cari sedang kosong.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="surface overflow-hidden mb-10">
                <div class="px-7 sm:px-8 py-6 border-b border-line flex items-center gap-3">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inksoft"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                    <h2 class="font-serif text-xl font-semibold text-ink">Riwayat Pengajuan Anda</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-7 sm:px-8 py-4">Unit Kendaraan</th>
                                <th class="px-7 sm:px-8 py-4">Rincian Kredit</th>
                                <th class="px-7 sm:px-8 py-4">Status</th>
                                <th class="px-7 sm:px-8 py-4 text-right">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <?php if ($q_riwayat && mysqli_num_rows($q_riwayat) > 0): ?>
                                <?php while ($riw = mysqli_fetch_assoc($q_riwayat)):
                                    $img_r = !empty($riw['gambar']) ? '../uploads/kendaraan/' . $riw['gambar'] : 'https://placehold.co/100x100/F1EDE4/A79E8E?text=Img';

                                    $stat = $riw['status_pengajuan'] ?? 'Menunggu';
                                    $badge = "badge-neutral";
                                    if(strtolower($stat) == 'disetujui') $badge = "badge-approved";
                                    if(strtolower($stat) == 'ditolak') $badge = "badge-rejected";
                                    if(in_array(strtolower($stat), ['menunggu', 'pending'])) $badge = "badge-pending";
                                ?>
                                <tr>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center gap-4">
                                            <img src="<?= $img_r ?>" class="w-12 h-12 rounded-sm object-cover border border-line">
                                            <div>
                                                <p class="font-semibold text-ink"><?= htmlspecialchars($riw['merk']) ?> <?= htmlspecialchars($riw['model']) ?></p>
                                                <p class="text-xs text-inksoft mt-0.5">Tenor: <?= htmlspecialchars($riw['tenor']) ?> Bulan</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="text-[10px] text-inkfaint uppercase font-bold tracking-wider mb-1">Cicilan / Bulan</p>
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($riw['cicilan_perbulan'], 0, ',', '.') ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <span class="badge <?= $badge ?>"><span class="badge-dot"></span> <?= htmlspecialchars($stat) ?></span>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4 text-right">
                                        <p class="font-medium text-inksoft"><?= !empty($riw['tanggal_transaksi']) ? date("d M Y", strtotime($riw['tanggal_transaksi'])) : '-' ?></p>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="px-8 py-10 text-center text-inkfaint font-medium">Anda belum pernah melakukan pengajuan kredit.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <div id="modal-backdrop" class="overlay-scrim fixed inset-0 z-[60] hidden opacity-0 transition-opacity duration-300"></div>

    <div id="pengajuanModal" class="fixed inset-0 z-[70] hidden items-center justify-center px-4 opacity-0 transition-all duration-300 transform scale-95">
        <div class="bg-paper w-full max-w-4xl rounded-lg overflow-hidden flex flex-col md:flex-row max-h-[90vh]">

            <div class="w-full md:w-1/2 p-8 md:p-10 overflow-y-auto no-scrollbar border-r border-line">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <p class="text-[10px] font-bold text-accentdark uppercase tracking-widest mb-1">Simulasi Kredit</p>
                        <h3 id="m_title" class="font-serif text-2xl font-semibold text-ink leading-tight">Nama Kendaraan</h3>
                    </div>
                    <button onclick="closeModal()" class="md:hidden w-8 h-8 flex items-center justify-center text-inksoft border border-line rounded-full">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div class="relative h-40 rounded-sm overflow-hidden bg-paperalt mb-6">
                    <img id="m_gambar" src="" class="w-full h-full object-cover">
                </div>

                <form action="" method="POST" id="formPengajuan">
                    <input type="hidden" name="id_kendaraan" id="m_id_kend">
                    <input type="hidden" id="m_harga_asli">

                    <div class="space-y-5">
                        <div>
                            <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Harga Kendaraan (OTR)</label>
                            <div class="tabular font-serif w-full px-5 py-3.5 bg-paperalt border border-line rounded-sm font-semibold text-ink text-lg" id="m_harga_display">Rp 0</div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Uang Muka (DP) - Rp</label>
                            <div class="relative">
                                <span class="absolute left-5 top-1/2 -translate-y-1/2 font-semibold text-inkfaint">Rp</span>
                                <input type="number" name="dp" id="m_dp" required oninput="hitungSimulasi()"
                                       class="field tabular w-full pl-12 pr-5 py-3.5 font-semibold text-lg">
                            </div>
                            <p class="text-[10px] font-bold text-accentdark mt-2">*Minimal DP disarankan 20%</p>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Jangka Waktu (Tenor)</label>
                            <select name="tenor" id="m_tenor" required onchange="hitungSimulasi()"
                                    class="field w-full px-5 py-3.5 font-semibold text-lg appearance-none cursor-pointer">
                                <option value="12">12 Bulan (1 Tahun)</option>
                                <option value="24">24 Bulan (2 Tahun)</option>
                                <option value="36">36 Bulan (3 Tahun)</option>
                                <option value="48">48 Bulan (4 Tahun)</option>
                                <option value="60">60 Bulan (5 Tahun)</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="w-full md:w-1/2 bg-charcoal p-8 md:p-10 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full border border-white/[.06]"></div>
                <button onclick="closeModal()" class="hidden md:flex absolute top-6 right-6 w-9 h-9 items-center justify-center text-sidebarmuted hover:text-sidebarink border border-white/[.12] rounded-full transition-colors z-10">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>

                <div class="relative z-10">
                    <p class="text-[11px] font-bold text-sidebarmuted uppercase tracking-widest mb-6">Rincian Pembayaran</p>

                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center border-b border-white/10 pb-3">
                            <span class="text-sm font-medium text-sidebarmuted">Pokok Kredit</span>
                            <span class="tabular font-semibold text-sidebarink" id="r_pokok">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/10 pb-3">
                            <span class="text-sm font-medium text-sidebarmuted">Estimasi Bunga (8% /thn)</span>
                            <span class="tabular font-semibold text-accent" id="r_bunga">Rp 0</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/10 pb-3">
                            <span class="text-sm font-medium text-sidebarmuted">Total Utang</span>
                            <span class="tabular font-semibold text-sidebarink" id="r_total_utang">Rp 0</span>
                        </div>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-sm p-6 text-center">
                        <p class="text-[11px] font-bold text-accent uppercase tracking-widest mb-2">Cicilan Per Bulan</p>
                        <h2 class="tabular font-serif text-4xl font-semibold text-sidebarink" id="r_cicilan">Rp 0</h2>
                    </div>
                </div>

                <div class="mt-8 relative z-10">
                    <button type="button" onclick="document.getElementById('formPengajuan').submit()" name="ajukan_kredit"
                            class="btn btn-primary w-full py-4 text-base">
                        Kirim Pengajuan
                    </button>
                    <p class="text-[10px] font-medium text-sidebarmuted text-center mt-4">Dengan menekan tombol, Anda menyetujui syarat &amp; ketentuan.</p>
                </div>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();

        // Sidebar Mobile Toggle
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

        // Utility Format Rupiah
        function formatRp(angka) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(angka);
        }

        // Modal Logic
        const backdrop = document.getElementById('modal-backdrop');
        const modal = document.getElementById('pengajuanModal');

        function openKreditModal(kendaraan, imagePath) {
            // Set Data
            document.getElementById('m_id_kend').value = kendaraan.id;
            document.getElementById('m_harga_asli').value = kendaraan.harga;
            document.getElementById('m_title').innerText = kendaraan.merk + ' ' + kendaraan.model;
            document.getElementById('m_harga_display').innerText = 'Rp ' + formatRp(kendaraan.harga);
            document.getElementById('m_gambar').src = imagePath;
            
            // Set Default DP (20%)
            const defaultDp = Math.floor(kendaraan.harga * 0.2);
            document.getElementById('m_dp').value = defaultDp;
            document.getElementById('m_tenor').value = '12';

            hitungSimulasi();

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

        // Fungsi Kalkulator Pintar
        function hitungSimulasi() {
            let harga = parseFloat(document.getElementById('m_harga_asli').value) || 0;
            let dp = parseFloat(document.getElementById('m_dp').value) || 0;
            let tenor = parseInt(document.getElementById('m_tenor').value) || 12;

            // Validasi DP
            if (dp < 0) dp = 0;
            if (dp > harga) dp = harga;

            let pokokKredit = harga - dp;
            let bungaTahunan = 0.08; // 8% Flat per tahun
            let totalBunga = pokokKredit * bungaTahunan * (tenor / 12);
            
            let totalUtang = pokokKredit + totalBunga;
            let cicilanBulanan = totalUtang / tenor;

            // Update UI Sidebar Kanan
            document.getElementById('r_pokok').innerText = 'Rp ' + formatRp(pokokKredit);
            document.getElementById('r_bunga').innerText = 'Rp ' + formatRp(totalBunga);
            document.getElementById('r_total_utang').innerText = 'Rp ' + formatRp(totalUtang);
            document.getElementById('r_cicilan').innerText = 'Rp ' + formatRp(cicilanBulanan);
        }
    </script>
</body>
</html>