<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

header('Content-Type: application/json');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu!']);
    exit();
}

$no_ppx = $_POST['no_ppx'] ?? '';
if (empty($no_ppx)) {
    echo json_encode(['success' => false, 'message' => 'No. PPX tidak valid!']);
    exit();
}

// Ambil data pengajuan
$stmt = mysqli_prepare($conn, "SELECT * FROM ppx_request WHERE no_ppx = ?");
mysqli_stmt_bind_param($stmt, "s", $no_ppx);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pengajuan = mysqli_fetch_assoc($result);

if (!$pengajuan) {
    echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan!']);
    exit();
}

// Cek owner
$user_id = (int)$_SESSION['id_user'];
$canDelete = $auth->hasPermission('ppx', 'delete');
$canCreate = $auth->hasPermission('ppx', 'create');

if (!$canDelete && !$canCreate) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses hapus!']);
    exit();
}

if (!$canDelete) {
    // Hanya bisa hapus milik sendiri yang status awal
    if ((int)$pengajuan['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'message' => 'Anda hanya bisa menghapus pengajuan milik Anda sendiri!']);
        exit();
    }

    $first_stage_keywords = ['asmen finance', 'leader', 'kacab', 'hr', 'operasional', 'revisi'];
    $status_lower = strtolower(trim($pengajuan['status']));
    if (!in_array($status_lower, $first_stage_keywords)) {
        echo json_encode(['success' => false, 'message' => 'Pengajuan dengan status "' . htmlspecialchars($pengajuan['status']) . '" sudah diproses dan tidak dapat dihapus!']);
        exit();
    }
}

// Proses hapus
mysqli_begin_transaction($conn);
try {
    // Hapus detail dulu
    $stmt_d = mysqli_prepare($conn, "DELETE FROM ppx_request_detail WHERE no_ppx = ?");
    mysqli_stmt_bind_param($stmt_d, "s", $no_ppx);
    mysqli_stmt_execute($stmt_d);

    // Hapus ppx_request utama
    $stmt_p = mysqli_prepare($conn, "DELETE FROM ppx_request WHERE no_ppx = ?");
    mysqli_stmt_bind_param($stmt_p, "s", $no_ppx);
    mysqli_stmt_execute($stmt_p);

    mysqli_commit($conn);

    echo json_encode(['success' => true, 'message' => 'Pengajuan ' . $no_ppx . ' berhasil dihapus!']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
}
