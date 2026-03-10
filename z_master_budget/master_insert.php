<?php
/**
 * Master Budget Insert
 * Create new master budget dengan initial top-up
 */

// Start session jika belum

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/MasterBudgetService.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Cek permission
$auth->requirePermission('master_budget', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

// Get POST data
$tanggal = mysqli_real_escape_string($conn, trim($_POST['tanggal'] ?? date('Y-m-d')));
$amount = floatval($_POST['amount'] ?? 0);
$has_carryover = isset($_POST['has_carryover']) ? 1 : 0;
$carryover_amount = $has_carryover ? floatval($_POST['carryover_amount'] ?? 0) : 0;
$description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
$created_by = $_SESSION['nama_user'] ?? $_SESSION['username'];

// Kategori: Kas Kecil / Kas Besar / Kas Besar Jakarta
$allowed_kategori = ['Kas Kecil', 'Kas Besar', 'Kas Besar BCA'];
$kategori = in_array($_POST['kategori'] ?? '', $allowed_kategori)
            ? $_POST['kategori'] : null;

// Calculate total amount (new + carryover)
$total_amount = $amount + $carryover_amount;

// Validasi
if ($amount <= 0) {
    header('Location: dashboard.php?error=' . urlencode('Jumlah dana baru harus lebih besar dari 0'));
    exit();
}

if (empty($kategori)) {
    header('Location: dashboard.php?error=' . urlencode('Kategori anggaran harus dipilih'));
    exit();
}

if ($has_carryover && $carryover_amount < 0) {
    header('Location: dashboard.php?error=' . urlencode('Jumlah sisa budget tidak valid'));
    exit();
}

if (empty($description)) {
    header('Location: dashboard.php?error=' . urlencode('Deskripsi tidak boleh kosong'));
    exit();
}

// Validate date format
$date_obj = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$date_obj || $date_obj->format('Y-m-d') !== $tanggal) {
    header('Location: dashboard.php?error=' . urlencode('Format tanggal tidak valid'));
    exit();
}

// Initialize service
$budgetService = new MasterBudgetService($conn);

// Create master budget with total amount (new + carryover)
$result = $budgetService->topupMasterBudget(
    null, // Master ID akan auto-generate
    $total_amount, // Use total amount including carryover
    $description,
    $created_by,
    'INIT-' . date('YmdHis'),
    $tanggal
);

// Jika berhasil, update kolom kategori (cek kolom defensif)
if ($result['success'] && !empty($result['master_id'])) {
    $chk_col = mysqli_query($conn,
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name='master_budget' AND column_name='kategori'");
    if ($chk_col && mysqli_num_rows($chk_col) > 0 && $kategori) {
        $new_master_id = $result['master_id'];
        $stmt_kat = mysqli_prepare($conn,
            "UPDATE master_budget SET kategori=? WHERE master_id=?");
        mysqli_stmt_bind_param($stmt_kat, 'ss', $kategori, $new_master_id);
        mysqli_stmt_execute($stmt_kat);
        mysqli_stmt_close($stmt_kat);
    }
}

if ($result['success']) {
    // Add note about carryover if applicable
    $successMsg = $result['message'];
    if ($has_carryover && $carryover_amount > 0) {
        $successMsg .= ' (Dana Baru: Rp ' . number_format($amount, 0, ',', '.') . 
                      ' + Sisa Bulan Lalu: Rp ' . number_format($carryover_amount, 0, ',', '.') . ')';
    }
    // If user requested to commit pending budget items individually, process them now
    $commit_budget_ids = isset($_POST['commit_budget_id']) && is_array($_POST['commit_budget_id']) ? $_POST['commit_budget_id'] : [];
    $commit_results = [];
    $new_master_id = $result['master_id'] ?? null;

    if (!empty($commit_budget_ids) && $new_master_id) {
        // Begin single transaction for all selected items
        mysqli_begin_transaction($conn);
        try {
            // Sanitize IDs
            $safe_ids = array_map('intval', $commit_budget_ids);
            $in_ids = implode(',', $safe_ids);
            
            // Lock and get pending budgets for selected IDs
            $sql = "SELECT id_budget, period_year, period_month, total_allocation, budget_name 
                    FROM ppx_monthly_budget 
                    WHERE id_budget IN ($in_ids) 
                    AND master_allocated = 0 
                    AND (master_id IS NULL OR master_id = '') 
                    AND status NOT IN ('Draft', 'Rejected', 'Closed')
                    FOR UPDATE";
            $res = mysqli_query($conn, $sql);
            if (!$res) {
                mysqli_rollback($conn);
                $commit_results[] = ['success' => false, 'message' => 'DB Error: ' . mysqli_error($conn) . ' Q=' . $sql];
            } else {
                $pending = [];
                $totalPending = 0;
                while ($r = mysqli_fetch_assoc($res)) {
                    $pending[] = $r;
                    $totalPending += floatval($r['total_allocation']);
                }

                if (count($pending) === 0) {
                    mysqli_rollback($conn);
                    $commit_results[] = ['success' => false, 'message' => 'Tidak ada pengajuan pending yang valid untuk ID: ' . $in_ids];
                } else {
                // Get IDs from pending items
                $pending_ids = array_map(function($p) { return intval($p['id_budget']); }, $pending);
                $pending_in_ids = implode(',', $pending_ids);

                // Assign master_id to these pending budgets
                $upd_assign = "UPDATE ppx_monthly_budget SET master_id = ? WHERE id_budget IN ($pending_in_ids)";
                $s2 = mysqli_prepare($conn, $upd_assign);
                mysqli_stmt_bind_param($s2, 's', $new_master_id);
                if (!mysqli_stmt_execute($s2)) {
                    mysqli_rollback($conn);
                    $commit_results[] = ['success' => false, 'message' => 'Gagal assign master_id: ' . mysqli_error($conn)];
                } else {
                    // Check sufficient balance
                    if (!$budgetService->hasSufficientBalance($new_master_id, $totalPending)) {
                        // revert assignment
                        $rev = "UPDATE ppx_monthly_budget SET master_id = NULL WHERE id_budget IN ($pending_in_ids)";
                        mysqli_query($conn, $rev);
                        mysqli_rollback($conn);
                        $commit_results[] = ['success' => false, 'message' => 'Saldo master tidak cukup. Dibutuhkan: Rp ' . number_format($totalPending,0,',','.')];
                    } else {
                        // Deduct from master
                        $deduct = $budgetService->deductFromMaster(
                            $new_master_id,
                            $totalPending,
                            'MONTHLY_ALLOCATION_COMMIT',
                            'MULTI-' . date('Ymd'),
                            'Commit alokasi ' . count($pending) . ' item saat pembuatan master',
                            $_SESSION['nama_user'] ?? ''
                        );

                        if (!$deduct['success']) {
                            // revert assignment
                            $rev = "UPDATE ppx_monthly_budget SET master_id = NULL WHERE id_budget IN ($pending_in_ids)";
                            mysqli_query($conn, $rev);
                            mysqli_rollback($conn);
                            $commit_results[] = ['success' => false, 'message' => 'Gagal deduct: ' . $deduct['message']];
                        } else {
                            // Mark budgets as allocated
                            $now = date('Y-m-d H:i:s');
                            $user = intval($_SESSION['id_user']);
                            $upd = "UPDATE ppx_monthly_budget SET master_allocated = 1, committed_at = ?, committed_by = ? WHERE id_budget IN ($pending_in_ids)";
                            $stmt3 = mysqli_prepare($conn, $upd);
                            mysqli_stmt_bind_param($stmt3, 'si', $now, $user);
                            if (!mysqli_stmt_execute($stmt3)) {
                                mysqli_rollback($conn);
                                $commit_results[] = ['success' => false, 'message' => 'Gagal update status pengajuan: ' . mysqli_error($conn)];
                            } else {
                                mysqli_commit($conn);
                                $commit_results[] = ['success' => true, 'message' => 'Berhasil commit ' . count($pending) . ' pengajuan, total: Rp ' . number_format($totalPending,0,',','.')];
                            }
                        }
                    }
                }
            }
            } // End else
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $commit_results[] = ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Build final success message including commit results
    if (!empty($commit_results)) {
        foreach ($commit_results as $cr) {
            if ($cr['success']) {
                $successMsg .= ' | ' . $cr['message'];
            } else {
                $successMsg .= ' | Error: ' . $cr['message'];
            }
        }
    }

    header('Location: dashboard.php?success=' . urlencode($successMsg));
} else {
    header('Location: dashboard.php?error=' . urlencode($result['message']));
}
exit;
