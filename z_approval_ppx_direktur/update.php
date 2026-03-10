<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_asset\process_edit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method!';
    header("Location: dashboard.php");
    exit();
}

$no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx'] ?? '');

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data existing
$cek = mysqli_query($conn, "SELECT * FROM ppx_request WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "' LIMIT 1");
if (!$cek || mysqli_num_rows($cek) === 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}
$existing = mysqli_fetch_assoc($cek);

// Permission: hanya owner bisa edit jika belum diproses; staff/superadmin boleh edit kapan saja
$nonEditableStatuses = ['Done','Finance','Direktur','Manager','Leader'];

// jika bukan pemilik dan bukan staff/superadmin -> tolak
// kecuali jika status = 'Revisi' maka tetap boleh diedit
if ($auth->isKaryawan() && $existing['user_id'] != $_SESSION['id_user'] && $existing['status'] !== 'Revisi') {
    $_SESSION['error'] = 'Anda tidak berhak mengedit pengajuan ini.';
    header("Location: dashboard.php");
    exit();
}

// jika status sudah termasuk status yang menunjukkan pengajuan telah diproses, tolak bagi user biasa
// kecuali jika status = 'Revisi' maka tetap boleh diedit
if (in_array($existing['status'], $nonEditableStatuses) && $existing['status'] !== 'Revisi' && !($auth->isStaff() || $auth->isSuperAdmin())) {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit.';
    header("Location: dashboard.php");
    exit();
}

// Ambil input dari form (sesuai add.php)
$nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic'] ?? $existing['nama_pic']);
$jabatan = mysqli_real_escape_string($conn, $_POST['jabatan'] ?? $existing['jabatan']);
$divisi = mysqli_real_escape_string($conn, $_POST['divisi'] ?? $existing['divisi']);
$cabang = mysqli_real_escape_string($conn, $_POST['cabang'] ?? $existing['cabang']);
$alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan'] ?? $existing['alasan_pengajuan']);
$tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan'] ?? $existing['tingkat_kepentingan']);
$tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan'] ?? $existing['tanggal_dibutuhkan']);
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? $existing['status']);

// Setelah update berhasil, set status kembali ke 'Leader' agar masuk alur approval lagi
$status = 'Leader';

// Validasi minimal
$errors = [];
if (empty($nama_pic)) $errors[] = 'Nama PIC harus diisi';
if (empty($jabatan)) $errors[] = 'Jabatan harus diisi';
if (empty($divisi)) $errors[] = 'Divisi harus diisi';
if (empty($alasan_pengajuan)) $errors[] = 'Alasan pengajuan harus diisi';
if (empty($tingkat_kepentingan)) $errors[] = 'Tingkat kepentingan harus dipilih';
if (empty($tanggal_dibutuhkan)) $errors[] = 'Tanggal dibutuhkan harus diisi';

$today = date('Y-m-d');
if (!empty($tanggal_dibutuhkan) && $tanggal_dibutuhkan < $today) {
    $errors[] = 'Tanggal dibutuhkan harus hari ini atau setelahnya';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

// Handle upload_file (sama dengan add_insert)
$upload_file = $existing['upload_file'] ?? null;
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/ppx/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $origName = basename($_FILES['upload_file']['name']);
    $safeName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $origName);
    $fileName = $no_ppx . '_' . time() . '_' . $safeName;
    $uploadPath = $uploadDir . $fileName;
    $allowed = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'Format file tidak didukung.';
        header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $uploadPath)) {
        // hapus lama jika ada
        if (!empty($existing['upload_file']) && file_exists($uploadDir . $existing['upload_file'])) {
            @unlink($uploadDir . $existing['upload_file']);
        }
        $upload_file = $fileName;
    } else {
        $_SESSION['error'] = 'Gagal mengupload file.';
        header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }
}

// Hitung total nominal dari detail (jika ada)
$total_nominal = 0;
$details = $_POST['detail'] ?? [];
if (is_array($details)) {
    foreach ($details as $d) {
        $harga = isset($d['harga_satuan']) ? (int)preg_replace('/[^0-9]/','', $d['harga_satuan']) : 0;
        $jumlah = isset($d['jumlah']) ? (int)$d['jumlah'] : 0;
        $total_nominal += ($harga * $jumlah);
    }
}

// Mulai transaction
mysqli_begin_transaction($conn);
try {
    // Update ppx_request
    $update_sql = "UPDATE ppx_request SET
        nama_pic = '". mysqli_real_escape_string($conn, $nama_pic) ."',
        jabatan = '". mysqli_real_escape_string($conn, $jabatan) ."',
        divisi = '". mysqli_real_escape_string($conn, $divisi) ."',
        cabang = '". mysqli_real_escape_string($conn, $cabang) ."',
        alasan_pengajuan = '". mysqli_real_escape_string($conn, $alasan_pengajuan) ."',
        tingkat_kepentingan = '". mysqli_real_escape_string($conn, $tingkat_kepentingan) ."',
        tanggal_dibutuhkan = '". mysqli_real_escape_string($conn, $tanggal_dibutuhkan) ."',
        upload_file = " . ($upload_file ? "'". mysqli_real_escape_string($conn, $upload_file) ."'" : "NULL") . ",
        nominal_ppx = ". (int)$total_nominal .",
        status = '". mysqli_real_escape_string($conn, $status) ."',
        updated_at = NOW(),
        updated_by = '". mysqli_real_escape_string($conn, $_SESSION['nama_user'] ?? $nama_pic) ."'
        WHERE no_ppx = '". mysqli_real_escape_string($conn, $no_ppx) ."' LIMIT 1";

    if (!mysqli_query($conn, $update_sql)) {
        throw new Exception('Gagal update header PPX: ' . mysqli_error($conn));
    }

    // Hapus detail lama
    if (!mysqli_query($conn, "DELETE FROM ppx_request_detail WHERE no_ppx = '". mysqli_real_escape_string($conn, $no_ppx) ."'")) {
        throw new Exception('Gagal menghapus detail lama: ' . mysqli_error($conn));
    }

    // Insert detail baru (prepared)
    if (is_array($details) && count($details) > 0) {
        $sql_detail = "INSERT INTO ppx_request_detail (
            no_ppx, no_akun, rincian, jumlah, satuan, harga_satuan, total_harga, deskripsi, created_at, created_by, updated_at, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?)";
        $stmt = mysqli_prepare($conn, $sql_detail);
        if (!$stmt) throw new Exception('Prepare statement gagal (detail): ' . mysqli_error($conn));

        foreach ($details as $d) {
            $no_akun = trim($d['no_akun'] ?? '');
            $rincian = trim($d['rincian'] ?? '');
            $jumlah = (int)($d['jumlah'] ?? 0);
            $satuan = trim($d['satuan'] ?? '');
            $harga_satuan = (int)preg_replace('/[^0-9]/','', $d['harga_satuan'] ?? '0');
            $total_harga = $harga_satuan * $jumlah;
            $deskripsi = trim($d['deskripsi'] ?? '');
            $created_by = $_SESSION['nama_user'] ?? $nama_pic;
            $updated_by = $created_by;

            if (empty($no_akun) || empty($rincian) || $jumlah <= 0) {
                continue; // skip invalid
            }

            mysqli_stmt_bind_param($stmt, 'sssississs',
                $bind_no_ppx,
                $bind_no_akun,
                $bind_rincian,
                $bind_jumlah,
                $bind_satuan,
                $bind_harga_satuan,
                $bind_total_harga,
                $bind_deskripsi,
                $bind_created_by,
                $bind_updated_by
            );

            // set bind values
            $bind_no_ppx = $no_ppx;
            $bind_no_akun = $no_akun;
            $bind_rincian = $rincian;
            $bind_jumlah = $jumlah;
            $bind_satuan = $satuan;
            $bind_harga_satuan = $harga_satuan;
            $bind_total_harga = $total_harga;
            $bind_deskripsi = $deskripsi;
            $bind_created_by = $created_by;
            $bind_updated_by = $updated_by;

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Gagal menyimpan detail: ' . mysqli_stmt_error($stmt));
            }
        }

        mysqli_stmt_close($stmt);
    }

    mysqli_commit($conn);
    $_SESSION['success'] = 'Pengajuan PPX berhasil diperbarui.';
    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header("Location: edit.php?no_ppx=" . urlencode($no_ppx));
    exit();
}
?>