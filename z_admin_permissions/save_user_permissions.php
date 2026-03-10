<?php
// z_admin_permissions/save_user_permissions.php
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

$user_id = mysqli_real_escape_string($conn, $_POST['user_id'] ?? '');
if (!$user_id) {
    $_SESSION['error'] = 'User ID tidak valid.';
    header('Location: dashboard.php'); exit();
}

// Verifikasi user ada
$chk = mysqli_query($conn, "SELECT id_user, nama_user FROM user_dfm WHERE id_user = '$user_id'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $_SESSION['error'] = 'User tidak ditemukan.';
    header('Location: dashboard.php'); exit();
}
$user = mysqli_fetch_assoc($chk);

// Bangun JSON custom_permissions dari checkbox yang disubmit
$new_permissions = [];
$posted = $_POST['permissions'] ?? [];

foreach ($PERMISSION_MODULES as $mod_key => $mod) {
    if (isset($posted[$mod_key]) && is_array($posted[$mod_key])) {
        // Hanya aksi yang valid untuk modul ini
        $valid = array_intersect($posted[$mod_key], $mod['actions']);
        if (!empty($valid)) {
            $new_permissions[$mod_key] = array_values($valid);
        }
    }
}

$perms_json = json_encode($new_permissions, JSON_UNESCAPED_UNICODE);

$stmt = mysqli_prepare($conn, "UPDATE user_dfm SET custom_permissions = ? WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "ss", $perms_json, $user_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = 'Permission untuk <strong>' . htmlspecialchars($user['nama_user']) .
        '</strong> berhasil disimpan.';
} else {
    $_SESSION['error'] = 'Gagal menyimpan: ' . mysqli_error($conn);
}
mysqli_stmt_close($stmt);

header('Location: dashboard.php'); exit();
