<?php
require_once __DIR__ . '/../dist/config/config.php';

$sql = "CREATE TABLE IF NOT EXISTS `master_vendor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_vendor` VARCHAR(50) NULL,
    `nama_vendor` VARCHAR(150) NOT NULL,
    `kategori_vendor` VARCHAR(100) NULL,
    `peruntukan_vendor` VARCHAR(150) NULL,
    `area` VARCHAR(100) NULL,
    `pic_ga` VARCHAR(100) NULL,
    `kontak_vendor` VARCHAR(100) NULL,
    `email` VARCHAR(100) NULL,
    `alamat` TEXT NULL,
    `status_hukum` VARCHAR(50) NULL,
    `npwp` VARCHAR(100) NULL,
    `status_pkp` VARCHAR(50) NULL,
    `nama_bank` VARCHAR(100) NULL,
    `no_rekening` VARCHAR(100) NULL,
    `atas_nama_rekening` VARCHAR(150) NULL,
    `metode_pembayaran` VARCHAR(100) NULL,
    `lead_time` VARCHAR(100) NULL,
    `status_vendor` ENUM('Aktif', 'Non-Aktif') DEFAULT 'Aktif',
    `status_mou` VARCHAR(50) NULL,
    `keterangan` TEXT NULL,
    `review_performa` TEXT NULL,
    `created_by` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_kategori` (`kategori_vendor`),
    INDEX `idx_status` (`status_vendor`),
    INDEX `idx_area` (`area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($conn, $sql)) {
    echo "[OK] Tabel master_vendor berhasil dibuat/sudah siap.\n";
} else {
    echo "[ERROR] Gagal membuat tabel: " . mysqli_error($conn) . "\n";
}

