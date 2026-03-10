<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_lokasi_asset', 'update');

if (isset($_POST['submit'])) {
    $id = $_POST['id_lokasi'];
    $kategori = $_POST['lokasi_asset'];
    $kode = $_POST['kode_lokasi'];

    $query = "UPDATE lokasi_asset SET lokasi_asset = '$kategori', kode_lokasi = '$kode' WHERE id_lokasi = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('Location: dashboard.php?update=success');
    } else {
        echo "Gagal update data!";
    }
}
?>
