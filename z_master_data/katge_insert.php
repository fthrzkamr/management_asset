<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('master_kategori_asset', 'create');

if (isset($_POST['submit'])) {
    // Ambil data dari form
    $kategori_asset = trim($_POST['kategori_asset']);
    $sub_kategori = trim($_POST['sub_kategori']);
    $kode_kategori = trim($_POST['kode_kategori']);

    // Validasi sederhana
    if ($kategori_asset == "" || $sub_kategori == "" || $kode_kategori == "") {
        $_SESSION['error'] = 'Semua field wajib diisi!';
        header("Location: katge_add.php");
        exit;
    }

    // Cek apakah kode sudah ada
    $cek = mysqli_query($conn, "SELECT * FROM kategori_asset WHERE kode_kategori = '$kode_kategori'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = 'Kode kategori sudah terdaftar!';
        header("Location: katge_add.php");
        exit;
    }

    // Simpan ke database
    $query = "INSERT INTO kategori_asset (kategori_asset, sub_kategori, kode_kategori) VALUES ('$kategori_asset', '$sub_kategori', '$kode_kategori')";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $_SESSION['success'] = 'Data berhasil disimpan!';
        header("Location: dashboard.php");
    } else {
        $_SESSION['error'] = 'Gagal menyimpan data!';
        header("Location: katge_add.php");
    }
    exit;
}
?>
