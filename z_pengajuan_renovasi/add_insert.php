<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\add_insert_fixed.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Akses tidak valid!';
    header("Location: add.php");
    exit();
}

try {
    mysqli_begin_transaction($conn);
    
    // Ambil data dari form
    $no_pengajuan = trim($_POST['no_pengajuan']);
    $nama_pic = trim($_POST['nama_pic']);
    $jabatan = ($_POST['jabatan'] === 'Lainnya') ? trim($_POST['jabatan_lainnya']) : $_POST['jabatan'];
    $kantor_cabang = ($_POST['kantor_cabang'] === 'Lainnya') ? trim($_POST['kantor_cabang_lainnya']) : $_POST['kantor_cabang'];
    $area_renovasi = trim($_POST['area_renovasi']);
    $jenis_pekerjaan = ($_POST['jenis_pekerjaan'] === 'Lainnya') ? trim($_POST['jenis_pekerjaan_lainnya']) : $_POST['jenis_pekerjaan'];
    $prioritas = $_POST['prioritas'];
    $alasan_renovasi = trim($_POST['alasan_renovasi']);
    $user_id = $_SESSION['id_user'];
    
    // Validasi input required
    if (empty($no_pengajuan) || empty($nama_pic) || empty($jabatan) || empty($kantor_cabang) || 
        empty($area_renovasi) || empty($jenis_pekerjaan) || empty($prioritas) || empty($alasan_renovasi)) {
        throw new Exception('Semua field yang wajib harus diisi!');
    }
    
    // Cek duplicate
    $check_query = "SELECT no_pengajuan FROM renovation_request WHERE no_pengajuan = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $no_pengajuan);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        throw new Exception('Nomor pengajuan sudah ada! Silakan refresh halaman dan coba lagi.');
    }
    mysqli_stmt_close($check_stmt);
    
    // Handle upload foto
    $foto_names = [];
    
    if (isset($_FILES['foto_area']) && is_array($_FILES['foto_area']['name']) && !empty($_FILES['foto_area']['name'][0])) {
        $upload_dir = '../uploads/renovasi/';
        
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Gagal membuat folder upload!');
            }
        }
        
        $max_files = 5;
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_count = count($_FILES['foto_area']['name']);
        
        if ($file_count > $max_files) {
            throw new Exception("Maksimal $max_files foto yang dapat diupload!");
        }
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['foto_area']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['foto_area']['tmp_name'][$i];
                $file_name = $_FILES['foto_area']['name'][$i];
                $file_size = $_FILES['foto_area']['size'][$i];
                
                // Validasi ukuran file
                if ($file_size > $max_size) {
                    throw new Exception("File '$file_name' terlalu besar! Maksimal 2MB per foto.");
                }
                
                // Validasi tipe file dengan getimagesize
                $image_info = getimagesize($file_tmp);
                if (!$image_info) {
                    throw new Exception("File '$file_name' bukan file gambar yang valid!");
                }
                
                // Generate nama file unik
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $timestamp = microtime(true);
                $safe_no_pengajuan = preg_replace('/[^a-zA-Z0-9]/', '_', $no_pengajuan);
                $new_file_name = $safe_no_pengajuan . '_foto_' . ($i + 1) . '_' . str_replace('.', '', $timestamp) . '.' . $file_extension;
                $upload_path = $upload_dir . $new_file_name;
                
                // Upload file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $foto_names[] = $new_file_name;
                } else {
                    throw new Exception("Gagal mengupload file '$file_name'!");
                }
            } elseif ($_FILES['foto_area']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                throw new Exception("Error saat mengupload file: " . $_FILES['foto_area']['error'][$i]);
            }
        }
    }
    
    // PERBAIKAN: Pastikan foto_json tidak null atau string kosong
    if (!empty($foto_names)) {
        $foto_json = json_encode($foto_names, JSON_UNESCAPED_SLASHES);
    } else {
        $foto_json = '[]'; // Array kosong JSON, bukan NULL
    }
    
    // PERBAIKAN: Insert dengan explicit NULL handling
    $insert_query = "INSERT INTO renovation_request (
        no_pengajuan, nama_pic, jabatan, kantor_cabang, area_renovasi, 
        jenis_pekerjaan, prioritas, alasan_renovasi, foto_area, user_id, 
        tanggal_pengajuan, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending', NOW())";
    
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    
    if (!$insert_stmt) {
        throw new Exception('Error prepare statement: ' . mysqli_error($conn));
    }
    
    // PERBAIKAN: Semua parameter sebagai string
    mysqli_stmt_bind_param($insert_stmt, "sssssssssi", 
        $no_pengajuan,
        $nama_pic,
        $jabatan,
        $kantor_cabang,
        $area_renovasi,
        $jenis_pekerjaan,
        $prioritas,
        $alasan_renovasi,
        $foto_json,     // Selalu string, tidak pernah NULL
        $user_id
    );
    
    if (mysqli_stmt_execute($insert_stmt)) {
        // Verifikasi data tersimpan
        $verify_query = "SELECT foto_area FROM renovation_request WHERE no_pengajuan = ?";
        $verify_stmt = mysqli_prepare($conn, $verify_query);
        mysqli_stmt_bind_param($verify_stmt, "s", $no_pengajuan);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        $verify_data = mysqli_fetch_assoc($verify_result);
        
        if ($verify_data['foto_area'] === null || $verify_data['foto_area'] === '') {
            error_log("WARNING: foto_area saved as NULL for no_pengajuan: $no_pengajuan");
            error_log("Original foto_json: $foto_json");
        }
        
        mysqli_stmt_close($verify_stmt);
        mysqli_commit($conn);
        
        $_SESSION['success'] = "Pengajuan renovasi berhasil disimpan dengan nomor: $no_pengajuan";
        header("Location: dashboard.php");
        exit();
        
    } else {
        throw new Exception('Gagal menyimpan data: ' . mysqli_stmt_error($insert_stmt));
    }
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    // Hapus file yang sudah diupload jika ada error
    if (!empty($foto_names)) {
        foreach ($foto_names as $foto_name) {
            $file_path = '../uploads/renovasi/' . $foto_name;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    $_SESSION['error'] = $e->getMessage();
    header("Location: add.php");
    exit();
}

if (isset($insert_stmt)) mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>