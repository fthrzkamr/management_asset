<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_master_ppx_routing\process.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || (!$auth->isSuperAdmin() && !$auth->isManagerFinance())) {
    $_SESSION['error'] = 'Anda tidak memiliki akses!';
    header("Location: dashboard.php");
    exit();
}

// ── Cek apakah kolom request_type sudah ada di tabel ──
$col_check = mysqli_query($conn,
    "SELECT COLUMN_NAME FROM information_schema.columns
     WHERE table_schema = DATABASE()
     AND table_name = 'ppx_approval_routing'
     AND column_name = 'request_type'");
$has_rt_col = ($col_check && mysqli_num_rows($col_check) > 0);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    mysqli_begin_transaction($conn);
    
    if ($action === 'add') {
        $divisi_id     = (int)$_POST['divisi_id'];
        $cabang        = !empty($_POST['cabang']) ? mysqli_real_escape_string($conn, trim($_POST['cabang'])) : null;
        $initial_status = mysqli_real_escape_string($conn, $_POST['initial_status']);
        $final_status   = !empty($_POST['final_status']) ? mysqli_real_escape_string($conn, trim($_POST['final_status'])) : null;
        $approver_role = mysqli_real_escape_string($conn, $_POST['approver_role']);
        $skip_leader   = (int)$_POST['skip_leader'];
        $is_active     = (int)$_POST['is_active'];
        $request_type  = ($has_rt_col && isset($_POST['request_type']) && $_POST['request_type'] !== '')
                         ? mysqli_real_escape_string($conn, $_POST['request_type']) : null;

        // Validasi divisi exists
        $chk = mysqli_query($conn, "SELECT id_divisi FROM divisi WHERE id_divisi = $divisi_id");
        if (!$chk || mysqli_num_rows($chk) === 0) throw new Exception('Divisi tidak valid!');

        // Validasi role exists
        $chk = mysqli_query($conn, "SELECT nama_role FROM roles WHERE nama_role = '$approver_role'");
        if (!$chk || mysqli_num_rows($chk) === 0) throw new Exception('Role tidak valid!');
        
        // Check duplicate (tanpa request_type jika kolom belum ada)
        $cabang_check = $cabang ? "AND cabang = '$cabang'" : "AND cabang IS NULL";
        $rt_check = ($has_rt_col && $request_type)
            ? "AND (request_type = '$request_type' OR request_type IS NULL)"
            : '';
        $dup = mysqli_query($conn, "SELECT id FROM ppx_approval_routing 
                           WHERE divisi_id = $divisi_id 
                           AND initial_status = '$initial_status'
                           $cabang_check $rt_check");
        if ($dup && mysqli_num_rows($dup) > 0) {
            throw new Exception('Routing untuk divisi, cabang, dan status ini sudah ada!');
        }
        
        // Build INSERT dinamis berdasarkan keberadaan kolom
        if ($has_rt_col) {
            $query = "INSERT INTO ppx_approval_routing 
                      (divisi_id, cabang, initial_status, final_status, approver_role, skip_leader, is_active, request_type)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssiis',
                $divisi_id, $cabang, $initial_status, $final_status, $approver_role,
                $skip_leader, $is_active, $request_type
            );
        } else {
            $query = "INSERT INTO ppx_approval_routing 
                      (divisi_id, cabang, initial_status, final_status, approver_role, skip_leader, is_active)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssii',
                $divisi_id, $cabang, $initial_status, $final_status, $approver_role,
                $skip_leader, $is_active
            );
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal menambahkan routing: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        $_SESSION['success'] = 'Routing berhasil ditambahkan!';
        
    } elseif ($action === 'edit') {
        $id            = (int)$_POST['id'];
        $divisi_id     = (int)$_POST['divisi_id'];
        $cabang        = !empty($_POST['cabang']) ? mysqli_real_escape_string($conn, trim($_POST['cabang'])) : null;
        $initial_status = mysqli_real_escape_string($conn, $_POST['initial_status']);
        $final_status   = !empty($_POST['final_status']) ? mysqli_real_escape_string($conn, trim($_POST['final_status'])) : null;
        $approver_role = mysqli_real_escape_string($conn, $_POST['approver_role']);
        $skip_leader   = (int)$_POST['skip_leader'];
        $is_active     = (int)$_POST['is_active'];
        $request_type  = ($has_rt_col && isset($_POST['request_type']) && $_POST['request_type'] !== '')
                         ? mysqli_real_escape_string($conn, $_POST['request_type']) : null;

        // Validasi
        $chk = mysqli_query($conn, "SELECT id_divisi FROM divisi WHERE id_divisi = $divisi_id");
        if (!$chk || mysqli_num_rows($chk) === 0) throw new Exception('Divisi tidak valid!');

        $chk = mysqli_query($conn, "SELECT nama_role FROM roles WHERE nama_role = '$approver_role'");
        if (!$chk || mysqli_num_rows($chk) === 0) throw new Exception('Role tidak valid!');
        
        // Check duplicate
        $cabang_check = $cabang ? "AND cabang = '$cabang'" : "AND cabang IS NULL";
        $rt_check = ($has_rt_col && $request_type)
            ? "AND (request_type = '$request_type' OR request_type IS NULL)"
            : '';
        $dup = mysqli_query($conn, "SELECT id FROM ppx_approval_routing 
                           WHERE divisi_id = $divisi_id 
                           AND initial_status = '$initial_status'
                           $cabang_check $rt_check AND id != $id");
        if ($dup && mysqli_num_rows($dup) > 0) {
            throw new Exception('Routing untuk divisi, cabang, dan status ini sudah ada!');
        }
        
        // Build UPDATE dinamis
        if ($has_rt_col) {
            $query = "UPDATE ppx_approval_routing 
                      SET divisi_id = ?, cabang = ?, initial_status = ?, final_status = ?, approver_role = ?,
                          skip_leader = ?, is_active = ?, request_type = ?, updated_at = NOW()
                      WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssiiis',
                $divisi_id, $cabang, $initial_status, $final_status, $approver_role,
                $skip_leader, $is_active, $request_type, $id
            );
        } else {
            $query = "UPDATE ppx_approval_routing 
                      SET divisi_id = ?, cabang = ?, initial_status = ?, final_status = ?, approver_role = ?,
                          skip_leader = ?, is_active = ?, updated_at = NOW()
                      WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issssiis',
                $divisi_id, $cabang, $initial_status, $final_status, $approver_role,
                $skip_leader, $is_active, $id
            );
        }
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal mengupdate routing: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        $_SESSION['success'] = 'Routing berhasil diupdate!';
        
    } elseif ($action === 'delete') {
        $id = (int)$_GET['id'];
        
        $stmt = mysqli_prepare($conn, "DELETE FROM ppx_approval_routing WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Gagal menghapus routing: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);
        $_SESSION['success'] = 'Routing berhasil dihapus!';
        
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
