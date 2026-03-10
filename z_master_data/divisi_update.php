<?php
include '../dist/config/config.php';
include '../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_divisi', 'update');

if (isset($_POST['submit'])) {
    $id = $_POST['id_divisi'];
    $divisi = $_POST['nama_divisi'];

    $query = "UPDATE divisi SET nama_divisi = '$divisi' WHERE id_divisi = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('Location: dashboard.php?update=success');
    } else {
        echo "Gagal update data!";
    }
}
?>
