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

// --- DYNAMIC ROUTING: Tentukan next status dari ppx_approval_routing ---
include_once __DIR__ . '/../z_admin_permissions/permissions_config.php';

// Ambil data PPX: divisi, cabang, flow_type, status saat ini, nominal
$check_query = "SELECT 
    pr.status AS current_status,
    pr.divisi,
    pr.cabang,
    pr.flow_type,
    pr.cabang_user,
    COALESCE(NULLIF(pr.nominal_ppx, 0), COALESCE(SUM(prd.total_harga), 0)) AS total_nominal
FROM ppx_request pr
LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
WHERE pr.no_ppx = ?
GROUP BY pr.no_ppx, pr.nominal_ppx, pr.flow_type, pr.cabang_user, pr.status, pr.divisi, pr.cabang";

$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "s", $no_pengajuan);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$ppx_data = mysqli_fetch_assoc($check_result);
$current_status = $ppx_data['current_status'] ?? 'Leader';
$ppx_divisi = $ppx_data['divisi'] ?? '';
$ppx_cabang = $ppx_data['cabang'] ?? null;
$total_nominal = (float)($ppx_data['total_nominal'] ?? 0);
$flow_type = $ppx_data['flow_type'] ?? 'pusat';
$cabang_user = $ppx_data['cabang_user'] ?? null;
mysqli_stmt_close($check_stmt);

// Urutan standar status approval PPX
$STANDARD_FLOW = ['Leader', 'Kacab', 'Manager', 'Finance', 'Direktur', 'Uang Muka', 'Done'];

$status = 'Revisi'; // default jika reject

if ($action === 'approve') {
    // Cari next status berdasarkan routing table
    // Cek apakah ada routing entry untuk divisi dan status SELANJUTNYA
    $current_index = array_search($current_status, $STANDARD_FLOW);
    $next_status = null;

    if ($current_index !== false) {
        // Cari status berikutnya yang punya routing entry, ATAU cukup maju 1 langkah
        for ($i = $current_index + 1; $i < count($STANDARD_FLOW); $i++) {
            $candidate = $STANDARD_FLOW[$i];

            // Cek apakah ada routing entry untuk divisi ini di status candidate
            $route_check = "SELECT id FROM ppx_approval_routing 
                           WHERE divisi_id = ? AND initial_status = ? AND is_active = 1
                           LIMIT 1";
            $route_stmt = mysqli_prepare($conn, $route_check);
            $divisi_int = (int)$ppx_divisi;
            mysqli_stmt_bind_param($route_stmt, "is", $divisi_int, $candidate);
            mysqli_stmt_execute($route_stmt);
            $route_result = mysqli_stmt_get_result($route_stmt);
            $has_route = ($route_result && mysqli_num_rows($route_result) > 0);
            mysqli_stmt_close($route_stmt);

            if ($has_route) {
                // Ada approver untuk status ini → set sebagai next
                $next_status = $candidate;
                break;
            }

            // Jika tidak ada routing entry, skip status ini (tidak ada approver = otomatis di-skip)
        }

        // Jika tidak ketemu routing entry setelah current status, langsung ke Done/Selesai
        if ($next_status === null) {
            // Cek apakah current status sudah Finance atau Direktur → ke Done/Uang Muka
            if (in_array($current_status, ['Finance', 'Direktur'])) {
                $next_status = 'Uang Muka';
            } else {
                $next_status = 'Done';
            }
        }
    } else {
        // Status tidak dikenali di flow standard → fallback ke Finance
        $next_status = 'Finance';
    }

    $status = $next_status;
}

// Periksa kolom yang mungkin ada di tabel ppx_request
$possible_cols = [
    'created_at_leader','created_at_leader',
    'created_by_leader','created_by_leader',
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
$col_created_at_leader = null;
foreach (['created_at_leader','created_at_leader'] as $c) {
    if (in_array($c, $found)) { $col_created_at_leader = $c; break; }
}
$col_created_by_leader = null;
foreach (['created_by_leader','created_by_leader'] as $c) {
    if (in_array($c, $found)) { $col_created_by_leader = $c; break; }
}
$col_updated_at = in_array('updated_at', $found) ? 'updated_at' : null;
$col_updated_by = in_array('updated_by', $found) ? 'updated_by' : null;

// Bangun SET dynamic (tanpa catatan_approval)
$setParts = [];
$params = [];

// status (selalu ada)
$setParts[] = "status = ?";
$params[] = $status;

// Jika status Finance/Direktur/Manager/Kacab dan kolom created_at_leader ada -> set NOW()
if (in_array($status, ['Finance', 'Direktur', 'Manager', 'Kacab']) && $col_created_at_leader) {
    $setParts[] = "$col_created_at_leader = NOW()";
}
// Jika status Finance/Direktur/Manager/Kacab dan kolom created_by_leader ada -> set nama_user dari session
if (in_array($status, ['Finance', 'Direktur', 'Manager', 'Kacab']) && $col_created_by_leader) {
    $setParts[] = "$col_created_by_leader = ?";
    $params[] = $nama_user;
}

// updated_at & updated_by jika ada
if ($col_updated_at) {
    $setParts[] = "$col_updated_at = NOW()";
}
if ($col_updated_by) {
    $setParts[] = "$col_updated_by = ?";
    // gunakan nama_user (string) bukan id_user
    $params[] = $nama_user;
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
    // Pesan sukses dengan info routing dinamis
    if ($action === 'approve') {
        $success_messages = [
            'Kacab' => 'Pengajuan berhasil disetujui dan diteruskan ke Kepala Cabang.',
            'Manager' => 'Pengajuan berhasil disetujui dan diteruskan ke Manager Finance.',
            'Finance' => 'Pengajuan berhasil disetujui dan diteruskan ke GA untuk realisasi.',
            'Direktur' => 'Pengajuan berhasil disetujui dan diteruskan ke Direktur.'
        ];
        $_SESSION['success'] = $success_messages[$status] ?? 'Pengajuan berhasil diproses.';
    } else {
        $_SESSION['success'] = 'Pengajuan berhasil direvisi.';
    }
} else {
    $_SESSION['error'] = 'Gagal memproses pengajuan: ' . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);

header("Location: dashboard.php");
exit();
?>