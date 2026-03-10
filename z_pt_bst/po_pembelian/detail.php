<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst', 'read');

$id_pembelian = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_pembelian == 0) { header("Location: dashboard.php"); exit(); }

$q_hdr = mysqli_query($conn, "SELECT p.*, u.nama_user as pembuat FROM bst_pembelian p LEFT JOIN user_dfm u ON p.created_by = u.id_user WHERE p.id_pembelian = $id_pembelian");
if(mysqli_num_rows($q_hdr) == 0) { header("Location: dashboard.php"); exit(); }
$hdr = mysqli_fetch_assoc($q_hdr);

$q_dtl = mysqli_query($conn, "SELECT d.*, a.kode_barang, a.nama_barang, a.satuan 
                              FROM bst_pembelian_detail d 
                              JOIN inventory_atk a ON d.id_barang = a.id_barang 
                              WHERE d.id_pembelian = $id_pembelian");

$page_title = "Detail PO Pembelian";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - DFM</title>
    <link rel="icon" href="../../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<?php include_once('../../layouts/navbar.php'); ?>
<div class="page-wrapper">
<div class="content container-fluid">

    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title"><i class="fas fa-file-invoice text-primary"></i> <?= $page_title ?></h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">List PO</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($hdr['nomor_po']) ?></li>
                </ul>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Kembali</a>
                <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Cetak</button>
            </div>
        </div>
    </div>

    <!-- Informasi PO -->
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="row mb-4">
                <div class="col-sm-6">
                    <h5 class="mb-3 text-muted">Kepada Supplier:</h5>
                    <h3 class="text-dark font-weight-bold"><?= htmlspecialchars($hdr['nama_supplier']) ?></h3>
                </div>
                <div class="col-sm-6 text-sm-right">
                    <h4 class="text-primary font-weight-bold">PO: <?= htmlspecialchars($hdr['nomor_po']) ?></h4>
                    <div class="mb-1 text-muted">Tanggal Cetak: <?= date('d M Y', strtotime($hdr['tanggal_po'])) ?></div>
                    <div class="mb-1 text-muted">Dibuat Oleh: <?= htmlspecialchars($hdr['pembuat']) ?></div>
                    <div class="mt-2">
                        <?php if($hdr['status'] == 'Selesai'): ?>
                            <span class="badge badge-success px-3 py-2" style="font-size: 14px;"><i class="fas fa-check-circle"></i> Selesai (Stok Telah Ditambah)</span>
                        <?php else: ?>
                            <span class="badge badge-warning px-3 py-2" style="font-size: 14px;"><i class="fas fa-clock"></i> Pending (Menunggu Barang)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabel Detail -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" width="5%">No</th>
                            <th width="15%">Kode Barang</th>
                            <th width="35%">Nama Barang</th>
                            <th class="text-center" width="10%">Kuantitas</th>
                            <th class="text-center" width="15%">Satuan</th>
                            <th class="text-right" width="20%">Harga Satuan (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; $total_qty=0; $total_harga=0; while($b=mysqli_fetch_assoc($q_dtl)): 
                            $total_qty += $b['qty'];
                            $total_harga += ($b['harga_satuan'] * $b['qty']);
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= htmlspecialchars($b['kode_barang']) ?></td>
                            <td class="font-weight-bold text-dark"><?= htmlspecialchars($b['nama_barang']) ?></td>
                            <td class="text-center font-weight-bold text-primary"><?= number_format($b['qty']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($b['satuan']) ?></td>
                            <td class="text-right"><?= number_format($b['harga_satuan'],0,',','.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="3" class="text-right">TOTAL KUANTITAS:</td>
                            <td class="text-center text-primary"><?= number_format($total_qty) ?></td>
                            <td class="text-right">GRAND TOTAL:</td>
                            <td class="text-right text-success">Rp <?= number_format($total_harga,0,',','.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if($hdr['status'] == 'Pending' && $auth->hasPermission('pt_bst', 'process')): ?>
            <div class="text-right mt-4 d-print-none">
                <hr>
                <div class="alert alert-info d-inline-block text-left mb-0 mr-3 align-middle" style="max-width:350px;">
                    <i class="fas fa-info-circle mr-1"></i> Klik 'Tandai Selesai' jika pesanan ini telah tiba di Gudang PT BST. Kuantitas stok mutlak akan bertambah.
                </div>
                <a href="proses_selesai.php?id=<?= $id_pembelian ?>" class="btn btn-lg btn-success shadow-sm align-middle" onclick="event.preventDefault(); var url = this.href; showConfirm('Anda yakin barang belanjaan ini sudah diserahterimakan? Sistem akan memasukkan item ini menjadi persediaan Gudang PT BST.', function() { window.location.href = url; });">
                    <i class="fas fa-check-double mt-1"></i> Tandai Selesai & Tambah Stok!
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include_once('../../layouts/footer.php'); ?>
</div>
</div>

<style type="text/css" media="print">
    @page { size: auto;  margin: 0mm; }
    body { background-color:#FFFFFF; color:#000; padding:15mm; }
    .card { border:none; box-shadow:none !important; }
    .page-wrapper { margin-left: 0 !important; padding-top: 0 !important; }
    .header, .sidebar, .d-print-none { display: none !important; }
</style>
</body>
</html>
