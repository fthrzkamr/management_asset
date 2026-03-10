<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Determine base path for includes
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

// Create auth instance
$auth = new AuthHelper($conn);

// Check if user is logged in and has permission to approve
$can_approve = method_exists($auth, 'canApproveATK') ? $auth->canApproveATK() : $auth->isGA() || $auth->isSuperAdmin();
if (!$auth->isLoggedIn() || !$can_approve) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pengambilan = intval($_POST['id_pengambilan']);
    $status = $_POST['status']; // 'Approved' or 'Rejected'

    // Debug log setup
    $logDir = __DIR__ . '/../tmp';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/approve_debug.log';
    $logHandle = @fopen($logFile, 'a');
    if ($logHandle) {
        fwrite($logHandle, "\n---\n" . date('Y-m-d H:i:s') . " - process_approve called\n");
        fwrite($logHandle, "POST: " . json_encode($_POST) . "\n");
        fwrite($logHandle, "X-Requested-With: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'none') . "\n");
        fwrite($logHandle, "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . "\n");
        fwrite($logHandle, "USER_SESSION: " . (session_id() ?: 'no-session') . " USER_ID: " . ($_SESSION['id_user'] ?? 'none') . "\n");
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Update status pengajuan
        $sql_update = "UPDATE atk_pengambilan 
                       SET status = ?
                       WHERE id_pengambilan = ?";
        $stmt = mysqli_prepare($conn, $sql_update);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, 'si', $status, $id_pengambilan);
        mysqli_stmt_execute($stmt);

        if ($status == 'Approved') {
            // Get detail items untuk update stok
            $sql_items = "SELECT id_barang, qty_diminta as jumlah FROM atk_pengambilan_detail WHERE id_pengambilan = ?";
            $stmt_items = mysqli_prepare($conn, $sql_items);
            if (!$stmt_items) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_items, 'i', $id_pengambilan);
            mysqli_stmt_execute($stmt_items);
            $items = mysqli_stmt_get_result($stmt_items);

            // Update stok dan create barang keluar record
            $user_id = $_SESSION['id_user'];
            // get nama pemohon
            $sql_pem = "SELECT nama_pengambil FROM atk_pengambilan WHERE id_pengambilan = ?";
            $stmt_pem = mysqli_prepare($conn, $sql_pem);
            if (!$stmt_pem) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_pem, 'i', $id_pengambilan);
            mysqli_stmt_execute($stmt_pem);
            $res_pem = mysqli_stmt_get_result($stmt_pem);
            $pem_row = mysqli_fetch_assoc($res_pem);
            $nama_penerima = $pem_row['nama_pengambil'] ?? '';

            // generate nomor_keluar for this pengambilan (BK-YYYYMMDD-XXX)
            $today = date('Ymd');
            $sql_last = "SELECT COALESCE(MAX(CAST(SUBSTRING(nomor_keluar, -3) AS UNSIGNED)), 0) as last_number FROM atk_barang_keluar WHERE DATE(tanggal_keluar) = CURDATE()";
            $stmt_last = mysqli_prepare($conn, $sql_last);
            if (!$stmt_last) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_execute($stmt_last);
            $res_last = mysqli_stmt_get_result($stmt_last);
            $last_row = mysqli_fetch_assoc($res_last);
            $new_number = intval($last_row['last_number'] ?? 0) + 1;
            $nomor_keluar_base = sprintf('BK-%s-', $today);
            
            while ($item = mysqli_fetch_assoc($items)) {
                // Generate unique nomor_keluar for each item
                $nomor_keluar = $nomor_keluar_base . str_pad($new_number, 3, '0', STR_PAD_LEFT);
                $new_number++;
                // Update stok (sekarang di tabel inventory_stok)
                // Cek apakah record stok sudah ada
                $sql_check_stok = "SELECT quantity FROM inventory_stok WHERE id_barang = ?";
                $stmt_check_stok = mysqli_prepare($conn, $sql_check_stok);
                mysqli_stmt_bind_param($stmt_check_stok, 'i', $item['id_barang']);
                mysqli_stmt_execute($stmt_check_stok);
                $res_check_stok = mysqli_stmt_get_result($stmt_check_stok);

                if ($row_stok = mysqli_fetch_assoc($res_check_stok)) {
                    // Update: Gunakan GREATEST(0, quantity - ?) agar tidak minus
                    $sql_update_stok = "UPDATE inventory_stok 
                                        SET quantity = GREATEST(0, quantity - ?) 
                                        WHERE id_barang = ?";
                    $stmt_stok = mysqli_prepare($conn, $sql_update_stok);
                    mysqli_stmt_bind_param($stmt_stok, 'ii', $item['jumlah'], $item['id_barang']);
                } else {
                    // Buat record baru jika belum ada, set 0 (karena tidak mau minus)
                    $sql_update_stok = "INSERT INTO inventory_stok (id_barang, quantity, created_by) 
                                        VALUES (?, 0, ?)";
                    $stmt_stok = mysqli_prepare($conn, $sql_update_stok);
                    mysqli_stmt_bind_param($stmt_stok, 'is', $item['id_barang'], $user_id);
                }
                
                if (!$stmt_stok) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                mysqli_stmt_execute($stmt_stok);

                // Insert ke atk_barang_keluar (match schema)
                $sql_keluar = "INSERT INTO atk_barang_keluar 
                               (nomor_keluar, id_pengambilan, id_barang, qty_keluar, nama_penerima, diserahkan_oleh) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_keluar = mysqli_prepare($conn, $sql_keluar);
                if (!$stmt_keluar) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_keluar, 'siiiss', $nomor_keluar, $id_pengambilan, $item['id_barang'], $item['jumlah'], $nama_penerima, $user_id);
                mysqli_stmt_execute($stmt_keluar);

                // Log activity (match atk_activity_log schema)
                $activity_type_log = 'barang_keluar';
                $activity_desc_log = "Barang keluar - Approval pengambilan ID: $id_pengambilan";
                $ref_table = 'atk_pengambilan';
                $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '';
                $sql_log = "INSERT INTO atk_activity_log 
                            (activity_type, reference_id, reference_table, description, user_id, ip_address) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_log = mysqli_prepare($conn, $sql_log);
                if (!$stmt_log) {
                    throw new Exception("Prepare failed: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_log, 'sisiss', $activity_type_log, $id_pengambilan, $ref_table, $activity_desc_log, $user_id, $ip_addr);
                mysqli_stmt_execute($stmt_log);
            }
        }

        // Log activity approval
        $activity_type = $status == 'Approved' ? 'approval' : 'rejection';
        $activity_desc = ($status == 'Approved' ? 'Approved' : 'Rejected') . " pengajuan ATK ID: $id_pengambilan";
        $user_id = $_SESSION['id_user'];
        $ref_table = 'atk_pengambilan';
        $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $sql_log = "INSERT INTO atk_activity_log 
                    (activity_type, reference_id, reference_table, description, user_id, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_log = mysqli_prepare($conn, $sql_log);
        if (!$stmt_log) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt_log, 'sisiss', $activity_type, $id_pengambilan, $ref_table, $activity_desc, $user_id, $ip_addr);
        mysqli_stmt_execute($stmt_log);

        // Commit transaction
        mysqli_commit($conn);

        $message = "Pengajuan berhasil " . ($status == 'Approved' ? 'disetujui' : 'ditolak') . "!";

        // write debug success
        if (!empty($logHandle)) {
            fwrite($logHandle, "SUCCESS: " . $message . "\n");
            fclose($logHandle);
        }

        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit();
        } else {
            $_SESSION['success_message'] = $message;
            header("Location: dashboard.php");
            exit();
        }

    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        $errMsg = "Gagal memproses pengajuan: " . $e->getMessage();

        // write debug error
        if (!empty($logHandle)) {
            fwrite($logHandle, "ERROR: " . $errMsg . "\n");
            // include last db error
            fwrite($logHandle, "Last DB error: " . mysqli_error($conn) . "\n");
            fclose($logHandle);
        }

        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        } else {
            $_SESSION['error_message'] = $errMsg;
            header("Location: dashboard.php");
            exit();
        }
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>
