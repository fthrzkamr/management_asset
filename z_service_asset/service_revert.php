<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_service_asset\service_revert.php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->hasPermission('pengajuan_service', 'delete')) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk membatalkan service!']);
        exit();
    }
    header('Location: dashboard.php');
    exit();
}

if (!isset($_SESSION['id_user'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
        exit();
    }
    header('Location: ../login.php');
    exit();
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$id = $_POST['id'] ?? $_GET['id'] ?? null;
if (empty($id) || !is_numeric($id)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit();
    }
    header('Location: dashboard.php?error=id_not_found');
    exit();
}

// Ensure service exists
$q = "SELECT id_service, kode_aset, status_service FROM service_asset WHERE id_service = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $q);
if (!$stmt) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gagal memproses']);
        exit();
    }
    header('Location: dashboard.php?error=service_not_found');
    exit();
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $sid, $kode_aset, $status_service);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Data service tidak ditemukan']);
        exit();
    }
    header('Location: dashboard.php?error=service_not_found');
    exit();
}

// If already not 'Service', nothing to do
if (trim($status_service ?? '') !== 'Service') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Service tidak dalam status aktif']);
        exit();
    }
    header('Location: dashboard.php?error=service_not_found');
    exit();
}

// VALIDASI: Cek apakah service sudah terhubung dengan PPX
// Ambil no_service dari service yang akan dibatalkan
$getNoServiceSql = "SELECT no_service FROM service_asset WHERE id_service = ? LIMIT 1";
$stmtNoService = mysqli_prepare($conn, $getNoServiceSql);
mysqli_stmt_bind_param($stmtNoService, 'i', $id);
mysqli_stmt_execute($stmtNoService);
mysqli_stmt_bind_result($stmtNoService, $no_service);
mysqli_stmt_fetch($stmtNoService);
mysqli_stmt_close($stmtNoService);

if (!empty($no_service)) {
    // Cek apakah no_service ini sudah digunakan di ppx_request
    // Cek dua kemungkinan nama kolom: no_service atau no_pengajuan_service
    $checkPPXSql = "SELECT COUNT(*) as total FROM ppx_request 
                    WHERE no_service = ? OR no_pengajuan_service = ? LIMIT 1";
    $stmtCheckPPX = mysqli_prepare($conn, $checkPPXSql);
    if ($stmtCheckPPX) {
        mysqli_stmt_bind_param($stmtCheckPPX, 'ss', $no_service, $no_service);
        mysqli_stmt_execute($stmtCheckPPX);
        mysqli_stmt_bind_result($stmtCheckPPX, $ppx_count);
        mysqli_stmt_fetch($stmtCheckPPX);
        mysqli_stmt_close($stmtCheckPPX);
        
        if ($ppx_count > 0) {
            // Service sudah terhubung dengan PPX, tidak bisa dibatalkan
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'Service tidak dapat dibatalkan karena sudah terhubung dengan pengajuan PPX. Silakan batalkan PPX terlebih dahulu jika diperlukan.'
                ]);
                exit();
            }
            header('Location: dashboard.php?error=service_linked_to_ppx');
            exit();
        }
    }
}

$revertSql = "UPDATE service_asset SET status_service = 'Dibatalkan', tanggal_selesai = NULL, biaya_service = NULL WHERE id_service = ? LIMIT 1";
$s2 = mysqli_prepare($conn, $revertSql);
if ($s2) {
    mysqli_stmt_bind_param($s2, 'i', $id);
    if (mysqli_stmt_execute($s2)) {
        mysqli_stmt_close($s2);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Service dibatalkan dan status dikembalikan']);
            exit();
        }
        header('Location: dashboard.php?success=service_reverted');
        exit();
    }
    mysqli_stmt_close($s2);
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Gagal membatalkan service']);
    exit();
}
header('Location: dashboard.php?error=service_not_found');
exit();
?>