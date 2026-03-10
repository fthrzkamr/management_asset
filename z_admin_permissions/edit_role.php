<?php
// z_admin_permissions/edit_role.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
include('permissions_config.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}

$role_id = intval($_GET['id'] ?? 0);
if (!$role_id) { header('Location: dashboard.php'); exit(); }

$res = mysqli_query($conn, "SELECT * FROM roles WHERE id_role = $role_id");
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = 'Role tidak ditemukan.';
    header('Location: dashboard.php'); exit();
}
$role = mysqli_fetch_assoc($res);
$current_perms = json_decode($role['permissions'] ?? '{}', true) ?: [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Permission Role - <?= htmlspecialchars($role['nama_role']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .perm-table th, .perm-table td { text-align: center; vertical-align: middle; font-size: 0.82rem; }
        .perm-table th:first-child, .perm-table td:first-child { text-align: left; }
        .perm-table th { background: #f8f9fa; }
        .group-row td { background: #eef2ff; font-weight: 700; color: #3451b2; font-size: 0.78rem;
                        text-transform: uppercase; letter-spacing: 1px; padding: 6px 12px !important; }
        .check-all-col { cursor: pointer; }
        .check-all-col:hover { background: #e8f0fe; }
        .super-notice { background: linear-gradient(135deg, #ff6b6b22, #ff000011); border: 1px solid #ff000033; border-radius: 8px; }
        .btn-check-all-row { font-size: 0.7rem; padding: 2px 6px; }
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
                            <i class="fas fa-shield-alt text-warning"></i>
                            Edit Permission &mdash;
                            <span class="badge badge-<?= $role['nama_role'] === 'super_admin' ? 'danger' : 'primary' ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role['nama_role']))) ?>
                            </span>
                        </h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Administrator</a></li>
                            <li class="breadcrumb-item active">Edit Permission</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($role['nama_role'] === 'super_admin'): ?>
            <div class="super-notice p-3 mb-4">
                <i class="fas fa-infinity text-danger"></i>
                <strong>Super Admin</strong> memiliki akses penuh ke semua fitur secara otomatis.
                Anda tetap bisa menyimpan data JSON-nya, namun permission akan selalu di-override ke full-access.
            </div>
            <?php endif; ?>

            <form action="save_role.php" method="POST">
                <input type="hidden" name="role_id" value="<?= $role_id ?>">

                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-table"></i> Matrix Permission Modul</h6>
                        <div class="d-flex" style="gap:8px">
                            <button type="button" class="btn btn-outline-success btn-sm" id="btn-check-all">
                                <i class="fas fa-check-double"></i> Centang Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-uncheck-all">
                                <i class="fas fa-times"></i> Hapus Semua
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered perm-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="min-width:200px">Modul</th>
                                        <?php foreach ($ALL_ACTIONS as $act): ?>
                                            <th class="check-all-col" title="Centang semua '<?= $ACTION_LABELS[$act] ?>'" data-action="<?= $act ?>">
                                                <?= $ACTION_LABELS[$act] ?><br>
                                                <small class="text-muted" style="font-size:0.65rem">(<?= $act ?>)</small>
                                            </th>
                                        <?php endforeach; ?>
                                        <th>Pilih Baris</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $prev_group = '';
                                    foreach ($PERMISSION_MODULES as $mod_key => $mod):
                                        if ($mod['group'] !== $prev_group):
                                            $prev_group = $mod['group'];
                                    ?>
                                    <tr>
                                        <td colspan="<?= count($ALL_ACTIONS) + 2 ?>" class="group-row">
                                            <i class="fas fa-folder-open mr-1"></i><?= $mod['group'] ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>
                                            <i class="<?= $mod['icon'] ?> text-primary mr-1"></i>
                                            <span><?= htmlspecialchars($mod['label']) ?></span><br>
                                            <code style="font-size:0.68rem;color:#888"><?= $mod_key ?></code>
                                        </td>
                                        <?php foreach ($ALL_ACTIONS as $act): ?>
                                            <?php
                                            $available = in_array($act, $mod['actions']);
                                            $checked   = isset($current_perms[$mod_key]) && in_array($act, $current_perms[$mod_key]);
                                            ?>
                                            <td style="<?= !$available ? 'background:#f5f5f5' : '' ?>">
                                                <?php if ($available): ?>
                                                    <div class="custom-control custom-checkbox d-flex justify-content-center">
                                                        <input type="checkbox"
                                                               class="custom-control-input perm-check action-<?= $act ?> module-<?= $mod_key ?>"
                                                               id="perm_<?= $mod_key ?>_<?= $act ?>"
                                                               name="permissions[<?= $mod_key ?>][]"
                                                               value="<?= $act ?>"
                                                               <?= $checked ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="perm_<?= $mod_key ?>_<?= $act ?>"></label>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" title="Tidak tersedia">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <button type="button" class="btn btn-outline-info btn-check-all-row btn-sm"
                                                    data-module="<?= $mod_key ?>">
                                                <i class="fas fa-check-double"></i> Semua
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="form-group mb-2">
                            <label><i class="fas fa-align-left"></i> Deskripsi Role</label>
                            <input type="text" name="deskripsi" class="form-control"
                                   value="<?= htmlspecialchars($role['deskripsi'] ?? '') ?>"
                                   placeholder="Deskripsi singkat role ini">
                        </div>
                        <div class="d-flex" style="gap:10px">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Permission
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <?php include('../layouts/footer.php'); ?>
        </div>
    </div>
</div>
<script>
$(function() {
    // Check all per action (kolom)
    $('.check-all-col').on('click', function() {
        const act = $(this).data('action');
        const checkboxes = $('.action-' + act + ':not(:disabled)');
        const allChecked = checkboxes.filter(':checked').length === checkboxes.length;
        checkboxes.prop('checked', !allChecked);
    });

    // Check all per row (baris modul)
    $(document).on('click', '.btn-check-all-row', function() {
        const mod = $(this).data('module');
        const checks = $('.module-' + mod);
        const allChecked = checks.filter(':checked').length === checks.length;
        checks.prop('checked', !allChecked);
    });

    // Check ALL
    $('#btn-check-all').on('click', function() {
        $('.perm-check').prop('checked', true);
    });

    // Uncheck ALL
    $('#btn-uncheck-all').on('click', function() {
        $('.perm-check').prop('checked', false);
    });
});
</script>
</body>
</html>
