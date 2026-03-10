<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

header('Content-Type: application/json; charset=utf-8');

// minimal check akses
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$no_ppx = isset($_GET['no_ppx']) ? mysqli_real_escape_string($conn, $_GET['no_ppx']) : '';
if (!$no_ppx) {
    echo json_encode(['success' => false, 'message' => 'Nomor PPX tidak diberikan']);
    exit();
}

// ambil header
$q = "SELECT no_ppx, nama_pic, jabatan, divisi, cabang, nominal_ppx, 
      COALESCE(nominal_tf,0) AS nominal_tf, COALESCE(ket_tf,'') AS ket_tf, 
      COALESCE(upload_tf,'') AS upload_tf,
      COALESCE(nama_bank,'') AS nama_bank, COALESCE(nama_rek,'') AS nama_rek, COALESCE(no_rek,'') AS no_rek
      FROM ppx_request WHERE no_ppx = '$no_ppx' LIMIT 1";
$r = mysqli_query($conn, $q);
if (!$r || mysqli_num_rows($r) == 0) {
    echo json_encode(['success' => false, 'message' => 'Data PPX tidak ditemukan']);
    exit();
}
$hdr = mysqli_fetch_assoc($r);

// ambil items
$items = [];
$q2 = "SELECT no_ppx_detail, no_akun, rincian, jumlah, satuan, harga_satuan, total_harga, deskripsi, status_tf FROM ppx_request_detail WHERE no_ppx = '$no_ppx' ORDER BY no_ppx_detail";
$r2 = mysqli_query($conn, $q2);
if ($r2) {
    while ($it = mysqli_fetch_assoc($r2)) {
        // pastikan numeric fields dikirim sebagai angka atau string sesuai kebutuhan
        $it['harga_satuan'] = (int)$it['harga_satuan'];
        $it['total_harga'] = (int)$it['total_harga'];
        $it['jumlah'] = $it['jumlah'] !== null ? (int)$it['jumlah'] : null;
        // pastikan status_tf tersedia sebagai string (mis. 'belum'/'sudah')
        $it['status_tf'] = isset($it['status_tf']) ? (string)$it['status_tf'] : 'belum';
        $items[] = $it;
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'no_ppx' => $hdr['no_ppx'],
        'nama_pic' => $hdr['nama_pic'],
        'jabatan' => $hdr['jabatan'],
        'divisi' => $hdr['divisi'],
        'cabang' => $hdr['cabang'],
        'nominal_ppx' => (int)($hdr['nominal_ppx'] ?? 0),
        'nominal_tf' => (int)($hdr['nominal_tf'] ?? 0),
        'ket_tf' => (string)($hdr['ket_tf'] ?? ''),
        // upload_tf & bank fields
        'upload_tf' => $hdr['upload_tf'] ?? '',
        'nama_bank' => (string)($hdr['nama_bank'] ?? ''),
        'nama_rek' => (string)($hdr['nama_rek'] ?? ''),
        'no_rek' => (string)($hdr['no_rek'] ?? ''),
        'items' => $items
    ]
]);
exit();
?>