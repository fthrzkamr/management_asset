<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
date_default_timezone_set('Asia/Jakarta');

// Cek session login

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='../login.php';</script>";
    exit;
}

// Check permission
$auth->requirePermission('management_asset_ga', 'update');

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
$kondisi        = mysqli_real_escape_string($conn, $_POST['kondisi'] ?? 'Baru');
$keterangan     = !empty($_POST['keterangan']) ? mysqli_real_escape_string($conn, $_POST['keterangan']) : null;

// New fields
$nilai_pengadaan_satuan = (float)($_POST['nilai_pengadaan_satuan'] ?? 0);
$total_nilai_pengadaan  = (float)($_POST['total_nilai_pengadaan'] ?? 0);
$informasi_barang       = mysqli_real_escape_string($conn, $_POST['informasi_barang'] ?? '');

// Update user_info dengan user yang sedang melakukan edit
$user_info      = (int)$_SESSION['id_user'];
$updated_at     = date("Y-m-d H:i:s");

// Validasi data required
if (empty($kategori_aset) || empty($lokasi) || empty($nama_aset) || empty($satuan) || empty($kondisi)) {
    echo "<script>alert('Data yang wajib diisi belum lengkap!'); history.back();</script>";
    exit;
}

// Cek apakah asset dengan kode lama ada
$check_query = mysqli_query($conn, "SELECT kode_aset FROM asset_ga WHERE kode_aset = '$kode_aset_lama' LIMIT 1");
if (!$check_query || mysqli_num_rows($check_query) == 0) {
    echo "<script>alert('Asset tidak ditemukan!'); window.location.href='dashboard.php';</script>";
    exit;
}

// TAMBAHAN: Handle upload foto asset baru
$foto_asset = $foto_lama; // Default menggunakan foto lama
$foto_baru_diupload = false;

if (isset($_FILES['foto_asset']) && $_FILES['foto_asset']['error'] == UPLOAD_ERR_OK && !empty($_FILES['foto_asset']['name'])) {
    $upload_dir = '../uploads/asset/';
    
    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_tmp = $_FILES['foto_asset']['tmp_name'];
    $file_name = $_FILES['foto_asset']['name'];
    $file_size = $_FILES['foto_asset']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validasi ekstensi file
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_ext, $allowed_ext)) {
        echo "<script>alert('Format file tidak didukung. Gunakan: JPG, JPEG, PNG, GIF, atau WEBP'); history.back();</script>";
        exit;
    }
    
    // Validasi ukuran file (max 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        echo "<script>alert('Ukuran file terlalu besar. Maksimal 5MB'); history.back();</script>";
        exit;
    }
    
    // Generate nama file unik
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid()), 0, 6);
    $new_filename = "asset_{$timestamp}_{$random}.{$file_ext}";
    $upload_path = $upload_dir . $new_filename;
    
    // Upload file baru
    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Resize gambar jika terlalu besar
        resizeImage($upload_path, $upload_path, 1200, 800);
        
        $foto_asset = $new_filename;
        $foto_baru_diupload = true;
        
        // Hapus foto lama jika ada dan berbeda dengan foto baru
        if (!empty($foto_lama) && $foto_lama !== $new_filename) {
            $old_photo_path = $upload_dir . $foto_lama;
            if (file_exists($old_photo_path)) {
                unlink($old_photo_path);
            }
        }
    } else {
        echo "<script>alert('Gagal upload foto baru'); history.back();</script>";
        exit;
    }
}

// Update data asset dengan field baru
$sql = "UPDATE asset_ga SET
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
            keterangan = " . ($keterangan ? "'$keterangan'" : "NULL") . ",
            nilai_pengadaan_satuan = '$nilai_pengadaan_satuan',
            total_nilai_pengadaan = '$total_nilai_pengadaan',
            informasi_barang = '$informasi_barang',
            user_info = '$user_info',
            updated_at = '$updated_at'
        WHERE kode_aset = '$kode_aset_lama'";

$update = mysqli_query($conn, $sql);

if ($update) {
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

// Function untuk resize gambar
function resizeImage($source, $destination, $max_width, $max_height, $quality = 80) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // Jika ukuran sudah kecil, tidak perlu resize
    if ($width <= $max_width && $height <= $max_height) {
        return true;
    }
    
    // Hitung rasio
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
    
    // Buat image resource berdasarkan tipe
    switch ($mime) {
        case 'image/jpeg':
            $source_img = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $source_img = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $source_img = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $source_img = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$source_img) return false;
    
    // Buat canvas baru
    $new_img = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency untuk PNG dan GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($new_img, false);
        imagesavealpha($new_img, true);
        $transparent = imagecolorallocatealpha($new_img, 255, 255, 255, 127);
        imagefilledrectangle($new_img, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize
    imagecopyresampled($new_img, $source_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Simpan hasil resize
    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($new_img, $destination, $quality);
            break;
        case 'image/png':
            $result = imagepng($new_img, $destination, 9);
            break;
        case 'image/gif':
            $result = imagegif($new_img, $destination);
            break;
        case 'image/webp':
            $result = imagewebp($new_img, $destination, $quality);
            break;
        default:
            $result = false;
    }
    
    // Bersihkan memory
    imagedestroy($source_img);
    imagedestroy($new_img);
    
    return $result;
}
?>