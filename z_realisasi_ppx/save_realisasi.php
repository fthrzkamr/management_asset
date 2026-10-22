<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('realisasi_ppx', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_budget']) || !isset($_POST['id_detail'])) {
    header("Location: dashboard.php"); exit();
}

$user_id      = (int)$_SESSION['id_user'];
$id_budget    = (int)$_POST['id_budget'];
$id_detail    = (int)$_POST['id_detail'];
$id_realisasi = !empty($_POST['id_realisasi']) ? (int)$_POST['id_realisasi'] : null;

$action       = trim($_POST['action'] ?? 'submit'); // Default 'submit'
$nom_input    = str_replace('.', '', $_POST['nominal_realisasi'] ?? '0');
$uk_input     = str_replace('.', '', $_POST['uang_kembali'] ?? '0');
$nom_real     = max(0, (float)$nom_input);
$uang_kembali = max(0, (float)$uk_input);
$catatan      = trim($_POST['catatan'] ?? '');

$redirect = "detail.php?id_budget=$id_budget";

$status_baru = ($action === 'draft') ? 'Draft' : 'Menunggu Approval';

// Generate no_realisasi
function generateNoReal($conn) {
    $prefix = 'REAL-' . date('Ym') . '-';
    $r = mysqli_query($conn, "SELECT no_realisasi FROM ppx_realisasi_monthly
        WHERE no_realisasi LIKE '{$prefix}%' ORDER BY id_realisasi DESC LIMIT 1");
    $seq = 1;
    if ($r && mysqli_num_rows($r) > 0) {
        $last = mysqli_fetch_assoc($r)['no_realisasi'];
        $seq  = (int)substr($last, -3) + 1;
    }
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

$uploadDir   = __DIR__ . '/../uploads/realisasi/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
$allowed_ext = ['jpg','jpeg','png','pdf'];
$max_size    = 5 * 1024 * 1024;

// Ambil info budget untuk bulan/tahun
$mb_q = mysqli_query($conn, "SELECT period_month, period_year FROM ppx_monthly_budget WHERE id_budget = $id_budget LIMIT 1");
if (!$mb_q || mysqli_num_rows($mb_q) === 0) {
    $_SESSION['error'] = 'Anggaran tidak ditemukan.';
    header("Location: dashboard.php"); exit();
}
$mb = mysqli_fetch_assoc($mb_q);
$bulan = (int)$mb['period_month'];
$tahun = (int)$mb['period_year'];

mysqli_begin_transaction($conn);
try {
    // ── Upsert header ppx_realisasi ─────────────────────────────────────
    // Jika tidak dikirim via POST, coba cari apakah sudah ada header untuk budget ini
    if (!$id_realisasi) {
        $chk_h = mysqli_query($conn, "SELECT id_realisasi FROM ppx_realisasi_monthly 
                                      WHERE id_budget = $id_budget AND user_id = $user_id LIMIT 1");
        if ($chk_h && mysqli_num_rows($chk_h) > 0) {
            $id_realisasi = (int)mysqli_fetch_assoc($chk_h)['id_realisasi'];
        }
    }

    if ($id_realisasi) {
        $st_safe = mysqli_real_escape_string($conn, $status_baru);
        // Note: total_nominal_realisasi di header idealnya adalah SUM(detail), 
        // tapi sementara kita update dengan nilai input ini atau biarkan dihitung di dashboard.
        mysqli_query($conn,
            "UPDATE ppx_realisasi_monthly SET status='$st_safe',
             updated_at=NOW()
             WHERE id_realisasi=$id_realisasi AND user_id=$user_id");
    } else {
        $no_real = mysqli_real_escape_string($conn, generateNoReal($conn));
        $st_safe = mysqli_real_escape_string($conn, $status_baru);
        mysqli_query($conn,
            "INSERT INTO ppx_realisasi_monthly
             (no_realisasi,id_budget,bulan,tahun,user_id,status,total_nominal_realisasi,created_at,updated_at)
             VALUES('$no_real',$id_budget,$bulan,$tahun,$user_id,'$st_safe',$nom_real,NOW(),NOW())");
        if (!mysqli_insert_id($conn)) throw new Exception("Gagal membuat record realisasi: " . mysqli_error($conn));
        $id_realisasi = mysqli_insert_id($conn);
    }

    // ── Upsert detail ppx_realisasi_detail2 ──────────────────────────────
    $chk = mysqli_query($conn, "SELECT id_real_detail, bukti_files FROM ppx_realisasi_detail2
        WHERE id_realisasi = $id_realisasi AND id_detail_budget = $id_detail LIMIT 1");
    $existing = ($chk && mysqli_num_rows($chk) > 0) ? mysqli_fetch_assoc($chk) : null;

    // Upload file bukti
    $new_files = [];
    if (!empty($_FILES['bukti']['name'][0])) {
        $fcount = count($_FILES['bukti']['name']);
        if ($fcount > 5) throw new Exception("Maksimal 5 file per item.");

        for ($f = 0; $f < $fcount; $f++) {
            if ($_FILES['bukti']['error'][$f] !== UPLOAD_ERR_OK) continue;
            $orig = basename($_FILES['bukti']['name'][$f]);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) throw new Exception("Format tidak diizinkan: $orig");
            if ($_FILES['bukti']['size'][$f] > $max_size) throw new Exception("File terlalu besar: $orig");
            $fname = 'rl_' . $id_detail . '_' . time() . "_{$f}.$ext";
            if (move_uploaded_file($_FILES['bukti']['tmp_name'][$f], $uploadDir . $fname)) {
                $new_files[] = $fname;
            }
        }
    }

    $old_bukti = $existing['bukti_files'] ?? '';
    $merged_files = array_values(array_filter(array_merge(
        $old_bukti ? explode(',', $old_bukti) : [],
        $new_files
    )));
    $bukti_safe = mysqli_real_escape_string($conn, implode(',', $merged_files));
    $catatan_safe = mysqli_real_escape_string($conn, $catatan);

    if ($action === 'submit' && empty($merged_files) && $nom_real > 0) {
        throw new Exception("Bukti wajib diupload saat mengirim ke Asmen.");
    }

    if ($existing) {
        mysqli_query($conn,
            "UPDATE ppx_realisasi_detail2 SET
                nominal_realisasi=$nom_real, uang_kembali=$uang_kembali,
                catatan='$catatan_safe', bukti_files='$bukti_safe', updated_at=NOW()
             WHERE id_realisasi=$id_realisasi AND id_detail_budget=$id_detail");
    } else {
        mysqli_query($conn,
            "INSERT INTO ppx_realisasi_detail2
                (id_realisasi, id_detail_budget, id_budget, nominal_realisasi,
                 uang_kembali, catatan, bukti_files, created_at, updated_at)
             VALUES ($id_realisasi, $id_detail, $id_budget, $nom_real,
                 $uang_kembali, '$catatan_safe', '$bukti_safe', NOW(), NOW())");
        if (mysqli_error($conn)) throw new Exception("Gagal simpan detail: " . mysqli_error($conn));
    }

    mysqli_commit($conn);

    if ($action === 'submit') {
        $_SESSION['success'] = 'Realisasi berhasil dikirim ke Asmen!';
    } else {
        $_SESSION['success'] = 'Draft realisasi berhasil disimpan.';
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header("Location: $redirect");
exit();
