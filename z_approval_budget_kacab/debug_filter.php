<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { die("Belum login"); }

echo "<style>body{font-family:sans-serif;padding:20px} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ccc;padding:6px}</style>";
echo "<h2>Debug: Session & Filter Cabang Kacab</h2>";

// 1. Tampilkan session values
echo "<h3>1. Nilai Session</h3><table>";
$keys = ['id_user','nama_user','id_role','nama_role','cabang','id_cabang','divisi'];
foreach ($keys as $k) {
    $v = $_SESSION[$k] ?? 'NOT SET';
    echo "<tr><td><code>$k</code></td><td><strong>" . htmlspecialchars(var_export($v, true)) . "</strong></td></tr>";
}
echo "</table>";

// 2. Ambil data user dari DB
echo "<h3>2. Data User di Database</h3>";
$uid = $_SESSION['id_user'];
$r = mysqli_query($conn, "SELECT u.id_user, u.nama_user, u.id_cabang, mc.nama_cabang, u.divisi, r.nama_role 
    FROM user_dfm u 
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    LEFT JOIN roles r ON u.id_role = r.id_role
    WHERE u.id_user = $uid");
$user = mysqli_fetch_assoc($r);
echo "<table><tr><th>Field</th><th>Nilai</th></tr>";
foreach ($user as $k => $v) {
    echo "<tr><td><code>$k</code></td><td>" . htmlspecialchars($v ?? 'NULL') . "</td></tr>";
}
echo "</table>";

// 3. Simulasikan query dashboard dengan session id_cabang
$id_cabang_session = intval($_SESSION['id_cabang'] ?? 0);
echo "<h3>3. Simulasi Query Dashboard (id_cabang = $id_cabang_session)</h3>";

$q = mysqli_query($conn, "SELECT mb.id_budget, mb.budget_name, mb.status, mb.budget_type,
    u.nama_user, u.id_cabang as user_cabang, mc.nama_cabang
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    WHERE mb.status = 'Kacab'
    AND u.id_cabang = $id_cabang_session
    ORDER BY mb.created_at DESC LIMIT 10");

echo "<p>Query dengan filter <code>u.id_cabang = $id_cabang_session</code>: " . mysqli_num_rows($q) . " baris</p>";
echo "<table><tr><th>ID</th><th>Nama Budget</th><th>Status</th><th>Type</th><th>Creator</th><th>id_cabang</th><th>Cabang</th></tr>";
while ($row = mysqli_fetch_assoc($q)) {
    echo "<tr><td>{$row['id_budget']}</td><td>{$row['budget_name']}</td><td>{$row['status']}</td><td>{$row['budget_type']}</td><td>{$row['nama_user']}</td><td>{$row['user_cabang']}</td><td>{$row['nama_cabang']}</td></tr>";
}
echo "</table>";

// 4. Semua data status Kacab tanpa filter
echo "<h3>4. SEMUA Data Status 'Kacab' (tanpa filter cabang)</h3>";
$q2 = mysqli_query($conn, "SELECT mb.id_budget, mb.budget_name, mb.status,
    u.nama_user, u.id_cabang as user_cabang, mc.nama_cabang
    FROM ppx_monthly_budget mb
    LEFT JOIN user_dfm u ON mb.created_by = u.id_user
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    WHERE mb.status = 'Kacab'
    ORDER BY mb.created_at DESC LIMIT 15");
echo "<table><tr><th>ID</th><th>Nama Budget</th><th>Creator</th><th>id_cabang creator</th><th>Nama Cabang</th></tr>";
while ($row = mysqli_fetch_assoc($q2)) {
    $match = ($row['user_cabang'] == $id_cabang_session) ? ' style="background:#d4edda"' : ' style="background:#f8d7da"';
    echo "<tr$match><td>{$row['id_budget']}</td><td>{$row['budget_name']}</td><td>{$row['nama_user']}</td><td>{$row['user_cabang']}</td><td>{$row['nama_cabang']}</td></tr>";
}
echo "</table><small>Hijau = sesuai cabang kacab ini, Merah = cabang lain</small>";
echo "<hr><a href='dashboard.php'>← Kembali</a>&nbsp;<small>(Hapus file ini setelah debug)</small>";
?>
