<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_lokasi_asset', 'create');

if (isset($_POST['submit'])) {
    // Ambil data dari form
    $lokasi_asset = trim($_POST['lokasi_asset']);
    $kode_lokasi = trim($_POST['kode_lokasi']);

    // Validasi sederhana
    if ($lokasi_asset == "" || $kode_lokasi == "") {
        $_SESSION['error'] = 'Semua field wajib diisi!';
        header("Location: lokasi_add.php");
        exit;
    }

    // Cek apakah kode sudah ada
    $cek = mysqli_query($conn, "SELECT * FROM lokasi_asset WHERE kode_lokasi = '$kode_lokasi'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = 'Kode Lokasi sudah terdaftar!';
        header("Location: lokasi_add.php");
        exit;
    }

    // Simpan ke database
    $query = "INSERT INTO lokasi_asset (lokasi_asset, kode_lokasi) VALUES ('$lokasi_asset', '$kode_lokasi')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $_SESSION['success'] = 'Data berhasil disimpan!';
        header("Location: dashboard.php");
    } else {
        $_SESSION['error'] = 'Gagal menyimpan data!';
        header("Location: lokasi_add.php");
    }
    exit;
}
?>
