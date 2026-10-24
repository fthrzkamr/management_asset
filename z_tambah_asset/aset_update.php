<?php
// filepath: c:\xampp\htdocs\asset_dfm\aset_update.php
include('../dist/config/config.php');
require_once('../dist/config/SentinelDeskSync.php');
require_once('../dist/config/image_helper.php');
date_default_timezone_set('Asia/Jakarta');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='login.php';</script>";
    exit;
}

if (!isset($_POST['submit'])) {
    header("Location: dashboard.php");
    exit;
}

// Ambil kode asset lama dan foto lama
$kode_aset_lama = mysqli_real_escape_string($conn, $_POST['kode_aset_lama']);
$foto_lama = mysqli_real_escape_string($conn, $_POST['foto_lama'] ?? '');

// Input dari form dengan escaping untuk keamanan
$kategori_aset    = mysqli_real_escape_string($conn, $_POST['kategori_aset'] ?? '');
$lokasi          = mysqli_real_escape_string($conn, $_POST['lokasi'] ?? '');
$lokasi_detail   = mysqli_real_escape_string($conn, $_POST['lokasi_detail'] ?? '');
$nama_aset       = mysqli_real_escape_string($conn, $_POST['nama_aset'] ?? '');
$merk            = mysqli_real_escape_string($conn, $_POST['merk'] ?? '');
$spesifikasi     = mysqli_real_escape_string($conn, $_POST['spesifikasi'] ?? '');
$jumlah          = (int)($_POST['jumlah'] ?? 1);
$satuan          = mysqli_real_escape_string($conn, $_POST['satuan'] ?? 'unit');
$tanggal_pengadaan = !empty($_POST['tanggal_pengadaan']) ? $_POST['tanggal_pengadaan'] : null;



$user_pemakai   = !empty($_POST['user']) ? mysqli_real_escape_string($conn, $_POST['user']) : null;
$kondisi        = mysqli_real_escape_string($conn, $_POST['kondisi'] ?? 'Baik');
$keterangan     = !empty($_POST['keterangan']) ? mysqli_real_escape_string($conn, $_POST['keterangan']) : null;

// New fields
$nilai_pengadaan_satuan = (float)($_POST['nilai_pengadaan_satuan'] ?? 0);
$total_nilai_pengadaan  = (float)($_POST['total_nilai_pengadaan'] ?? 0);
$informasi_barang       = mysqli_real_escape_string($conn, $_POST['informasi_barang'] ?? '');
$status_barang          = in_array($_POST['status_barang'] ?? '', ['Asset','Sewa']) ? $_POST['status_barang'] : 'Asset';
$status_kepemilikan     = in_array($_POST['status_kepemilikan'] ?? '', ['DFM','MPF','PLM','DUI']) ? $_POST['status_kepemilikan'] : null;

// Update user_info dengan user yang sedang melakukan edit
$user_info      = (int)$_SESSION['id_user'];
$updated_at     = date("Y-m-d H:i:s");

// Validasi data required
if (empty($kategori_aset) || empty($lokasi) || empty($nama_aset) || empty($satuan) || empty($kondisi)) {
    echo "<script>alert('Data yang wajib diisi belum lengkap!'); history.back();</script>";
    exit;
}

// Cek apakah asset dengan kode lama ada
$check_query = mysqli_query($conn, "SELECT kode_aset FROM asset_dfm WHERE kode_aset = '$kode_aset_lama' LIMIT 1");
if (!$check_query || mysqli_num_rows($check_query) == 0) {
    echo "<script>alert('Asset tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}

// TAMBAHAN: Handle upload foto asset baru dengan optimasi WebP & resize otomatis
$upload_dir = '../uploads/asset/';
$foto_asset = $foto_lama; // Default menggunakan foto lama
$foto_baru_diupload = false;

if (isset($_FILES['foto_asset']) && $_FILES['foto_asset']['error'] == UPLOAD_ERR_OK && !empty($_FILES['foto_asset']['name'])) {
    $uploadResult = optimizeAndSaveAssetPhoto($_FILES['foto_asset'], $upload_dir, 'asset_it_');
    if ($uploadResult['success']) {
        $foto_asset = $uploadResult['filename'];
        $foto_baru_diupload = true;
    } else {
        $errMsg = addslashes($uploadResult['error'] ?: 'Gagal upload foto baru.');
        echo "<script>alert('{$errMsg}'); history.back();</script>";
        exit;
    }
}

// Update data asset dengan field baru
$sql = "UPDATE asset_dfm SET
            kategori_aset = '$kategori_aset',
            lokasi = '$lokasi',
            lokasi_detail = '$lokasi_detail',
            nama_aset = '$nama_aset',
            merk = '$merk',
            spesifikasi = '$spesifikasi',
            jumlah = '$jumlah',
            satuan = '$satuan',
            tanggal_pengadaan = " . ($tanggal_pengadaan ? "'$tanggal_pengadaan'" : "NULL") . ",
            foto_asset = '" . mysqli_real_escape_string($conn, $foto_asset) . "',
            user = " . ($user_pemakai ? "'$user_pemakai'" : "NULL") . ",
            kondisi = '$kondisi',
            status_barang = '$status_barang',
            status_kepemilikan = " . ($status_kepemilikan ? "'$status_kepemilikan'" : "NULL") . ",
            keterangan = " . ($keterangan ? "'$keterangan'" : "NULL") . ",
            nilai_pengadaan_satuan = '$nilai_pengadaan_satuan',
            total_nilai_pengadaan = '$total_nilai_pengadaan',
            informasi_barang = '$informasi_barang',
            user_info = '$user_info',
            updated_at = '$updated_at'
        WHERE kode_aset = '$kode_aset_lama'";

$update = mysqli_query($conn, $sql);

if ($update) {
    // Hapus foto lama jika berhasil ganti foto baru
    if ($foto_baru_diupload && !empty($foto_lama) && $foto_lama !== $foto_asset) {
        $old_photo_path = $upload_dir . $foto_lama;
        if (file_exists($old_photo_path)) {
            @unlink($old_photo_path);
        }
    }

    // Best-effort push to SentinelDesk - see SentinelDeskSync.php. Never
    // blocks or alters this success response even if it fails.
    sentineldesk_sync_asset($kode_aset_lama, $kondisi, $status_barang, $user_pemakai, $lokasi, $nama_aset, $merk, $spesifikasi, $lokasi_detail);

    $success_message = "Data asset berhasil diupdate!";
    
    if ($foto_baru_diupload) {
        $success_message .= "\\nFoto baru: $foto_asset";
    }
    
    echo "<script>alert('$success_message'); window.location.href='dashboard.php?status=updated';</script>";
} else {
    // Jika gagal update dan ada foto baru yang sudah diupload, hapus foto baru
    if ($foto_baru_diupload && !empty($foto_asset)) {
        $new_photo_path = $upload_dir . $foto_asset;
        if (file_exists($new_photo_path)) {
            unlink($new_photo_path);
        }
    }
    
    // Tampilkan error detail untuk debugging
    $error = mysqli_error($conn);
    echo "<script>alert('Gagal update data: $error'); history.back();</script>";
}
?>