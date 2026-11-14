<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_ppx\realisasi_submit.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header("Location: ../login.php");
    exit();
}

$user_id   = (int)($_SESSION['id_user'] ?? 0);
$nama_user = $_SESSION['nama_user'] ?? '';
$action    = trim($_POST['action'] ?? (isset($_POST['submit_realisasi']) ? 'submit_legacy' : 'save_kas'));
$no_ppx    = mysqli_real_escape_string($conn, trim($_POST['no_ppx'] ?? ''));

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Cek apakah user adalah pemilik PPX ini atau punya akses (admin / finance)
$check_query = "SELECT no_ppx, user_id, status, request_type, nominal_ppx, uang_muka, cabang 
                FROM ppx_request 
                WHERE no_ppx = '$no_ppx' LIMIT 1";
$check_result = mysqli_query($conn, $check_query);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($check_result);
$is_owner = ($ppx_data['user_id'] == $user_id);
$is_admin = ($auth->hasRole('admin') || $auth->hasRole('finance'));

if (!$is_owner && !$is_admin) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk PPX ini!';
    header("Location: dashboard.php");
    exit();
}

// Upload directory
$upload_path = __DIR__ . '/../uploads/ppx_realisasi/';
if (!is_dir($upload_path)) {
    @mkdir($upload_path, 0755, true);
}

// Helper generate No. Transaksi Kas
function generateNoTransaksiKasPPX($conn, $jenis) {
    $prefix = ($jenis === 'BKM') ? 'BKM' : 'BKK';
    $yearmonth = date('Ym');
    $pattern = $prefix . '-' . $yearmonth . '-%';
    
    $q = mysqli_query($conn, "SELECT no_transaksi FROM ppx_realisasi WHERE no_transaksi LIKE '$pattern' ORDER BY no_transaksi DESC LIMIT 1");
    $next_seq = 1;
    if ($q && mysqli_num_rows($q) > 0) {
        $last_no = mysqli_fetch_assoc($q)['no_transaksi'];
        $parts = explode('-', $last_no);
        $last_seq = (int)end($parts);
        $next_seq = $last_seq + 1;
    }
    return $prefix . '-' . $yearmonth . '-' . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ACTION: DELETE KAS TRANSACTION
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_kas') {
    $id_del = (int)($_POST['id_realisasi'] ?? 0);
    if ($id_del <= 0) {
        $_SESSION['error'] = 'ID transaksi tidak valid.';
        header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }

    $chk_r = mysqli_query($conn, "SELECT * FROM ppx_realisasi WHERE id_realisasi = $id_del AND no_ppx = '$no_ppx' LIMIT 1");
    if (!$chk_r || mysqli_num_rows($chk_r) === 0) {
        $_SESSION['error'] = 'Transaksi realisasi tidak ditemukan.';
        header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }
    $row_del = mysqli_fetch_assoc($chk_r);

    // Hapus file fisik bukti
    if (!empty($row_del['bukti_realisasi'])) {
        $arr_f = json_decode($row_del['bukti_realisasi'], true);
        if (!is_array($arr_f)) {
            $arr_f = explode(',', $row_del['bukti_realisasi']);
        }
        foreach ($arr_f as $f) {
            $f = trim($f);
            if ($f && file_exists($upload_path . $f)) {
                @unlink($upload_path . $f);
            }
        }
    }

    mysqli_query($conn, "DELETE FROM ppx_realisasi WHERE id_realisasi = $id_del");
    $_SESSION['success'] = 'Transaksi kas berhasil dihapus.';
    header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ACTION: FINALIZE PPX (Tandai Realisasi Selesai)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'finalize_ppx') {
    $q_cnt = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ppx_realisasi WHERE no_ppx = '$no_ppx'");
    $cnt_real = (int)(mysqli_fetch_assoc($q_cnt)['cnt'] ?? 0);
    if ($cnt_real === 0) {
        $_SESSION['error'] = 'Belum ada transaksi realisasi kas yang diinput untuk PPX ini.';
        header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }

    // PPX Regular → 'Realisasi' (menunggu verifikasi oleh GA / Finance)
    // PPX Monthly_Budget → 'Selesai'
    $new_status = (($ppx_data['request_type'] ?? 'Regular') === 'Regular') ? 'Realisasi' : 'Selesai';
    mysqli_query($conn, "UPDATE ppx_request SET status = '$new_status', updated_at = NOW() WHERE no_ppx = '$no_ppx'");

    $_SESSION['success'] = "Realisasi untuk PPX #$no_ppx berhasil diselesaikan dan diteruskan ke Finance!";
    header("Location: dashboard.php");
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ACTION: SAVE TRANSAKSI KAS (BKK / BKM)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'save_kas' || $action === 'submit_realisasi' || !empty($_POST['jenis_transaksi'])) {
    mysqli_begin_transaction($conn);
    try {
        $jenis_transaksi = in_array($_POST['jenis_transaksi'] ?? '', ['BKK', 'BKM']) ? $_POST['jenis_transaksi'] : 'BKK';
        $tanggal_real    = !empty($_POST['tanggal_realisasi']) ? $_POST['tanggal_realisasi'] : date('Y-m-d');
        $dibayar_kepada  = trim($_POST['dibayar_kepada'] ?? '');
        $keperluan       = trim($_POST['keperluan'] ?? '');

        if (empty($dibayar_kepada)) {
            throw new Exception(($jenis_transaksi === 'BKM' ? 'Diterima dari' : 'Dibayar kepada') . ' wajib diisi.');
        }

        // Ambil item-item rincian dari form dynamic table
        $items_ids  = $_POST['item_detail_id'] ?? [];
        $items_kets = $_POST['item_keterangan'] ?? [];
        $items_amts = $_POST['item_jumlah'] ?? [];

        // Fallback jika form legacy
        if (empty($items_ids) && !empty($_POST['realisasi'])) {
            foreach ($_POST['realisasi'] as $legacy_it) {
                $items_ids[]  = $legacy_it['no_ppx_detail'] ?? '0';
                $items_kets[] = $legacy_it['nama_barang'] ?? '';
                $items_amts[] = $legacy_it['total_harga'] ?? '0';
            }
        }

        if (empty($items_ids)) {
            throw new Exception("Rincian akun/item biaya harus diisi minimal 1 baris.");
        }

        // Proses Upload File Bukti
        $uploaded_files = [];
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB

        // Cek $_FILES['bukti'] atau $_FILES['bukti_realisasi']
        $files_arr = $_FILES['bukti'] ?? ($_FILES['bukti_realisasi'] ?? null);
        if ($files_arr && !empty($files_arr['name'])) {
            $is_multi = is_array($files_arr['name']);
            $count = $is_multi ? count($files_arr['name']) : 1;

            if ($count > 10) {
                throw new Exception("Maksimal 10 file bukti nota!");
            }

            for ($i = 0; $i < $count; $i++) {
                $err  = $is_multi ? $files_arr['error'][$i] : $files_arr['error'];
                if ($err !== UPLOAD_ERR_OK) continue;

                $name = $is_multi ? $files_arr['name'][$i] : $files_arr['name'];
                $tmp  = $is_multi ? $files_arr['tmp_name'][$i] : $files_arr['tmp_name'];
                $sz   = $is_multi ? $files_arr['size'][$i] : $files_arr['size'];
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_ext)) {
                    throw new Exception("Format file '$name' tidak valid! Hanya JPG, PNG, PDF yang diperbolehkan.");
                }
                if ($sz > $max_size) {
                    throw new Exception("Ukuran file '$name' melebihi 5MB.");
                }

                $clean_no = preg_replace('/[^a-zA-Z0-9_-]/', '_', $no_ppx);
                $new_fname = 'real_' . $clean_no . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (!move_uploaded_file($tmp, $upload_path . $new_fname)) {
                    throw new Exception("Gagal menyimpan file upload '$name'.");
                }
                $uploaded_files[] = $new_fname;
            }
        }

        if (empty($uploaded_files) && empty($_POST['id_realisasi'])) {
            throw new Exception("Bukti nota / kwitansi wajib diupload!");
        }

        // Hitung total transaksi & bangun detail barang JSON
        $detail_barang_array = [];
        $total_transaksi = 0;

        foreach ($items_ids as $idx => $id_val) {
            $raw_amt = str_replace('.', '', $items_amts[$idx] ?? '0');
            $amt_val = max(0, (float)$raw_amt);
            if ($amt_val <= 0) continue;

            $line_ket = trim($items_kets[$idx] ?? '');
            if (empty($line_ket)) $line_ket = $keperluan;

            $nama_item = '';
            $no_akun = '';
            $id_detail_db = 0;

            if (str_starts_with($id_val, 'ppx_')) {
                $id_detail_db = (int)substr($id_val, 4);
            } elseif (is_numeric($id_val)) {
                $id_detail_db = (int)$id_val;
            }

            if ($id_detail_db > 0) {
                $q_it = mysqli_query($conn, "SELECT rincian, no_akun FROM ppx_request_detail WHERE no_ppx_detail = $id_detail_db LIMIT 1");
                if ($q_it && $r_it = mysqli_fetch_assoc($q_it)) {
                    $nama_item = $r_it['rincian'];
                    $no_akun   = $r_it['no_akun'];
                }
            } elseif (str_starts_with($id_val, 'akun_')) {
                $no_akun = substr($id_val, 5);
                $nama_item = $line_ket;
            }

            if (empty($nama_item)) {
                $nama_item = $line_ket ?: 'Biaya Operasional';
            }

            $detail_barang_array[] = [
                'no_ppx_detail' => $id_detail_db,
                'nama_barang'   => $nama_item,
                'no_akun'       => $no_akun,
                'keterangan'    => $line_ket,
                'jumlah'        => 1,
                'satuan'        => 'Unit',
                'harga_satuan'  => $amt_val,
                'total_harga'   => $amt_val
            ];

            $total_transaksi += $amt_val;
        }

        if ($total_transaksi <= 0) {
            throw new Exception("Total nominal transaksi kas harus lebih dari Rp 0.");
        }

        // Generate No Transaksi & Urutan Realisasi
        $no_transaksi = generateNoTransaksiKasPPX($conn, $jenis_transaksi);
        $q_cnt = mysqli_query($conn, "SELECT COUNT(*) as jumlah FROM ppx_realisasi WHERE no_ppx = '$no_ppx'");
        $urutan = (int)(mysqli_fetch_assoc($q_cnt)['jumlah'] ?? 0) + 1;

        // Tentukan total_realisasi vs uang_kembali
        $nom_realisasi = ($jenis_transaksi === 'BKK') ? $total_transaksi : 0;
        $nom_kembali   = ($jenis_transaksi === 'BKM') ? $total_transaksi : 0;
        $status_selisih = ($jenis_transaksi === 'BKM') ? 'kembali' : 'pas';

        // Hitung komparasi akumulatif terhadap uang muka
        $uang_muka = (float)$ppx_data['uang_muka'];
        $q_prev = mysqli_query($conn, "SELECT 
            SUM(CASE WHEN jenis_transaksi = 'BKM' THEN 0 ELSE total_realisasi END) as sum_bkk,
            SUM(CASE WHEN jenis_transaksi = 'BKM' THEN uang_kembali ELSE 0 END) as sum_bkm
        FROM ppx_realisasi WHERE no_ppx = '$no_ppx'");
        $prev_data = mysqli_fetch_assoc($q_prev);
        $tot_bkk_all = (float)($prev_data['sum_bkk'] ?? 0) + $nom_realisasi;
        $tot_bkm_all = (float)($prev_data['sum_bkm'] ?? 0) + $nom_kembali;
        $net_spent = $tot_bkk_all - $tot_bkm_all;

        $confirm_pakai_uang_pribadi = 0;
        if ($net_spent > $uang_muka) {
            $status_selisih = 'kurang';
            $confirm_pakai_uang_pribadi = 1;
        } elseif ($net_spent < $uang_muka && $tot_bkm_all > 0) {
            $status_selisih = 'kembali';
        }

        $json_bukti  = mysqli_real_escape_string($conn, json_encode($uploaded_files));
        $json_detail = mysqli_real_escape_string($conn, json_encode($detail_barang_array));
        $dk_safe     = mysqli_real_escape_string($conn, $dibayar_kepada);
        $kp_safe     = mysqli_real_escape_string($conn, $keperluan);
        $ket_safe    = mysqli_real_escape_string($conn, $keperluan ?: ($jenis_transaksi === 'BKM' ? 'Pengembalian Uang Muka' : 'Realisasi Kas Keluar'));

        $sql_insert = "INSERT INTO ppx_realisasi 
            (no_ppx, no_transaksi, jenis_transaksi, urutan_realisasi, tanggal_realisasi, dibayar_kepada, keperluan, total_realisasi, uang_kembali, status_selisih, keterangan_realisasi, bukti_realisasi, detail_barang, confirm_pakai_uang_pribadi, created_at, status)
            VALUES 
            ('$no_ppx', '$no_transaksi', '$jenis_transaksi', $urutan, '$tanggal_real', '$dk_safe', '$kp_safe', $nom_realisasi, $nom_kembali, '$status_selisih', '$ket_safe', '$json_bukti', '$json_detail', $confirm_pakai_uang_pribadi, NOW(), 'Approved')";

        if (!mysqli_query($conn, $sql_insert)) {
            throw new Exception("Gagal menyimpan transaksi realisasi: " . mysqli_error($conn));
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Transaksi Kas #$no_transaksi ($jenis_transaksi) sebesar Rp " . number_format($total_transaksi, 0, ',', '.') . " berhasil disimpan!";
        header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        // Hapus file yang sempat terupload jika gagal
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $uf) {
                if (file_exists($upload_path . $uf)) @unlink($upload_path . $uf);
            }
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
        exit();
    }
}

// Fallback
header("Location: realisasi.php?no_ppx=" . urlencode($no_ppx));
exit();
