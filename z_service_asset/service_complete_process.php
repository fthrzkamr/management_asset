<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\service_complete_process.php
include('../dist/config/config.php');
date_default_timezone_set('Asia/Jakarta');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    echo "<script>alert('Session expired. Silakan login kembali.'); window.location.href='../login.php';</script>";
    exit;
}

if (!isset($_POST['submit'])) {
    header("Location: service_list.php");
    exit;
}

// Input dari form
$id_service = (int)$_POST['id_service'];
$tanggal_selesai = $_POST['tanggal_selesai'];
$biaya_raw = preg_replace('/[^0-9]/', '', $_POST['biaya_service'] ?? '0');
$biaya_service = floatval($biaya_raw);
$catatan_selesai = !empty($_POST['catatan_selesai']) ? mysqli_real_escape_string($conn, $_POST['catatan_selesai']) : null;

$updated_at = date("Y-m-d H:i:s");

// Validasi required fields
if (empty($id_service) || empty($tanggal_selesai) || $biaya_service <= 0) {
    echo "<script>alert('Data yang wajib diisi belum lengkap!'); history.back();</script>";
    exit;
}

// Cek apakah service ada dan masih status 'Service'
$check_service = mysqli_query($conn, "
    SELECT id_service, no_service 
    FROM service_asset 
    WHERE id_service = $id_service AND status_service = 'Service' 
    LIMIT 1
");

if (!$check_service || mysqli_num_rows($check_service) == 0) {
    echo "<script>alert('Service tidak ditemukan atau sudah selesai!'); window.location.href='service_list.php';</script>";
    exit;
}

$service_data = mysqli_fetch_assoc($check_service);

// Handle upload foto nota
$foto_nota = '';
if (isset($_FILES['foto_nota']) && $_FILES['foto_nota']['error'] == UPLOAD_ERR_OK && !empty($_FILES['foto_nota']['name'])) {
    $upload_dir = '../uploads/nota/';
    
    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_tmp = $_FILES['foto_nota']['tmp_name'];
    $file_name = $_FILES['foto_nota']['name'];
    $file_size = $_FILES['foto_nota']['size'];
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
    $new_filename = "nota_{$timestamp}_{$random}.{$file_ext}";
    $upload_path = $upload_dir . $new_filename;
    
    // Upload file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $foto_nota = $new_filename;
        
        // Resize gambar jika terlalu besar
        resizeImage($upload_path, $upload_path, 1200, 800);
    } else {
        echo "<script>alert('Gagal upload foto nota'); history.back();</script>";
        exit;
    }
}

// Update status service menjadi 'Selesai'
$sql = "UPDATE service_asset SET
            status_service = 'Selesai',
            tanggal_selesai = '$tanggal_selesai',
            biaya_service = '$biaya_service',
            foto_nota = " . ($foto_nota ? "'$foto_nota'" : "NULL") . ",
            catatan_selesai = " . ($catatan_selesai ? "'$catatan_selesai'" : "NULL") . ",
            updated_at = '$updated_at'
        WHERE id_service = $id_service";

$update = mysqli_query($conn, $sql);

if ($update) {
    $success_message = "Service {$service_data['no_service']} berhasil diselesaikan!";
    
    if ($foto_nota) {
        $success_message .= "\\nFoto nota: $foto_nota";
    }
    
    echo "<script>alert('$success_message'); window.location.href='dashboard.php?status=completed';</script>";
} else {
    // Jika gagal update dan ada foto nota yang sudah diupload, hapus foto
    if ($foto_nota && file_exists($upload_dir . $foto_nota)) {
        unlink($upload_dir . $foto_nota);
    }
    
    $error = mysqli_error($conn);
    echo "<script>alert('Gagal menyelesaikan service: $error'); history.back();</script>";
}

// Function untuk resize gambar (sama seperti sebelumnya)
function resizeImage($source, $destination, $max_width, $max_height, $quality = 80) {
    // ... (sama seperti function sebelumnya)
}
?>