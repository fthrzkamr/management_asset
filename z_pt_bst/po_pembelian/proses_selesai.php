<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst_po_pembelian', 'process');

$id_pembelian = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_pembelian == 0) { header("Location: dashboard.php"); exit(); }

mysqli_begin_transaction($conn);
try {
    // Kunci Baris Pembelian (Lock for Update)
    $q_hdr = mysqli_query($conn, "SELECT status, nomor_po FROM bst_pembelian WHERE id_pembelian = $id_pembelian FOR UPDATE");
    if(mysqli_num_rows($q_hdr) == 0) {
        throw new Exception("Data Pembelian tidak ditemukan.");
    }
    
    $hdr = mysqli_fetch_assoc($q_hdr);
    if($hdr['status'] == 'Selesai') {
        throw new Exception("Dokumen PO {$hdr['nomor_po']} ini sudah berstatus Selesai sebelumnya!");
    }

    // Ubah status ke diselesaikan agar tidak dieksekusi 2x
    if (!mysqli_query($conn, "UPDATE bst_pembelian SET status = 'Selesai' WHERE id_pembelian = $id_pembelian")) {
        throw new Exception("Gagal mengubah status PO Pembelian: " . mysqli_error($conn));
    }

    // Ambil semua item detail di dalam PO tersebut
    $q_dtl = mysqli_query($conn, "SELECT id_barang, qty FROM bst_pembelian_detail WHERE id_pembelian = $id_pembelian");
    
    // Perulangan untuk menambah stok ke tabel bst_stok
    while ($dtl = mysqli_fetch_assoc($q_dtl)) {
        $id_brg = $dtl['id_barang'];
        $qty_tambah = $dtl['qty'];

        // Cek apakah item ini sudah ada di rak stok BST
        $cek_stok = mysqli_query($conn, "SELECT id_stok_bst, quantity FROM bst_stok WHERE id_barang = $id_brg FOR UPDATE");
        
        if (mysqli_num_rows($cek_stok) > 0) {
            // Sudah pernah ada, maka UPDATE kuantitas berjalan akumulatif (+)
            $sql_upd = "UPDATE bst_stok SET quantity = quantity + $qty_tambah, updated_at = NOW() WHERE id_barang = $id_brg";
            if (!mysqli_query($conn, $sql_upd)) {
                throw new Exception("Gagal mengakumulasi stok milik barang ID:$id_brg");
            }
        } else {
            // Belum pernah ada, maka INSERT baris pencatatan awal stok BST
            $sql_ins = "INSERT INTO bst_stok (id_barang, quantity) VALUES ($id_brg, $qty_tambah)";
            if (!mysqli_query($conn, $sql_ins)) {
                throw new Exception("Gagal mencatat sisa kardus stok awal milik barang ID:$id_brg");
            }
        }
    }

    // Rampungkan transaksinya
    mysqli_commit($conn);
    $_SESSION['success'] = "Luar biasa! Seluruh produk dari PO Pembelian " . $hdr['nomor_po'] . " tersebut berhasil diserahterimakan dan secara otomatis dijumlahkan pada Gudang Stok PT BST!";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Terjadi Kesalahan Kritis: " . $e->getMessage();
}

header("Location: detail.php?id=" . $id_pembelian);
exit();
?>
