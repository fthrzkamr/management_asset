<?php
// filepath: c:\xampp\htdocs\ppx_dfm\pengajuan_ppx\insert.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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
        $no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx']);
        $nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic']);
        $jabatan = mysqli_real_escape_string($conn, $_POST['jabatan']);
        $divisi = mysqli_real_escape_string($conn, $_POST['divisi']);
        // Tambahkan departemen
        $departemen = mysqli_real_escape_string($conn, $_POST['departemen']);
        $cabang = mysqli_real_escape_string($conn, $_POST['cabang']);

        // NEW: ambil data bank/rekening
        $nama_bank = isset($_POST['nama_bank']) ? mysqli_real_escape_string($conn, $_POST['nama_bank']) : null;
        $nama_rek  = isset($_POST['nama_rek'])  ? mysqli_real_escape_string($conn, $_POST['nama_rek'])  : null;
        $no_rek    = isset($_POST['no_rek'])    ? mysqli_real_escape_string($conn, $_POST['no_rek'])    : null;

        $user_id = (int)$_POST['user_id'];
        $tanggal_pengajuan = mysqli_real_escape_string($conn, $_POST['tanggal_pengajuan']);
        $alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan']);
        $tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan']);
        $tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        // Ambil request_type dan id_category dari form
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
                                 (cabang = ?) DESC 
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
            // No routing config found - fallback to original logic
            if ($flow_type === 'cabang' && !empty($cabang_user)) {
                // Check if a Kacab exists for this cabang
                $check_kacab_q = "SELECT 1 FROM user_dfm u JOIN roles r ON u.id_role = r.id_role WHERE r.nama_role = 'kacab' AND u.id_cabang = '" . mysqli_real_escape_string($conn, $cabang_user) . "' LIMIT 1";
                $check_kacab_r = mysqli_query($conn, $check_kacab_q);
                if ($check_kacab_r && mysqli_num_rows($check_kacab_r) > 0) {
                    $initial_status = 'Kacab';
                } else {
                    $initial_status = 'Leader';
                }
                error_log("PPX Insert: No routing config - using cabang logic, initial_status = $initial_status");
            } else {
                error_log("PPX Insert: No routing config - keeping default status = $initial_status");
            }
        }
        
        // TRANSITION LOGIC: Bypass routing for requests made in March 2026
        $current_date = date('Y-m');
        if ($current_date === '2026-03') {
            $initial_status = 'Finance';
            error_log("PPX Insert: March 2026 Transition - Bypassing routing and setting status to Finance");
        }
        
        // Safety fallback if status is still empty
        if (empty($initial_status)) {
            $initial_status = 'Leader';
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
            id_category
        ) VALUES (
            '$no_ppx', 
            '$user_id', 
            '$nama_pic', 
            '$jabatan', 
            '$divisi', 
            '$departemen', 
            '$cabang', 
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
            " . ($id_category ? "'$id_category'" : "NULL") . "
        )";
        
        error_log("PPX Insert: About to execute INSERT query for no_ppx = $no_ppx");
        error_log("PPX Insert: Query = " . substr($query_ppx, 0, 200) . "...");
        
        if (!mysqli_query($conn, $query_ppx)) {
            $error = mysqli_error($conn);
            error_log("PPX Insert: INSERT query failed - " . $error);
            throw new Exception('Gagal menyimpan data PPX: ' . $error);
        }
        
        error_log("PPX Insert: INSERT query successful");
        
        // Jika request_type = Monthly_Budget, cek dan potong budget
        if ($request_type === 'Monthly_Budget' && $id_category && $total_nominal > 0) {
            error_log("PPX Insert: Processing Monthly_Budget type with category = $id_category");
            
            // Get current period
            $current_year = date('Y');
            $current_month = (int)date('m');
            
            // Get current user's division and role for filtering
            $user_id = $_SESSION['id_user'];
            $user_query = "SELECT u.divisi, d.nama_divisi, r.nama_role 
                           FROM user_dfm u 
                           LEFT JOIN divisi d ON u.divisi = d.id_divisi 
                           LEFT JOIN roles r ON u.id_role = r.id_role
                           WHERE u.id_user = $user_id";
            $user_result = mysqli_query($conn, $user_query);
            $user_data = mysqli_fetch_assoc($user_result);
            $user_division_id = $user_data['divisi'];
            $user_division = $user_data['nama_divisi'];
            $user_role = $user_data['nama_role'];
            
            // Filter budget berdasarkan role dan divisi (sama seperti get_budget_categories.php):
            // 1. IT, GA -> pakai budget yang dibuat oleh divisi HR (divisi 7)
            // 2. User divisi HR -> pakai budget divisi HR
            // 3. User lain -> pakai budget yang mereka buat sendiri
            if (in_array(strtolower($user_role), ['it', 'ga'])) {
                // IT dan GA pakai budget yang dibuat oleh user divisi HR (divisi 7)
                $division_filter = "AND ub.divisi = 7";
                error_log("PPX Insert: Role $user_role - menggunakan budget divisi HR");
            } elseif ($user_division_id == 7) {
                // User divisi HR pakai budget divisi HR
                $division_filter = "AND ub.divisi = 7";
                error_log("PPX Insert: Divisi HR - menggunakan budget divisi HR");
            } else {
                // User lain hanya bisa pakai budget yang mereka buat sendiri
                $division_filter = "AND mb.created_by = $user_id";
                error_log("PPX Insert: Role $user_role - hanya budget created_by=$user_id");
            }
            
            // Find budget item untuk category + period ini dengan filter sesuai role
            // Prioritaskan budget item yang punya sisa terbanyak
            // JOIN dengan user_dfm untuk filter divisi (untuk IT/GA/HR)
            $budget_query = "SELECT mbi.id_budget_item, mbi.remaining, mbi.allocation 
                           FROM ppx_monthly_budget_item mbi
                           JOIN ppx_monthly_budget mb ON mbi.id_budget = mb.id_budget
                           LEFT JOIN user_dfm ub ON mb.created_by = ub.id_user
                           WHERE mbi.id_category = '$id_category'
                           AND mb.period_year = '$current_year'
                           AND mb.period_month = '$current_month'
                           AND mb.status IN ('Approved', 'Active')
                           AND mbi.remaining > 0
                           $division_filter
                           ORDER BY mbi.remaining DESC
                           LIMIT 1";
            
            error_log("PPX Insert: Budget query = " . $budget_query);
            
            $budget_result = mysqli_query($conn, $budget_query);
            
            if (!$budget_result) {
                error_log("PPX Insert: Budget query failed - " . mysqli_error($conn));
                throw new Exception('Error saat mengecek budget: ' . mysqli_error($conn));
            }
            
            if (mysqli_num_rows($budget_result) > 0) {
                $budget_row = mysqli_fetch_assoc($budget_result);
                $id_budget_item = $budget_row['id_budget_item'];
                $remaining = $budget_row['remaining'];
                
                error_log("PPX Insert: Budget found - id_budget_item=$id_budget_item, remaining=$remaining, required=$total_nominal");
                
                // Cek apakah budget cukup dengan pesan error yang jelas
                if ($remaining < $total_nominal) {
                    error_log("PPX Insert: Budget insufficient");
                    $kelebihan = $total_nominal - $remaining;
                    $error_message = "❌ PENGAJUAN PPX DITOLAK: NOMINAL MELEBIHI BUDGET!\n\n";
                    $error_message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                    $error_message .= "Total Nominal PPX: Rp " . number_format($total_nominal, 0, ',', '.') . "\n";
                    $error_message .= "Sisa Budget Tersedia: Rp " . number_format($remaining, 0, ',', '.') . "\n";
                    $error_message .= "Kelebihan: Rp " . number_format($kelebihan, 0, ',', '.') . "\n";
                    $error_message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                    $error_message .= "Silakan lakukan salah satu berikut:\n";
                    $error_message .= "1. Kurangi nominal item sesuai sisa budget\n";
                    $error_message .= "2. Pilih kategori anggaran lain yang memiliki budget cukup\n";
                    $error_message .= "3. Hubungi Manager Finance untuk penambahan alokasi budget\n\n";
                    $error_message .= "📞 Kontak: Manager Finance - Ext: 101 / WA: 0812-xxxx-xxxx";
                    
                    throw new Exception($error_message);
                }
                
                // Update id_budget_item di ppx_request
                $update_ppx = "UPDATE ppx_request 
                             SET id_budget_item = '$id_budget_item'
                             WHERE no_ppx = '$no_ppx'";
                
                error_log("PPX Insert: Updating ppx_request with id_budget_item");
                
                if (!mysqli_query($conn, $update_ppx)) {
                    $error = mysqli_error($conn);
                    error_log("PPX Insert: Failed to update id_budget_item - " . $error);
                    throw new Exception('Gagal mengupdate id_budget_item: ' . $error);
                }
                
                // Potong budget: kurangi remaining
                $new_remaining = $remaining - $total_nominal;
                $update_budget = "UPDATE ppx_monthly_budget_item 
                                SET remaining = '$new_remaining',
                                    updated_at = NOW()
                                WHERE id_budget_item = '$id_budget_item'";
                
                error_log("PPX Insert: Deducting budget, new_remaining = $new_remaining");
                
                if (!mysqli_query($conn, $update_budget)) {
                    $error = mysqli_error($conn);
                    error_log("PPX Insert: Failed to deduct budget - " . $error);
                    throw new Exception('Gagal memotong budget: ' . $error);
                }
                
                error_log("PPX Insert: Budget deducted successfully");
            } else {
                error_log("PPX Insert: No approved budget found for this category/period");
                throw new Exception('Budget untuk kategori ini belum tersedia atau belum diapprove untuk bulan ini!');
            }
        } else {
            error_log("PPX Insert: Skipping budget logic (Regular type or no category)");
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