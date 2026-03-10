<?php
// filepath: c:\xampp\htdocs\ppx_dfm\pengajuan_ppx\insert.php
include('../dist/config/config.php');

// Cek session login

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Start transaction
        mysqli_begin_transaction($conn);

        // Ambil data dari form - PPX Request
        $no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx']);
        $nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic']);
        $jabatan = mysqli_real_escape_string($conn, $_POST['jabatan']);
        $divisi = mysqli_real_escape_string($conn, $_POST['divisi']);
        $cabang = mysqli_real_escape_string($conn, $_POST['cabang']);
        $user_id = (int)$_POST['user_id'];
        $tanggal_pengajuan = mysqli_real_escape_string($conn, $_POST['tanggal_pengajuan']);
        $alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan']);
        $tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan']);
        $tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        // Cek duplikasi no_ppx
        $checkQuery = "SELECT no_ppx FROM ppx_request WHERE no_ppx = '$no_ppx'";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            // Generate ulang nomor jika sudah ada
            $no_ppx = "PPX-" . date('Ymd') . "-" . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        }

        // Re-check/generate unique no_ppx
        $orig_no_ppx = $no_ppx;
        $attempt = 0;
        do {
            $checkQuery = "SELECT 1 FROM ppx_request WHERE no_ppx = '$no_ppx' LIMIT 1";
            $checkResult = mysqli_query($conn, $checkQuery);
            if (mysqli_num_rows($checkResult) == 0) break;
            $no_ppx = "PPX-" . date('Ymd') . "-" . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            $attempt++;
            if ($attempt > 5) throw new Exception('Gagal menghasilkan nomor PPX unik.');
        } while (true);

        // Handle file upload
        $upload_file = null;
        if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
            $uploadDir = '../uploads/ppx/';
            
            // Buat folder jika belum ada
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Sanitasi nama file upload
            $origName = basename($_FILES['upload_file']['name']);
            $safeName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $origName);
            $fileName = $no_ppx . '_' . time() . '_' . $safeName;
            $uploadPath = $uploadDir . $fileName;
            
            // Validasi file
            $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Gunakan batas dari php.ini (upload_max_filesize / post_max_size)
            function returnBytes($val) {
                $val = trim($val);
                $last = strtolower($val[strlen($val)-1]);
                $num = (int)$val;
                switch($last) {
                    case 'g': $num *= 1024;
                    case 'm': $num *= 1024;
                    case 'k': $num *= 1024;
                }
                return $num;
            }
            $iniUploadMax = returnBytes(ini_get('upload_max_filesize'));
            $iniPostMax = returnBytes(ini_get('post_max_size'));
            $maxSize = min($iniUploadMax, $iniPostMax);

            // jika ingin menghapus batas validasi di aplikasi, set $maxSize = PHP_INT_MAX;
            
            if (!in_array($fileExt, $allowedTypes)) {
                throw new Exception('Format file tidak didukung!');
            }
            
            if ($_FILES['upload_file']['size'] > $maxSize) {
                throw new Exception('Ukuran file terlalu besar! Maksimal sesuai konfigurasi server (php.ini).');
            }
            
            if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $uploadPath)) {
                $upload_file = $fileName;
            }
        }

        // Calculate total nominal dari detail
        $total_nominal = 0;
        if (isset($_POST['detail']) && is_array($_POST['detail'])) {
            foreach ($_POST['detail'] as $detail) {
                if (!empty($detail['harga_satuan']) && !empty($detail['jumlah'])) {
                    $harga_satuan = (int)preg_replace('/[^0-9]/', '', $detail['harga_satuan']);
                    $jumlah = (int)$detail['jumlah'];
                    $total_nominal += ($harga_satuan * $jumlah);
                }
            }
        }

        // Insert ke ppx_request dengan field yang benar
        $query_ppx = "INSERT INTO ppx_request (
            no_ppx, 
            nama_pic, 
            jabatan, 
            divisi, 
            cabang, 
            user_id, 
            tanggal_pengajuan,
            alasan_pengajuan, 
            tingkat_kepentingan, 
            tanggal_dibutuhkan, 
            upload_file,
            status, 
            nominal_ppx, 
            created_at_pengaju, 
            created_by_pengaju
        ) VALUES (
            '$no_ppx', 
            '$nama_pic', 
            '$jabatan', 
            '$divisi', 
            '$cabang', 
            $user_id, 
            '$tanggal_pengajuan',
            '$alasan_pengajuan', 
            '$tingkat_kepentingan', 
            '$tanggal_dibutuhkan', 
            " . ($upload_file ? "'$upload_file'" : "NULL") . ",
            '$status', 
            '$total_nominal', 
            NOW(), 
            '$nama_pic'
        )";
        
        if (!mysqli_query($conn, $query_ppx)) {
            throw new Exception('Gagal menyimpan data PPX: ' . mysqli_error($conn));
        }

        // Insert detail items ke ppx_request_detail (prepared statement dengan kolom yang sesuai)
        $inserted_details = 0;
        $detail_post_count = isset($_POST['detail']) && is_array($_POST['detail']) ? count($_POST['detail']) : 0;

        // Debug jika ada masalah
        if ($detail_post_count > 0) {
            error_log('add_insert.php: received detail count = ' . $detail_post_count);
        }

        if ($detail_post_count > 0) {
            // Prepared statement sesuai dengan struktur tabel ppx_request_detail
            $sql_detail = "INSERT INTO ppx_request_detail (
                no_ppx,
                no_akun,
                rincian,
                jumlah,
                satuan,
                harga_satuan,
                total_harga,
                deskripsi,
                created_at,
                created_by,
                updated_at,
                updated_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?
            )";
            
            $stmt = mysqli_prepare($conn, $sql_detail);
            if (!$stmt) {
                throw new Exception('Prepare statement gagal (detail): ' . mysqli_error($conn));
            }

            // Bind parameters sesuai tipe data
            mysqli_stmt_bind_param($stmt, 'sssisiisss',
                $bind_no_ppx,
                $bind_no_akun,
                $bind_rincian,
                $bind_jumlah,
                $bind_satuan,
                $bind_harga_satuan,
                $bind_total_harga,
                $bind_deskripsi,
                $bind_created_by,
                $bind_updated_by
            );

            // Set nilai tetap untuk bind parameters
            $bind_no_ppx = $no_ppx; // Menggunakan no_ppx yang sudah digenerate dan digunakan di tabel ppx_request
            $bind_created_by = $nama_pic;
            $bind_updated_by = $nama_pic;

            foreach ($_POST['detail'] as $index => $detail) {
                // Ambil & sanitasi data detail
                $no_akun_raw = $detail['no_akun'] ?? '';
                $rincian_raw = $detail['rincian'] ?? '';
                $jumlah_raw = $detail['jumlah'] ?? 0;
                $satuan_raw = $detail['satuan'] ?? '';
                $harga_raw = $detail['harga_satuan'] ?? '';
                $deskripsi_raw = $detail['deskripsi'] ?? '';

                // Sanitasi dan konversi data
                $bind_no_akun = trim($no_akun_raw);
                $bind_rincian = trim($rincian_raw);
                $bind_jumlah = (int)$jumlah_raw;
                $bind_satuan = trim($satuan_raw);
                $bind_harga_satuan = (int)preg_replace('/[^0-9]/', '', $harga_raw);
                $bind_deskripsi = trim($deskripsi_raw);
                $bind_total_harga = $bind_harga_satuan * $bind_jumlah;

                // Skip item yang tidak valid
                if (empty($bind_no_akun) || empty($bind_rincian) || $bind_jumlah <= 0) {
                    continue;
                }

                // Eksekusi prepared statement
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Gagal menyimpan detail item #' . ($index + 1) . ': ' . mysqli_stmt_error($stmt));
                }
                
                $inserted_details++;
            }
            
            mysqli_stmt_close($stmt);

            // Periksa apakah ada detail yang berhasil dimasukkan
            if ($detail_post_count > 0 && $inserted_details === 0) {
                throw new Exception('Tidak ada detail yang valid disimpan. Periksa input item detail Anda.');
            }
        }

        // Commit transaction
        mysqli_commit($conn);

        $_SESSION['success'] = 'Pengajuan PPX berhasil disubmit untuk persetujuan!';
        $_SESSION['no_ppx'] = $no_ppx;
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: add.php");
        exit();
    }
} else {
    header("Location: add.php");
    exit();
}
?>