🇬🇧 [Read in English](README.en.md)

# MyKredit

MyKredit itu aplikasi web buat simulasi dan pengajuan kredit kendaraan (motor sama mobil). Jadi nasabah bisa lihat katalog kendaraan, ajukan kredit, bayar cicilan tiap bulan dengan upload bukti transfer, terus admin di belakang yang verifikasi semuanya — mulai dari approve pengajuan, cek bukti bayar, sampai lihat laporan keuangan.

Dibangun pakai PHP native (bukan framework, murni `mysqli`) dan MySQL. Tampilannya Tailwind CSS lewat CDN, font dari Google Fonts (Fraunces buat heading, Public Sans buat body), ikon pakai Lucide. Gak ada proses build apa-apa, jadi tinggal taruh di folder `htdocs` XAMPP dan langsung jalan.

Project ini awalnya ada banyak bug peninggalan (nama kolom salah, query yang gak match struktur tabel, dll), dan desainnya juga sempat generik banget — khas template AI gitu, emerald-slate standar dengan glassmorphism dan shadow gede-gede di semua elemen. Jadi sebagian besar riwayat kerjaan di project ini ya dua itu: benerin bug satu-satu, sama redesign total biar kelihatan lebih punya identitas. Detailnya di bawah.

## Struktur folder

- `main/` — semua halaman yang diakses nasabah (role `user`). Ada `dashboard.php`, `pengajuan_kredit.php`, `riwayat_transaksi.php`, `pembayaran_cicilan.php`, `profil_akun.php`, sama `settings.php`.
- `admin/` — halaman buat admin. `dashboard.php`, `kendaraan.php` (kelola katalog), `transaksi.php` (approve/tolak pengajuan), `verifikasi_pembayaran.php`, `laporan.php`, dan `pengguna.php`.
- `auth/` — `login.php`, `register.php`, `logout.php`. Dipakai bareng sama user dan admin.
- `assets/css/theme.css` — design system utama, dipakai semua halaman `main/`. Palet cream + burnt copper, serif buat angka-angka penting.
- `assets/css/admin-theme.css` — di-load setelah `theme.css`, cuma nge-override variabel warnanya jadi graphite + steel blue. Jadi halaman admin kelihatan beda "dunia" dari halaman user tanpa harus nulis CSS dari nol, komponennya (`.surface`, `.badge-*`, `.btn-*`, dst) sama persis.
- `uploads/` — tempat file yang diupload user (foto profil, cover, bukti transfer, foto kendaraan). Ini di-gitignore, gak masuk repo.
- `migrations/` — file SQL buat perubahan struktur tabel yang dilakukan belakangan, setelah `kreditku_db.sql` awal dibuat.
- `kreditku_db.sql` — dump struktur database plus sedikit data awal (2 akun: satu admin, satu user).
- `conn.php` — koneksi database. Ini juga di-gitignore karena isinya kredensial; yang masuk repo cuma `conn.example.php` sebagai contoh.

## Fitur

### Sisi user
Nasabah bisa daftar dan login, lihat dashboard yang nampilin ringkasan (berapa pengajuan yang lagi jalan, progress pelunasan, notifikasi aktivitas terakhir), terus lihat katalog kendaraan dan ajukan kredit — ada simulasi cicilan otomatis (DP, tenor, bunga, sampai nominal cicilan per bulan dihitung langsung pas milih kendaraan). Setelah diajukan bisa dipantau di riwayat transaksi, lengkap sama status dan catatan dari admin kalau ditolak.

Begitu disetujui, sistem otomatis generate tagihan cicilan sesuai tenornya. Nasabah bayar lewat halaman pembayaran cicilan, upload bukti transfer, nanti nunggu admin verifikasi. Ada juga halaman profil (bisa ganti foto profil dan foto cover) sama settings buat atur notifikasi dan ganti password.

### Sisi admin
Admin punya dashboard sendiri dengan statistik keseluruhan sistem. Dari situ bisa kelola data kendaraan (tambah/edit/hapus, upload foto), proses pengajuan kredit yang masuk (approve otomatis bikin cicilan dan kurangin stok, atau tolak dengan catatan), sampai verifikasi bukti transfer yang dikirim nasabah.

Ada juga halaman laporan yang ngitung nilai kredit yang masih berjalan, total cicilan yang udah lunas (bisa difilter per periode), dan tunggakan — plus breakdown per nasabah biar kelihatan siapa yang rajin bayar dan siapa yang nunggak. Terakhir ada halaman pengguna terdaftar, sifatnya read-only aja buat sekarang, nampilin daftar nasabah dan riwayat transaksi masing-masing kalau diklik.

## Riwayat bug (dan gimana nemu + benerinnya)

Ini bagian yang paling panjang ceritanya karena emang paling banyak kejadian. Kebanyakan bug di project ini satu jenis: query SQL yang nyebut nama kolom yang sebenernya gak ada di tabel, atau PK yang disangka `id_transaksi` padahal aslinya cuma `id`. Efeknya biasanya bukan error yang kelihatan, tapi data yang diem-diem selalu nol atau kosong — jadi harus dicek satu-satu ke `kreditku_db.sql` buat tahu nama kolom yang bener.

Yang paling awal ketemu itu di `profil_akun.php`: form edit profil pakai nama field `no_telp`, padahal kolom di tabel `pengguna` namanya `no_hp`. Jadi setiap kali user update nomor HP, query-nya gagal diam-diam dan datanya gak pernah kesimpen. Sekalian pas itu juga ketahuan upload foto profil dan cover sama sekali gak pernah nulis path filenya ke kolom `foto`/`cover` di database — file-nya kesimpen di folder `uploads/`, tapi database-nya gak tahu, jadi setelah refresh fotonya balik ke avatar default lagi.

Notifikasi juga lumayan berantakan. Tabel `notifikasi` kolom tanggalnya itu `created_at` (otomatis keisi), tapi ada beberapa tempat yang query-nya masih nyebut kolom `tanggal` yang emang gak pernah ada di skema. Terus pas admin approve atau tolak transaksi, ada kode yang insert ke tabel notifikasi tapi lupa isi kolom `judul` — padahal kolom itu `NOT NULL`, jadi insert-nya gagal dan notifikasi buat user gak pernah terkirim.

Dashboard utama (`main/dashboard.php`) ada dua masalah mirip: query statistiknya manggil kolom `status` di tabel `transaksi`, padahal nama kolomnya `status_pengajuan`. Terus buat hitung cicilan lunas, join-nya pakai `t.id_transaksi` buat nyocokin ke tabel `transaksi`, padahal primary key tabel itu cuma `id`. Dua-duanya nyebabin angka statistik selalu nol walau datanya sebenernya ada.

Hal serupa, versi lebih parah, ketemu lagi pas lagi bangun fitur admin baru — `admin/dashboard.php` ternyata punya empat bug sejenis sekaligus yang gak ketahuan sebelumnya karena gak pernah ada yang ngetes halaman itu dengan data asli. Statistik "Rasio Pengajuan Kredit" salah kolom (`status` vs `status_pengajuan`), daftar "Aktivitas Transaksi Terbaru" di-sort pakai `id_transaksi` yang gak ada (harusnya `id`), log notifikasi manggil `tanggal` yang juga gak ada (harusnya `created_at`), dan yang terakhir agak beda: statistik "Verifikasi Cicilan Pembayaran" ngecek kolom `status_pembayaran` (yang isinya cuma `belum_bayar`/`lunas`) buat nyari status kayak "menunggu" atau "ditolak" — padahal status verifikasi kayak gitu ada di kolom lain, `status_verifikasi`. Jadi walaupun ada data pembayaran yang nunggu diverifikasi, angkanya tetep kebaca nol karena ngeceknya di kolom yang salah.

Selain soal kolom, ada beberapa bug "fitur gak komplit": link "Lihat Semua" di dashboard ngarah ke `pengajuan.php` yang emang gak ada (harusnya `pengajuan_kredit.php`), link "Lupa password" masih `href="#"` alias belum ada fiturnya sama sekali, dan di `settings.php` toggle notifikasi email/SMS query-nya nyoba update kolom `notif_email`/`notif_sms` yang awalnya beneran gak ada di tabel `pengguna` — ini yang butuh migration SQL baru (`ALTER TABLE ... ADD COLUMN`) buat nambah kolomnya dulu sebelum fiturnya bisa jalan.

Satu kebiasaan yang dipegang tiap benerin bug jenis ini: seed data dummy dulu, login beneran lewat form, cek angkanya cocok sama hitungan manual, baru setelah itu data dummy-nya dihapus lagi. Soalnya kalau cuma dicek dari tampilan doang gampang ketipu — halaman bisa kelihatan "jalan" padahal query-nya diam-diam gagal dan fallback ke nilai default.

## Soal desain

Desain awalnya generik: emerald-slate, Inter, glassmorphism, shadow gede di mana-mana — pola yang gampang ketebak dari template AI manapun. Sekarang dipakai satu design system yang konsisten di semua halaman `main/`: background cream, sidebar gelap dengan aksen warna burnt copper (satu warna aksen aja, dipakai terbatas buat hal yang emang penting — CTA, status positif, angka utama), font serif Fraunces buat heading sama angka besar, border tipis (hairline) ketimbang shadow tebal. Halaman admin pakai sistem yang sama tapi paletnya dioverride jadi graphite + steel blue lewat `admin-theme.css`, biar kelihatan beda "rasa" dari sisi user tanpa nulis ulang komponennya.

Ada juga sentuhan depth yang sengaja dibuat minim dan dipakai sangat terbatas — cuma di halaman admin, cuma buat satu-dua elemen per halaman (misalnya satu kartu statistik yang paling penting dikasih shadow arah bawah yang halus, atau tombol aksi utama yang kerasa "bisa dipencet" pas hover/klik). Sengaja gak dipasang di semua elemen biar gak jadi neumorphism atau kesan 3D yang berlebihan.

## Cara setup

1. Clone atau taruh folder project ini di `htdocs` XAMPP.
2. Copy `conn.example.php` jadi `conn.php`, terus isi host/user/password database sesuai punya kamu (default-nya cocok buat XAMPP lokal: host `localhost`, user `root`, password kosong).
3. Buat database baru namanya `kreditku_db`, import `kreditku_db.sql` ke situ (lewat phpMyAdmin atau `mysql -u root kreditku_db < kreditku_db.sql`).
4. Jalankan juga file-file di folder `migrations/` satu per satu kalau ada — ini nambahin kolom yang gak ikut di dump awal (misalnya `notif_email` dan `notif_sms` di tabel `pengguna`).
5. Nyalain Apache dan MySQL dari XAMPP, buka `http://localhost/MYkredit/auth/login.php`.
6. Data awal cuma ada satu akun user dan satu akun admin, tapi passwordnya udah di-hash jadi gak ada yang tahu plaintext-nya. Paling gampang: daftar akun baru lewat halaman register (otomatis jadi role `user`), atau kalau butuh akses admin, daftar dulu terus ubah manual kolom `role` jadi `admin` lewat phpMyAdmin.

## Yang belum ada / catatan buat lanjutannya

- Fitur lupa password belum ada sama sekali — link-nya di halaman login sengaja dimatikan (ada tooltip "fitur belum tersedia") daripada ngarah ke halaman kosong.
- Laporan admin (`laporan.php`) baru sebatas tampilan web, belum bisa di-export ke PDF atau Excel. Kalau mau nambahin, angkanya udah kehitung dengan bener tinggal bikin lapisan export-nya.
- Di `settings.php` ada variabel `$notif_whatsapp` yang dihitung dari form tapi sebenernya gak pernah dipakai — gak ada checkbox WhatsApp di tampilannya, dan kolomnya juga gak ada di database. Kalau suatu saat mau diaktifkan beneran, perlu nambah kolom `notif_whatsapp` lewat migration baru dan tambahin UI-nya.
- `pengguna.php` di admin sengaja read-only, belum ada fitur buat nonaktifkan atau hapus akun nasabah.
- Belum ada verifikasi email pas register, dan belum ada automated test sama sekali — semua pengecekan selama ini manual lewat seed data + login beneran.
- Beberapa query lama masih nyambung string langsung ke SQL (bukan prepared statement), biasanya yang nilainya dari hasil perhitungan internal bukan input user langsung, tapi tetep worth dibenerin pelan-pelan kalau ada waktu buat konsistensi keamanan.
