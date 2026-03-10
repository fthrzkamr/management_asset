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

// Cek permission - hanya super admin yang bisa edit user lain
if (!$auth->isSuperAdmin()) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit user lain!';
    header("Location: ../index.php");
    exit();
}

// Ambil ID dari parameter URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'ID user tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$user_id = intval($_GET['id']);

// Ambil data user dengan JOIN
$query = "SELECT u.*, r.nama_role, d.nama_divisi, dep.nama_departemen, c.nama_cabang
          FROM user_dfm u 
          LEFT JOIN roles r ON u.id_role = r.id_role
          LEFT JOIN divisi d ON u.divisi = d.id_divisi
          LEFT JOIN departemen dep ON u.departemen = dep.id_departemen
          LEFT JOIN master_cabang c ON u.id_cabang = c.id_cabang
          WHERE u.id_user = '$user_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'User tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$user = mysqli_fetch_assoc($result);

// Ambil data divisi untuk dropdown
$query_divisi = "SELECT id_divisi, nama_divisi FROM divisi ORDER BY nama_divisi ASC";
$result_divisi = mysqli_query($conn, $query_divisi);
if (!$result_divisi) {
    die("Error query divisi: " . mysqli_error($conn));
}
$divisi_list = [];
while ($row = mysqli_fetch_assoc($result_divisi)) {
    $divisi_list[] = $row;
}

// Ambil data roles untuk dropdown (tidak read-only seperti di profile)
$query_roles = "SELECT id_role, nama_role FROM roles ORDER BY nama_role ASC";
$result_roles = mysqli_query($conn, $query_roles);
if (!$result_roles) {
    die("Error query roles: " . mysqli_error($conn));
}
$roles_list = [];
while ($row = mysqli_fetch_assoc($result_roles)) {
    $roles_list[] = $row;
}

// Ambil data departemen untuk dropdown
$query_departemen = "SELECT id_departemen, nama_departemen FROM departemen ORDER BY nama_departemen ASC";
$result_departemen = mysqli_query($conn, $query_departemen);
if (!$result_departemen) {
    die("Error query departemen: " . mysqli_error($conn));
}
$departemen_list = [];
while ($row = mysqli_fetch_assoc($result_departemen)) {
    $departemen_list[] = $row;
}

// Ambil data cabang untuk dropdown
$query_cabang = "SELECT id_cabang, nama_cabang FROM master_cabang ORDER BY nama_cabang ASC";
$result_cabang = mysqli_query($conn, $query_cabang);
if (!$result_cabang) {
    die("Error query cabang: " . mysqli_error($conn));
}
$cabang_list = [];
while ($row = mysqli_fetch_assoc($result_cabang)) {
    $cabang_list[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User - DFM Asset</title>
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
                                <i class="fas fa-edit text-primary"></i> Edit User
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Manajemen Pengguna</a>
                                </li>
                                <li class="breadcrumb-item active">Edit User</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
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
                                    <i class="fas fa-user-edit"></i> Edit User: <?= htmlspecialchars($user['nama_user']) ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="update.php" method="POST" id="editUserForm">
                                    <input type="hidden" name="id_user" value="<?= $user_id ?>">
                                    <input type="hidden" name="action" value="edit_user">

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
                                        <!-- Divisi (Dropdown) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="divisi">
                                                    Divisi <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control" id="divisi" name="divisi" required>
                                                    <option value="">-- Pilih Divisi --</option>
                                                    <?php foreach ($divisi_list as $div): ?>
                                                        <option value="<?= $div['id_divisi'] ?>" <?= ($user['divisi'] == $div['id_divisi']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($div['nama_divisi']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Role (Dropdown untuk admin) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id_role">
                                                    Role <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control" id="id_role" name="id_role" required>
                                                    <option value="">-- Pilih Role --</option>
                                                    <?php foreach ($roles_list as $role): ?>
                                                        <option value="<?= $role['id_role'] ?>" <?= ($user['id_role'] == $role['id_role']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($role['nama_role']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reset Password -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="reset_password">Reset Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="reset_password"
                                                        name="reset_password" placeholder="Kosongkan jika tidak ingin reset password">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            onclick="togglePassword('reset_password')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Kosongkan jika tidak ingin mereset password</small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="departemen">
                                                    Departemen <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control" id="departemen" name="departemen" required>
                                                    <option value="">-- Pilih Departemen --</option>
                                                    <?php foreach ($departemen_list as $dep): ?>
                                                        <option value="<?= $dep['id_departemen'] ?>" <?= ($user['departemen'] == $dep['id_departemen']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($dep['nama_departemen']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Cabang dan Jabatan -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id_cabang">Cabang</label>
                                                <select class="form-control" id="id_cabang" name="id_cabang">
                                                    <option value="">-- Pilih Cabang --</option>
                                                    <?php foreach ($cabang_list as $cab): ?>
                                                        <option value="<?= $cab['id_cabang'] ?>" <?= (isset($user['id_cabang']) && $user['id_cabang'] == $cab['id_cabang']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cab['nama_cabang']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="jabatan">Jabatan</label>
                                                <select class="form-control" id="jabatan" name="jabatan">
                                                    <option value="">-- Pilih Jabatan --</option>
                                                    <option value="Direktur" <?= ($user['jabatan'] == 'Direktur') ? 'selected' : '' ?>>Direktur</option>
                                                    <option value="HR" <?= ($user['jabatan'] == 'HR') ? 'selected' : '' ?>>HR</option>
                                                    <option value="Manager" <?= ($user['jabatan'] == 'Manager') ? 'selected' : '' ?>>Manager</option>
                                                    <option value="Supervisor" <?= ($user['jabatan'] == 'Supervisor') ? 'selected' : '' ?>>Supervisor</option>
                                                    <option value="Kepala Cabang" <?= ($user['jabatan'] == 'Kepala Cabang') ? 'selected' : '' ?>>Kepala Cabang</option>
                                                    <option value="Staff" <?= ($user['jabatan'] == 'Staff') ? 'selected' : '' ?>>Staff</option>
                                                    <option value="Admin" <?= ($user['jabatan'] == 'Admin') ? 'selected' : '' ?>>Admin</option>
                                                    <option value="Koordinator" <?= ($user['jabatan'] == 'Koordinator') ? 'selected' : '' ?>>Koordinator</option>
                                                    <option value="Team Leader" <?= ($user['jabatan'] == 'Team Leader') ? 'selected' : '' ?>>Team Leader</option>
                                                    <option value="Operasional" <?= ($user['jabatan'] == 'Operasional') ? 'selected' : '' ?>>Operasional</option>
                                                    <option value="Bisnis" <?= ($user['jabatan'] == 'Bisnis') ? 'selected' : '' ?>>Bisnis</option>
                                                    <option value="Manager Finance" <?= ($user['jabatan'] == 'Manager Finance') ? 'selected' : '' ?>>Manager Finance</option>
                                                    <option value="Finance" <?= ($user['jabatan'] == 'Finance') ? 'selected' : '' ?>>Finance</option>
                                                    <option value="General Affair" <?= ($user['jabatan'] == 'General Affair') ? 'selected' : '' ?>>General Affair</option>
                                                    <option value="Staff IT" <?= ($user['jabatan'] == 'Staff IT') ? 'selected' : '' ?>>Staff IT</option>
                                                    <option value="Asmen Finance" <?= ($user['jabatan'] == 'Asmen Finance') ? 'selected' : '' ?>>Asmen Finance</option>
                                                </select>
                                                <small class="text-muted">Jabatan user di perusahaan</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submit Buttons -->
                                    <div class="text-right">
                                        <a href="dashboard.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-primary" id="submitBtn">
                                            <i class="fas fa-save"></i> Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('../layouts/footer.php'); ?>
            </div>
        </div>
    </div>

    <script src="../assets/plugins/jquery/jquery.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
        function togglePassword(id) {
            var passwordInput = document.getElementById(id);
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
            } else {
                passwordInput.type = "password";
            }
        }

        $(document).ready(function() {
            // Status switch label update
            $('#status').change(function() {
                if ($(this).is(':checked')) {
                    $(this).next().text('Aktif');
                } else {
                    $(this).next().text('Tidak Aktif');
                }
            });

            // Disable submit button on form submit
            $('#editUserForm').on('submit', function() {
                $('#submitBtn').prop('disabled', true).html(
                    '<i class="fas fa-spinner fa-spin"></i> Menyimpan...'
                );
            });
        });
    </script>
</body>

</html>