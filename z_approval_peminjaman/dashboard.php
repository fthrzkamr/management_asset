<?php
require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { header("Location: ../login.php"); exit(); }

// Sementara kita izinkan Administrator (isSuperAdmin) sesuai permintaan user
if (!$auth->isSuperAdmin() && !$auth->hasPermission('approval_peminjaman', 'read')) {
    header("Location: ../index.php");
    exit();
}

/* ───────── 1. Ambil Data Peminjaman ───────── */
$query = "SELECT p.*, m.nama_mobil, m.plat_nomor 
          FROM peminjaman_kendaraan p
          JOIN master_kendaraan m ON p.id_mobil = m.id_mobil
          ORDER BY p.created_at DESC";
$result = mysqli_query($conn, $query);

$peminjaman = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $peminjaman[] = $row;
    }
}

/* ───────── 2. Statistik ───────── */
$cnt_all      = count($peminjaman);
$cnt_pending  = count(array_filter($peminjaman, fn($r) => $r['status_approval'] === 'Pending'));
$cnt_approved = count(array_filter($peminjaman, fn($r) => $r['status_approval'] === 'Disetujui'));
$cnt_rejected = count(array_filter($peminjaman, fn($r) => $r['status_approval'] === 'Ditolak'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Peminjaman Mobil - DF GROUP</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) include $layout_dir . '/header_css.php';
    ?>
    <style>
        .stat-card { border-radius:12px; border:none; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,.05); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .peminjaman-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .s-pending { background: #fff7ed; color: #9a3412; }
        .s-approved { background: #f0fdf4; color: #166534; }
        .s-rejected { background: #fef2f2; color: #991b1b; }
        .s-finished { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; }
        .btn-approve { background: #10b981; color: #fff; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: #fff; }
        .btn-reject:hover { background: #dc2626; }
        .empty-state { padding: 60px; text-align: center; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title"><i class="fa-solid fa-car-side mr-2 text-primary"></i> Approval Peminjaman Mobil</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Peminjaman Mobil</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
                        <i class="fas fa-check-circle mr-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- ── Stats ── -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small uppercase tracking-wider">Total Request</h6>
                                    <h3 class="font-weight-bold mb-0"><?= $cnt_all ?></h3>
                                </div>
                                <div class="bg-primary-light p-3 rounded-circle text-primary">
                                    <i class="fas fa-list fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-white border-left-warning" style="border-left: 4px solid #f59e0b;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small uppercase tracking-wider">Pending</h6>
                                    <h3 class="text-warning font-weight-bold mb-0"><?= $cnt_pending ?></h3>
                                </div>
                                <div class="bg-warning-light p-3 rounded-circle text-warning">
                                    <i class="fas fa-clock fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-white border-left-success" style="border-left: 4px solid #10b981;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small uppercase tracking-wider">Disetujui</h6>
                                    <h3 class="text-success font-weight-bold mb-0"><?= $cnt_approved ?></h3>
                                </div>
                                <div class="bg-success-light p-3 rounded-circle text-success">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-white border-left-danger" style="border-left: 4px solid #ef4444;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small uppercase tracking-wider">Ditolak</h6>
                                    <h3 class="text-danger font-weight-bold mb-0"><?= $cnt_rejected ?></h3>
                                </div>
                                <div class="bg-danger-light p-3 rounded-circle text-danger">
                                    <i class="fas fa-times-circle fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Data Table ── -->
                <div class="peminjaman-table border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0"><i class="fas fa-tasks mr-2 text-primary"></i> Daftar Permintaan Peminjaman</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-center mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Peminjam</th>
                                    <th>Unit Mobil</th>
                                    <th>Keperluan</th>
                                    <th>Waktu Pinjam</th>
                                    <th>Status</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($peminjaman)): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="fas fa-car fa-3x mb-3 opacity-25"></i>
                                                <p>Belum ada data peminjaman kendaraan.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no = 1;
                                    foreach ($peminjaman as $p): 
                                        // Cek apakah waktu sudah habis untuk status Disetujui
                                        $is_expired = ($p['status_approval'] === 'Disetujui' && strtotime($p['tanggal_selesai']) < time());
                                        
                                        // Tentukan status tampilan (Jika expired otomatis dianggap Selesai di UI)
                                        $display_status = $p['status_approval'];
                                        if ($is_expired) {
                                            $display_status = 'Selesai';
                                        }

                                        $status_class = '';
                                        if ($display_status === 'Pending') $status_class = 's-pending';
                                        elseif ($display_status === 'Disetujui') $status_class = 's-approved';
                                        elseif ($display_status === 'Ditolak') $status_class = 's-rejected';
                                        elseif ($display_status === 'Selesai') $status_class = 's-finished';
                                    ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <div class="font-weight-bold text-dark"><?= htmlspecialchars($p['nama_peminjam']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($p['divisi']) ?> | <?= htmlspecialchars($p['no_hp']) ?></small>
                                            </td>
                                            <td>
                                                <div class="font-weight-600"><?= htmlspecialchars($p['nama_mobil']) ?></div>
                                                <small class="badge badge-light border"><?= htmlspecialchars($p['plat_nomor']) ?></small>
                                            </td>
                                            <td style="max-width: 200px;">
                                                <div class="text-truncate" title="<?= htmlspecialchars($p['keperluan']) ?>">
                                                    <?= htmlspecialchars($p['keperluan']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <span class="text-success font-weight-bold">Mulai:</span> <?= date('d/m/Y H:i', strtotime($p['tanggal_mulai'])) ?><br>
                                                    <span class="text-danger font-weight-bold">Selesai:</span> <?= date('d/m/Y H:i', strtotime($p['tanggal_selesai'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pill <?= $status_class ?>"><?= $display_status ?></span>
                                            </td>
                                            <td class="text-right">
                                                <?php if ($p['status_approval'] === 'Pending'): ?>
                                                    <div class="d-flex justify-content-end" style="gap: 5px;">
                                                        <form action="action.php" method="POST">
                                                            <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="button" class="btn-action btn-approve btn-confirm-submit" data-message="Setujui peminjaman kendaraan ini?">
                                                                <i class="fas fa-check mr-1"></i> Approve
                                                            </button>
                                                        </form>
                                                        <form action="action.php" method="POST">
                                                            <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="button" class="btn-action btn-reject btn-confirm-submit" data-message="Tolak peminjaman kendaraan ini?">
                                                                <i class="fas fa-times mr-1"></i> Reject
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php elseif ($p['status_approval'] === 'Disetujui' && !$is_expired): ?>
                                                    <div class="d-flex justify-content-end">
                                                        <form action="action.php" method="POST">
                                                            <input type="hidden" name="id_peminjaman" value="<?= $p['id_peminjaman'] ?>">
                                                            <input type="hidden" name="action" value="return">
                                                            <button type="button" class="btn-action btn-confirm-submit" 
                                                                    style="background: #6366f1; color: white;"
                                                                    data-message="Tandai kendaraan ini sudah dikembalikan?">
                                                                <i class="fas fa-undo mr-1"></i> Kembalikan
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small italic">Selesai</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../layouts/footer.php'; ?>
    </div>

    <script>
    $(document).ready(function() {
        // Handle custom confirmation modal for Approve/Reject
        $('.btn-confirm-submit').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var message = $btn.data('message');
            var $form = $btn.closest('form');

            // showConfirm is defined in layouts/confirm_modal.php (included via footer)
            if (typeof showConfirm === 'function') {
                showConfirm(message, function() {
                    $form.submit();
                });
            } else {
                // Fallback in case modal script didn't load
                if (confirm(message)) {
                    $form.submit();
                }
            }
        });
    });
    </script>
</body>
</html>
