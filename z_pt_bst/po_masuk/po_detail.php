<?php
require __DIR__ . '/../../dist/config/config.php';
require __DIR__ . '/../../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('pt_bst', 'read');

$id_po = isset($_GET['id']) ? intval($_GET['id']) : 0;

$q_po = mysqli_query($conn, "SELECT * FROM bst_po WHERE id_po = $id_po");
$po = mysqli_fetch_assoc($q_po);

if (!$po) {
    echo "<script>alert('Data PO tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}

// Ambil detail PO dan bandingkan dengan ketersediaan stok PT BST
$q_detail = mysqli_query($conn, "
    SELECT 
        d.*, 
        i.kode_barang, 
        i.nama_barang, 
        i.satuan,
        COALESCE(s.quantity, 0) as stok_tersedia
    FROM bst_po_detail d
    JOIN inventory_atk i ON d.id_barang_bst = i.id_barang
    LEFT JOIN bst_stok s ON i.id_barang = s.id_barang
    WHERE d.id_po = $id_po
");
$details = [];
$can_process = true; // Flag untuk mencegat proses bila stok kurang
while($d = mysqli_fetch_assoc($q_detail)) {
    if ($d['stok_tersedia'] < $d['qty_pesan']) {
        // $can_process = false; // Disable pembatas stok sesuai request
    }
    $details[] = $d;
}

// Handler Aksi PO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $auth->requirePermission('pt_bst', 'process');
    $action = $_POST['action'];
    $keterangan_admin = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');

    if ($action == 'proses_kirim' && $can_process && $po['status'] == 'Pending') {
        mysqli_begin_transaction($conn);
        try {
            // 1. Update status PO jadi Dikirim
            mysqli_query($conn, "UPDATE bst_po SET status = 'Dikirim', keterangan = '$keterangan_admin' WHERE id_po = $id_po");
            
            // 2. Loop detail PO: potong stok PT BST, alokasikan qty_dikirim
            foreach ($details as $item) {
                $id_bst = $item['id_barang_bst']; // Ini mewakili id_barang dari ATK
                $qty = $item['qty_pesan'];
                
                // Cek apakah stok fisik (barang ini) sudah teregistrasi di Gudang BST?
                $cek_stok = mysqli_query($conn, "SELECT id_stok_bst FROM bst_stok WHERE id_barang = $id_bst");
                if (mysqli_num_rows($cek_stok) > 0) {
                    // Gunakan GREATEST(0, ...) agar tidak minus
                    mysqli_query($conn, "UPDATE bst_stok SET quantity = GREATEST(0, quantity - $qty), updated_at = NOW() WHERE id_barang = $id_bst");
                } else {
                    // Jika belum ada, stok baru adalah 0 (karena pesanan mengeluarkan apa yang tidak ada)
                    mysqli_query($conn, "INSERT INTO bst_stok (id_barang, quantity) VALUES ($id_bst, 0)");
                }
                
                // Set qty yang dikirim sama dengan qty pesanan (asumsi full fulfillment)
                mysqli_query($conn, "UPDATE bst_po_detail SET qty_dikirim = $qty WHERE id_detail = " . $item['id_detail']);
            }
            
            mysqli_commit($conn);
            echo "<script>alert('PO Berhasil di-Kirim. Stok BST telah dipotong!'); window.location.href='dashboard.php';</script>";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script>alert('Gagal memproses PO: " . $e->getMessage() . "');</script>";
        }
    } 
    elseif ($action == 'tolak' && $po['status'] == 'Pending') {
        mysqli_query($conn, "UPDATE bst_po SET status = 'Ditolak', keterangan = '$keterangan_admin' WHERE id_po = $id_po");
        echo "<script>alert('Pesanan PO Ditolak.'); window.location.href='dashboard.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Detail PO Masuk - Aplikasi Asset Management</title>

</head>
<body>
    <div class="main-wrapper">
        <?php include __DIR__ . '/../../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Detail Pesanan PO dari Gudang ATK</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Daftar PO</a></li>
                                <li class="breadcrumb-item active">Proses Pesanan</li>
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
                    <div class="col-md-4">
                        <div class="card shadow-sm">
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
                                        <td class="text-muted">Tanggal</td>
                                        <td><?= date('d M Y', strtotime($po['tanggal_po'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Status</td>
                                        <td>
                                            <?php
                                            $badge = $po['status'] == 'Selesai' ? 'badge-success' : ($po['status'] == 'Dikirim' ? 'badge-primary' : ($po['status'] == 'Ditolak' ? 'badge-danger' : 'badge-warning'));
                                            ?>
                                            <span class="badge <?= $badge ?>"><?= $po['status'] ?></span>
                                        </td>
                                    </tr>
                                    <?php if($po['keterangan']): ?>
                                    <tr>
                                        <td class="text-muted">Keterangan</td>
                                        <td><div class="alert alert-light p-2 mb-0" style="font-size:12px;"><?= nl2br(htmlspecialchars($po['keterangan'])) ?></div></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <?php if($po['status'] == 'Pending' && $auth->hasPermission('pt_bst', 'process')): ?>
                        <div class="card shadow-sm border-primary pb-0">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title text-white mb-0">Aksi Proses PO</h5>
                            </div>
                            <div class="card-body">
                                <!-- <?php if(!$can_process): ?>...<?php endif; ?> dihilangkan -->

                                <form method="POST" id="formProsesPO">
                                    <input type="hidden" name="action" id="actionValue" value="">
                                    <div class="form-group">
                                        <label>Catatan/Keterangan (Opsi)</label>
                                        <textarea name="keterangan" id="keteranganValue" class="form-control" rows="2" placeholder="Alasan penolakan / catatan pengiriman..."></textarea>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-block mb-2" onclick="tolakPO()">
                                        <i class="fas fa-times-circle"></i> Tolak PO
                                    </button>
                                    <button type="button" class="btn btn-success btn-block" onclick="setujuiPO()">
                                        <i class="fas fa-truck"></i> Setujui & Kirim Barang
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Rincian Barang ATK (Yang Dipesan)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th width="5%">No</th>
                                                <th>Nama Barang / Item</th>
                                                <th class="text-center">Pesanan</th>
                                                <th class="text-center">Sisa Stok BST Anda</th>
                                                <th class="text-center">Status Pemenuhan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1; 
                                            foreach($details as $item): 
                                                $shortage = $item['stok_tersedia'] - $item['qty_pesan'];
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['nama_barang']) ?></strong><br>
                                                    <small class="text-muted">Kode: <?= htmlspecialchars($item['kode_barang']) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-primary py-1 px-2" style="font-size:14px;"><?= $item['qty_pesan'] ?> <?= htmlspecialchars($item['satuan']) ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?= $item['stok_tersedia'] ?> <?= htmlspecialchars($item['satuan']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if($shortage < 0): ?>
                                                        <span class="text-danger font-weight-bold"><i class="fas fa-times"></i> Kurang <?= abs($shortage) ?> <?= $item['satuan'] ?>!</span>
                                                    <?php else: ?>
                                                        <span class="text-success font-weight-bold"><i class="fas fa-check"></i> Cukup</span>
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
    function setujuiPO() {
        if(typeof showConfirm === 'function') {
            showConfirm('Konfirmasi Persetujuan', 'Kirimkan barang ke Gudang ATK?\n\nStok internal Anda akan otomatis dikurangi sejumlah pesanan ini sekalipun akan menjadi minus.', function() {
                $('#actionValue').val('proses_kirim');
                $('#formProsesPO').submit();
            });
        } else {
            if(confirm('Kirimkan barang ke Gudang ATK? Stok internal Anda akan otomatis dikurangi sejumlah pesanan ini sekalipun akan menjadi minus.')) {
                $('#actionValue').val('proses_kirim');
                $('#formProsesPO').submit();
            }
        }
    }

    function tolakPO() {
        var catatan = $('#keteranganValue').val();
        if(catatan.trim() === '') {
            alert('Mohon isi kolom keterangan/catatan kenapa pesanan ini ditolak.');
            $('#keteranganValue').focus();
            return;
        }
        
        if(typeof showConfirm === 'function') {
            showConfirm('Konfirmasi Penolakan', 'Yakin ingin menolak pesanan PO ini?', function() {
                $('#actionValue').val('tolak');
                $('#formProsesPO').submit();
            });
        } else {
             if(confirm('Yakin ingin menolak pesanan PO ini?')) {
                $('#actionValue').val('tolak');
                $('#formProsesPO').submit();
             }
        }
    }
    </script>
</body>
</html>
