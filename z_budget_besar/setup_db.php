<?php
require_once __DIR__ . '/../dist/config/config.php';

echo "<h3>Setup Anggaran Bulanan Besar</h3>";

// 1. Tambah kolom budget_type ke ppx_monthly_budget jika belum ada
$col = mysqli_query($conn, "SHOW COLUMNS FROM ppx_monthly_budget LIKE 'budget_type'");
if (mysqli_num_rows($col) == 0) {
    $r = mysqli_query($conn, "ALTER TABLE ppx_monthly_budget 
                              ADD COLUMN budget_type ENUM('biasa','besar') NOT NULL DEFAULT 'biasa' AFTER notes");
    echo $r ? "[OK] Kolom <code>budget_type</code> ditambahkan.<br>" 
            : "[ERROR] " . mysqli_error($conn) . "<br>";
} else {
    echo "[OK] Kolom <code>budget_type</code> sudah ada.<br>";
}

echo "<hr><strong>Setup selesai!</strong> <a href='dashboard.php' style='color:#4f46e5'>&larr; Kembali</a>";
?>
