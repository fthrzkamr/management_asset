<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Tidak terautentikasi']);
    exit();
}


$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $auth->requirePermission('budget_routing', 'create');
} elseif ($action === 'edit' || $action === 'save_cabang' || $action === 'save_divisi') {
    $auth->requirePermission('budget_routing', 'update');
} elseif ($action === 'delete') {
    $auth->requirePermission('budget_routing', 'delete');
}

try {
    mysqli_begin_transaction($conn);

    // ─── Global Step: Add ────────────────────────────────────────────
    if ($action === 'add') {
        $step_name     = trim($_POST['step_name']);
        $approver_role = trim($_POST['approver_role']);
        $approver_id   = !empty($_POST['approver_id']) ? (int)$_POST['approver_id'] : null;
        $step_order    = (int)$_POST['step_order'];
        $is_optional   = isset($_POST['is_optional']) ? 1 : 0;
        $is_final      = isset($_POST['is_final']) ? 1 : 0;
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if (empty($step_name) || empty($approver_role)) {
            throw new Exception("Nama step dan role penyetujui harus diisi!");
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO ppx_budget_routing 
            (step_name, approver_role, approver_id, step_order, is_optional, is_final, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssiiiiii', $step_name, $approver_role, $approver_id, $step_order, $is_optional, $is_final, $is_active);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
        mysqli_commit($conn);
        $_SESSION['success'] = "Step routing baru berhasil ditambahkan.";

    // ─── Global Step: Edit ───────────────────────────────────────────
    } elseif ($action === 'edit') {
        $id            = (int)$_POST['id'];
        $step_name     = trim($_POST['step_name']);
        $approver_role = trim($_POST['approver_role']);
        $approver_id   = !empty($_POST['approver_id']) ? (int)$_POST['approver_id'] : null;
        $step_order    = (int)$_POST['step_order'];
        $is_optional   = isset($_POST['is_optional']) ? 1 : 0;
        $is_final      = isset($_POST['is_final']) ? 1 : 0;
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        $stmt = mysqli_prepare($conn, "UPDATE ppx_budget_routing SET
            step_name=?, approver_role=?, approver_id=?, step_order=?,
            is_optional=?, is_final=?, is_active=?
            WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssiiiiii', $step_name, $approver_role, $approver_id, $step_order, $is_optional, $is_final, $is_active, $id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_error($conn));
        mysqli_commit($conn);
        $_SESSION['success'] = "Step routing berhasil diperbarui.";

    // ─── Global Step: Delete ─────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id = (int)$_GET['id'];
        // Hapus juga overrides yang terkait
        $s1 = mysqli_prepare($conn, "DELETE FROM ppx_budget_routing_cabang WHERE id_routing_step = ?");
        mysqli_stmt_bind_param($s1, 'i', $id);
        mysqli_stmt_execute($s1);

        $s2 = mysqli_prepare($conn, "DELETE FROM ppx_budget_routing WHERE id = ?");
        mysqli_stmt_bind_param($s2, 'i', $id);
        if (!mysqli_stmt_execute($s2)) throw new Exception(mysqli_error($conn));
        mysqli_commit($conn);
        $_SESSION['success'] = "Step routing berhasil dihapus.";

    // ─── Per-Cabang: Save ─────────────────────────────────────────────
    } elseif ($action === 'save_cabang') {
        $id_cabang = trim($_POST['id_cabang'] ?? '');
        if (empty($id_cabang)) {
            $rcv = $_POST['id_cabang'] ?? 'tidak ada';
            throw new Exception("Cabang tidak valid! Diterima: [{$rcv}]");
        }

        $step_approvers = $_POST['step_approver'] ?? [];
        $step_skips     = $_POST['step_skip'] ?? [];

        // 1) Hapus config lama untuk cabang ini
        $del = mysqli_prepare($conn, "DELETE FROM ppx_budget_routing_cabang WHERE id_cabang = ?");
        mysqli_stmt_bind_param($del, 's', $id_cabang);
        if (!mysqli_stmt_execute($del)) throw new Exception("Delete gagal: " . mysqli_error($conn));

        // 2) Insert konfigurasi baru
        $steps_result = mysqli_query($conn, "SELECT id FROM ppx_budget_routing WHERE is_active = 1 ORDER BY step_order");
        if (!$steps_result) throw new Exception("Query steps gagal: " . mysqli_error($conn));

        while ($step_row = mysqli_fetch_assoc($steps_result)) {
            $sid         = (int)$step_row['id'];
            $approver_id = !empty($step_approvers[$sid]) ? (int)$step_approvers[$sid] : null;
            $skip        = !empty($step_skips[$sid]) ? 1 : 0;

            // Hanya simpan jika ada konfigurasi (ada approver atau skip=1)
            if ($approver_id !== null || $skip) {
                $approver_sql     = ($approver_id !== null) ? intval($approver_id) : 'NULL';
                $id_cabang_safe   = mysqli_real_escape_string($conn, $id_cabang);
                $sql_ins = "INSERT INTO ppx_budget_routing_cabang 
                    (id_cabang, id_routing_step, approver_id, skip_step, is_active)
                    VALUES ('{$id_cabang_safe}', {$sid}, {$approver_sql}, {$skip}, 1)";
                if (!mysqli_query($conn, $sql_ins)) {
                    throw new Exception("Insert gagal (step {$sid}): " . mysqli_error($conn));
                }
            }
        }

        mysqli_commit($conn);

        // Return JSON (dipanggil via AJAX fetch)
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'msg' => 'Konfigurasi berhasil disimpan.']);
        exit();

    // ─── Per-Divisi: Save ─────────────────────────────────────────────
    } elseif ($action === 'save_divisi') {
        $id_divisi = (int)($_POST['id_divisi'] ?? 0);
        if ($id_divisi <= 0) {
            throw new Exception("ID Divisi tidak valid! Diterima: [{$_POST['id_divisi']}]");
        }

        // id_cabang: NULL = berlaku semua cabang, value = hanya cabang tertentu
        $id_cabang_raw = trim($_POST['id_cabang'] ?? '');
        $id_cabang_val = !empty($id_cabang_raw) ? $id_cabang_raw : null;

        $step_states = $_POST['step_state'] ?? []; // [id_step => 'none'|'wajib'|'skip']

        // 1) Hapus config lama untuk divisi + cabang ini saja (bukan semua aturan divisi)
        if ($id_cabang_val !== null) {
            $id_cabang_safe = mysqli_real_escape_string($conn, $id_cabang_val);
            $del = mysqli_query($conn, "DELETE FROM ppx_budget_routing_divisi 
                WHERE id_divisi = {$id_divisi} AND id_cabang = '{$id_cabang_safe}'");
        } else {
            // Hapus aturan global (id_cabang IS NULL) untuk divisi ini
            $del = mysqli_query($conn, "DELETE FROM ppx_budget_routing_divisi 
                WHERE id_divisi = {$id_divisi} AND id_cabang IS NULL");
        }
        if (!$del) throw new Exception("Delete gagal: " . mysqli_error($conn));

        // 2) Insert konfigurasi baru (hanya yang bukan 'none')
        foreach ($step_states as $sid => $state) {
            $sid   = (int)$sid;
            $state = trim($state);
            if ($state === 'none') continue; // tidak ada aturan, skip

            $skip_step = ($state === 'skip') ? 1 : 0; // 'wajib' → skip_step=0
            $cabang_sql = ($id_cabang_val !== null)
                ? "'" . mysqli_real_escape_string($conn, $id_cabang_val) . "'"
                : "NULL";

            $sql_ins = "INSERT INTO ppx_budget_routing_divisi
                (id_divisi, id_cabang, id_routing_step, skip_step, is_active)
                VALUES ({$id_divisi}, {$cabang_sql}, {$sid}, {$skip_step}, 1)
                ON DUPLICATE KEY UPDATE skip_step = {$skip_step}, is_active = 1";
            if (!mysqli_query($conn, $sql_ins)) {
                throw new Exception("Insert gagal (step {$sid}): " . mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'msg' => 'Konfigurasi divisi berhasil disimpan.']);
        exit();


    } else {
        throw new Exception("Aksi tidak valid.");
    }

} catch (Exception $e) {
    mysqli_rollback($conn);

    // Jika aksi AJAX, return JSON error bukan redirect
    if ($action === 'save_cabang' || $action === 'save_divisi') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        exit();
    }

    $_SESSION['error'] = $e->getMessage();
}

header("Location: dashboard.php");
exit();
?>
