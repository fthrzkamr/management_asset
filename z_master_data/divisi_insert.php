<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_divisi', 'create');

if (isset($_POST['submit'])) {
    // Ambil data dari form
    $nama_divisi = trim($_POST['nama_divisi']);

    // Validasi sederhana
    if ($nama_divisi == "") {
        $_SESSION['error'] = 'Semua field wajib diisi!';
        header("Location: divisi_add.php");
        exit;
    }

    // Cek apakah nama sudah ada
    $cek = mysqli_query($conn, "SELECT * FROM divisi WHERE nama_divisi = '$nama_divisi'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = 'Nama divisi sudah terdaftar!';
        header("Location: divisi_add.php");
        exit;
    }

    // Simpan ke database
    $query = "INSERT INTO divisi (nama_divisi) VALUES ('$nama_divisi')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $_SESSION['success'] = 'Data berhasil disimpan!';
        header("Location: dashboard.php");
    } else {
        $_SESSION['error'] = 'Gagal menyimpan data!';
        header("Location: divisi_add.php");
    }
    exit;
}
?>
