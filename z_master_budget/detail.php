<?php
/**
 * Master Budget Detail & Transaction Log
 * Menampilkan detail master budget dan history transaksi
 */

// Start session jika belum

require_once __DIR__ . '/../dist/config/config.php';
require_once __DIR__ . '/../dist/config/auth_helper.php';
require_once __DIR__ . '/MasterBudgetService.php';

// Initialize auth helper
$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Cek permission
$auth->requirePermission('master_budget', 'read');

$master_id = $_GET['id'] ?? '';

if (empty($master_id)) {
    header('Location: dashboard.php?error=' . urlencode('Master ID tidak valid'));
    exit();
}

// Initialize service
$budgetService = new MasterBudgetService($conn);

// Get master budget detail
$master = $budgetService->getMasterBudget($master_id);

if (!$master) {
    header('Location: dashboard.php?error=' . urlencode('Master Budget tidak ditemukan'));
    exit();
}

// Get transaction log
$transactions = $budgetService->getTransactionLog($master_id, 100);

// Calculate statistics
$usage_percentage = $master['amount_in'] > 0 ? ($master['amount_out'] / $master['amount_in'] * 100) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<?php include_once('../layouts/navbar.php'); ?>

<div class="page-wrapper">
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Detail Master Budget</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="dashboard.php">Master Anggaran</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($master_id) ?></li>
                    </ul>
                </div>
                <div class="col-auto float-right ml-auto">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <?php if ($auth->hasPermission('master_budget', 'create')): ?>
                    <button class="btn btn-success" data-toggle="modal" data-target="#topupModal">
                        <i class="fas fa-plus-circle"></i> Top-Up
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Master Budget Info -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Informasi Master Budget</h4>
                    </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="200">Master ID</th>
                                <td><strong><?= htmlspecialchars($master['master_id']) ?></strong></td>
                            </tr>
                            <tr>
                                <th>Tanggal Dibuat</th>
                                <td><?= date('d F Y H:i', strtotime($master['tanggal'])) ?></td>
                            </tr>
                            <tr>
                                <th>Dibuat Oleh</th>
                                <td><?= htmlspecialchars($master['created_by']) ?></td>
                            </tr>
                            <tr>
                                <th>Deskripsi</th>
                                <td><?= nl2br(htmlspecialchars($master['description'])) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">Total Pemasukan</h6>
                                <h3 class="text-primary">Rp <?= number_format($master['amount_in'], 0, ',', '.') ?></h3>
                                
                                <h6 class="text-muted mt-3">Total Pengeluaran</h6>
                                <h3 class="text-danger">Rp <?= number_format($master['amount_out'], 0, ',', '.') ?></h3>
                                
                                <h6 class="text-muted mt-3">Saldo Tersedia</h6>
                                <h3 class="text-success">Rp <?= number_format($master['balance'], 0, ',', '.') ?></h3>
                                
                                <div class="mt-3">
                                    <label>Persentase Terpakai</label>
                                    <?php
                                    $bar_class = 'bg-success';
                                    if ($usage_percentage > 80) $bar_class = 'bg-danger';
                                    elseif ($usage_percentage > 60) $bar_class = 'bg-warning';
                                    ?>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar <?= $bar_class ?>" role="progressbar" 
                                             style="width: <?= $usage_percentage ?>%">
                                            <strong><?= number_format($usage_percentage, 2) ?>%</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transaction Log -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Riwayat Transaksi</h4>
                    </div>
                    <div class="card-body">
                <div class="table-responsive">
                    <table id="transactionTable" class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th>Referensi</th>
                                <th>Jumlah</th>
                                <th>Saldo Sebelum</th>
                                <th>Saldo Sesudah</th>
                                <th>Catatan</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($transactions as $trx): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                                <td class="text-center">
                                    <?php if ($trx['type'] == 'IN'): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-arrow-down"></i> IN
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">
                                            <i class="fas fa-arrow-up"></i> OUT
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($trx['reference_type']) ?></small><br>
                                    <strong><?= htmlspecialchars($trx['reference_id']) ?></strong>
                                </td>
                                <td class="text-right">
                                    <?php if ($trx['type'] == 'IN'): ?>
                                        <span class="text-success">
                                            +Rp <?= number_format($trx['amount'], 0, ',', '.') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger">
                                            -Rp <?= number_format($trx['amount'], 0, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">Rp <?= number_format($trx['balance_before'], 0, ',', '.') ?></td>
                                <td class="text-right">Rp <?= number_format($trx['balance_after'], 0, ',', '.') ?></td>
                                <td><small><?= htmlspecialchars($trx['note']) ?></small></td>
                                <td><?= htmlspecialchars($trx['created_by']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <!-- Modal: Top-Up -->
    <div class="modal fade" id="topupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="master_topup.php" method="POST">
                    <input type="hidden" name="master_id" value="<?= htmlspecialchars($master_id) ?>">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Top-Up Dana</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Master ID:</strong> <?= htmlspecialchars($master_id) ?><br>
                            <strong>Saldo Saat Ini:</strong> Rp <?= number_format($master['balance'], 0, ',', '.') ?>
                        </div>
                        
                        <div class="form-group">
                            <label>Jumlah Top-Up <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" required min="0" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label>Catatan</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Top-Up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#transactionTable').DataTable({
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
                "language": {
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Tidak ada transaksi",
                    "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                    "infoEmpty": "Tidak ada data tersedia",
                    "infoFiltered": "(difilter dari _MAX_ total data)",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "&raquo;",
                        "previous": "&laquo;"
                    }
                },
                "order": [[1, 'desc']] // Sort by tanggal descending
            });
        });
    </script>

<?php include '../layouts/footer.php'; ?>
