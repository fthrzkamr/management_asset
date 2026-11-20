<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('realisasi_ppx', 'read');

init_acc_fase3_db($conn);
init_ppx_fase2_db($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = (int)$_SESSION['id_user'];
$is_admin = $auth->isSuperAdmin();

// ── SAVE CASH OPNAME ───────────────────────────────────────────────────────
if ($action === 'save_opname') {
    $id_opname       = (int)($_POST['id_opname'] ?? 0);
    $id_cabang_ppx   = (int)($_POST['id_cabang_ppx'] ?? 0);
    $id_coa_kas      = (int)($_POST['id_coa_kas'] ?? 0);
    $tanggal_opname  = trim($_POST['tanggal_opname'] ?? date('Y-m-d'));
    $periode_minggu  = trim($_POST['periode_minggu'] ?? '');
    $petugas_opname  = trim($_POST['petugas_opname'] ?? '');
    $saksi_opname    = trim($_POST['saksi_opname'] ?? '');
    $keterangan_selisih = trim($_POST['keterangan_selisih'] ?? '');
    $sub_status      = ($_POST['submit_action'] === 'submit') ? 'Disetujui' : 'Draft';

    $raw_buku  = str_replace('.', '', $_POST['saldo_buku'] ?? '0');
    $saldo_buku = (float)$raw_buku;

    // Ambil pecahan JSON dari form
    $pecahan = [
        'kertas_100k' => (int)($_POST['k_100k'] ?? 0),
        'kertas_50k'  => (int)($_POST['k_50k'] ?? 0),
        'kertas_20k'  => (int)($_POST['k_20k'] ?? 0),
        'kertas_10k'  => (int)($_POST['k_10k'] ?? 0),
        'kertas_5k'   => (int)($_POST['k_5k'] ?? 0),
        'kertas_2k'   => (int)($_POST['k_2k'] ?? 0),
        'kertas_1k'   => (int)($_POST['k_1k'] ?? 0),
        'koin_1000'   => (int)($_POST['c_1000'] ?? 0),
        'koin_500'    => (int)($_POST['c_500'] ?? 0),
        'koin_200'    => (int)($_POST['c_200'] ?? 0),
        'koin_100'    => (int)($_POST['c_100'] ?? 0),
    ];

    $saldo_fisik = ($pecahan['kertas_100k'] * 100000)
                 + ($pecahan['kertas_50k']  * 50000)
                 + ($pecahan['kertas_20k']  * 20000)
                 + ($pecahan['kertas_10k']  * 10000)
                 + ($pecahan['kertas_5k']   * 5000)
                 + ($pecahan['kertas_2k']   * 2000)
                 + ($pecahan['kertas_1k']   * 1000)
                 + ($pecahan['koin_1000']   * 1000)
                 + ($pecahan['koin_500']    * 500)
                 + ($pecahan['koin_200']    * 200)
                 + ($pecahan['koin_100']    * 100);

    $selisih = $saldo_fisik - $saldo_buku;
    $pecahan_json = json_encode($pecahan);

    // Dapatkan kode cabang untuk penomoran
    $q_cab = mysqli_query($conn, "SELECT kode_cabang FROM ppx_master_cabang WHERE id_cabang_ppx = $id_cabang_ppx LIMIT 1");
    $kode_cab = 'JKT';
    if ($q_cab && $rc = mysqli_fetch_assoc($q_cab)) {
        $kode_cab = $rc['kode_cabang'];
    }

    // Handle foto bukti
    $uploaded_foto = null;
    if (!empty($_FILES['bukti_foto']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/cash_opname/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['bukti_foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $filename = 'CO_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['bukti_foto']['tmp_name'], $upload_dir . $filename)) {
                $uploaded_foto = $filename;
            }
        }
    }

    $ket_esc     = mysqli_real_escape_string($conn, $keterangan_selisih);
    $petugas_esc = mysqli_real_escape_string($conn, $petugas_opname);
    $saksi_esc   = mysqli_real_escape_string($conn, $saksi_opname);
    $periode_esc = mysqli_real_escape_string($conn, $periode_minggu);
    $coa_sql     = $id_coa_kas > 0 ? $id_coa_kas : "NULL";

    if ($id_opname > 0) {
        $foto_clause = $uploaded_foto ? ", bukti_foto = '" . mysqli_real_escape_string($conn, $uploaded_foto) . "'" : "";
        $sql = "UPDATE acc_cash_opname SET
                    id_cabang_ppx = $id_cabang_ppx,
                    id_coa_kas = $coa_sql,
                    tanggal_opname = '$tanggal_opname',
                    periode_minggu = '$periode_esc',
                    saldo_buku = $saldo_buku,
                    saldo_fisik = $saldo_fisik,
                    selisih = $selisih,
                    keterangan_selisih = '$ket_esc',
                    rincian_pecahan_json = '" . mysqli_real_escape_string($conn, $pecahan_json) . "',
                    petugas_opname = '$petugas_esc',
                    saksi_opname = '$saksi_esc',
                    status = '$sub_status'
                    $foto_clause
                WHERE id_opname = $id_opname";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Cash Opname berhasil diperbarui.";
            $saved_id = $id_opname;
        } else {
            $_SESSION['error'] = "Gagal memperbarui Cash Opname: " . mysqli_error($conn);
            header("Location: index.php");
            exit();
        }
    } else {
        $no_opname = acc_generate_no_opname($conn, $kode_cab);
        $foto_sql = $uploaded_foto ? "'" . mysqli_real_escape_string($conn, $uploaded_foto) . "'" : "NULL";

        $sql = "INSERT INTO acc_cash_opname 
            (no_opname, id_cabang_ppx, id_coa_kas, tanggal_opname, periode_minggu, saldo_buku, saldo_fisik, selisih, keterangan_selisih, rincian_pecahan_json, petugas_opname, saksi_opname, bukti_foto, status, created_by)
            VALUES ('$no_opname', $id_cabang_ppx, $coa_sql, '$tanggal_opname', '$periode_esc', $saldo_buku, $saldo_fisik, $selisih, '$ket_esc', '" . mysqli_real_escape_string($conn, $pecahan_json) . "', '$petugas_esc', '$saksi_esc', $foto_sql, '$sub_status', $user_id)";
        
        if (mysqli_query($conn, $sql)) {
            $saved_id = mysqli_insert_id($conn);
            $_SESSION['success'] = "Cash Opname ($no_opname) berhasil dibuat.";
        } else {
            $_SESSION['error'] = "Gagal menyimpan Cash Opname: " . mysqli_error($conn);
            header("Location: index.php");
            exit();
        }
    }

    // Jika langsung disubmit dan disetujui serta ada selisih, buat jurnal penyesuaian
    if ($sub_status === 'Disetujui' && abs($selisih) >= 0.01) {
        acc_post_opname_adjustment_journal($conn, $saved_id);
    }

    header("Location: detail.php?id=" . $saved_id);
    exit();
}

// ── APPROVE CASH OPNAME ────────────────────────────────────────────────────
if ($action === 'approve_opname') {
    $id_opname = (int)($_POST['id_opname'] ?? $_GET['id'] ?? 0);
    $q = mysqli_query($conn, "SELECT * FROM acc_cash_opname WHERE id_opname = $id_opname LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        mysqli_query($conn, "UPDATE acc_cash_opname SET 
            status = 'Disetujui', 
            approved_by = $user_id, 
            approved_at = NOW() 
            WHERE id_opname = $id_opname");
        
        // Buat jurnal penyesuaian jika ada selisih
        if (abs((float)$r['selisih']) >= 0.01 && empty($r['id_jurnal_penyesuaian'])) {
            acc_post_opname_adjustment_journal($conn, $id_opname);
        }

        $_SESSION['success'] = "Berita Acara Cash Opname {$r['no_opname']} telah disetujui.";
    }
    header("Location: detail.php?id=" . $id_opname);
    exit();
}

// ── DELETE CASH OPNAME ─────────────────────────────────────────────────────
if ($action === 'delete_opname') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Hanya Administrator yang berhak menghapus Cash Opname.';
        header("Location: index.php");
        exit();
    }
    $id_opname = (int)($_POST['id_opname'] ?? $_GET['id'] ?? 0);
    $q = mysqli_query($conn, "SELECT * FROM acc_cash_opname WHERE id_opname = $id_opname LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        if (!empty($r['id_jurnal_penyesuaian'])) {
            mysqli_query($conn, "DELETE FROM acc_jurnal_detail WHERE id_jurnal = " . (int)$r['id_jurnal_penyesuaian']);
            mysqli_query($conn, "DELETE FROM acc_jurnal_header WHERE id_jurnal = " . (int)$r['id_jurnal_penyesuaian']);
        }
        mysqli_query($conn, "DELETE FROM acc_cash_opname WHERE id_opname = $id_opname");
        $_SESSION['success'] = "Cash Opname {$r['no_opname']} berhasil dihapus.";
    }
    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
