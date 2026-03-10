<?php
require_once __DIR__ . '/../dist/config/config.php';

echo "<h3>Setup Database - Budget Routing</h3>";

// Migration for status column in ppx_monthly_budget
mysqli_query($conn, "ALTER TABLE ppx_monthly_budget MODIFY status VARCHAR(50) DEFAULT 'Draft'");
echo "[OK] Kolom <code>status</code> di ppx_monthly_budget diubah menjadi VARCHAR(50).<br>";

// ===================================================================
// 1. Tabel ppx_budget_routing (Global Steps)
// ===================================================================
$sql = "CREATE TABLE IF NOT EXISTS ppx_budget_routing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    step_name VARCHAR(50) NOT NULL,
    approver_role VARCHAR(50) NOT NULL,
    approver_id INT NULL,
    step_order INT NOT NULL,
    is_optional TINYINT(1) DEFAULT 0,
    is_final TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    ho_only TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (mysqli_query($conn, $sql)) {
    echo "[OK] Tabel <code>ppx_budget_routing</code> sudah siap.<br>";
} else {
    echo "[ERROR] " . mysqli_error($conn) . "<br>";
}

// Migrasi kolom ho_only jika belum ada
$col_ho = mysqli_query($conn, "SHOW COLUMNS FROM ppx_budget_routing LIKE 'ho_only'");
if (mysqli_num_rows($col_ho) == 0) {
    mysqli_query($conn, "ALTER TABLE ppx_budget_routing ADD COLUMN ho_only TINYINT(1) DEFAULT 0 AFTER is_active");
    echo "[OK] Kolom <code>ho_only</code> ditambahkan.<br>";
} else {
    echo "[OK] Kolom <code>ho_only</code> sudah ada.<br>";
}

// Seed global routing jika kosong
$check = mysqli_query($conn, "SELECT id FROM ppx_budget_routing LIMIT 1");
if (mysqli_num_rows($check) == 0) {
    $seed = "INSERT INTO ppx_budget_routing (step_name, approver_role, step_order, is_optional, is_final, ho_only) VALUES
        ('Kacab',               'kacab',        1, 1, 0, 0),
        ('Asmen Finance',       'asmen_finance', 2, 1, 0, 0),
        ('Manager Finance',     'manag_finance', 3, 0, 0, 0),
        ('Finance (Realisasi)', 'finance',       4, 0, 1, 0)";
    if (mysqli_query($conn, $seed)) {
        echo "[OK] Data routing global di-seed (4 steps).<br>";
    } else {
        echo "[ERROR] Seed gagal: " . mysqli_error($conn) . "<br>";
    }
} else {
    // Tambah Asmen Finance jika belum ada
    $asmen = mysqli_query($conn, "SELECT id FROM ppx_budget_routing WHERE step_name = 'Asmen Finance' LIMIT 1");
    if (mysqli_num_rows($asmen) == 0) {
        mysqli_query($conn, "UPDATE ppx_budget_routing SET step_order = step_order + 1 WHERE step_order >= 2");
        mysqli_query($conn, "INSERT INTO ppx_budget_routing (step_name, approver_role, step_order, is_optional, is_final, ho_only) VALUES ('Asmen Finance', 'asmen_finance', 2, 1, 0, 0)");
        echo "[OK] Step <code>Asmen Finance</code> ditambahkan.<br>";
    } else {
        echo "[OK] Step <code>Asmen Finance</code> sudah ada.<br>";
    }

    // Tambah Manager Operasional jika belum ada (sebelum Finance/Realisasi)
    $manajer_ops = mysqli_query($conn, "SELECT id FROM ppx_budget_routing WHERE step_name = 'Manager Operasional' LIMIT 1");
    if (mysqli_num_rows($manajer_ops) == 0) {
        // Cari step_order Finance (Realisasi) saat ini
        $finance_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT step_order FROM ppx_budget_routing WHERE approver_role = 'finance' AND is_final = 1 LIMIT 1"));
        $finance_order = $finance_row ? (int)$finance_row['step_order'] : 4;
        // Geser semua step >= finance_order ke atas
        mysqli_query($conn, "UPDATE ppx_budget_routing SET step_order = step_order + 1 WHERE step_order >= $finance_order");
        // Insert Manager Operasional di posisi tepat sebelum Finance
        mysqli_query($conn, "INSERT INTO ppx_budget_routing (step_name, approver_role, step_order, is_optional, is_final, ho_only)
                             VALUES ('Manager Operasional', 'manag_operasional', $finance_order, 0, 0, 0)");
        echo "[OK] Step <code>Manager Operasional</code> ditambahkan di urutan $finance_order.<br>";
    } else {
        echo "[OK] Step <code>Manager Operasional</code> sudah ada.<br>";
    }
}

// ===================================================================
// 2. Tabel ppx_budget_routing_cabang (Per-Branch Overrides)
// ===================================================================
$sql2 = "CREATE TABLE IF NOT EXISTS ppx_budget_routing_cabang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_cabang VARCHAR(10) NOT NULL   COMMENT 'ID cabang yang dikonfigurasi',
    id_routing_step INT NOT NULL      COMMENT 'ID step dari ppx_budget_routing',
    approver_id INT DEFAULT NULL      COMMENT 'User spesifik (bisa cross-branch)',
    skip_step TINYINT(1) DEFAULT 0   COMMENT '1 = step dilewati untuk cabang ini',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cabang_step (id_cabang, id_routing_step)
)";
if (mysqli_query($conn, $sql2)) {
    echo "[OK] Tabel <code>ppx_budget_routing_cabang</code> sudah siap.<br>";
} else {
    echo "[ERROR] " . mysqli_error($conn) . "<br>";
}

// Fix column type if it's already created as INT
$col_type = mysqli_query($conn, "SHOW COLUMNS FROM ppx_budget_routing_cabang LIKE 'id_cabang'");
if ($row = mysqli_fetch_assoc($col_type)) {
    if (stripos($row['Type'], 'int') !== false) {
        mysqli_query($conn, "ALTER TABLE ppx_budget_routing_cabang MODIFY id_cabang VARCHAR(10) NOT NULL");
        echo "[OK] Kolom <code>id_cabang</code> diubah dari INT ke VARCHAR(10).<br>";
    }
}

echo "<hr><strong>Setup selesai!</strong> <a href='dashboard.php' style='color:#4f46e5'>&larr; Kembali ke Dashboard Routing</a>";
?>
