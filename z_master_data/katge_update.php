<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_kategori_asset', 'update');

if (isset($_POST['submit'])) {
    $id = $_POST['id_kategori'];
    $kategori = $_POST['kategori_asset'];
    $sub_kategori = $_POST['sub_kategori'];
    $kode = $_POST['kode_kategori'];

    $query = "UPDATE kategori_asset SET kategori_asset = '$kategori', sub_kategori = '$sub_kategori', kode_kategori = '$kode' WHERE id_kategori = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('Location: dashboard.php?update=success');
    } else {
        echo "Gagal update data!";
    }
}
?>
