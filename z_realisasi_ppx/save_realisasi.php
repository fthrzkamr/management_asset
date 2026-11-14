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

// Pastikan kolom transaksi kas (BKK/BKM) tersedia di tabel ppx_realisasi_detail2 (auto-migrate untuk server)
$chk_col = mysqli_query($conn, "SHOW COLUMNS FROM ppx_realisasi_detail2 LIKE 'jenis_transaksi'");
if ($chk_col && mysqli_num_rows($chk_col) === 0) {
    @mysqli_query($conn, "ALTER TABLE ppx_realisasi_detail2
        ADD COLUMN jenis_transaksi ENUM('BKK','BKM') NOT NULL DEFAULT 'BKK' AFTER no_realisasi,
        ADD COLUMN dibayar_kepada VARCHAR(255) DEFAULT NULL AFTER tanggal_realisasi,
        ADD COLUMN keperluan VARCHAR(255) DEFAULT NULL AFTER dibayar_kepada");
}

$user_id        = (int)$_SESSION['id_user'];
$is_admin       = $auth->isSuperAdmin();
$id_budget      = (int)$_POST['id_budget'];
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
                $_SESSION['error'] = 'Hanya draft yang dapat dihapus.';
            }
        }
    }
    header("Location: $redirect");
    exit();
}

// Parameter BKK / BKM
$jenis_transaksi = in_array(strtoupper(trim($_POST['jenis_transaksi'] ?? 'BKK')), ['BKK', 'BKM']) ? strtoupper(trim($_POST['jenis_transaksi'] ?? 'BKK')) : 'BKK';
$dibayar_kepada  = trim($_POST['dibayar_kepada'] ?? '');
$keperluan       = trim($_POST['keperluan'] ?? '');
$tanggal_real    = !empty($_POST['tanggal_realisasi']) ? trim($_POST['tanggal_realisasi']) : date('Y-m-d');
$status_baru     = ($action === 'draft') ? 'Draft' : 'Menunggu Approval';

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

// Generator Kode BKK / BKM
function generateNoTransaksiKas($conn, $jenis) {
    $prefix = $jenis . '-' . date('Ym') . '-';
    $r = mysqli_query($conn, "SELECT no_realisasi FROM ppx_realisasi_detail2 WHERE no_realisasi LIKE '{$prefix}%' ORDER BY id_real_detail DESC LIMIT 1");
    $seq = 1;
    if ($r && mysqli_num_rows($r) > 0) {
        $last = mysqli_fetch_assoc($r)['no_realisasi'];
        $seq = (int)substr($last, -4) + 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
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

    // Cek apakah ini Single Edit (edit 1 record yang sudah ada)
    if ($id_real_detail > 0) {
        $chk_ex = mysqli_query($conn, "SELECT * FROM ppx_realisasi_detail2 WHERE id_real_detail = $id_real_detail AND id_budget = $id_budget LIMIT 1");
        if (!$chk_ex || mysqli_num_rows($chk_ex) === 0) {
            throw new Exception("Data transaksi tidak ditemukan.");
        }
        $existing = mysqli_fetch_assoc($chk_ex);
        if (!in_array($existing['status'], ['Draft', 'Ditolak']) && !$is_admin) {
            throw new Exception("Transaksi ini sedang diproses atau sudah disetujui, tidak dapat diedit.");
        }

        $items_detail_ids = $_POST['item_detail_id'] ?? [];
        $items_keterangan = $_POST['item_keterangan'] ?? [];
        $items_jumlah     = $_POST['item_jumlah'] ?? [];

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
                    catatan_approval  = NULL,
                    updated_at        = NOW()
                   WHERE id_real_detail = $id_real_detail";
        if (!mysqli_query($conn, $sql_up)) {
            throw new Exception("Gagal update transaksi: " . mysqli_error($conn));
        }

    } else {
        // Multi-Row / Single Create Baru dari Form BKK/BKM
        $items_detail_ids = $_POST['item_detail_id'] ?? [];
        $items_keterangan = $_POST['item_keterangan'] ?? [];
        $items_jumlah     = $_POST['item_jumlah'] ?? [];

        if (empty($items_detail_ids)) {
            throw new Exception("Rincian akun/item biaya harus diisi minimal 1 baris.");
        }

        $merged_files = $new_files;
        if ($action === 'submit' && empty($merged_files)) {
            throw new Exception("Bukti nota / kwitansi wajib diupload saat mengirim transaksi ke Asmen.");
        }

        $bukti_safe = mysqli_real_escape_string($conn, implode(',', $merged_files));
        $no_transaksi = mysqli_real_escape_string($conn, generateNoTransaksiKas($conn, $jenis_transaksi));
        $tgl_safe   = mysqli_real_escape_string($conn, $tanggal_real);
        $st_safe    = mysqli_real_escape_string($conn, $status_baru);
        $dk_safe    = mysqli_real_escape_string($conn, $dibayar_kepada);
        $kp_safe    = mysqli_real_escape_string($conn, $keperluan);

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
                         status, created_by, created_at, updated_at)
                        VALUES 
                        ($id_realisasi, $id_budget, '$no_transaksi', '$jenis_transaksi', '$dk_safe', '$kp_safe',
                         $det_id, '$tgl_safe', $nom_val, $uk_val, '$line_ket', '$bukti_safe',
                         '$st_safe', $user_id, NOW(), NOW())";
            if (!mysqli_query($conn, $sql_ins)) {
                throw new Exception("Gagal menyimpan rincian transaksi: " . mysqli_error($conn));
            }
            $inserted_count++;
        }

        if ($inserted_count === 0) {
            throw new Exception("Nominal transaksi harus lebih dari 0.");
        }
    }

    // Sync header status dan total nominal realisasi
    $q_sum = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN status = 'Disetujui' AND jenis_transaksi = 'BKK' THEN nominal_realisasi ELSE 0 END) as total_approved,
        COUNT(CASE WHEN status = 'Menunggu Approval' THEN 1 END) as pending_cnt,
        COUNT(CASE WHEN status = 'Ditolak' THEN 1 END) as rejected_cnt
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
        $_SESSION['success'] = "Transaksi $jenis_transaksi berhasil dikirim ke Asmen Finance!";
    } else {
        $_SESSION['success'] = "Draft transaksi $jenis_transaksi berhasil disimpan.";
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header("Location: $redirect");
exit();
