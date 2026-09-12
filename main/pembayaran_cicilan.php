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

include '../conn.php';
date_default_timezone_set('Asia/Jakarta');

$user_id = $_SESSION['id_pengguna'];
$alert = '';

// Ambil data user
$q_user = mysqli_query($conn, "SELECT username, email FROM pengguna WHERE id = '$user_id'");
$user = $q_user ? mysqli_fetch_assoc($q_user) : null;
$nama_user = $user['username'] ?? 'Nasabah';

// 2. Folder Upload Bukti
$upload_dir = '../uploads/bukti/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 3. Proses Upload Bukti Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_cicilan'])) {
    $id_pembayaran = (int)$_POST['id_pembayaran'];
    $bank = mysqli_real_escape_string($conn, $_POST['bank']);
    
    // Validasi kepemilikan data (Keamanan)
    $q_cek = mysqli_query($conn, "SELECT id FROM pembayaran WHERE id = '$id_pembayaran' AND id_pengguna = '$user_id'");
    
    if (mysqli_num_rows($q_cek) > 0 && isset($_FILES['bukti']) && $_FILES['bukti']['error'] === 0) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_name = $_FILES['bukti']['name'];
        $file_size = $_FILES['bukti']['size'];
        $file_tmp = $_FILES['bukti']['tmp_name'];
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_ext)) {
            $_SESSION['alert_error'] = "Format file tidak diizinkan. Gunakan JPG, PNG, atau WEBP.";
        } elseif ($file_size > $max_size) {
            $_SESSION['alert_error'] = "Ukuran file terlalu besar. Maksimal 5MB.";
        } else {
            $new_name = uniqid('bukti_') . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $tgl_sekarang = date('Y-m-d H:i:s');
                
                // Gunakan prepared statement untuk update
                $stmt = mysqli_prepare($conn, "UPDATE pembayaran SET tanggal_bayar = ?, metode_bank = ?, bukti_pembayaran = ?, status_verifikasi = 'menunggu' WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssi", $tgl_sekarang, $bank, $new_name, $id_pembayaran);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['alert_success'] = "Bukti pembayaran berhasil dikirim. Menunggu verifikasi admin.";
                } else {
                    $_SESSION['alert_error'] = "Gagal menyimpan data ke database.";
                }
            } else {
                $_SESSION['alert_error'] = "Gagal mengunggah file. Periksa izin folder.";
            }
        }
    } else {
        $_SESSION['alert_error'] = "File bukti pembayaran tidak ditemukan atau data tidak valid.";
    }
    
    header("Location: pembayaran_cicilan.php");
    exit();
}

// Tangkap Notifikasi
if (isset($_SESSION['alert_success'])) {
    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><div><h4 class='font-semibold text-sm text-ink'>Berhasil!</h4><p class='text-xs text-inksoft mt-0.5'>{$_SESSION['alert_success']}</p></div></div>";
    unset($_SESSION['alert_success']);
}
if (isset($_SESSION['alert_error'])) {
    $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><div><h4 class='font-semibold text-sm' style='color:#7A2E22'>Gagal!</h4><p class='text-xs mt-0.5' style='color:#7A2E22'>{$_SESSION['alert_error']}</p></div></div>";
    unset($_SESSION['alert_error']);
}

// 4. Statistik Pembayaran
$stats = ['total' => 0, 'sudah' => 0, 'belum' => 0, 'menunggu' => 0];
$q_stats = mysqli_query($conn, "SELECT status_pembayaran, status_verifikasi FROM pembayaran WHERE id_pengguna = '$user_id'");
if ($q_stats) {
    while ($r = mysqli_fetch_assoc($q_stats)) {
        $stats['total']++;
        $sp = strtolower($r['status_pembayaran']);
        $sv = strtolower($r['status_verifikasi']);
        
        if ($sp === 'sudah_bayar' || $sv === 'disetujui') {
            $stats['sudah']++;
        } elseif ($sv === 'menunggu') {
            $stats['menunggu']++;
        } else {
            $stats['belum']++;
        }
    }
}

// Progress lunas untuk kartu ringkasan (tampilan saja, dari $stats yang sudah dihitung di atas)
$progress_persen_cicilan = ($stats['total'] > 0) ? round(($stats['sudah'] / $stats['total']) * 100) : 0;

// 5. Filter & Search
$search = $_GET['search'] ?? '';
$filter = strtolower($_GET['filter'] ?? 'semua');

$where = ["p.id_pengguna = '$user_id'"];

if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where[] = "(k.merk LIKE '%$s%' OR k.model LIKE '%$s%')";
}

if ($filter === 'belum bayar' || $filter === 'belum_bayar') {
    $where[] = "(LOWER(p.status_verifikasi) = 'belum_kirim' OR LOWER(p.status_verifikasi) = 'ditolak')";
} elseif ($filter === 'menunggu verifikasi' || $filter === 'menunggu') {
    $where[] = "LOWER(p.status_verifikasi) = 'menunggu'";
} elseif ($filter === 'disetujui') {
    $where[] = "LOWER(p.status_verifikasi) = 'disetujui'";
} elseif ($filter === 'ditolak') {
    $where[] = "LOWER(p.status_verifikasi) = 'ditolak'";
}

$where_sql = "WHERE " . implode(" AND ", $where);

// 6. Query Utama
$query_pembayaran = mysqli_query($conn, "
    SELECT p.*, k.merk, k.model, k.gambar 
    FROM pembayaran p 
    JOIN transaksi t ON p.id_transaksi = t.id 
    JOIN kendaraan k ON t.id_kendaraan = k.id 
    $where_sql 
    ORDER BY p.tanggal_jatuh_tempo ASC, p.cicilan_ke ASC
");

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Cicilan - MyKredit</title>
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
                <a href="pembayaran_cicilan.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= $stats['sudah'] ?> dari <?= $stats['total'] ?> Cicilan Lunas</p>
                <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Pembayaran Cicilan</h1>
                <p class="text-sm text-inksoft mt-2">Kelola dan pantau seluruh cicilan kendaraan Anda.</p>
            </header>

            <?= $alert ?>

            <div class="stat-grid surface grid grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Cicilan</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $stats['total'] ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Sudah Dibayar</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $stats['sudah'] ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Belum Dibayar</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $stats['belum'] ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Proses Verifikasi</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $stats['menunggu'] ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-charcoal rounded-lg p-9 md:p-12 mb-8 relative overflow-hidden">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 rounded-full border border-white/[.06]"></div>

                <div class="relative z-10 max-w-xl">
                    <span class="inline-block text-[11px] font-bold uppercase tracking-[0.12em] text-accent mb-4">Status Cicilan</span>
                    <h2 class="font-serif text-3xl md:text-4xl text-sidebarink font-semibold tracking-tight mb-8">Progress Pembayaran Cicilan</h2>

                    <div class="flex items-end justify-between mb-3">
                        <span class="text-sm font-medium text-sidebarmuted">Selesai bayar</span>
                        <span class="tabular font-serif text-4xl font-semibold text-accent"><?= $progress_persen_cicilan ?>%</span>
                    </div>
                    <div class="w-full bg-white/10 h-[3px] rounded-full mb-9">
                        <div class="bg-accent h-[3px] rounded-full" style="width: <?= $progress_persen_cicilan ?>%"></div>
                    </div>

                    <div class="flex flex-wrap gap-x-8 gap-y-3">
                        <div><span class="tabular font-serif text-xl font-semibold text-sidebarink"><?= $stats['sudah'] ?></span> <span class="text-xs text-sidebarmuted uppercase tracking-wider ml-1">Lunas</span></div>
                        <div><span class="tabular font-serif text-xl font-semibold text-sidebarink"><?= $stats['menunggu'] ?></span> <span class="text-xs text-sidebarmuted uppercase tracking-wider ml-1">Menunggu</span></div>
                        <div><span class="tabular font-serif text-xl font-semibold text-sidebarink"><?= $stats['belum'] ?></span> <span class="text-xs text-sidebarmuted uppercase tracking-wider ml-1">Belum Bayar</span></div>
                    </div>
                </div>
            </div>

            <div id="daftar-cicilan" class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="surface inline-flex items-center gap-1 p-1 w-max overflow-x-auto no-scrollbar">
                    <a href="?filter=semua&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter == 'semua' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Semua</a>
                    <a href="?filter=belum+bayar&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter == 'belum bayar' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Belum Bayar</a>
                    <a href="?filter=menunggu&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter == 'menunggu' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Menunggu</a>
                    <a href="?filter=disetujui&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter == 'disetujui' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Disetujui</a>
                    <a href="?filter=ditolak&search=<?= urlencode($search) ?>" class="px-4 py-2 rounded-sm text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-colors <?= $filter == 'ditolak' ? 'bg-accent text-white' : 'text-inksoft hover:bg-paperalt' ?>">Ditolak</a>
                </div>

                <form action="" method="GET" class="relative w-full md:w-72">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-inkfaint"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari merk atau model..."
                           class="field w-full pl-11 pr-4 py-2.5 text-sm font-medium">
                </form>
            </div>

            <?php if ($query_pembayaran && mysqli_num_rows($query_pembayaran) > 0): ?>
                <div class="surface overflow-hidden mb-10">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="text-inkfaint text-[11px] uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-7 sm:px-8 py-4">Kendaraan</th>
                                    <th class="px-7 sm:px-8 py-4">Jatuh Tempo</th>
                                    <th class="px-7 sm:px-8 py-4">Nominal</th>
                                    <th class="px-7 sm:px-8 py-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <?php while ($row = mysqli_fetch_assoc($query_pembayaran)):
                                    $img = !empty($row['gambar']) ? '../uploads/kendaraan/' . $row['gambar'] : 'https://placehold.co/100x100/F1EDE4/A79E8E?text=Img';

                                    $sp = strtolower($row['status_pembayaran']);
                                    $sv = strtolower($row['status_verifikasi']);

                                    // Satu badge status yang merangkum status_pembayaran + status_verifikasi (tampilan saja)
                                    if ($sp === 'sudah_bayar' || $sv === 'disetujui') {
                                        $status_label = 'Lunas'; $status_badge = 'badge-approved';
                                    } elseif ($sv === 'menunggu') {
                                        $status_label = 'Menunggu Verifikasi'; $status_badge = 'badge-pending';
                                    } elseif ($sv === 'ditolak') {
                                        $status_label = 'Ditolak'; $status_badge = 'badge-rejected';
                                    } else {
                                        $status_label = 'Belum Bayar'; $status_badge = 'badge-neutral';
                                    }

                                    // Determine if we show the pay button
                                    $show_pay_btn = ($sv === 'belum_kirim' || $sv === 'ditolak' || empty($row['bukti_pembayaran']));

                                    // Highlight jatuh tempo yang dekat/lewat & belum lunas (tampilan saja)
                                    $is_urgent = $show_pay_btn && (strtotime($row['tanggal_jatuh_tempo']) <= strtotime('+3 days'));

                                    $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr onclick="openModal(<?= $json_data ?>)" class="cursor-pointer hover:bg-paperalt/40 transition-colors">
                                    <td class="px-7 sm:px-8 py-4 <?= $is_urgent ? 'border-l-2 border-accent' : '' ?>">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-sm overflow-hidden bg-paperalt border border-line shrink-0">
                                                <img src="<?= $img ?>" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <p class="font-semibold text-ink"><?= htmlspecialchars($row['merk']) ?> <?= htmlspecialchars($row['model']) ?></p>
                                                <p class="text-[11px] font-semibold text-inkfaint uppercase mt-0.5">Cicilan Ke-<?= $row['cicilan_ke'] ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="font-semibold <?= $is_urgent ? 'text-accentdark' : 'text-inksoft' ?>"><?= date("d M Y", strtotime($row['tanggal_jatuh_tempo'])) ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['total_bayar'], 0, ',', '.') ?></p>
                                    </td>
                                    <td class="px-7 sm:px-8 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <span class="badge <?= $status_badge ?>"><span class="badge-dot"></span> <?= $status_label ?></span>
                                            <?php if ($show_pay_btn): ?>
                                                <span class="hidden sm:inline text-xs font-bold text-accentdark uppercase tracking-wider">Bayar</span>
                                            <?php endif; ?>
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
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    </div>
                    <h3 class="font-serif text-xl font-semibold text-ink mb-2">Belum ada cicilan aktif</h3>
                    <p class="text-sm text-inksoft max-w-md mx-auto leading-relaxed">Anda tidak memiliki tagihan cicilan saat ini atau data tidak ditemukan sesuai filter pencarian.</p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <div id="modal-backdrop" class="overlay-scrim fixed inset-0 z-[60] hidden opacity-0 transition-opacity duration-300"></div>

    <div id="paymentModal" class="fixed inset-0 z-[70] hidden items-center justify-center px-4 opacity-0 transition-all duration-300 transform scale-95">
        <div class="bg-paper w-full max-w-5xl rounded-lg overflow-hidden flex flex-col md:flex-row max-h-[95vh]">

            <div class="w-full md:w-3/5 p-6 md:p-10 overflow-y-auto no-scrollbar border-r border-line">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="font-serif text-2xl font-semibold text-ink" id="m_title">Rincian Pembayaran</h3>
                        <p class="text-[10px] font-bold text-accentdark uppercase tracking-widest mt-1" id="m_subtitle">Silakan selesaikan tagihan</p>
                    </div>
                    <button onclick="closeModal()" class="md:hidden w-8 h-8 flex items-center justify-center text-inksoft border border-line rounded-full">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div class="flex gap-4 mb-8 bg-paperalt p-4 rounded-sm border border-line">
                    <img id="m_gambar" src="" class="w-20 h-20 rounded-sm object-cover bg-paper border border-line shrink-0">
                    <div class="flex flex-col justify-center">
                        <p class="text-[10px] font-bold text-inkfaint uppercase tracking-widest mb-0.5">Cicilan Ke-<span id="m_cicilan_ke"></span></p>
                        <h4 class="font-serif font-semibold text-ink text-lg leading-tight mb-1" id="m_kendaraan"></h4>
                        <p class="text-xs font-semibold text-accentdark">Jatuh Tempo: <span id="m_jatuh_tempo"></span></p>
                    </div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" id="formUpload" class="hidden">
                    <input type="hidden" name="id_pembayaran" id="m_id_pembayaran">

                    <div id="alert_ditolak" class="hidden p-4 rounded-sm mb-6 text-sm font-medium border-l-2" style="border-color:var(--danger);background:var(--danger-soft);color:#7A2E22">
                        Pembayaran sebelumnya ditolak. Silakan unggah ulang bukti transfer yang valid.
                        <p class="text-xs mt-1" style="color:#7A2E22" id="m_catatan_admin"></p>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Pilih Bank Tujuan</label>
                            <div class="relative">
                                <select name="bank" required class="field w-full pl-5 pr-10 py-3.5 font-semibold appearance-none cursor-pointer">
                                    <option value="" disabled selected>Pilih Metode Transfer...</option>
                                    <option value="BCA">BCA - 1234567890 (PT MyKredit)</option>
                                    <option value="BRI">BRI - 0987654321 (PT MyKredit)</option>
                                    <option value="BNI">BNI - 1122334455 (PT MyKredit)</option>
                                    <option value="Mandiri">Mandiri - 5544332211 (PT MyKredit)</option>
                                    <option value="CIMB Niaga">CIMB Niaga - 6677889900 (PT MyKredit)</option>
                                </select>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute right-5 top-1/2 -translate-y-1/2 text-inkfaint pointer-events-none"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-inksoft uppercase tracking-wider mb-2">Upload Bukti Transfer (Max 5MB)</label>
                            <div class="relative border border-dashed border-line-strong rounded-sm bg-paperalt hover:border-accent transition-colors cursor-pointer group h-40 flex items-center justify-center overflow-hidden" onclick="document.getElementById('m_file').click()">
                                <input type="file" name="bukti" id="m_file" accept=".jpg,.jpeg,.png,.webp" class="hidden" required onchange="previewImage(this)">

                                <div id="m_upload_ui" class="text-center">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-2 text-inkfaint group-hover:text-accent transition-colors"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1"/><polyline points="7 9 12 4 17 9"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                                    <p class="text-sm font-semibold text-inksoft">Klik untuk pilih gambar</p>
                                    <p class="text-[10px] text-inkfaint mt-1">JPG, PNG, WEBP</p>
                                </div>
                                <img id="m_preview" src="" class="absolute inset-0 w-full h-full object-cover hidden">
                            </div>
                        </div>

                        <button type="submit" name="bayar_cicilan" class="btn btn-primary w-full py-4 text-base mt-4">
                            Kirim Bukti Pembayaran
                        </button>
                    </div>
                </form>

                <div id="detailView" class="hidden">
                    <p class="text-[11px] font-bold text-inkfaint uppercase tracking-wider mb-3">Bukti Pembayaran Terkirim</p>
                    <div class="bg-paperalt rounded-sm overflow-hidden border border-line mb-6 flex justify-center">
                        <img id="v_bukti" src="" class="max-h-64 object-contain">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-paperalt p-4 rounded-sm border border-line">
                            <p class="text-[10px] font-bold text-inkfaint uppercase mb-1">Metode Bank</p>
                            <p class="font-semibold text-ink text-sm" id="v_bank"></p>
                        </div>
                        <div class="bg-paperalt p-4 rounded-sm border border-line">
                            <p class="text-[10px] font-bold text-inkfaint uppercase mb-1">Tanggal Upload</p>
                            <p class="font-semibold text-ink text-sm" id="v_tgl_upload"></p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="w-full md:w-2/5 bg-charcoal p-8 flex flex-col relative overflow-hidden">
                <div class="absolute -right-16 -bottom-16 w-56 h-56 rounded-full border border-white/[.06]"></div>
                <button onclick="closeModal()" class="hidden md:flex absolute top-6 right-6 w-9 h-9 items-center justify-center text-sidebarmuted hover:text-sidebarink border border-white/[.12] rounded-full transition-colors z-10">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>

                <div class="relative z-10 mb-10">
                    <p class="text-[11px] font-bold text-sidebarmuted uppercase tracking-widest mb-4">Total Tagihan</p>
                    <h2 class="tabular font-serif text-4xl font-semibold text-sidebarink" id="r_total">Rp 0</h2>
                    <p class="text-xs text-sidebarmuted mt-2">Termasuk denda keterlambatan (jika ada).</p>
                </div>

                <div class="relative z-10 flex-1">
                    <p class="text-[11px] font-bold text-sidebarmuted uppercase tracking-widest mb-6 border-b border-white/10 pb-3">Status Timeline</p>

                    <div class="space-y-6" id="m_timeline">
                        </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        lucide.createIcons();

        // Setelah reload karena klik filter/cari, lanjutkan scroll ke daftar cicilan
        // (bukan ke atas halaman) supaya user tidak kehilangan posisi.
        if (new URLSearchParams(window.location.search).has('filter')) {
            const daftarCicilan = document.getElementById('daftar-cicilan');
            if (daftarCicilan) {
                daftarCicilan.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

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
        const modal = document.getElementById('paymentModal');

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('m_preview');
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    document.getElementById('m_upload_ui').classList.add('opacity-0');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openModal(data) {
            // Reset forms
            document.getElementById('formUpload').reset();
            document.getElementById('m_preview').classList.add('hidden');
            document.getElementById('m_preview').src = '';
            document.getElementById('m_upload_ui').classList.remove('opacity-0');

            // Data Umum
            document.getElementById('m_id_pembayaran').value = data.id;
            const imgSrc = data.gambar ? '../uploads/kendaraan/' + data.gambar : 'https://placehold.co/400x300/e2e8f0/64748b?text=Img';
            document.getElementById('m_gambar').src = imgSrc;
            document.getElementById('m_kendaraan').innerText = data.merk + ' ' + data.model;
            document.getElementById('m_cicilan_ke').innerText = data.cicilan_ke;
            
            let dateJt = new Date(data.tanggal_jatuh_tempo);
            document.getElementById('m_jatuh_tempo').innerText = dateJt.toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'});
            document.getElementById('r_total').innerText = 'Rp ' + formatRp(data.total_bayar);

            const statVerif = data.status_verifikasi ? data.status_verifikasi.toLowerCase() : 'belum_kirim';
            
            const formUi = document.getElementById('formUpload');
            const detailUi = document.getElementById('detailView');
            const alertDitolak = document.getElementById('alert_ditolak');
            const catatanAdmin = document.getElementById('m_catatan_admin');

            // Logic View & Form
            if (statVerif === 'belum_kirim' || statVerif === 'ditolak' || !data.bukti_pembayaran) {
                formUi.classList.remove('hidden');
                detailUi.classList.add('hidden');
                document.getElementById('m_title').innerText = 'Bayar Cicilan';
                document.getElementById('m_subtitle').innerText = 'Unggah bukti transfer untuk verifikasi';
                
                if(statVerif === 'ditolak') {
                    alertDitolak.classList.remove('hidden');
                    catatanAdmin.innerText = data.catatan_admin ? 'Catatan Admin: ' + data.catatan_admin : '';
                } else {
                    alertDitolak.classList.add('hidden');
                }
            } else {
                // Menunggu / Disetujui
                formUi.classList.add('hidden');
                detailUi.classList.remove('hidden');
                document.getElementById('m_title').innerText = 'Detail Pembayaran';
                document.getElementById('m_subtitle').innerText = 'Rincian data yang Anda kirim';
                
                document.getElementById('v_bukti').src = '../uploads/bukti/' + data.bukti_pembayaran;
                document.getElementById('v_bank').innerText = data.metode_bank || '-';
                
                let dBayar = data.tanggal_bayar ? new Date(data.tanggal_bayar).toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'}) : '-';
                document.getElementById('v_tgl_upload').innerText = dBayar;
            }

            // Timeline Builder
            const timelineContainer = document.getElementById('m_timeline');
            let tHtml = '';

            // Step 1 selalu ada
            tHtml += `
                <div class="flex gap-4 relative timeline-item">
                    <div class="timeline-line"></div>
                    <div class="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-white text-[10px] z-10 shrink-0 mt-0.5">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div><h4 class="text-sidebarink font-semibold text-sm">Cicilan Dibuat</h4><p class="text-xs text-sidebarmuted mt-1">Tagihan diterbitkan sistem.</p></div>
                </div>
            `;

            if (statVerif === 'belum_kirim') {
                tHtml += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="w-6 h-6 rounded-full bg-white/5 border border-white/15 flex items-center justify-center text-sidebarmuted text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
                        </div>
                        <div><h4 class="text-sidebarink font-semibold text-sm">Menunggu Pembayaran</h4><p class="text-xs text-sidebarmuted mt-1">Silakan upload bukti bayar.</p></div>
                    </div>
                `;
            } else if (statVerif === 'menunggu') {
                tHtml += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="timeline-line"></div>
                        <div class="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-white text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1"/><polyline points="7 9 12 4 17 9"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                        </div>
                        <div><h4 class="text-sidebarink font-semibold text-sm">Bukti Dikirim</h4><p class="text-xs text-sidebarmuted mt-1">Menunggu cek admin.</p></div>
                    </div>
                    <div class="flex gap-4 relative timeline-item">
                        <div class="w-6 h-6 rounded-full bg-white/5 border border-white/15 flex items-center justify-center text-sidebarmuted text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                        </div>
                        <div><h4 class="text-sidebarink font-semibold text-sm">Verifikasi Admin</h4><p class="text-xs text-sidebarmuted mt-1">Sedang diproses.</p></div>
                    </div>
                `;
            } else if (statVerif === 'disetujui') {
                tHtml += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="timeline-line"></div>
                        <div class="w-6 h-6 rounded-full bg-accent flex items-center justify-center text-white text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12l6 6L20 6"/></svg>
                        </div>
                        <div><h4 class="text-sidebarink font-semibold text-sm">Pembayaran Diterima</h4><p class="text-xs text-sidebarmuted mt-1">Cicilan ini telah lunas.</p></div>
                    </div>
                `;
            } else if (statVerif === 'ditolak') {
                tHtml += `
                    <div class="flex gap-4 relative timeline-item">
                        <div class="timeline-line"></div>
                        <div class="w-6 h-6 rounded-full bg-white/5 border border-white/15 flex items-center justify-center text-sidebarmuted text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </div>
                        <div><h4 class="text-sidebarink font-semibold text-sm">Pembayaran Ditolak</h4><p class="text-xs text-sidebarmuted mt-1">Bukti tidak valid.</p></div>
                    </div>
                    <div class="flex gap-4 relative timeline-item">
                        <div class="w-6 h-6 rounded-full bg-white/5 border border-white/15 flex items-center justify-center text-sidebarmuted text-[10px] z-10 shrink-0 mt-0.5">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1"/><polyline points="7 9 12 4 17 9"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                        </div>
                        <div><h4 class="text-sidebarink font-semibold text-sm">Menunggu Upload Ulang</h4><p class="text-xs text-sidebarmuted mt-1">Silakan kirim bukti baru.</p></div>
                    </div>
                `;
            }

            timelineContainer.innerHTML = tHtml;

            // Show Modal
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