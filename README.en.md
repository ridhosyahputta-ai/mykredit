🇮🇩 [Baca dalam Bahasa Indonesia](README.md)

# MyKredit

MyKredit is a web app for vehicle financing (motorcycles and cars). Customers browse a catalog, apply for credit with an instant repayment simulation, then pay off monthly installments by uploading transfer proof. On the other side, admins approve applications, verify those payment proofs, and keep an eye on the numbers through a small reporting dashboard.

It's plain PHP with `mysqli`, no framework, no build step. Tailwind comes in through the CDN, fonts are Google Fonts (Fraunces for headings, Public Sans for body text), icons are from Lucide. Drop the folder into XAMPP's `htdocs`, point it at a MySQL database, and it runs.

Honestly, most of the work that went into this repo falls into two buckets: chasing down bugs that had been sitting there silently for a while, and redesigning the whole thing because the original UI looked like it came out of a template generator. Both stories are below.

## What's in the folders

- `main/` — everything a logged-in customer sees: `dashboard.php`, `pengajuan_kredit.php` (credit application + catalog), `riwayat_transaksi.php`, `pembayaran_cicilan.php`, `profil_akun.php`, `settings.php`.
- `admin/` — the admin side: `dashboard.php`, `kendaraan.php` (vehicle CRUD), `transaksi.php` (approve/reject applications), `verifikasi_pembayaran.php`, `laporan.php`, `pengguna.php`.
- `auth/` — `login.php`, `register.php`, `logout.php`, shared by both roles.
- `assets/css/theme.css` — the main design system, used across every page in `main/`. Cream background, burnt-copper accent, serif numbers.
- `assets/css/admin-theme.css` — loads right after `theme.css` and just swaps the color variables to a graphite/steel-blue palette. Same components (`.surface`, `.badge-*`, `.btn-*`, and so on), different mood — no CSS duplicated.
- `uploads/` — user-uploaded files: profile photos, cover images, payment proofs, vehicle photos. Gitignored, never committed.
- `migrations/` — SQL files for schema changes made after the initial `kreditku_db.sql` dump.
- `kreditku_db.sql` — the database schema plus a couple of seed rows (one admin account, one user account).
- `conn.php` — the DB connection file. Gitignored too, since it holds credentials. What's actually in the repo is `conn.example.php`.

## Features

On the customer side: register, log in, land on a dashboard that shows how many applications are active, how far along repayment is, and recent notifications. Browse the vehicle catalog and apply for credit right there — the down payment, tenor, and interest all feed into a live monthly installment calculation before you even submit. Every application shows up in transaction history afterward, with the admin's notes attached if it got rejected.

Once approved, the system generates the full installment schedule automatically based on the tenor. Paying is just uploading transfer proof on the installments page and waiting for an admin to confirm it. There's also a profile page (photo and cover image uploads) and a settings page for notification preferences and password changes.

The admin side starts with a dashboard summarizing the whole system. From there you manage the vehicle catalog (add/edit/delete, photo upload with real MIME-type checking, not just trusting the file extension), process incoming credit applications — approving one automatically builds the installment schedule and decrements stock — and verify the payment proofs customers send in.

There's a reports page too, showing outstanding credit exposure, total installments paid off (filterable by period), and how much is overdue, broken down per customer so it's obvious who's paying on time and who isn't. Last one is a read-only customer directory — just a list with each person's transaction history a click away, no edit or delete yet.

## Bugs I ran into (and how they got found)

This section ended up being the longest one, because honestly there were a lot of these. Almost all of them are the same shape: a query referencing a column name that doesn't actually exist in the table, or assuming the primary key is called `id_transaksi` when it's just `id`. None of these throw visible errors — they just quietly return nothing, so every stat on the page sits at zero and you'd never know unless you went and checked the actual schema in `kreditku_db.sql`.

First one I hit was in `profil_akun.php`: the edit-profile form posted a field called `no_telp`, but the actual column in `pengguna` is `no_hp`. So every time someone updated their phone number, the query silently failed and nothing got saved. While I was in there, I also noticed profile photo and cover uploads never wrote the file path back to the `foto`/`cover` columns — the file would land in `uploads/` just fine, but the database had no idea, so the avatar reverted to the default on the next page load.

Notifications were a mess in a different way. The `notifikasi` table's timestamp column is `created_at` (auto-filled), but a few places in the code were still querying a `tanggal` column that never existed in the schema to begin with. And separately, when admin approved or rejected a transaction, the code that inserted a notification forgot to fill in `judul` — which is a `NOT NULL` column — so the insert failed outright and the customer never got notified either way.

The main dashboard had two bugs from the same family: the stats query checked a `status` column on `transaksi` that doesn't exist (it's `status_pengajuan`), and the installments join used `t.id_transaksi` to match against `transaksi` when the actual primary key is just `id`. Both bugs, same symptom — every number on the page read zero.

A nastier version of the same thing showed up again later, while building out the new admin features — turned out `admin/dashboard.php` had four bugs like this stacked on top of each other, and nobody had caught them because nobody had ever tested that page with real data. "Rasio Pengajuan Kredit" used the wrong column (`status` instead of `status_pengajuan`), the recent-activity list sorted by a nonexistent `id_transaksi`, the notification log queried `tanggal` again instead of `created_at`, and the fourth one was slightly different: the "payment verification" stats were checking `status_pembayaran` (which only ever holds `belum_bayar` or `lunas`) looking for values like "pending" or "rejected" — but that's a completely different column, `status_verifikasi`. So even with payments genuinely sitting in a pending-review state, the count stayed at zero because it was looking in the wrong place.

A few other loose ends worth mentioning even though they're not the same bug pattern: a "See All" link on the dashboard pointed to `pengajuan.php`, a file that doesn't exist (should've been `pengajuan_kredit.php`), the "Forgot password" link on the login page was still a bare `href="#"`, and the email/SMS notification toggle in `settings.php` was trying to update `notif_email`/`notif_sms` columns that genuinely didn't exist on the `pengguna` table yet — that one needed an actual migration (`ALTER TABLE ... ADD COLUMN`) before the feature could work at all.

One thing I stuck to for every one of these: seed some dummy data, log in through the actual form, check the number against a hand-computed expectation, then delete the dummy data. Eyeballing the page isn't enough — a page can look like it's working fine while the query behind it is failing quietly and just falling back to a default value.

## About the redesign

The original UI was about as generic as it gets — emerald and slate, Inter font, glassmorphism everywhere, heavy shadows on every single element. The kind of look you'd get from any AI design generator on autopilot. It's now one consistent system across every `main/` page: cream background, a dark sidebar with a single burnt-copper accent used sparingly (CTAs, positive status, the numbers that actually matter), Fraunces serif for headings and big figures, hairline borders instead of drop shadows. Admin pages run the exact same components but with the accent variables swapped to graphite and steel blue via `admin-theme.css` — different feel, zero duplicated CSS.

There's also a small amount of depth styling, used very deliberately and only on the admin side — one priority stat card per page gets a soft downward shadow, primary action buttons get a slight press effect on hover/click. It's intentionally restrained; the goal was a hint of physicality, not neumorphism or a 3D-icon-pack look.

## Running it locally

1. Drop the project folder into XAMPP's `htdocs`.
2. Copy `conn.example.php` to `conn.php` and fill in your own DB credentials (defaults already match a standard local XAMPP setup: host `localhost`, user `root`, empty password).
3. Create a database called `kreditku_db` and import `kreditku_db.sql` into it, either through phpMyAdmin or `mysql -u root kreditku_db < kreditku_db.sql`.
4. Run whatever's in `migrations/` too — those add columns that weren't part of the original dump (like `notif_email`/`notif_sms` on `pengguna`).
5. Start Apache and MySQL from the XAMPP control panel, then open `http://localhost/MYkredit/auth/login.php`.
6. The seed data has one user account and one admin account, but both passwords are hashed and nobody actually knows the plaintext. Easiest path: register a fresh account through the signup page (you'll get the `user` role by default), and if you need admin access, register normally and then flip that row's `role` column to `admin` directly in phpMyAdmin.

## What's missing / ideas for later

Password reset doesn't exist yet — the link on the login page is disabled on purpose (with a "not available yet" tooltip) rather than pointing somewhere broken. The admin reports page calculates everything correctly but has no export to PDF or Excel; the numbers are already right, it just needs an export layer on top. There's a leftover `$notif_whatsapp` variable in `settings.php` that gets read from the form but is never actually used anywhere — no checkbox for it in the UI, no column for it in the database either, so if that's ever built for real it needs both. The customer directory page is intentionally read-only right now, no way to disable or delete an account from there. There's no email verification on signup, and no automated tests at all — every check so far has been manual, seeding data and logging in for real. A handful of older queries still interpolate strings straight into SQL instead of using prepared statements; most of those values come from internal calculations rather than raw user input, but it'd still be worth tightening up for consistency's sake.
