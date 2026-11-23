<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';

init_ppx_fase2_db($conn);
init_acc_fase3_db($conn);

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
$id_real_detail = !empty($_POST['id_real_detail']) ? (int)$_POST['id_real_detail'] : 0;
$action         = trim($_POST['action'] ?? 'submit'); // 'draft', 'submit', 'delete', 'request_delete', 'upload_nota_susulan'

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

// ─────────────────────────────────────────────────────────────────────────────
// 1. Handler Hapus Draft (Hard delete hanya untuk status Draft)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if ($id_real_detail > 0) {
        $chk_draft = mysqli_query($conn, "SELECT id_real_detail, status, bukti_files FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
        if ($chk_draft && $d = mysqli_fetch_assoc($chk_draft)) {
            if ($d['status'] === 'Draft' || $is_admin) {
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
                $_SESSION['success'] = 'Draft transaksi berhasil dihapus.';
            } else {
                $_SESSION['error'] = 'Hanya draft yang dapat langsung dihapus.';
            }
        }
    }
    header("Location: $redirect");
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Handler Permohonan Hapus Transaksi Disetujui (Fase 2 Item 8)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'request_delete') {
    $del_reason = trim($_POST['delete_reason'] ?? '');
    if (empty($del_reason)) {
        $_SESSION['error'] = 'Alasan pengajuan pembatalan/hapus transaksi wajib diisi.';
        header("Location: $redirect");
        exit();
    }
    if ($id_real_detail <= 0) {
        $_SESSION['error'] = 'ID transaksi tidak valid.';
        header("Location: $redirect");
        exit();
    }

    $chk = mysqli_query($conn, "SELECT id_real_detail, no_realisasi, status, delete_status FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        $_SESSION['error'] = 'Transaksi tidak ditemukan.';
        header("Location: $redirect");
        exit();
    }
    $d_tr = mysqli_fetch_assoc($chk);

    if ($d_tr['delete_status'] === 'Requested') {
        $_SESSION['error'] = 'Transaksi ini sudah dalam proses permohonan hapus ke Asmen Finance.';
        header("Location: $redirect");
        exit();
    }

    $dr_safe = mysqli_real_escape_string($conn, $del_reason);
    $up_dr = mysqli_query($conn, "UPDATE ppx_realisasi_detail2 SET
                                    delete_status = 'Requested',
                                    delete_reason = '$dr_safe',
                                    delete_requested_by = $user_id,
                                    delete_requested_at = NOW(),
                                    updated_at = NOW()
                                 WHERE id_real_detail = $id_real_detail");
    if ($up_dr) {
        $nom_txt = $d_tr['no_realisasi'] ?: ('ID #' . $id_real_detail);
        $_SESSION['success'] = "Permohonan pembatalan transaksi $nom_txt berhasil diajukan ke Asmen Finance.";
    } else {
        $_SESSION['error'] = "Gagal mengajukan pembatalan: " . mysqli_error($conn);
    }
    header("Location: $redirect");
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Handler Upload Nota Susulan (Fase 2 Item 9)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'upload_nota_susulan') {
    if ($id_real_detail <= 0) {
        $_SESSION['error'] = 'ID transaksi tidak valid.';
        header("Location: $redirect");
        exit();
    }

    $uploadDir   = __DIR__ . '/../uploads/realisasi/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size    = 5 * 1024 * 1024;
    $uploaded_ns = [];

    if (!empty($_FILES['nota_susulan']['name'][0])) {
        $fc = count($_FILES['nota_susulan']['name']);
        for ($f = 0; $f < $fc; $f++) {
            if ($_FILES['nota_susulan']['error'][$f] !== UPLOAD_ERR_OK) continue;
            $orig = basename($_FILES['nota_susulan']['name'][$f]);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                $_SESSION['error'] = "Format file tidak didukung: $orig (gunakan JPG, PNG, atau PDF).";
                header("Location: $redirect");
                exit();
            }
            if ($_FILES['nota_susulan']['size'][$f] > $max_size) {
                $_SESSION['error'] = "File terlalu besar: $orig (maksimal 5MB).";
                header("Location: $redirect");
                exit();
            }
            $fname = "susulan_" . time() . "_{$f}." . $ext;
            if (move_uploaded_file($_FILES['nota_susulan']['tmp_name'][$f], $uploadDir . $fname)) {
                $uploaded_ns[] = $fname;
            }
        }
    }

    if (empty($uploaded_ns)) {
        $_SESSION['error'] = 'Pilih minimal 1 file nota/kwitansi susulan untuk diunggah.';
        header("Location: $redirect");
        exit();
    }

    $chk = mysqli_query($conn, "SELECT id_real_detail, no_realisasi, bukti_files, nota_susulan_files, catatan FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
    if ($chk && $r_tr = mysqli_fetch_assoc($chk)) {
        $existing_ns = $r_tr['nota_susulan_files'] ? explode(',', $r_tr['nota_susulan_files']) : [];
        $merged_ns   = array_values(array_filter(array_merge($existing_ns, $uploaded_ns)));
        $ns_safe     = mysqli_real_escape_string($conn, implode(',', $merged_ns));
        
        // Juga tambahkan ke bukti_files agar selalu tampil di histori bukti
        $existing_bf = $r_tr['bukti_files'] ? explode(',', $r_tr['bukti_files']) : [];
        $merged_bf   = array_values(array_filter(array_merge($existing_bf, $uploaded_ns)));
        $bf_safe     = mysqli_real_escape_string($conn, implode(',', $merged_bf));

        $ns_note = " [Nota susulan diunggah " . date('d/m/Y H:i') . "]";
        $cat_safe = mysqli_real_escape_string($conn, $r_tr['catatan'] . $ns_note);

        mysqli_query($conn, "UPDATE ppx_realisasi_detail2 SET 
                                nota_susulan_files = '$ns_safe',
                                bukti_files = '$bf_safe',
                                catatan = '$cat_safe',
                                updated_at = NOW()
                             WHERE id_real_detail = $id_real_detail");
        $_SESSION['success'] = "Nota susulan untuk {$r_tr['no_realisasi']} berhasil diunggah.";
    } else {
        $_SESSION['error'] = 'Transaksi tidak ditemukan.';
    }
    header("Location: $redirect");
    exit();
}

// Input transaksi baru/edit hanya boleh setelah budget sampai tahap "Finance (Realisasi)" (atau sudah "Active")
if (!in_array($budget['status'], ['Finance (Realisasi)', 'Active'])) {
    $_SESSION['error'] = "Belum bisa input transaksi. Status anggaran masih \"{$budget['status']}\", realisasi baru bisa diinput setelah sampai tahap \"Finance (Realisasi)\".";
    header("Location: $redirect");
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Parameter Form Transaksi BKK / BKM Baru & Edit
// ─────────────────────────────────────────────────────────────────────────────
$jenis_transaksi = in_array(strtoupper(trim($_POST['jenis_transaksi'] ?? 'BKK')), ['BKK', 'BKM']) ? strtoupper(trim($_POST['jenis_transaksi'] ?? 'BKK')) : 'BKK';
$dibayar_kepada  = trim($_POST['dibayar_kepada'] ?? '');
$keperluan       = trim($_POST['keperluan'] ?? '');
$tanggal_real    = !empty($_POST['tanggal_realisasi']) ? trim($_POST['tanggal_realisasi']) : date('Y-m-d');

// Upload file bukti (nota / kwitansi)
$uploadDir   = __DIR__ . '/../uploads/realisasi/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size    = 5 * 1024 * 1024; // 5MB per file

$new_files = [];
if (!empty($_FILES['bukti']['name'][0])) {
    $fcount = count($_FILES['bukti']['name']);
    if ($fcount > 10) {
        $_SESSION['error'] = 'Maksimal 10 file nota lampiran sekaligus.';
        header("Location: $redirect");
        exit();
    }

    for ($f = 0; $f < $fcount; $f++) {
        if ($_FILES['bukti']['error'][$f] !== UPLOAD_ERR_OK) continue;
        $orig = basename($_FILES['bukti']['name'][$f]);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            $_SESSION['error'] = "Format file tidak didukung: $orig (gunakan JPG, PNG, atau PDF).";
            header("Location: $redirect");
            exit();
        }
        if ($_FILES['bukti']['size'][$f] > $max_size) {
            $_SESSION['error'] = "File terlalu besar: $orig (maksimal 5MB per file).";
            header("Location: $redirect");
            exit();
        }
        $fname = strtolower($jenis_transaksi) . '_' . time() . "_{$f}." . $ext;
        if (move_uploaded_file($_FILES['bukti']['tmp_name'][$f], $uploadDir . $fname)) {
            $new_files[] = $fname;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Cek Threshold Approval (Fase 2 Item 7)
// ─────────────────────────────────────────────────────────────────────────────
$items_detail_ids = $_POST['item_detail_id'] ?? [];
$items_keterangan = $_POST['item_keterangan'] ?? [];
$items_jumlah     = $_POST['item_jumlah'] ?? [];

$items_check = [];
$total_input_nominal = 0;
foreach ($items_detail_ids as $idx => $det_id) {
    $raw_amt = str_replace('.', '', $items_jumlah[$idx] ?? '0');
    $amt_val = max(0, (float)$raw_amt);
    if ($amt_val > 0) {
        $items_check[] = [
            'id_detail' => (int)$det_id,
            'nominal'   => $amt_val
        ];
        $total_input_nominal += $amt_val;
    }
}

$is_auto_approved = false;
$catatan_auto_approval = null;
$threshold_reasons = [];

if ($action === 'draft') {
    $status_baru = 'Draft';
} else {
    // Aksi submit transaksi
    $threshold = check_realisasi_threshold($conn, $id_budget, $items_check, $jenis_transaksi);
    if (!$threshold['needs_approval']) {
        // <= 5jt dan tidak over budget: Auto-approved!
        $status_baru = 'Disetujui';
        $is_auto_approved = true;
        $catatan_auto_approval = 'Disetujui Otomatis (<= Rp 5.000.000 & dalam pagu anggaran)';
    } else {
        $status_baru = 'Menunggu Approval';
        $threshold_reasons = $threshold['reasons'];
    }
}

mysqli_begin_transaction($conn);
try {
    // Header realisasi bulanan
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

    // ─────────────────────────────────────────────────────────────────────────
    // A. Single Edit (edit 1 record yang sudah ada)
    // ─────────────────────────────────────────────────────────────────────────
    if ($id_real_detail > 0) {
        $chk_ex = mysqli_query($conn, "SELECT * FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
        if (!$chk_ex || mysqli_num_rows($chk_ex) === 0) {
            throw new Exception("Data transaksi tidak ditemukan.");
        }
        $existing = mysqli_fetch_assoc($chk_ex);
        if (!in_array($existing['status'], ['Draft', 'Ditolak']) && !$is_admin) {
            throw new Exception("Transaksi ini sedang diproses atau sudah disetujui, tidak dapat diedit langsung.");
        }

        $id_detail_item = !empty($items_detail_ids[0]) ? (int)$items_detail_ids[0] : (int)$existing['id_detail_budget'];
        $raw_amt        = str_replace('.', '', $items_jumlah[0] ?? '0');
        $amt_val        = max(0, (float)$raw_amt);
        if ($amt_val <= 0) {
            throw new Exception("Nominal transaksi harus lebih dari 0.");
        }

        $nom_val = ($jenis_transaksi === 'BKK') ? $amt_val : 0;
        $uk_val  = ($jenis_transaksi === 'BKM') ? $amt_val : 0;

        $old_bukti = $existing['bukti_files'] ?? '';
        $merged_files = array_values(array_filter(array_merge(
            $old_bukti ? explode(',', $old_bukti) : [],
            $new_files
        )));
        $bukti_safe   = mysqli_real_escape_string($conn, implode(',', $merged_files));
        $ket_input    = trim($items_keterangan[0] ?? '');
        $catatan_safe = mysqli_real_escape_string($conn, $ket_input !== '' ? $ket_input : ($keperluan ?: $existing['catatan']));
        $tgl_safe     = mysqli_real_escape_string($conn, $tanggal_real);
        $st_safe      = mysqli_real_escape_string($conn, $status_baru);
        $dk_safe      = mysqli_real_escape_string($conn, $dibayar_kepada ?: $existing['dibayar_kepada']);
        $kp_safe      = mysqli_real_escape_string($conn, $keperluan ?: $existing['keperluan']);

        $cat_app_sql = $is_auto_approved ? "'" . mysqli_real_escape_string($conn, $catatan_auto_approval) . "'" : "NULL";
        $app_by_sql  = "NULL";
        $app_at_sql  = $is_auto_approved ? "NOW()" : "NULL";

        $sql_up = "UPDATE ppx_realisasi_detail2 SET
                    id_realisasi      = $id_realisasi,
                    id_detail_budget  = $id_detail_item,
                    jenis_transaksi   = '$jenis_transaksi',
                    tanggal_realisasi = '$tgl_safe',
                    dibayar_kepada    = '$dk_safe',
                    keperluan         = '$kp_safe',
                    nominal_realisasi = $nom_val,
                    uang_kembali      = $uk_val,
                    catatan           = '$catatan_safe',
                    bukti_files       = '$bukti_safe',
                    status            = '$st_safe',
                    catatan_approval  = $cat_app_sql,
                    approved_by       = $app_by_sql,
                    approved_at       = $app_at_sql,
                    updated_at        = NOW()
                   WHERE id_real_detail = $id_real_detail";
        if (!mysqli_query($conn, $sql_up)) {
            throw new Exception("Gagal update transaksi: " . mysqli_error($conn));
        }

    } else {
        // ─────────────────────────────────────────────────────────────────────
        // B. Create Baru (Form BKK / BKM) — Format Nomor Baru per Cabang
        // ─────────────────────────────────────────────────────────────────────
        if (empty($items_detail_ids)) {
            throw new Exception("Rincian akun/item biaya harus diisi minimal 1 baris.");
        }

        $merged_files = $new_files;
        if ($action === 'submit' && empty($merged_files)) {
            throw new Exception("Bukti nota / kwitansi wajib diupload saat mengirim transaksi.");
        }

        $bukti_safe = mysqli_real_escape_string($conn, implode(',', $merged_files));
        
        // Nomor Transaksi Format Baru: BKK/JKT-21-01/2027/01/0002
        $no_transaksi = mysqli_real_escape_string($conn, generate_no_transaksi_ppx($conn, $jenis_transaksi, $id_budget, $tanggal_real));
        
        $tgl_safe   = mysqli_real_escape_string($conn, $tanggal_real);
        $st_safe    = mysqli_real_escape_string($conn, $status_baru);
        $dk_safe    = mysqli_real_escape_string($conn, $dibayar_kepada);
        $kp_safe    = mysqli_real_escape_string($conn, $keperluan);

        $cat_app_sql = $is_auto_approved ? "'" . mysqli_real_escape_string($conn, $catatan_auto_approval) . "'" : "NULL";
        $app_at_sql  = $is_auto_approved ? "NOW()" : "NULL";

        $inserted_count = 0;
        foreach ($items_detail_ids as $idx => $det_id) {
            $det_id = (int)$det_id;
            if ($det_id <= 0) continue;

            $raw_amt = str_replace('.', '', $items_jumlah[$idx] ?? '0');
            $amt_val = max(0, (float)$raw_amt);
            if ($amt_val <= 0) continue;

            $line_ket = mysqli_real_escape_string($conn, trim($items_keterangan[$idx] ?? ''));
            if (empty($line_ket) && !empty($kp_safe)) {
                $line_ket = $kp_safe;
            }

            // Jika BKK: masuk ke nominal_realisasi (pengeluaran)
            // Jika BKM: masuk ke uang_kembali (penerimaan/kembalian)
            $nom_val = ($jenis_transaksi === 'BKK') ? $amt_val : 0;
            $uk_val  = ($jenis_transaksi === 'BKM') ? $amt_val : 0;

            $sql_ins = "INSERT INTO ppx_realisasi_detail2 
                        (id_realisasi, id_budget, no_realisasi, jenis_transaksi, dibayar_kepada, keperluan,
                         id_detail_budget, tanggal_realisasi, nominal_realisasi, uang_kembali, catatan, bukti_files,
                         status, catatan_approval, approved_by, approved_at,
                         created_by, created_at, updated_at)
                        VALUES 
                        ($id_realisasi, $id_budget, '$no_transaksi', '$jenis_transaksi', '$dk_safe', '$kp_safe',
                         $det_id, '$tgl_safe', $nom_val, $uk_val, '$line_ket', '$bukti_safe',
                         '$st_safe', $cat_app_sql, NULL, $app_at_sql,
                         $user_id, NOW(), NOW())";
            if (!mysqli_query($conn, $sql_ins)) {
                throw new Exception("Gagal menyimpan rincian transaksi: " . mysqli_error($conn));
            }
            $inserted_id = mysqli_insert_id($conn);
            $inserted_count++;

            // Auto-Journaling Fase 3 jika transaksi auto-approved
            if ($is_auto_approved && $inserted_id > 0) {
                if ($jenis_transaksi === 'BKM') {
                    acc_post_bkm_journal($conn, $inserted_id);
                } else {
                    acc_post_bkk_journal($conn, $inserted_id);
                }
            }
        }

        if ($inserted_count === 0) {
            throw new Exception("Nominal transaksi harus lebih dari 0.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C. Sinkronisasi Header Realisasi Bulanan
    // ─────────────────────────────────────────────────────────────────────────
    $q_sum = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN status = 'Disetujui' AND delete_status != 'Approved' AND jenis_transaksi = 'BKK' THEN nominal_realisasi ELSE 0 END) as total_approved,
        COUNT(CASE WHEN status = 'Menunggu Approval' AND delete_status != 'Approved' THEN 1 END) as pending_cnt,
        COUNT(CASE WHEN status = 'Ditolak' AND delete_status != 'Approved' THEN 1 END) as rejected_cnt
    FROM ppx_realisasi_detail2 WHERE id_budget = $id_budget");
    $sum_data    = mysqli_fetch_assoc($q_sum);
    $tot_app     = (float)($sum_data['total_approved'] ?? 0);
    $pending_cnt = (int)($sum_data['pending_cnt'] ?? 0);
    $rej_cnt     = (int)($sum_data['rejected_cnt'] ?? 0);

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
        if ($is_auto_approved) {
            $_SESSION['success'] = "Transaksi $jenis_transaksi ($no_transaksi) berhasil disimpan dan DISETUJUI OTOMATIS (nominal <= Rp 5.000.000 & dalam alokasi pagu anggaran).";
        } else {
            $reason_txt = !empty($threshold_reasons) ? ' (' . implode('; ', $threshold_reasons) . ')' : '';
            $_SESSION['success'] = "Transaksi $jenis_transaksi ($no_transaksi) berhasil dikirim dan MENUNGGU APPROVAL ASMEN FINANCE$reason_txt.";
        }
    } else {
        $_SESSION['success'] = "Draft transaksi $jenis_transaksi berhasil disimpan.";
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header("Location: $redirect");
exit();
