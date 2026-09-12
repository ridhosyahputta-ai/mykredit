<?php
session_start();

// 1. Keamanan Session & Role
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

// --- TAMBAHAN: Ambil Data Admin untuk Header ---
$admin_id = $_SESSION['id_pengguna'] ?? 0;
$q_admin = mysqli_query($conn, "SELECT username, foto FROM pengguna WHERE id = '$admin_id'");
$admin = ($q_admin && mysqli_num_rows($q_admin) > 0) ? mysqli_fetch_assoc($q_admin) : null;
$nama_admin = $admin['username'] ?? 'Administrator';
$foto_admin = !empty($admin['foto']) ? $admin['foto'] : 'https://ui-avatars.com/api/?name=' . urlencode($nama_admin) . '&background=0f172a&color=10b981&bold=true';

$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$tanggal_sekarang = $hari[date("w")] . ", " . date("d") . " " . $bulan[date("n")] . " " . date("Y");
// ------------------------------------------------

// Folder upload
$upload_dir = '../uploads/kendaraan/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$alert = '';

// 2. Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Ambil Data Input Umum
    $id = $_POST['id'] ?? '';
    $merk = mysqli_real_escape_string($conn, $_POST['merk'] ?? '');
    $model = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe'] ?? '');
    $tahun = (int)($_POST['tahun'] ?? 0);
    $harga = (float)($_POST['harga'] ?? 0);
    $stok = (int)($_POST['stok'] ?? 0);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? '');
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Tersedia');

    // Fungsi Upload Gambar
    function handleUpload() {
        global $upload_dir;
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $file_name = $_FILES['gambar']['name'];
            $file_size = $_FILES['gambar']['size'];
            $file_tmp = $_FILES['gambar']['tmp_name'];

            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_ext, true)) return ['error' => 'Format file tidak diizinkan! (Gunakan JPG, JPEG, PNG, WEBP)'];
            if ($file_size > $max_size) return ['error' => 'Ukuran file terlalu besar! (Maksimal 2MB)'];

            // Verifikasi MIME type asli isi file (bukan cuma percaya nama/ekstensi)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            if (!in_array($real_mime, $allowed_mime, true)) {
                return ['error' => 'Isi file tidak sesuai format gambar yang diizinkan.'];
            }

            // Pastikan file benar-benar bisa didekode sebagai gambar (bukan file lain berkedok ekstensi)
            if (@getimagesize($file_tmp) === false) {
                return ['error' => 'File yang diunggah bukan gambar yang valid.'];
            }

            // Nama file baru yang aman (acak), bukan nama asli dari user
            $new_name = uniqid('veh_', true) . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                return ['success' => $new_name];
            }
            return ['error' => 'Gagal mengunggah file.'];
        }
        return ['error' => 'Pilih gambar terlebih dahulu.'];
    }

    // ACTION: TAMBAH
    if ($action === 'add') {
        $upload = handleUpload();
        if (isset($upload['success'])) {
            $gambar = $upload['success'];
            $query = "INSERT INTO kendaraan (merk, model, tipe, tahun, harga, stok, gambar, deskripsi, status) 
                      VALUES ('$merk', '$model', '$tipe', '$tahun', '$harga', '$stok', '$gambar', '$deskripsi', '$status')";
            if (mysqli_query($conn, $query)) {
                $_SESSION['alert'] = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>Kendaraan berhasil ditambahkan!</p></div>";
                header("Location: kendaraan.php"); exit;
            } else {
                $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>Gagal menyimpan ke database!</p></div>";
            }
        } else {
            $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>" . $upload['error'] . "</p></div>";
        }
    }

    // ACTION: EDIT
    elseif ($action === 'edit' && !empty($id)) {
        $q_old = mysqli_query($conn, "SELECT gambar FROM kendaraan WHERE id = '$id'");
        $old_data = mysqli_fetch_assoc($q_old);
        $gambar = $old_data['gambar'] ?? '';

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
            $upload = handleUpload();
            if (isset($upload['success'])) {
                // Hapus gambar lama
                if (!empty($gambar) && file_exists($upload_dir . $gambar)) unlink($upload_dir . $gambar);
                $gambar = $upload['success'];
            } else {
                $alert = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 rounded-sm' style='border-color:var(--danger);background:var(--danger-soft)'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#7A2E22' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='shrink-0'><path d='M12 9v4M12 17h.01'/><path d='M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z'/></svg><p class='text-sm font-medium' style='color:#7A2E22'>" . $upload['error'] . "</p></div>";
            }
        }

        if (empty($alert)) {
            $query = "UPDATE kendaraan SET merk='$merk', model='$model', tipe='$tipe', tahun='$tahun', harga='$harga', stok='$stok', deskripsi='$deskripsi', status='$status', gambar='$gambar' WHERE id='$id'";
            if (mysqli_query($conn, $query)) {
                $_SESSION['alert'] = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>Data kendaraan diperbarui!</p></div>";
                header("Location: kendaraan.php"); exit;
            }
        }
    }

    // ACTION: HAPUS
    elseif ($action === 'delete' && !empty($id)) {
        $q_old = mysqli_query($conn, "SELECT gambar FROM kendaraan WHERE id = '$id'");
        if ($old_data = mysqli_fetch_assoc($q_old)) {
            if (!empty($old_data['gambar']) && file_exists($upload_dir . $old_data['gambar'])) {
                unlink($upload_dir . $old_data['gambar']);
            }
            mysqli_query($conn, "DELETE FROM kendaraan WHERE id = '$id'");
            $_SESSION['alert'] = "<div class='flex items-center gap-3 px-5 py-4 mb-8 border-l-2 border-accent bg-accentsoft rounded-sm'><svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' class='text-accentdark shrink-0'><circle cx='12' cy='12' r='9'/><path d='M8 12.5l2.5 2.5L16 9.5'/></svg><p class='text-sm font-medium text-ink'>Kendaraan berhasil dihapus!</p></div>";
            header("Location: kendaraan.php"); exit;
        }
    }
}

if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}

// 3. Statistik
$q_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM kendaraan"))['c'] ?? 0;
$q_tersedia = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM kendaraan WHERE status = 'Tersedia' AND stok > 0"))['c'] ?? 0;
$q_habis = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM kendaraan WHERE status != 'Tersedia' OR stok <= 0"))['c'] ?? 0;

// 4. Search & Get Data
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $search_query = "WHERE merk LIKE '%$s%' OR model LIKE '%$s%'";
}
$query_kendaraan = mysqli_query($conn, "SELECT * FROM kendaraan $search_query ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kendaraan - Admin MyKredit</title>
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
                <a href="kendaraan.php" class="nav-link active flex items-center gap-3 px-3 py-3 rounded-sm">
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
                    <p class="text-xs font-semibold text-inksoft uppercase tracking-[0.1em] mb-2"><?= $q_tersedia ?> Kendaraan Tersedia, <?= $q_habis ?> Habis</p>
                    <h1 class="font-serif text-4xl md:text-[2.75rem] leading-tight font-semibold text-ink tracking-tight">Kelola Kendaraan</h1>
                </div>
                <div class="flex items-center gap-3 bg-paper pl-4 pr-1.5 py-1.5 rounded-full border border-line w-max">
                    <span class="text-sm font-semibold text-ink"><?= htmlspecialchars($nama_admin) ?></span>
                    <img src="<?= htmlspecialchars($foto_admin) ?>" alt="Admin" class="w-9 h-9 rounded-full object-cover border border-line">
                </div>
            </header>

            <?= $alert ?>

            <div class="surface grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-line mb-8">
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Total Unit</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $q_total ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent shrink-0"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Tersedia</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $q_tersedia ?></h3>
                    </div>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-inkfaint shrink-0"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em]">Habis / Tidak Aktif</p>
                        <h3 class="tabular font-serif text-2xl font-semibold text-ink"><?= $q_habis ?></h3>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-8">
                <form action="" method="GET" class="w-full sm:w-auto relative">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-4 top-1/2 -translate-y-1/2 text-inkfaint"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari merk atau model..."
                           class="field w-full sm:w-80 pl-11 pr-4 py-3 text-sm font-medium">
                </form>

                <button onclick="openModal('formModal')" class="btn btn-primary w-full sm:w-auto px-6 py-3 text-sm inline-flex items-center justify-center gap-2">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Unit Baru
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 pb-10">
                <?php if (mysqli_num_rows($query_kendaraan) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($query_kendaraan)):
                        $img_src = !empty($row['gambar']) && file_exists($upload_dir . $row['gambar']) ? $upload_dir . $row['gambar'] : 'https://placehold.co/600x400/F1EDE4/A79E8E?text=No+Image';
                        $is_tersedia = ($row['status'] == 'Tersedia' && $row['stok'] > 0);
                        $status_badge = $is_tersedia ? 'badge-approved' : 'badge-neutral';
                    ?>
                    <div class="surface overflow-hidden flex flex-col group">
                        <div class="relative h-40 overflow-hidden bg-paperalt">
                            <img src="<?= $img_src ?>" alt="Foto Kendaraan" class="w-full h-full object-cover">
                            <div class="overlay-scrim absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                                <button onclick='editVehicle(<?= json_encode($row) ?>)' class="btn btn-primary w-9 h-9 rounded-full flex items-center justify-center" title="Edit">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
                                </button>
                                <button onclick="deleteVehicle(<?= $row['id'] ?>)" class="w-9 h-9 rounded-full flex items-center justify-center border border-white/25 text-white/80 hover:text-white hover:border-white/50 transition-colors" title="Hapus">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/></svg>
                                </button>
                            </div>
                            <span class="badge <?= $status_badge ?> absolute top-3 left-3"><span class="badge-dot"></span> <?= htmlspecialchars($row['status']) ?></span>
                        </div>

                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-1.5 text-[10px] font-bold text-inkfaint uppercase tracking-wider">
                                    <span><?= htmlspecialchars($row['tahun']) ?></span>
                                    <span>•</span>
                                    <span><?= htmlspecialchars($row['tipe']) ?></span>
                                </div>
                                <h3 class="font-serif font-semibold text-ink text-lg leading-tight"><?= htmlspecialchars($row['merk']) ?> <?= htmlspecialchars($row['model']) ?></h3>
                            </div>

                            <div class="mt-4 pt-4 border-t border-line flex items-end justify-between">
                                <div>
                                    <p class="text-[10px] font-bold text-inkfaint uppercase mb-0.5">Harga Tunai</p>
                                    <p class="tabular font-serif font-semibold text-ink">Rp <?= number_format($row['harga'], 0, ',', '.') ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold text-inkfaint uppercase mb-0.5">Sisa Stok</p>
                                    <p class="tabular font-semibold text-ink"><?= $row['stok'] ?> Unit</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full surface py-20 flex flex-col items-center justify-center text-center" style="border-style:dashed">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mb-4 text-inkfaint"><path d="M3 16l1-5a2 2 0 0 1 2-1.5h12A2 2 0 0 1 20 11l1 5"/><rect x="2" y="16" width="20" height="3" rx="1"/><circle cx="7" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/></svg>
                        <h3 class="font-serif text-lg font-semibold text-ink mb-1">Belum ada kendaraan</h3>
                        <p class="text-sm text-inksoft">Klik "Tambah Unit Baru" untuk memasukkan data katalog.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="modal-backdrop" class="overlay-scrim fixed inset-0 z-[60] hidden opacity-0 transition-opacity duration-300"></div>

    <div id="formModal" class="fixed inset-0 z-[70] hidden items-center justify-center px-4 opacity-0 transition-all duration-300 transform scale-95">
        <div class="bg-paper w-full max-w-3xl rounded-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-7 py-5 border-b border-line flex justify-between items-center">
                <h3 id="modalTitle" class="font-serif text-xl font-semibold text-ink">Tambah Kendaraan</h3>
                <button onclick="closeModal('formModal')" class="w-8 h-8 flex items-center justify-center text-inksoft border border-line rounded-full hover:border-linestrong transition-colors">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="overflow-y-auto no-scrollbar p-7">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="vehId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <div>
                        <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Merk</label>
                        <input type="text" name="merk" id="vehMerk" required placeholder="Cth: Toyota" class="field w-full px-4 py-3 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Model</label>
                        <input type="text" name="model" id="vehModel" required placeholder="Cth: Avanza Veloz" class="field w-full px-4 py-3 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Tipe (Transmisi dll)</label>
                        <input type="text" name="tipe" id="vehTipe" required placeholder="Cth: AT / MT / Hybrid" class="field w-full px-4 py-3 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Tahun Rilis</label>
                        <input type="number" name="tahun" id="vehTahun" required placeholder="Cth: 2023" class="field w-full px-4 py-3 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Harga (Rp)</label>
                        <input type="number" name="harga" id="vehHarga" required placeholder="Cth: 250000000" class="field tabular w-full px-4 py-3 text-sm font-semibold">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Stok</label>
                            <input type="number" name="stok" id="vehStok" required value="1" class="field w-full px-4 py-3 text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Status</label>
                            <select name="status" id="vehStatus" class="field w-full px-4 py-3 text-sm font-medium">
                                <option value="Tersedia">Tersedia</option>
                                <option value="Habis">Habis</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Deskripsi &amp; Fitur</label>
                    <textarea name="deskripsi" id="vehDeskripsi" rows="3" placeholder="Jelaskan kondisi, fitur, atau promo..." class="field w-full px-4 py-3 text-sm font-medium resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-inksoft uppercase tracking-[0.08em] mb-1.5">Foto Kendaraan (Max 2MB)</label>
                    <div class="relative border border-dashed border-linestrong rounded-sm bg-paperalt hover:border-accent transition-colors cursor-pointer group h-36 flex items-center justify-center overflow-hidden" onclick="document.getElementById('vehGambar').click()">
                        <input type="file" name="gambar" id="vehGambar" accept=".jpg,.jpeg,.png,.webp" class="hidden" onchange="previewImage(this)">

                        <div id="uploadPlaceholder" class="text-center">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-2 text-inkfaint group-hover:text-accent transition-colors"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1"/><polyline points="7 9 12 4 17 9"/><line x1="12" y1="4" x2="12" y2="16"/></svg>
                            <p class="text-sm font-semibold text-inksoft">Klik untuk upload gambar</p>
                            <p class="text-[10px] text-inkfaint mt-1">JPG, PNG, WEBP</p>
                        </div>
                        <img id="imagePreview" src="" class="absolute inset-0 w-full h-full object-cover hidden">
                    </div>
                    <p id="editImageHelp" class="text-xs text-accentdark font-medium mt-2 hidden">*Biarkan kosong jika tidak ingin mengubah foto.</p>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-line">
                    <button type="button" onclick="closeModal('formModal')" class="btn btn-ghost px-6 py-3 text-sm">Batal</button>
                    <button type="submit" class="btn btn-primary px-6 py-3 text-sm">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-[70] hidden items-center justify-center px-4 opacity-0 transition-all duration-300 transform scale-95">
        <div class="bg-paper w-full max-w-sm rounded-lg p-7 text-center border border-line">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center" style="background:var(--danger-soft)">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7A2E22" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L2.4 18a1.5 1.5 0 0 0 1.3 2.2h16.6a1.5 1.5 0 0 0 1.3-2.2L13.7 3.9a1.5 1.5 0 0 0-2.7 0z"/></svg>
            </div>
            <h3 class="font-serif text-xl font-semibold text-ink mb-2">Hapus Kendaraan?</h3>
            <p class="text-sm text-inksoft mb-6">Tindakan ini tidak dapat dibatalkan. Foto kendaraan juga akan dihapus dari server.</p>

            <form action="" method="POST" class="flex gap-3 justify-center">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="">
                <button type="button" onclick="closeModal('deleteModal')" class="btn btn-ghost flex-1 py-3 text-sm">Batal</button>
                <button type="submit" class="btn btn-danger flex-1 py-3 text-sm">Ya, Hapus</button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const backdrop = document.getElementById('modal-backdrop');

        function openModal(modalId) {
            backdrop.classList.remove('hidden');
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                modal.classList.remove('opacity-0', 'scale-95');
            }, 10);

            if(modalId === 'formModal' && document.getElementById('formAction').value !== 'edit') {
                resetForm();
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            
            backdrop.classList.add('opacity-0');
            modal.classList.add('opacity-0', 'scale-95');
            
            setTimeout(() => {
                backdrop.classList.add('hidden');
                modal.classList.add('hidden');
                modal.style.display = 'none';
                if(modalId === 'formModal') resetForm();
            }, 300);
        }

        function resetForm() {
            document.getElementById('formAction').value = 'add';
            document.getElementById('vehId').value = '';
            document.getElementById('modalTitle').innerText = 'Tambah Kendaraan';
            document.getElementById('vehMerk').value = '';
            document.getElementById('vehModel').value = '';
            document.getElementById('vehTipe').value = '';
            document.getElementById('vehTahun').value = '';
            document.getElementById('vehHarga').value = '';
            document.getElementById('vehStok').value = '1';
            document.getElementById('vehStatus').value = 'Tersedia';
            document.getElementById('vehDeskripsi').value = '';
            
            document.getElementById('vehGambar').required = true;
            document.getElementById('editImageHelp').classList.add('hidden');
            
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('imagePreview').src = '';
            document.getElementById('uploadPlaceholder').classList.remove('hidden');
        }

        function editVehicle(data) {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('vehId').value = data.id;
            document.getElementById('modalTitle').innerText = 'Edit Data Kendaraan';
            
            document.getElementById('vehMerk').value = data.merk;
            document.getElementById('vehModel').value = data.model;
            document.getElementById('vehTipe').value = data.tipe;
            document.getElementById('vehTahun').value = data.tahun;
            document.getElementById('vehHarga').value = data.harga;
            document.getElementById('vehStok').value = data.stok;
            document.getElementById('vehStatus').value = data.status;
            document.getElementById('vehDeskripsi').value = data.deskripsi;

            document.getElementById('vehGambar').required = false;
            document.getElementById('editImageHelp').classList.remove('hidden');
            
            if(data.gambar) {
                const preview = document.getElementById('imagePreview');
                preview.src = '../uploads/kendaraan/' + data.gambar;
                preview.classList.remove('hidden');
                document.getElementById('uploadPlaceholder').classList.add('hidden');
            }

            openModal('formModal');
        }

        function deleteVehicle(id) {
            document.getElementById('deleteId').value = id;
            openModal('deleteModal');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    document.getElementById('uploadPlaceholder').classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

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