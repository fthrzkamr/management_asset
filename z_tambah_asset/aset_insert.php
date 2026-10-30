<?php
// filepath: c:\xampp\htdocs\asset_dfm\aset_insert.php
include '../dist/config/config.php';
require_once '../dist/config/SentinelDeskSync.php';
require_once '../dist/config/image_helper.php';
date_default_timezone_set('Asia/Jakarta');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='login.php';</script>";
    exit;
}

// Cek izin (create)
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
$auth->requirePermission('management_asset', 'create', 'dashboard.php');

if (!isset($_POST['submit'])) {
    header("Location: aset_add.php");
    exit;
}

// Input dari form dengan escaping untuk keamanan
$kategori_id     = (int)$_POST['kategori_aset'];
$lokasi_id       = (int)$_POST['lokasi'];
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

// User info dari session
$user_info      = (int)$_SESSION['id_user'];
$created_at     = date("Y-m-d H:i:s");
$updated_at     = $created_at;

// Validasi user_info tidak boleh kosong
if (empty($user_info)) {
    echo "<script>alert('Error: User info tidak valid. Silakan login kembali.'); window.location.href='login.php';</script>";
    exit;
}

// Handle upload foto asset dengan optimasi WebP & resize otomatis
$upload_dir = '../uploads/asset/';
$foto_asset = '';
if (isset($_FILES['foto_asset']) && $_FILES['foto_asset']['error'] == UPLOAD_ERR_OK && !empty($_FILES['foto_asset']['name'])) {
    $uploadResult = optimizeAndSaveAssetPhoto($_FILES['foto_asset'], $upload_dir, 'asset_it_');
    if ($uploadResult['success']) {
        $foto_asset = $uploadResult['filename'];
    } else {
        $errMsg = addslashes($uploadResult['error'] ?: 'Gagal upload foto aset.');
        echo "<script>alert('{$errMsg}'); history.back();</script>";
        exit;
    }
}

// --- Ambil kategori & lokasi dari tabel master
$qKat = mysqli_query($conn, "SELECT kode_kategori, kategori_asset FROM kategori_asset WHERE id_kategori='$kategori_id' LIMIT 1");
$qLok = mysqli_query($conn, "SELECT kode_lokasi, lokasi_asset FROM lokasi_asset WHERE id_lokasi='$lokasi_id' LIMIT 1");

if (!$qKat || !$qLok || mysqli_num_rows($qKat) == 0 || mysqli_num_rows($qLok) == 0) {
    echo "<script>alert('Kategori atau lokasi tidak valid!'); history.back();</script>";
    exit;
}

$rowKat = mysqli_fetch_assoc($qKat);
$rowLok = mysqli_fetch_assoc($qLok);

$kode_kategori = $rowKat['kode_kategori'];
$kategori_nama = mysqli_real_escape_string($conn, $rowKat['kategori_asset']);
$kode_lokasi   = $rowLok['kode_lokasi'];
$lokasi_nama   = mysqli_real_escape_string($conn, $rowLok['lokasi_asset']);

// --- Generate kode_aset dengan nomor urut global
if ($tanggal_pengadaan) {
    $tahun = date("y", strtotime($tanggal_pengadaan));
} else {
    $tahun = date("y");
}

// Ambil nomor urut terbesar
$table_name = 'asset_dfm';
$qAll = mysqli_query($conn, "SELECT kode_aset FROM $table_name");
$max_no = 0;

if ($qAll && mysqli_num_rows($qAll) > 0) {
    while ($row = mysqli_fetch_assoc($qAll)) {
        $kode = $row['kode_aset'];
        $parts = explode('-', $kode);
        
        if (count($parts) >= 2) {
            $middle_part = $parts[1];
            if (strlen($middle_part) >= 3) {
                $current_no = (int)substr($middle_part, -3);
                if ($current_no > $max_no) {
                    $max_no = $current_no;
                }
            }
        }
    }
}

$new_no = str_pad($max_no + 1, 3, '0', STR_PAD_LEFT);
$kode_aset = "{$kode_kategori}-{$tahun}{$new_no}-{$kode_lokasi}";

// --- Insert ke tabel asset_dfm
$sql = "INSERT INTO asset_dfm (
    kode_aset, kategori_aset, lokasi, lokasi_detail, nama_aset, merk, spesifikasi,
    jumlah, satuan, tanggal_pengadaan, foto_asset,
    user, kondisi, status_barang, status_kepemilikan, keterangan, user_info, created_at, updated_at,
    nilai_pengadaan_satuan, total_nilai_pengadaan, informasi_barang
) VALUES (
    '$kode_aset', '$kategori_nama', '$lokasi_nama', '$lokasi_detail', '$nama_aset', '$merk', '$spesifikasi',
    '$jumlah', '$satuan', " . ($tanggal_pengadaan ? "'$tanggal_pengadaan'" : "NULL") . ",
    " . ($foto_asset ? "'$foto_asset'" : "NULL") . ",
    " . ($user_pemakai ? "'$user_pemakai'" : "NULL") . ", '$kondisi', '$status_barang', " . ($status_kepemilikan ? "'$status_kepemilikan'" : "NULL") . ", " . ($keterangan ? "'$keterangan'" : "NULL") . ",
    '$user_info', '$created_at', '$updated_at',
    '$nilai_pengadaan_satuan', '$total_nilai_pengadaan', '$informasi_barang'
)";

$insert = mysqli_query($conn, $sql);

if ($insert) {
    // Best-effort push to SentinelDesk - see SentinelDeskSync.php. Never
    // blocks or alters this success response even if it fails.
    sentineldesk_sync_asset($kode_aset, $kondisi, $status_barang, $user_pemakai, $lokasi_nama, $nama_aset, $merk, $spesifikasi, $lokasi_detail);

    echo "<script>alert('Data aset berhasil disimpan dengan kode $kode_aset\\nFoto: $foto_asset'); window.location.href='dashboard.php';</script>";
} else {
    // Jika gagal dan ada foto yang sudah diupload, hapus foto
    if ($foto_asset && file_exists($upload_dir . $foto_asset)) {
        unlink($upload_dir . $foto_asset);
    }
    
    $error = mysqli_error($conn);
    echo "<script>alert('Gagal simpan data: $error'); history.back();</script>";
}
?>
