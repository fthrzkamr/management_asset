<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Determine base path for includes
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';


// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: form_public.php");
    exit();
}

// Get form data
$nama_pengambil = mysqli_real_escape_string($conn, trim($_POST['nama_pengambil']));
$divisi = mysqli_real_escape_string($conn, trim($_POST['divisi']));
$items = $_POST['items'];

// Get IP and session info
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$session_id = session_id();

// Validation
if (empty($nama_pengambil) || empty($divisi) || empty($items)) {
    $_SESSION['error_message'] = "Data tidak lengkap. Pastikan semua field wajib terisi.";
    header("Location: form_public.php");
    exit();
}

// Check rate limiting
$today = date('Y-m-d');
$check_rate = "SELECT COUNT(*) as total FROM atk_pengambilan 
               WHERE ip_address = '$ip_address' AND DATE(tanggal_pengambilan) = '$today'";
$result = mysqli_query($conn, $check_rate);
$rate_data = mysqli_fetch_assoc($result);

$max_requests = 5; // Bisa diambil dari atk_settings
if ($rate_data['total'] >= $max_requests) {
    $_SESSION['error_message'] = "Anda sudah mencapai batas maksimal $max_requests pengajuan per hari.";
    header("Location: form_public.php");
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Generate nomor pengambilan manually
    $today = date('Ymd');
    $check_last = "SELECT nomor_pengambilan FROM atk_pengambilan 
                   WHERE DATE(tanggal_pengambilan) = CURDATE() 
                   ORDER BY id_pengambilan DESC LIMIT 1";
    $result_last = mysqli_query($conn, $check_last);
    
    if ($result_last && mysqli_num_rows($result_last) > 0) {
        $last_row = mysqli_fetch_assoc($result_last);
        $last_number = intval(substr($last_row['nomor_pengambilan'], -3));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    $nomor_pengambilan = 'ATK-REQ-' . $today . '-' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
    
    // Count total items and qty
    $total_item = count($items);
    $total_qty = 0;
    foreach ($items as $item) {
        $total_qty += intval($item['qty_diminta'] ?? $item['qty'] ?? 0);
    }
    
    // Insert to atk_pengambilan
    $sql_pengambilan = "INSERT INTO atk_pengambilan 
                        (nomor_pengambilan, nama_pengambil, divisi, 
                         status, total_item, total_qty, ip_address, user_agent, session_id) 
                        VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql_pengambilan);
    mysqli_stmt_bind_param($stmt, 'sssiisss', 
        $nomor_pengambilan, $nama_pengambil, $divisi,
        $total_item, $total_qty, $ip_address, $user_agent, $session_id
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Gagal menyimpan data pengambilan: " . mysqli_error($conn));
    }
    
    $id_pengambilan = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    // Insert items to atk_pengambilan_detail
    $sql_detail = "INSERT INTO atk_pengambilan_detail 
                   (id_pengambilan, id_barang, qty_diminta) 
                   VALUES (?, ?, ?)";
    
    $stmt_detail = mysqli_prepare($conn, $sql_detail);
    
    foreach ($items as $item) {
        if (empty($item['id_barang']) || empty($item['qty_diminta']) && empty($item['qty'])) {
            continue;
        }
        
        $id_barang = intval($item['id_barang']);
        $qty = intval($item['qty_diminta'] ?? $item['qty'] ?? 0);
        
        // Validasi: barang harus ada di master inventory
        $check_barang = "SELECT b.id_barang, b.nama_barang, COALESCE(s.quantity, 0) as stok_tersedia
                          FROM inventory_atk b
                          LEFT JOIN inventory_stok s ON b.id_barang = s.id_barang
                          WHERE b.id_barang = $id_barang";
        $barang_result = mysqli_query($conn, $check_barang);
        $barang_data   = mysqli_fetch_assoc($barang_result);
        
        if (!$barang_data) {
            throw new Exception("Barang dengan ID $id_barang tidak ditemukan.");
        }
        
        if ($qty <= 0) {
            throw new Exception("Jumlah barang '{$barang_data['nama_barang']}' harus lebih dari 0.");
        }
        
        mysqli_stmt_bind_param($stmt_detail, 'iii', 
            $id_pengambilan, $id_barang, $qty
        );
        
        if (!mysqli_stmt_execute($stmt_detail)) {
            throw new Exception("Gagal menyimpan detail item: " . mysqli_error($conn));
        }
    }
    
    mysqli_stmt_close($stmt_detail);
    
    // Log activity
    $activity_desc = "Pengajuan baru dari $nama_pengambil ($divisi) - $total_item item, total qty: $total_qty";
    $sql_log = "INSERT INTO atk_activity_log 
                (activity_type, reference_id, reference_table, description, ip_address) 
                VALUES ('pengambilan', ?, 'atk_pengambilan', ?, ?)";
    
    $stmt_log = mysqli_prepare($conn, $sql_log);
    mysqli_stmt_bind_param($stmt_log, 'iss', $id_pengambilan, $activity_desc, $ip_address);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success_message'] = "Pengajuan berhasil dikirim dengan nomor: " . $nomor_pengambilan;
    header("Location: form_public.php");
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: form_public.php");
    exit();
}
?>
