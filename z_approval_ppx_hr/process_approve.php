<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_hr\process_approve.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';



$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$auth->requirePermission('ppx', 'process');

if (!isset($_GET['no_ppx']) || !isset($_GET['action'])) {
    $_SESSION['error'] = 'Parameter tidak lengkap!';
    header("Location: dashboard.php");
    exit();
}

$no_ppx = mysqli_real_escape_string($conn, $_GET['no_ppx']);
$action = $_GET['action'];

try {
    mysqli_begin_transaction($conn);
    
    // Get current PPX data
    $query = "SELECT status, divisi FROM ppx_request WHERE no_ppx = '$no_ppx' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        throw new Exception('PPX tidak ditemukan!');
    }
    
    $ppx = mysqli_fetch_assoc($result);
    $current_status = $ppx['status'];
    $divisi_id = $ppx['divisi'];
    
    if ($action === 'approve') {
        // HR approves IT/HR/GA dari status Leader
        if ($current_status === 'Leader' && in_array($divisi_id, [1, 6, 7])) {
            $new_status = 'Selesai'; // Default lama
            
            // Cek final_status dari routing table
            // Karena di file ini tidak men-select 'cabang' dan 'request_type' dari PPX,
            // kita gunakan query termudah mencocokkan divisi dsb.
            $q_route = "SELECT final_status FROM ppx_approval_routing 
                        WHERE divisi_id = " . (int)$divisi_id . " 
                        AND initial_status IN ('HR', 'Leader')
                        AND is_active = 1 
                        ORDER BY id DESC LIMIT 1";
            $r_route = mysqli_query($conn, $q_route);
            if ($r_route && $row = mysqli_fetch_assoc($r_route)) {
                if (!empty($row['final_status'])) {
                    if ($row['final_status'] === 'HR') {
                        $new_status = 'Selesai'; // HR adalah final
                    } else {
                        // Jika ada next status (misal GA), oper ke sana
                        $new_status = $row['final_status'];
                    }
                }
            }
            
            $update_query = "UPDATE ppx_request 
                           SET status = '$new_status',
                               updated_at = NOW()
                           WHERE no_ppx = '$no_ppx'";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception('Gagal mengupdate status: ' . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = 'PPX berhasil diapprove!';
        } else {
            throw new Exception('Status PPX tidak valid untuk approval!');
        }
    } else {
        throw new Exception('Action tidak valid!');
    }
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
}

header("Location: dashboard.php");
exit();
?>
