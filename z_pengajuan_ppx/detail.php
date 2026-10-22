<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
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

// Inisialisasi tampilan status: jika 'Leader' -> 'Kacap/Manager', jika 'Manager' -> 'Manager Finance'
$orig_status = trim($ppx_data['status']);
$display_status = $orig_status;
if ($orig_status === 'Leader') {
    $display_status = 'Kacap/Manager';
} elseif ($orig_status === 'Manager') {
    $display_status = 'Manager Finance';
}

// Query untuk mendapatkan detail items
$query_detail = "SELECT * FROM ppx_request_detail 
                 WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "'
                 ORDER BY no_ppx_detail";

$result_detail = mysqli_query($conn, $query_detail);

// Query untuk mendapatkan semua realisasi
$query_realisasi = "SELECT * FROM ppx_realisasi 
                    WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "'
                    ORDER BY created_at ASC";

$result_realisasi = mysqli_query($conn, $query_realisasi);

// Simpan semua realisasi dalam array
$all_realisasi = [];
$total_realisasi_all = 0;
$ada_reimbursement = false;
$total_reimbursement = 0;
$reimbursement_processed = true;

if ($result_realisasi) {
    while ($row_real = mysqli_fetch_assoc($result_realisasi)) {
        $all_realisasi[] = $row_real;
        $total_realisasi_all += floatval($row_real['total_realisasi']);

        // Cek apakah ada yang perlu reimbursement
        if ($row_real['status_selisih'] === 'kurang' && $row_real['confirm_pakai_uang_pribadi'] == 1) {
            $ada_reimbursement = true;
            $total_reimbursement += abs(floatval($row_real['uang_kembali']));

            // Check if this reimbursement has been processed
            if (!isset($row_real['is_reimbursed']) || $row_real['is_reimbursed'] == 0) {
                $reimbursement_processed = false;
            }
        }
    }
}

// Tentukan URL kembali secara dinamis
$back_url = "dashboard.php";
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'z_approval_ppx_asmen') !== false) {
    $back_url = "../z_approval_ppx_asmen/dashboard.php";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail PPX - <?= htmlspecialchars($no_ppx) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    } else {
        error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/header_css.php' : __DIR__ . '/../layouts/header_css.php'));
        // Fallback minimal CSS to avoid layout break (sesuaikan path jika perlu)
        echo '<link rel="stylesheet" href="/assets/css/bootstrap.min.css">';
    }
    ?>

    <style>
        .detail-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .detail-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 8px 12px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="page-title">Detail Pengajuan PPX</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="<?= $back_url ?>">Pengajuan PPX</a></li>
                                <li class="breadcrumb-item active">Detail PPX</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="<?= $back_url ?>" class="btn btn-secondary text-white">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- PPX Info Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt"></i>
                                    Informasi PPX: <?= htmlspecialchars($ppx_data['no_ppx']) ?>
                                </h5>
                                <div>
                                    <?php if (strtolower(trim($ppx_data['status'])) === 'revisi' && $auth->hasPermission('pengajuan', 'create')): ?>
                                        <a href="edit.php?no_ppx=<?= urlencode($ppx_data['no_ppx']) ?>" class="btn btn-danger btn-sm mr-1" title="Edit">
                                            <i class="fas fa-edit"></i> Revisi
                                        </a>
                                        <a href="delete.php?no_ppx=<?= urlencode($ppx_data['no_ppx']) ?>" class="btn btn-danger btn-sm btn-delete-confirm" title="Hapus"
                                            data-message="Yakin ingin menghapus PPX <?= htmlspecialchars($ppx_data['no_ppx']) ?> ?">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (strtolower(trim($ppx_data['status'])) === 'ditolak'): ?>
                            <div class="alert alert-danger mx-3 mt-3">
                                <h5><i class="fas fa-times-circle"></i> Pengajuan Ditolak</h5>
                                <p class="mb-0"><strong>Silakan ajukan kembali dengan perbaikan yang diperlukan.</strong></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="info-label" width="40%">Nama PIC:</td>
                                                <td><?= htmlspecialchars($ppx_data['nama_pic']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Jabatan:</td>
                                                <td><?= htmlspecialchars($ppx_data['jabatan']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Divisi:</td>
                                                <td><?= htmlspecialchars($ppx_data['divisi']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Cabang:</td>
                                                <td><?= htmlspecialchars($ppx_data['cabang']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Tanggal Pengajuan:</td>
                                                <td><?= date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Tanggal Dibutuhkan:</td>
                                                <td><?= date('d/m/Y', strtotime($ppx_data['tanggal_dibutuhkan'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="info-label" width="40%">Tingkat Kepentingan:</td>
                                                <td>
                                                    <?php
                                                    $priority_class = '';
                                                    switch ($ppx_data['tingkat_kepentingan']) {
                                                        case 'Rendah':
                                                            $priority_class = 'success';
                                                            break;
                                                        case 'Sedang':
                                                            $priority_class = 'info';
                                                            break;
                                                        case 'Tinggi':
                                                            $priority_class = 'warning';
                                                            break;
                                                        case 'Urgent':
                                                            $priority_class = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?= $priority_class ?>">
                                                        <?= htmlspecialchars($ppx_data['tingkat_kepentingan']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Status:</td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch ($orig_status) {
                                                        case 'Leader':
                                                            $status_class = 'primary';
                                                            break;
                                                        case 'Manager':
                                                            $status_class = 'info';
                                                            break;
                                                        case 'Finance':
                                                            $status_class = 'warning';
                                                            break;
                                                        case 'Direktur':
                                                            $status_class = 'secondary';
                                                            break;
                                                        case 'Uang Muka':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'Selesai':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'Revisi':
                                                            $status_class = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?= $status_class ?> status-badge">
                                                        <?= htmlspecialchars($display_status) ?>
                                                    </span>
                                                </td>
                                            </tr>

                                            <!-- Tampilkan Uang Muka dan Nominal Realisasi jika status = Uang Muka atau Selesai -->
                                            <tr>
                                                <td class="info-label">Uang Muka:</td>
                                                <td>
                                                    <strong>Rp <?= number_format(isset($ppx_data['uang_muka']) ? (int)$ppx_data['uang_muka'] : 0, 0, ',', '.') ?></strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Nominal Realisasi:</td>
                                                <td>
                                                    <strong>Rp <?= number_format(isset($ppx_data['nominal_realisasi']) ? (int)$ppx_data['nominal_realisasi'] : 0, 0, ',', '.') ?></strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Total Nominal:</td>
                                                <td><strong>Rp <?= number_format(isset($ppx_data['nominal_ppx']) ? (int)$ppx_data['nominal_ppx'] : 0, 0, ',', '.') ?></strong></td>
                                            </tr>

                                        </table>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="info-label">Alasan Pengajuan:</h6>
                                        <p class="text-justify"><?= nl2br(htmlspecialchars($ppx_data['alasan_pengajuan'])) ?></p>
                                    </div>
                                </div>

                                <?php if (!empty($ppx_data['upload_file'])): ?>
                                    <div class="row">
                                        <div class="col-12">
                                            <h6 class="info-label">File Lampiran:</h6>
                                            <a href="../uploads/ppx/<?= htmlspecialchars($ppx_data['upload_file']) ?>"
                                                target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-download"></i> Download File
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Items Card -->
                               <!-- Detail Items Card -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i>
                                    Detail Item PPX (<?= mysqli_num_rows($result_detail) ?> item)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($result_detail) > 0): ?>
                                    <?php 
                                    $grouped_details = [];
                                    $total_all = 0;
                                    while ($detail = mysqli_fetch_assoc($result_detail)) {
                                        $akun = $detail['no_akun'] ?: 'Tanpa Akun';
                                        if (!isset($grouped_details[$akun])) {
                                            $grouped_details[$akun] = [];
                                        }
                                        $grouped_details[$akun][] = $detail;
                                        $total_all += $detail['total_harga'];
                                    }
                                    ?>
                                    
                                    <?php foreach ($grouped_details as $akun => $items): ?>
                                        <?php 
                                            // Hitung subtotal untuk akun ini
                                            $subtotal = 0;
                                            foreach($items as $item) $subtotal += $item['total_harga']; 
                                        ?>
                                        <div class="card mb-4 border-info shadow-sm">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 text-dark"><i class="fas fa-wallet text-info"></i> Kategori: <strong><?= htmlspecialchars($akun) ?></strong></h6>
                                                <h6 class="mb-0 text-primary">Subtotal: Rp <?= number_format($subtotal, 0, ',', '.') ?></h6>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-hover mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th width="5%" class="text-center">No</th>
                                                            <th width="35%">Rincian Item</th>
                                                            <th width="15%" class="text-center">Qty</th>
                                                            <th width="20%" class="text-right">Harga Satuan</th>
                                                            <th width="25%" class="text-right">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $no = 1; foreach ($items as $item): ?>
                                                            <tr>
                                                                <td class="text-center"><?= $no++ ?></td>
                                                                <td>
                                                                    <?= htmlspecialchars($item['rincian']) ?>
                                                                    <?php if (!empty($item['deskripsi'])): ?>
                                                                        <br><small class="text-muted"><?= htmlspecialchars($item['deskripsi']) ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center"><?= number_format($item['jumlah']) ?> <?= htmlspecialchars($item['satuan']) ?></td>
                                                                <td class="text-right">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                                                <td class="text-right"><strong>Rp <?= number_format($item['total_harga'], 0, ',', '.') ?></strong></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Total Summary -->
                                    <div class="alert alert-info mt-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>TOTAL KESELURUHAN PPX:</strong>
                                            </div>
                                            <div class="col-md-6 text-right">
                                                <h5 class="mb-0"><strong>Rp <?= number_format($total_all, 0, ',', '.') ?></strong></h5>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tombol Kembali di bawah Total (pindah ke kiri) -->
                                    <div class="text-left mb-4">
                                        <a href="dashboard.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Kembali
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Tidak ada detail item untuk PPX ini</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Realisasi Card - Only show if there are realisasi records -->
                <?php if (count($all_realisasi) > 0): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card detail-card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-receipt"></i>
                                        Riwayat Realisasi (<?= count($all_realisasi) ?> kali)
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Summary Realisasi -->
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="alert alert-info text-center">
                                                <small class="text-muted d-block">Uang Muka Diterima</small>
                                                <h5 class="mb-0"><strong>Rp <?= number_format($ppx_data['uang_muka'], 0, ',', '.') ?></strong></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="alert alert-primary text-center">
                                                <small class="text-muted d-block">Total Direalisasi</small>
                                                <h5 class="mb-0"><strong>Rp <?= number_format($total_realisasi_all, 0, ',', '.') ?></strong></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="alert alert-<?= ($ppx_data['uang_muka'] - $total_realisasi_all) >= 0 ? 'success' : 'danger' ?> text-center">
                                                <small class="text-muted d-block">Selisih</small>
                                                <h5 class="mb-0"><strong>Rp <?= number_format(abs($ppx_data['uang_muka'] - $total_realisasi_all), 0, ',', '.') ?></strong></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="alert alert-<?= $ada_reimbursement ? 'warning' : 'secondary' ?> text-center">
                                                <small class="text-muted d-block">Reimbursement</small>
                                                <h5 class="mb-0">
                                                    <?php if ($ada_reimbursement): ?>
                                                        <strong>Rp <?= number_format($total_reimbursement, 0, ',', '.') ?></strong>
                                                    <?php else: ?>
                                                        <strong>-</strong>
                                                    <?php endif; ?>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($ada_reimbursement): ?>
                                        <!-- Reimbursement Status Info -->
                                        <div class="alert alert-<?= $reimbursement_processed ? 'success' : 'warning' ?> mb-4">
                                            <i class="fas fa-<?= $reimbursement_processed ? 'check-circle' : 'clock' ?>"></i>
                                            <strong>
                                                <?php if ($reimbursement_processed): ?>
                                                    Reimbursement Sudah Diproses
                                                <?php else: ?>
                                                    Menunggu Reimbursement dari Finance
                                                <?php endif; ?>
                                            </strong><br>
                                            <small>
                                                <?php if ($reimbursement_processed): ?>
                                                    Finance sudah memproses reimbursement sebesar <strong>Rp <?= number_format($total_reimbursement, 0, ',', '.') ?></strong>.
                                                <?php else: ?>
                                                    Anda menggunakan uang pribadi sebesar <strong>Rp <?= number_format($total_reimbursement, 0, ',', '.') ?></strong>.
                                                    Finance sedang memproses pengembalian dana.
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    <!-- List Semua Realisasi -->
                                    <?php foreach ($all_realisasi as $idx => $real):
                                        $detail_barang_real = json_decode($real['detail_barang'], true) ?: [];
                                    ?>
                                        <div class="card mb-3 shadow-sm">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-file-invoice"></i> Realisasi #<?= $idx + 1 ?>
                                                    <small class="text-muted">- <?= date('d/m/Y H:i', strtotime($real['tanggal_realisasi'])) ?></small>
                                                    <?php if ($real['status_selisih'] === 'kurang' && $real['confirm_pakai_uang_pribadi'] == 1): ?>
                                                        <?php if (isset($real['is_reimbursed']) && $real['is_reimbursed'] == 1): ?>
                                                            <span class="badge badge-success ml-2">
                                                                <i class="fas fa-check-circle"></i> Reimbursement Selesai
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning ml-2">
                                                                <i class="fas fa-clock"></i> Menunggu Reimbursement
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php elseif ($real['status_selisih'] === 'kembali'): ?>
                                                        <span class="badge badge-success ml-2">
                                                            <i class="fas fa-hand-holding-usd"></i> Ada Uang Kembali
                                                        </span>
                                                    <?php elseif ($real['status_selisih'] === 'pas'): ?>
                                                        <span class="badge badge-info ml-2">
                                                            <i class="fas fa-check"></i> Pas
                                                        </span>
                                                    <?php endif; ?>
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Total Realisasi</small><br>
                                                        <strong class="text-primary">Rp <?= number_format($real['total_realisasi'], 0, ',', '.') ?></strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Uang Kembali/Kurang</small><br>
                                                        <strong class="text-<?= $real['status_selisih'] === 'kurang' ? 'danger' : 'success' ?>">
                                                            Rp <?= number_format(abs($real['uang_kembali']), 0, ',', '.') ?>
                                                        </strong>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small class="text-muted">Status</small><br>
                                                        <strong>
                                                            <?php
                                                            switch ($real['status_selisih']) {
                                                                case 'kembali':
                                                                    echo '<span class="badge badge-success">Uang Kembali</span>';
                                                                    break;
                                                                case 'kurang':
                                                                    echo '<span class="badge badge-danger">Kurang</span>';
                                                                    break;
                                                                case 'pas':
                                                                    echo '<span class="badge badge-info">Pas</span>';
                                                                    break;
                                                            }
                                                            ?>
                                                        </strong>
                                                    </div>
                                                </div>

                                                <?php if (!empty($real['keterangan_realisasi'])): ?>
                                                    <div class="alert alert-info mb-3">
                                                        <small class="text-muted"><i class="fas fa-info-circle"></i> Keterangan:</small><br>
                                                        <?= nl2br(htmlspecialchars($real['keterangan_realisasi'])) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Detail Barang Realisasi -->
                                                <?php if (!empty($detail_barang_real)): ?>
                                                    <h6 class="text-muted mb-2"><i class="fas fa-shopping-cart"></i> Detail Barang yang Dibeli:</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered table-hover">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th width="5%" class="text-center">No</th>
                                                                    <th width="20%">Nama Barang</th>
                                                                    <th width="15%">Jumlah</th>
                                                                    <th width="10%" class="text-center">Qty</th>
                                                                    <th width="20%" class="text-right">Harga Satuan</th>
                                                                    <th width="20%" class="text-right">Total</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($detail_barang_real as $idx_item => $item): ?>
                                                                    <tr>
                                                                        <td class="text-center"><?= $idx_item + 1 ?></td>
                                                                        <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                                                        <td class="text-center"><?= $item['jumlah'] ?> <?= htmlspecialchars($item['satuan']) ?></td>
                                                                        <td class="text-right">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                                                        <td class="text-right"><strong>Rp <?= number_format($item['total_harga'], 0, ',', '.') ?></strong></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Bukti Nota/Realisasi -->
                                                <?php
                                                $files_bukti = json_decode($real['bukti_realisasi'], true);
                                                if (!$files_bukti || !is_array($files_bukti)) {
                                                    $files_bukti = [];
                                                }
                                                ?>
                                                <?php if (count($files_bukti) > 0): ?>
                                                    <hr>
                                                    <h6 class="text-muted mb-2">
                                                        <i class="fas fa-receipt"></i> Bukti Nota/Realisasi
                                                        <span class="badge badge-info ml-2"><?= count($files_bukti) ?> file</span>
                                                    </h6>
                                                    <div class="row">
                                                        <?php foreach ($files_bukti as $index => $file): ?>
                                                            <?php
                                                            $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png']);
                                                            $file_path = '../uploads/ppx_realisasi/' . htmlspecialchars($file);
                                                            ?>
                                                            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-6 mb-3">
                                                                <div class="card h-100 shadow-sm">
                                                                    <div class="card-body p-2 text-center" style="position: relative; min-height: 150px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                                                        <?php if ($is_image): ?>
                                                                            <a href="<?= $file_path ?>" target="_blank">
                                                                                <img src="<?= $file_path ?>"
                                                                                    alt="Bukti Realisasi <?= $index + 1 ?>"
                                                                                    style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                                                                    onerror="this.onerror=null; this.src='../assets/img/no-image.png';">
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <a href="<?= $file_path ?>" target="_blank" class="text-center text-danger" style="text-decoration: none;">
                                                                                <i class="fas fa-file-pdf" style="font-size: 40px;"></i><br>
                                                                                <span style="font-size: 11px; color: #6c757d; margin-top: 5px; display: block;">PDF</span>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="card-footer bg-light p-2">
                                                                        <small class="d-block mb-1 text-muted text-center">
                                                                            <i class="fas fa-<?= $is_image ? 'image' : 'file-pdf' ?>"></i>
                                                                            File #<?= $index + 1 ?>
                                                                        </small>
                                                                        <a href="<?= $file_path ?>"
                                                                            download
                                                                            class="btn btn-sm btn-outline-primary btn-block">
                                                                            <i class="fas fa-download"></i> Download
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php include('../layouts/footer.php'); ?>
            </div>
        </div>
    </div>

    <?php
    // Prior include removed to avoid fatal warning if file missing.
    $footer_js_path = __DIR__ . '/../layouts/footer_js.php';
    if (file_exists($footer_js_path)) {
        include $footer_js_path;
    } else {
        error_log('Missing layout file: ' . $footer_js_path);
        // Fallback minimal scripts — sesuaikan path jika project Anda menempatkan asset di lokasi lain
    ?>
        <script src="/assets/plugins/jquery/jquery.min.js"></script>
        <script src="/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="/assets/js/app.js"></script>
    <?php
    }
    ?>
</body>

</html>