<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\process_approval.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Deteksi apakah request dari AJAX (fetch)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
// Atau cek Content-Type
if (!$isAjax && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $isAjax = true;
}

// Function untuk response JSON
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit();
}

// Cek login dan permission
if (!$auth->isLoggedIn() || (!$auth->canApprove() && !$auth->isFinance())) {
    if ($isAjax) {
        jsonResponse(false, 'Anda tidak memiliki akses untuk melakukan tindakan ini!');
    }
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

// Terima dari POST atau GET
$no_pengajuan = $_POST['no_pengajuan'] ?? $_POST['no_ppx'] ?? $_GET['no_ppx'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? 'approve';
$user_id = (int)($_SESSION['id_user'] ?? 0);
$nama_user = $_SESSION['nama_user'] ?? '';

if (!$no_pengajuan) {
    if ($isAjax) {
        jsonResponse(false, 'Nomor pengajuan tidak ditemukan!');
    }
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// ====== LOGIC PENENTUAN STATUS ======
$checked_post = $_POST['checked_detail'] ?? [];
$checked_ids = is_array($checked_post) ? array_map('intval', $checked_post) : [];

// Hitung total item pada ppx_request_detail
$total_items = 0;
$qCount = "SELECT COUNT(*) FROM ppx_request_detail WHERE no_ppx = ?";
$sCount = mysqli_prepare($conn, $qCount);
if ($sCount) {
    mysqli_stmt_bind_param($sCount, 's', $no_pengajuan);
    mysqli_stmt_execute($sCount);
    mysqli_stmt_bind_result($sCount, $c);
    if (mysqli_stmt_fetch($sCount)) $total_items = (int)$c;
    mysqli_stmt_close($sCount);
}

// Hitung berapa item yang SUDAH DIBAYAR sebelumnya (status_tf = 'sudah')
$already_paid = 0;
$qPaid = "SELECT COUNT(*) FROM ppx_request_detail WHERE no_ppx = ? AND status_tf = 'sudah'";
$sPaid = mysqli_prepare($conn, $qPaid);
if ($sPaid) {
    mysqli_stmt_bind_param($sPaid, 's', $no_pengajuan);
    mysqli_stmt_execute($sPaid);
    mysqli_stmt_bind_result($sPaid, $p);
    if (mysqli_stmt_fetch($sPaid)) $already_paid = (int)$p;
    mysqli_stmt_close($sPaid);
}

// Cek status PPX saat ini, divisi, dan cabang
$current_status = '';
$divisi_ppx = 0;
$cabang_ppx = '';
$query_status = "SELECT status, divisi, cabang FROM ppx_request WHERE no_ppx = ?";
$stmt_status = mysqli_prepare($conn, $query_status);
mysqli_stmt_bind_param($stmt_status, 's', $no_pengajuan);
mysqli_stmt_execute($stmt_status);
mysqli_stmt_bind_result($stmt_status, $current_status, $divisi_ppx, $cabang_ppx);
mysqli_stmt_fetch($stmt_status);
mysqli_stmt_close($stmt_status);

// Konversi divisi ke integer untuk perbandingan
$divisi_ppx = (int)$divisi_ppx;
$current_status = trim($current_status);
$cabang_ppx = strtolower(trim($cabang_ppx));

// STATUS LOGIC:
if ($action === 'approve') {
    // Verifikasi realisasi opsional (Status 'Realisasi') → langsung 'Selesai'
    if ($current_status === 'Realisasi') {
        $status = 'Selesai';
    }
    // Finance approve Finance AP Cibinong (divisi 9) dari status 'Finance' → langsung 'Selesai'
    elseif ($divisi_ppx == 9 && $cabang_ppx === 'cibinong' && $current_status === 'Finance') {
        $status = 'Selesai';
    }
    // Manager Finance approve Finance AP Cibinong dari status 'Manager' → 'Selesai'
    elseif ($divisi_ppx == 9 && $current_status === 'Manager') {
        $status = 'Selesai';
    }
    // Jika status saat ini "Selesai Realisasi" → Finance approve jadi "Selesai"
    elseif ($current_status === 'Selesai Realisasi') {
        $status = 'Selesai';
    }
    else {
        // Finance approve pengajuan awal divisi lain → set "Uang Muka"
        $status = 'Uang Muka';
    }
} else {
    $status = 'Revisi';
}
// ====== END LOGIC PENENTUAN STATUS ======

// Periksa kolom yang mungkin ada di tabel ppx_request
$possible_cols = [
    'created_at_finance',
    'created_by_finance',
    'updated_at',
    'updated_by'
];
$in_list = "'" . implode("','", $possible_cols) . "'";
$col_sql = "SELECT column_name FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = 'ppx_request' 
            AND column_name IN ($in_list)";
$col_res = mysqli_query($conn, $col_sql);

$found = [];
if ($col_res) {
    while ($r = mysqli_fetch_assoc($col_res)) {
        $found[] = $r['column_name'];
    }
}

// Pilih nama kolom yang ada
$col_created_at_finance = in_array('created_at_finance', $found) ? 'created_at_finance' : null;
$col_created_by_finance = in_array('created_by_finance', $found) ? 'created_by_finance' : null;
$col_updated_at = in_array('updated_at', $found) ? 'updated_at' : null;
$col_updated_by = in_array('updated_by', $found) ? 'updated_by' : null;

// Bangun SET dynamic
$setParts = [];
$params = [];

// status (selalu "Uang Muka" saat approve)
$setParts[] = "status = ?";
$params[] = $status;

// Set created_at_finance dan created_by_finance saat pertama kali approve
// Cek apakah ini approve pertama kali (created_at_finance masih NULL)
$isFirstApprove = false;
$qFirst = "SELECT created_at_finance FROM ppx_request WHERE no_ppx = ? LIMIT 1";
$sFirst = mysqli_prepare($conn, $qFirst);
if ($sFirst) {
    mysqli_stmt_bind_param($sFirst, 's', $no_pengajuan);
    mysqli_stmt_execute($sFirst);
    mysqli_stmt_bind_result($sFirst, $created_val);
    if (mysqli_stmt_fetch($sFirst)) {
        $isFirstApprove = empty($created_val);
    }
    mysqli_stmt_close($sFirst);
}

if ($isFirstApprove) {
    if ($col_created_at_finance) {
        $setParts[] = "$col_created_at_finance = NOW()";
    }
    if ($col_created_by_finance) {
        $setParts[] = "$col_created_by_finance = ?";
        $params[] = $nama_user;
    }
}

// updated_at & updated_by jika ada
if ($col_updated_at) {
    $setParts[] = "$col_updated_at = NOW()";
}
if ($col_updated_by) {
    $setParts[] = "$col_updated_by = ?";
    $params[] = $nama_user;
}

$set_sql = implode(",\n    ", $setParts);
$sql = "UPDATE ppx_request SET\n    $set_sql\nWHERE no_ppx = ?";
$params[] = $no_pengajuan;

// Siapkan statement
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $_SESSION['error'] = 'Gagal menyiapkan query: ' . mysqli_error($conn);
    header("Location: dashboard.php");
    exit();
}

// Bind dynamic params
$types = '';
foreach ($params as $p) {
    $types .= is_int($p) ? 'i' : 's';
}
$tmp = array_merge([$types], $params);
$refs = [];
foreach ($tmp as $key => $value) {
    $refs[$key] = &$tmp[$key];
}
call_user_func_array([$stmt, 'bind_param'], $refs);

if (mysqli_stmt_execute($stmt)) {
    $success_msg = ($status === 'Selesai') 
        ? 'PPX berhasil diapprove dan status diubah menjadi Selesai!' 
        : 'Berhasil diproses.';
    $_SESSION['success'] = $success_msg;

    // ====== UPDATE STATUS_TF PER ITEM ======
    $checked = $_POST['checked_detail'] ?? [];
    if (is_array($checked) && count($checked) > 0) {
        $ids = array_map('intval', $checked);

        mysqli_begin_transaction($conn);
        try {
            // PENTING: Jangan reset semua jadi 'belum'
            // Hanya update yang dicentang sekarang jadi 'sudah'
            // Yang sudah bayar sebelumnya tetap 'sudah', yang belum tetap 'belum'
            
            foreach ($ids as $detail_id) {
                $updItem = "UPDATE ppx_request_detail 
                            SET status_tf = 'sudah' 
                            WHERE no_ppx = ? 
                            AND no_ppx_detail = ?";
                
                $s2 = mysqli_prepare($conn, $updItem);
                if ($s2) {
                    mysqli_stmt_bind_param($s2, 'si', $no_pengajuan, $detail_id);
                    mysqli_stmt_execute($s2);
                    mysqli_stmt_close($s2);
                }
            }

            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log('Failed updating status_tf: ' . $e->getMessage());
        }
    }
    // ====== END UPDATE STATUS_TF ======

    // ====== SIMPAN DATA TRANSFER ======
    $nominal_tf_value = $_POST['nominal_tf_value'] ?? '';
    $ket_tf = $_POST['ket_tf'] ?? '';
    $uploadedNames = [];

    // Allowed extensions
    $allowed = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx'];

    // Handle multiple files
    $uploadDir = __DIR__ . '/../uploads/ppx_tf/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!empty($_FILES['upload_tf']) && isset($_FILES['upload_tf']['name'])) {
        if (is_array($_FILES['upload_tf']['name'])) {
            foreach ($_FILES['upload_tf']['error'] as $idx => $err) {
                if ($err !== UPLOAD_ERR_OK) continue;
                $orig = basename($_FILES['upload_tf']['name'][$idx]);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;
                $newName = $no_pengajuan . '_' . time() . '_' . $idx . '.' . $ext;
                if (move_uploaded_file($_FILES['upload_tf']['tmp_name'][$idx], $uploadDir . $newName)) {
                    $uploadedNames[] = $newName;
                }
            }
        } else {
            if ($_FILES['upload_tf']['error'] === UPLOAD_ERR_OK) {
                $orig = basename($_FILES['upload_tf']['name']);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $newName = $no_pengajuan . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['upload_tf']['tmp_name'], $uploadDir . $newName)) {
                        $uploadedNames[] = $newName;
                    }
                }
            }
        }
    }

    // Periksa kolom transfer di tabel
    $col_check_q = "SELECT column_name FROM information_schema.columns 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'ppx_request' 
                    AND column_name IN ('nominal_tf','ket_tf','upload_tf','uang_muka','nominal_realisasi')";
    $col_res = mysqli_query($conn, $col_check_q);
    $cols = [];
    if ($col_res) {
        while ($r = mysqli_fetch_assoc($col_res)) $cols[] = $r['column_name'];
    }

    if (!empty($cols)) {
        // Ambil nilai existing
        $existingVals = [];
        $qOld = "SELECT
            " . (in_array('uang_muka',$cols) ? 'COALESCE(uang_muka,0) AS uang_muka,' : '') . "
            " . (in_array('nominal_realisasi',$cols) ? 'COALESCE(nominal_realisasi,0) AS nominal_realisasi,' : '') . "
            " . (in_array('upload_tf',$cols) ? 'upload_tf' : 'NULL AS upload_tf') . "
            FROM ppx_request WHERE no_ppx = ? LIMIT 1";
        $sOld = mysqli_prepare($conn, $qOld);
        if ($sOld) {
            mysqli_stmt_bind_param($sOld, 's', $no_pengajuan);
            mysqli_stmt_execute($sOld);
            $resOld = mysqli_stmt_get_result($sOld);
            if ($resOld && $rowOld = mysqli_fetch_assoc($resOld)) {
                $existingVals = $rowOld;
            }
            mysqli_stmt_close($sOld);
        }

        // Normalize nominal input
        $numTf = (int)preg_replace('/\D/', '', $nominal_tf_value);
        $parts = [];
        $params2 = [];
        $types2 = '';

        // ket_tf
        if (in_array('ket_tf', $cols)) {
            $parts[] = "ket_tf = ?";
            $params2[] = $ket_tf;
            $types2 .= 's';
        }

        // LOGIC: Selalu tambahkan ke uang_muka (tidak peduli full/cicilan)
        // Karena status selalu "Uang Muka" sampai pengaju melakukan Realisasi
        $existing_uang_muka = isset($existingVals['uang_muka']) ? (int)$existingVals['uang_muka'] : 0;
        
        if (in_array('uang_muka', $cols)) {
            $new_uang = $existing_uang_muka + $numTf;
            $parts[] = "uang_muka = ?";
            $params2[] = $new_uang;
            $types2 .= 'i';
        }

        // Append upload_tf
        if (in_array('upload_tf', $cols) && count($uploadedNames) > 0) {
            $existingArr = [];
            if (!empty($existingVals['upload_tf'])) {
                $dec = json_decode($existingVals['upload_tf'], true);
                if (is_array($dec)) {
                    $existingArr = $dec;
                } else {
                    $existingArr = array_filter(array_map('trim', explode(',', $existingVals['upload_tf'])));
                }
            }
            $merged = array_values(array_filter(array_unique(array_merge($existingArr, $uploadedNames))));
            $finalStored = json_encode($merged);
            $parts[] = "upload_tf = ?";
            $params2[] = $finalStored;
            $types2 .= 's';
        }

        if (!empty($parts)) {
            $sqlUpdateTf = "UPDATE ppx_request SET " . implode(', ', $parts) . " WHERE no_ppx = ?";
            $params2[] = $no_pengajuan;
            $types2 .= 's';

            $stmtTf = mysqli_prepare($conn, $sqlUpdateTf);
            if ($stmtTf) {
                $tmp = array_merge([$types2], $params2);
                $refs = [];
                foreach ($tmp as $k => $v) $refs[$k] = &$tmp[$k];
                call_user_func_array([$stmtTf, 'bind_param'], $refs);
                mysqli_stmt_execute($stmtTf);
                mysqli_stmt_close($stmtTf);
            }
        }
    }
    // ====== END SIMPAN DATA TRANSFER ======

    // Response untuk AJAX
    if ($isAjax) {
        jsonResponse(true, $success_msg);
    }

} else {
    $error_msg = 'Gagal memproses pengajuan: ' . mysqli_stmt_error($stmt);
    $_SESSION['error'] = $error_msg;
    
    // Response untuk AJAX
    if ($isAjax) {
        jsonResponse(false, $error_msg);
    }
}

mysqli_stmt_close($stmt);

header("Location: dashboard.php");
exit();
?>