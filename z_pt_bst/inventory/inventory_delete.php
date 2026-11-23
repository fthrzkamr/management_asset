<?php
require '../dist/config/config.php';
require_once __DIR__ . '/../../dist/config/flash_helper.php';
require '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('pt_bst_inventory', 'delete');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    if (mysqli_query($conn, "DELETE FROM bst_inventory WHERE id_barang_bst = $id")) {
        flashModalPage("Barang BST berhasil dihapus permanen!", 'success', 'dashboard.php');
        // Note: Tabel bst_stok akan ikut terhapus berkat aturan ON DELETE CASCADE di database.
    } else {
        flashModalPage("Gagal menghapus: " . mysqli_error($conn) , 'error', 'dashboard.php');
    }
} else {
    echo "<script>window.location.href='dashboard.php';</script>";
}
