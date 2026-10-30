<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst_po_pembelian', 'create');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_po      = mysqli_real_escape_string($conn, $_POST['nomor_po']);
    $tanggal_po    = mysqli_real_escape_string($conn, $_POST['tanggal_po']);
    $nama_supplier = mysqli_real_escape_string($conn, $_POST['nama_supplier']);
    $id_user       = $_SESSION['id_user'];

    $arr_id_barang = $_POST['id_barang'] ?? [];
    $arr_qty       = $_POST['qty'] ?? [];
    $arr_harga     = $_POST['harga_satuan'] ?? [];

    if (empty($arr_id_barang)) {
        $_SESSION['error'] = "Item barang pembelian tidak boleh kosong.";
        header("Location: add.php");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        // Insert Header
        $sql_hdr = "INSERT INTO bst_pembelian (nomor_po, tanggal_po, nama_supplier, status, created_by) 
                    VALUES ('$nomor_po', '$tanggal_po', '$nama_supplier', 'Pending', $id_user)";
        if (!mysqli_query($conn, $sql_hdr)) {
            throw new Exception("Gagal menyimpan Header PO: " . mysqli_error($conn));
        }
        
        $id_pembelian = mysqli_insert_id($conn);

        // Insert Detail
        $count = count($arr_id_barang);
        for ($i=0; $i<$count; $i++) {
            $id_brg = (int)$arr_id_barang[$i];
            $qty    = (int)$arr_qty[$i];
            
            $hrg_str = str_replace('.', '', $arr_harga[$i] ?? '0');
            $hrg    = (float)$hrg_str;

            if ($id_brg > 0 && $qty > 0) {
                $sql_dtl = "INSERT INTO bst_pembelian_detail (id_pembelian, id_barang, qty, harga_satuan) 
                            VALUES ($id_pembelian, $id_brg, $qty, $hrg)";
                if (!mysqli_query($conn, $sql_dtl)) {
                    throw new Exception("Gagal menyimpan item detail barang ID($id_brg)!");
                }
            }
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "<b>$nomor_po</b> berhasil dibuat! Silakan tandai 'Terima' pada menu utama jika barang-barang tersebut telah sampai (agar masuk stok).";
        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: add.php");
        exit();
    }
} else {
    header("Location: dashboard.php");
}
?>
