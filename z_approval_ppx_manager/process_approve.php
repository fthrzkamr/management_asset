<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_approval_ppx_manager\process_approve.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || (!$auth->isManagerFinance() && !$auth->isSuperAdmin())) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}

// Terima dari POST atau GET
$no_pengajuan = $_POST['no_pengajuan'] ?? $_GET['no_ppx'] ?? null;
$action = $_POST['action'] ?? $_GET['action'] ?? 'approve';
$user_id = (int)($_SESSION['id_user'] ?? 0);
$nama_user = $_SESSION['nama_user'] ?? '';

if (!$no_pengajuan) {
    $_SESSION['error'] = 'Nomor pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Cek data PPX dan divisi
$get_ppx = "SELECT 
    pr.*,
    d.nama_divisi
    FROM ppx_request pr
    LEFT JOIN divisi d ON pr.divisi = d.id_divisi
    WHERE pr.no_ppx = ?";

$stmt = mysqli_prepare($conn, $get_ppx);
mysqli_stmt_bind_param($stmt, 's', $no_pengajuan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Data pengajuan tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($result);
$divisi_name = strtolower($ppx_data['nama_divisi'] ?? '');
$current_status = $ppx_data['status'] ?? '';

// Cek apakah divisi Finance AR, Finance AP, Tax, atau Finance
$is_finance_division = (
    strpos($divisi_name, 'finance ar') !== false || 
    strpos($divisi_name, 'finance ap') !== false || 
    strpos($divisi_name, 'tax') !== false || 
    strpos($divisi_name, 'finance') !== false
);

// Cek apakah Leader approval sudah diisi
$leader_approved = !empty($ppx_data['created_at_leader']) && !empty($ppx_data['created_by_leader']);

// Tentukan status baru dan field yang diupdate
if ($action === 'approve') {
    // KASUS 1: Status Leader dan divisi Finance - perlu 2x approval
    if ($current_status === 'Leader' && $is_finance_division) {
        // Jika Leader approval belum diisi, isi itu dulu
        if (!$leader_approved) {
            // Step 1: Isi created_at_leader, created_by_leader dan ubah status jadi 'Manager'
            $update_sql = "UPDATE ppx_request SET 
                status = 'Manager',
                created_at_leader = NOW(),
                created_by_leader = ?
                WHERE no_ppx = ?";
                
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, 'ss', $nama_user, $no_pengajuan);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = 'Approval Leader berhasil. Silakan lakukan approval kedua untuk Manager.';
            } else {
                $_SESSION['error'] = 'Gagal memproses approval Leader: ' . mysqli_stmt_error($stmt);
            }
        } 
        // Jika Leader approval sudah diisi, lanjut ke Manager approval
        else {
            // Step 2: Isi created_at_manager, created_by_manager, dan ubah status
            // Cek nominal untuk menentukan next status
            $nominal_value = floatval(preg_replace('/[^0-9.]/', '', $ppx_data['nominal_ppx'] ?? '0'));
            
            if ($nominal_value > 10000000) {
                $next_status = 'Direktur';
            } else {
                $next_status = 'Finance';
            }
            
            // Override dengan final_status
            $rt_val = isset($ppx_data['request_type']) ? mysqli_real_escape_string($conn, $ppx_data['request_type']) : 'Regular';
            $cab_val = mysqli_real_escape_string($conn, $ppx_data['cabang'] ?? '');
            $div_val = (int)($ppx_data['divisi'] ?? 0);
    
            $q_route = "SELECT final_status FROM ppx_approval_routing 
                        WHERE divisi_id = $div_val 
                        AND (cabang IS NULL OR cabang = '' OR cabang = '$cab_val')
                        AND is_active = 1
                        ORDER BY (cabang = '$cab_val') DESC, 
                                 (request_type = '$rt_val') DESC LIMIT 1";
            $r_route = mysqli_query($conn, $q_route);
            if ($r_route && $row = mysqli_fetch_assoc($r_route)) {
                if (!empty($row['final_status'])) {
                    if ($row['final_status'] === 'Manager' || $row['final_status'] === 'Leader') {
                        $next_status = 'Selesai';
                    } else {
                        $next_status = $row['final_status'];
                    }
                }
            }
            
            $update_sql = "UPDATE ppx_request SET 
                status = ?,
                created_at_manager = NOW(),
                created_by_manager = ?
                WHERE no_ppx = ?";
                
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, 'sss', $next_status, $nama_user, $no_pengajuan);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Pengajuan berhasil disetujui dan diteruskan ke $next_status.";
            } else {
                $_SESSION['error'] = 'Gagal memproses approval Manager: ' . mysqli_stmt_error($stmt);
            }
        }
    }
    // KASUS 2: Status Manager normal - 1x approval saja
    elseif ($current_status === 'Manager') {
        // Cek nominal untuk menentukan next status
        $nominal_value = floatval(preg_replace('/[^0-9.]/', '', $ppx_data['nominal_ppx'] ?? '0'));
        
        // Cek apakah divisi termasuk HR, GA, atau IT (yang perlu approval GA)
        $divisi_need_ga = (
            stripos($divisi_name, 'hr') !== false || 
            stripos($divisi_name, 'ga') !== false || 
            stripos($divisi_name, 'it') !== false ||
            stripos($divisi_name, 'human resources') !== false ||
            stripos($divisi_name, 'general affairs') !== false ||
            stripos($divisi_name, 'information technology') !== false
        );
        
        // Tentukan next status berdasarkan divisi dan nominal
        if ($divisi_need_ga) {
            // Divisi HR/GA/IT → ke GA (Finance) untuk approval
            $next_status = 'Finance';
            $next_label = 'Verifikasi PJ';
        } else {
            // Divisi lain → langsung ke Direktur jika > 10 juta, atau Finance jika <= 10 juta
            if ($nominal_value > 10000000) {
                $next_status = 'Direktur';
                $next_label = 'Direktur';
            } else {
                // Skip GA, langsung selesai atau bisa ditambah status khusus
                // Untuk sementara, tetap ke Finance tapi akan diproses langsung oleh Finance
                $next_status = 'Finance';
                $next_label = 'Finance';
            }
        }
        
        // Override dengan final_status
        $rt_val = isset($ppx_data['request_type']) ? mysqli_real_escape_string($conn, $ppx_data['request_type']) : 'Regular';
        $cab_val = mysqli_real_escape_string($conn, $ppx_data['cabang'] ?? '');
        $div_val = (int)($ppx_data['divisi'] ?? 0);

        $q_route = "SELECT final_status FROM ppx_approval_routing 
                    WHERE divisi_id = $div_val 
                    AND (cabang IS NULL OR cabang = '' OR cabang = '$cab_val')
                    AND is_active = 1
                    ORDER BY (cabang = '$cab_val') DESC, 
                             (request_type = '$rt_val') DESC LIMIT 1";
        $r_route = mysqli_query($conn, $q_route);
        if ($r_route && $row = mysqli_fetch_assoc($r_route)) {
            if (!empty($row['final_status'])) {
                if ($row['final_status'] === 'Manager') {
                    $next_status = 'Selesai';
                    $next_label = 'Selesai';
                } else {
                    $next_status = $row['final_status'];
                    $next_label = $row['final_status'];
                }
            }
        }
        
        $update_sql = "UPDATE ppx_request SET 
            status = ?,
            created_at_manager = NOW(),
            created_by_manager = ?
            WHERE no_ppx = ?";
            
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'sss', $next_status, $nama_user, $no_pengajuan);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Pengajuan berhasil disetujui dan diteruskan ke $next_label.";
        } else {
            $_SESSION['error'] = 'Gagal memproses approval: ' . mysqli_stmt_error($stmt);
        }
    }
    // KASUS 3: Status tidak valid
    else {
        $_SESSION['error'] = 'Status pengajuan tidak valid untuk approval!';
    }
} 
// Jika action = reject, set status = 'Revisi'
else {
    $update_sql = "UPDATE ppx_request SET 
        status = 'Revisi',
        updated_at = NOW(),
        updated_by = ?
        WHERE no_ppx = ?";
        
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, 'ss', $nama_user, $no_pengajuan);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Pengajuan telah ditolak dan status diubah menjadi Revisi.';
    } else {
        $_SESSION['error'] = 'Gagal memproses penolakan: ' . mysqli_stmt_error($stmt);
    }
}

mysqli_stmt_close($stmt);
header("Location: dashboard.php");
exit();
?>