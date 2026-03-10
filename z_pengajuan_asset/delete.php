<?php
// filepath: c:\xampp\htdocs\asset_dfm\pengajuan_asset\delete.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Anda harus login terlebih dahulu!';
    header("Location: ../login.php");
    exit();
}

// Cek permission delete
if (!$auth->hasPermission('pengajuan_asset', 'delete') && !$auth->hasPermission('pengajuan_asset', 'create')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk menghapus pengajuan!';
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'ID pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = $_GET['id'];

// Ambil data pengajuan
$query = "SELECT * FROM asset_request WHERE no_pengajuan = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $no_pengajuan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pengajuan = mysqli_fetch_assoc($result);

if (!$pengajuan) {
    $_SESSION['error'] = 'Data pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Logika delete berdasarkan permission
$can_delete = false;
$error_message = '';
$canDelete = $auth->hasPermission('pengajuan_asset', 'delete');
$canCreate = $auth->hasPermission('pengajuan_asset', 'create');
$user_id = $_SESSION['id_user'];

if ($canDelete) {
    // Punya permission delete: bisa hapus semua, cek status saja
    $can_delete = true;
} elseif ($canCreate) {
    // Hanya punya create: hanya bisa hapus milik sendiri yang Pending
    if ($pengajuan['id_user'] != $user_id) {
        $error_message = 'Anda hanya bisa menghapus pengajuan milik Anda sendiri!';
    } elseif (strtolower($pengajuan['status']) != 'pending') {
        $error_message = 'Hanya pengajuan berstatus Pending yang dapat dihapus!';
    } else {
        $can_delete = true;
    }
}

if ($can_delete) {
    // Cek apakah sudah ada PPX yang terkait
    $check_ppx = "SELECT no_ppx FROM ppx_request WHERE no_pengajuan_asset = ? LIMIT 1";
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
    }
} else {
    if (empty($error_message)) $error_message = 'Anda tidak memiliki akses untuk menghapus pengajuan ini!';
}

// Jika tidak bisa delete, redirect dengan error
if (!$can_delete) {
    $_SESSION['error'] = $error_message;
    header("Location: dashboard.php");
    exit();
}

// Proses soft-delete langsung (tanpa POST confirmation)
mysqli_begin_transaction($conn);

try {
    // Tandai pengajuan sebagai terhapus
    $soft_delete = "UPDATE asset_request SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE no_pengajuan = ?";
    $stmt_soft = mysqli_prepare($conn, $soft_delete);
    
    if (!$stmt_soft) {
        throw new Exception("Gagal prepare soft-delete query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt_soft, "is", $_SESSION['id_user'], $no_pengajuan);
    
    if (!mysqli_stmt_execute($stmt_soft)) {
        throw new Exception("Gagal execute soft-delete: " . mysqli_stmt_error($stmt_soft));
    }

    mysqli_commit($conn);

    $_SESSION['success'] = 'Pengajuan berhasil dihapus.';
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Gagal menghapus pengajuan: ' . $e->getMessage();
    header("Location: dashboard.php");
    exit();
}