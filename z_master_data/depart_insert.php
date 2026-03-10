<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_departemen', 'create');

if (isset($_POST['submit'])) {
    // Ambil data dari form
    $nama_departemen = trim($_POST['nama_departemen']);

    // Validasi sederhana
    if ($nama_departemen == "") {
        echo "<script>alert('Semua field wajib diisi!'); window.history.back();</script>";
        exit;
    }

    // Cek apakah nama sudah ada
    $cek = mysqli_query($conn, "SELECT * FROM departemen WHERE nama_departemen = '$nama_departemen'");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Nama departemen sudah terdaftar!'); window.history.back();</script>";
        exit;
    }

    // Simpan ke database
    $query = "INSERT INTO departemen (nama_departemen) VALUES ('$nama_departemen')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        echo "<script>alert('Data berhasil disimpan!'); window.location.href='dashboard.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan data!'); window.history.back();</script>";
    }
}
?>
