<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('budget_routing', 'read');

// ── Cek tabel cabang & divisi override ─────────────────────────────────────
$has_cabang_tbl = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'ppx_budget_routing_cabang'")) > 0;
$has_divisi_tbl = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'ppx_budget_routing_divisi'")) > 0;
$has_ho_col     = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM ppx_budget_routing LIKE 'ho_only'")) > 0;

// ── Global Steps ──────────────────────────────────────────────────────────
$routing_steps = [];
$res = mysqli_query($conn, "SELECT * FROM ppx_budget_routing ORDER BY step_order ASC");
while ($r = mysqli_fetch_assoc($res)) $routing_steps[] = $r;

// ── Semua Cabang ──────────────────────────────────────────────────────────
$cabang_list = [];
$res_cb = mysqli_query($conn, "SELECT id_cabang, nama_cabang FROM master_cabang ORDER BY nama_cabang ASC");
while ($r = mysqli_fetch_assoc($res_cb)) $cabang_list[] = $r;

// ── Per-Cabang Overrides ─────────────────────────────────────────────────
$overrides = []; // [id_cabang][id_routing_step] = {approver_id, skip_step}
if ($has_cabang_tbl) {
    $res_ov = mysqli_query($conn, "SELECT * FROM ppx_budget_routing_cabang WHERE is_active = 1");
    while ($r = mysqli_fetch_assoc($res_ov)) {
        $overrides[$r['id_cabang']][$r['id_routing_step']] = $r;
    }
}

// ── Semua Divisi ──────────────────────────────────────────────────────────
$divisi_list = [];
$res_dv = mysqli_query($conn, "SELECT id_divisi, nama_divisi FROM divisi ORDER BY nama_divisi ASC");
if ($res_dv) {
    while ($r = mysqli_fetch_assoc($res_dv)) $divisi_list[] = $r;
}

// ── Per-Divisi Overrides ─────────────────────────────────────────────────
// Format baru: [id_divisi][id_cabang_or_'__GLOBAL__'][id_routing_step]
// '__GLOBAL__' digunakan untuk id_cabang IS NULL
$divisi_overrides   = [];
$divisi_cabang_list = []; // [id_divisi] = [array id_cabang yang valid]
$has_cabang_col_dv  = false;
if ($has_divisi_tbl) {
    $ccol = mysqli_query($conn, "SHOW COLUMNS FROM ppx_budget_routing_divisi LIKE 'id_cabang'");
    $has_cabang_col_dv = $ccol && mysqli_num_rows($ccol) > 0;

    $dv_cols = $has_cabang_col_dv ? '*' : 'id, id_divisi, NULL AS id_cabang, id_routing_step, skip_step, is_active, created_at, updated_at';
    $res_dv_ov = mysqli_query($conn, "SELECT {$dv_cols} FROM ppx_budget_routing_divisi WHERE is_active = 1");
    if ($res_dv_ov) {
        while ($r = mysqli_fetch_assoc($res_dv_ov)) {
            $cab_key = ($r['id_cabang'] !== null) ? $r['id_cabang'] : '__GLOBAL__';
            $divisi_overrides[$r['id_divisi']][$cab_key][$r['id_routing_step']] = $r;
            if ($r['id_cabang'] !== null && !in_array($r['id_cabang'], $divisi_cabang_list[$r['id_divisi']] ?? [])) {
                $divisi_cabang_list[$r['id_divisi']][] = $r['id_cabang'];
            }
        }
    }
}

// ── JSON untuk JS auto-fill checkbox ─────────────────────────────────────
// Format: {"id_divisi": {"cab_key": {"id_step": 0|1}}}
// cab_key = "" untuk global (NULL), atau id_cabang, nilai: 0=Wajib, 1=Lewati
$divisi_json = [];
foreach ($divisi_overrides as $dvid => $cab_map) {
    foreach ($cab_map as $cab_key => $step_map) {
        $jkey = ($cab_key === '__GLOBAL__') ? '' : $cab_key;
        foreach ($step_map as $sid => $rule) {
            $divisi_json[$dvid][$jkey][$sid] = (int)$rule['skip_step'];
        }
    }
}

// ── Semua User (untuk dropdown) ───────────────────────────────────────────
$available_users = [];
$res_u = mysqli_query($conn, "SELECT u.id_user, u.nama_user, u.username, r.nama_role, mc.nama_cabang 
    FROM user_dfm u 
    LEFT JOIN roles r ON u.id_role = r.id_role 
    LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
    ORDER BY u.nama_user ASC");
while ($u = mysqli_fetch_assoc($res_u)) $available_users[] = $u;

// ── Role list untuk add/edit modal ────────────────────────────────────────
$available_roles = [];
$res_r = mysqli_query($conn, "SELECT nama_role FROM roles ORDER BY nama_role");
while ($r = mysqli_fetch_assoc($res_r)) $available_roles[] = $r['nama_role'];

// ── Stats ─────────────────────────────────────────────────────────────────
$total_steps    = count($routing_steps);
$active_steps   = count(array_filter($routing_steps, fn($s) => $s['is_active']));
$cabang_config  = $has_cabang_tbl ? count($overrides) : 0;
$divisi_config  = $has_divisi_tbl ? count($divisi_overrides) : 0;

// ── Role Meta ─────────────────────────────────────────────────────────────
$ROLE_META = [
    'kacab'              => ['label' => 'Kepala Cabang',      'icon' => 'fas fa-user-tie',          'color' => '#8b5cf6'],
    'asmen_finance'      => ['label' => 'Asmen Finance',      'icon' => 'fas fa-user-check',        'color' => '#0ea5e9'],
    'manag_finance'      => ['label' => 'Manager Finance',    'icon' => 'fas fa-user-cog',          'color' => '#f59e0b'],
    'manag_operasional'  => ['label' => 'Manager Operasional','icon' => 'fas fa-user-shield',       'color' => '#ef4444'],
    'finance'            => ['label' => 'Finance',            'icon' => 'fas fa-coins',             'color' => '#10b981'],
];
function getRoleMeta($role, $meta) {
    return $meta[$role] ?? ['label' => $role, 'icon' => 'fas fa-user', 'color' => '#64748b'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfigurasi Routing Approval Anggaran</title>
    <link rel="shortcut icon" href="../assets/img/dua.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/plugins/select2/css/select2.min.css">
    <style>
        :root { --g-primary: #4f46e5; }
        body { background: #f1f5f9; }

        /* Stats */
        .stat-card { background:#fff; border-radius:16px; padding:20px 24px; box-shadow:0 1px 4px rgba(0,0,0,.07); display:flex; align-items:center; gap:16px; transition:.15s; }
        .stat-card:hover { transform:translateY(-2px); }
        .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#fff; }
        .stat-value { font-size:1.75rem; font-weight:700; line-height:1; }
        .stat-label { font-size:.8rem; color:#64748b; }

        /* Global Flow */
        .flow-card { background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,.07); overflow:hidden; margin-bottom:24px; }
        .flow-header { padding:18px 24px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .flow-header-title { font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px; }
        .flow-container { padding:24px; display:flex; align-items:flex-start; flex-wrap:wrap; gap:0; overflow-x:auto; }

        .flow-step { min-width:170px; max-width:210px; flex-shrink:0; position:relative; }
        .flow-step-inner { border:2px solid #e2e8f0; border-radius:14px; padding:16px; background:#fff; transition:.2s; }
        .flow-step-inner:hover { border-color:var(--sc,#4f46e5); box-shadow:0 4px 14px rgba(99,102,241,.15); }
        .flow-step-inner.inactive { opacity:.4; border-style:dashed; }
        .step-num { position:absolute; top:-10px; left:14px; width:24px; height:24px; border-radius:50%; border:2px solid var(--sc); color:var(--sc); background:#fff; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; }
        .step-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; margin-bottom:10px; }
        .step-name { font-size:.9rem; font-weight:700; color:#1e293b; margin-bottom:3px; }
        .step-role { font-size:.72rem; color:#64748b; margin-bottom:8px; }
        .step-bgs { display:flex; flex-wrap:wrap; gap:4px; }
        .bg { font-size:.68rem; border-radius:10px; padding:2px 7px; }
        .bg-opt  { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
        .bg-final{ background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        .bg-off  { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .flow-arrow { display:flex; align-items:center; height:80px; padding:0 6px; color:#94a3b8; font-size:1.1rem; flex-shrink:0; }
        .step-acts { display:flex; gap:5px; margin-top:10px; }
        .step-acts .btn { padding:3px 9px; font-size:.72rem; border-radius:8px; }

        /* Per-Cabang & Per-Divisi Table */
        .cabang-card { background:#fff; border-radius:16px; box-shadow:0 1px 4px rgba(0,0,0,.07); overflow:hidden; margin-bottom:24px; }
        .cabang-table-wrap { overflow-x:auto; }
        table.routing-tbl { width:100%; border-collapse:separate; border-spacing:0; }
        table.routing-tbl th { background:#f8fafc; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; padding:12px 16px; white-space:nowrap; border-bottom:2px solid #e2e8f0; }
        table.routing-tbl td { padding:10px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        table.routing-tbl tr:last-child td { border-bottom:none; }
        table.routing-tbl tr:hover td { background:#fafafa; }
        .cabang-name { font-weight:600; font-size:.88rem; color:#1e293b; }

        /* Step cell in table */
        .step-cell { min-width:220px; }
        .step-cell .select2-container { width:100% !important; }
        .skip-chk { display:flex; align-items:center; gap:6px; margin-top:6px; font-size:.75rem; color:#64748b; }

        /* Divisi step cell */
        .divisi-step-cell { min-width:150px; text-align:left; }
        .dv-chk-wrap { display:flex; flex-direction:column; gap:5px; }
        .dv-chk-label { display:flex; align-items:center; gap:6px; font-size:.78rem; color:#475569; cursor:pointer; user-select:none; }
        .dv-chk-label input[type=checkbox] { width:15px; height:15px; cursor:pointer; accent-color:#4f46e5; }
        .dv-chk-label.lbl-skip input[type=checkbox] { accent-color:#ef4444; }
        .dv-chk-label.lbl-wajib input[type=checkbox] { accent-color:#10b981; }
        .dv-chk-label.lbl-skip  { color:#991b1b; }
        .dv-chk-label.lbl-wajib { color:#166534; }
        .dv-chk-label.lbl-skip.active  { font-weight:700; }
        .dv-chk-label.lbl-wajib.active { font-weight:700; }
        .divisi-name { font-weight:600; font-size:.88rem; color:#1e293b; white-space:nowrap; }

        /* Config indicator dot */
        .cfg-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }

        /* Fix: dropdown profile navbar terpotong — hanya halaman ini */
        .header { overflow: visible !important; }
        .header .header-left,
        .header .header-right,
        .header ul.nav,
        .header ul.nav .nav-item.dropdown { overflow: visible !important; }
        .header .dropdown-menu {
            position: fixed !important;
            right: 16px !important;
            left: auto !important;
            z-index: 9999 !important;
        }

        @media(max-width:768px) {
            .flow-container { flex-direction:column; }
            .flow-arrow { height:20px; transform:rotate(90deg); }
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php include '../layouts/navbar.php'; ?>
    <div class="page-wrapper">
    <div class="content container-fluid">

        <!-- Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title"><i class="fas fa-route mr-2"></i>Konfigurasi Routing Approval Anggaran</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Routing Anggaran</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="setup_db.php" class="btn btn-outline-secondary btn-sm mr-2">
                        <i class="fas fa-database mr-1"></i> Setup DB
                    </a>
                    <?php if ($auth->hasPermission('budget_routing', 'create')): ?>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalAdd">
                        <i class="fas fa-plus mr-1"></i> Tambah Step
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['success']; unset($_SESSION['success']); ?> <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['error']; unset($_SESSION['error']); ?> <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
        <?php endif; ?>

        <?php if (!$has_cabang_tbl): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Tabel konfigurasi per-cabang belum dibuat. <a href="setup_db.php"><strong>Jalankan Setup DB</strong></a> terlebih dahulu.
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-sm-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)"><i class="fas fa-list-ol"></i></div>
                    <div><div class="stat-value"><?= $total_steps ?></div><div class="stat-label">Total Step Global</div></div>
                </div>
            </div>
            <div class="col-sm-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)"><i class="fas fa-check-circle"></i></div>
                    <div><div class="stat-value"><?= $active_steps ?></div><div class="stat-label">Step Aktif</div></div>
                </div>
            </div>
            <div class="col-sm-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)"><i class="fas fa-building"></i></div>
                    <div><div class="stat-value"><?= $cabang_config ?></div><div class="stat-label">Cabang Dikonfigurasi</div></div>
                </div>
            </div>
            <div class="col-sm-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:linear-gradient(135deg,#ec4899,#db2777)"><i class="fas fa-sitemap"></i></div>
                    <div><div class="stat-value"><?= $divisi_config ?></div><div class="stat-label">Divisi Dikonfigurasi</div></div>
                </div>
            </div>
        </div>

        <!-- ═══ GLOBAL FLOW STEPS ═══ -->
        <div class="flow-card">
            <div class="flow-header">
                <div class="flow-header-title"><i class="fas fa-project-diagram text-primary"></i> Alur Global (Urutan Step)</div>
                <small class="text-muted">Step ini berlaku secara default, bisa di-override per cabang di bawah</small>
            </div>
            <div class="flow-container">
                <?php foreach ($routing_steps as $i => $step):
                    $meta  = getRoleMeta($step['approver_role'], $ROLE_META);
                    $color = $meta['color'];
                ?>
                <div class="flow-step">
                    <div class="flow-step-inner <?= !$step['is_active'] ? 'inactive' : '' ?>" style="--sc:<?= $color ?>">
                        <div class="step-num" style="border-color:<?= $color ?>;color:<?= $color ?>"><?= $step['step_order'] ?></div>
                        <div class="step-icon" style="background:<?= $color ?>"><i class="<?= $meta['icon'] ?>"></i></div>
                        <div class="step-name"><?= htmlspecialchars($step['step_name']) ?></div>
                        <div class="step-role"><?= $meta['label'] ?></div>
                        <div class="step-bgs">
                            <?php if ($step['is_optional']): ?><span class="bg bg-opt"><i class="fas fa-forward fa-xs mr-1"></i>Opsional</span><?php endif; ?>
                            <?php if ($step['is_final']): ?><span class="bg bg-final"><i class="fas fa-flag-checkered fa-xs mr-1"></i>Final</span><?php endif; ?>
                            <?php if (!$step['is_active']): ?><span class="bg bg-off">Non-Aktif</span><?php endif; ?>
                        </div>
                        <div class="step-acts">
                            <?php if ($auth->hasPermission('budget_routing', 'update')): ?>
                            <button class="btn btn-sm btn-outline-primary btn-edit"
                                data-id="<?= $step['id'] ?>"
                                data-name="<?= htmlspecialchars($step['step_name']) ?>"
                                data-role="<?= $step['approver_role'] ?>"
                                data-order="<?= $step['step_order'] ?>"
                                data-optional="<?= $step['is_optional'] ?>"
                                data-final="<?= $step['is_final'] ?>"
                                data-active="<?= $step['is_active'] ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php endif; ?>
                            <?php if ($auth->hasPermission('budget_routing', 'delete')): ?>
                            <a href="process.php?action=delete&id=<?= $step['id'] ?>" class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Hapus step ini? Override per-cabang yang terkait juga akan dihapus.')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($i < count($routing_steps) - 1): ?>
                <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ═══ PER-CABANG CONFIG TABLE ═══ -->
        <div class="cabang-card">
            <div class="flow-header">
                <div class="flow-header-title"><i class="fas fa-map-marker-alt text-success"></i> Konfigurasi Per-Cabang</div>
                <div>
                    <span class="badge" style="background:#dcfce7;color:#166534;border-radius:10px;padding:4px 10px;">
                        <span class="cfg-dot" style="background:#10b981"></span>Dikonfigurasi
                    </span>
                    <span class="badge ml-1" style="background:#f1f5f9;color:#64748b;border-radius:10px;padding:4px 10px;">
                        <span class="cfg-dot" style="background:#cbd5e1"></span>Default
                    </span>
                </div>
            </div>
            <div class="p-3 bg-light" style="border-bottom:1px solid #e2e8f0;font-size:.82rem;color:#475569;">
                <i class="fas fa-info-circle mr-1"></i>
                Pilih <strong>User Approver</strong> untuk menentukan siapa yang approve step tersebut dari cabang ini. Centang <strong>"Lewati"</strong> untuk melewati step tersebut. Kosongkan arti-nya gunakan aturan global.
            </div>
            <div class="cabang-table-wrap">
                <table class="routing-tbl">
                    <thead>
                        <tr>
                            <th style="width:180px">Cabang</th>
                            <?php foreach ($routing_steps as $step): ?>
                            <th class="step-cell">
                                <?php $meta = getRoleMeta($step['approver_role'], $ROLE_META); ?>
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $meta['color'] ?>;margin-right:5px;"></span>
                                <?= htmlspecialchars($step['step_name']) ?>
                            </th>
                            <?php endforeach; ?>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cabang_list as $cabang):
                            $cbid = $cabang['id_cabang'];
                            $cab_overrides = $overrides[$cbid] ?? [];
                            $has_any_config = !empty($cab_overrides);
                        ?>
                        <tr data-cabang="<?= $cbid ?>">
                            <td>
                                <?php if ($has_any_config): ?>
                                    <span class="cfg-dot" style="background:#10b981"></span>
                                <?php else: ?>
                                    <span class="cfg-dot" style="background:#cbd5e1"></span>
                                <?php endif; ?>
                                <span class="cabang-name"><?= htmlspecialchars($cabang['nama_cabang']) ?></span>
                            </td>
                            <?php foreach ($routing_steps as $step):
                                $sid = $step['id'];
                                $ov  = $cab_overrides[$sid] ?? null;
                                $selected_user = $ov['approver_id'] ?? '';
                                $is_skip       = $ov['skip_step'] ?? 0;
                            ?>
                            <td class="step-cell">
                                <select name="step_approver[<?= $sid ?>]"
                                        class="form-control form-control-sm select2-cabang"
                                        data-step="<?= $sid ?>">
                                    <option value="">— Default (role global) —</option>
                                    <?php foreach ($available_users as $u): ?>
                                    <option value="<?= $u['id_user'] ?>"
                                        <?= $selected_user == $u['id_user'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nama_user']) ?>
                                        (<?= $u['nama_role'] ?? '-' ?>,
                                        <?= $u['nama_cabang'] ?? '-' ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="skip-chk">
                                    <input type="checkbox" class="skip-cb" data-step="<?= $sid ?>" value="1" <?= $is_skip ? 'checked' : '' ?>>
                                    Lewati step ini
                                </label>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($auth->hasPermission('budget_routing', 'update')): ?>
                                <button type="button" class="btn btn-sm btn-success btn-save-cabang" data-cabang="<?= $cbid ?>">
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ PER-DIVISI CONFIG TABLE ═══ -->
        <div class="cabang-card">
            <div class="flow-header">
                <div class="flow-header-title"><i class="fas fa-sitemap text-danger"></i> Konfigurasi Per-Divisi</div>
                <div>
                    <?php if (!$has_divisi_tbl): ?>
                    <a href="setup_divisi.php" class="btn btn-sm btn-warning" id="btn-setup-divisi">
                        <i class="fas fa-database mr-1"></i> Buat Tabel Divisi
                    </a>
                    <?php else: ?>
                    <span class="badge" style="background:#fce7f3;color:#9d174d;border-radius:10px;padding:4px 10px;">
                        <span class="cfg-dot" style="background:#ec4899"></span>Dikonfigurasi
                    </span>
                    <span class="badge ml-1" style="background:#f1f5f9;color:#64748b;border-radius:10px;padding:4px 10px;">
                        <span class="cfg-dot" style="background:#cbd5e1"></span>Default (ikuti cabang)
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-3 bg-light" style="border-bottom:1px solid #e2e8f0;font-size:.82rem;color:#475569;">
                <i class="fas fa-info-circle mr-1"></i>
                Atur routing khusus berdasarkan <strong>divisi + cabang</strong> pengaju.
                Pilih cabang yang ingin dikonfigurasi lalu centang <strong class="text-danger">Lewati</strong> atau <strong class="text-success">Wajib</strong> per step.
                Biarkan cabang kosong agar aturan berlaku <strong>semua cabang (global)</strong>.
            </div>

            <?php if (!$has_divisi_tbl): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning d-block"></i>
                Tabel <code>ppx_budget_routing_divisi</code> belum dibuat.<br>
                <a href="setup_divisi.php" class="btn btn-warning mt-2"><i class="fas fa-database mr-1"></i>Jalankan Setup Divisi</a>
            </div>
            <?php elseif (empty($divisi_list)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                Tidak ada data divisi ditemukan di tabel <code>divisi</code>.
            </div>
            <?php else: ?>
            <div class="cabang-table-wrap">
                <table class="routing-tbl">
                    <thead>
                        <tr>
                            <th style="width:160px">Divisi</th>
                            <th style="width:180px">Cabang <small class="text-muted">(kosong=semua)</small></th>
                            <?php foreach ($routing_steps as $step): ?>
                            <th class="divisi-step-cell">
                                <?php $meta = getRoleMeta($step['approver_role'], $ROLE_META); ?>
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $meta['color'] ?>;margin-right:5px;"></span>
                                <?= htmlspecialchars($step['step_name']) ?>
                            </th>
                            <?php endforeach; ?>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisi_list as $divisi):
                            $dvid      = $divisi['id_divisi'];
                            $dv_all    = $divisi_overrides[$dvid] ?? [];
                            $has_dv_config = !empty($dv_all);

                            // Baris utama — checkbox untuk aturan GLOBAL + dropdown pilih cabang
                            $dv_global  = $dv_all['__GLOBAL__'] ?? [];   // aturan global
                        ?>
                        <tr data-divisi="<?= $dvid ?>" data-cabang="">
                            <td>
                                <?php if ($has_dv_config): ?>
                                    <span class="cfg-dot" style="background:#ec4899"></span>
                                <?php else: ?>
                                    <span class="cfg-dot" style="background:#cbd5e1"></span>
                                <?php endif; ?>
                                <span class="divisi-name"><?= htmlspecialchars($divisi['nama_divisi']) ?></span>
                            </td>
                            <td>
                                <!-- Dropdown pilih cabang untuk aturan ini -->
                                <select class="form-control form-control-sm sel-dv-cabang" data-divisi="<?= $dvid ?>" style="width:100%">
                                    <option value=""><?= isset($divisi_json[$dvid]['']) ? '✓ ' : '' ?>Semua Cabang (Global)</option>
                                    <?php foreach ($cabang_list as $cb):
                                        $cab_has_rule = isset($divisi_json[$dvid][$cb['id_cabang']]);
                                    ?>
                                    <option value="<?= htmlspecialchars($cb['id_cabang']) ?>">
                                        <?= $cab_has_rule ? '✓ ' : '' ?><?= htmlspecialchars($cb['nama_cabang']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($divisi_cabang_list[$dvid])): ?>
                                <div class="mt-1" style="font-size:.75rem;color:#64748b;">
                                    <i class="fas fa-link"></i> Sudah ada aturan spesifik:
                                    <?php foreach ($divisi_cabang_list[$dvid] as $cid):
                                        $cnm = '';
                                        foreach ($cabang_list as $cb2) { if ($cb2['id_cabang']==$cid){$cnm=$cb2['nama_cabang'];break;} }
                                    ?>
                                    <span class="badge badge-sm" style="background:#dbeafe;color:#1e40af;border-radius:6px;padding:2px 6px;margin:1px"><?= htmlspecialchars($cnm ?: $cid) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($routing_steps as $step):
                                $sid     = $step['id'];
                                // Default tampilkan aturan global (akan reload saat pilih cabang via JS jika perlu)
                                $dv_ov   = $dv_global[$sid] ?? null;
                                $has_rule = !is_null($dv_ov) && ($dv_ov['is_active'] ?? 0);
                                $is_skip  = $has_rule ? (int)$dv_ov['skip_step'] : null;
                            ?>
                            <td class="divisi-step-cell">
                                <div class="dv-chk-wrap">
                                    <label class="dv-chk-label lbl-lewati <?= ($has_rule && $is_skip) ? 'active' : '' ?>">
                                        <input type="checkbox"
                                            class="cb-divisi-lewati"
                                            data-step="<?= $sid ?>"
                                            data-divisi="<?= $dvid ?>"
                                            <?= ($has_rule && $is_skip) ? 'checked' : '' ?>>
                                        <i class="fas fa-forward fa-xs"></i> Lewati
                                    </label>
                                    <label class="dv-chk-label lbl-wajib <?= ($has_rule && !$is_skip) ? 'active' : '' ?>">
                                        <input type="checkbox"
                                            class="cb-divisi-wajib"
                                            data-step="<?= $sid ?>"
                                            data-divisi="<?= $dvid ?>"
                                            <?= ($has_rule && !$is_skip) ? 'checked' : '' ?>>
                                        <i class="fas fa-check fa-xs"></i> Wajib
                                    </label>
                                </div>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($auth->hasPermission('budget_routing', 'update')): ?>
                                <button type="button" class="btn btn-sm btn-danger btn-save-divisi" data-divisi="<?= $dvid ?>">
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div><!-- /.cabang-card divisi -->
        <?php include '../layouts/footer.php'; ?>

    </div><!-- /.content.container-fluid -->
    <!-- footer.php nutup .page-wrapper dan .main-wrapper, lalu load jQuery+script.js -->

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <form action="process.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus mr-2"></i>Tambah Step Global</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nama Step <span class="text-danger">*</span></label>
                    <input type="text" name="step_name" class="form-control" required placeholder="cth: Kacab / Asmen Finance">
                </div>
                <div class="form-group">
                    <label>Role Default <span class="text-danger">*</span></label>
                    <select name="approver_role" class="form-control sel2-add" required style="width:100%">
                        <option value="">-- Pilih Role --</option>
                        <?php foreach ($available_roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Urutan (Step Order) <span class="text-danger">*</span></label>
                    <input type="number" name="step_order" class="form-control" value="<?= count($routing_steps) + 1 ?>" required min="1">
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="opt_add" name="is_optional"><label class="custom-control-label" for="opt_add">Opsional</label></div>
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="final_add" name="is_final"><label class="custom-control-label" for="final_add">Step Final</label></div>
                    </div>
                    <div class="col-6">
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="active_add" name="is_active" checked><label class="custom-control-label" for="active_add">Aktif</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal Edit Global Step ═══ -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form action="process.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Step Global</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label>Nama Step <span class="text-danger">*</span></label><input type="text" name="step_name" id="edit_name" class="form-control" required></div>
                <div class="form-group">
                    <label>Role Default <span class="text-danger">*</span></label>
                    <select name="approver_role" id="edit_role" class="form-control sel2-edit" required style="width:100%">
                        <?php foreach ($available_roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Urutan (Step Order) <span class="text-danger">*</span></label><input type="number" name="step_order" id="edit_order" class="form-control" required min="1"></div>
                <div class="row">
                    <div class="col-6">
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="opt_edit" name="is_optional"><label class="custom-control-label" for="opt_edit">Opsional</label></div>
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="final_edit" name="is_final"><label class="custom-control-label" for="final_edit">Step Final</label></div>
                    </div>
                    <div class="col-6">
                        <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="active_edit" name="is_active"><label class="custom-control-label" for="active_edit">Aktif</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/plugins/select2/js/select2.min.js"></script>
<script>
// Data aturan yang sudah tersimpan (dari PHP)
const divisiRules = <?= json_encode($divisi_json, JSON_UNESCAPED_UNICODE) ?>;

$(document).ready(function() {
    // ── AUTO-FILL CHECKBOX saat ganti dropdown cabang ────────────────────
    $(document).on('change', '.sel-dv-cabang', function() {
        const dvid    = $(this).data('divisi') + '';
        const cab_key = $(this).val() || '';   // '' = global
        const row     = $(this).closest('tr');

        // 1) Clear semua checkbox di baris ini dulu
        row.find('.cb-divisi-lewati, .cb-divisi-wajib').prop('checked', false)
            .closest('label').removeClass('active');

        // 2) Load aturan tersimpan untuk divisi + cabang ini
        const rules = (divisiRules[dvid] && divisiRules[dvid][cab_key]) ? divisiRules[dvid][cab_key] : null;

        if (rules) {
            $.each(rules, function(sid, skip_val) {
                if (skip_val === 1) {
                    // Lewati
                    const cb = row.find('.cb-divisi-lewati[data-step="' + sid + '"]');
                    cb.prop('checked', true).closest('label').addClass('active');
                } else {
                    // Wajib
                    const cb = row.find('.cb-divisi-wajib[data-step="' + sid + '"]');
                    cb.prop('checked', true).closest('label').addClass('active');
                }
            });
            // Kasih visual hint bahwa ini sudah punya aturan
            $(this).css('border-color', '#10b981');
        } else {
            // Tidak ada aturan untuk kombinasi ini → reset border
            $(this).css('border-color', '');
        }
    });

    // Select2 untuk setiap row cabang
    $('.select2-cabang').each(function() {
        $(this).select2({
            placeholder: '— Default (role global) —',
            allowClear: true,
            width: '100%'
        });
    });

    // Modal Add
    $('.sel2-add').select2({ dropdownParent: $('#modalAdd'), width: '100%' });

    // Modal Edit
    $('.btn-edit').on('click', function() {
        const d = $(this).data();
        $('#edit_id').val(d.id);
        $('#edit_name').val(d.name);
        $('#edit_order').val(d.order);
        $('#opt_edit').prop('checked', d.optional == 1);
        $('#final_edit').prop('checked', d.final == 1);
        $('#active_edit').prop('checked', d.active == 1);
        if (!$('#edit_role').hasClass('select2-hidden-accessible')) {
            $('#edit_role').select2({ dropdownParent: $('#modalEdit'), width: '100%' });
        }
        $('#edit_role').val(d.role).trigger('change');
        $('#modalEdit').modal('show');
    });

    // ── SAVE PER-CABANG via AJAX ─────────────────────────────────
    $(document).on('click', '.btn-save-cabang', function() {
        const btn        = $(this);
        const id_cabang  = (btn.attr('data-cabang') || '').trim();
        const row        = btn.closest('tr');

        // Validasi sebelum kirim
        if (!id_cabang) {
            alert('Error: id_cabang tidak valid. Coba refresh halaman.');
            return;
        }

        // Build FormData — kirim sebagai string eksplisit
        const fd = new FormData();
        fd.append('action', 'save_cabang');
        fd.append('id_cabang', id_cabang);

        // Collect all step selects in this row
        row.find('select[name^="step_approver"]').each(function() {
            const sid = $(this).attr('data-step');
            const val = $(this).val() || '';
            fd.append('step_approver[' + sid + ']', val);
        });

        // Collect skip checkboxes
        row.find('.skip-cb').each(function() {
            const sid = $(this).attr('data-step');
            if ($(this).is(':checked')) {
                fd.append('step_skip[' + sid + ']', '1');
            }
        });

        // Disable button sementara
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        fetch('process.php', { method: 'POST', body: fd })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data.ok) {
                    // Tampilkan konfirmasi tersimpan
                    btn.prop('disabled', false)
                       .html('<i class="fas fa-check-circle"></i> Tersimpan!')
                       .removeClass('btn-success').addClass('btn-success')
                       .css({'background':'#10b981','border-color':'#10b981','color':'#fff'});

                    // Update cfg-dot jadi hijau (menandakan ada konfigurasi)
                    row.find('.cfg-dot').css('background', '#10b981');

                    // Kembalikan tombol ke normal setelah 3 detik
                    setTimeout(function() {
                        btn.html('<i class="fas fa-save"></i> Simpan')
                           .css({'background':'','border-color':'','color':''})
                           .addClass('btn-success');
                    }, 3000);
                } else {
                    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                    alert('Gagal: ' + (data.msg || 'Terjadi kesalahan'));
                }
            })
            .catch(function(err) {
                btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                alert('Gagal menyimpan: ' + err);
            });
    });

    // ── CHECKBOX PER-DIVISI: Lewati & Wajib saling eksklusif ────────────────
    // Jika centang Lewati → uncheck Wajib, dan sebaliknya
    $(document).on('change', '.cb-divisi-lewati', function() {
        const sid   = $(this).attr('data-step');
        const row   = $(this).closest('tr');
        if ($(this).is(':checked')) {
            row.find('.cb-divisi-wajib[data-step="' + sid + '"]').prop('checked', false)
               .closest('label').removeClass('active');
            $(this).closest('label').addClass('active');
        } else {
            $(this).closest('label').removeClass('active');
        }
    });

    $(document).on('change', '.cb-divisi-wajib', function() {
        const sid   = $(this).attr('data-step');
        const row   = $(this).closest('tr');
        if ($(this).is(':checked')) {
            row.find('.cb-divisi-lewati[data-step="' + sid + '"]').prop('checked', false)
               .closest('label').removeClass('active');
            $(this).closest('label').addClass('active');
        } else {
            $(this).closest('label').removeClass('active');
        }
    });

    // ── SAVE PER-DIVISI via AJAX ─────────────────────────────────────────────
    $(document).on('click', '.btn-save-divisi', function() {
        const btn       = $(this);
        const id_divisi = (btn.attr('data-divisi') || '').trim();
        const row       = btn.closest('tr');

        if (!id_divisi) {
            alert('Error: id_divisi tidak valid. Coba refresh halaman.');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'save_divisi');
        fd.append('id_divisi', id_divisi);

        // Ambil pilihan cabang dari dropdown di baris yang sama
        const id_cabang = row.find('.sel-dv-cabang').val() || '';
        fd.append('id_cabang', id_cabang);

        row.find('.cb-divisi-lewati').each(function() {
            const sid     = $(this).attr('data-step');
            const isSkip  = $(this).is(':checked');
            const isWajib = row.find('.cb-divisi-wajib[data-step="' + sid + '"]').is(':checked');
            let state = 'none';
            if (isSkip)  state = 'skip';
            if (isWajib) state = 'wajib';
            fd.append('step_state[' + sid + ']', state);
        });

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        fetch('process.php', { method: 'POST', body: fd })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data.ok) {
                    btn.prop('disabled', false)
                       .html('<i class="fas fa-check-circle"></i> Tersimpan!')
                       .removeClass('btn-danger').addClass('btn-success');
                    row.find('.cfg-dot').css('background', '#ec4899');
                    setTimeout(function() {
                        btn.html('<i class="fas fa-save"></i> Simpan')
                           .removeClass('btn-success').addClass('btn-danger');
                    }, 3000);
                } else {
                    btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                    alert('Gagal: ' + (data.msg || 'Terjadi kesalahan'));
                }
            })
            .catch(function(err) {
                btn.prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                alert('Gagal menyimpan: ' + err);
            });
    });

});

</script>
