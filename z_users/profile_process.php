<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_users\profile_process.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'edit') {
        // Edit Profile
        $nama_user = mysqli_real_escape_string($conn, $_POST['nama_user']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp'] ?? '');

        // Validasi input
        if (empty($nama_user) || empty($username)) {
            $_SESSION['error'] = 'Nama lengkap dan username wajib diisi!';
            header("Location: profile_edit.php");
            exit();
        }

        // Cek username sudah ada atau belum (kecuali milik user ini)
        $check_username = "SELECT id_user FROM user_dfm WHERE username = '$username' AND id_user != '$user_id'";
        $result_check = mysqli_query($conn, $check_username);

        if (mysqli_num_rows($result_check) > 0) {
            $_SESSION['error'] = 'Username sudah digunakan oleh user lain!';
            header("Location: profile_edit.php");
            exit();
        }

        // Update profile
        $update_query = "UPDATE user_dfm SET 
                        nama_user = '$nama_user',
                        username = '$username',
                        email = '$email',
                        no_hp = '$no_hp',
                        updated_at = NOW()
                        WHERE id_user = '$user_id'";

        if (mysqli_query($conn, $update_query)) {
            // Update session nama_user
            $_SESSION['nama_user'] = $nama_user;
            $_SESSION['success'] = 'Profile berhasil diperbarui!';
        } else {
            $_SESSION['error'] = 'Gagal memperbarui profile: ' . mysqli_error($conn);
        }

        header("Location: profile.php");
        exit();

    } elseif ($action == 'change_password') {
        // Change Password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validasi input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = 'Semua field password wajib diisi!';
            header("Location: profile_edit.php");
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'Password baru dan konfirmasi password tidak cocok!';
            header("Location: profile_edit.php");
            exit();
        }

        if (strlen($new_password) < 6) {
            $_SESSION['error'] = 'Password baru minimal 6 karakter!';
            header("Location: profile_edit.php");
            exit();
        }

        // Ambil password lama dari database
        $get_user = "SELECT password FROM user_dfm WHERE id_user = '$user_id'";
        $result_user = mysqli_query($conn, $get_user);
        $user_data = mysqli_fetch_assoc($result_user);

        // Verifikasi password lama
        if (!password_verify($current_password, $user_data['password'])) {
            $_SESSION['error'] = 'Password lama tidak sesuai!';
            header("Location: profile_edit.php");
            exit();
        }

        // Hash password baru
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password
        $update_password = "UPDATE user_dfm SET 
                           password = '$hashed_password',
                           updated_at = NOW()
                           WHERE id_user = '$user_id'";

        if (mysqli_query($conn, $update_password)) {
            $_SESSION['success'] = 'Password berhasil diubah!';
        } else {
            $_SESSION['error'] = 'Gagal mengubah password: ' . mysqli_error($conn);
        }

        header("Location: profile.php");
        exit();

    } elseif ($action == 'delete') {
        // Delete Account (Soft Delete)
        $update_query = "UPDATE user_dfm SET 
                        is_active = 0,
                        updated_at = NOW()
                        WHERE id_user = '$user_id'";

        if (mysqli_query($conn, $update_query)) {
            // Logout user
            session_destroy();
            header("Location: ../login.php?message=account_deleted");
        } else {
            $_SESSION['error'] = 'Gagal menghapus akun: ' . mysqli_error($conn);
            header("Location: profile.php");
        }
        exit();
    }
}

// Jika tidak ada action yang valid
$_SESSION['error'] = 'Aksi tidak valid!';
header("Location: profile.php");
exit();
?>