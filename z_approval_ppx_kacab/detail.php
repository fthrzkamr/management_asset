<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_kacab\detail.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');



$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - hanya Kacab
if (!$auth->isKacab()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini!';
    header("Location: dashboard.php");
    exit();
}

// Get no_ppx from URL
$no_ppx = $_GET['no_ppx'] ?? '';

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak valid!';
    header("Location: dashboard.php");
    exit();
}

// Query untuk mendapatkan data PPX Request
$query_ppx = "SELECT pr.*, u.nama_user as nama_pengaju, d.nama_divisi 
              FROM ppx_request pr
              LEFT JOIN user_dfm u ON pr.user_id = u.id_user
              LEFT JOIN divisi d ON pr.divisi = d.id_divisi
              WHERE pr.no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "'";

$result_ppx = mysqli_query($conn, $query_ppx);

if (!$result_ppx || mysqli_num_rows($result_ppx) == 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($result_ppx);

// Validasi bahwa PPX ini adalah untuk cabang user yang login
$user_id = $_SESSION['id_user'];
$user_query = "SELECT u.id_cabang, mc.nama_cabang 
               FROM user_dfm u 
               LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang 
               WHERE u.id_user = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_cabang_id = $user_data['id_cabang'] ?? null;
$user_cabang_nama = $user_data['nama_cabang'] ?? null;

// Validasi menggunakan nama cabang (field 'cabang' di ppx_request)
if ($ppx_data['cabang'] != $user_cabang_nama) {
    $_SESSION['error'] = 'PPX ini bukan untuk cabang Anda!';
    header("Location: dashboard.php");
    exit();
}

// Initialize alert messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Inisialisasi tampilan status
$orig_status = trim($ppx_data['status']);
$display_status = $orig_status;
if ($orig_status === 'Kacab') {
    $display_status = 'Kepala Cabang';
}

// Query untuk mendapatkan detail items
$query_detail = "SELECT * FROM ppx_request_detail 
                 WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "'
                 ORDER BY no_ppx_detail";

$result_detail = mysqli_query($conn, $query_detail);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail PPX - <?= htmlspecialchars($no_ppx) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .info-label { font-weight: 600; color: #495057; }
        .info-value { color: #212529; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <h3 class="page-title">Detail PPX - <?= htmlspecialchars($no_ppx) ?></h3>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h4>Informasi PPX</h4>
                        <span class="badge badge-warning"><?= $display_status ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><span class="info-label">No. PPX:</span><br>
                                <strong><?= htmlspecialchars($ppx_data['no_ppx']) ?></strong></p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="info-label">Tanggal Pengajuan:</span><br>
                                <?= date('d F Y', strtotime($ppx_data['tanggal_pengajuan'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="info-label">PIC:</span><br>
                                <?= htmlspecialchars($ppx_data['nama_pic']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="info-label">Jabatan:</span><br>
                                <?= htmlspecialchars($ppx_data['jabatan']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="info-label">Divisi:</span><br>
                                <?= htmlspecialchars($ppx_data['nama_divisi']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><span class="info-label">Cabang:</span><br>
                                <?= htmlspecialchars($ppx_data['cabang']) ?></p>
                            </div>
                            <div class="col-12">
                                <p><span class="info-label">Alasan Pengajuan:</span><br>
                                <?= nl2br(htmlspecialchars($ppx_data['alasan_pengajuan'])) ?></p>
                            </div>
                        </div>
                    </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Detail Item PPX</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Item</th>
                                        <th>Deskripsi</th>
                                        <th>No. Akun</th>
                                        <th>Jumlah</th>
                                        <th>Harga Satuan</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $grand_total = 0;
                                    while ($detail = mysqli_fetch_assoc($result_detail)):
                                        $total_item = $detail['total_harga'];
                                        $grand_total += $total_item;
                                    ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($detail['rincian'] ?? '-') ?></td>
                                            <td><?= nl2br(htmlspecialchars($detail['deskripsi'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars($detail['no_akun'] ?? '-') ?></td>
                                            <td class="text-center"><?= htmlspecialchars($detail['jumlah']) ?></td>
                                            <td class="text-right">Rp <?= number_format($detail['harga_satuan'], 0, ',', '.') ?></td>
                                            <td class="text-right">Rp <?= number_format($total_item, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="6" class="text-right">GRAND TOTAL:</th>
                                        <th class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($orig_status === 'Kacab'): ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <form method="POST" action="process_approve.php" style="display: inline;" 
                                  onsubmit="return confirm('Apakah Anda yakin ingin menyetujui PPX ini?')">
                                <input type="hidden" name="no_pengajuan" value="<?= htmlspecialchars($no_ppx) ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Setujui
                                </button>
                            </form>

                            <form method="POST" action="process_reject.php" style="display: inline;" 
                                  onsubmit="return confirm('Apakah Anda yakin ingin menolak PPX ini? PPX akan dikembalikan untuk revisi.')">
                                <input type="hidden" name="no_pengajuan" value="<?= htmlspecialchars($no_ppx) ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times-circle"></i> Tolak (Revisi)
                                </button>
                            </form>

                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include __DIR__ . '/../layouts/footer.php'; ?>
    </div>
</body>
</html>
