<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_users\profile_edit.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id_user'];

// Ambil data user
$query = "SELECT * FROM user_dfm WHERE id_user = '$user_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'User tidak ditemukan!';
    header("Location: profile.php");
    exit();
}

$user = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Profile - DFM Asset</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">
                                <i class="fas fa-edit text-primary"></i> Edit Profile
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="profile.php">Profile</a>
                                </li>
                                <li class="breadcrumb-item active">Edit</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="profile.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error'] ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Edit Form -->
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user"></i> Edit Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="profile_process.php" method="POST" id="editProfileForm">
                                    <input type="hidden" name="action" value="edit">
                                    
                                    <div class="row">
                                        <!-- Nama User -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="nama_user">
                                                    Nama Lengkap <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="nama_user" name="nama_user" 
                                                       value="<?= htmlspecialchars($user['nama_user']) ?>" 
                                                       placeholder="Masukkan nama lengkap" required>
                                            </div>
                                        </div>

                                        <!-- Username -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="username">
                                                    Username <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="username" name="username" 
                                                       value="<?= htmlspecialchars($user['username']) ?>" 
                                                       placeholder="Masukkan username" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Email -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                                       placeholder="Masukkan email">
                                            </div>
                                        </div>

                                        <!-- No HP -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="no_hp">No. HP</label>
                                                <input type="text" class="form-control" id="no_hp" name="no_hp" 
                                                       value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" 
                                                       placeholder="Masukkan nomor HP">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Role (Read Only) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Role</label>
                                                <input type="text" class="form-control" 
                                                       value="<?= htmlspecialchars($user['role']) ?>" readonly>
                                                <small class="text-muted">Role tidak dapat diubah</small>
                                            </div>
                                        </div>

                                        <!-- Status (Read Only) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Status</label>
                                                <input type="text" class="form-control" 
                                                       value="<?= $user['is_active'] == 1 ? 'Aktif' : 'Tidak Aktif' ?>" readonly>
                                                <small class="text-muted">Status tidak dapat diubah</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submit Buttons -->
                                    <div class="text-right">
                                        <a href="profile.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i class="fas fa-save"></i> Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password Section -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-key"></i> Ubah Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="profile_process.php" method="POST" id="changePasswordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="current_password">
                                                    Password Lama <span class="text-danger">*</span>
                                                </label>
                                                <input type="password" class="form-control" id="current_password" 
                                                       name="current_password" placeholder="Password lama" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="new_password">
                                                    Password Baru <span class="text-danger">*</span>
                                                </label>
                                                <input type="password" class="form-control" id="new_password" 
                                                       name="new_password" placeholder="Password baru" 
                                                       minlength="6" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="confirm_password">
                                                    Konfirmasi Password <span class="text-danger">*</span>
                                                </label>
                                                <input type="password" class="form-control" id="confirm_password" 
                                                       name="confirm_password" placeholder="Konfirmasi password" 
                                                       minlength="6" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key"></i> Ubah Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include('../layouts/footer.php'); ?>

    <script src="../assets/plugins/jquery/jquery.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Validasi password confirmation
            $('#changePasswordForm').on('submit', function(e) {
                var newPassword = $('#new_password').val();
                var confirmPassword = $('#confirm_password').val();

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Password baru dan konfirmasi password tidak cocok!');
                    $('#confirm_password').focus();
                    return false;
                }

                // Disable submit button
                $(this).find('button[type="submit"]').prop('disabled', true).html(
                    '<i class="fas fa-spinner fa-spin"></i> Mengubah Password...'
                );
            });

            // Disable submit button on profile edit
            $('#editProfileForm').on('submit', function() {
                $('#submitBtn').prop('disabled', true).html(
                    '<i class="fas fa-spinner fa-spin"></i> Menyimpan...'
                );
            });
        });
    </script>
</body>
</html>