<?php
/**
 * Integration Example: PPX Realization dengan Master Budget Deduction
 * File ini menunjukkan bagaimana mengintegrasikan deduction dari master budget
 * ke dalam proses realisasi PPX di z_approval_ppx_finance/process_reimbursement.php
 */

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../z_master_budget/MasterBudgetService.php';

// Cek login dan role
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Only Finance can process reimbursement
$user_role = $_SESSION['role_id'];
if ($user_role != 3) { // Role Finance
    header('Location: dashboard.php?error=' . urlencode('Akses ditolak. Hanya Finance yang dapat memproses realisasi.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

// Get form data
$no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx']);
$id_budget = intval($_POST['id_budget'] ?? 0);
$id_category = intval($_POST['id_category'] ?? 0);
$total_approved = floatval($_POST['total_approved'] ?? 0);
$bukti_tf = $_FILES['bukti_tf'] ?? null;
$catatan_realisasi = mysqli_real_escape_string($conn, $_POST['catatan_realisasi'] ?? '');

// Validasi input
if (empty($no_ppx) || $total_approved <= 0) {
    header('Location: dashboard.php?error=' . urlencode('Data tidak valid'));
    exit();
}

// Get PPX data
$query_ppx = "SELECT p.*, pb.master_id, u.nama_user 
              FROM ppx p
              LEFT JOIN ppx_monthly_budget pb ON p.id_budget = pb.id_budget
              LEFT JOIN user_dfm u ON p.user_id = u.id_user
              WHERE p.no_ppx = ?";

$stmt = $conn->prepare($query_ppx);
$stmt->bind_param("s", $no_ppx);
$stmt->execute();
$result = $stmt->get_result();
$ppx_data = $result->fetch_assoc();
$stmt->close();

if (!$ppx_data) {
    header('Location: dashboard.php?error=' . urlencode('PPX tidak ditemukan'));
    exit();
}

$master_id = $ppx_data['master_id'];

// Validasi master_id tersedia
if (empty($master_id)) {
    header('Location: dashboard.php?error=' . urlencode('Master Budget tidak terhubung dengan anggaran bulanan ini'));
    exit();
}

// Initialize Master Budget Service
$budgetService = new MasterBudgetService($conn);

// Check if master has sufficient balance
if (!$budgetService->hasSufficientBalance($master_id, $total_approved)) {
    $master = $budgetService->getMasterBudget($master_id);
    $available = $master ? $master['balance'] : 0;
    header('Location: dashboard.php?error=' . urlencode(
        'Saldo Master Budget tidak cukup. Tersedia: Rp ' . number_format($available, 0, ',', '.') . 
        ', Dibutuhkan: Rp ' . number_format($total_approved, 0, ',', '.')
    ));
    exit();
}

// Handle file upload (bukti transfer)
$bukti_tf_filename = '';
if ($bukti_tf && $bukti_tf['error'] == UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/ppx_tf/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($bukti_tf['name'], PATHINFO_EXTENSION);
    $bukti_tf_filename = 'TF_' . $no_ppx . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $bukti_tf_filename;
    
    if (!move_uploaded_file($bukti_tf['tmp_name'], $upload_path)) {
        header('Location: dashboard.php?error=' . urlencode('Gagal upload bukti transfer'));
        exit();
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Update PPX status to 'Realisasi'
    $update_ppx = "UPDATE ppx 
                   SET status_finance = 'Approved',
                       status = 'Realisasi',
                       tanggal_realisasi = NOW(),
                       bukti_tf = ?,
                       catatan_realisasi = ?,
                       processed_by_finance = ?,
                       processed_at_finance = NOW()
                   WHERE no_ppx = ?";
    
    $stmt = $conn->prepare($update_ppx);
    $processed_by = $_SESSION['nama_user'];
    $stmt->bind_param("ssss", $bukti_tf_filename, $catatan_realisasi, $processed_by, $no_ppx);
    $stmt->execute();
    $stmt->close();
    
    // 2. Deduct from Master Budget (REALTIME dengan Row Locking)
    $deduct_result = $budgetService->deductFromMaster(
        $master_id,
        $total_approved,
        'PPX_REALISASI',
        $no_ppx,
        'Realisasi PPX: ' . $ppx_data['keterangan'] . ' - User: ' . $ppx_data['nama_user'],
        $_SESSION['nama_user']
    );
    
    if (!$deduct_result['success']) {
        throw new Exception('Gagal deduct dari Master Budget: ' . $deduct_result['message']);
    }
    
    // 3. Update ppx_monthly_budget_item (remaining balance)
    if ($id_budget > 0 && $id_category > 0) {
        $update_item = "UPDATE ppx_monthly_budget_item 
                        SET remaining = remaining - ?,
                            total_used = total_used + ?
                        WHERE id_budget = ? AND id_category = ?";
        
        $stmt = $conn->prepare($update_item);
        $stmt->bind_param("ddii", $total_approved, $total_approved, $id_budget, $id_category);
        $stmt->execute();
        $stmt->close();
        
        // 4. Insert to usage log
        $insert_log = "INSERT INTO ppx_budget_usage_log 
                       (id_budget, id_category, reference_type, reference_id, 
                        amount, balance_before, balance_after, note, created_by) 
                       SELECT ?, ?, 'PPX_REALISASI', ?, ?,
                              remaining + ?, remaining, 
                              CONCAT('Realisasi PPX: ', ?),
                              ?
                       FROM ppx_monthly_budget_item
                       WHERE id_budget = ? AND id_category = ?";
        
        $stmt = $conn->prepare($insert_log);
        $stmt->bind_param("iisddsssii", 
            $id_budget, $id_category, $no_ppx, $total_approved,
            $total_approved, $ppx_data['keterangan'], $_SESSION['nama_user'],
            $id_budget, $id_category
        );
        $stmt->execute();
        $stmt->close();
    }
    
    // 5. Insert notification/activity log (optional)
    $insert_activity = "INSERT INTO activity_log 
                        (user_id, action, description, created_at) 
                        VALUES (?, 'PPX_REALISASI', ?, NOW())";
    
    $stmt = $conn->prepare($insert_activity);
    $activity_desc = "Realisasi PPX {$no_ppx} sebesar Rp " . number_format($total_approved, 0, ',', '.') . 
                     " dari Master Budget {$master_id}. Balance tersisa: Rp " . 
                     number_format($deduct_result['balance'], 0, ',', '.');
    $stmt->bind_param("is", $_SESSION['user_id'], $activity_desc);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Success redirect
    header('Location: dashboard.php?success=' . urlencode(
        'Berhasil memproses realisasi PPX ' . $no_ppx . '. ' .
        'Dana Rp ' . number_format($total_approved, 0, ',', '.') . ' telah dipotong dari Master Budget ' . $master_id . '. ' .
        'Saldo Master tersisa: Rp ' . number_format($deduct_result['balance'], 0, ',', '.')
    ));
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    // Delete uploaded file if exists
    if (!empty($bukti_tf_filename)) {
        $file_path = __DIR__ . '/../uploads/ppx_tf/' . $bukti_tf_filename;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Error redirect
    header('Location: dashboard.php?error=' . urlencode('Error: ' . $e->getMessage()));
    exit();
}
