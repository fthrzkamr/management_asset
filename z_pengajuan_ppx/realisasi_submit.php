<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_ppx\realisasi_submit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header("Location: ../z_auth/login.php");
    exit();
}

// Pastikan form di-submit
if (!isset($_POST['submit_realisasi'])) {
    $_SESSION['error'] = 'Akses tidak valid!';
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['id_user'] ?? 0;
$nama_user = $_SESSION['nama_user'] ?? '';
$no_ppx = mysqli_real_escape_string($conn, $_POST['no_ppx'] ?? '');
$total_realisasi = (float)($_POST['total_realisasi'] ?? 0);
$uang_kembali = (float)($_POST['uang_kembali'] ?? 0);
$status_selisih = mysqli_real_escape_string($conn, $_POST['status_selisih'] ?? 'pas'); // kembali/kurang/pas
$keterangan_realisasi = mysqli_real_escape_string($conn, $_POST['keterangan_realisasi'] ?? '');
$confirm_pakai_uang_pribadi = isset($_POST['confirm_pakai_uang_pribadi']) ? 1 : 0;
// Jika form mengirimkan finalize_ppx=1 artinya user ingin menandai PPX selesai
$finalize_ppx = isset($_POST['finalize_ppx']) && $_POST['finalize_ppx'] == '1' ? 1 : 0;

// Ambil data detail realisasi (array barang yang dibeli)
$detail_realisasi = $_POST['realisasi'] ?? [];

// Validasi input
if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

if ($total_realisasi <= 0) {
    $_SESSION['error'] = 'Total realisasi harus lebih dari 0!';
    header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

// Validasi detail realisasi
if (empty($detail_realisasi)) {
    $_SESSION['error'] = 'Detail barang realisasi harus diisi minimal 1 item!';
    header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

// Cek apakah user adalah pemilik PPX ini dan status = Uang Muka
$check_query = "SELECT no_ppx, user_id, status, request_type, nominal_ppx, uang_muka 
                FROM ppx_request 
                WHERE no_ppx = ? AND user_id = ? AND status = 'Uang Muka'";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, 'si', $no_ppx, $user_id);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['error'] = 'PPX tidak ditemukan atau Anda tidak memiliki akses, atau status bukan Uang Muka!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($stmt);

// HAPUS validasi total realisasi tidak boleh melebihi uang muka
// Karena sekarang user bisa pakai uang pribadi
// if ($total_realisasi > $ppx_data['uang_muka']) {
//     $_SESSION['error'] = 'Total realisasi tidak boleh melebihi uang muka yang diterima!';
//     header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
//     exit();
// }

// Validasi jika ada kekurangan, user harus konfirmasi
if ($status_selisih === 'kurang' && !$confirm_pakai_uang_pribadi) {
    $_SESSION['error'] = 'Anda harus mengkonfirmasi bahwa telah menggunakan uang pribadi untuk menutupi kekurangan!';
    header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

// REMOVED: Pengecekan realisasi sudah pernah disubmit
// Sekarang kita mengizinkan multiple realisasi untuk satu PPX (realisasi bertahap)

// --- UPLOAD MULTIPLE FILES ---
$upload_path = '../uploads/ppx_realisasi/';
if (!is_dir($upload_path)) {
    mkdir($upload_path, 0755, true);
}

$allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 5 * 1024 * 1024; // 5MB
$max_files = 5;

// Function untuk handle multiple upload
function uploadMultipleFiles($files, $field_name, $no_ppx, $upload_path, $allowed_ext, $max_size, $max_files)
{
    $uploaded_files = [];

    if (!isset($files[$field_name]) || empty($files[$field_name]['name'])) {
        throw new Exception("Bukti realisasi harus diupload!");
    }

    // Handle jika single file atau multiple files
    if (is_array($files[$field_name]['name'])) {
        // Multiple files
        $file_count = count($files[$field_name]['name']);

        if ($file_count > $max_files) {
            throw new Exception("Maksimal $max_files file untuk bukti realisasi!");
        }

        for ($i = 0; $i < $file_count; $i++) {
            if ($files[$field_name]['error'][$i] !== UPLOAD_ERR_OK) {
                continue; // Skip file yang error
            }

            $file_name = $files[$field_name]['name'][$i];
            $file_tmp = $files[$field_name]['tmp_name'][$i];
            $file_size = $files[$field_name]['size'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Validasi extension
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception("Format file '$file_name' tidak valid! Hanya JPG, PNG, PDF yang diperbolehkan.");
            }

            // Validasi size
            if ($file_size > $max_size) {
                throw new Exception("File '$file_name' terlalu besar! Maksimal 5MB per file.");
            }

            // Generate nama file unik
            $new_filename = 'realisasi_' . $no_ppx . '_' . ($i + 1) . '_' . time() . '.' . $file_ext;
            $target_file = $upload_path . $new_filename;

            if (!move_uploaded_file($file_tmp, $target_file)) {
                throw new Exception("Gagal mengupload file '$file_name'!");
            }

            $uploaded_files[] = $new_filename;
        }
    } else {
        // Single file
        if ($files[$field_name]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error upload file bukti realisasi!");
        }

        $file_name = $files[$field_name]['name'];
        $file_tmp = $files[$field_name]['tmp_name'];
        $file_size = $files[$field_name]['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validasi extension
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception("Format file tidak valid! Hanya JPG, PNG, PDF yang diperbolehkan.");
        }

        // Validasi size
        if ($file_size > $max_size) {
            throw new Exception("File terlalu besar! Maksimal 5MB per file.");
        }

        // Generate nama file unik
        $new_filename = 'realisasi_' . $no_ppx . '_' . time() . '.' . $file_ext;
        $target_file = $upload_path . $new_filename;

        if (!move_uploaded_file($file_tmp, $target_file)) {
            throw new Exception("Gagal mengupload file bukti realisasi!");
        }

        $uploaded_files[] = $new_filename;
    }

    if (empty($uploaded_files)) {
        throw new Exception("Minimal 1 file bukti realisasi harus diupload!");
    }

    return $uploaded_files;
}

// Function untuk parse rupiah dari format "Rp 1.000.000"
function parseRupiah($rupiah)
{
    return (float) preg_replace('/[^0-9]/', '', $rupiah);
}

// --- BEGIN TRANSACTION ---
mysqli_begin_transaction($conn);

try {
    // Upload bukti realisasi
    $files_bukti = uploadMultipleFiles($_FILES, 'bukti_realisasi', $no_ppx, $upload_path, $allowed_ext, $max_size, $max_files);

    // Convert array ke JSON
    $json_bukti = json_encode($files_bukti, JSON_UNESCAPED_SLASHES);

    // Convert detail barang ke JSON
    $detail_barang_array = [];
    $total_item = 0;

    foreach ($detail_realisasi as $item) {
        $nama_barang = trim($item['nama_barang'] ?? '');
        $no_akun = trim($item['no_akun'] ?? '');
        $jumlah = (int)($item['jumlah'] ?? 0);
        $satuan = trim($item['satuan'] ?? '');
        $harga_satuan = parseRupiah($item['harga_satuan'] ?? '0');
        $total_harga = parseRupiah($item['total_harga'] ?? '0');
        $no_ppx_detail = $item['no_ppx_detail'] ?? null; // ID dari tabel ppx_request_detail

        // Validasi item
        if (empty($nama_barang) || $jumlah <= 0 || empty($satuan) || $harga_satuan <= 0) {
            throw new Exception('Data item realisasi tidak lengkap atau tidak valid!');
        }

        $detail_barang_array[] = [
            'no_ppx_detail' => $no_ppx_detail, // Simpan referensi ke item asli
            'nama_barang' => $nama_barang,
            'no_akun' => $no_akun,
            'jumlah' => $jumlah,
            'satuan' => $satuan,
            'harga_satuan' => $harga_satuan,
            'total_harga' => $total_harga
        ];

        $total_item++;
    }

    if ($total_item == 0) {
        throw new Exception('Tidak ada item realisasi yang valid!');
    }

    // Convert detail barang ke JSON
    $json_detail_barang = json_encode($detail_barang_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Hitung urutan realisasi (ke berapa kalinya untuk PPX ini)
    $count_query = "SELECT COUNT(*) as jumlah FROM ppx_realisasi WHERE no_ppx = ?";
    $stmt_count = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt_count, 's', $no_ppx);
    mysqli_stmt_execute($stmt_count);
    $count_result = mysqli_stmt_get_result($stmt_count);
    $count_row = mysqli_fetch_assoc($count_result);
    $urutan_realisasi = ($count_row['jumlah'] ?? 0) + 1;
    mysqli_stmt_close($stmt_count);

    // INSERT ke tabel ppx_realisasi (dengan status_selisih, confirm_pakai_uang_pribadi, dan urutan_realisasi)
    $insert_query = "INSERT INTO ppx_realisasi 
        (no_ppx, urutan_realisasi, tanggal_realisasi, total_realisasi, uang_kembali, status_selisih, keterangan_realisasi, bukti_realisasi, detail_barang, confirm_pakai_uang_pribadi, created_at) 
        VALUES 
        (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt_insert = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt_insert, 'siddssssi', 
        $no_ppx,
        $urutan_realisasi,
        $total_realisasi, 
        $uang_kembali, 
        $status_selisih,
        $keterangan_realisasi, 
        $json_bukti, 
        $json_detail_barang,
        $confirm_pakai_uang_pribadi
    );

    if (!mysqli_stmt_execute($stmt_insert)) {
        throw new Exception('Gagal menyimpan data realisasi: ' . mysqli_stmt_error($stmt_insert));
    }
    mysqli_stmt_close($stmt_insert);

    // Cek apakah SEMUA item dari ppx_request_detail sudah direalisasikan
    // Hitung total item di ppx_request_detail
    $query_total = "SELECT COUNT(*) as total FROM ppx_request_detail WHERE no_ppx = ?";
    $stmt_total = mysqli_prepare($conn, $query_total);
    mysqli_stmt_bind_param($stmt_total, 's', $no_ppx);
    mysqli_stmt_execute($stmt_total);
    $result_total = mysqli_stmt_get_result($stmt_total);
    $row_total = mysqli_fetch_assoc($result_total);
    $total_items = $row_total['total'];
    mysqli_stmt_close($stmt_total);

    // Hitung berapa item yang sudah direalisasikan (dari JSON)
    $query_realisasi = "SELECT detail_barang FROM ppx_realisasi WHERE no_ppx = ?";
    $stmt_real = mysqli_prepare($conn, $query_realisasi);
    mysqli_stmt_bind_param($stmt_real, 's', $no_ppx);
    mysqli_stmt_execute($stmt_real);
    $result_real = mysqli_stmt_get_result($stmt_real);
    
    $paid_detail_ids = [];
    while ($row_real = mysqli_fetch_assoc($result_real)) {
        if (!empty($row_real['detail_barang'])) {
            $detail_items = json_decode($row_real['detail_barang'], true);
            if (is_array($detail_items)) {
                foreach ($detail_items as $item) {
                    if (!empty($item['no_ppx_detail'])) {
                        $paid_detail_ids[$item['no_ppx_detail']] = true;
                    }
                }
            }
        }
    }
    mysqli_stmt_close($stmt_real);
    
    $paid_items_count = count($paid_detail_ids);
    $all_items_paid = ($total_items > 0 && $paid_items_count >= $total_items);

    // UPDATE status ppx_request:
    // - PPX Regular → 'Realisasi' (menunggu verifikasi opsional oleh GA atau Finance)
    // - PPX Monthly_Budget → 'Selesai' langsung (tidak berubah)
    $new_status = 'Selesai'; // default
    if (($ppx_data['request_type'] ?? 'Regular') === 'Regular') {
        $new_status = 'Realisasi';
    }

    $update_status = "UPDATE ppx_request 
                     SET status = ?,
                         updated_at = NOW()
                     WHERE no_ppx = ?";

    $stmt_update = mysqli_prepare($conn, $update_status);
    mysqli_stmt_bind_param($stmt_update, 'ss', $new_status, $no_ppx);

    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception('Gagal mengupdate status PPX: ' . mysqli_stmt_error($stmt_update));
    }
    mysqli_stmt_close($stmt_update);

    // Commit transaction
    mysqli_commit($conn);

    // Success message berdasarkan status selisih
    $_SESSION['success'] = 'Realisasi berhasil disubmit!';

    // Cek status selisih dari form
    $uang_kembali_abs = abs($uang_kembali);
    
    if ($status_selisih === 'kembali' && $uang_kembali_abs > 0) {
        // Ada uang kembali ke Finance
        $_SESSION['success'] .= 'Realisasi berhasil disubmit!';

    }

} catch (Exception $e) {
    // Rollback jika ada error
    mysqli_rollback($conn);

    // Hapus file yang sudah diupload (jika ada)
    if (isset($files_bukti)) {
        foreach ($files_bukti as $file) {
            $file_path = $upload_path . $file;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }

    $_SESSION['error'] = $e->getMessage();
    header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

header("Location: dashboard.php");
exit();
?>
