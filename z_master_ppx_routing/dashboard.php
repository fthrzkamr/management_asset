<?php
// filepath: z_master_ppx_routing/dashboard.php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }
$auth->requirePermission('ppx_routing', 'read');

// Cek kolom request_type (dengan SHOW COLUMNS agar lebih robust)
$col_chk = mysqli_query($conn, "SHOW COLUMNS FROM ppx_approval_routing LIKE 'request_type'");
$has_rt = ($col_chk && mysqli_num_rows($col_chk) > 0);
$sel_rt  = $has_rt ? ', par.request_type' : ', NULL AS request_type';

// Ambil semua routing
$rows = mysqli_query($conn,
    "SELECT par.id, par.divisi_id, d.nama_divisi, par.cabang,
            par.initial_status, par.final_status, par.approver_role, par.skip_leader, par.is_active $sel_rt
     FROM ppx_approval_routing par
     LEFT JOIN divisi d ON par.divisi_id = d.id_divisi
     ORDER BY d.nama_divisi, par.cabang,
         FIELD(par.initial_status,'Leader','Kacab','HR','Asmen Finance','Manager','Operasional','Finance','Direktur')");
if (!$rows) $rows_arr = [];
else { $rows_arr = []; while($r = mysqli_fetch_assoc($rows)) $rows_arr[] = $r; }

// Ambil daftar divisi
$divisi_list = [];
$dq = mysqli_query($conn, "SELECT id_divisi, nama_divisi FROM divisi ORDER BY nama_divisi");
while($d = mysqli_fetch_assoc($dq)) $divisi_list[] = $d;

// Ambil daftar roles
$roles_list = [];
$rq = mysqli_query($conn, "SELECT nama_role FROM roles ORDER BY nama_role");
while($r = mysqli_fetch_assoc($rq)) $roles_list[] = $r['nama_role'];

// Daftar cabang aktif
$cabang_list = [];
$cq = mysqli_query($conn, "SELECT nama_cabang FROM master_cabang ORDER BY nama_cabang");
if ($cq) while($c = mysqli_fetch_assoc($cq)) $cabang_list[] = $c['nama_cabang'];

$status_options = ['Leader','Kacab','HR','Asmen Finance','Manager','Operasional','Finance','Direktur'];
$status_colors  = [
    'Leader'=>'primary','Kacab'=>'purple','HR'=>'pink','Asmen Finance'=>'orange',
    'Manager'=>'warning','Operasional'=>'teal','Finance'=>'info','Direktur'=>'danger',
];
$role_labels = [
    'koordinator'=>'Koordinator','kacab'=>'Kepala Cabang','hr'=>'HR','it'=>'IT',
    'asmen_finance'=>'Asmen Finance','GA'=>'General Affairs','manag_finance'=>'Manager Finance',
    'manag_operasional'=>'Manager Operasional','finance'=>'Finance','finance_ap'=>'Finance AP',
    'direktur'=>'Direktur','gudang'=>'Gudang','super_admin'=>'Super Admin',
];

$success = $_SESSION['success'] ?? ''; $error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$layout_dir = realpath(__DIR__ . '/../layouts');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Routing Approval PPX</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        .badge-purple { background:#8b5cf6; color:#fff; }
        .badge-pink   { background:#ec4899; color:#fff; }
        .badge-orange { background:#f97316; color:#fff; }
        .badge-teal   { background:#14b8a6; color:#fff; }
        .badge-sm     { font-size:0.72rem; padding:3px 7px; border-radius:10px; }
        .tbl-action   { white-space:nowrap; }
        .info-migrate { background:#fffbeb; border-left:4px solid #f59e0b; padding:10px 14px; border-radius:4px; margin-bottom:16px; font-size:13px; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <?php if ($layout_dir) include $layout_dir . '/navbar.php'; ?>
    <div class="page-wrapper">
        <div class="content container-fluid">

            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-1">
                            <i class="fas fa-route text-primary"></i> Routing Approval PPX
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Routing PPX</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalRouting" onclick="openAdd()">
                            <i class="fas fa-plus fa-sm"></i> Tambah Routing
                        </button>
                        <?php if (!$has_rt): ?>
                        <a href="migrate_request_type.php" class="btn btn-warning btn-sm ml-1" title="Jalankan migration untuk aktifkan kolom Tipe PPX">
                            <i class="fas fa-database fa-sm"></i> Jalankan Migration
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($success): ?><div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger  alert-dismissible"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div><?php endif; ?>

            <?php if (!$has_rt): ?>
            <div class="info-migrate">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <strong>Kolom "Tipe PPX" belum ada.</strong>
                Klik tombol <strong>"Jalankan Migration"</strong> di atas (sekali saja) agar kolom tipe PPX aktif
                dan routing Asmen Finance otomatis di-insert.
                Setelah migration, routing bisa dibedakan antara <em>PPX Regular</em> dan <em>PPX Anggaran Bulanan</em>.
            </div>
            <?php endif; ?>

            <!-- TABEL ROUTING -->
            <div class="card">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list-ul fa-sm"></i> Daftar Routing
                        <small class="text-muted ml-2" style="font-weight:normal; font-size:12px;">
                            — Atur siapa yang approve pertama kali untuk setiap divisi & cabang
                        </small>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="routingTable" class="table table-hover table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th width="3%">#</th>
                                    <th>Divisi</th>
                                    <th>Cabang</th>
                                    <th>Tipe PPX</th>
                                    <th>Awal (Status Pertama)</th>
                                    <th>Akhir (Status Final)</th>
                                    <th>Role DB (Approve Awal)</th>
                                    <th width="8%">Aktif</th>
                                    <th width="10%" class="tbl-action">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($rows_arr)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada routing. Klik "Tambah Routing" untuk mulai.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows_arr as $i => $r): ?>
                                <?php
                                    $sc = $status_colors[$r['initial_status']] ?? 'secondary';
                                    $rt = $r['request_type'] ?? null;
                                    $rt_badge = $rt === 'Regular'
                                        ? '<span class="badge badge-success badge-sm">Regular</span>'
                                        : ($rt === 'Monthly_Budget'
                                            ? '<span class="badge badge-info badge-sm">Anggaran Bulanan</span>'
                                            : '<span class="badge badge-secondary badge-sm">Semua</span>');
                                    $role_label = $role_labels[$r['approver_role']] ?? $r['approver_role'];
                                ?>
                                <tr>
                                    <td><small><?= $i+1 ?></small></td>
                                    <td><small><strong><?= htmlspecialchars($r['nama_divisi'] ?: '—') ?></strong></small></td>
                                    <td><small><?= $r['cabang'] ? htmlspecialchars($r['cabang']) : '<span class="text-muted font-italic">Semua Cabang</span>' ?></small></td>
                                    <td><?= $rt_badge ?></td>
                                    <td>
                                        <span class="badge badge-<?= strpos('purple pink orange teal', $sc) !== false ? '' : $sc ?> <?= in_array($sc,['purple','pink','orange','teal']) ? 'badge-'.$sc : '' ?> badge-sm">
                                            <?= htmlspecialchars($r['initial_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($r['final_status']): ?>
                                            <span class="badge badge-<?= strpos('purple pink orange teal', ($status_colors[$r['final_status']] ?? 'secondary')) !== false ? '' : ($status_colors[$r['final_status']] ?? 'secondary') ?> <?= in_array(($status_colors[$r['final_status']] ?? 'secondary'),['purple','pink','orange','teal']) ? 'badge-'.($status_colors[$r['final_status']] ?? 'secondary') : '' ?> badge-sm">
                                                <?= htmlspecialchars($r['final_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted font-italic"><small>Default / Lanjut</small></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($role_label) ?></small></td>
                                    <td>
                                        <?php if ($r['is_active']): ?>
                                            <span class="badge badge-success badge-sm"><i class="fas fa-check fa-xs"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary badge-sm"><i class="fas fa-times fa-xs"></i> Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tbl-action">
                                        <button class="btn btn-xs btn-outline-primary" title="Edit"
                                            onclick='openEdit(<?= json_encode($r) ?>)'>
                                            <i class="fas fa-edit fa-xs"></i>
                                        </button>
                                        <a href="process.php?action=delete&id=<?= $r['id'] ?>"
                                           class="btn btn-xs btn-outline-danger btn-delete-confirm"
                                           data-message="Hapus routing untuk divisi <?= htmlspecialchars($r['nama_divisi']) ?> di cabang <?= htmlspecialchars($r['cabang'] ?: 'Semua') ?>?"
                                           title="Hapus">
                                            <i class="fas fa-trash fa-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PANDUAN -->
            <div class="card mt-3">
                <div class="card-header py-2"><h6 class="card-title mb-0"><i class="fas fa-lightbulb fa-sm text-warning"></i> Panduan Tipe PPX</h6></div>
                <div class="card-body py-2">
                    <div class="row" style="font-size:13px;">
                        <div class="col-md-4 mb-1">
                            <span class="badge badge-secondary badge-sm">Semua</span>
                            — Berlaku untuk <strong>semua</strong> tipe PPX dari divisi tersebut
                        </div>
                        <div class="col-md-4 mb-1">
                            <span class="badge badge-success badge-sm">Regular</span>
                            — Hanya berlaku untuk PPX <strong>tanpa anggaran bulanan</strong>
                        </div>
                        <div class="col-md-4 mb-1">
                            <span class="badge badge-info badge-sm">Anggaran Bulanan</span>
                            — Hanya berlaku untuk PPX <strong>dengan anggaran bulanan</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <p class="mb-0 text-muted" style="font-size:12px;">
                        <i class="fas fa-info-circle"></i>
                        Jika ada dua routing untuk divisi yang sama — satu Semua dan satu Regular — maka rule yang lebih
                        spesifik (Regular / Anggaran Bulanan) akan <strong>diprioritaskan</strong>.
                        Contoh: untuk Divisi Finance AP di Jakarta, buat dua routing: satu <em>Semua</em> (ke HR) dan
                        satu <em>Regular</em> (ke Asmen Finance). Maka PPX Regular ke Asmen, PPX Anggaran ke HR.
                    </p>
                </div>
            </div>

        <?php if ($layout_dir) include $layout_dir . '/footer.php'; ?>

<!-- MODAL ADD/EDIT -->
<div class="modal fade" id="modalRouting" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formRouting" method="POST" action="process.php">
                <input type="hidden" name="action" id="frm_action" value="add">
                <input type="hidden" name="id"     id="frm_id"     value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Routing</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label class="font-weight-bold small">Divisi <span class="text-danger">*</span></label>
                        <select name="divisi_id" id="frm_divisi" class="form-control form-control-sm" required>
                            <option value="">— Pilih Divisi —</option>
                            <?php foreach ($divisi_list as $d): ?>
                            <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold small">Cabang</label>
                        <select name="cabang" id="frm_cabang" class="form-control form-control-sm">
                            <option value="">Semua Cabang (tidak spesifik)</option>
                            <?php foreach ($cabang_list as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Kosongkan jika berlaku untuk semua cabang</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold small">Tipe PPX</label>
                        <select name="request_type" id="frm_rt" class="form-control form-control-sm" <?= !$has_rt ? 'disabled title="Jalankan migration dulu"' : '' ?>>
                            <option value="">Semua Tipe</option>
                            <option value="Regular">Regular (Tanpa Anggaran Bulanan)</option>
                            <option value="Monthly_Budget">Anggaran Bulanan</option>
                        </select>
                        <?php if (!$has_rt): ?>
                        <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Jalankan migration dulu untuk mengaktifkan fitur ini</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold small">Tahap Approval Pertama <span class="text-danger">*</span></label>
                        <select name="initial_status" id="frm_status" class="form-control form-control-sm" required>
                            <option value="">— Pilih Status Awal —</option>
                            <?php foreach ($status_options as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Siapa yang pertama menerima dan approve PPX ini?</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold small">Tahap Approval Akhir (Final)</label>
                        <select name="final_status" id="frm_final_status" class="form-control form-control-sm">
                            <option value="">— Tidak Di-set (Default/Lanjut) —</option>
                            <?php foreach ($status_options as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                            <option value="Selesai">Selesai</option>
                        </select>
                        <small class="text-muted">Opsional. Di tahap apa persetujuan akan berhenti menjadi Selesai?</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold small">Role yang Approve <span class="text-danger">*</span></label>
                        <select name="approver_role" id="frm_role" class="form-control form-control-sm" required>
                            <option value="">— Pilih Role —</option>
                            <?php foreach ($roles_list as $role): ?>
                            <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role_labels[$role] ?? $role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small">Status</label>
                                <select name="is_active" id="frm_active" class="form-control form-control-sm">
                                    <option value="1">✅ Aktif</option>
                                    <option value="0">❌ Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small">Skip Leader?</label>
                                <select name="skip_leader" id="frm_skip" class="form-control form-control-sm">
                                    <option value="0">Tidak</option>
                                    <option value="1">Ya, skip leader</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save fa-xs"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($layout_dir) include $layout_dir . '/confirm_modal.php'; ?>

<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function(){
    $('#routingTable').DataTable({
        ordering: false, pageLength: 25,
        language: {
            search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
            info: '_START_-_END_ dari _TOTAL_', zeroRecords: 'Tidak ada data',
            paginate: { previous: '«', next: '»' }
        }
    });
});

function openAdd() {
    $('#modalTitle').text('Tambah Routing');
    $('#frm_action').val('add');
    $('#frm_id').val('');
    $('#formRouting')[0].reset();
}

function openEdit(row) {
    $('#modalTitle').text('Edit Routing — ' + (row.nama_divisi || '') + ' / ' + (row.cabang || 'Semua'));
    $('#frm_action').val('edit');
    $('#frm_id').val(row.id);
    $('#frm_divisi').val(row.divisi_id);
    $('#frm_cabang').val(row.cabang || '');
    $('#frm_rt').val(row.request_type || '');
    $('#frm_status').val(row.initial_status);
    $('#frm_final_status').val(row.final_status || '');
    $('#frm_role').val(row.approver_role);
    $('#frm_active').val(row.is_active);
    $('#frm_skip').val(row.skip_leader);
    $('#modalRouting').modal('show');
}
</script>
</body>
</html>