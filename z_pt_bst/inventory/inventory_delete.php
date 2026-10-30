<?php
require '../dist/config/config.php';
require '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('pt_bst_inventory', 'delete');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    if (mysqli_query($conn, "DELETE FROM bst_inventory WHERE id_barang_bst = $id")) {
        echo "<script>alert('Barang BST berhasil dihapus permanen!'); window.location.href='dashboard.php';</script>";
        // Note: Tabel bst_stok akan ikut terhapus berkat aturan ON DELETE CASCADE di database.
    } else {
        echo "<script>alert('Gagal menghapus: " . mysqli_error($conn) . "'); window.location.href='dashboard.php';</script>";
    }
} else {
    echo "<script>window.location.href='dashboard.php';</script>";
}
