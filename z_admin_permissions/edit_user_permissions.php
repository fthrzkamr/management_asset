<?php
// z_admin_permissions/edit_user_permissions.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
include('permissions_config.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn() || !$auth->isSuperAdmin()) {
    header('Location: ../index.php'); exit();
}

$user_id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
if (!$user_id) { header('Location: dashboard.php'); exit(); }

// Ambil data user lengkap
$u_res = mysqli_query($conn,
    "SELECT u.*, r.nama_role, r.permissions as role_permissions,
            d.nama_divisi, dep.nama_departemen, mc.nama_cabang
     FROM user_dfm u
     LEFT JOIN roles r ON u.id_role = r.id_role
     LEFT JOIN divisi d ON u.divisi = d.id_divisi
     LEFT JOIN departemen dep ON u.departemen = dep.id_departemen
     LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
     WHERE u.id_user = '$user_id'");

if (!$u_res || mysqli_num_rows($u_res) === 0) {
    $_SESSION['error'] = 'User tidak ditemukan.';
    header('Location: dashboard.php'); exit();
}
$user = mysqli_fetch_assoc($u_res);

// Permission saat ini: custom (per-user) > role default
$has_custom   = !empty($user['custom_permissions']) && $user['custom_permissions'] !== 'null';
$custom_perms = $has_custom ? (json_decode($user['custom_permissions'], true) ?: []) : [];
$role_perms   = json_decode($user['role_permissions'] ?? '{}', true) ?: [];

// Jika belum ada custom, tampilkan dengan role permission sebagai default (checked)
$current_perms = $has_custom ? $custom_perms : $role_perms;

$initial = strtoupper(substr($user['nama_user'] ?? 'U', 0, 1));
$color_list = ['#4361ee','#3a0ca3','#7209b7','#f72585','#4cc9f0'];
$bg_color = $color_list[crc32($user['id_user']) % count($color_list)];
$is_super = ($user['nama_role'] === 'super_admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Permission — <?= htmlspecialchars($user['nama_user']) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .user-info-card { border-radius: 12px; overflow: hidden; }
        .user-info-header { background: linear-gradient(135deg, <?= $bg_color ?>, <?= $bg_color ?>cc);
                            color: #fff; padding: 20px 24px; }
        .avatar-lg { width: 54px; height: 54px; border-radius: 50%; background: rgba(255,255,255,.25);
                     display: flex; align-items: center; justify-content: center;
                     font-size: 1.4rem; font-weight: 700; color: #fff; flex-shrink: 0; }
        .perm-table th, .perm-table td { text-align: center; vertical-align: middle; font-size: 0.82rem; }
        .perm-table th:first-child, .perm-table td:first-child { text-align: left; }
        .perm-table th { background: #f8f9fa; }
        .group-row td { background: #eef2ff; font-weight: 700; color: #3451b2;
                        font-size: 0.78rem; text-transform: uppercase;
                        letter-spacing: 1px; padding: 6px 12px !important; }
        .role-inherited { position: relative; }
        .role-inherited::after {
            content: 'R'; position: absolute; top: 2px; right: 2px;
            font-size: 0.55rem; background: #6c757d; color: #fff;
            border-radius: 2px; padding: 0 2px; opacity: 0.8;
        }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .btn-check-all-row { font-size: 0.7rem; padding: 2px 6px; }
        .sticky-save { position: sticky; bottom: 0; background: #fff;
                       border-top: 1px solid #dee2e6; padding: 12px 0; z-index: 100; }
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
                        <h3 class="page-title">
                            <i class="fas fa-user-edit text-warning"></i> Edit Permission User
                        </h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php">Administrator</a></li>
                            <li class="breadcrumb-item active">Edit Permission</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="card user-info-card shadow mb-4">
                <div class="user-info-header">
                    <div class="d-flex align-items-center">
                        <div class="avatar-lg mr-3"><?= $initial ?></div>
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($user['nama_user']) ?></h5>
                            <div style="opacity:.85; font-size:.85rem">
                                <i class="fas fa-at"></i> <?= htmlspecialchars($user['username']) ?>
                                &nbsp;&nbsp;
                                <i class="fas fa-id-badge"></i>
                                <span class="badge badge-light text-dark ml-1">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['nama_role'] ?? '-'))) ?>
                                </span>
                            </div>
                            <div style="opacity:.75; font-size:.8rem; margin-top:4px">
                                <?php if (!empty($user['nama_divisi'])): ?>
                                    <i class="fas fa-building"></i> <?= htmlspecialchars($user['nama_divisi']) ?>
                                <?php endif; ?>
                                <?php if (!empty($user['nama_departemen'])): ?>
                                    &nbsp;·&nbsp; <?= htmlspecialchars($user['nama_departemen']) ?>
                                <?php endif; ?>
                                <?php if (!empty($user['nama_cabang'])): ?>
                                    &nbsp;·&nbsp; <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['nama_cabang']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-auto text-right">
                            <?php if ($has_custom): ?>
                                <span class="badge badge-light">
                                    <i class="fas fa-user-cog"></i> Sudah dikustomisasi
                                </span>
                            <?php else: ?>
                                <span class="badge badge-light" style="opacity:.8">
                                    <i class="fas fa-users"></i> Mengikuti default role
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_super): ?>
            <div class="alert alert-warning">
                <i class="fas fa-infinity"></i>
                <strong>Super Admin</strong> memiliki akses penuh secara otomatis.
                Anda tetap bisa menyimpan permission khusus untuk user ini, namun tidak akan berpengaruh.
            </div>
            <?php endif; ?>

            <!-- Legend -->
            <div class="d-flex align-items-center mb-3" style="gap: 16px; font-size:.8rem;">
                <span><span class="legend-dot" style="background:#28a745"></span> Centang = akses diberikan</span>
                <span><span class="legend-dot" style="background:#6c757d"></span> — = aksi tidak tersedia di modul ini</span>
                <span><small class="badge badge-secondary">R</small> = inherited dari role (saat belum ada custom)</span>
            </div>

            <form action="save_user_permissions.php" method="POST">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id_user']) ?>">

                <div class="card shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-table"></i> Matrix Permission Modul
                        </h6>
                        <div class="d-flex" style="gap:8px">
                            <button type="button" class="btn btn-outline-success btn-sm" id="btn-check-all">
                                <i class="fas fa-check-double"></i> Centang Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-uncheck-all">
                                <i class="fas fa-times"></i> Hapus Semua
                            </button>
                            <?php if ($has_custom): ?>
                            <button type="button" class="btn btn-outline-info btn-sm" id="btn-load-role">
                                <i class="fas fa-sync"></i> Load dari Role
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered perm-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="min-width:200px">Modul</th>
                                        <?php foreach ($ALL_ACTIONS as $act): ?>
                                            <th class="check-all-col" data-action="<?= $act ?>"
                                                style="cursor:pointer" title="Centang/uncentang semua '<?= $ACTION_LABELS[$act] ?>'">
                                                <?= $ACTION_LABELS[$act] ?><br>
                                                <small class="text-muted" style="font-size:.65rem">(<?= $act ?>)</small>
                                            </th>
                                        <?php endforeach; ?>
                                        <th>Baris</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Encode role permissions untuk JS
                                    $role_perms_js = json_encode($role_perms, JSON_HEX_TAG);
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
                                            <span><?= htmlspecialchars($mod['label']) ?></span><br>
                                            <code style="font-size:.68rem;color:#888"><?= $mod_key ?></code>
                                        </td>
                                        <?php foreach ($ALL_ACTIONS as $act):
                                            $available       = in_array($act, $mod['actions']);
                                            $is_role_checked = isset($role_perms[$mod_key]) && in_array($act, $role_perms[$mod_key]);
                                            $is_checked      = isset($current_perms[$mod_key]) && in_array($act, $current_perms[$mod_key]);
                                        ?>
                                            <td style="<?= !$available ? 'background:#f5f5f5' : '' ?>"
                                                class="<?= ($available && $is_role_checked && !$has_custom) ? 'role-inherited' : '' ?>">
                                                <?php if ($available): ?>
                                                    <div class="custom-control custom-checkbox d-flex justify-content-center">
                                                        <input type="checkbox"
                                                               class="custom-control-input perm-check action-<?= $act ?> module-<?= $mod_key ?>"
                                                               id="perm_<?= $mod_key ?>_<?= $act ?>"
                                                               name="permissions[<?= $mod_key ?>][]"
                                                               value="<?= $act ?>"
                                                               data-role-checked="<?= $is_role_checked ? '1' : '0' ?>"
                                                               <?= $is_checked ? 'checked' : '' ?>>
                                                        <label class="custom-control-label"
                                                               for="perm_<?= $mod_key ?>_<?= $act ?>"></label>
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

                <!-- Sticky Save Bar -->
                <div class="sticky-save">
                    <div class="d-flex align-items-center" style="gap:10px">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Permission
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <?php if ($has_custom): ?>
                        <a href="reset_user_permissions.php?id=<?= urlencode($user['id_user']) ?>"
                           class="btn btn-outline-danger ml-auto btn-reset-confirm">
                            <i class="fas fa-undo"></i> Reset ke Default Role
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php include('../layouts/footer.php'); ?>
        </div>
    </div>
</div>

<script>
var rolePerms = <?= $role_perms_js ?>;

$(function() {
    // Check all per action (klik header kolom)
    $('.check-all-col').on('click', function() {
        var act = $(this).data('action');
        var checks = $('.action-' + act);
        var allChecked = checks.filter(':checked').length === checks.length;
        checks.prop('checked', !allChecked);
    });

    // Check all per row
    $(document).on('click', '.btn-check-all-row', function() {
        var mod = $(this).data('module');
        var checks = $('.module-' + mod);
        var allChecked = checks.filter(':checked').length === checks.length;
        checks.prop('checked', !allChecked);
    });

    $('#btn-check-all').on('click', function() {
        $('.perm-check').prop('checked', true);
    });

    $('#btn-uncheck-all').on('click', function() {
        $('.perm-check').prop('checked', false);
    });

    // Load dari role — set checkbox sesuai role permission
    $('#btn-load-role').on('click', function() {
        $('.perm-check').prop('checked', false);
        $.each(rolePerms, function(mod, actions) {
            $.each(actions, function(i, act) {
                $('#perm_' + mod + '_' + act).prop('checked', true);
            });
        });
    });

    // Konfirmasi reset
    $('.btn-reset-confirm').on('click', function(e) {
        if (!confirm('Reset permission user ini ke default role?\nSemua kustomisasi akan hilang.')) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>
