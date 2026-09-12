<?php
/**
 * SIDEBAR TEMPLATE KONSISTEN - MyKredit
 * 
 * Gunakan template ini untuk memastikan semua halaman user memiliki sidebar yang konsisten.
 * Setiap halaman harus mengubah nilai "HALAMAN_AKTIF" sesuai dengan halaman yang ditampilkan.
 * 
 * HALAMAN_AKTIF yang tersedia:
 * - dashboard
 * - pengajuan_kredit
 * - riwayat_transaksi
 * - pembayaran_cicilan
 * - profil_akun
 * - settings
 */

// Tentukan halaman aktif (ubah sesuai halaman yang ditampilkan)
$halaman_aktif = 'dashboard'; // Ubah ini di setiap file

// Fungsi untuk menentukan class active
function getMenuClass($halaman, $halaman_aktif) {
    if ($halaman === $halaman_aktif) {
        return 'bg-slate-900 text-white rounded-2xl shadow-lg shadow-slate-200/50 transition-all';
    } else {
        return 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 rounded-2xl group transition-all';
    }
}

// Fungsi untuk menentukan icon color
function getIconClass($halaman, $halaman_aktif) {
    if ($halaman === $halaman_aktif) {
        return 'text-emerald-400';
    } else {
        return 'text-slate-400 group-hover:text-emerald-500 transition-colors';
    }
}

// Fungsi untuk menentukan text weight
function getTextWeight($halaman, $halaman_aktif) {
    if ($halaman === $halaman_aktif) {
        return 'semibold';
    } else {
        return 'medium';
    }
}
?>

<!-- SIDEBAR HTML -->
<aside id="sidebar" class="bg-white w-72 fixed inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-out z-40 lg:static border-r border-slate-100 flex flex-col justify-between shadow-2xl lg:shadow-none">
    
    <!-- Logo -->
    <div class="hidden lg:flex items-center gap-3 px-8 pt-8 pb-6">
        <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center text-white shadow-lg shadow-emerald-200/50">
            <i class="fa-solid fa-wallet text-lg"></i>
        </div>
        <span class="text-2xl font-black text-slate-900 tracking-tight">MyKredit<span class="text-emerald-500">.</span></span>
    </div>
    
    <!-- Menu Navigation -->
    <div class="flex-1 overflow-y-auto py-8 lg:py-4 px-4 space-y-1 mt-16 lg:mt-0 no-scrollbar">
        
        <!-- Menu Utama Section -->
        <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Menu Utama</p>
        
        <!-- Dashboard Utama -->
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3.5 <?= getMenuClass('dashboard', $halaman_aktif) ?>">
            <i class="fa-solid fa-house w-5 text-center <?= getIconClass('dashboard', $halaman_aktif) ?>"></i> 
            <span class="font-<?= getTextWeight('dashboard', $halaman_aktif) ?> text-sm">Dashboard Utama</span>
        </a>
        
        <!-- Pengajuan Kredit -->
        <a href="pengajuan_kredit.php" class="flex items-center gap-3 px-4 py-3.5 <?= getMenuClass('pengajuan_kredit', $halaman_aktif) ?>">
            <i class="fa-solid fa-file-signature w-5 text-center <?= getIconClass('pengajuan_kredit', $halaman_aktif) ?>"></i> 
            <span class="font-<?= getTextWeight('pengajuan_kredit', $halaman_aktif) ?> text-sm">Pengajuan Kredit</span>
        </a>
        
        <!-- Riwayat Transaksi -->
        <a href="riwayat_transaksi.php" class="flex items-center gap-3 px-4 py-3.5 <?= getMenuClass('riwayat_transaksi', $halaman_aktif) ?>">
            <i class="fa-solid fa-arrow-right-arrow-left w-5 text-center <?= getIconClass('riwayat_transaksi', $halaman_aktif) ?>"></i> 
            <span class="font-<?= getTextWeight('riwayat_transaksi', $halaman_aktif) ?> text-sm">Riwayat Transaksi</span>
        </a>
        
        <!-- Pembayaran Cicilan -->
        <a href="pembayaran_cicilan.php" class="flex items-center gap-3 px-4 py-3.5 <?= getMenuClass('pembayaran_cicilan', $halaman_aktif) ?>">
            <i class="fa-solid fa-money-bill-wave w-5 text-center <?= getIconClass('pembayaran_cicilan', $halaman_aktif) ?>"></i> 
            <span class="font-<?= getTextWeight('pembayaran_cicilan', $halaman_aktif) ?> text-sm">Pembayaran Cicilan</span>
        </a>

        <!-- Pengaturan Section -->
        <p class="px-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 mt-8">Pengaturan</p>
        
        <!-- Profil -->
        <a href="profil_akun.php" class="flex items-center gap-3 px-4 py-3.5 <?= getMenuClass('profil_akun', $halaman_aktif) ?>">
            <i class="fa-regular fa-user w-5 text-center <?= getIconClass('profil_akun', $halaman_aktif) ?>"></i> 
            <span class="font-<?= getTextWeight('profil_akun', $halaman_aktif) ?> text-sm">Profil</span>
        </a>

        <!-- Settings -->
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3.5 <?= getMenuClass('settings', $halaman_aktif) ?>">
            <i class="fa-solid fa-gear w-5 text-center <?= getIconClass('settings', $halaman_aktif) ?>"></i> 
            <span class="font-<?= getTextWeight('settings', $halaman_aktif) ?> text-sm">Settings</span>
        </a>
    </div>
    
    <!-- Logout Section -->
    <div class="p-4 border-t border-slate-100">
        <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3.5 text-red-500 hover:bg-red-50 hover:text-red-600 rounded-2xl transition-all">
            <i class="fa-solid fa-right-from-bracket w-5 text-center"></i> 
            <span class="font-bold text-sm">Keluar Aplikasi</span>
        </a>
    </div>
</aside>

<!-- SIDEBAR OVERLAY (untuk mobile) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 hidden lg:hidden transition-opacity opacity-0"></div>

<!-- JAVASCRIPT untuk Mobile Toggle -->
<script>
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    mobileMenuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
        overlay.classList.toggle('opacity-100');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        overlay.classList.remove('opacity-100');
    });
</script>

<!-- CATATAN IMPLEMENTASI -->
<!--

CARA MENGGUNAKAN TEMPLATE INI:

1. Letakkan kode di atas di dalam body tag dari setiap halaman user

2. Pada bagian awal file (setelah <?php), tambahkan:
   $halaman_aktif = 'dashboard'; // Ubah sesuai halaman
   
   Pilihan halaman_aktif:
   - 'dashboard' untuk dashboard.php
   - 'pengajuan_kredit' untuk pengajuan_kredit.php
   - 'riwayat_transaksi' untuk riwayat_transaksi.php
   - 'pembayaran_cicilan' untuk pembayaran_cicilan.php
   - 'profil_akun' untuk profil_akun.php
   - 'settings' untuk settings.php

3. Pastikan file CSS sudah include:
   - Tailwind CSS
   - Font Awesome

4. Template otomatis akan:
   - Highlight menu yang aktif
   - Menampilkan icon dengan warna emerald untuk menu aktif
   - Memberikan shadow effect pada menu aktif
   - Menambah hover effect pada menu tidak aktif
   - Responsive untuk mobile dan desktop

PENTING: Jangan lupa update variabel $halaman_aktif setiap kali membuat file baru!

-->
