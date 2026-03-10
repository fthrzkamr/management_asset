<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_users\dashboard.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - User Management
$auth->requirePermission('user_management', 'read');

// Handle delete user
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // Cek jangan hapus diri sendiri
    if ($user_id == $_SESSION['id_user']) {
        $_SESSION['error'] = 'Anda tidak dapat menghapus akun sendiri!';
    } else {
        $delete_query = "DELETE FROM user_dfm WHERE id_user = $user_id";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = 'User berhasil dihapus!';
        } else {
            $_SESSION['error'] = 'Gagal menghapus user: ' . mysqli_error($conn);
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Get users data dengan role DAN DIVISI (ditambahkan JOIN dengan tabel divisi)
$users_query = "SELECT u.*, r.nama_role, d.nama_divisi , dep.nama_departemen
                FROM user_dfm u 
                LEFT JOIN roles r ON u.id_role = r.id_role 
                LEFT JOIN divisi d ON u.divisi = d.id_divisi 
                LEFT JOIN departemen dep ON u.departemen = dep.id_departemen
                ORDER BY u.tgl_user DESC";
$users_result = mysqli_query($conn, $users_query);

// Get statistics
$total_users = mysqli_num_rows($users_result);
$super_admin_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM user_dfm u JOIN roles r ON u.id_role = r.id_role WHERE r.nama_role = 'super_admin'"));
$staff_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM user_dfm u JOIN roles r ON u.id_role = r.id_role WHERE r.nama_role = 'hr'"));
$karyawan_count = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM user_dfm u JOIN roles r ON u.id_role = r.id_role WHERE r.nama_role = 'koordinator'"));

// Reset pointer
mysqli_data_seek($users_result, 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Pengguna - Dua Farma Mahakarsa</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
</head>

<body>
    <div class="main-wrapper">

        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">

                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">
                                <i class="fas fa-users text-primary"></i>
                                Manajemen Pengguna
                            </h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item">
                                    <a href="../index.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Manajemen Pengguna</li>
                            </ul>
                            
                            <!-- Button Group - Berjejer Horizontal -->
                            <div class="d-flex align-items-center" style="gap: 10px;">
                                <!-- Button Tambah Pengguna -->
                                <a href="add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus fa-sm"></i> Tambah Pengguna
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Page Header -->

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle fa-sm"></i>
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle fa-sm"></i>
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <!-- <div class="row mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Pengguna
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($total_users) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="card border-left-danger">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Super Admin
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($super_admin_count) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="card border-left-warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Staff
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($staff_count) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="card border-left-info">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Karyawan
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= number_format($karyawan_count) ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Users Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-table fa-sm"></i>
                                    Daftar Pengguna
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="usersTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nama</th>
                                                <th>Username</th>
                                                <th>Role</th>
                                                <th>Divisi</th>
                                                <th>Departemen</th>
                                                <th>Jabatan</th>
                                                <th>Dibuat</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                                <tr>
                                                    <td><small class="font-weight-bold"><?= $user['id_user'] ?></small></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar avatar-xs mr-2">
                                                                <i class="fas fa-user-circle text-muted"></i>
                                                            </div>
                                                            <small class="font-weight-bold"><?= htmlspecialchars($user['nama_user']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td><small><?= htmlspecialchars($user['username']) ?></small></td>
                                                    <td>
                                                        <?php
                                                        $role_name = $user['nama_role'] ?? 'Unknown';
                                                        ?>
                                                        <span class="badge badge-danger badge-sm">
                                                            <?= ucfirst(str_replace('_', ' ', $role_name)) ?>
                                                        </span>
                                                    </td>
                                                    <!-- Kolom Divisi -->
                                                    <td>
                                                        <?php if (!empty($user['nama_divisi'])): ?>
                                                            <span class="badge badge-secondary badge-sm">
                                                                <?= htmlspecialchars($user['nama_divisi']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-light badge-sm">
                                                                Tidak ada divisi
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span><?= htmlspecialchars($user['nama_departemen']) ?></span>
                                                    </td>
                                                    <!-- Kolom Jabatan -->
                                                    <td>
                                                        <?php if (!empty($user['jabatan'])): ?>
                                                            <span class="badge badge-info badge-sm">
                                                                <?= htmlspecialchars($user['jabatan']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-light badge-sm">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= isset($user['tgl_user']) ? date('d/m/Y', strtotime($user['tgl_user'])) : '-' ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <!-- View -->
                                                            <button type="button" class="btn btn-outline-info btn-action"
                                                                onclick="viewUser(<?= $user['id_user'] ?>)" title="Detail">
                                                                <i class="fas fa-eye fa-xs"></i>
                                                            </button>

                                                            <!-- Edit -->
                                                            <a href="edit.php?id=<?= $user['id_user'] ?>"
                                                                class="btn btn-outline-warning btn-action" title="Edit">
                                                                <i class="fas fa-edit fa-xs"></i>
                                                            </a>

                                                            <!-- Delete - Tidak bisa hapus diri sendiri -->
                                                            <?php if ($user['id_user'] != $_SESSION['id_user']): ?>
                                                                <button type="button" class="btn btn-outline-danger btn-action"
                                                                    onclick="deleteUser(<?= $user['id_user'] ?>, '<?= htmlspecialchars($user['nama_user']) ?>')" title="Hapus">
                                                                    <i class="fas fa-trash fa-xs"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('../layouts/footer.php'); ?>

            </div>
        </div>
    </div>

    <!-- Modal View User -->
    <div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">
                        <i class="fas fa-user fa-sm text-info"></i> Detail Pengguna
                    </h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="viewContent">
                    <!-- Content akan dimuat via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times fa-xs"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Scripts -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            // DataTable initialization
            $('#usersTable').DataTable({
                "order": [[0, "asc"]], // Kolom 0 (ID) urut DESC
                "pageLength": 25,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        });

        function viewUser(id) {
            $.ajax({
                url: 'view.php',
                type: 'GET',
                data: {
                    id: id
                },
                success: function(response) {
                    $('#viewContent').html(response);
                    $('#viewModal').modal('show');
                },
                error: function() {
                    alert('Gagal memuat detail pengguna');
                }
            });
        }

        function deleteUser(id, nama) {
            showConfirm(
                'Apakah Anda yakin ingin menghapus pengguna "' + nama + '"?<br><br><strong>Tindakan ini tidak dapat dibatalkan!</strong>',
                function() {
                    window.location.href = 'dashboard.php?delete=1&id=' + id;
                },
                'delete'
            );
        }
    </script>

</body>

</html>