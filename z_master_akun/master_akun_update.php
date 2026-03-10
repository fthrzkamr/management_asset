<?php
include '../dist/config/config.php';

if (isset($_POST['submit'])) {
    $id = $_POST['id_ma'];
    $no_akun = $_POST['no_akun'];
    $nama_akun = $_POST['nama_akun'];
    $updated_by = $_POST['updated_by'];
    $updated_at = date('Y-m-d');

    $query = "UPDATE master_akun SET no_akun = '$no_akun', nama_akun = '$nama_akun', updated_at = '$updated_at', updated_by = '$updated_by' WHERE id_ma = '$id'";
    $result = mysqli_query($conn, $query);

    if ($result) {
        header('Location: dashboard.php?update=success');
    } else {
        echo "Gagal update data: " . mysqli_error($conn);
    }
}
?>