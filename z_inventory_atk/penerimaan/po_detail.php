<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('inventory_atk_penerimaan', 'read');

$id_po = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_user = $_SESSION['id_user'] ?? 1;

$q_po = mysqli_query($conn, "SELECT * FROM bst_po WHERE id_po = $id_po");
$po = mysqli_fetch_assoc($q_po);

if (!$po) {
    echo "<script>alert('Data PO tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}

// Ambil detail pesanan dan crosscheck inventory master ATK
$q_detail = mysqli_query($conn, "
    SELECT 
        d.*, 
        i.kode_barang, 
        i.nama_barang, 
        i.satuan,
        i.kategori
    FROM bst_po_detail d
    JOIN inventory_atk i ON d.id_barang_bst = i.id_barang
    WHERE d.id_po = $id_po
");
$details = [];
while($d = mysqli_fetch_assoc($q_detail)) {
    $details[] = $d;
}

// Handler Proses Penerimaan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_terima'])) {
    $auth->requirePermission('inventory_atk_penerimaan', 'process');
    
    if ($po['status'] == 'Dikirim') {
        mysqli_begin_transaction($conn);
        try {
            // Nomor Bukti Barang Masuk 
            $tahun = date('Y');
            $bulan = date('m');
            $q_urut = mysqli_query($conn, "SELECT COUNT(*) as total FROM atk_barang_masuk WHERE MONTH(tanggal_masuk) = '$bulan' AND YEAR(tanggal_masuk) = '$tahun'");
            $r_urut = mysqli_fetch_assoc($q_urut);
            $urut = str_pad($r_urut['total'] + 1, 3, "0", STR_PAD_LEFT);
            $no_bukti_masuk = "BM/ATK/$tahun/$bulan/$urut";
            $tgl_masuk = date('Y-m-d H:i:s');

            foreach ($details as $item) {
                // Yang dimasukkan stok adalah jumlah real yg dikirim
                $qty_real = $item['qty_dikirim']; 
                $id_bst = $item['id_barang_bst'];
                $kode_bst = $item['kode_barang'];
                $nama_bst = mysqli_real_escape_string($conn, $item['nama_barang']);
                $kategori_bst = mysqli_real_escape_string($conn, $item['kategori']);
                $satuan_bst = mysqli_real_escape_string($conn, $item['satuan']);

                if ($qty_real > 0) {
                    // Karena ini memang sudah Master ATK, ID-nya adalah id_barang_bst
                    $id_barang_atk = $id_bst; 
                    
                    // 2. Tambah Stok ATK (Upsert: buat baru jika belum ada, atau update saldo jika sudah ada)
                    $sql_stok = "INSERT INTO inventory_stok (id_barang, quantity, created_by) 
                                 VALUES ($id_barang_atk, $qty_real, '$id_user') 
                                 ON DUPLICATE KEY UPDATE quantity = quantity + $qty_real, updated_at = NOW()";
                    if (!mysqli_query($conn, $sql_stok)) throw new Exception(mysqli_error($conn));
                    
                    // 3. Catat ke Histori atk_barang_masuk
                    $nomor_po_teks = mysqli_real_escape_string($conn, $po['nomor_po']);
                    mysqli_query($conn, "INSERT INTO atk_barang_masuk (nomor_masuk, tanggal_masuk, id_barang, qty_masuk, created_by, keterangan) 
                                         VALUES ('BM-PO-$nomor_po_teks', '$tgl_masuk', $id_barang_atk, $qty_real, $id_user, 'Penerimaan dr PO BST')");
                                         
                    // 4. Update status detail PO bahwa sudah diterima
                    mysqli_query($conn, "UPDATE bst_po_detail SET qty_diterima = $qty_real WHERE id_detail = " . $item['id_detail']);
                }
            }

            // Update status PO menjadi Selesai (Completed)
            mysqli_query($conn, "UPDATE bst_po SET status = 'Selesai' WHERE id_po = $id_po");

            mysqli_commit($conn);
            echo "<script>alert('Penerimaan Berhasil! Stok ATK otomatis bertambah.'); window.location.href='dashboard.php';</script>";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script>alert('Gagal memproses Penerimaan: " . $e->getMessage() . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Detail & Penerimaan PO - Aplikasi Asset Management</title>

</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Pemeriksaan Barang PO ATK</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Riwayat PO</a></li>
                                <li class="breadcrumb-item active">Detail</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <!-- Info PO -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Informasi PO</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td class="text-muted">Nomor PO</td>
                                        <td><strong><?= htmlspecialchars($po['nomor_po']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Tanggal Booking</td>
                                        <td><?= date('d M Y', strtotime($po['tanggal_po'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Status Saat Ini</td>
                                        <td>
                                            <?php
                                            $badge = $po['status'] == 'Selesai' ? 'badge-success' : ($po['status'] == 'Dikirim' ? 'badge-primary' : ($po['status'] == 'Ditolak' ? 'badge-danger' : 'badge-warning'));
                                            ?>
                                            <span class="badge <?= $badge ?>"><?= $po['status'] ?></span>
                                        </td>
                                    </tr>
                                    <?php if($po['keterangan']): ?>
                                    <tr>
                                        <td class="text-muted">Ket. Dari/Bagi Sup.</td>
                                        <td><div class="alert alert-light p-2 mb-0" style="font-size:12px;"><?= nl2br(htmlspecialchars($po['keterangan'])) ?></div></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <!-- Card Action Penerimaan-->
                        <?php if($po['status'] == 'Dikirim' && $auth->hasPermission('inventory_atk', 'process')): ?>
                        <div class="card shadow-sm border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title text-white mb-0">Aksi Penerimaan Barang</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small"><strong>Harap Diperhatikan:</strong> Dengan mengklik tombol proses di bawah ini, seluruh stok aktual yang dikirimkan oleh PT BST akan segera disinkronisasi (dimasukkan) secara otomatis bertambah ke Gudang Inventory master ATK Anda yang asli.</p>
                                
                                <form method="POST" id="formTerimaPO">
                                    <input type="hidden" name="proses_terima" value="1">
                                    <button type="button" class="btn btn-success btn-block btn-lg" onclick="terimaPO()">
                                        <i class="fas fa-clipboard-check"></i> Terima Barang & Update Stok
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Rincian Fisik Barang & Pengiriman</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th width="5%">No</th>
                                                <th>Nama Barang Dipesan</th>
                                                <th class="text-center text-primary">Kuantitas Pesanan</th>
                                                <th class="text-center text-success">Kuantitas Real Dikirim</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1; 
                                            foreach($details as $item): 
                                                $bg_kirim = ($item['qty_pesan'] > $item['qty_dikirim']) ? 'bg-warning text-dark' : 'bg-success text-white';
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['nama_barang']) ?></strong><br>
                                                    <small class="text-muted">SKU/Kode: <?= htmlspecialchars($item['kode_barang']) ?></small>
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    <?= $item['qty_pesan'] ?> <?= htmlspecialchars($item['satuan']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge py-2 px-3 <?= $bg_kirim ?>" style="font-size:15px;">
                                                        <?= $po['status'] == 'Pending' ? '-' : $item['qty_dikirim'] ?> <?= htmlspecialchars($item['satuan']) ?>
                                                    </span>
                                                    <?php if($item['qty_pesan'] > $item['qty_dikirim'] && $po['status'] != 'Pending'): ?>
                                                        <br><small class="text-danger mt-1 d-block"><i class="fas fa-exclamation-circle"></i> Kurang pengiriman (stok kurang)</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include __DIR__ . '/../../layouts/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <!-- Sertakan Modal Konfirmasi Universal -->
    <?php include __DIR__ . '/../../layouts/confirm_modal.php'; ?>
    
    <script>
    function terimaPO() {
        if(typeof showConfirm === 'function') {
            showConfirm('Konfirmasi Penerimaan', 'Apakah Anda yakin barang yang diterima secara fisik sudah sesuai?\n\nProses ini akan mencatatkan stok secara permanen ke Gudang Master ATK.', function() {
                $('#formTerimaPO').submit();
            });
        } else {
            if(confirm('Apakah Anda yakin barang yang diterima secara fisik sudah sesuai?\n\nProses ini akan mencatatkan stok secara permanen.')) {
                $('#formTerimaPO').submit();
            }
        }
    }
    </script>
</body>
</html>
