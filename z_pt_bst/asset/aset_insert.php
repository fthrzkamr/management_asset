<?php
// filepath: c:\xampp\htdocs\asset_bst\aset_insert.php
include '../dist/config/config.php';
date_default_timezone_set('Asia/Jakarta');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='../../login.php';</script>";
    exit;
}

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
$kondisi        = mysqli_real_escape_string($conn, $_POST['kondisi'] ?? 'Baru');
$keterangan     = !empty($_POST['keterangan']) ? mysqli_real_escape_string($conn, $_POST['keterangan']) : null;

// New fields
$nilai_pengadaan_satuan = (float)($_POST['nilai_pengadaan_satuan'] ?? 0);
$total_nilai_pengadaan  = (float)($_POST['total_nilai_pengadaan'] ?? 0);
$informasi_barang       = mysqli_real_escape_string($conn, $_POST['informasi_barang'] ?? '');

// User info dari session
$user_info      = (int)$_SESSION['id_user'];
$created_at     = date("Y-m-d H:i:s");
$updated_at     = $created_at;

// Validasi user_info tidak boleh kosong
if (empty($user_info)) {
    echo "<script>alert('Error: User info tidak valid. Silakan login kembali.'); window.location.href='../../login.php';</script>";
    exit;
}

// Handle upload foto asset
$foto_asset = '';
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
    
    // Upload file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $foto_asset = $new_filename;

        // Resize gambar jika terlalu besar (cek GD extension dulu)
        if (extension_loaded('gd')) {
            resizeImage($upload_path, $upload_path, 1200, 800);
        } else {
            // Jika GD tidak tersedia, lewati resize untuk mencegah fatal error
            // Anda disarankan mengaktifkan ekstensi GD di php.ini (XAMPP: C:\\xampp\\php\\php.ini)
        }
    } else {
        echo "<script>alert('Gagal upload foto'); history.back();</script>";
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
$table_name = 'asset_bst';
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

// --- Insert ke tabel asset_bst
$sql = "INSERT INTO asset_bst (
    kode_aset, kategori_aset, lokasi, lokasi_detail, nama_aset, merk, spesifikasi,
    jumlah, satuan, tanggal_pengadaan, foto_asset,
    user, kondisi, keterangan, user_info, created_at, updated_at,
    nilai_pengadaan_satuan, total_nilai_pengadaan, informasi_barang
) VALUES (
    '$kode_aset', '$kategori_nama', '$lokasi_nama', '$lokasi_detail', '$nama_aset', '$merk', '$spesifikasi',
    '$jumlah', '$satuan', " . ($tanggal_pengadaan ? "'$tanggal_pengadaan'" : "NULL") . ",
    " . ($foto_asset ? "'$foto_asset'" : "NULL") . ",
    " . ($user_pemakai ? "'$user_pemakai'" : "NULL") . ", '$kondisi', " . ($keterangan ? "'$keterangan'" : "NULL") . ",
    '$user_info', '$created_at', '$updated_at',
    '$nilai_pengadaan_satuan', '$total_nilai_pengadaan', '$informasi_barang'
)";

$insert = mysqli_query($conn, $sql);

if ($insert) {
    echo "<script>alert('Data aset berhasil disimpan dengan kode $kode_aset\\nFoto: $foto_asset'); window.location.href='dashboard.php';</script>";
} else {
    // Jika gagal dan ada foto yang sudah diupload, hapus foto
    if ($foto_asset && file_exists($upload_dir . $foto_asset)) {
        unlink($upload_dir . $foto_asset);
    }
    
    $error = mysqli_error($conn);
    echo "<script>alert('Gagal simpan data: $error'); history.back();</script>";
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
