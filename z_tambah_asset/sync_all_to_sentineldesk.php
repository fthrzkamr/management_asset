<?php
/**
 * One-time (or occasional) bulk push of every existing asset_dfm row to
 * SentinelDesk — for records that existed before the per-save sync in
 * aset_insert.php/aset_update.php was added, which only fires on a NEW
 * save going forward and never touches rows nobody re-edits. Safe to
 * re-run any time: SentinelDesk upserts by asset_code (see
 * asset_matching.sync_asset), so this never creates duplicates.
 */
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
require_once('../dist/config/SentinelDeskSync.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('management_asset', 'create');

$result = mysqli_query($conn, "SELECT kode_aset, kondisi, status_barang, user, lokasi, nama_aset, merk, spesifikasi, lokasi_detail FROM asset_dfm");

$total = 0;
$ok = 0;
$failed = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $total++;
        $success = sentineldesk_sync_asset(
            $row['kode_aset'],
            $row['kondisi'],
            $row['status_barang'],
            $row['user'],
            $row['lokasi'],
            $row['nama_aset'],
            $row['merk'],
            $row['spesifikasi'],
            $row['lokasi_detail']
        );
        if ($success) {
            $ok++;
        } else {
            $failed[] = $row['kode_aset'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sync ke SentinelDesk</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .ok { color: #16a34a; }
        .fail { color: #dc2626; }
        ul { background: #f8fafc; border-radius: 8px; padding: 12px 24px; }
        a.btn { display: inline-block; margin-top: 16px; padding: 8px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; }
    </style>
</head>
<body>
    <h2>Sync Semua Asset ke SentinelDesk</h2>
    <p>Total diproses: <strong><?= $total ?></strong></p>
    <p class="ok">Berhasil: <strong><?= $ok ?></strong></p>
    <?php if (!empty($failed)): ?>
        <p class="fail">Gagal (<?= count($failed) ?>):</p>
        <ul>
            <?php foreach ($failed as $kode): ?>
                <li><?= htmlspecialchars($kode) ?></li>
            <?php endforeach; ?>
        </ul>
        <p>Cek SentinelDesk dan backend-nya sudah jalan, lalu klik tombol di bawah untuk coba lagi (aman diulang, tidak akan dobel).</p>
    <?php else: ?>
        <p>Semua asset berhasil disinkronkan. Cek halaman <strong>Assets</strong> di SentinelDesk.</p>
    <?php endif; ?>
    <a class="btn" href="javascript:location.reload()">Jalankan Lagi</a>
    <a class="btn" href="dashboard.php" style="background:#64748b;">Kembali ke Dashboard</a>
</body>
</html>
