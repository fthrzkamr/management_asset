<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';
date_default_timezone_set('Asia/Jakarta');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('monitoring_ga', 'update');

$id_task = (int)($_GET['id'] ?? 0);
if (!$id_task) {
    header("Location: dashboard.php");
    exit();
}

$myBagian = null;
$q_me = mysqli_query($conn, "SELECT bagian_ga FROM user_dfm WHERE id_user = '" . mysqli_real_escape_string($conn, $_SESSION['id_user']) . "'");
if ($q_me && $row_me = mysqli_fetch_assoc($q_me)) {
    $myBagian = $row_me['bagian_ga'] ?: null;
}
$isScoped = !$auth->isSuperAdmin() && !empty($myBagian);

$res = mysqli_query($conn, "SELECT * FROM ga_tasks WHERE id_task = " . (int)$id_task);
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = 'Data pekerjaan tidak ditemukan.';
    header("Location: dashboard.php");
    exit();
}
$task = mysqli_fetch_assoc($res);

// PIC hanya boleh edit tugas di bagiannya sendiri
if ($isScoped && $task['bagian'] !== $myBagian) {
    $_SESSION['error'] = 'Anda tidak memiliki izin mengedit pekerjaan bagian lain.';
    header("Location: dashboard.php");
    exit();
}

$STATUS_LIST = ['Belum Dikerjakan', 'Sedang Dikerjakan', 'Sudah Dikerjakan'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Pekerjaan GA</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="page-title"><i class="fas fa-edit text-info"></i> Edit Pekerjaan GA</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Monitoring GA</a></li>
                            <li class="breadcrumb-item active">Edit</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form action="task_update.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_task" value="<?= (int)$task['id_task'] ?>">
                        <div class="row">
                            <div class="col-12 col-md-4 mb-3">
                                <label>Bagian</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($task['bagian']) ?>" readonly>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <label>Tanggal Mulai <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($task['tanggal_mulai']) ?>" required>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <label>Status <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-control" required>
                                    <?php foreach ($STATUS_LIST as $s): ?>
                                        <option value="<?= htmlspecialchars($s) ?>" <?= $task['status'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label>Keterangan Jenis Pekerjaan <span class="text-danger">*</span></label>
                                <textarea name="deskripsi_pekerjaan" class="form-control" rows="3" required><?= htmlspecialchars($task['deskripsi_pekerjaan']) ?></textarea>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <label>Tanggal Selesai <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control" value="<?= htmlspecialchars($task['tanggal_selesai'] ?? '') ?>" min="<?= htmlspecialchars($task['tanggal_mulai']) ?>" required>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <label>Durasi</label>
                                <input type="text" id="durasi_hari" class="form-control" readonly>
                            </div>
                            <div class="col-12 col-md-4 mb-3">
                                <label>Lampiran / Foto Bukti <small class="text-muted">(opsional - kosongkan jika tidak ingin mengganti)</small></label>
                                <input type="file" name="lampiran" class="form-control" accept="image/*">
                                <?php if (!empty($task['lampiran'])): ?>
                                    <small class="text-muted">Saat ini: <a href="../uploads/ga_tasks/<?= htmlspecialchars($task['lampiran']) ?>" target="_blank"><?= htmlspecialchars($task['lampiran']) ?></a></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 mb-3">
                                <label>Catatan Tambahan <small class="text-muted">(opsional)</small></label>
                                <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($task['keterangan'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                    </form>
                </div>
            </div>

        </div>
    <?php include '../layouts/footer.php'; ?>

<script>
// Hitung durasi (hari, inklusif: mulai = selesai dihitung 1 hari)
(function() {
    var mulai = document.getElementById('tanggal_mulai');
    var selesai = document.getElementById('tanggal_selesai');
    var durasi = document.getElementById('durasi_hari');

    function hitungDurasi() {
        if (mulai.value) {
            selesai.min = mulai.value;
        }
        if (mulai.value && selesai.value) {
            var diff = Math.round((new Date(selesai.value) - new Date(mulai.value)) / 86400000) + 1;
            durasi.value = diff > 0 ? diff + ' hari' : 'Tanggal selesai tidak valid';
        } else {
            durasi.value = '';
        }
    }

    mulai.addEventListener('change', hitungDurasi);
    selesai.addEventListener('change', hitungDurasi);
    hitungDurasi();
})();
</script>
</body>
</html>
