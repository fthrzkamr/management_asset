<?php
// filepath: z_approval_ppx_asmen/process_approve.php
// Approval Asmen Finance: Approve → Uang Muka, Revisi → Revisi
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn() || !$auth->canApprove()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = $_POST['no_pengajuan'] ?? $_GET['no_ppx'] ?? null;
$action       = $_POST['action']       ?? $_GET['action']  ?? 'approve';
$user_id      = (int)($_SESSION['id_user'] ?? 0);
$nama_user    = $_SESSION['nama_user'] ?? '';

if (!$no_pengajuan) {
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Validasi: PPX harus ada, status harus 'Asmen Finance', dan request_type = Regular
$validQ = mysqli_prepare($conn,
    "SELECT * FROM ppx_request WHERE no_ppx = ? LIMIT 1"
);
mysqli_stmt_bind_param($validQ, 's', $no_pengajuan);
mysqli_stmt_execute($validQ);
$validR = mysqli_stmt_get_result($validQ);
$ppx    = mysqli_fetch_assoc($validR);
mysqli_stmt_close($validQ);

if (!$ppx) {
    $_SESSION['error'] = 'Pengajuan PPX tidak ditemukan.';
    header("Location: dashboard.php");
    exit();
}

if ($ppx['status'] !== 'Asmen Finance') {
    $_SESSION['error'] = 'Pengajuan ini tidak dalam status Asmen Finance (status saat ini: ' . $ppx['status'] . ').';
    header("Location: dashboard.php");
    exit();
}

// Tentukan status baru
$new_status = ($action === 'approve') ? 'Uang Muka' : 'Revisi';

// Override dengan final_status jika approve
if ($action === 'approve') {
    $rt_val = isset($ppx['request_type']) ? mysqli_real_escape_string($conn, $ppx['request_type']) : 'Regular';
    $cab_val = mysqli_real_escape_string($conn, $ppx['cabang'] ?? '');
    $div_val = (int)($ppx['divisi'] ?? 0);

    $q_route = "SELECT final_status FROM ppx_approval_routing 
                WHERE divisi_id = $div_val 
                AND (cabang IS NULL OR cabang = '' OR cabang = '$cab_val')
                AND is_active = 1
                ORDER BY (cabang = '$cab_val') DESC, 
                         (request_type = '$rt_val') DESC LIMIT 1";
    $r_route = mysqli_query($conn, $q_route);
    if ($r_route && $row = mysqli_fetch_assoc($r_route)) {
        if (!empty($row['final_status'])) {
            if ($row['final_status'] === 'Asmen Finance') {
                $new_status = 'Selesai';
            } else {
                $new_status = $row['final_status'];
            }
        }
    }
}

// Cek kolom updated_at / updated_by
$col_q = "SELECT column_name FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'ppx_request'
           AND column_name IN ('updated_at','updated_by','created_at_asmen','created_by_asmen')";
$col_r  = mysqli_query($conn, $col_q);
$found_cols = [];
while ($c = mysqli_fetch_assoc($col_r)) $found_cols[] = $c['column_name'];

$setParts = ["status = ?"];
$params   = [$new_status];

if ($new_status === 'Uang Muka') {
    if (in_array('created_at_asmen', $found_cols)) {
        $setParts[] = "created_at_asmen = NOW()";
    }
    if (in_array('created_by_asmen', $found_cols)) {
        $setParts[] = "created_by_asmen = ?";
        $params[] = $nama_user;
    }
}
if (in_array('updated_at', $found_cols)) $setParts[] = "updated_at = NOW()";
if (in_array('updated_by', $found_cols)) {
    $setParts[] = "updated_by = ?";
    $params[] = $nama_user;
}

$params[] = $no_pengajuan;
$sql = "UPDATE ppx_request SET " . implode(", ", $setParts) . " WHERE no_ppx = ?";
$stmt = mysqli_prepare($conn, $sql);

$types = str_repeat('s', count($params));
$tmp = array_merge([$types], $params);
$refs = [];
foreach ($tmp as $k => $v) $refs[$k] = &$tmp[$k];
call_user_func_array([$stmt, 'bind_param'], $refs);

if (mysqli_stmt_execute($stmt)) {
    if ($action === 'approve') {
        $_SESSION['success'] = 'Pengajuan PPX berhasil disetujui. Status berubah ke Uang Muka — menunggu proses transfer oleh GA/Finance.';
    } else {
        $_SESSION['success'] = 'Pengajuan dikembalikan untuk revisi.';
    }
} else {
    $_SESSION['error'] = 'Gagal memproses: ' . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
header("Location: dashboard.php");
exit();
?>
