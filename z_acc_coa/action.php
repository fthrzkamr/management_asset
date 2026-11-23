<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/acc_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_akun', 'read');

init_acc_fase3_db($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = (int)$_SESSION['id_user'];
$is_admin = $auth->isSuperAdmin();

// ── SAVE (ADD / EDIT) COA ──────────────────────────────────────────────────
if ($action === 'save') {
    if (!$auth->hasPermission('master_akun', 'create') && !$auth->hasPermission('master_akun', 'update')) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk mengubah Master COA.';
        header("Location: index.php");
        exit();
    }

    $id_coa        = (int)($_POST['id_coa'] ?? 0);
    $kode_akun     = trim($_POST['kode_akun'] ?? '');
    $nama_akun     = trim($_POST['nama_akun'] ?? '');
    $kategori_akun = trim($_POST['kategori_akun'] ?? '');
    $tipe_saldo    = trim($_POST['tipe_saldo'] ?? 'Debit');
    $parent_kode   = trim($_POST['parent_kode'] ?? '');
    $is_header     = isset($_POST['is_header']) ? 1 : 0;
    $status        = trim($_POST['status'] ?? 'Aktif');
    $keterangan    = trim($_POST['keterangan'] ?? '');

    if (empty($kode_akun) || empty($nama_akun) || empty($kategori_akun)) {
        $_SESSION['error'] = 'Kode Akun, Nama Akun, dan Kategori Akun wajib diisi.';
        header("Location: index.php");
        exit();
    }

    // Hitung level berdasarkan parent
    $level = 4;
    if ($is_header) {
        $level = empty($parent_kode) ? 1 : 2;
    } else {
        $level = empty($parent_kode) ? 3 : 4;
    }
    if (!empty($parent_kode)) {
        $qp = mysqli_query($conn, "SELECT level FROM acc_coa WHERE kode_akun = '" . mysqli_real_escape_string($conn, $parent_kode) . "' LIMIT 1");
        if ($qp && $rp = mysqli_fetch_assoc($qp)) {
            $level = min(4, ((int)$rp['level'] + 1));
        }
    }

    $kode_esc  = mysqli_real_escape_string($conn, $kode_akun);
    $nama_esc  = mysqli_real_escape_string($conn, $nama_akun);
    $kat_esc   = mysqli_real_escape_string($conn, $kategori_akun);
    $saldo_esc = ($tipe_saldo === 'Kredit') ? 'Kredit' : 'Debit';
    $parent_sql = !empty($parent_kode) ? "'" . mysqli_real_escape_string($conn, $parent_kode) . "'" : "NULL";
    $ket_esc   = mysqli_real_escape_string($conn, $keterangan);
    $stat_esc  = ($status === 'Nonaktif') ? 'Nonaktif' : 'Aktif';

    if ($id_coa > 0) {
        // Cek duplikasi kode selain ID ini
        $chk = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '$kode_esc' AND id_coa != $id_coa LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $_SESSION['error'] = "Kode Akun '$kode_akun' sudah digunakan oleh akun lain.";
            header("Location: index.php");
            exit();
        }

        $sql = "UPDATE acc_coa SET
                    kode_akun = '$kode_esc',
                    nama_akun = '$nama_esc',
                    kategori_akun = '$kat_esc',
                    tipe_saldo = '$saldo_esc',
                    level = $level,
                    parent_kode = $parent_sql,
                    is_header = $is_header,
                    status = '$stat_esc',
                    keterangan = '$ket_esc'
                WHERE id_coa = $id_coa";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Akun COA [$kode_akun] $nama_akun berhasil diperbarui.";
        } else {
            $_SESSION['error'] = "Gagal memperbarui akun: " . mysqli_error($conn);
        }
    } else {
        // Cek duplikasi kode
        $chk = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '$kode_esc' LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $_SESSION['error'] = "Kode Akun '$kode_akun' sudah terdaftar.";
            header("Location: index.php");
            exit();
        }

        $sql = "INSERT INTO acc_coa 
                (kode_akun, nama_akun, kategori_akun, tipe_saldo, level, parent_kode, is_header, status, keterangan)
                VALUES ('$kode_esc', '$nama_esc', '$kat_esc', '$saldo_esc', $level, $parent_sql, $is_header, '$stat_esc', '$ket_esc')";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Akun COA baru [$kode_akun] $nama_akun berhasil ditambahkan.";
        } else {
            $_SESSION['error'] = "Gagal menambahkan akun: " . mysqli_error($conn);
        }
    }

    header("Location: index.php");
    exit();
}

// ── TOGGLE STATUS ──────────────────────────────────────────────────────────
if ($action === 'toggle_status') {
    $id_coa = (int)($_GET['id'] ?? 0);
    $q = mysqli_query($conn, "SELECT id_coa, kode_akun, nama_akun, status FROM acc_coa WHERE id_coa = $id_coa LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $new_stat = ($r['status'] === 'Aktif') ? 'Nonaktif' : 'Aktif';
        mysqli_query($conn, "UPDATE acc_coa SET status = '$new_stat' WHERE id_coa = $id_coa");
        $_SESSION['success'] = "Status akun [{$r['kode_akun']}] {$r['nama_akun']} diubah menjadi $new_stat.";
    }
    header("Location: index.php");
    exit();
}

// ── DELETE COA ─────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if (!$auth->hasPermission('master_akun', 'delete') && !$is_admin) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk menghapus akun COA.';
        header("Location: index.php");
        exit();
    }

    $id_coa = (int)($_POST['id_coa'] ?? $_GET['id'] ?? 0);

    // Cek apakah akun memiliki child akun
    $q_kode = mysqli_query($conn, "SELECT kode_akun, nama_akun FROM acc_coa WHERE id_coa = $id_coa LIMIT 1");
    if (!$q_kode || mysqli_num_rows($q_kode) === 0) {
        $_SESSION['error'] = 'Akun tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
    $acc = mysqli_fetch_assoc($q_kode);
    $kode = $acc['kode_akun'];

    $chk_child = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE parent_kode = '$kode' LIMIT 1");
    if ($chk_child && mysqli_num_rows($chk_child) > 0) {
        $_SESSION['error'] = "Akun [$kode] tidak dapat dihapus karena masih memiliki sub-akun (child). Hapus atau ubah sub-akun terlebih dahulu.";
        header("Location: index.php");
        exit();
    }

    // Cek apakah akun pernah digunakan di jurnal
    $chk_jurnal = mysqli_query($conn, "SELECT id_jurnal_detail FROM acc_jurnal_detail WHERE id_coa = $id_coa LIMIT 1");
    if ($chk_jurnal && mysqli_num_rows($chk_jurnal) > 0) {
        $_SESSION['error'] = "Akun [$kode] tidak dapat dihapus karena sudah memiliki riwayat transaksi jurnal akuntansi. Silakan nonaktifkan akun sebagai gantinya.";
        header("Location: index.php");
        exit();
    }

    // Cek apakah akun masih dipakai sebagai akun rekening bank (Mutasi Bank)
    $chk_rek = mysqli_query($conn, "SELECT id_rekening, nama_bank FROM acc_master_rekening WHERE id_coa = $id_coa LIMIT 1");
    if ($chk_rek && $rrek = mysqli_fetch_assoc($chk_rek)) {
        $_SESSION['error'] = "Akun [$kode] tidak dapat dihapus karena masih dipakai sebagai akun rekening bank \"{$rrek['nama_bank']}\" di Mutasi Bank. Lepaskan/ubah rekening itu terlebih dahulu.";
        header("Location: index.php");
        exit();
    }

    // Cek apakah akun masih dipakai sebagai akun kas di Cash Opname
    $chk_co = mysqli_query($conn, "SELECT id_opname FROM acc_cash_opname WHERE id_coa_kas = $id_coa LIMIT 1");
    if ($chk_co && mysqli_num_rows($chk_co) > 0) {
        $_SESSION['error'] = "Akun [$kode] tidak dapat dihapus karena masih dipakai sebagai akun kas di riwayat Cash Opname.";
        header("Location: index.php");
        exit();
    }

    if (mysqli_query($conn, "DELETE FROM acc_coa WHERE id_coa = $id_coa")) {
        $_SESSION['success'] = "Akun COA [$kode] {$acc['nama_akun']} berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal menghapus akun: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
