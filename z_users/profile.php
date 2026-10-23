<?php
// filepath: c:\xampp\htdocs\asset_dfm\profile.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php'); // TAMBAHKAN INI

// Cek session login
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

// TAMBAHKAN: Instance auth helper
$auth = new AuthHelper($conn);

$user_id = $_SESSION['id_user'];

// Ambil data user dari database
$query = mysqli_query($conn, "SELECT * FROM user_dfm WHERE id_user = '$user_id' LIMIT 1");
if (!$query || mysqli_num_rows($query) == 0) {
    echo "<script>alert('User tidak ditemukan!'); window.location.href='logout.php';</script>";
    exit;
}

$user = mysqli_fetch_assoc($query);

// Hitung jumlah asset yang dibuat user ini
$asset_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_dfm WHERE user_info = '$user_id'");
$total_assets = mysqli_fetch_assoc($asset_count)['total'];

// Asset terbaru yang dibuat user
$recent_assets = mysqli_query($conn, "
    SELECT kode_aset, nama_aset, created_at 
    FROM asset_dfm 
    WHERE user_info = '$user_id' 
    ORDER BY created_at DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Asset Management</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- PERBAIKAN: Custom CSS untuk mobile optimization -->
    <style>
        @media (max-width: 767.98px) {
            .page-header h3 {
                font-size: 1.5rem;
            }

            .avatar-xxl {
                width: 80px !important;
                height: 80px !important;
            }

            .card-body {
                padding: 1rem 0.75rem;
            }

            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }

            /* PERBAIKAN: Tambah margin bottom untuk avatar di mobile */
            .avatar.avatar-xxl {
                margin-bottom: 1.5rem !important;
            }
        }

        @media (max-width: 575.98px) {
            .breadcrumb {
                font-size: 0.85rem;
            }

            .form-control-plaintext {
                font-size: 0.9rem;
            }

            /* PERBAIKAN: Margin bottom lebih besar untuk mobile kecil */
            .avatar.avatar-xxl {
                margin-bottom: 2rem !important;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">
                <div class="page-header">
                    <div class="row">
                        <div class="col-12">
                            <h3 class="page-title mb-2 mb-md-3">User Profile</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Profile</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Profile Card - Mobile First Layout -->
                    <div class="col-12 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <!-- PERBAIKAN: Avatar dengan margin bottom responsif -->
                                <div class="avatar avatar-xxl mb-3 d-inline-block">
                                    <img src="<?= BASE_URL ?>/assets/img/profiles/default-avatar.svg"
                                        alt="User Avatar"
                                        class="avatar-img rounded-circle"
                                        style="width: 100px; height: 100px; object-fit: cover;">
                                </div>
                                <!-- PERBAIKAN: Nama profile dengan spacing yang lebih baik -->
                                <h4 class="mb-3 h5 h4-md"><?= htmlspecialchars($user['nama_user']) ?></h4>

                                <!-- PERBAIKAN: Responsive stats -->
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border-right">
                                            <h5 class="mb-0 text-primary h6 h5-md"><?= $total_assets ?></h5>
                                            <small class="text-muted">Total Assets</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="mb-0 text-success h6 h5-md">Active</h5>
                                        <small class="text-muted">Status</small>
                                    </div>
                                </div>

                                <!-- PERBAIKAN: Responsive buttons -->
                                <div class="mt-3">
                                    <div class="d-flex flex-column d-sm-block">
                                        <button class="btn btn-primary btn-sm mb-2 mb-sm-0 mr-sm-2"
                                            data-toggle="modal" data-target="#editProfileModal" disabled>
                                            <i class="fas fa-edit"></i>
                                            <span class="d-none d-sm-inline">Edit Profile</span>
                                            <span class="d-inline d-sm-none">Edit</span>
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm"
                                            data-toggle="modal" data-target="#changePasswordModal" disabled>
                                            <i class="fas fa-key"></i>
                                            <span class="d-none d-sm-inline">Change Password</span>
                                            <span class="d-inline d-sm-none">Password</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Details - Responsive Layout -->
                    <div class="col-12 col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0 h6 h5-md">
                                    <i class="fas fa-user"></i> Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- PERBAIKAN: Responsive grid -->
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="form-group mb-2">
                                            <label class="font-weight-bold small">Full Name</label>
                                            <p class="form-control-plaintext mb-0"><?= htmlspecialchars($user['nama_user']) ?></p>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="form-group mb-2">
                                            <label class="font-weight-bold small">Username</label>
                                            <p class="form-control-plaintext mb-0"><?= htmlspecialchars($user['username']) ?></p>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="form-group mb-2">
                                            <label class="font-weight-bold small">Role</label>
                                            <p class="form-control-plaintext mb-0">
                                                <span class="badge badge-success">Administrator</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="form-group mb-2">
                                            <label class="font-weight-bold small">Member Since</label>
                                            <p class="form-control-plaintext mb-0">
                                                <?= date('d M Y', strtotime($user['created_at'] ?? date('Y-m-d'))) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3 mb-md-0">
                                        <div class="form-group mb-2">
                                            <label class="font-weight-bold small">Last Login</label>
                                            <p class="form-control-plaintext mb-0">
                                                <i class="fas fa-clock text-muted"></i>
                                                <span class="d-none d-sm-inline"><?= date('d M Y, H:i') ?></span>
                                                <span class="d-inline d-sm-none"><?= date('d/m H:i') ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Assets - Responsive -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0 h6 h5-md">
                                    <i class="fas fa-history"></i> Recent Assets Created
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($recent_assets) > 0): ?>
                                    <!-- PERBAIKAN: Responsive table -->
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="border-0">Kode Asset</th>
                                                    <th class="border-0">Nama Asset</th>
                                                    <th class="border-0 d-none d-md-table-cell">Created</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($asset = mysqli_fetch_assoc($recent_assets)): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge badge-info"><?= htmlspecialchars($asset['kode_aset']) ?></span>
                                                        </td>
                                                        <td>
                                                            <div><?= htmlspecialchars($asset['nama_aset']) ?></div>
                                                            <!-- PERBAIKAN: Show date on mobile -->
                                                            <small class="text-muted d-block d-md-none">
                                                                <?= date('d M Y, H:i', strtotime($asset['created_at'])) ?>
                                                            </small>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <small class="text-muted">
                                                                <?= date('d M Y, H:i', strtotime($asset['created_at'])) ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                                            <span class="d-none d-sm-inline">View All Assets</span>
                                            <span class="d-inline d-sm-none">View All</span>
                                            <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-box-open fa-2x fa-3x-md text-muted mb-3"></i>
                                        <p class="text-muted">Belum ada asset yang dibuat</p>

                                        <?php if (!$auth->isKoordinator()): ?>
                                            <a href="aset_add.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i>
                                                <span class="d-none d-sm-inline">Tambah Asset Pertama</span>
                                                <span class="d-inline d-sm-none">Tambah Asset</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- PERBAIKAN: Responsive Modal - Edit Profile -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form action="profile_update.php" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="nama_user" class="form-control"
                                value="<?= htmlspecialchars($user['nama_user']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control"
                                value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PERBAIKAN: Responsive Modal - Change Password -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form action="profile_update.php" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control"
                                minlength="6" required>
                        </div>
                        <div class="form-group"></div>
                        <label>Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control"
                            minlength="6" required>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
            </form>
        </div>
    </div>
    </div>


    <script>
        // Password confirmation validation
        $('form').on('submit', function(e) {
            const newPassword = $('input[name="new_password"]').val();
            const confirmPassword = $('input[name="confirm_password"]').val();

            if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Password konfirmasi tidak cocok!');
                return false;
            }
        });
    </script>
</body>

</html>