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
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Informasi Pengajuan</h5>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p class="info-label">No. PPX</p>
                                        <p class="info-value"><?= htmlspecialchars($ppx_data['no_ppx']) ?></p>

                                        <p class="info-label">Nama PIC</p>
                                        <p class="info-value"><?= htmlspecialchars($ppx_data['nama_pic']) ?></p>

                                        <p class="info-label">Jabatan</p>
                                        <p class="info-value"><?= htmlspecialchars($ppx_data['jabatan']) ?></p>

                                        <p class="info-label">Divisi</p>
                                        <p class="info-value"><?= htmlspecialchars($ppx_data['nama_divisi'] ?? $ppx_data['divisi']) ?></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="info-label">Cabang</p>
                                        <p class="info-value"><?= htmlspecialchars($ppx_data['cabang']) ?></p>

                                        <p class="info-label">Tanggal Pengajuan</p>
                                        <p class="info-value"><?= $ppx_data['tanggal_pengajuan'] ? date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) : '-' ?></p>

                                        <p class="info-label">Tanggal Dibutuhkan</p>
                                        <p class="info-value"><?= $ppx_data['tanggal_dibutuhkan'] ? date('d/m/Y', strtotime($ppx_data['tanggal_dibutuhkan'])) : '-' ?></p>

                                        <p class="info-label">Status</p>
                                        <p class="info-value"><span class="badge badge-primary status-badge"><?= htmlspecialchars($display_status) ?></span></p>
                                    </div>
                                </div>

                                <hr>
                                <h6>Rincian Pengajuan</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Rincian</th>
                                                <th>Qty</th>
                                                <th>Harga Satuan</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($items)): $i=1; foreach($items as $it): ?>
                                                <tr>
                                                    <td class="text-center"><?= $i++ ?></td>
                                                    <td><?= htmlspecialchars($it['rincian']) ?></td>
                                                    <td class="text-center"><?= (int)$it['jumlah'] . ' ' . htmlspecialchars($it['satuan']) ?></td>
                                                    <td class="text-right">Rp <?= number_format($it['harga_satuan'],0,',','.') ?></td>
                                                    <td class="text-right">Rp <?= number_format($it['total_harga'],0,',','.') ?></td>
                                                </tr>
                                            <?php endforeach; else: ?>
                                                <tr><td colspan="5" class="text-center">Tidak ada item</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-primary">
                                                <td colspan="4" class="text-right"><strong>Total</strong></td>
                                                <td class="text-right"><strong>Rp <?= number_format($total_nominal,0,',','.') ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">

                        <div class="card">
                            <div class="card-body">
                                <h6>Meta</h6>
                                <p class="info-label">Pengaju</p>
                                <p class="info-value"><?= htmlspecialchars($ppx_data['nama_pengaju'] ?? $_SESSION['nama_user']) ?></p>
                                <p class="info-label">Departemen</p>
                                <p class="info-value"><?= htmlspecialchars($ppx_data['nama_departemen'] ?? '-') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Realisasi Section -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Daftar Realisasi</h5>
                        <?php if (!empty($all_realisasi)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>Jumlah</th>
                                            <th>Keterangan</th>
                                            <th>Status Selisih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $r=1; foreach($all_realisasi as $real): ?>
                                            <tr>
                                                <td class="text-center"><?= $r++ ?></td>
                                                <td><?= $real['created_at'] ? date('d/m/Y H:i', strtotime($real['created_at'])) : '-' ?></td>
                                                <td class="text-right">Rp <?= number_format($real['total_realisasi'],0,',','.') ?></td>
                                                <td><?= nl2br(htmlspecialchars($real['keterangan'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars($real['status_selisih'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>Tidak ada data realisasi.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // footer JS includes
    if ($layout_dir && file_exists($layout_dir . '/footer_js.php')) {
        include $layout_dir . '/footer_js.php';
    } else {
        echo '<script src="../assets/plugins/jquery/jquery.min.js"></script>';
        echo '<script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>';
    }
    ?>

    <script>
        // small helper if needed
        function closeWindow() { window.close(); }
    </script>
</body>
</html>
