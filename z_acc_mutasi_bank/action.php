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

// ── SAVE REKENING BANK ─────────────────────────────────────────────────────
if ($action === 'save_rekening') {
    $id_rekening   = (int)($_POST['id_rekening'] ?? 0);
    $nama_bank     = trim($_POST['nama_bank'] ?? '');
    $nomor_rekening= trim($_POST['nomor_rekening'] ?? '');
    $atas_nama     = trim($_POST['atas_nama'] ?? '');
    $id_coa        = (int)($_POST['id_coa'] ?? 0);
    $saldo_awal    = (float)str_replace('.', '', $_POST['saldo_awal'] ?? '0');
    $keterangan    = trim($_POST['keterangan'] ?? '');
    $status        = trim($_POST['status'] ?? 'Aktif');

    if (empty($nama_bank) || empty($nomor_rekening) || empty($atas_nama)) {
        $_SESSION['error'] = 'Nama Bank, Nomor Rekening, dan Atas Nama wajib diisi.';
        header("Location: index.php");
        exit();
    }

    $nb_esc  = mysqli_real_escape_string($conn, $nama_bank);
    $nr_esc  = mysqli_real_escape_string($conn, $nomor_rekening);
    $an_esc  = mysqli_real_escape_string($conn, $atas_nama);
    $coa_sql = $id_coa > 0 ? $id_coa : "NULL";
    $ket_esc = mysqli_real_escape_string($conn, $keterangan);
    $st_esc  = ($status === 'Nonaktif') ? 'Nonaktif' : 'Aktif';

    if ($id_rekening > 0) {
        $sql = "UPDATE acc_master_rekening SET
                    nama_bank = '$nb_esc',
                    nomor_rekening = '$nr_esc',
                    atas_nama = '$an_esc',
                    id_coa = $coa_sql,
                    keterangan = '$ket_esc',
                    status = '$st_esc'
                WHERE id_rekening = $id_rekening";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Rekening Bank $nama_bank ($nomor_rekening) berhasil diperbarui.";
        } else {
            $_SESSION['error'] = "Gagal memperbarui rekening: " . mysqli_error($conn);
        }
    } else {
        $sql = "INSERT INTO acc_master_rekening 
                (nama_bank, nomor_rekening, atas_nama, id_coa, saldo_awal, saldo_saat_ini, keterangan, status)
                VALUES ('$nb_esc', '$nr_esc', '$an_esc', $coa_sql, $saldo_awal, $saldo_awal, '$ket_esc', '$st_esc')";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Rekening Bank baru $nama_bank ($nomor_rekening) berhasil ditambahkan.";
        } else {
            $_SESSION['error'] = "Gagal menambahkan rekening: " . mysqli_error($conn);
        }
    }

    header("Location: index.php");
    exit();
}

// ── SAVE MUTASI KAS / BANK ─────────────────────────────────────────────────
if ($action === 'save_mutasi') {
    $id_rekening    = (int)($_POST['id_rekening'] ?? 0);
    $tanggal        = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $jenis_mutasi   = trim($_POST['jenis_mutasi'] ?? 'Masuk');
    $kategori_mutasi= trim($_POST['kategori_mutasi'] ?? 'Lainnya');
    $id_coa_lawan   = (int)($_POST['id_coa_lawan'] ?? 0);
    $no_referensi   = trim($_POST['no_referensi'] ?? '');
    $keterangan     = trim($_POST['keterangan'] ?? '');

    $raw_nominal    = str_replace('.', '', $_POST['nominal'] ?? '0');
    $nominal        = (float)$raw_nominal;

    if ($id_rekening <= 0 || $nominal <= 0 || empty($keterangan)) {
        $_SESSION['error'] = 'Rekening Bank, Nominal, dan Keterangan wajib diisi.';
        header("Location: index.php");
        exit();
    }

    if ($id_coa_lawan <= 0) {
        $_SESSION['error'] = 'Akun Lawan COA wajib dipilih, supaya mutasi ini otomatis tercatat ke Jurnal Umum.';
        header("Location: index.php");
        exit();
    }

    // Ambil data rekening
    $q_rek = mysqli_query($conn, "SELECT * FROM acc_master_rekening WHERE id_rekening = $id_rekening LIMIT 1 FOR UPDATE");
    if (!$q_rek || mysqli_num_rows($q_rek) === 0) {
        $_SESSION['error'] = 'Rekening bank tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
    $rek = mysqli_fetch_assoc($q_rek);

    mysqli_begin_transaction($conn);

    try {
        $saldo_sebelum = (float)$rek['saldo_saat_ini'];
        if ($jenis_mutasi === 'Masuk') {
            $saldo_setelah = $saldo_sebelum + $nominal;
        } else {
            $saldo_setelah = $saldo_sebelum - $nominal;
        }

        // Handle upload bukti file
        $uploaded_file = null;
        if (!empty($_FILES['bukti_file']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/mutasi_bank/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['bukti_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $filename = 'MUT_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['bukti_file']['tmp_name'], $upload_dir . $filename)) {
                    $uploaded_file = $filename;
                }
            }
        }

        $no_mutasi = acc_generate_no_mutasi($conn, $rek['nama_bank']);
        $jenis_esc = ($jenis_mutasi === 'Keluar') ? 'Keluar' : 'Masuk';
        $kat_esc   = mysqli_real_escape_string($conn, $kategori_mutasi);
        $coa_lawan_sql = $id_coa_lawan > 0 ? $id_coa_lawan : "NULL";
        $ref_esc   = mysqli_real_escape_string($conn, $no_referensi);
        $ket_esc   = mysqli_real_escape_string($conn, $keterangan);
        $file_sql  = $uploaded_file ? "'" . mysqli_real_escape_string($conn, $uploaded_file) . "'" : "NULL";

        $sql_mut = "INSERT INTO acc_mutasi_bank 
            (no_mutasi, id_rekening, tanggal, jenis_mutasi, nominal, kategori_mutasi, id_coa_lawan, no_referensi, keterangan, bukti_file, saldo_setelahnya, created_by)
            VALUES ('$no_mutasi', $id_rekening, '$tanggal', '$jenis_esc', $nominal, '$kat_esc', $coa_lawan_sql, '$ref_esc', '$ket_esc', $file_sql, $saldo_setelah, $user_id)";
        
        if (!mysqli_query($conn, $sql_mut)) {
            throw new Exception("Gagal menyimpan mutasi: " . mysqli_error($conn));
        }

        $id_mutasi = mysqli_insert_id($conn);

        // Update saldo saat ini pada rekening
        $sql_upd_rek = "UPDATE acc_master_rekening SET saldo_saat_ini = $saldo_setelah WHERE id_rekening = $id_rekening";
        if (!mysqli_query($conn, $sql_upd_rek)) {
            throw new Exception("Gagal update saldo rekening: " . mysqli_error($conn));
        }

        // Generate Jurnal Otomatis jika akun lawan terisi
        if ($id_coa_lawan > 0) {
            acc_post_mutasi_journal($conn, $id_mutasi);
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Mutasi $jenis_mutasi ($no_mutasi) sebesar Rp " . number_format($nominal, 0, ',', '.') . " berhasil dicatat dan saldo rekening diperbarui.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: index.php?id_rekening=" . $id_rekening);
    exit();
}

// ── DELETE MUTASI ──────────────────────────────────────────────────────────
if ($action === 'delete_mutasi') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Hanya Administrator yang dapat membatalkan / menghapus mutasi bank.';
        header("Location: index.php");
        exit();
    }

    $id_mutasi = (int)($_POST['id_mutasi'] ?? $_GET['id'] ?? 0);
    $q_mut = mysqli_query($conn, "SELECT * FROM acc_mutasi_bank WHERE id_mutasi = $id_mutasi LIMIT 1");
    if (!$q_mut || mysqli_num_rows($q_mut) === 0) {
        $_SESSION['error'] = 'Data mutasi tidak ditemukan.';
        header("Location: index.php");
        exit();
    }
    $m = mysqli_fetch_assoc($q_mut);

    mysqli_begin_transaction($conn);
    try {
        // Rollback saldo di rekening
        $nom = (float)$m['nominal'];
        $adj = ($m['jenis_mutasi'] === 'Masuk') ? -$nom : $nom;
        mysqli_query($conn, "UPDATE acc_master_rekening SET saldo_saat_ini = saldo_saat_ini + ($adj) WHERE id_rekening = " . (int)$m['id_rekening']);

        // Hapus jurnal terkait jika ada
        if (!empty($m['id_jurnal'])) {
            mysqli_query($conn, "DELETE FROM acc_jurnal_detail WHERE id_jurnal = " . (int)$m['id_jurnal']);
            mysqli_query($conn, "DELETE FROM acc_jurnal_header WHERE id_jurnal = " . (int)$m['id_jurnal']);
        }

        // Hapus mutasi
        mysqli_query($conn, "DELETE FROM acc_mutasi_bank WHERE id_mutasi = $id_mutasi");

        mysqli_commit($conn);
        $_SESSION['success'] = "Mutasi {$m['no_mutasi']} berhasil dibatalkan dan saldo dikembalikan.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Gagal membatalkan mutasi: " . $e->getMessage();
    }

    header("Location: index.php?id_rekening=" . $m['id_rekening']);
    exit();
}

header("Location: index.php");
exit();
