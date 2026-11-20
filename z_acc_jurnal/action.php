<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_budget', 'read');

init_acc_fase3_db($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = (int)$_SESSION['id_user'];
$is_admin = $auth->isSuperAdmin();

// ── SAVE JURNAL MANUAL ─────────────────────────────────────────────────────
if ($action === 'save_jurnal') {
    $tanggal_jurnal = trim($_POST['tanggal_jurnal'] ?? date('Y-m-d'));
    $tipe_jurnal    = trim($_POST['tipe_jurnal'] ?? 'Umum');
    $referensi_no   = trim($_POST['referensi_no'] ?? '');
    $keterangan     = trim($_POST['keterangan'] ?? '');

    $coas    = $_POST['id_coa'] ?? [];
    $posisis = $_POST['posisi'] ?? [];
    $nominals= $_POST['nominal'] ?? [];
    $memos   = $_POST['memo'] ?? [];

    if (empty($coas) || count($coas) < 2) {
        $_SESSION['error'] = 'Jurnal akuntansi minimal harus memiliki 2 baris (Debit dan Kredit).';
        header("Location: create.php");
        exit();
    }

    if (empty($keterangan)) {
        $_SESSION['error'] = 'Keterangan jurnal wajib diisi.';
        header("Location: create.php");
        exit();
    }

    $header = [
        'tipe_jurnal'    => in_array($tipe_jurnal, ['Umum', 'Penyesuaian']) ? $tipe_jurnal : 'Umum',
        'tanggal_jurnal' => $tanggal_jurnal,
        'referensi_no'   => $referensi_no,
        'keterangan'     => $keterangan,
        'created_by'     => $user_id,
        'status'         => 'Posted',
    ];

    $details = [];
    for ($i = 0; $i < count($coas); $i++) {
        $id_c = (int)($coas[$i] ?? 0);
        $pos  = ($posisis[$i] === 'Kredit') ? 'Kredit' : 'Debit';
        $raw_nom = str_replace('.', '', $nominals[$i] ?? '0');
        $nom  = (float)$raw_nom;
        $memo = trim($memos[$i] ?? '');

        if ($id_c > 0 && $nom > 0) {
            $details[] = [
                'id_coa'  => $id_c,
                'posisi'  => $pos,
                'nominal' => $nom,
                'memo'    => $memo,
            ];
        }
    }

    if (count($details) < 2) {
        $_SESSION['error'] = 'Minimal 2 baris jurnal harus memiliki akun COA dan nominal valid.';
        header("Location: create.php");
        exit();
    }

    $res = acc_create_jurnal($conn, $header, $details);

    if ($res['success']) {
        if (!$res['is_balanced']) {
            $_SESSION['error'] = "Jurnal berhasil disimpan ({$res['no_jurnal']}) namun status TIDAK SEIMBANG / UNBALANCED. Periksa kembali debit dan kredit.";
        } else {
            $_SESSION['success'] = "Jurnal Umum ({$res['no_jurnal']}) berhasil dibukukan dengan status SEIMBANG (Total: Rp " . number_format($res['total_debit'], 0, ',', '.') . ").";
        }
        header("Location: index.php?q=" . urlencode($res['no_jurnal']));
    } else {
        $_SESSION['error'] = $res['message'];
        header("Location: create.php");
    }
    exit();
}

// ── DELETE JURNAL ──────────────────────────────────────────────────────────
if ($action === 'delete_jurnal') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Hanya Administrator yang berhak menghapus entri jurnal akuntansi.';
        header("Location: index.php");
        exit();
    }

    $id_jurnal = (int)($_POST['id_jurnal'] ?? $_GET['id'] ?? 0);
    $q = mysqli_query($conn, "SELECT no_jurnal, tipe_jurnal FROM acc_jurnal_header WHERE id_jurnal = $id_jurnal LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        if (!in_array($r['tipe_jurnal'], ['Umum', 'Penyesuaian'])) {
            $_SESSION['error'] = "Jurnal otomatis ({$r['no_jurnal']}) berasal dari modul {$r['tipe_jurnal']}. Silakan batalkan transaksi langsung dari modul asalnya.";
            header("Location: index.php");
            exit();
        }

        mysqli_query($conn, "DELETE FROM acc_jurnal_detail WHERE id_jurnal = $id_jurnal");
        mysqli_query($conn, "DELETE FROM acc_jurnal_header WHERE id_jurnal = $id_jurnal");
        $_SESSION['success'] = "Jurnal {$r['no_jurnal']} berhasil dihapus.";
    }
    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
