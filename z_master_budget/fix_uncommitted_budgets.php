<?php
/**
 * Script untuk commit pengajuan yang sudah Active tapi belum di-deduct dari master
 * PERHATIAN: Script ini akan mengubah data! Pastikan backup database terlebih dahulu.
 */

session_start();
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/MasterBudgetService.php';

$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn()) {
    die('Error: Anda harus login terlebih dahulu.');
}

if (!$auth->isFinance() && !$auth->isManagerFinance() && !$auth->isSuperAdmin()) {
    die('Error: Hanya Finance yang dapat menjalankan script ini.');
}

$budgetService = new MasterBudgetService($conn);
$user_id = $_SESSION['id_user'];

// Ambil pengajuan yang Active tapi belum di-commit
$query = "SELECT 
    mb.id_budget,
    mb.budget_name,
    mb.period_month,
    mb.period_year,
    mb.master_id,
    mb.total_allocation,
    mb.status
FROM ppx_monthly_budget mb
WHERE mb.status = 'Active' AND mb.master_allocated = 0 AND mb.master_id IS NOT NULL AND mb.master_id != ''
ORDER BY mb.period_year ASC, mb.period_month ASC";

$result = mysqli_query($conn, $query);

echo "<h2>Commit Pengajuan Active ke Master Budget</h2>";
echo "<p>Ditemukan: <strong>" . mysqli_num_rows($result) . "</strong> pengajuan yang perlu di-commit</p>";

if (mysqli_num_rows($result) == 0) {
    echo "<div style='padding: 15px; background: #d4edda; border: 1px solid #28a745;'>";
    echo "<p>Tidak ada pengajuan yang perlu di-commit. Semua sudah sinkron!</p>";
    echo "</div>";
    echo "<p><a href='dashboard.php'>Kembali ke Dashboard</a></p>";
    exit();
}

// Jika ada POST request, proses commit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_commit'])) {
    echo "<h3>Memproses Commit...</h3>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 15px; border: 1px solid #ddd;'>";
    
    $success_count = 0;
    $error_count = 0;
    $total_deducted = 0;
    
    mysqli_data_seek($result, 0); // Reset pointer
    
    while ($row = mysqli_fetch_assoc($result)) {
        $id_budget = $row['id_budget'];
        $master_id = $row['master_id'];
        $amount = $row['total_allocation'];
        $budget_name = $row['budget_name'];
        
        echo "<br><strong>[" . date('H:i:s') . "]</strong> Processing Budget #{$id_budget} - {$budget_name} (Rp " . number_format($amount, 0, ',', '.') . ")...<br>";
        
        // Deduct dari master
        $deduct_result = $budgetService->deductFromMaster(
            $master_id,
            $amount,
            'MONTHLY_BUDGET',
            $id_budget,
            "Commit Budget: {$budget_name}",
            $user_id
        );
        
        if ($deduct_result['success']) {
            // Update master_allocated flag
            $update_query = "UPDATE ppx_monthly_budget 
                            SET master_allocated = 1, 
                                committed_at = NOW(), 
                                committed_by = ? 
                            WHERE id_budget = ?";
            
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'ii', $user_id, $id_budget);
            
            if (mysqli_stmt_execute($stmt)) {
                echo "  → <span style='color: green;'>✓ SUCCESS</span> - Saldo master setelah commit: Rp " . number_format($deduct_result['balance'], 0, ',', '.') . "<br>";
                $success_count++;
                $total_deducted += $amount;
            } else {
                echo "  → <span style='color: red;'>✗ ERROR updating flag</span>: " . mysqli_error($conn) . "<br>";
                $error_count++;
            }
            
            mysqli_stmt_close($stmt);
        } else {
            echo "  → <span style='color: red;'>✗ ERROR</span>: " . $deduct_result['message'] . "<br>";
            $error_count++;
        }
    }
    
    echo "</div>";
    
    echo "<div style='margin-top: 20px; padding: 15px; background: " . ($error_count > 0 ? '#fff3cd' : '#d4edda') . "; border: 1px solid " . ($error_count > 0 ? '#ffc107' : '#28a745') . ";'>";
    echo "<h3>Ringkasan</h3>";
    echo "<p>✓ Berhasil: <strong>{$success_count}</strong> pengajuan</p>";
    if ($error_count > 0) {
        echo "<p>✗ Gagal: <strong style='color: red;'>{$error_count}</strong> pengajuan</p>";
    }
    echo "<p>Total Dana Di-Commit: <strong>Rp " . number_format($total_deducted, 0, ',', '.') . "</strong></p>";
    echo "</div>";
    
    echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 20px;'>Lihat Dashboard Master Budget</a></p>";
    
} else {
    // Tampilkan preview dan form konfirmasi
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-top: 20px;'>";
    echo "<thead><tr>
            <th>ID Budget</th>
            <th>Periode</th>
            <th>Budget Name</th>
            <th>Master ID</th>
            <th>Total Alokasi</th>
          </tr></thead><tbody>";
    
    $total_to_commit = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id_budget'] . "</td>";
        echo "<td>" . str_pad($row['period_month'], 2, '0', STR_PAD_LEFT) . "/" . $row['period_year'] . "</td>";
        echo "<td>" . htmlspecialchars($row['budget_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['master_id']) . "</td>";
        echo "<td>Rp " . number_format($row['total_allocation'], 0, ',', '.') . "</td>";
        echo "</tr>";
        
        $total_to_commit += $row['total_allocation'];
    }
    
    echo "</tbody></table>";
    
    echo "<div style='margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107;'>";
    echo "<h3>⚠ PERHATIAN</h3>";
    echo "<p>Script ini akan:</p>";
    echo "<ol>";
    echo "<li>Men-deduct total <strong>Rp " . number_format($total_to_commit, 0, ',', '.') . "</strong> dari Master Budget</li>";
    echo "<li>Mengubah flag <code>master_allocated</code> menjadi 1 untuk pengajuan di atas</li>";
    echo "<li>Mencatat transaksi di <code>master_budget_log</code></li>";
    echo "</ol>";
    echo "<p><strong>Pastikan Anda sudah backup database sebelum melanjutkan!</strong></p>";
    echo "</div>";
    
    echo "<form method='POST' style='margin-top: 20px;'>";
    echo "<input type='hidden' name='confirm_commit' value='1'>";
    echo "<button type='submit' style='padding: 15px 30px; background: #dc3545; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;' onclick='return confirm(\"Apakah Anda yakin ingin commit semua pengajuan ini? Saldo master budget akan berkurang!\");'>Commit Semua Pengajuan</button>";
    echo " ";
    echo "<a href='dashboard.php' style='padding: 15px 30px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block;'>Batal</a>";
    echo "</form>";
}
?>
