<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Determine base path for includes
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

// Create auth instance
$auth = new AuthHelper($conn);

// Check permission for ATK (same as dashboard)
$auth->requirePermission('inventory_atk_pengambilan', 'read');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get pengajuan data
$sql = "SELECT p.*
        FROM atk_pengambilan p
        WHERE p.id_pengambilan = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pengajuan = mysqli_fetch_assoc($result);

if (!$pengajuan) {
    header("Location: dashboard.php");
    exit();
}

// Get detail items
$sql_detail = "SELECT pd.id_barang, pd.qty_diminta as jumlah, i.kode_barang, i.nama_barang, i.satuan, 
                      COALESCE(s.quantity, 0) as stok_aktual
               FROM atk_pengambilan_detail pd
               JOIN inventory_atk i ON pd.id_barang = i.id_barang
               LEFT JOIN inventory_stok s ON i.id_barang = s.id_barang
               WHERE pd.id_pengambilan = ?";
$stmt_detail = mysqli_prepare($conn, $sql_detail);
if (!$stmt_detail) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt_detail, 'i', $id);
mysqli_stmt_execute($stmt_detail);
$items = mysqli_stmt_get_result($stmt_detail);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengajuan - DFM</title>
    
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/dua.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Detail Pengajuan ATK</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan ATK</a></li>
                                <li class="breadcrumb-item active">Detail</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Informasi Pengajuan -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Informasi Pengajuan</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Nomor Pengambilan</strong></td>
                                        <td><?= $pengajuan['nomor_pengambilan'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tanggal</strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($pengajuan['tanggal_pengambilan'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nama Pemohon</strong></td>
                                        <td><?= $pengajuan['nama_pengambil'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Divisi</strong></td>
                                        <td><span class="badge badge-info"><?= $pengajuan['divisi'] ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>IP Address</strong></td>
                                        <td><code><?= $pengajuan['ip_address'] ?></code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status</strong></td>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            switch ($pengajuan['status']) {
                                                case 'Pending':
                                                    $badge_class = 'badge-warning';
                                                    break;
                                                case 'Approved':
                                                    $badge_class = 'badge-success';
                                                    break;
                                                case 'Rejected':
                                                    $badge_class = 'badge-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= $pengajuan['status'] ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Approval -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Informasi Approval</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($pengajuan['status'] == 'Pending'): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock"></i> Pengajuan menunggu approval
                                </div>
                                <?php if ($auth->hasPermission('inventory_atk_pengambilan', 'approve')): ?>
                                <div class="text-center">
                                    <button type="button" class="btn btn-success btn-lg mr-2" 
                                            onclick="approveItem(<?= $pengajuan['id_pengambilan'] ?>, 'Approved')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button type="button" class="btn btn-danger btn-lg" 
                                            onclick="approveItem(<?= $pengajuan['id_pengambilan'] ?>, 'Rejected')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Status:</strong> Pengajuan telah <?= $pengajuan['status'] == 'Approved' ? 'disetujui' : 'ditolak' ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Barang -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Daftar Barang yang Diminta</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Kode Barang</th>
                                                <th>Nama Barang</th>
                                                <th>Qty Diminta</th>
                                                <th>Satuan</th>
                                                <th>Stok Saat Ini</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while ($item = mysqli_fetch_assoc($items)): 
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><code><?= $item['kode_barang'] ?></code></td>
                                                <td><?= $item['nama_barang'] ?></td>
                                                <td>
                                                    <?php if ($pengajuan['status'] == 'Pending' && $auth->hasPermission('inventory_atk_pengambilan', 'approve')): ?>
                                                        <input type="number" class="form-control form-control-sm qty-edit" data-id_barang="<?= $item['id_barang'] ?>" value="<?= $item['jumlah'] ?>" min="0" style="width: 80px;">
                                                    <?php else: ?>
                                                        <strong><?= $item['jumlah'] ?></strong>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $item['satuan'] ?></td>
                                                <td>
                                                    <?php if ($item['stok_aktual'] < $item['jumlah']): ?>
                                                    <span class="badge badge-danger">
                                                        <?= $item['stok_aktual'] ?> (Kurang!)
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="badge badge-success"><?= $item['stok_aktual'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['stok_aktual'] >= $item['jumlah']): ?>
                                                    <i class="fas fa-check-circle text-success"></i> Tersedia
                                                    <?php else: ?>
                                                    <i class="fas fa-exclamation-circle text-danger"></i> Stok Kurang
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                </div> <!-- row -->
            </div> <!-- content -->
            <?php include '../layouts/footer.php'; ?>

    <script>
    function approveItem(id, status) {
        const action = status === 'Approved' ? 'menyetujui' : 'menolak';
        
        if (confirm(`Apakah Anda yakin ingin ${action} pengajuan ini?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process_approve.php';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id_pengambilan';
            idInput.value = id;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            
            form.appendChild(idInput);
            form.appendChild(statusInput);
            
            // Append updated quantities
            const qtyInputs = document.querySelectorAll('.qty-edit');
            qtyInputs.forEach(input => {
                const hiddenQty = document.createElement('input');
                hiddenQty.type = 'hidden';
                hiddenQty.name = `qty[${input.dataset.id_barang}]`;
                hiddenQty.value = input.value;
                form.appendChild(hiddenQty);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
