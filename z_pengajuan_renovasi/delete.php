<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\delete.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Anda harus login terlebih dahulu!';
    header("Location: ../login.php");
    exit();
}

// Cek permission delete atau create
if (!$auth->hasPermission('pengajuan_renovasi', 'delete') && !$auth->hasPermission('pengajuan_renovasi', 'create')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk menghapus pengajuan!';
    header("Location: dashboard.php");
    exit();
}

$param = trim($_GET['no_pengajuan'] ?? $_GET['id'] ?? '');
if (empty($param)) {
    $_SESSION['error'] = 'ID pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$esc = mysqli_real_escape_string($conn, $param);
$where_find = is_numeric($param) 
    ? "(id = '$esc' OR no_pengajuan = '$esc')" 
    : "no_pengajuan = '$esc'";

$query = "SELECT * FROM renovation_request WHERE $where_find";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = 'Data pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$pengajuan = mysqli_fetch_assoc($result);
$no_pengajuan = $pengajuan['no_pengajuan'];

// Logika delete berdasarkan permission
$can_delete = false;
$error_message = '';
$canDelete = $auth->hasPermission('pengajuan_renovasi', 'delete');
$canCreate = $auth->hasPermission('pengajuan_renovasi', 'create');
$user_id = $_SESSION['id_user'];

if ($auth->isSuperAdmin() || $canDelete) {
    $can_delete = true;
} elseif ($canCreate) {
    if ($pengajuan['user_id'] != $user_id) {
        $error_message = 'Anda hanya bisa menghapus pengajuan milik Anda sendiri!';
    } elseif ($pengajuan['status'] != 'Pending') {
        $error_message = 'Hanya pengajuan berstatus Pending yang dapat dihapus!';
    } else {
        $can_delete = true;
    }
}

if ($can_delete) {
    // Cek apakah sudah ada PPX yang terkait
    $check_ppx = "SELECT no_ppx FROM ppx_request WHERE no_pengajuan_renovasi = ? LIMIT 1";
    $stmt_ppx = mysqli_prepare($conn, $check_ppx);
    if ($stmt_ppx) {
        mysqli_stmt_bind_param($stmt_ppx, "s", $no_pengajuan);
        mysqli_stmt_execute($stmt_ppx);
        $res_ppx = mysqli_stmt_get_result($stmt_ppx);
        $ppx_row = mysqli_fetch_assoc($res_ppx);
        if ($ppx_row) {
            $can_delete = false;
            $error_message = 'Pengajuan tidak dapat dihapus karena sudah terdapat pengajuan PPX terkait (No PPX: ' . htmlspecialchars($ppx_row['no_ppx']) . ').';
        }
        mysqli_stmt_close($stmt_ppx);
    }
}

if (!$can_delete) {
    $_SESSION['error'] = !empty($error_message) ? $error_message : 'Anda tidak memiliki akses untuk menghapus pengajuan ini!';
    header("Location: dashboard.php");
    exit();
}

// Hapus foto jika ada
if (!empty($pengajuan['foto_area'])) {
    $fotos = json_decode($pengajuan['foto_area'], true) ?: [];
    foreach ($fotos as $f) {
        $f_path = '../uploads/renovasi/' . basename($f);
        if (file_exists($f_path)) {
            @unlink($f_path);
        }
    }
}

$del_stmt = mysqli_prepare($conn, "DELETE FROM renovation_request WHERE no_pengajuan = ?");
if ($del_stmt) {
    mysqli_stmt_bind_param($del_stmt, "s", $no_pengajuan);
    if (mysqli_stmt_execute($del_stmt)) {
        $_SESSION['success'] = 'Pengajuan renovasi berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'Gagal menghapus pengajuan: ' . mysqli_stmt_error($del_stmt);
    }
    mysqli_stmt_close($del_stmt);
} else {
    $_SESSION['error'] = 'Gagal memproses penghapusan data.';
}

header("Location: dashboard.php");
exit();
