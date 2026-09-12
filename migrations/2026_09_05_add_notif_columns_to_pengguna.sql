-- Migration: add notification preference columns to `pengguna`
-- Needed by main/settings.php (notif_email / notif_sms toggle)

ALTER TABLE `pengguna`
  ADD COLUMN `notif_email` TINYINT(1) DEFAULT 1,
  ADD COLUMN `notif_sms` TINYINT(1) DEFAULT 0;
