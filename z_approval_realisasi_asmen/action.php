<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('approval_realisasi_asmen', 'approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: dashboard.php"); exit(); }

$asmen_id     = (int)$_SESSION['id_user'];
$id_realisasi = (int)($_POST['id_realisasi'] ?? 0);
$action       = trim($_POST['action'] ?? ''); // 'Disetujui' atau 'Ditolak'
$catatan      = mysqli_real_escape_string($conn, trim($_POST['catatan_approval'] ?? ''));
$redirect_back = trim($_POST['redirect_to'] ?? 'dashboard.php');
// Whitelist redirect
if (!preg_match('/^(dashboard|detail_user|detail)\.php/', $redirect_back)) {
    $redirect_back = 'dashboard.php';
}

if (!$id_realisasi || !in_array($action, ['Disetujui', 'Ditolak'])) {
    $_SESSION['error'] = 'Aksi tidak valid.';
    header("Location: dashboard.php"); exit();
}

if ($action === 'Ditolak' && !$catatan) {
    $_SESSION['error'] = 'Catatan wajib diisi saat menolak realisasi.';
    header("Location: $redirect_back"); exit();
}

// Pastikan status masih Menunggu Approval
$chk = mysqli_query($conn, "SELECT status FROM ppx_realisasi_monthly WHERE id_realisasi = $id_realisasi LIMIT 1");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $_SESSION['error'] = 'Data realisasi tidak ditemukan.';
    header("Location: dashboard.php"); exit();
}
$curr = mysqli_fetch_assoc($chk);
if ($curr['status'] !== 'Menunggu Approval') {
    $_SESSION['error'] = 'Realisasi ini sudah diproses sebelumnya.';
    header("Location: $redirect_back"); exit();
}

$status_safe = mysqli_real_escape_string($conn, $action);
$sql = "UPDATE ppx_realisasi_monthly SET
    status          = '$status_safe',
    catatan_approval= '$catatan',
    approved_by     = $asmen_id,
    approved_at     = NOW(),
    updated_at      = NOW()
WHERE id_realisasi = $id_realisasi";

if (mysqli_query($conn, $sql)) {
    if ($action === 'Disetujui') {
        // Ambil info realisasi untuk update budget
        $chk_budget = mysqli_query($conn, "SELECT id_budget, total_nominal_realisasi FROM ppx_realisasi_monthly WHERE id_realisasi = $id_realisasi");
        if ($chk_budget && $rb = mysqli_fetch_assoc($chk_budget)) {
            $id_budget = (int)$rb['id_budget'];
            $nominal = (float)$rb['total_nominal_realisasi'];
            
            // Update total_used dan total_remaining di ppx_monthly_budget
            mysqli_query($conn, "UPDATE ppx_monthly_budget 
                SET total_used = total_used + $nominal, 
                    total_remaining = total_remaining - $nominal 
                WHERE id_budget = $id_budget");
                
            // Update juga ppx_monthly_budget_item
            // Karena realisasi detail ada per-kategori, kita bisa aggregate berdasarkan `id_category`
            $q_cat = mysqli_query($conn, "SELECT rld.id_detail_budget, rld.nominal_realisasi, mbd.id_category 
                FROM ppx_realisasi_detail2 rld
                LEFT JOIN ppx_monthly_budget_item_detail mbd ON rld.id_detail_budget = mbd.id_detail
                WHERE rld.id_realisasi = $id_realisasi");
            if ($q_cat) {
                while($c = mysqli_fetch_assoc($q_cat)) {
                    $id_cat = (int)$c['id_category'];
                    $nom_cat = (float)$c['nominal_realisasi'];
                    mysqli_query($conn, "UPDATE ppx_monthly_budget_item 
                        SET used_amount = used_amount + $nom_cat,
                            remaining = remaining - $nom_cat
                        WHERE id_budget = $id_budget AND id_category = $id_cat");
                }
            }
        }
        
        $_SESSION['success'] = "Realisasi berhasil disetujui!";
        header("Location: $redirect_back"); exit();
    } else {
        $_SESSION['success'] = "Realisasi ditolak. Pengaju perlu memperbaiki dan mengirim ulang.";
        header("Location: $redirect_back"); exit();
    }
} else {
    $_SESSION['error'] = 'Gagal memproses: ' . mysqli_error($conn);
    header("Location: $redirect_back"); exit();
}
