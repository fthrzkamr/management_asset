<?php
/**
 * Script untuk mengecek dan memperbaiki pengajuan yang sudah Active tapi belum di-commit ke master
 * Jalankan file ini sekali untuk sinkronisasi data
 */

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/MasterBudgetService.php';

// Query untuk cek pengajuan yang Active tapi belum di-commit
$check_query = "SELECT 
    mb.id_budget,
    mb.budget_name,
    mb.period_month,
    mb.period_year,
    mb.master_id,
    mb.total_allocation,
    mb.master_allocated,
    mb.status,
    u.nama_user as creator_name
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
WHERE mb.status = 'Active' AND mb.master_allocated = 0
ORDER BY mb.period_year DESC, mb.period_month DESC";

$result = mysqli_query($conn, $check_query);

echo "<h2>Pengajuan Yang Sudah Active Tapi Belum Di-Commit ke Master Budget</h2>";
echo "<p>Total ditemukan: <strong>" . mysqli_num_rows($result) . "</strong> pengajuan</p>";

if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead><tr>
            <th>ID</th>
            <th>Periode</th>
            <th>Budget Name</th>
            <th>Master ID</th>
            <th>Total Alokasi</th>
            <th>Status</th>
            <th>Master Allocated</th>
            <th>Dibuat Oleh</th>
          </tr></thead><tbody>";
    
    $total_uncommitted = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id_budget'] . "</td>";
        echo "<td>" . str_pad($row['period_month'], 2, '0', STR_PAD_LEFT) . "/" . $row['period_year'] . "</td>";
        echo "<td>" . htmlspecialchars($row['budget_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['master_id'] ?? '-') . "</td>";
        echo "<td>Rp " . number_format($row['total_allocation'], 0, ',', '.') . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . ($row['master_allocated'] ? 'Ya' : '<strong style="color: red;">Tidak</strong>') . "</td>";
        echo "<td>" . htmlspecialchars($row['creator_name']) . "</td>";
        echo "</tr>";
        
        $total_uncommitted += $row['total_allocation'];
    }
    
    echo "</tbody></table>";
    
    echo "<div style='margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107;'>";
    echo "<h3>Total Dana Yang Belum Di-Commit: Rp " . number_format($total_uncommitted, 0, ',', '.') . "</h3>";
    echo "<p><strong>Penjelasan:</strong></p>";
    echo "<ul>";
    echo "<li>Pengajuan di atas sudah berstatus <strong>Active</strong> (disetujui)</li>";
    echo "<li>Namun dana belum di-deduct dari Master Budget (master_allocated = 0)</li>";
    echo "<li>Ini menyebabkan saldo Master Budget tidak berkurang meski ada pengajuan yang approved</li>";
    echo "</ul>";
    echo "<p><strong>Solusi:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Otomatis (Recommended):</strong> <a href='fix_uncommitted_budgets.php' style='padding: 8px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Commit Semua Pengajuan Active</a></li>";
    echo "<li><strong>Manual:</strong> Finance harus melakukan commit untuk setiap periode yang pending di dashboard Master Budget</li>";
    echo "</ol>";
    echo "</div>";
    
} else {
    echo "<div style='padding: 15px; background: #d4edda; border: 1px solid #28a745; color: #155724;'>";
    echo "<p><strong>✓ Semua pengajuan yang Active sudah di-commit ke Master Budget.</strong></p>";
    echo "<p>Tidak ada masalah sinkronisasi.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Saldo Master Budget Saat Ini</h3>";

$master_query = "SELECT master_id, balance, amount_in, amount_out, description FROM master_budget ORDER BY tanggal DESC";
$master_result = mysqli_query($conn, $master_query);

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<thead><tr>
        <th>Master ID</th>
        <th>Deskripsi</th>
        <th>Pemasukan</th>
        <th>Pengeluaran</th>
        <th>Saldo</th>
      </tr></thead><tbody>";

while ($master = mysqli_fetch_assoc($master_result)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($master['master_id']) . "</td>";
    echo "<td>" . htmlspecialchars($master['description']) . "</td>";
    echo "<td>Rp " . number_format($master['amount_in'], 0, ',', '.') . "</td>";
    echo "<td>Rp " . number_format($master['amount_out'], 0, ',', '.') . "</td>";
    echo "<td><strong>Rp " . number_format($master['balance'], 0, ',', '.') . "</strong></td>";
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div style='margin-top: 30px; padding: 15px; background: #e7f3ff; border: 1px solid #2196F3;'>";
echo "<h4>Cara Kerja Sistem (Pending-First Flow):</h4>";
echo "<ol>";
echo "<li>User cabang mengajukan budget bulanan → Status: <strong>pending</strong>, master_allocated = 0</li>";
echo "<li>Manager menyetujui → Status: <strong>Active</strong>, tapi master_allocated masih 0 (dana belum di-deduct)</li>";
echo "<li><strong>Finance commit di Master Budget dashboard</strong> → Deduct dana dari master, set master_allocated = 1</li>";
echo "</ol>";
echo "<p><strong>Catatan:</strong> Jika ada pengajuan Active yang belum di-commit, saldo Master Budget tidak akan berkurang.</p>";
echo "</div>";
?>
