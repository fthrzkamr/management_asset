<?php
// z_admin_permissions/add_role.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
include('permissions_config.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}

// Handle POST - simpan role baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_role = trim(mysqli_real_escape_string($conn, strtolower(str_replace(' ', '_', $_POST['nama_role'] ?? ''))));
    $deskripsi = trim(mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? ''));

    if (empty($nama_role)) {
        $error = 'Nama role tidak boleh kosong.';
    } else {
        // Cek duplikat
        $chk = mysqli_query($conn, "SELECT id_role FROM roles WHERE nama_role = '$nama_role'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $error = 'Nama role sudah ada.';
        } else {
            // Bangun permission dari form
            $new_perms = [];
            $posted = $_POST['permissions'] ?? [];
            foreach ($PERMISSION_MODULES as $mod_key => $mod) {
                if (isset($posted[$mod_key]) && is_array($posted[$mod_key])) {
                    $valid = array_intersect($posted[$mod_key], $mod['actions']);
                    if (!empty($valid)) $new_perms[$mod_key] = array_values($valid);
                }
            }
            $perms_json = json_encode($new_perms, JSON_UNESCAPED_UNICODE);

            $ins = mysqli_prepare($conn,
                "INSERT INTO roles (nama_role, deskripsi, permissions) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($ins, "sss", $nama_role, $deskripsi, $perms_json);
            if (mysqli_stmt_execute($ins)) {
                $_SESSION['success'] = 'Role "' . htmlspecialchars($nama_role) . '" berhasil ditambahkan.';
                header('Location: dashboard.php'); exit();
            } else {
                $error = 'Gagal menyimpan: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($ins);
        }
    }
}

// Default permissions = kosong
$current_perms = [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Role Baru</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .perm-table th, .perm-table td { text-align: center; vertical-align: middle; font-size: 0.82rem; }
        .perm-table th:first-child, .perm-table td:first-child { text-align: left; }
        .perm-table th { background: #f8f9fa; }
        .group-row td { background: #eef2ff; font-weight: 700; color: #3451b2;
                        font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; padding: 6px 12px !important; }
        .btn-check-all-row { font-size: 0.7rem; padding: 2px 6px; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">
            <div class="page-header">
                <div class="row">
                    <div class="col-12">
                        <h3 class="page-title"><i class="fas fa-plus-circle text-success"></i> Tambah Role Baru</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Administrator</a></li>
                            <li class="breadcrumb-item active">Tambah Role</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h6 class="mb-0"><i class="fas fa-tag"></i> Informasi Role</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Nama Role <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_role" class="form-control"
                                           placeholder="contoh: finance_puri" required
                                           value="<?= htmlspecialchars($_POST['nama_role'] ?? '') ?>">
                                    <small class="text-muted">Huruf kecil dan underscore. Spasi otomatis jadi underscore.</small>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Deskripsi</label>
                                    <input type="text" name="deskripsi" class="form-control"
                                           placeholder="Deskripsi singkat role ini"
                                           value="<?= htmlspecialchars($_POST['deskripsi'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-table"></i> Set Permission</h6>
                        <div style="gap:8px" class="d-flex">
                            <button type="button" id="btn-check-all" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-check-double"></i> Centang Semua
                            </button>
                            <button type="button" id="btn-uncheck-all" class="btn btn-outline-secondary btn-sm">
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
                                            <th class="check-all-col" data-action="<?= $act ?>" style="cursor:pointer" title="Centang kolom ini">
                                                <?= $ACTION_LABELS[$act] ?><br>
                                                <small class="text-muted" style="font-size:0.65rem">(<?= $act ?>)</small>
                                            </th>
                                        <?php endforeach; ?>
                                        <th>Baris</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $prev_group = '';
                                    foreach ($PERMISSION_MODULES as $mod_key => $mod):
                                        if ($mod['group'] !== $prev_group):
                                            $prev_group = $mod['group'];
                                    ?>
                                    <tr><td colspan="<?= count($ALL_ACTIONS) + 2 ?>" class="group-row">
                                        <i class="fas fa-folder-open mr-1"></i><?= $mod['group'] ?>
                                    </td></tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>
                                            <i class="<?= $mod['icon'] ?> text-primary mr-1"></i>
                                            <?= htmlspecialchars($mod['label']) ?><br>
                                            <code style="font-size:0.68rem;color:#888"><?= $mod_key ?></code>
                                        </td>
                                        <?php foreach ($ALL_ACTIONS as $act): ?>
                                            <?php $available = in_array($act, $mod['actions']); ?>
                                            <td style="<?= !$available ? 'background:#f5f5f5' : '' ?>">
                                                <?php if ($available): ?>
                                                    <div class="custom-control custom-checkbox d-flex justify-content-center">
                                                        <input type="checkbox"
                                                               class="custom-control-input perm-check action-<?= $act ?> module-<?= $mod_key ?>"
                                                               id="perm_<?= $mod_key ?>_<?= $act ?>"
                                                               name="permissions[<?= $mod_key ?>][]"
                                                               value="<?= $act ?>">
                                                        <label class="custom-control-label" for="perm_<?= $mod_key ?>_<?= $act ?>"></label>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <button type="button" class="btn btn-outline-info btn-check-all-row btn-sm"
                                                    data-module="<?= $mod_key ?>">Semua</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex mb-4" style="gap:10px">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Buat Role
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>

            <?php include('../layouts/footer.php'); ?>
        </div>
    </div>
</div>
<script>
$(function() {
    $('.check-all-col').on('click', function() {
        const act = $(this).data('action');
        const checks = $('.action-' + act);
        checks.prop('checked', checks.filter(':checked').length !== checks.length);
    });
    $(document).on('click', '.btn-check-all-row', function() {
        const mod = $(this).data('module');
        const checks = $('.module-' + mod);
        checks.prop('checked', checks.filter(':checked').length !== checks.length);
    });
    $('#btn-check-all').on('click', () => $('.perm-check').prop('checked', true));
    $('#btn-uncheck-all').on('click', () => $('.perm-check').prop('checked', false));
});
</script>
</body>
</html>
