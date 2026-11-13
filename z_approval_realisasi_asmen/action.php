<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('approval_realisasi_asmen', 'approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$asmen_id       = (int)$_SESSION['id_user'];
$id_real_detail = (int)($_POST['id_real_detail'] ?? 0);
$id_budget      = (int)($_POST['id_budget'] ?? 0);
$id_realisasi   = (int)($_POST['id_realisasi'] ?? 0);
$action         = trim($_POST['action'] ?? ''); // 'Disetujui' atau 'Ditolak'
$catatan        = mysqli_real_escape_string($conn, trim($_POST['catatan_approval'] ?? ''));
$redirect_back  = trim($_POST['redirect_to'] ?? 'dashboard.php');

// Whitelist redirect
if (!preg_match('/^(dashboard|detail_user|detail)\.php/', $redirect_back)) {
    $redirect_back = 'dashboard.php';
}

if (!in_array($action, ['Disetujui', 'Ditolak'])) {
    $_SESSION['error'] = 'Aksi tidak valid.';
    header("Location: $redirect_back");
    exit();
}

if ($action === 'Ditolak' && empty($catatan)) {
    $_SESSION['error'] = 'Catatan wajib diisi saat menolak realisasi.';
    header("Location: $redirect_back");
    exit();
}

mysqli_begin_transaction($conn);

try {
    $status_safe = mysqli_real_escape_string($conn, $action);

    // Kasus 1: Action untuk 1 item realisasi spesifik (id_real_detail)
    if ($id_real_detail > 0) {
        $chk = mysqli_query($conn, "SELECT rd.*, mbd.id_category 
                                    FROM ppx_realisasi_detail2 rd
                                    LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
                                    WHERE rd.id_real_detail = $id_real_detail LIMIT 1");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            throw new Exception("Data realisasi tidak ditemukan.");
        }
        $rd = mysqli_fetch_assoc($chk);

        if ($rd['status'] !== 'Menunggu Approval') {
            throw new Exception("Realisasi item ini sudah diproses sebelumnya (Status: {$rd['status']}).");
        }

        $b_id = (int)$rd['id_budget'];
        $nominal = (float)$rd['nominal_realisasi'];
        $id_cat = (int)($rd['id_category'] ?? 0);

        // Update record realisasi detail
        $sql_up = "UPDATE ppx_realisasi_detail2 SET
                    status           = '$status_safe',
                    catatan_approval = '$catatan',
                    approved_by      = $asmen_id,
                    approved_at      = NOW(),
                    updated_at       = NOW()
                   WHERE id_real_detail = $id_real_detail";
        if (!mysqli_query($conn, $sql_up)) {
            throw new Exception("Gagal update realisasi: " . mysqli_error($conn));
        }

        // Jika disetujui, update budget terpakai & sisa
        if ($action === 'Disetujui' && $nominal > 0) {
            mysqli_query($conn, "UPDATE ppx_monthly_budget 
                SET total_used = total_used + $nominal,
                    total_remaining = GREATEST(0, total_remaining - $nominal)
                WHERE id_budget = $b_id");

            if ($id_cat > 0) {
                mysqli_query($conn, "UPDATE ppx_monthly_budget_item 
                    SET used_amount = used_amount + $nominal,
                        remaining = GREATEST(0, remaining - $nominal)
                    WHERE id_budget = $b_id AND id_category = $id_cat");
            }
        }

        // Sync ppx_realisasi_monthly header
        syncMonthlyHeader($conn, $b_id);

        mysqli_commit($conn);
        $_SESSION['success'] = "Realisasi item #{$rd['no_realisasi']} berhasil " . strtolower($action) . "!";
        header("Location: $redirect_back");
        exit();

    } elseif ($id_budget > 0 && $action === 'Disetujui') {
        // Kasus 2: Setujui semua item yang menunggu dalam satu budget
        $chk_all = mysqli_query($conn, "SELECT rd.*, mbd.id_category 
                                        FROM ppx_realisasi_detail2 rd
                                        LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
                                        WHERE rd.id_budget = $id_budget AND rd.status = 'Menunggu Approval'");
        $cnt = 0;
        while ($rd = mysqli_fetch_assoc($chk_all)) {
            $rd_id = (int)$rd['id_real_detail'];
            $nominal = (float)$rd['nominal_realisasi'];
            $id_cat = (int)($rd['id_category'] ?? 0);

            mysqli_query($conn, "UPDATE ppx_realisasi_detail2 SET
                        status           = 'Disetujui',
                        catatan_approval = '$catatan',
                        approved_by      = $asmen_id,
                        approved_at      = NOW(),
                        updated_at       = NOW()
                       WHERE id_real_detail = $rd_id");

            if ($nominal > 0) {
                mysqli_query($conn, "UPDATE ppx_monthly_budget 
                    SET total_used = total_used + $nominal,
                        total_remaining = GREATEST(0, total_remaining - $nominal)
                    WHERE id_budget = $id_budget");

                if ($id_cat > 0) {
                    mysqli_query($conn, "UPDATE ppx_monthly_budget_item 
                        SET used_amount = used_amount + $nominal,
                            remaining = GREATEST(0, remaining - $nominal)
                        WHERE id_budget = $id_budget AND id_category = $id_cat");
                }
            }
            $cnt++;
        }

        syncMonthlyHeader($conn, $id_budget);

        mysqli_commit($conn);
        $_SESSION['success'] = "$cnt realisasi item berhasil disetujui sekaligus!";
        header("Location: $redirect_back");
        exit();

    } elseif ($id_realisasi > 0) {
        // Kasus 3: Fallback ke header id_realisasi lama
        $chk_h = mysqli_query($conn, "SELECT id_budget FROM ppx_realisasi_monthly WHERE id_realisasi = $id_realisasi LIMIT 1");
        if ($chk_h && $h = mysqli_fetch_assoc($chk_h)) {
            $b_id = (int)$h['id_budget'];
            
            // Update detail-detail yang menunggu
            $q_items = mysqli_query($conn, "SELECT rd.*, mbd.id_category 
                                            FROM ppx_realisasi_detail2 rd
                                            LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
                                            WHERE (rd.id_realisasi = $id_realisasi OR rd.id_budget = $b_id)
                                              AND rd.status = 'Menunggu Approval'");
            while ($rd = mysqli_fetch_assoc($q_items)) {
                $rd_id = (int)$rd['id_real_detail'];
                $nominal = (float)$rd['nominal_realisasi'];
                $id_cat = (int)($rd['id_category'] ?? 0);

                mysqli_query($conn, "UPDATE ppx_realisasi_detail2 SET
                            status           = '$status_safe',
                            catatan_approval = '$catatan',
                            approved_by      = $asmen_id,
                            approved_at      = NOW(),
                            updated_at       = NOW()
                           WHERE id_real_detail = $rd_id");

                if ($action === 'Disetujui' && $nominal > 0) {
                    mysqli_query($conn, "UPDATE ppx_monthly_budget 
                        SET total_used = total_used + $nominal,
                            total_remaining = GREATEST(0, total_remaining - $nominal)
                        WHERE id_budget = $b_id");

                    if ($id_cat > 0) {
                        mysqli_query($conn, "UPDATE ppx_monthly_budget_item 
                            SET used_amount = used_amount + $nominal,
                                remaining = GREATEST(0, remaining - $nominal)
                            WHERE id_budget = $b_id AND id_category = $id_cat");
                    }
                }
            }

            syncMonthlyHeader($conn, $b_id);
            mysqli_commit($conn);
            $_SESSION['success'] = "Realisasi anggaran berhasil diproses ($action).";
            header("Location: $redirect_back");
            exit();
        }
    }

    throw new Exception("Parameter approval tidak lengkap.");

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Gagal memproses approval: ' . $e->getMessage();
    header("Location: $redirect_back");
    exit();
}

// Helper sync header ppx_realisasi_monthly
function syncMonthlyHeader($conn, $id_budget) {
    $q_sum = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN status = 'Disetujui' THEN nominal_realisasi ELSE 0 END) as total_approved,
        COUNT(CASE WHEN status = 'Menunggu Approval' THEN 1 END) as pending_cnt,
        COUNT(CASE WHEN status = 'Ditolak' THEN 1 END) as rejected_cnt
    FROM ppx_realisasi_detail2 WHERE id_budget = $id_budget");
    $data = mysqli_fetch_assoc($q_sum);
    $tot_app = (float)($data['total_approved'] ?? 0);
    $pend_cnt = (int)($data['pending_cnt'] ?? 0);
    $rej_cnt = (int)($data['rejected_cnt'] ?? 0);

    $hdr_status = 'Draft';
    if ($pend_cnt > 0) {
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
                         WHERE id_budget = $id_budget");
}
