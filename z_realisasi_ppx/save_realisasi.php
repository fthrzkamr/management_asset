<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('realisasi_ppx', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_budget'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id        = (int)$_SESSION['id_user'];
$is_admin       = $auth->isSuperAdmin();
$id_budget      = (int)$_POST['id_budget'];
$id_detail      = (int)($_POST['id_detail'] ?? 0);
$id_real_detail = !empty($_POST['id_real_detail']) ? (int)$_POST['id_real_detail'] : 0;
$action         = trim($_POST['action'] ?? 'submit'); // 'draft', 'submit', 'delete'

$redirect = "detail.php?id_budget=$id_budget";

// Pastikan budget ada dan dapat diakses user
$mb_q = mysqli_query($conn, "SELECT id_budget, created_by, period_month, period_year, status FROM ppx_monthly_budget WHERE id_budget = $id_budget LIMIT 1");
if (!$mb_q || mysqli_num_rows($mb_q) === 0) {
    $_SESSION['error'] = 'Anggaran bulanan tidak ditemukan.';
    header("Location: dashboard.php");
    exit();
}
$budget = mysqli_fetch_assoc($mb_q);
if (!$is_admin && (int)$budget['created_by'] !== $user_id) {
    $_SESSION['error'] = 'Akses ditolak. Anda bukan pembuat anggaran ini.';
    header("Location: dashboard.php");
    exit();
}

// Handler delete draft
if ($action === 'delete') {
    if ($id_real_detail > 0) {
        $chk_draft = mysqli_query($conn, "SELECT id_real_detail, status, bukti_files FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
        if ($chk_draft && $d = mysqli_fetch_assoc($chk_draft)) {
            if ($d['status'] === 'Draft' || $is_admin) {
                // Hapus file jika ada
                if (!empty($d['bukti_files'])) {
                    foreach (explode(',', $d['bukti_files']) as $bf) {
                        $bf = trim($bf);
                        $fpath = __DIR__ . '/../uploads/realisasi/' . $bf;
                        if ($bf && file_exists($fpath)) {
                            @unlink($fpath);
                        }
                    }
                }
                mysqli_query($conn, "DELETE FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail");
                $_SESSION['success'] = 'Draft realisasi berhasil dihapus.';
            } else {
                $_SESSION['error'] = 'Hanya draft yang dapat dihapus.';
            }
        }
    }
    header("Location: $redirect");
    exit();
}

// Pastikan item detail valid untuk budget ini
if ($id_detail <= 0) {
    $_SESSION['error'] = 'Detail item anggaran tidak valid.';
    header("Location: $redirect");
    exit();
}
$it_q = mysqli_query($conn, "SELECT id, rincian, total FROM ppx_monthly_budget_item_detail WHERE id = $id_detail AND id_budget = $id_budget LIMIT 1");
if (!$it_q || mysqli_num_rows($it_q) === 0) {
    $_SESSION['error'] = 'Rincian item anggaran tidak ditemukan dalam anggaran ini.';
    header("Location: $redirect");
    exit();
}
$item_budget = mysqli_fetch_assoc($it_q);

$nom_input    = str_replace('.', '', $_POST['nominal_realisasi'] ?? '0');
$uk_input     = str_replace('.', '', $_POST['uang_kembali'] ?? '0');
$nom_real     = max(0, (float)$nom_input);
$uang_kembali = max(0, (float)$uk_input);
$catatan      = trim($_POST['catatan'] ?? '');
$tanggal_real = !empty($_POST['tanggal_realisasi']) ? trim($_POST['tanggal_realisasi']) : date('Y-m-d');

if ($nom_real <= 0 && $uang_kembali <= 0) {
    $_SESSION['error'] = 'Nominal realisasi harus diisi lebih dari 0.';
    header("Location: $redirect");
    exit();
}

$status_baru = ($action === 'draft') ? 'Draft' : 'Menunggu Approval';

// Generator No Realisasi
function generateNoRealDetail($conn) {
    $prefix = 'REAL-' . date('Ym') . '-';
    $r = mysqli_query($conn, "SELECT no_realisasi FROM ppx_realisasi_detail2 WHERE no_realisasi LIKE '{$prefix}%' ORDER BY id_real_detail DESC LIMIT 1");
    $seq = 1;
    if ($r && mysqli_num_rows($r) > 0) {
        $last = mysqli_fetch_assoc($r)['no_realisasi'];
        $seq = (int)substr($last, -4) + 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

$uploadDir   = __DIR__ . '/../uploads/realisasi/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size    = 5 * 1024 * 1024; // 5MB

mysqli_begin_transaction($conn);
try {
    // Pastikan atau buat header di ppx_realisasi_monthly
    $chk_h = mysqli_query($conn, "SELECT id_realisasi FROM ppx_realisasi_monthly WHERE id_budget = $id_budget AND user_id = {$budget['created_by']} LIMIT 1");
    if ($chk_h && mysqli_num_rows($chk_h) > 0) {
        $id_realisasi = (int)mysqli_fetch_assoc($chk_h)['id_realisasi'];
    } else {
        $no_h = 'M-REAL-' . date('Ym') . '-' . str_pad($id_budget, 4, '0', STR_PAD_LEFT);
        $bln = (int)$budget['period_month'];
        $thn = (int)$budget['period_year'];
        mysqli_query($conn, "INSERT INTO ppx_realisasi_monthly (no_realisasi, id_budget, bulan, tahun, user_id, status, created_at, updated_at) 
                             VALUES ('$no_h', $id_budget, $bln, $thn, {$budget['created_by']}, 'Draft', NOW(), NOW())");
        $id_realisasi = mysqli_insert_id($conn);
    }

    // Cek record existing jika sedang edit draft / ditolak
    $existing = null;
    if ($id_real_detail > 0) {
        $chk_ex = mysqli_query($conn, "SELECT * FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
        if ($chk_ex && mysqli_num_rows($chk_ex) > 0) {
            $existing = mysqli_fetch_assoc($chk_ex);
            if (!in_array($existing['status'], ['Draft', 'Ditolak']) && !$is_admin) {
                throw new Exception("Realisasi ini sedang diproses atau sudah disetujui, tidak dapat diedit.");
            }
        }
    }

    // Upload file bukti baru
    $new_files = [];
    if (!empty($_FILES['bukti']['name'][0])) {
        $fcount = count($_FILES['bukti']['name']);
        if ($fcount > 5) {
            throw new Exception("Maksimal 5 file lampiran sekaligus.");
        }

        for ($f = 0; $f < $fcount; $f++) {
            if ($_FILES['bukti']['error'][$f] !== UPLOAD_ERR_OK) continue;
            $orig = basename($_FILES['bukti']['name'][$f]);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                throw new Exception("Format file tidak didukung: $orig (gunakan JPG, PNG, atau PDF).");
            }
            if ($_FILES['bukti']['size'][$f] > $max_size) {
                throw new Exception("File terlalu besar: $orig (maksimal 5MB per file).");
            }
            $fname = 'rl_' . $id_detail . '_' . time() . "_{$f}." . $ext;
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

    if ($action === 'submit' && empty($merged_files) && $nom_real > 0) {
        throw new Exception("Bukti nota / kwitansi wajib diupload saat mengirim realisasi ke Asmen.");
    }

    $bukti_safe    = mysqli_real_escape_string($conn, implode(',', $merged_files));
    $catatan_safe  = mysqli_real_escape_string($conn, $catatan);
    $tgl_safe      = mysqli_real_escape_string($conn, $tanggal_real);
    $st_safe       = mysqli_real_escape_string($conn, $status_baru);

    if ($existing) {
        // Update existing record
        $sql = "UPDATE ppx_realisasi_detail2 SET
                    id_realisasi = $id_realisasi,
                    tanggal_realisasi = '$tgl_safe',
                    nominal_realisasi = $nom_real,
                    uang_kembali = $uang_kembali,
                    catatan = '$catatan_safe',
                    bukti_files = '$bukti_safe',
                    status = '$st_safe',
                    catatan_approval = NULL,
                    updated_at = NOW()
                WHERE id_real_detail = $id_real_detail";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Gagal update realisasi: " . mysqli_error($conn));
        }
    } else {
        // Insert new realization record for this detail item
        $no_real = mysqli_real_escape_string($conn, generateNoRealDetail($conn));
        $sql = "INSERT INTO ppx_realisasi_detail2 
                    (id_realisasi, id_budget, no_realisasi, id_detail_budget, tanggal_realisasi,
                     nominal_realisasi, uang_kembali, catatan, bukti_files, status, created_by, created_at, updated_at)
                VALUES 
                    ($id_realisasi, $id_budget, '$no_real', $id_detail, '$tgl_safe',
                     $nom_real, $uang_kembali, '$catatan_safe', '$bukti_safe', '$st_safe', $user_id, NOW(), NOW())";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Gagal simpan realisasi: " . mysqli_error($conn));
        }
    }

    // Sync header status dan total nominal realisasi
    $q_sum = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN status = 'Disetujui' THEN nominal_realisasi ELSE 0 END) as total_approved,
        COUNT(CASE WHEN status = 'Menunggu Approval' THEN 1 END) as pending_cnt,
        COUNT(CASE WHEN status = 'Ditolak' THEN 1 END) as rejected_cnt,
        COUNT(*) as total_cnt
    FROM ppx_realisasi_detail2 WHERE id_budget = $id_budget");
    $sum_data = mysqli_fetch_assoc($q_sum);
    $tot_app = (float)($sum_data['total_approved'] ?? 0);
    $pending_cnt = (int)($sum_data['pending_cnt'] ?? 0);
    $rej_cnt = (int)($sum_data['rejected_cnt'] ?? 0);

    $hdr_status = 'Draft';
    if ($pending_cnt > 0) {
        $hdr_status = 'Menunggu Approval';
    } elseif ($tot_app > 0) {
        $hdr_status = 'Disetujui';
    } elseif ($rej_cnt > 0) {
        $hdr_status = 'Ditolak';
    }

    mysqli_query($conn, "UPDATE ppx_realisasi_monthly SET
                            total_nominal_realisasi = $tot_app,
                            status = '$hdr_status',
                            updated_at = NOW()
                         WHERE id_realisasi = $id_realisasi");

    mysqli_commit($conn);

    if ($action === 'submit') {
        $_SESSION['success'] = 'Realisasi untuk item "' . htmlspecialchars($item_budget['rincian']) . '" berhasil dikirim ke Asmen!';
    } else {
        $_SESSION['success'] = 'Draft realisasi untuk item "' . htmlspecialchars($item_budget['rincian']) . '" berhasil disimpan.';
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header("Location: $redirect");
exit();
