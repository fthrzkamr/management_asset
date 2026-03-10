<?php

include('../dist/config/config.php');
include('../dist/config/auth_helper.php');
require_once __DIR__ . '/../dist/config/ApprovalHelper.php';
require_once __DIR__ . '/../z_master_budget/MasterBudgetService.php';

$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn() || (!$auth->isManagerFinance() && !$auth->isSuperAdmin())) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
    header("Location: dashboard.php");
    exit();
}
$helper = new ApprovalHelper($conn);

// Terima ID budget dari GET
$id_budget = $_GET['id'] ?? null;

if (!$id_budget) {
    $_SESSION['error'] = 'ID budget tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

// Ambil data budget
$query = "SELECT mb.*, u.nama_user as creator_name, u.id_cabang as creator_id_cabang, u.divisi as creator_id_divisi, mc.nama_cabang as creator_cabang_name
          FROM ppx_monthly_budget mb
          LEFT JOIN user_dfm u ON mb.created_by = u.id_user
          LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
          WHERE mb.id_budget = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id_budget);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Data budget tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Cek status - harus Manager Finance
if ($budget['status'] !== 'Manager Finance') {
    $_SESSION['error'] = 'Budget ini tidak dalam status Manager Finance!';
    header("Location: dashboard.php");
    exit();
}

// Ambil detail item-level (rincian per kategori) — gunakan tabel detail yang sekarang
$items_query = "SELECT d.*, pbc.category_name, pbc.category_code
                FROM ppx_monthly_budget_item_detail d
                LEFT JOIN ppx_budget_category pbc ON d.id_category = pbc.id_category
                WHERE d.id_budget = ?
                ORDER BY pbc.category_name, d.id";

$stmt_items = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt_items, 'i', $id_budget);
mysqli_stmt_execute($stmt_items);
$items_result = mysqli_stmt_get_result($stmt_items);

$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Handle GET langsung (dari tombol di dashboard/detail)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $reject_reason = trim($_GET['reject_reason'] ?? '');

    if ($action === 'approve') {
        $next_status = $helper->getNextStatusFrom('Manager Finance', $budget['creator_id_cabang'], $budget['creator_id_divisi']);
        $stmt_u = mysqli_prepare($conn, "UPDATE ppx_monthly_budget SET status=?, approved_by=?, approved_at=NOW() WHERE id_budget=? AND status='Manager Finance'");
        mysqli_stmt_bind_param($stmt_u, 'sii', $next_status, $_SESSION['id_user'], $id_budget);
        if (mysqli_stmt_execute($stmt_u) && mysqli_stmt_affected_rows($stmt_u) > 0) {
            $_SESSION['success'] = 'Anggaran berhasil disetujui!';
        } else {
            $_SESSION['error'] = 'Gagal menyetujui anggaran: ' . mysqli_error($conn);
        }
        header("Location: dashboard.php");
        exit();

    } elseif ($action === 'reject' && !empty($reject_reason)) {
        $combined_notes = trim($budget['notes'] ?? '') . "\n\nDitolak: " . $reject_reason;
        $stmt_u = mysqli_prepare($conn, "UPDATE ppx_monthly_budget SET status='Rejected', approved_by=?, approved_at=NOW(), notes=? WHERE id_budget=? AND status='Manager Finance'");
        mysqli_stmt_bind_param($stmt_u, 'isi', $_SESSION['id_user'], $combined_notes, $id_budget);
        if (mysqli_stmt_execute($stmt_u) && mysqli_stmt_affected_rows($stmt_u) > 0) {
            $_SESSION['success'] = 'Anggaran berhasil ditolak.';
        } else {
            $_SESSION['error'] = 'Gagal menolak anggaran: ' . mysqli_error($conn);
        }
        header("Location: dashboard.php");
        exit();
    }
    // Jika GET action=reject tapi tanpa reason, jatuh ke tampilkan form di bawah
}

// Handle POST untuk approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $approval_notes = trim($_POST['approval_notes'] ?? '');
    
    // Debug
    error_log("POST received - Action: $action, ID: $id_budget, User: {$_SESSION['id_user']}");
    
    if ($action === 'approve') {
        // Approve budget - ubah status jadi Pending (menunggu realisasi dari Finance)
        // Gabungkan notes lama dengan approval notes
        $combined_notes = trim($budget['notes'] ?? '');
        if (!empty($approval_notes)) {
            $combined_notes .= ($combined_notes ? "\n\n" : "") . "Approved by Manager: " . $approval_notes;
        }
        
        $next_status = $helper->getNextStatusFrom('Manager Finance', $budget['creator_id_cabang'], $budget['creator_id_divisi']);
        
        $update_sql = "UPDATE ppx_monthly_budget SET 
                      status = ?,
                      approved_by = ?,
                      approved_at = NOW(),
                      notes = ?
                      WHERE id_budget = ? AND status = 'Manager Finance'";
        
        $stmt_update = mysqli_prepare($conn, $update_sql);
        
        if ($stmt_update === false) {
            $_SESSION['error'] = 'Error prepare statement: ' . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt_update, 'sisi', $next_status, $_SESSION['id_user'], $combined_notes, $id_budget);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $affected = mysqli_stmt_affected_rows($stmt_update);
                if ($affected > 0) {
                    $_SESSION['success'] = 'Budget berhasil disetujui!';
                    mysqli_stmt_close($stmt_update);
                    mysqli_close($conn);
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $_SESSION['error'] = 'Tidak ada perubahan. Budget sudah disetujui atau status bukan Draft.';
                }
            } else {
                $_SESSION['error'] = 'Gagal execute: ' . mysqli_stmt_error($stmt_update);
            }
            mysqli_stmt_close($stmt_update);
        }
    } elseif ($action === 'reject') {
        // Kembalikan ke status 'Revisi' agar user bisa edit dan submit ulang langsung ke Manager Finance
        if (empty($approval_notes)) {
            $_SESSION['error'] = 'Alasan penolakan harus diisi!';
        } else {
            $note_revisi = trim($budget['notes'] ?? '');
            $note_revisi .= ($note_revisi ? "\n\n" : "") . "⚠ Dikembalikan untuk Revisi oleh Manager: " . $approval_notes;

            $update_sql = "UPDATE ppx_monthly_budget SET 
                          status = 'Revisi',
                          approved_by = ?,
                          approved_at = NOW(),
                          notes = ?
                          WHERE id_budget = ? AND status = 'Manager Finance'";
            
            $stmt_update = mysqli_prepare($conn, $update_sql);
            if ($stmt_update === false) {
                $_SESSION['error'] = 'Error prepare statement: ' . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($stmt_update, 'isi', $_SESSION['id_user'], $note_revisi, $id_budget);
                if (mysqli_stmt_execute($stmt_update) && mysqli_stmt_affected_rows($stmt_update) > 0) {
                    $_SESSION['success'] = 'Anggaran dikembalikan ke pengaju untuk direvisi.';
                    mysqli_stmt_close($stmt_update);
                    mysqli_close($conn);
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $_SESSION['error'] = 'Gagal memproses: ' . mysqli_stmt_error($stmt_update);
                }
                mysqli_stmt_close($stmt_update);
            }
        }
    }
}

$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<?php include_once('../layouts/navbar.php'); ?>

<body>
<div class="main-wrapper">
<div class="page-wrapper">
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Proses Approval Anggaran Bulanan</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Approval Anggaran</a></li>
                        <li class="breadcrumb-item active">Proses Approval</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Budget Info Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h4 class="card-title mb-0 text-white">Informasi Budget</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Periode</th>
                                        <td><?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?></td>
                                    </tr>
                                    <tr>
                                        <th>Nama Budget</th>
                                        <td><?= htmlspecialchars($budget['budget_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Sumber Dana</th>
                                        <td><?= htmlspecialchars($budget['master_id']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td><?= htmlspecialchars($budget['creator_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Cabang</th>
                                        <td><?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Total Alokasi</th>
                                        <td><strong class="text-primary">Rp <?= number_format($budget['total_allocation'], 0, ',', '.') ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td><span class="badge badge-warning"><?= $budget['status'] ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Dibuat</th>
                                        <td><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Catatan</th>
                                        <td><?= htmlspecialchars($budget['notes']) ?: '-' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Items -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Detail Alokasi per Kategori</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="10%">Kode</th>
                                        <th width="25%">Kategori</th>
                                        <th>Rincian</th>
                                        <th width="8%">Qty</th>
                                        <th width="8%">Satuan</th>
                                        <th width="15%">Harga Satuan</th>
                                        <th width="15%">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $items_data = [];
                                    while ($item = mysqli_fetch_assoc($items_result)) {
                                        $items_data[] = $item;
                                    }

                                    if (empty($items_data)): ?>
                                        <tr><td colspan="8" class="text-center">Tidak ada rincian item.</td></tr>
                                    <?php else:
                                        $grouped = [];
                                        foreach ($items_data as $it) {
                                            $grouped[$it['category_name']][] = $it;
                                        }

                                        $grand_total = 0;
                                        foreach ($grouped as $cat_name => $rows): 
                                            $sub_total = 0;
                                    ?>
                                        <tr class="bg-light">
                                            <td colspan="8"><strong>Kategori: <?= htmlspecialchars($cat_name) ?></strong></td>
                                        </tr>
                                        <?php 
                                        $no_item = 1;
                                        foreach ($rows as $r): 
                                            $line_total = $r['total'];
                                            $sub_total += $line_total;
                                            $grand_total += $line_total;
                                        ?>
                                            <tr>
                                                <td><?= $no_item++ ?></td>
                                                <td><?= htmlspecialchars($r['category_code']) ?></td>
                                                <td><?= htmlspecialchars($r['category_name']) ?></td>
                                                <td><?= htmlspecialchars($r['rincian']) ?></td>
                                                <td class="text-center"><?= intval($r['qty']) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($r['satuan']) ?></td>
                                                <td class="text-right">Rp <?= number_format(floatval($r['harga_satuan']), 0, ',', '.') ?></td>
                                                <td class="text-right">Rp <?= number_format($line_total, 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="font-weight-bold">
                                            <td colspan="7" class="text-right">Total <?= htmlspecialchars($cat_name) ?>:</td>
                                            <td class="text-right">Rp <?= number_format($sub_total, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                        <tr class="bg-dark text-white font-weight-bold">
                                            <td colspan="7" class="text-right">GRAND TOTAL:</td>
                                            <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <!-- Tfoot removed as totals are now in tbody -->
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Form -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="card-title mb-0">Proses Approval</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="approvalForm">
                            <div class="form-group">
                                <label>Catatan Approval <span class="text-muted">(Opsional untuk Approve, Wajib untuk Reject)</span></label>
                                <textarea name="approval_notes" id="approval_notes" class="form-control" rows="4" placeholder="Masukkan catatan approval Anda..."></textarea>
                            </div>

                            <div class="form-group">
                                <button type="button" class="btn btn-success btn-sm btn-action" onclick="submitApproval('approve')">
                                    <i class="fas fa-check fa-sm"></i> Setujui Budget
                                </button>
                                <button type="button" class="btn btn-danger btn-sm btn-action" onclick="submitApproval('reject')">
                                    <i class="fas fa-times fa-sm"></i> Tolak Budget
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary btn-sm btn-action">
                                    <i class="fas fa-arrow-left fa-sm"></i> Kembali
                                </a>
                            </div>

                            <input type="hidden" name="action" id="action">
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include_once('../layouts/footer.php'); ?>
    </div>

    
    <script>
    // Script setelah footer (jQuery sudah dimuat)
    function submitApproval(action) {
        const notes = document.getElementById('approval_notes').value.trim();
        
        if (action === 'reject' && notes === '') {
            alert('Catatan wajib diisi untuk menolak budget!');
            return;
        }
        
        let confirmMsg = action === 'approve' 
            ? 'Apakah Anda yakin ingin menyetujui budget ini?' 
            : 'Apakah Anda yakin ingin menolak budget ini?';
        
        // Set action value
        document.getElementById('action').value = action;
        
        // Use SweetAlert or bootstrap modal if available, otherwise use native confirm
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Konfirmasi',
                text: confirmMsg,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('approvalForm').submit();
                }
            });
        } else {
            // Langsung submit tanpa confirm - sudah ada button yang jelas
            document.getElementById('approvalForm').submit();
        }
    }
    </script>
</div>

</body>
</html>
