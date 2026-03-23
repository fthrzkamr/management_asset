<?php
// filepath: c:\xampp\htdocs\asset_dfm\pengajuan_asset\insert.php
include('../dist/config/config.php');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Ambil data dari form
        $no_pengajuan = mysqli_real_escape_string($conn, $_POST['no_pengajuan']);
        $nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic']);
        $jabatan = mysqli_real_escape_string($conn, $_POST['jabatan']);
        $divisi = mysqli_real_escape_string($conn, $_POST['divisi']);
        $user_id = (int)$_POST['user_id'];
        $tanggal_pengajuan = mysqli_real_escape_string($conn, $_POST['tanggal_pengajuan']);
        
        // Detail Asset
        $kategori_asset = (int)$_POST['kategori_asset'];
        $jenis_pengadaan = mysqli_real_escape_string($conn, $_POST['jenis_pengadaan']);
        $deskripsi_asset = mysqli_real_escape_string($conn, $_POST['deskripsi_asset']);
        $spesifikasi_merek = mysqli_real_escape_string($conn, $_POST['spesifikasi_merek']);
        
        // Convert harga dari format Rupiah ke angka
        $estimasi_harga = 0;
        if (!empty($_POST['estimasi_harga'])) {
            $estimasi_harga = (float)preg_replace('/[^0-9]/', '', $_POST['estimasi_harga']);
        }
        
        $jumlah = (int)$_POST['jumlah'];
        $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
        
        // Justifikasi
        $alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan']);
        $tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan']);
        $tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan']);
        $lokasi_penggunaan = (int)$_POST['lokasi_penggunaan'];
        $detail_lokasi = mysqli_real_escape_string($conn, $_POST['detail_lokasi']);
        $catatan_tambahan = mysqli_real_escape_string($conn, $_POST['catatan_tambahan']);
        
        // Get user cabang untuk menentukan flow approval
        $user_query = "SELECT mc.nama_cabang, r.nama_role 
                       FROM user_dfm u 
                       LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
                       LEFT JOIN roles r ON u.id_role = r.id_role
                       WHERE u.id_user = $user_id";
        $user_result = mysqli_query($conn, $user_query);
        $user_data = mysqli_fetch_assoc($user_result);
        $cabang_pengaju = $user_data['nama_cabang'];
        $role_pengaju = $user_data['nama_role'];
        
        // Tentukan approval_level berdasarkan cabang dan role:
        // GA Surabaya -> approval_level = 2 (butuh 2 level: GA Jakarta -> Manager HR)
        // GA Jakarta atau role lain -> approval_level = 1 (langsung HR/GA Jakarta)
        $approval_level = 1;
        if (strtolower($role_pengaju) == 'ga' && strtolower($cabang_pengaju) == 'surabaya') {
            $approval_level = 2;
        }
        
        // Tentukan status berdasarkan tombol yang diklik
        $status = 'Draft';
        if (isset($_POST['submit_pengajuan'])) {
            $status = 'Pending';
        }
        
        // PERBAIKAN: Cek duplikasi no_pengajuan
        $checkQuery = "SELECT no_pengajuan FROM asset_request WHERE no_pengajuan = '$no_pengajuan'";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            // Generate ulang nomor jika sudah ada
            $no_pengajuan = "REQ-" . date('Ymd') . "-" . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        }
        
        // Handle file upload
        $file_attachment = null;
        if (isset($_FILES['file_attachment']) && $_FILES['file_attachment']['error'] == 0) {
            $uploadDir = '../uploads/pengajuan/';
            
            // Buat folder jika belum ada
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = $no_pengajuan . '_' . time() . '_' . $_FILES['file_attachment']['name'];
            $uploadPath = $uploadDir . $fileName;
            
            // Validasi file
            $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($fileExt, $allowedTypes)) {
                throw new Exception('Format file tidak didukung!');
            }
            
            if ($_FILES['file_attachment']['size'] > $maxSize) {
                throw new Exception('Ukuran file terlalu besar! Maksimal 5MB.');
            }
            
            if (move_uploaded_file($_FILES['file_attachment']['tmp_name'], $uploadPath)) {
                $file_attachment = $fileName;
            }
        }
        
        // PERBAIKAN: Insert ke database dengan cabang_pengaju dan approval_level
        $query = "INSERT INTO asset_request (
            no_pengajuan, nama_pic, jabatan, divisi, user_id, tanggal_pengajuan,
            kategori_asset, jenis_pengadaan, deskripsi_asset, spesifikasi_merek,
            estimasi_harga, jumlah, satuan, alasan_pengajuan, tingkat_kepentingan,
            tanggal_dibutuhkan, lokasi_penggunaan, detail_lokasi, catatan_tambahan,
            file_attachment, status, cabang_pengaju, approval_level
        ) VALUES (
            '$no_pengajuan', '$nama_pic', '$jabatan', '$divisi', $user_id, '$tanggal_pengajuan',
            $kategori_asset, '$jenis_pengadaan', '$deskripsi_asset', '$spesifikasi_merek',
            $estimasi_harga, $jumlah, '$satuan', '$alasan_pengajuan', '$tingkat_kepentingan',
            '$tanggal_dibutuhkan', $lokasi_penggunaan, '$detail_lokasi', '$catatan_tambahan',
            " . ($file_attachment ? "'$file_attachment'" : "NULL") . ", '$status', '$cabang_pengaju', $approval_level
        )";
        
        if (mysqli_query($conn, $query)) {
            $message = ($status == 'Draft') ? 'Draft pengajuan berhasil disimpan!' : 'Pengajuan berhasil disubmit untuk persetujuan!';
            $_SESSION['success'] = $message;
            $_SESSION['no_pengajuan'] = $no_pengajuan;
            header("Location: dashboard.php");
            exit();
        } else {
            throw new Exception('Gagal menyimpan pengajuan: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: add.php");
        exit();
    }
} else {
    header("Location: add.php");
    exit();
}
?>