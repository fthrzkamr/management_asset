<?php
// z_admin_permissions/dashboard.php — Per-User Permission Manager
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}

// Cek dan tambahkan kolom custom_permissions jika belum ada
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM user_dfm LIKE 'custom_permissions'");
if (mysqli_num_rows($col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE user_dfm ADD COLUMN custom_permissions LONGTEXT DEFAULT NULL");
}

// Ambil semua users dengan info role, divisi, departemen, dan custom perm
$users_result = mysqli_query($conn,
    "SELECT u.id_user, u.nama_user, u.username, u.id_role, u.jabatan,
            u.custom_permissions,
            r.nama_role,
            d.nama_divisi,
            dep.nama_departemen,
            mc.nama_cabang
     FROM user_dfm u
     LEFT JOIN roles r ON u.id_role = r.id_role
     LEFT JOIN divisi d ON u.divisi = d.id_divisi
     LEFT JOIN departemen dep ON u.departemen = dep.id_departemen
     LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
     ORDER BY u.nama_user ASC");

// Ambil semua roles beserta jumlah user
$roles_list_result = mysqli_query($conn,
    "SELECT r.*, COUNT(u.id_user) AS user_count
     FROM roles r
     LEFT JOIN user_dfm u ON u.id_role = r.id_role
     GROUP BY r.id_role
     ORDER BY r.id_role ASC");

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'roles') ? 'roles' : 'users';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator - Permission Manager</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        .user-row:hover { background: #f0f4ff !important; cursor: pointer; }
        .badge-custom { background: #6f42c1; }
        .badge-role-default { background: #6c757d; }
        .search-hint { font-size: 0.78rem; color: #888; }
        .perm-pill { font-size: 0.68rem; margin: 1px; padding: 2px 6px; border-radius: 20px; }
        .avatar-circle {
            width: 34px; height: 34px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
        }
        .has-custom { border-left: 3px solid #6f42c1 !important; }
        .custom-indicator { font-size: 0.7rem; }
        #searchBox { border-radius: 20px; padding-left: 38px; }
        .search-wrap { position: relative; }
        .search-wrap .fa-search { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #aaa; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-12">
                        <h3 class="page-title">
                            <i class="fas fa-user-shield text-danger"></i> Administrator
                            <small class="text-muted" style="font-size:0.9rem"> — Kelola Permission Per User</small>
                        </h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Administrator</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error'] ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Navigation Tabs: Per User vs Default Role -->
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'users' ? 'active font-weight-bold' : '' ?>"
                       href="?tab=users">
                        <i class="fas fa-user-cog mr-1"></i> Permission Per User
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active_tab === 'roles' ? 'active font-weight-bold' : '' ?>"
                       href="?tab=roles">
                        <i class="fas fa-shield-alt mr-1"></i> Kelola Role (Default Role)
                    </a>
                </li>
            </ul>

            <?php if ($active_tab === 'users'): ?>
            <!-- Info Box -->
            <div class="alert alert-info py-2 mb-3">
                <i class="fas fa-info-circle"></i>
                <strong>Cara pakai:</strong> Cari user di tabel, lalu klik <strong>Edit Permission</strong> untuk mengatur akses modul user tersebut secara individual.
                <span class="badge badge-custom ml-2"><i class="fas fa-user-cog"></i> Custom</span>
                = user sudah punya permission khusus (override role).
            </div>

            <!-- Search + Filter -->
            <div class="card shadow-sm mb-3">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="search-wrap">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchBox" class="form-control"
                                       placeholder="Cari nama, username, divisi, jabatan...">
                            </div>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0">
                            <select id="filterRole" class="form-control form-control-sm">
                                <option value="">— Semua Role —</option>
                                <?php
                                $roles_q = mysqli_query($conn, "SELECT DISTINCT nama_role FROM roles ORDER BY nama_role");
                                while ($r = mysqli_fetch_assoc($roles_q)):
                                ?>
                                    <option value="<?= htmlspecialchars($r['nama_role']) ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $r['nama_role']))) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0">
                            <select id="filterCustom" class="form-control form-control-sm">
                                <option value="">— Semua User —</option>
                                <option value="custom">Sudah Custom Permission</option>
                                <option value="default">Masih Pakai Role Default</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Table -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-users"></i> Daftar Pengguna
                        <span class="badge badge-secondary ml-1" id="total-count"></span>
                    </h6>
                    <small class="search-hint"><i class="fas fa-mouse-pointer"></i> Klik baris untuk edit permission</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Pengguna</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Divisi / Departemen</th>
                                    <th>Status Permission</th>
                                    <th style="width:120px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php $no = 1; while ($u = mysqli_fetch_assoc($users_result)): ?>
                                <?php
                                $has_custom = !empty($u['custom_permissions']) && $u['custom_permissions'] !== 'null' && $u['custom_permissions'] !== '{}';
                                $custom_perms = $has_custom ? (json_decode($u['custom_permissions'], true) ?: []) : [];
                                $initial = strtoupper(substr($u['nama_user'] ?? 'U', 0, 1));
                                $colors = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0','#4895ef','#2d6a4f'];
                                $color  = $colors[crc32($u['id_user']) % count($colors)];
                                $role_slug = strtolower($u['nama_role'] ?? '');
                                ?>
                                <tr class="user-row <?= $has_custom ? 'has-custom' : '' ?>"
                                    data-name="<?= htmlspecialchars(strtolower($u['nama_user'])) ?>"
                                    data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                                    data-divisi="<?= htmlspecialchars(strtolower($u['nama_divisi'] ?? '')) ?>"
                                    data-jabatan="<?= htmlspecialchars(strtolower($u['jabatan'] ?? '')) ?>"
                                    data-role="<?= htmlspecialchars($role_slug) ?>"
                                    data-custom="<?= $has_custom ? 'custom' : 'default' ?>"
                                    onclick="window.location='edit_user_permissions.php?id=<?= urlencode($u['id_user']) ?>'">
                                    <td><small class="text-muted"><?= $no++ ?></small></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle mr-2" style="background:<?= $color ?>;color:#fff">
                                                <?= $initial ?>
                                            </div>
                                            <div>
                                                <div class="font-weight-bold" style="font-size:0.85rem">
                                                    <?= htmlspecialchars($u['nama_user']) ?>
                                                </div>
                                                <?php if (!empty($u['jabatan'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($u['jabatan']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($u['username']) ?></small></td>
                                    <td>
                                        <span class="badge badge-<?= $role_slug === 'super_admin' ? 'danger' : 'primary' ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $u['nama_role'] ?? '-'))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if (!empty($u['nama_divisi'])): ?>
                                                <i class="fas fa-building text-muted"></i>
                                                <?= htmlspecialchars($u['nama_divisi']) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($u['nama_departemen'])): ?>
                                                &nbsp;·&nbsp;<?= htmlspecialchars($u['nama_departemen']) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($u['nama_cabang'])): ?>
                                                <br><i class="fas fa-map-marker-alt text-muted"></i>
                                                <?= htmlspecialchars($u['nama_cabang']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($has_custom): ?>
                                            <span class="badge badge-custom">
                                                <i class="fas fa-user-cog"></i> Custom
                                            </span>
                                            <br>
                                            <small class="text-muted custom-indicator">
                                                <?= count($custom_perms) ?> modul dikustomisasi
                                            </small>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-users"></i> Default Role
                                            </span>
                                            <br>
                                            <small class="text-muted custom-indicator">
                                                Ikut permission role
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td onclick="event.stopPropagation()">
                                        <a href="edit_user_permissions.php?id=<?= urlencode($u['id_user']) ?>"
                                           class="btn btn-warning btn-sm" title="Edit Permission">
                                            <i class="fas fa-edit fa-xs"></i> Edit
                                        </a>
                                        <?php if ($has_custom): ?>
                                        <a href="reset_user_permissions.php?id=<?= urlencode($u['id_user']) ?>"
                                           class="btn btn-outline-secondary btn-sm btn-reset"
                                           data-name="<?= htmlspecialchars($u['nama_user']) ?>"
                                           title="Reset ke default role">
                                            <i class="fas fa-undo fa-xs"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- TAB KELOLA ROLE (DEFAULT ROLE) -->
            <div class="alert alert-info py-2 mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:8px">
                <div>
                    <i class="fas fa-info-circle"></i>
                    <strong>Default Role:</strong> Mengatur hak akses bawaan untuk setiap role (berlaku bagi user dengan status <em>Default Role</em> dan sebagai template dasar saat mereset permission user).
                </div>
                <div class="d-flex" style="gap:8px">
                    <form action="sync_standard_roles.php" method="POST" class="d-inline"
                          onsubmit="return confirm('Standarisasi default permission semua role sesuai struktur modul terbaru?\n\nCatatan: Custom permission milik masing-masing user TIDAK akan ditimpa.');">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt"></i> Standarisasi Default Role
                        </button>
                    </form>
                    <a href="add_role.php" class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Tambah Role Baru
                    </a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-shield-alt"></i> Daftar Role & Default Permission</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:50px">#</th>
                                    <th>Nama Role</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah Modul Aktif</th>
                                    <th>Pengguna</th>
                                    <th style="width:170px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rno = 1; while ($rl = mysqli_fetch_assoc($roles_list_result)): ?>
                                <?php
                                $rperms = json_decode($rl['permissions'] ?? '{}', true) ?: [];
                                $is_sa  = ($rl['nama_role'] === 'super_admin');
                                ?>
                                <tr>
                                    <td><small class="text-muted"><?= $rno++ ?></small></td>
                                    <td>
                                        <span class="badge badge-<?= $is_sa ? 'danger' : 'primary' ?>" style="font-size:0.82rem">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $rl['nama_role']))) ?>
                                        </span>
                                        <br><small class="text-muted"><code><?= htmlspecialchars($rl['nama_role']) ?></code></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($rl['deskripsi'] ?: '—') ?></small></td>
                                    <td>
                                        <?php if ($is_sa): ?>
                                            <span class="badge badge-danger"><i class="fas fa-infinity"></i> Semua Modul (Full Access)</span>
                                        <?php else: ?>
                                            <span class="badge badge-info"><?= count($rperms) ?> modul</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?= (int)$rl['user_count'] ?> user</span>
                                    </td>
                                    <td>
                                        <a href="edit_role.php?id=<?= (int)$rl['id_role'] ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit fa-xs"></i> Edit Role
                                        </a>
                                        <?php if (!$is_sa && (int)$rl['user_count'] === 0): ?>
                                        <a href="delete_role.php?id=<?= (int)$rl['id_role'] ?>"
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Hapus role <?= htmlspecialchars($rl['nama_role']) ?>?');"
                                           title="Hapus Role">
                                            <i class="fas fa-trash fa-xs"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php include('../layouts/footer.php'); ?>
        </div>
    </div>
</div>

<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function() {
    // Update count
    function updateCount() {
        var visible = $('#userTableBody tr:visible').length;
        $('#total-count').text(visible + ' user');
    }

    // Live search
    $('#searchBox').on('input', function() {
        var q = $(this).val().toLowerCase();
        filter(q, $('#filterRole').val(), $('#filterCustom').val());
    });

    $('#filterRole').on('change', function() {
        var q = $('#searchBox').val().toLowerCase();
        filter(q, $(this).val(), $('#filterCustom').val());
    });

    $('#filterCustom').on('change', function() {
        var q = $('#searchBox').val().toLowerCase();
        filter(q, $('#filterRole').val(), $(this).val());
    });

    function filter(q, role, customFilter) {
        $('#userTableBody tr').each(function() {
            var $row = $(this);
            // Ensure all data attributes are strings to prevent errors with numeric data
            var name     = String($row.data('name')     || "").toLowerCase();
            var username = String($row.data('username') || "").toLowerCase();
            var divisi   = String($row.data('divisi')   || "").toLowerCase();
            var jabatan  = String($row.data('jabatan')  || "").toLowerCase();
            var rowRole  = String($row.data('role')     || "").toLowerCase();
            var custom   = String($row.data('custom')   || "").toLowerCase();

            var matchQ = !q ||
                name.indexOf(q) !== -1 ||
                username.indexOf(q) !== -1 ||
                divisi.indexOf(q) !== -1 ||
                jabatan.indexOf(q) !== -1;

            var matchRole   = !role   || rowRole === String(role).toLowerCase();
            var matchCustom = !customFilter || custom === String(customFilter).toLowerCase();
            
            $row.toggle(matchQ && matchRole && matchCustom);
        });
        updateCount();
    }

    // Reset konfirmasi
    $(document).on('click', '.btn-reset', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var name = $(this).data('name');
        if (confirm('Reset permission "' + name + '" ke default role?\nPermission kustom akan dihapus.')) {
            window.location.href = href;
        }
    });

    updateCount();
});
</script>
</body>
</html>
