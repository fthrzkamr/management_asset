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

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $_SESSION['error'] = 'Invalid request method!';
    header("Location: dashboard.php");
    exit();
}

if (!isset($_POST['no_pengajuan'])) {
    $_SESSION['error'] = 'No Pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$no_pengajuan = mysqli_real_escape_string($conn, $_POST['no_pengajuan']);

// Get pengajuan data untuk validasi permission
$check_query = "SELECT * FROM asset_request WHERE no_pengajuan = '$no_pengajuan'";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['error'] = 'Pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$existing_data = mysqli_fetch_assoc($check_result);

// Cek permission
$canUpdate = $auth->hasPermission('pengajuan_asset', 'update');
$canCreate = $auth->hasPermission('pengajuan_asset', 'create');

if (!$canUpdate && !$canCreate) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit pengajuan!';
    header("Location: dashboard.php");
    exit();
}

if ($canCreate && !$canUpdate) {
    if ($existing_data['user_id'] != $_SESSION['id_user'] || $existing_data['status'] != 'Pending') {
        $_SESSION['error'] = 'Anda tidak dapat mengedit pengajuan ini!';
        header("Location: dashboard.php");
        exit();
    }
}

if ($canUpdate && $existing_data['status'] != 'Pending') {
    $_SESSION['error'] = 'Pengajuan yang sudah diproses tidak dapat diedit!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data dari form
$kategori_asset = mysqli_real_escape_string($conn, $_POST['kategori_asset']);
$jenis_pengadaan = mysqli_real_escape_string($conn, $_POST['jenis_pengadaan']);
$tingkat_kepentingan = mysqli_real_escape_string($conn, $_POST['tingkat_kepentingan']);
$nama_pic = mysqli_real_escape_string($conn, $_POST['nama_pic']);
$deskripsi_asset = mysqli_real_escape_string($conn, $_POST['deskripsi_asset']);
$spesifikasi_merek = mysqli_real_escape_string($conn, $_POST['spesifikasi_merek']);
$alasan_pengajuan = mysqli_real_escape_string($conn, $_POST['alasan_pengajuan']);
$lokasi_penggunaan = intval($_POST['lokasi_penggunaan']);
$detail_lokasi = mysqli_real_escape_string($conn, $_POST['detail_lokasi']);
$tanggal_dibutuhkan = mysqli_real_escape_string($conn, $_POST['tanggal_dibutuhkan']);
$jumlah = intval($_POST['jumlah']);
$satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
$estimasi_harga = isset($_POST['estimasi_harga']) ? preg_replace('/[^\d]/', '', $_POST['estimasi_harga']) : 0;
$estimasi_harga = floatval($estimasi_harga);
$catatan_tambahan = mysqli_real_escape_string($conn, $_POST['catatan_tambahan']);

// Validasi input
$errors = [];

if (empty($kategori_asset)) $errors[] = 'Kategori asset harus dipilih';
if (empty($jenis_pengadaan)) $errors[] = 'Jenis pengadaan harus dipilih';
if (empty($tingkat_kepentingan)) $errors[] = 'Tingkat kepentingan harus dipilih';
if (empty($nama_pic)) $errors[] = 'Nama PIC harus diisi';
if (empty($deskripsi_asset)) $errors[] = 'Deskripsi asset harus diisi';
if (empty($alasan_pengajuan)) $errors[] = 'Alasan pengajuan harus diisi';
if ($jumlah < 1) $errors[] = 'Jumlah minimal 1';
if (empty($satuan)) $errors[] = 'Satuan harus dipilih';
if (empty($lokasi_penggunaan)) $errors[] = 'Lokasi penggunaan harus dipilih';
if (empty($tanggal_dibutuhkan)) $errors[] = 'Tanggal dibutuhkan harus diisi';

// Validasi tanggal
$today = date('Y-m-d');
if ($tanggal_dibutuhkan <= $today) {
    $errors[] = 'Tanggal dibutuhkan harus lebih dari hari ini';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: edit.php?id=" . urlencode($no_pengajuan));
    exit();
}

// Handle file upload dokumen pendukung
$file_attachment = $existing_data['file_attachment']; // Keep existing file by default

if (isset($_FILES['file_attachment']) && $_FILES['file_attachment']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/pengajuan/';
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    $file_info = pathinfo($_FILES['file_attachment']['name']);
    $file_extension = strtolower($file_info['extension']);
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = 'Format file tidak didukung! Gunakan PDF, DOC, DOCX, XLS, XLSX, JPG, atau PNG.';
        header("Location: edit.php?id=" . urlencode($no_pengajuan));
        exit();
    }
    
    if ($_FILES['file_attachment']['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = 'Ukuran file terlalu besar! Maksimal 5MB.';
        header("Location: edit.php?id=" . urlencode($no_pengajuan));
        exit();
    }
    
    // Generate unique filename
    $new_filename = $no_pengajuan . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['file_attachment']['name']);
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($_FILES['file_attachment']['tmp_name'], $upload_path)) {
        // Delete old file if exists
        if (!empty($existing_data['file_attachment']) && file_exists($upload_dir . $existing_data['file_attachment'])) {
            unlink($upload_dir . $existing_data['file_attachment']);
        }
        $file_attachment = $new_filename;
    } else {
        $_SESSION['error'] = 'Gagal mengupload file dokumen!';
        header("Location: edit.php?id=" . urlencode($no_pengajuan));
        exit();
    }
}

// Update data pengajuan
$update_query = "UPDATE asset_request SET 
                kategori_asset = '$kategori_asset',
                jenis_pengadaan = '$jenis_pengadaan',
                tingkat_kepentingan = '$tingkat_kepentingan',
                nama_pic = '$nama_pic',
                deskripsi_asset = '$deskripsi_asset',
                spesifikasi_merek = '$spesifikasi_merek',
                alasan_pengajuan = '$alasan_pengajuan',
                lokasi_penggunaan = $lokasi_penggunaan,
                detail_lokasi = '$detail_lokasi',
                tanggal_dibutuhkan = '$tanggal_dibutuhkan',
                jumlah = $jumlah,
                satuan = '$satuan',
                estimasi_harga = $estimasi_harga,
                catatan_tambahan = '$catatan_tambahan',
                file_attachment = " . ($file_attachment ? "'$file_attachment'" : "NULL") . ",
                updated_at = NOW()
                WHERE no_pengajuan = '$no_pengajuan'";

if (mysqli_query($conn, $update_query)) {
    $_SESSION['success'] = 'Pengajuan asset berhasil diperbarui!';
    header("Location: dashboard.php");
} else {
    $_SESSION['error'] = 'Gagal memperbarui pengajuan: ' . mysqli_error($conn);
    header("Location: edit.php?id=" . urlencode($no_pengajuan));
}

exit();
?>
