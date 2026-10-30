<?php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header('Location: ../login.php'); exit(); }
$auth->requirePermission('master_akun', 'create', 'dashboard.php');


if (isset($_POST['submit'])) {
    // Retrieve data from the form
    $id_ma = mysqli_real_escape_string($conn, trim($_POST['id_ma']));
    $no_akun = mysqli_real_escape_string($conn, trim($_POST['no_akun']));
    $nama_akun = mysqli_real_escape_string($conn, trim($_POST['nama_akun']));
    $kategori_akun = mysqli_real_escape_string($conn, trim($_POST['kategori_akun']));
    $created_by = isset($_SESSION['nama_user']) ? $_SESSION['nama_user'] : 'Admin';
    $created_at = date('Y-m-d');

    // Simple validation
    if ($id_ma == "" || $no_akun == "" || $nama_akun == "" || $kategori_akun == "") {
        echo "<script>alert('Semua field wajib diisi!'); window.history.back();</script>";
        exit;
    }

    // Check if the account number or id_ma already exists
    $cek = mysqli_query($conn, "SELECT * FROM master_akun WHERE no_akun = '$no_akun' OR id_ma = '$id_ma'");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('ID atau Nomor Akun sudah terdaftar!'); window.history.back();</script>";
        exit;
    }

    // Insert into the database (note corrected column name created_by)
    $query = "INSERT INTO master_akun (id_ma, no_akun, nama_akun, kategori_akun, created_at, created_by) VALUES ('$id_ma', '$no_akun', '$nama_akun', '$kategori_akun', '$created_at', '$created_by')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        echo "<script>alert('Data berhasil disimpan!'); window.location.href='dashboard.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan data: ".mysqli_error($conn)."'); window.history.back();</script>";
    }
}
?>