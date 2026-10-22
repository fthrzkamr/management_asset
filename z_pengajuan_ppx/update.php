<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\process_edit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method!';
    header("Location: dashboard.php");
    exit();
}

$no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx'] ?? '');

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data existing
$cek = mysqli_query($conn, "SELECT * FROM ppx_request WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "' LIMIT 1");
if (!$cek || mysqli_num_rows($cek) === 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}
$existing = mysqli_fetch_assoc($cek);

// Permission: pakai hasPermission
$nonEditableStatuses = ['Done','Finance','Direktur','Manager','Leader'];
$canUpdate = $auth->hasPermission('ppx', 'update');
$canCreate = $auth->hasPermission('ppx', 'create');

if (!$canUpdate && !$canCreate) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah pengajuan ini.';
    header("Location: dashboard.php");
    exit();
}

// User hanya punya create: hanya boleh edit milik sendiri
if ($canCreate && !$canUpdate) {
    if ($existing['user_id'] != $_SESSION['id_user'] && $existing['status'] !== 'Revisi') {
        $_SESSION['error'] = 'Anda tidak berhak mengedit pengajuan ini.';
        header("Location: dashboard.php");
        exit();
    }
}

// Status non-editable untuk user tanpa update permission
if (in_array($existing['status'], $nonEditableStatuses) && $existing['status'] !== 'Revisi' && !$canUpdate) {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit.';
    header("Location: dashboard.php");
    exit();
}

// Ambil input dari form (sesuai add.php)
$nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic'] ?? $existing['nama_pic']);
$jabatan = mysqli_real_escape_string($conn, $_POST['jabatan'] ?? $existing['jabatan']);
$divisi = mysqli_real_escape_string($conn, $_POST['divisi'] ?? $existing['divisi']);
$cabang = mysqli_real_escape_string($conn, $_POST['cabang'] ?? $existing['cabang']);

// Bank / Rekening (optional)
$nama_bank = mysqli_real_escape_string($conn, $_POST['nama_bank'] ?? $existing['nama_bank']);
$nama_rek  = mysqli_real_escape_string($conn, $_POST['nama_rek'] ?? $existing['nama_rek']);
$no_rek    = mysqli_real_escape_string($conn, $_POST['no_rek'] ?? $existing['no_rek']);

$alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan'] ?? $existing['alasan_pengajuan']);
$tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan'] ?? $existing['tingkat_kepentingan']);
$tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan'] ?? $existing['tanggal_dibutuhkan']);
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? $existing['status']);

// Ambil request_type dari data existing jika ada
$request_type = $existing['request_type'] ?? 'Regular';

// Setelah update berhasil, reset status sesuai aturan routing (tidak di-hardcode)
$routing_request_type = str_replace('_', ' ', $request_type);

// Cek apakah kolom request_type sudah ada di tabel routing
$col_check_rt = mysqli_query($conn, 
    "SELECT 1 FROM information_schema.columns 
     WHERE table_schema = DATABASE() AND table_name = 'ppx_approval_routing' 
     AND column_name = 'request_type'");
$has_rt_col = ($col_check_rt && mysqli_num_rows($col_check_rt) > 0);

if ($has_rt_col) {
    // Kolom ada, filter dengan request_type
    $routing_query = "SELECT initial_status 
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
    // Fallback jika tidak ada request_type
    $routing_query = "SELECT initial_status 
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
    // Status ketemu di tabel routing (prioritas)
    $status = $routing_row['initial_status'];
} else {
    // Fallback: Jika tidak ketemu, cek cabang via user data
    $user_id_existing = $existing['user_id'];
    $user_flow_query = "SELECT u.id_cabang, d.nama_divisi 
                        FROM user_dfm u 
                        LEFT JOIN divisi d ON u.divisi = d.id_divisi 
                        WHERE u.id_user = $user_id_existing";
    $user_flow_result = mysqli_query($conn, $user_flow_query);
    
    $status = 'Leader'; // Set default dulu
    if ($user_flow_result && mysqli_num_rows($user_flow_result) > 0) {
        $user_flow_data = mysqli_fetch_assoc($user_flow_result);
        $user_cabang_code = $user_flow_data['id_cabang'];
        
        if (!empty($user_cabang_code) && strtoupper($user_cabang_code) != 'PUSAT') {
            $check_kacab_q = "SELECT 1 FROM user_dfm u JOIN roles r ON u.id_role = r.id_role WHERE r.nama_role = 'kacab' AND u.id_cabang = '" . mysqli_real_escape_string($conn, $user_cabang_code) . "' LIMIT 1";
            $check_kacab_r = mysqli_query($conn, $check_kacab_q);
            if ($check_kacab_r && mysqli_num_rows($check_kacab_r) > 0) {
                $status = 'Kacab'; // Kalau dia di cabang dan ada Kacab
            }
        }
    }
}
mysqli_stmt_close($stmt_routing);

// Validasi minimal
$errors = [];
if (empty($nama_pic)) $errors[] = 'Nama PIC harus diisi';
if (empty($jabatan)) $errors[] = 'Jabatan harus diisi';
if (empty($divisi)) $errors[] = 'Divisi harus diisi';
if (empty($alasan_pengajuan)) $errors[] = 'Alasan pengajuan harus diisi';
if (empty($tingkat_kepentingan)) $errors[] = 'Tingkat kepentingan harus dipilih';
if (empty($tanggal_dibutuhkan)) $errors[] = 'Tanggal dibutuhkan harus diisi';

$today = date('Y-m-d');
if (!empty($tanggal_dibutuhkan) && $tanggal_dibutuhkan < $today) {
    $errors[] = 'Tanggal dibutuhkan harus hari ini atau setelahnya';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

// Handle upload_file (sama dengan add_insert)
$upload_file = $existing['upload_file'] ?? null;
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/ppx/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $origName = basename($_FILES['upload_file']['name']);
    $safeName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $origName);
    $fileName = $no_ppx . '_' . time() . '_' . $safeName;
    $uploadPath = $uploadDir . $fileName;
    $allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'Format file tidak didukung.';
        header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $uploadPath)) {
        // hapus lama jika ada
        if (!empty($existing['upload_file']) && file_exists($uploadDir . $existing['upload_file'])) {
            @unlink($uploadDir . $existing['upload_file']);
        }
        $upload_file = $fileName;
    } else {
        $_SESSION['error'] = 'Gagal mengupload file.';
        header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }
}

// Hitung total nominal dari detail (jika ada)
$total_nominal = 0;
$details = $_POST['detail'] ?? [];
if (is_array($details)) {
    foreach ($details as $d) {
        $harga = isset($d['harga_satuan']) ? (int)preg_replace('/[^0-9]/','', $d['harga_satuan']) : 0;
        $jumlah = isset($d['jumlah']) ? (int)$d['jumlah'] : 0;
        $total_nominal += ($harga * $jumlah);
    }
}

// Mulai transaction
mysqli_begin_transaction($conn);
try {
    // Update ppx_request
    $update_sql = "UPDATE ppx_request SET
        nama_pic = '". mysqli_real_escape_string($conn, $nama_pic) ."',
        jabatan = '". mysqli_real_escape_string($conn, $jabatan) ."',
        divisi = '". mysqli_real_escape_string($conn, $divisi) ."',
        cabang = '". mysqli_real_escape_string($conn, $cabang) ."',
        nama_bank = " . ($nama_bank !== '' ? "'" . mysqli_real_escape_string($conn, $nama_bank) . "'" : "NULL") . ",
        nama_rek  = " . ($nama_rek  !== '' ? "'" . mysqli_real_escape_string($conn, $nama_rek)  . "'" : "NULL") . ",
        no_rek    = " . ($no_rek    !== '' ? "'" . mysqli_real_escape_string($conn, $no_rek)    . "'" : "NULL") . ",
        alasan_pengajuan = '". mysqli_real_escape_string($conn, $alasan_pengajuan) ."',
        tingkat_kepentingan = '". mysqli_real_escape_string($conn, $tingkat_kepentingan) ."',
        tanggal_dibutuhkan = '". mysqli_real_escape_string($conn, $tanggal_dibutuhkan) ."',
        upload_file = " . ($upload_file ? "'". mysqli_real_escape_string($conn, $upload_file) ."'" : "NULL") . ",
        nominal_ppx = ". (int)$total_nominal .",
        status = '". mysqli_real_escape_string($conn, $status) ."',
        updated_at = NOW(),
        updated_by = '". mysqli_real_escape_string($conn, $_SESSION['nama_user'] ?? $nama_pic) ."'
        WHERE no_ppx = '". mysqli_real_escape_string($conn, $no_ppx) ."' LIMIT 1";

    if (!mysqli_query($conn, $update_sql)) {
        throw new Exception('Gagal update header PPX: ' . mysqli_error($conn));
    }

    // Hapus detail lama
    if (!mysqli_query($conn, "DELETE FROM ppx_request_detail WHERE no_ppx = '". mysqli_real_escape_string($conn, $no_ppx) ."'")) {
        throw new Exception('Gagal menghapus detail lama: ' . mysqli_error($conn));
    }

    // Insert detail baru (prepared)
    if (is_array($details) && count($details) > 0) {
        $sql_detail = "INSERT INTO ppx_request_detail (
            no_ppx, no_akun, rincian, jumlah, satuan, harga_satuan, total_harga, deskripsi, created_at, created_by, updated_at, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?)";
        $stmt = mysqli_prepare($conn, $sql_detail);
        if (!$stmt) throw new Exception('Prepare statement gagal (detail): ' . mysqli_error($conn));

        foreach ($details as $d) {
            $no_akun = trim($d['no_akun'] ?? '');
            $rincian = trim($d['rincian'] ?? '');
            $jumlah = (int)($d['jumlah'] ?? 0);
            $satuan = trim($d['satuan'] ?? '');
            $harga_satuan = (int)preg_replace('/[^0-9]/','', $d['harga_satuan'] ?? '0');
            $total_harga = $harga_satuan * $jumlah;
            $deskripsi = trim($d['deskripsi'] ?? '');
            $created_by = $_SESSION['nama_user'] ?? $nama_pic;
            $updated_by = $created_by;

            if (empty($no_akun) || empty($rincian) || $jumlah <= 0) {
                continue; // skip invalid
            }

            mysqli_stmt_bind_param($stmt, 'sssississs',
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

            // set bind values
            $bind_no_ppx = $no_ppx;
            $bind_no_akun = $no_akun;
            $bind_rincian = $rincian;
            $bind_jumlah = $jumlah;
            $bind_satuan = $satuan;
            $bind_harga_satuan = $harga_satuan;
            $bind_total_harga = $total_harga;
            $bind_deskripsi = $deskripsi;
            $bind_created_by = $created_by;
            $bind_updated_by = $updated_by;

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal menyimpan detail: ' . mysqli_stmt_error($stmt));
            }
        }

        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
    
    // TAMBAHAN: Potong budget kembali jika tipenya Monthly_Budget (setelah revisi)
    if ($request_type === 'Monthly_Budget' && !empty($existing['id_budget_item'])) {
        $id_item = $existing['id_budget_item'];
        
        error_log("PPX Update: Request $no_ppx updated from Revisi, re-deducting budget ($total_nominal)");
        
        // Cek sisa budget saat ini
        $q_cek_b = "SELECT remaining FROM ppx_monthly_budget_item WHERE id_budget_item = '$id_item'";
        $r_cek_b = mysqli_query($conn, $q_cek_b);
        if ($r_cek_b && $b_row = mysqli_fetch_assoc($r_cek_b)) {
            $rem = $b_row['remaining'];
            if ($rem < $total_nominal) {
                // Warning jika sisa tidak cukup (kasus jarang karena sudah divalidasi manual di add, tapi bagus untuk preventif)
                error_log("PPX Update: Warning - budget remaining ($rem) less than new nominal ($total_nominal)");
            }
            
            $update_b = "UPDATE ppx_monthly_budget_item 
                        SET remaining = remaining - $total_nominal,
                            updated_at = NOW()
                        WHERE id_budget_item = '$id_item'";
            
            if (mysqli_query($conn, $update_b)) {
                error_log("PPX Update: Budget deducted successfully");
            } else {
                error_log("PPX Update: Failed to deduct budget - " . mysqli_error($conn));
            }
        }
    }

    $_SESSION['success'] = 'Pengajuan PPX berhasil diperbarui.';
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
    exit();
}
?>