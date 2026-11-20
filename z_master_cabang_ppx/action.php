<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/../dist/config/ppx_helper.php';

init_ppx_fase2_db($conn);

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$action = trim($_POST['action'] ?? '');
$redirect = 'index.php';

try {
    if ($action === 'create') {
        $kode  = strtoupper(trim($_POST['kode_cabang'] ?? ''));
        $nama  = trim($_POST['nama_cabang'] ?? '');
        $asset = trim($_POST['id_cabang_asset'] ?? '') ?: null;
        $st    = in_array($_POST['status'] ?? '', ['Aktif', 'Nonaktif']) ? $_POST['status'] : 'Aktif';

        if (empty($kode) || empty($nama)) {
            throw new Exception("Kode cabang dan nama cabang wajib diisi.");
        }

        // Cek duplikasi kode
        $chk = mysqli_query($conn, "SELECT id_cabang_ppx FROM ppx_master_cabang WHERE kode_cabang = '" . mysqli_real_escape_string($conn, $kode) . "' LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            throw new Exception("Kode cabang '$kode' sudah digunakan.");
        }

        $k_safe = mysqli_real_escape_string($conn, $kode);
        $n_safe = mysqli_real_escape_string($conn, $nama);
        $a_safe = $asset ? "'" . mysqli_real_escape_string($conn, $asset) . "'" : "NULL";

        $ins = mysqli_query($conn, "INSERT INTO ppx_master_cabang (kode_cabang, nama_cabang, id_cabang_asset, status) 
                                   VALUES ('$k_safe', '$n_safe', $a_safe, '$st')");
        if (!$ins) throw new Exception("Gagal menambah cabang: " . mysqli_error($conn));

        $_SESSION['success'] = "Cabang PPX '$nama' ($kode) berhasil ditambahkan.";

    } elseif ($action === 'update') {
        $id    = (int)($_POST['id_cabang_ppx'] ?? 0);
        $kode  = strtoupper(trim($_POST['kode_cabang'] ?? ''));
        $nama  = trim($_POST['nama_cabang'] ?? '');
        $asset = trim($_POST['id_cabang_asset'] ?? '') ?: null;
        $st    = in_array($_POST['status'] ?? '', ['Aktif', 'Nonaktif']) ? $_POST['status'] : 'Aktif';

        if ($id <= 0 || empty($kode) || empty($nama)) {
            throw new Exception("Data tidak valid.");
        }

        $chk = mysqli_query($conn, "SELECT id_cabang_ppx FROM ppx_master_cabang WHERE kode_cabang = '" . mysqli_real_escape_string($conn, $kode) . "' AND id_cabang_ppx != $id LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            throw new Exception("Kode cabang '$kode' sudah digunakan oleh cabang lain.");
        }

        $k_safe = mysqli_real_escape_string($conn, $kode);
        $n_safe = mysqli_real_escape_string($conn, $nama);
        $a_safe = $asset ? "'" . mysqli_real_escape_string($conn, $asset) . "'" : "NULL";

        $up = mysqli_query($conn, "UPDATE ppx_master_cabang SET
                                    kode_cabang = '$k_safe',
                                    nama_cabang = '$n_safe',
                                    id_cabang_asset = $a_safe,
                                    status = '$st',
                                    updated_at = NOW()
                                   WHERE id_cabang_ppx = $id");
        if (!$up) throw new Exception("Gagal update cabang: " . mysqli_error($conn));

        $_SESSION['success'] = "Data cabang '$nama' ($kode) berhasil diperbarui.";

    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id_cabang_ppx'] ?? 0);
        if ($id <= 0) throw new Exception("ID cabang tidak valid.");

        $q = mysqli_query($conn, "SELECT status, nama_cabang FROM ppx_master_cabang WHERE id_cabang_ppx = $id LIMIT 1");
        if ($q && $r = mysqli_fetch_assoc($q)) {
            $new_st = ($r['status'] === 'Aktif') ? 'Nonaktif' : 'Aktif';
            mysqli_query($conn, "UPDATE ppx_master_cabang SET status = '$new_st', updated_at = NOW() WHERE id_cabang_ppx = $id");
            $_SESSION['success'] = "Status cabang '{$r['nama_cabang']}' diubah menjadi $new_st.";
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: $redirect");
exit();
