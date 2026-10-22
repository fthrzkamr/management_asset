<?php
// filepath: c:\xampp\htdocs\ppx_dfm\pengajuan_ppx\insert.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// DEBUG: tulis log ke file untuk konfirmasi POST diterima
$debug_log = __DIR__ . '/debug_submit.txt';
file_put_contents($debug_log, 
    date('Y-m-d H:i:s') . " METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'NONE') . "\n" .
    "POST keys: " . implode(', ', array_keys($_POST ?? [])) . "\n" .
    "user_id=" . ($_POST['user_id'] ?? 'NOT SET') . " | " .
    "no_ppx=" . ($_POST['no_ppx'] ?? 'NOT SET') . " | " .
    "cabang=" . ($_POST['cabang'] ?? 'NOT SET') . "\n\n",
    FILE_APPEND
);

include('../dist/config/config.php');

// Cek session login
if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        error_log("PPX Insert: Starting submission process");
        
        // Start transaction
        mysqli_begin_transaction($conn);
        error_log("PPX Insert: Transaction started");

        // Ambil data dari form - PPX Request
        $no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx'] ?? '');
        // Ambil user_id dari POST, fallback ke SESSION jika tidak ada/0
        $user_id = isset($_POST['user_id']) && (int)$_POST['user_id'] > 0 
                   ? (int)$_POST['user_id'] 
                   : (int)($_SESSION['id_user'] ?? 0);
        
        if ($user_id <= 0) {
            throw new Exception('User ID tidak valid. Silakan login ulang.');
        }
        
        // Ambil data user dari database jika form inputnya disembunyikan
        $user_query = "SELECT u.nama_user, u.divisi, u.departemen, u.jabatan
                       FROM user_dfm u WHERE u.id_user = $user_id";
        $user_res = mysqli_query($conn, $user_query);
        if (!$user_res) {
            throw new Exception('Query user gagal: ' . mysqli_error($conn));
        }
        $user_row = mysqli_fetch_assoc($user_res);
        if (!$user_row) {
            throw new Exception("Data user dengan ID $user_id tidak ditemukan di database.");
        }
        
        $nama_pic = isset($_POST['nama_pic']) ? mysqli_real_escape_string($conn, $_POST['nama_pic']) : mysqli_real_escape_string($conn, $user_row['nama_user'] ?? '');
        $jabatan = isset($_POST['jabatan']) ? mysqli_real_escape_string($conn, $_POST['jabatan']) : mysqli_real_escape_string($conn, $user_row['jabatan'] ?? '');
        $divisi = isset($_POST['divisi']) ? mysqli_real_escape_string($conn, $_POST['divisi']) : (isset($_POST['id_divisi']) ? mysqli_real_escape_string($conn, $_POST['id_divisi']) : mysqli_real_escape_string($conn, $user_row['divisi'] ?? ''));
        $departemen = isset($_POST['departemen']) ? mysqli_real_escape_string($conn, $_POST['departemen']) : (isset($_POST['id_departemen']) ? mysqli_real_escape_string($conn, $_POST['id_departemen']) : mysqli_real_escape_string($conn, $user_row['departemen'] ?? ''));
        
        $cabang = mysqli_real_escape_string($conn, $_POST['cabang']);
        $tujuan_pencarian = mysqli_real_escape_string($conn, $_POST['tujuan_pencarian'] ?? '');
        $metode_pembayaran = mysqli_real_escape_string($conn, $_POST['metode_pembayaran'] ?? '');

        // NEW: ambil data bank/rekening berdasarkan metode pembayaran
        $nama_bank = isset($_POST['nama_bank']) ? mysqli_real_escape_string($conn, $_POST['nama_bank']) : null;
        $nama_rek  = isset($_POST['nama_rek'])  ? mysqli_real_escape_string($conn, $_POST['nama_rek'])  : null;
        $no_rek    = isset($_POST['no_rek'])    ? mysqli_real_escape_string($conn, $_POST['no_rek'])    : null;
        
        // Jika Cash: nama_rek diambil dari nama_rek_cash (tombol submit sudah sync-kan)
        // nama_bank & no_rek kosong untuk Cash
        if ($metode_pembayaran === 'Cash') {
            $nama_bank = null;
            $no_rek    = null;
        }

        $tanggal_pengajuan = mysqli_real_escape_string($conn, $_POST['tanggal_pengajuan']);
        $alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan']);
        $tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan']);
        $tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan']);
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
        
        // Ambil request_type dan id_category dari form
        // id_category sekarang selalu kosong (dipindah ke Realisasi Bulanan)
        $request_type = isset($_POST['request_type']) ? mysqli_real_escape_string($conn, $_POST['request_type']) : 'Regular';
        $id_category = isset($_POST['id_category']) && !empty($_POST['id_category']) ? (int)$_POST['id_category'] : null;
        
        error_log("PPX Insert: request_type = $request_type, id_category = " . ($id_category ?? 'NULL'));

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

        // Calculate total nominal dari detail - prioritaskan total_harga manual jika ada
        $total_nominal = 0;
        if (isset($_POST['detail']) && is_array($_POST['detail'])) {
            foreach ($_POST['detail'] as $detail) {
                // Cek apakah user mengisi total_harga manual
                $manual_total = isset($detail['total_harga']) ? (int)preg_replace('/[^0-9]/', '', $detail['total_harga']) : 0;
                
                if ($manual_total > 0) {
                    // Prioritas: pakai nilai total_harga yang diinput manual
                    $total_nominal += $manual_total;
                } elseif (!empty($detail['harga_satuan']) && !empty($detail['jumlah'])) {
                    // Fallback: hitung dari harga_satuan * jumlah
                    $harga_satuan = (int)preg_replace('/[^0-9]/', '', $detail['harga_satuan']);
                    $jumlah = (int)$detail['jumlah'];
                    $total_nominal += ($harga_satuan * $jumlah);
                }
            }
        }
        
        error_log("PPX Insert: Total nominal calculated = " . $total_nominal);

        // Insert ke ppx_request dengan field yang benar (ditambah kolom departemen dan bank/rekening)
        // Parse relation: could be asset_XXXXX, renovasi_XXXXX, or service_SVC-XXXXX
        $no_pengajuan_relasi = $_POST['no_pengajuan_relasi'] ?? null;
        $no_pengajuan_asset = null;
        $no_pengajuan_renovasi = null;
        $no_service = null; // will store no_service if relation points to a service

        if ($no_pengajuan_relasi) {
            if (strpos($no_pengajuan_relasi, 'renovasi_') === 0) {
                $no_pengajuan_renovasi = str_replace('renovasi_', '', $no_pengajuan_relasi);
            } elseif (strpos($no_pengajuan_relasi, 'asset_') === 0) {
                $no_pengajuan_asset = str_replace('asset_', '', $no_pengajuan_relasi);
            } elseif (strpos($no_pengajuan_relasi, 'service_') === 0) {
                // service_<no_service> format
                $no_service = str_replace('service_', '', $no_pengajuan_relasi);
            } elseif (stripos($no_pengajuan_relasi, 'SVC-') === 0) {
                // direct no_service value like SVC-20251101-001
                $no_service = $no_pengajuan_relasi;
            } else {
                // Fallback: assume asset
                $no_pengajuan_asset = $no_pengajuan_relasi;
            }
        }

        // Set nominal_ppx dari total
        $nominal_ppx = $total_nominal;
        
        // Determine flow_type and get cabang_user based on user's division and cabang
        $flow_type = 'pusat'; // default
        $cabang_user = null;
        
        $user_flow_query = "SELECT u.id_cabang, d.nama_divisi 
                            FROM user_dfm u 
                            LEFT JOIN divisi d ON u.divisi = d.id_divisi 
                            WHERE u.id_user = $user_id";
        $user_flow_result = mysqli_query($conn, $user_flow_query);
        
        if ($user_flow_result && mysqli_num_rows($user_flow_result) > 0) {
            $user_flow_data = mysqli_fetch_assoc($user_flow_result);
            $user_cabang_code = $user_flow_data['id_cabang'];
            $user_divisi_name = $user_flow_data['nama_divisi'];
            
            // Set cabang_user from id_cabang
            $cabang_user = !empty($user_cabang_code) ? $user_cabang_code : null;
            
            // Determine flow_type
            if (!empty($user_cabang_code) && strtoupper($user_cabang_code) != 'PUSAT') {
                // User punya cabang dan bukan PUSAT → flow cabang
                $flow_type = 'cabang';
            } elseif (!empty($user_divisi_name) && stripos($user_divisi_name, 'Finance AP') !== false) {
                // User di divisi Finance AP → langsung ke Manager Finance
                $flow_type = 'finance_ap';
            } else {
                // Default: pusat (HR/IT/GA/etc) → melalui GA
                $flow_type = 'pusat';
            }
        }
        
        error_log("PPX Insert: flow_type = $flow_type, cabang_user = " . ($cabang_user ?? 'NULL'));

        // Determine initial status using approval routing table (flexible configuration)
        $initial_status = $status; // default from form
        
        // Prepare request_type for routing comparison (change "Monthly_Budget" to "Monthly Budget" to match DB entries)
        $routing_request_type = str_replace('_', ' ', $request_type);
        
        // Check approval routing configuration from database
        // Cek apakah kolom request_type sudah ada di tabel routing
        $col_check_rt = mysqli_query($conn, 
            "SELECT 1 FROM information_schema.columns 
             WHERE table_schema = DATABASE() AND table_name = 'ppx_approval_routing' 
             AND column_name = 'request_type'");
        $has_rt_col = ($col_check_rt && mysqli_num_rows($col_check_rt) > 0);
        
        if ($has_rt_col) {
            // Kolom sudah ada: filter menggunakan request_type
            $routing_query = "SELECT initial_status, approver_role, skip_leader 
                             FROM ppx_approval_routing 
                             WHERE divisi_id = ? 
                             AND (cabang IS NULL OR cabang = '' OR cabang = ?) 
                             AND is_active = 1 
                             AND (request_type IS NULL OR request_type = '' OR request_type = ? OR request_type = 'Semua')
                             ORDER BY 
                                 (request_type = ?) DESC,
                                 CASE WHEN cabang = ? THEN 1 WHEN (cabang IS NULL OR cabang = '') THEN 0 ELSE -1 END DESC
                             LIMIT 1";
            $stmt_routing = mysqli_prepare($conn, $routing_query);
            mysqli_stmt_bind_param($stmt_routing, 'issss', $divisi, $cabang, $routing_request_type, $routing_request_type, $cabang);
        } else {
            // Kolom belum ada (migration belum dijalankan): fallback ke query lama
            $routing_query = "SELECT initial_status, approver_role, skip_leader 
                             FROM ppx_approval_routing 
                             WHERE divisi_id = ? 
                             AND (cabang IS NULL OR cabang = '' OR cabang = ?) 
                             AND is_active = 1 
                             ORDER BY (cabang = ?) DESC 
                             LIMIT 1";
            $stmt_routing = mysqli_prepare($conn, $routing_query);
            mysqli_stmt_bind_param($stmt_routing, 'iss', $divisi, $cabang, $cabang);
        }
        
        mysqli_stmt_execute($stmt_routing);
        $routing_result = mysqli_stmt_get_result($stmt_routing);
        
        if ($routing_row = mysqli_fetch_assoc($routing_result)) {
            // Found routing config - use configured initial status
            $initial_status = $routing_row['initial_status'];
            error_log("PPX Insert: Using configured routing - divisi=$divisi, cabang=$cabang, request_type=$request_type, initial_status=$initial_status, approver_role=" . ($routing_row['approver_role'] ?? 'NULL'));
        } else {
            // Tidak ada konfigurasi routing → tampilkan error agar admin segera mengatur routing
            throw new Exception('Routing approval untuk divisi Anda belum dikonfigurasi. Silakan hubungi Administrator untuk mengatur routing PPX.');
        }
        
        // Safety fallback if status is still empty — tidak boleh terjadi lagi
        if (empty($initial_status)) {
            throw new Exception('Gagal menentukan status awal pengajuan. Hubungi Administrator.');
        }
        mysqli_stmt_close($stmt_routing);
        
        $query_ppx = "INSERT INTO ppx_request (
            no_ppx, 
            user_id,
            nama_pic, 
            jabatan, 
            divisi, 
            departemen, 
            cabang,
            tujuan_pencarian,
            metode_pembayaran,
            nama_bank,
            nama_rek,
            no_rek,
            tanggal_pengajuan, 
            tanggal_dibutuhkan,
            tingkat_kepentingan, 
            alasan_pengajuan, 
            nominal_ppx, 
            status, 
            upload_file,
            no_pengajuan_asset,
            no_pengajuan_renovasi,
            no_service,
            request_type,
            id_category,
            flow_type,
            cabang_user
        ) VALUES (
            '$no_ppx', 
            '$user_id', 
            '$nama_pic', 
            '$jabatan', 
            '$divisi', 
            '$departemen', 
            '$cabang',
            '$tujuan_pencarian',
            '$metode_pembayaran',
            '$nama_bank',
            '$nama_rek',
            '$no_rek',
            '$tanggal_pengajuan', 
            '$tanggal_dibutuhkan',
            '$tingkat_kepentingan', 
            '$alasan_pengajuan', 
            '$nominal_ppx', 
            '$initial_status', 
            '$upload_file',
            " . ($no_pengajuan_asset ? "'$no_pengajuan_asset'" : "NULL") . ",
            " . ($no_pengajuan_renovasi ? "'$no_pengajuan_renovasi'" : "NULL") . ",
            " . ($no_service ? "'" . mysqli_real_escape_string($conn, $no_service) . "'" : "NULL") . ",
            '$request_type',
            " . ($id_category ? "'$id_category'" : "NULL") . ",
            '$flow_type',
            " . ($cabang_user ? "'$cabang_user'" : "NULL") . "
        )";
        
        error_log("PPX Insert: About to execute INSERT query for no_ppx = $no_ppx");
        error_log("PPX Insert: Query = " . substr($query_ppx, 0, 200) . "...");
        
        if (!mysqli_query($conn, $query_ppx)) {
            $error = mysqli_error($conn);
            error_log("PPX Insert: INSERT query failed - " . $error);
            throw new Exception('Gagal menyimpan data PPX: ' . $error);
        }
        
        error_log("PPX Insert: INSERT query successful");
        
        // =====================================================================
        // LOGIKA BUDGET DEDUCTION — DINONAKTIFKAN
        // Kategori Anggaran tidak lagi dipilih saat pengajuan PPX.
        // Pemotongan budget akan dilakukan dari modul Realisasi Bulanan.
        // id_category selalu NULL → blok ini tidak akan pernah aktif.
        // =====================================================================
        // if ($request_type === 'Monthly_Budget' && $id_category && $total_nominal > 0) {
        //     ... budget deduction logic ...
        // }
        error_log("PPX Insert: Budget deduction skipped — akan diproses via Realisasi Bulanan");

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
                
                // Prioritaskan total_harga yang diinput manual user
                $total_harga_raw = $detail['total_harga'] ?? '';
                $manual_total = (int)preg_replace('/[^0-9]/', '', $total_harga_raw);
                $bind_total_harga = $manual_total > 0 ? $manual_total : ($bind_harga_satuan * $bind_jumlah);

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
        
        error_log("PPX Insert: Transaction committed successfully for no_ppx = $no_ppx");

        $_SESSION['success'] = 'Pengajuan PPX berhasil disubmit untuk persetujuan!';
        $_SESSION['no_ppx'] = $no_ppx;
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
        error_log("PPX Insert: Exception caught - " . $error_msg);
        error_log("PPX Insert: Stack trace - " . $e->getTraceAsString());
        // Tulis ke debug file juga
        file_put_contents(__DIR__ . '/debug_submit.txt', 
            date('Y-m-d H:i:s') . " EXCEPTION: " . $error_msg . "\n", FILE_APPEND);
        $_SESSION['error'] = $error_msg;
        header("Location: add.php");
        exit();
    } catch (Error $e) {
        // Catch PHP Fatal Errors
        mysqli_rollback($conn);
        $error_msg = "PHP Error: " . $e->getMessage();
        error_log("PPX Insert: PHP Error - " . $error_msg);
        $_SESSION['error'] = $error_msg;
        header("Location: add.php");
        exit();
    }
} else {
    header("Location: add.php");
    exit();
}
?>