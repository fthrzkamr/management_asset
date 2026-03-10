<?php
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../../login.php"); exit(); }
$auth->requirePermission('pt_bst', 'update');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_barang = isset($_POST['id_barang']) ? (int)$_POST['id_barang'] : 0;
    $stok_baru = isset($_POST['stok_baru']) ? (int)$_POST['stok_baru'] : 0;
    
    if ($stok_baru < 0) {
        $_SESSION['error'] = "Stok tidak boleh bernilai negatif.";
        header("Location: dashboard.php");
        exit();
    }
    
    // Cek apakah barang sudah ada di bst_stok
    $cek = mysqli_query($conn, "SELECT id_stok_bst, quantity FROM bst_stok WHERE id_barang = $id_barang");
    
    if(mysqli_num_rows($cek) > 0) {
        // Update
        $q = "UPDATE bst_stok SET quantity = $stok_baru, updated_at = NOW() WHERE id_barang = $id_barang";
        if(mysqli_query($conn, $q)) {
            $_SESSION['success'] = "Berhasil memperbarui stok milik PT BST!";
        } else {
            $_SESSION['error'] = "Gagal mengubah stok: " . mysqli_error($conn);
        }
    } else {
        // Insert
        $q = "INSERT INTO bst_stok (id_barang, quantity) VALUES ($id_barang, $stok_baru)";
        if(mysqli_query($conn, $q)) {
            $_SESSION['success'] = "Stok awal PT BST mulai dicatat!";
        } else {
            $_SESSION['error'] = "Gagal menambah master stok PT BST: " . mysqli_error($conn);
        }
    }
}
header("Location: dashboard.php");
exit();
?>
