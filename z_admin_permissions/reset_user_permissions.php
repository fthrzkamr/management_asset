<?php
// z_admin_permissions/reset_user_permissions.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}

$user_id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
if (!$user_id) { header('Location: dashboard.php'); exit(); }

$chk = mysqli_query($conn, "SELECT id_user, nama_user FROM user_dfm WHERE id_user = '$user_id'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $_SESSION['error'] = 'User tidak ditemukan.';
    header('Location: dashboard.php'); exit();
}
$user = mysqli_fetch_assoc($chk);

// Hapus custom permission (set NULL = ikut role lagi)
if (mysqli_query($conn, "UPDATE user_dfm SET custom_permissions = NULL WHERE id_user = '$user_id'")) {
    $_SESSION['success'] = 'Permission <strong>' . htmlspecialchars($user['nama_user']) .
        '</strong> berhasil direset ke default role.';
} else {
    $_SESSION['error'] = 'Gagal reset: ' . mysqli_error($conn);
}

header('Location: dashboard.php'); exit();
