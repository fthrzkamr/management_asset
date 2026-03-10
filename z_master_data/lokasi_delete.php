<?php
include '../dist/config/config.php'; // pastikan file conn ada
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_lokasi_asset', 'delete');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Hapus data dari tabel kategori_asset
    $query = "DELETE FROM lokasi_asset WHERE id_lokasi = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        // Berhasil dihapus
        header("Location: dashboard.php?status=deleted");
    } else {
        // Gagal hapus
        echo "Gagal menghapus data: " . mysqli_error($conn);
    }
} else {
    echo "ID tidak ditemukan.";
}
?>
