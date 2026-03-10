<?php
include '../dist/config/config.php'; // pastikan file koneksi ada
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_divisi', 'delete');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Hapus data dari tabel divisi
    $query = "DELETE FROM divisi WHERE id_divisi = '$id'";
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
