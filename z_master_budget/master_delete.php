<?php
/**
 * Master Budget Delete
 * Hapus master budget jika belum digunakan (amount_out = 0)
 */

// Start session jika belum

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Cek permission
$auth->requirePermission('master_budget', 'delete');

// Validasi parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php?error=' . urlencode('Master ID tidak valid'));
    exit();
}

$master_id = mysqli_real_escape_string($conn, trim($_GET['id']));

// Debug log
error_log("Master Budget Delete - User: " . ($_SESSION['username'] ?? 'unknown') . ", Master ID: " . $master_id);

try {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Cek apakah master budget exists dan belum digunakan
    $check_query = "SELECT master_id, amount_out, description 
                    FROM master_budget 
                    WHERE master_id = ? 
                    FOR UPDATE";
    
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $master_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Cek apakah sudah digunakan
        if ($row['amount_out'] > 0) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($conn);
            header('Location: dashboard.php?error=' . urlencode('Master Budget tidak dapat dihapus karena dana sudah digunakan'));
            exit();
        }
        
        mysqli_stmt_close($stmt);
        
        // Hapus log terlebih dahulu (karena ada FK constraint)
        $delete_log_query = "DELETE FROM master_budget_log WHERE master_id = ?";
        $stmt_log = mysqli_prepare($conn, $delete_log_query);
        mysqli_stmt_bind_param($stmt_log, "s", $master_id);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);
        
        // Hapus master budget
        $delete_query = "DELETE FROM master_budget WHERE master_id = ?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, "s", $master_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
        
        // Commit transaction
        mysqli_commit($conn);
        
        header('Location: dashboard.php?success=' . urlencode('Master Budget berhasil dihapus'));
        exit();
        
    } else {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        header('Location: dashboard.php?error=' . urlencode('Master Budget tidak ditemukan'));
        exit();
    }
    
} catch (Exception $e) {
    if (isset($conn) && mysqli_ping($conn)) {
        mysqli_rollback($conn);
    }
    error_log("Error deleting master budget: " . $e->getMessage());
    header('Location: dashboard.php?error=' . urlencode('Terjadi kesalahan: ' . $e->getMessage()));
    exit();
}

// Close connection
if (isset($conn)) {
    mysqli_close($conn);
}
