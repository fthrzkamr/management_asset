<?php
// z_admin_permissions/save_role.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
include('permissions_config.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); exit();
}

$role_id  = intval($_POST['role_id'] ?? 0);
$deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));

if (!$role_id) {
    $_SESSION['error'] = 'Role ID tidak valid.';
    header('Location: dashboard.php'); exit();
}

// Verifikasi role ada
$chk = mysqli_query($conn, "SELECT id_role, nama_role FROM roles WHERE id_role = $role_id");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $_SESSION['error'] = 'Role tidak ditemukan.';
    header('Location: dashboard.php'); exit();
}

$role = mysqli_fetch_assoc($chk);

// Bangun JSON permissions dari input form
// $_POST['permissions'] = [ 'module_key' => ['action1', 'action2', ...], ... ]
$new_permissions = [];

$posted = $_POST['permissions'] ?? [];

foreach ($PERMISSION_MODULES as $mod_key => $mod) {
    if (isset($posted[$mod_key]) && is_array($posted[$mod_key])) {
        // Filter hanya aksi yang memang tersedia untuk modul ini
        $valid_actions = array_intersect($posted[$mod_key], $mod['actions']);
        if (!empty($valid_actions)) {
            $new_permissions[$mod_key] = array_values($valid_actions);
        }
    }
    // Jika tidak ada yang dicentang, modul tersebut tidak disertakan (= no access)
}

$permissions_json = json_encode($new_permissions, JSON_UNESCAPED_UNICODE);

$stmt = mysqli_prepare($conn,
    "UPDATE roles SET permissions = ?, deskripsi = ? WHERE id_role = ?");
mysqli_stmt_bind_param($stmt, "ssi", $permissions_json, $deskripsi, $role_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = 'Permission untuk role "' .
        ucfirst(str_replace('_', ' ', $role['nama_role'])) . '" berhasil disimpan.';
} else {
    $_SESSION['error'] = 'Gagal menyimpan: ' . mysqli_error($conn);
}
mysqli_stmt_close($stmt);

header('Location: dashboard.php'); exit();
