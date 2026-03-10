<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\process_edit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Method tidak diizinkan!';
    header("Location: dashboard.php");
    exit();
}

// Validasi dan ambil data dari form
$no_pengajuan = mysqli_real_escape_string($conn, $_POST['no_pengajuan']);
$nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic']);
$jabatan = mysqli_real_escape_string($conn, $_POST['jabatan']);
$jabatan_lainnya = mysqli_real_escape_string($conn, $_POST['jabatan_lainnya'] ?? '');
$kantor_cabang = mysqli_real_escape_string($conn, $_POST['kantor_cabang']);
$kantor_cabang_lainnya = mysqli_real_escape_string($conn, $_POST['kantor_cabang_lainnya'] ?? '');
$area_renovasi = mysqli_real_escape_string($conn, $_POST['area_renovasi']);
$jenis_pekerjaan = mysqli_real_escape_string($conn, $_POST['jenis_pekerjaan']);
$jenis_pekerjaan_lainnya = mysqli_real_escape_string($conn, $_POST['jenis_pekerjaan_lainnya'] ?? '');
$prioritas = mysqli_real_escape_string($conn, $_POST['prioritas']);
$alasan_renovasi = mysqli_real_escape_string($conn, $_POST['alasan_renovasi']);

// Validasi field yang wajib diisi
if (empty($no_pengajuan) || empty($nama_pic) || empty($area_renovasi) || 
    empty($prioritas) || empty($alasan_renovasi)) {
    $_SESSION['error'] = 'Semua field yang wajib diisi harus diisi!';
    header("Location: edit.php?id=" . urlencode($no_pengajuan));
    exit();
}

// Proses field "Lainnya"
if ($jabatan === 'Lainnya' && !empty($jabatan_lainnya)) {
    $jabatan = $jabatan_lainnya;
}

if ($kantor_cabang === 'Lainnya' && !empty($kantor_cabang_lainnya)) {
    $kantor_cabang = $kantor_cabang_lainnya;
}

if ($jenis_pekerjaan === 'Lainnya' && !empty($jenis_pekerjaan_lainnya)) {
    $jenis_pekerjaan = $jenis_pekerjaan_lainnya;
}

// Cek apakah pengajuan exists dan permission
$check_query = "SELECT * FROM renovation_request WHERE no_pengajuan = '$no_pengajuan'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['error'] = 'Pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$existing_data = mysqli_fetch_assoc($check_result);

// Cek permission berdasarkan jenis akses
$canUpdate = $auth->hasPermission('pengajuan_renovasi', 'update');
$canCreate = $auth->hasPermission('pengajuan_renovasi', 'create');

if (!$canUpdate && !$canCreate) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit pengajuan!';
    header("Location: dashboard.php");
    exit();
}

if ($canCreate && !$canUpdate) {
    if ($existing_data['user_id'] != $_SESSION['id_user'] || $existing_data['status'] != 'Pending') {
        $_SESSION['error'] = 'Anda tidak dapat mengedit pengajuan ini!';
        header("Location: dashboard.php");
        exit();
    }
}

if ($canUpdate && $existing_data['status'] != 'Pending') {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit!';
    header("Location: dashboard.php");
    exit();
}

// Handle upload foto
$foto_area_json = $existing_data['foto_area']; // Default gunakan foto yang sudah ada

if (isset($_FILES['foto_area']) && !empty($_FILES['foto_area']['name'][0])) {
    $upload_dir = '../uploads/renovasi/';
    
    // Buat direktori jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_files = [];
    $max_files = 5;
    $max_size = 2 * 1024 * 1024; // 2MB
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    
    // Validasi jumlah file
    $file_count = count(array_filter($_FILES['foto_area']['name']));
    if ($file_count > $max_files) {
        $_SESSION['error'] = "Maksimal $max_files foto yang dapat diupload!";
        header("Location: edit.php?id=" . urlencode($no_pengajuan));
        exit();
    }
    
    // Process setiap file
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['foto_area']['error'][$i] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['foto_area']['tmp_name'][$i];
            $file_name = $_FILES['foto_area']['name'][$i];
            $file_size = $_FILES['foto_area']['size'][$i];
            $file_type = $_FILES['foto_area']['type'][$i];
            
            // Validasi ukuran file
            if ($file_size > $max_size) {
                $_SESSION['error'] = "File '$file_name' terlalu besar! Maksimal 2MB per foto.";
                header("Location: edit.php?id=" . urlencode($no_pengajuan));
                exit();
            }
            
            // Validasi tipe file
            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = "Format file '$file_name' tidak didukung! Gunakan JPG, JPEG, atau PNG.";
                header("Location: edit.php?id=" . urlencode($no_pengajuan));
                exit();
            }
            
            // Generate nama file unik
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_filename = $no_pengajuan . '_foto_' . ($i + 1) . '_' . time() . '.' . $file_ext;
            $target_path = $upload_dir . $new_filename;
            
            // Upload file
            if (move_uploaded_file($file_tmp, $target_path)) {
                $uploaded_files[] = $new_filename;
            } else {
                $_SESSION['error'] = "Gagal mengupload file '$file_name'!";
                header("Location: edit.php?id=" . urlencode($no_pengajuan));
                exit();
            }
        }
    }
    
    // Jika ada file yang berhasil diupload, hapus foto lama dan update
    if (!empty($uploaded_files)) {
        // Hapus foto lama jika ada
        if (!empty($existing_data['foto_area'])) {
            $old_photos = json_decode($existing_data['foto_area'], true);
            if (is_array($old_photos)) {
                foreach ($old_photos as $old_photo) {
                    $old_file_path = $upload_dir . $old_photo;
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            }
        }
        
        // Set foto baru
        $foto_area_json = json_encode($uploaded_files);
    }
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Update data pengajuan
    $update_query = "UPDATE renovation_request SET 
        nama_pic = '$nama_pic',
        jabatan = '$jabatan', 
        kantor_cabang = '$kantor_cabang',
        area_renovasi = '$area_renovasi',
        jenis_pekerjaan = '$jenis_pekerjaan',
        prioritas = '$prioritas',
        alasan_renovasi = '$alasan_renovasi',
        foto_area = '$foto_area_json',
        updated_at = NOW()
    WHERE no_pengajuan = '$no_pengajuan'";
    
    if (!mysqli_query($conn, $update_query)) {
        throw new Exception('Gagal update data pengajuan: ' . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = 'Pengajuan renovasi berhasil diupdate!';
    header("Location: dashboard.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    // Hapus file yang sudah diupload jika ada error
    if (!empty($uploaded_files)) {
        foreach ($uploaded_files as $file) {
            $file_path = $upload_dir . $file;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    $_SESSION['error'] = 'Gagal mengupdate pengajuan: ' . $e->getMessage();
    header("Location: edit.php?id=" . urlencode($no_pengajuan));
    exit();
}
?>