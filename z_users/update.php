<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - hanya super admin yang bisa update user
if (!$auth->isSuperAdmin()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah data user!';
    header("Location: ../index.php");
    exit();
}

// Pengecekan action
if (!isset($_POST['action']) || $_POST['action'] !== 'edit_user') {
    $_SESSION['error'] = 'Invalid request!';
    header("Location: dashboard.php");
    exit();
}

// Validasi input
if (empty($_POST['id_user']) || empty($_POST['nama_user']) || empty($_POST['username']) || 
    empty($_POST['divisi']) || empty($_POST['id_role']) || empty($_POST['departemen'])) {
    $_SESSION['error'] = 'Semua field wajib diisi!';
    header("Location: edit.php?id=" . $_POST['id_user']);
    exit();
}

// Sanitasi input
$id_user = mysqli_real_escape_string($conn, trim($_POST['id_user']));
$nama_user = mysqli_real_escape_string($conn, trim($_POST['nama_user']));
$username = mysqli_real_escape_string($conn, trim($_POST['username']));
$divisi = mysqli_real_escape_string($conn, trim($_POST['divisi']));
$id_role = mysqli_real_escape_string($conn, trim($_POST['id_role']));
$departemen = mysqli_real_escape_string($conn, trim($_POST['departemen']));
$jabatan = mysqli_real_escape_string($conn, trim($_POST['jabatan']));
$id_cabang = isset($_POST['id_cabang']) ? mysqli_real_escape_string($conn, trim($_POST['id_cabang'])) : '';
$reset_password = isset($_POST['reset_password']) ? mysqli_real_escape_string($conn, trim($_POST['reset_password'])) : '';

// Cek username apakah sudah digunakan user lain
$check_username = "SELECT id_user FROM user_dfm WHERE username = '$username' AND id_user != '$id_user'";
$result_username = mysqli_query($conn, $check_username);

if (mysqli_num_rows($result_username) > 0) {
    $_SESSION['error'] = 'Username sudah digunakan! Pilih username lain.';
    header("Location: edit.php?id=" . $id_user);
    exit();
}

// Buat query update (tanpa updated_at dan status)
$update_query = "UPDATE user_dfm SET 
                nama_user = '$nama_user', 
                username = '$username', 
                divisi = '$divisi',
                departemen = '$departemen',
                jabatan = '$jabatan',
                id_cabang = '$id_cabang',
                id_role = '$id_role'";

// Tambahkan reset password jika diisi
if (!empty($reset_password)) {
    $update_query .= ", password = '$reset_password'";
}

// Selesaikan query
$update_query .= " WHERE id_user = '$id_user'";

// Jalankan query update
if (mysqli_query($conn, $update_query)) {
    $_SESSION['success'] = 'Data user berhasil diperbarui!';
    
    // Jika user yang diupdate adalah user yang sedang login, update session
    if ($id_user == $_SESSION['id_user']) {
        $_SESSION['nama_user'] = $nama_user;
    }
    
    header("Location: dashboard.php");
    exit();
} else {
    $_SESSION['error'] = 'Gagal memperbarui data user: ' . mysqli_error($conn);
    header("Location: edit.php?id=" . $id_user);
    exit();
}
?>