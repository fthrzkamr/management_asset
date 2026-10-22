<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\service_insert.php
include('../dist/config/config.php');
date_default_timezone_set('Asia/Jakarta');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='../login.php';</script>";
    exit;
}

if (!isset($_POST['submit'])) {
    header("Location: service_add.php");
    exit;
}

// Input dari form dengan escaping untuk keamanan
$no_service = mysqli_real_escape_string($conn, $_POST['no_service']);
$tanggal_service = $_POST['tanggal_service'];
$jenis_service = mysqli_real_escape_string($conn, $_POST['jenis_service']);
$keluhan = mysqli_real_escape_string($conn, $_POST['keluhan']);
$tindakan = !empty($_POST['tindakan']) ? mysqli_real_escape_string($conn, $_POST['tindakan']) : null;

// User info dari session
$user_input = (int)$_SESSION['id_user'];
$created_at = date("Y-m-d H:i:s");

// DEBUGGING: Tampilkan info file upload

// PERBAIKAN: Validasi array assets
if (!isset($_POST['assets']) || !is_array($_POST['assets']) || count($_POST['assets']) == 0) {
    echo "<script>alert('Pilih minimal 1 asset untuk service!'); history.back();</script>";
    exit;
}

// Validasi required fields
if (empty($tanggal_service) || empty($jenis_service) || empty($keluhan)) {
    echo "<script>alert('Data yang wajib diisi belum lengkap!'); history.back();</script>";
    exit;
}

// PERBAIKAN: Ambil dan validasi semua kode asset
$assets = [];
foreach ($_POST['assets'] as $index => $asset) {
    if (!empty($asset['kode_aset'])) {
        $kode_aset = mysqli_real_escape_string($conn, $asset['kode_aset']);
        
        // Cek apakah asset ada
        $check_asset = mysqli_query($conn, "
            SELECT kode_aset FROM asset_dfm WHERE kode_aset = '$kode_aset'
            UNION ALL
            SELECT kode_aset FROM asset_ga WHERE kode_aset = '$kode_aset'
            UNION ALL
            SELECT kode_aset FROM asset_bst WHERE kode_aset = '$kode_aset'
            LIMIT 1
        ");
        if (!$check_asset || mysqli_num_rows($check_asset) == 0) {
            echo "<script>alert('Asset dengan kode $kode_aset tidak ditemukan!'); history.back();</script>";
            exit;
        }
        
        // Cek duplikasi dalam array
        if (in_array($kode_aset, $assets)) {
            echo "<script>alert('Asset $kode_aset dipilih lebih dari sekali!'); history.back();</script>";
            exit;
        }
        
        $assets[] = $kode_aset;
    }
}

if (count($assets) == 0) {
    echo "<script>alert('Tidak ada asset yang dipilih!'); history.back();</script>";
    exit;
}

// Cek apakah nomor service sudah ada
$check_no_service = mysqli_query($conn, "SELECT no_service FROM service_asset WHERE no_service = '$no_service' LIMIT 1");
if ($check_no_service && mysqli_num_rows($check_no_service) > 0) {
    // Generate nomor service baru jika duplikat
    $no_service = "SVC-" . date('Ymd') . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Handle upload dokumentasi service
$dokumentasi_service = '';

// DEBUGGING: Cek kondisi upload file
if (isset($_FILES['dokumentasi'])) {
            }

if (isset($_FILES['dokumentasi']) && $_FILES['dokumentasi']['error'] == UPLOAD_ERR_OK && !empty($_FILES['dokumentasi']['name'])) {

    
    $upload_dir = '../uploads/service/';

    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        if (mkdir($upload_dir, 0755, true)) {
                    } else {
                    }
    } else {
            }

    $file_tmp = $_FILES['dokumentasi']['tmp_name'];
    $file_name = $_FILES['dokumentasi']['name'];
    $file_size = $_FILES['dokumentasi']['size'];
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
    $new_filename = "service_{$timestamp}_{$random}.{$file_ext}";
    $upload_path = $upload_dir . $new_filename;

        
    // Upload file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $dokumentasi_service = $new_filename;
        
        // Resize gambar jika terlalu besar
        if (resizeImage($upload_path, $upload_path, 1200, 800)) {
                    } else {
                    }
    } else {
                echo "<script>alert('Gagal upload dokumentasi'); history.back();</script>";
        exit;
    }
} else {
        if (isset($_FILES['dokumentasi']) && $_FILES['dokumentasi']['error'] != UPLOAD_ERR_NO_FILE) {
        $error_msg = '';
        switch ($_FILES['dokumentasi']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error_msg = 'File terlalu besar (php.ini limit)';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = 'File terlalu besar (form limit)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg = 'File hanya terupload sebagian';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_msg = 'Folder temporary tidak ditemukan';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_msg = 'Gagal menulis file ke disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_msg = 'Upload dihentikan oleh extension';
                break;
            default:
                $error_msg = 'Error tidak dikenal: ' . $_FILES['dokumentasi']['error'];
        }
            }
}

// PERBAIKAN: Insert data service untuk setiap asset dengan NO SERVICE YANG SAMA
$inserted_count = 0;
$failed_assets = [];

// PENTING: Semua asset dalam satu pengajuan service harus punya no_service yang SAMA
// Jadi kita gunakan $no_service yang sudah di-generate di awal untuk SEMUA asset
foreach ($assets as $index => $kode_aset) {
    
    $sql = "INSERT INTO service_asset (
                no_service, kode_aset, tanggal_service, jenis_service, keluhan, tindakan,
                dokumentasi_service, status_service, user_input, created_at
            ) VALUES (
                '$no_service', '$kode_aset', '$tanggal_service', '$jenis_service', '$keluhan',
                " . ($tindakan ? "'$tindakan'" : "NULL") . ",
                " . ($dokumentasi_service ? "'$dokumentasi_service'" : "NULL") . ",
                'Service', '$user_input', '$created_at'
            )";
    
    // DEBUGGING: Tampilkan query SQL
        
    $insert = mysqli_query($conn, $sql);
    
    if ($insert) {
        $inserted_count++;
            } else {
        $error = mysqli_error($conn);
        $failed_assets[] = "$kode_aset ($no_service): $error";
            }
}

// Hasil akhir
if ($inserted_count > 0 && count($failed_assets) == 0) {
    // Semua berhasil
        $success_msg = "Berhasil menyimpan $inserted_count service asset";
    $success_msg .= "\\nNomor Service: $no_service (SAMA untuk semua asset)";
    if ($dokumentasi_service) {
        $success_msg .= "\\nDokumentasi: $dokumentasi_service";
    }
    echo "<script>alert('$success_msg'); window.location.href='dashboard.php';</script>";
} else if ($inserted_count > 0 && count($failed_assets) > 0) {
    // Sebagian berhasil
    $error_msg = "Berhasil: $inserted_count asset\\nGagal: " . count($failed_assets) . " asset";
    $error_msg .= "\\nNomor Service: $no_service";
    $error_msg .= "\\n\\nDetail error:\\n" . implode("\\n", $failed_assets);
    echo "<script>alert('$error_msg'); window.location.href='dashboard.php';</script>";
} else {
    // Semua gagal
    // Jika gagal dan ada dokumentasi yang sudah diupload, hapus file
    if ($dokumentasi_service && file_exists($upload_dir . $dokumentasi_service)) {
        unlink($upload_dir . $dokumentasi_service);
            }
    
    $error_msg = "Gagal menyimpan semua asset:\\n\\n" . implode("\\n", $failed_assets);
    echo "<script>alert('$error_msg'); history.back();</script>";
}

// Function untuk resize gambar - sama seperti sebelumnya
function resizeImage($source, $destination, $max_width, $max_height, $quality = 80)
{
    // Jika module GD tidak aktif/tersedia di XAMPP PHP ini, skip proses resize gambar agar tidak error.
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
        return true;
    }

    $info = @getimagesize($source);
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
            if (function_exists('imagecreatefromwebp')) {
                $source_img = imagecreatefromwebp($source);
            } else {
                return false;
            }
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
            if (function_exists('imagewebp')) {
                $result = imagewebp($new_img, $destination, $quality);
            } else {
                $result = false;
            }
            break;
        default:
            $result = false;
    }

    // Bersihkan memory
    imagedestroy($source_img);
    imagedestroy($new_img);

    return $result;
}
