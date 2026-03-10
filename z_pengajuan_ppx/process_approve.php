<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\process_approval.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || !$auth->canApprove()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

// Terima dari POST atau GET
$no_pengajuan = $_POST['no_pengajuan'] ?? $_GET['no_ppx'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? 'approve';
$user_id = (int)($_SESSION['id_user'] ?? 0);
$nama_user = $_SESSION['nama_user'] ?? '';

if (!$no_pengajuan) {
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Tentukan status baru: approve -> Manager, else -> Revisi
$status = ($action === 'approve') ? 'Manager' : 'Revisi';

// Periksa kolom yang mungkin ada di tabel ppx_request
$possible_cols = [
    'create_at_leader','created_at_leader',
    'create_by_leader','created_by_leader',
    'updated_at','updated_by'
];
$in_list = "'" . implode("','", $possible_cols) . "'";
$col_sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ppx_request' AND column_name IN ($in_list)";
$col_res = mysqli_query($conn, $col_sql);

$found = [];
if ($col_res) {
    while ($r = mysqli_fetch_assoc($col_res)) {
        $found[] = $r['column_name'];
    }
}

// Pilih nama kolom yang ada (prioritas pada versi tanpa 'd' jika keduanya ada)
$col_create_at_leader = null;
foreach (['create_at_leader','created_at_leader'] as $c) {
    if (in_array($c, $found)) { $col_create_at_leader = $c; break; }
}
$col_create_by_leader = null;
foreach (['create_by_leader','created_by_leader'] as $c) {
    if (in_array($c, $found)) { $col_create_by_leader = $c; break; }
}
$col_updated_at = in_array('updated_at', $found) ? 'updated_at' : null;
$col_updated_by = in_array('updated_by', $found) ? 'updated_by' : null;

// Bangun SET dynamic (tanpa catatan_approval)
$setParts = [];
$params = [];

// status (selalu ada)
$setParts[] = "status = ?";
$params[] = $status;

// Jika status Manager dan kolom create_at_leader ada -> set NOW()
if ($status === 'Manager' && $col_create_at_leader) {
    $setParts[] = "$col_create_at_leader = NOW()";
}
// Jika status Manager dan kolom create_by_leader ada -> set nama_user dari session
if ($status === 'Manager' && $col_create_by_leader) {
    $setParts[] = "$col_create_by_leader = ?";
    $params[] = $nama_user;
}

// updated_at & updated_by jika ada
if ($col_updated_at) {
    $setParts[] = "$col_updated_at = NOW()";
}
if ($col_updated_by) {
    $setParts[] = "$col_updated_by = ?";
    $params[] = $user_id;
}

$set_sql = implode(",\n    ", $setParts);
$sql = "UPDATE ppx_request SET\n    $set_sql\nWHERE no_ppx = ?";

$params[] = $no_pengajuan;

// Siapkan statement
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $_SESSION['error'] = 'Gagal menyiapkan query: ' . mysqli_error($conn);
    header("Location: dashboard.php");
    exit();
}

// Bind dynamic params
$types = '';
foreach ($params as $p) {
    $types .= is_int($p) ? 'i' : 's';
}
$tmp = array_merge([$types], $params);
// Convert to references untuk call_user_func_array
$refs = [];
foreach ($tmp as $key => $value) {
    $refs[$key] = &$tmp[$key];
}
call_user_func_array([$stmt, 'bind_param'], $refs);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = 'Pengajuan berhasil diproses.';
} else {
    $_SESSION['error'] = 'Gagal memproses pengajuan: ' . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);

header("Location: dashboard.php");
exit();
?>