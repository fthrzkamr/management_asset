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

// Query untuk mendapatkan data PPX Request dengan JOIN ke tabel divisi dan departemen
$query_ppx = "SELECT 
                pr.*,
                u.nama_user as nama_pengaju,
                d.nama_divisi,
                dep.nama_departemen
              FROM ppx_request pr
              LEFT JOIN user_dfm u ON pr.user_id = u.id_user
              LEFT JOIN divisi d ON pr.divisi = d.id_divisi
              LEFT JOIN departemen dep ON pr.departemen = dep.id_departemen
              WHERE pr.no_ppx = ?";

$stmt = mysqli_prepare($conn, $query_ppx);
mysqli_stmt_bind_param($stmt, 's', $no_ppx);
mysqli_stmt_execute($stmt);
$result_ppx = mysqli_stmt_get_result($stmt);

if (!$result_ppx || mysqli_num_rows($result_ppx) == 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($result_ppx);
mysqli_stmt_close($stmt);

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
                 WHERE no_ppx = ?
                 ORDER BY no_ppx_detail";

$stmt_detail = mysqli_prepare($conn, $query_detail);
mysqli_stmt_bind_param($stmt_detail, 's', $no_ppx);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

$items = [];
$total_nominal = 0;
while ($row = mysqli_fetch_assoc($result_detail)) {
    $items[] = $row;
    $total_nominal += $row['total_harga'];
}
mysqli_stmt_close($stmt_detail);

// Query untuk mendapatkan semua data realisasi (bisa multiple)
$query_realisasi = "SELECT * FROM ppx_realisasi 
                    WHERE no_ppx = ?
                    ORDER BY created_at ASC";
$stmt_realisasi = mysqli_prepare($conn, $query_realisasi);
mysqli_stmt_bind_param($stmt_realisasi, 's', $no_ppx);
mysqli_stmt_execute($stmt_realisasi);
$result_realisasi = mysqli_stmt_get_result($stmt_realisasi);

// Simpan semua realisasi dalam array
$all_realisasi = [];
$total_realisasi_all = 0;
$ada_reimbursement = false;
$total_reimbursement = 0;
$reimbursement_processed = true; // Assume all processed, will be set to false if found any unprocessed

while ($row_real = mysqli_fetch_assoc($result_realisasi)) {
    $all_realisasi[] = $row_real;
    $total_realisasi_all += floatval($row_real['total_realisasi']);
    
    // Cek apakah ada yang perlu reimbursement (status_selisih = 'kurang' dan confirm_pakai_uang_pribadi = 1)
    if ($row_real['status_selisih'] === 'kurang' && $row_real['confirm_pakai_uang_pribadi'] == 1) {
        $ada_reimbursement = true;
        $total_reimbursement += abs(floatval($row_real['uang_kembali']));
        
        // Check if this reimbursement has been processed
        if (!isset($row_real['is_reimbursed']) || $row_real['is_reimbursed'] == 0) {
            $reimbursement_processed = false;
        }
    }
}
mysqli_stmt_close($stmt_realisasi);

// Untuk backward compatibility, set realisasi_data ke realisasi pertama
$realisasi_data = !empty($all_realisasi) ? $all_realisasi[0] : null;

// Parse detail barang dari realisasi pertama (untuk backward compatibility)
$detail_barang = [];
if ($realisasi_data && !empty($realisasi_data['detail_barang'])) {
    $detail_barang = json_decode($realisasi_data['detail_barang'], true);
    if (!is_array($detail_barang)) {
        $detail_barang = [];
    }
}

// Messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

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
        echo '<link rel="stylesheet" href="../assets/css/bootstrap.min.css">';
    }
    ?>

    <!-- Lightbox CSS untuk gallery -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet">

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

        .btn-back {
            background: #6c757d;
            border-color: #6c757d;
        }

        .btn-back:hover {
            background: #545b62;
            border-color: #4e555b;
        }

        /* Custom styles for detail page */
        .page-header {
            background: white;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
            margin-bottom: 20px;
        }

        .card-header {
            border-bottom: none;
            padding: 15px;
        }

        .card-header h5 {
            margin-bottom: 0;
        }

        .info-group {
            margin-bottom: 1rem;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: #2d3748;
            margin-bottom: 0;
            font-size: 14px;
        }

        .summary-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #0066cc;
            text-align: center;
        }

        .summary-item {
            padding: 15px;
        }

        .card-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 15px;
        }

        .table-responsive {
            border-radius: 0;
        }

        .table {
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php 
        $layout_dir = realpath(__DIR__ . '/../layouts');
        if ($layout_dir && file_exists($layout_dir . '/navbar.php')) {
            include $layout_dir . '/navbar.php';
        }
        ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="page-title">
                                <i class="fas fa-file-invoice-dollar"></i> Detail Pengajuan PPX
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Approval PPX Finance</a></li>
                                <li class="breadcrumb-item active">Detail</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Informasi PPX Header -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Informasi PPX
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="info-label">No. PPX:</label>
                                    <p class="info-value font-weight-bold"><?= htmlspecialchars($ppx_data['no_ppx']) ?></p>
                                </div>
                                <div class="info-group mb-3">
                                    <label class="info-label">Tanggal Pengajuan:</label>
                                    <p class="info-value"><?= date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) ?></p>
                                </div>
                                <div class="info-group mb-3">
                                    <label class="info-label">Nama PIC:</label>
                                    <p class="info-value"><?= htmlspecialchars($ppx_data['nama_pic']) ?></p>
                                </div>
                                <div class="info-group mb-3">
                                    <label class="info-label">Jabatan:</label>
                                    <p class="info-value"><?= htmlspecialchars($ppx_data['jabatan']) ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="info-label">Divisi:</label>
                                    <p class="info-value"><?= htmlspecialchars($ppx_data['nama_divisi'] ?? $ppx_data['divisi']) ?></p>
                                </div>
                                <div class="info-group mb-3">
                                    <label class="info-label">Departemen:</label>
                                    <p class="info-value"><?= htmlspecialchars($ppx_data['nama_departemen'] ?? $ppx_data['departemen']) ?></p>
                                </div>
                                <div class="info-group mb-3">
                                    <label class="info-label">Cabang:</label>
                                    <p class="info-value"><?= htmlspecialchars($ppx_data['cabang']) ?></p>
                                </div>
                                <div class="info-group mb-3">
                                    <label class="info-label">Status:</label>
                                    <p class="info-value">
                                        <span class="badge badge-<?= $ppx_data['status'] == 'Uang Muka' ? 'success' : ($ppx_data['status'] == 'Realisasi' ? 'info' : 'primary') ?>">
                                            <?= htmlspecialchars($ppx_data['status']) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Items -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Detail Item Pengajuan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="15%">No. Akun</th>
                                        <th>Rincian</th>
                                        <th width="12%">Jumlah</th>
                                        <th width="15%">Harga Satuan</th>
                                        <th width="15%">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($items) > 0): ?>
                                        <?php foreach ($items as $index => $item): ?>
                                            <tr>
                                                <td class="text-center"><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($item['no_akun']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($item['rincian']) ?>
                                                    <?php if (!empty($item['deskripsi'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($item['deskripsi']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= $item['jumlah'] ?> <?= htmlspecialchars($item['satuan']) ?></td>
                                                <td class="text-right">Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                                                <td class="text-right font-weight-bold">Rp <?= number_format($item['total_harga'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Tidak ada item</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="5" class="text-right">Total:</th>
                                        <th class="text-right">Rp <?= number_format($total_nominal, 0, ',', '.') ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Informasi Uang Muka -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-money-bill-wave"></i> Informasi Uang Muka
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-group">
                                    <label class="info-label">Total Pengajuan:</label>
                                    <p class="info-value font-weight-bold text-primary">
                                        Rp <?= number_format($total_nominal, 0, ',', '.') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-group">
                                    <label class="info-label">Uang Muka Diberikan:</label>
                                    <p class="info-value font-weight-bold text-success">
                                        Rp <?= number_format($ppx_data['uang_muka'], 0, ',', '.') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-group">
                                    <label class="info-label">Sisa/Kekurangan:</label>
                                    <p class="info-value font-weight-bold">
                                        <span class="<?= ($total_nominal - $ppx_data['uang_muka']) > 0 ? 'text-danger' : 'text-success' ?>">
                                            Rp <?= number_format(abs($total_nominal - $ppx_data['uang_muka']), 0, ',', '.') ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informasi Realisasi (Tampil jika ada data realisasi) -->
                <?php if (!empty($all_realisasi)): ?>
                    <!-- Alert Reimbursement jika ada -->
                    <?php if ($ada_reimbursement && $ppx_data['status'] === 'Selesai Realisasi'): ?>
                    <div class="alert alert-warning border-left-warning shadow-sm mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-3x mr-3"></i>
                            <div class="flex-grow-1">
                                <h5 class="alert-heading mb-2">
                                    <i class="fas fa-hand-holding-usd"></i> Perlu Reimbursement
                                </h5>
                                <p class="mb-2">
                                    User menggunakan <strong>uang pribadi</strong> untuk menutupi kekurangan sebesar 
                                    <strong class="text-danger">Rp <?= number_format($total_reimbursement, 0, ',', '.') ?></strong>
                                </p>
                                <hr>
                                <p class="mb-0 small">
                                    <strong>Aksi yang perlu dilakukan:</strong><br>
                                    1. Proses transfer reimbursement ke rekening user: <strong><?= htmlspecialchars($ppx_data['nama_rek']) ?> - <?= htmlspecialchars($ppx_data['no_rek']) ?></strong><br>
                                    2. Setelah transfer, klik tombol "Proses Reimbursement" di bawah<br>
                                    3. Setelah reimbursement selesai, baru bisa approve final
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-check-circle"></i> Informasi Realisasi (<?= count($all_realisasi) ?> kali)
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Summary Box Total -->
                            <div class="alert alert-light border mb-4">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="summary-item">
                                            <i class="fas fa-wallet text-success fa-2x"></i><br>
                                            <small class="text-muted">Uang Muka</small><br>
                                            <strong class="text-success">Rp <?= number_format($ppx_data['uang_muka'], 0, ',', '.') ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-item">
                                            <i class="fas fa-shopping-cart text-primary fa-2x"></i><br>
                                            <small class="text-muted">Total Direalisasi</small><br>
                                            <strong class="text-primary">Rp <?= number_format($total_realisasi_all, 0, ',', '.') ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-item">
                                            <i class="fas fa-equals text-<?= $ada_reimbursement ? 'danger' : 'success' ?> fa-2x"></i><br>
                                            <small class="text-muted">Selisih</small><br>
                                            <?php 
                                            $selisih = $ppx_data['uang_muka'] - $total_realisasi_all;
                                            $selisih_class = $selisih >= 0 ? 'success' : 'danger';
                                            ?>
                                            <strong class="text-<?= $selisih_class ?>">
                                                Rp <?= number_format(abs($selisih), 0, ',', '.') ?>
                                                <?= $selisih >= 0 ? '(Kembali)' : '(Kurang)' ?>
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="summary-item">
                                            <?php if ($ada_reimbursement): ?>
                                                <i class="fas fa-hand-holding-usd text-warning fa-2x"></i><br>
                                                <small class="text-muted">Perlu Reimbursement</small><br>
                                                <strong class="text-warning">Rp <?= number_format($total_reimbursement, 0, ',', '.') ?></strong>
                                            <?php else: ?>
                                                <i class="fas fa-check-circle text-success fa-2x"></i><br>
                                                <small class="text-muted">Status</small><br>
                                                <strong class="text-success">Tidak Ada Reimbursement</strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                                    <i class="fas fa-exclamation-triangle"></i> Perlu Reimbursement
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif ($real['status_selisih'] === 'kembali'): ?>
                                            <span class="badge badge-success ml-2">
                                                <i class="fas fa-hand-holding-usd"></i> Ada Uang Kembali
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
                                                        echo '<span class="badge badge-danger">Kekurangan</span>';
                                                        if ($real['confirm_pakai_uang_pribadi'] == 1) {
                                                            echo ' <span class="badge badge-warning">Pakai Uang Pribadi</span>';
                                                        }
                                                        break;
                                                    case 'pas':
                                                        echo '<span class="badge badge-info">PAS</span>';
                                                        break;
                                                }
                                                ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <?php if ($real['keterangan_realisasi']): ?>
                                    <div class="alert alert-secondary small mb-3">
                                        <strong>Keterangan:</strong> <?= htmlspecialchars($real['keterangan_realisasi']) ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Detail Barang -->
                                    <?php if (count($detail_barang_real) > 0): ?>
                                    <h6 class="mb-2"><i class="fas fa-shopping-cart"></i> Detail Barang (<?= count($detail_barang_real) ?> item)</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th>Nama Barang</th>
                                                    <th width="15%">No. Akun</th>
                                                    <th width="10%" class="text-center">Qty</th>
                                                    <th width="15%" class="text-right">Harga Satuan</th>
                                                    <th width="15%" class="text-right">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($detail_barang_real as $no => $item): ?>
                                                <tr>
                                                    <td class="text-center"><?= $no + 1 ?></td>
                                                    <td><?= htmlspecialchars($item['nama_barang'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($item['no_akun'] ?? '-') ?></td>
                                                    <td class="text-center"><?= $item['jumlah'] ?? 0 ?> <?= htmlspecialchars($item['satuan'] ?? '') ?></td>
                                                    <td class="text-right">Rp <?= number_format($item['harga_satuan'] ?? 0, 0, ',', '.') ?></td>
                                                    <td class="text-right font-weight-bold">Rp <?= number_format($item['total_harga'] ?? 0, 0, ',', '.') ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Bukti Realisasi (Semua) -->
                            <div class="row">
                                <div class="col-12">
                                    <?php
                                    // Kumpulkan semua file bukti dari semua realisasi
                                    $all_files_bukti = [];
                                    $file_counter = 0;
                                    foreach ($all_realisasi as $idx_real => $real_item) {
                                        $files_bukti_temp = json_decode($real_item['bukti_realisasi'], true);
                                        if ($files_bukti_temp && is_array($files_bukti_temp)) {
                                            foreach ($files_bukti_temp as $file) {
                                                $all_files_bukti[] = [
                                                    'file' => $file,
                                                    'realisasi_no' => $idx_real + 1
                                                ];
                                                $file_counter++;
                                            }
                                        }
                                    }
                                    ?>
                                    <h6 class="info-label mb-3">
                                        <i class="fas fa-receipt"></i> Bukti Nota/Realisasi (Semua)
                                        <span class="badge badge-info ml-2"><?= $file_counter ?> file</span>
                                    </h6>

                                    <?php if (count($all_files_bukti) > 0): ?>
                                        <div class="row">
                                            <?php foreach ($all_files_bukti as $index => $file_data): ?>
                                                <?php
                                                $file = $file_data['file'];
                                                $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png']);
                                                $file_path = '../uploads/ppx_realisasi/' . htmlspecialchars($file);
                                                ?>
                                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="card-body p-2 text-center" style="position: relative; height: 180px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; overflow: hidden;">
                                                            <?php if ($is_image): ?>
                                                                <a href="<?= $file_path ?>" data-lightbox="realisasi-gallery" data-title="Bukti Realisasi #<?= $file_data['realisasi_no'] ?> - File <?= $index + 1 ?>">
                                                                    <img src="<?= $file_path ?>"
                                                                        alt="Bukti Realisasi <?= $index + 1 ?>"
                                                                        style="max-width: 100%; max-height: 170px; width: auto; height: auto; object-fit: contain;"
                                                                        onerror="this.onerror=null; this.src='../assets/img/no-image.png';">
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="<?= $file_path ?>" target="_blank" class="text-center text-danger" style="text-decoration: none;">
                                                                    <i class="fas fa-file-pdf" style="font-size: 50px;"></i><br>
                                                                    <span style="font-size: 12px; color: #6c757d; margin-top: 10px; display: block;">PDF Document</span>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-footer bg-light p-2">
                                                            <small class="d-block mb-2 text-muted">
                                                                <i class="fas fa-<?= $is_image ? 'image' : 'file-pdf' ?>"></i>
                                                                Realisasi #<?= $file_data['realisasi_no'] ?> - File #<?= $index + 1 ?> (<?= strtoupper($file_ext) ?>)
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
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Tidak ada file bukti realisasi.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-footer text-right">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <?php if ($ppx_data['status'] == 'Finance'): ?>
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#approveModal">
                                <i class="fas fa-check"></i> Setujui
                            </button>
                            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php include __DIR__ . '/../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Lightbox JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

    <script>
    // Function to approve "Selesai Realisasi" to "Selesai"
    function approveSelesaiRealisasi(no_ppx) {
        const message = 'Approve PPX ini ke status Selesai?<br><br><small class="text-muted">Data tidak dapat diubah setelah diapprove.</small>';
        
        showConfirm(message, function(confirmed) {
            if (!confirmed) return;
            
            // Submit approval
            const form = new FormData();
            form.append('no_ppx', no_ppx);
            form.append('action', 'approve');

            fetch('process_approve.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: form
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Berhasil! ' + data.message);
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Gagal! ' + data.message);
                }
            })
            .catch(error => {
                alert('Error! Terjadi kesalahan saat memproses approval: ' + error.message);
                console.error('Error:', error);
            });
        });
    }

    // Function to process reimbursement
    function prosesReimbursement(no_ppx) {
        const message = 'Apakah reimbursement sudah ditransfer ke user?<br><br><small class="text-muted">Pastikan transfer sudah berhasil sebelum melanjutkan</small>';
        
        showConfirm(message, function(confirmed) {
            if (!confirmed) return;
            
            // Submit reimbursement
            const form = new FormData();
            form.append('no_ppx', no_ppx);

            fetch('process_reimbursement.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: form
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Berhasil! ' + data.message);
                    // Enable approve button
                    const btnApprove = document.getElementById('btnApproveSelesai');
                    if (btnApprove) {
                        btnApprove.disabled = false;
                    }
                    // Reload page to update UI
                    location.reload();
                } else {
                    alert('Gagal! ' + data.message);
                }
            })
            .catch(error => {
                alert('Error! Terjadi kesalahan saat memproses reimbursement');
                console.error('Error:', error);
            });
        });
    }
    </script>

</body>

</html>