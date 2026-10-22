<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isSuperAdmin()) {
    die('<h2 style="color:red">Akses ditolak. Hanya Super Admin yang boleh menjalankan script ini.</h2>');
}

$results = [];

function runSQL($conn, $label, $sql) {
    global $results;
    $ok = mysqli_query($conn, $sql);
    $results[] = [
        'label' => $label,
        'ok'    => $ok,
        'error' => $ok ? '' : mysqli_error($conn),
    ];
}

// Step 1: Drop FK lama jika ada
runSQL($conn, 'Drop FK lama (fk_real2_header)',
    "ALTER TABLE `ppx_realisasi_detail2` DROP FOREIGN KEY `fk_real2_header`"
);

// Step 2: Drop FK baru jika sudah ada sebelumnya (idempotent)
runSQL($conn, 'Drop FK baru jika sudah ada (fk_real2_header_monthly)',
    "ALTER TABLE `ppx_realisasi_detail2` DROP FOREIGN KEY `fk_real2_header_monthly`"
);

// Step 3: Tambah FK baru ke ppx_realisasi_monthly
runSQL($conn, 'Tambah FK baru → ppx_realisasi_monthly',
    "ALTER TABLE `ppx_realisasi_detail2`
     ADD CONSTRAINT `fk_real2_header_monthly`
     FOREIGN KEY (`id_realisasi`)
     REFERENCES `ppx_realisasi_monthly` (`id_realisasi`)
     ON DELETE CASCADE ON UPDATE CASCADE"
);

// Step 4: Cek apakah ppx_realisasi_monthly ada
$r = mysqli_query($conn, "SHOW TABLES LIKE 'ppx_realisasi_monthly'");
$results[] = [
    'label' => 'Cek tabel ppx_realisasi_monthly',
    'ok'    => mysqli_num_rows($r) > 0,
    'error' => mysqli_num_rows($r) > 0 ? '' : 'Tabel belum dibuat! Jalankan migration_realisasi.sql dulu.',
];

// Cek FK saat ini
$fk_check = mysqli_query($conn, "
    SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ppx_realisasi_detail2'
      AND REFERENCED_TABLE_NAME IS NOT NULL
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Migrasi FK Realisasi</title>
<style>
body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
h2 { color: #333; }
.card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.ok   { color: green; font-weight: bold; }
.fail { color: #c0392b; font-weight: bold; }
.info { color: #555; font-size: 13px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
th { background: #f0f0f0; }
a.btn { display: inline-block; margin-top: 15px; padding: 10px 20px; background: #3498db; color: #fff; border-radius: 5px; text-decoration: none; }
</style>
</head>
<body>
<h2>🔧 Migrasi Foreign Key — ppx_realisasi_detail2</h2>

<div class="card">
    <h4>Hasil Eksekusi</h4>
    <?php foreach ($results as $res): ?>
        <p>
            <?= $res['ok'] ? '<span class="ok">✅</span>' : '<span class="fail">❌</span>' ?>
            <strong><?= htmlspecialchars($res['label']) ?></strong>
            <?php if (!$res['ok'] && $res['error']): ?>
                <br><span class="info">ℹ️ <?= htmlspecialchars($res['error']) ?></span>
            <?php endif; ?>
        </p>
    <?php endforeach; ?>
</div>

<div class="card">
    <h4>Status Foreign Key Saat Ini</h4>
    <table>
        <tr><th>Constraint</th><th>Mengarah ke Tabel</th></tr>
        <?php while ($fk = mysqli_fetch_assoc($fk_check)): ?>
        <tr>
            <td><?= htmlspecialchars($fk['CONSTRAINT_NAME']) ?></td>
            <td>
                <?php if ($fk['REFERENCED_TABLE_NAME'] === 'ppx_realisasi_monthly'): ?>
                    <span class="ok"><?= $fk['REFERENCED_TABLE_NAME'] ?> ✅</span>
                <?php else: ?>
                    <span class="fail"><?= $fk['REFERENCED_TABLE_NAME'] ?> ⚠️ (masih ke tabel lama!)</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<a class="btn" href="../z_realisasi_ppx/dashboard.php">← Kembali ke Realisasi</a>
</body>
</html>
