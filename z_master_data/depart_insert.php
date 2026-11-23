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
        $_SESSION['error'] = 'Semua field wajib diisi!';
        header("Location: depart_add.php");
        exit;
    }

    // Cek apakah nama sudah ada
    $cek = mysqli_query($conn, "SELECT * FROM departemen WHERE nama_departemen = '$nama_departemen'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = 'Nama departemen sudah terdaftar!';
        header("Location: depart_add.php");
        exit;
    }

    // Simpan ke database
    $query = "INSERT INTO departemen (nama_departemen) VALUES ('$nama_departemen')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $_SESSION['success'] = 'Data berhasil disimpan!';
        header("Location: dashboard.php");
    } else {
        $_SESSION['error'] = 'Gagal menyimpan data!';
        header("Location: depart_add.php");
    }
    exit;
}
?>
