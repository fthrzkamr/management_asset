<?php
// z_admin_permissions/delete_role.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}

$role_id = intval($_GET['id'] ?? 0);
if (!$role_id) { header('Location: dashboard.php'); exit(); }

// Ambil data role
$res = mysqli_query($conn, "SELECT * FROM roles WHERE id_role = $role_id");
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = 'Role tidak ditemukan.';
    header('Location: dashboard.php'); exit();
}
$role = mysqli_fetch_assoc($res);

// Tidak bisa hapus super_admin
if ($role['nama_role'] === 'super_admin') {
    $_SESSION['error'] = 'Role "super_admin" tidak dapat dihapus.';
    header('Location: dashboard.php'); exit();
}

// Cek apakah role masih dipakai user
$used = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as cnt FROM user_dfm WHERE id_role = $role_id"));
if ($used['cnt'] > 0) {
    $_SESSION['error'] = 'Role "' . htmlspecialchars($role['nama_role']) . '" masih digunakan oleh ' .
        $used['cnt'] . ' user. Pindahkan user tersebut terlebih dahulu.';
    header('Location: dashboard.php'); exit();
}

// Hapus role
if (mysqli_query($conn, "DELETE FROM roles WHERE id_role = $role_id")) {
    $_SESSION['success'] = 'Role "' . htmlspecialchars($role['nama_role']) . '" berhasil dihapus.';
} else {
    $_SESSION['error'] = 'Gagal menghapus: ' . mysqli_error($conn);
}

header('Location: dashboard.php'); exit();
