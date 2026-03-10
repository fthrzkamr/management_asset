<?php

include_once(__DIR__ . '/../dist/config/config.php');

// Auth check
if (!isset($_SESSION['id_user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;

if ($month < 1 || $month > 12 || $year < 2000) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid period']);
    exit();
}

// Query master budgets yang aktif untuk periode tersebut
// Asumsi: master_budget memiliki kolom tanggal atau periode_bulan/periode_tahun
// Sesuaikan query dengan struktur tabel Anda

$query = "SELECT 
    vw.master_id, 
    vw.description, 
    vw.balance,
    mb.tanggal
FROM vw_master_budget_summary vw
LEFT JOIN master_budget mb ON vw.master_id = mb.master_id
WHERE vw.balance > 0 
    AND MONTH(mb.tanggal) = ? 
    AND YEAR(mb.tanggal) = ?
ORDER BY mb.tanggal DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $month, $year);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$budgets = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $budgets[] = [
            'master_id' => $row['master_id'],
            'description' => $row['description'],
            'balance' => $row['balance']
        ];
    }
}

mysqli_stmt_close($stmt);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'budgets' => $budgets,
    'period' => [
        'month' => $month,
        'year' => $year
    ]
]);
