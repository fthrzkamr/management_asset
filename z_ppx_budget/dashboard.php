<?php

include_once(__DIR__ . '/../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

// Initialize auth helper
$auth = new AuthHelper($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if user has permission to read budget
if (!$auth->hasPermission('ppx_budget', 'read')) {
    $_SESSION['error'] = 'Akses ditolak untuk menu ini!';
    header("Location: ../index.php");
    exit();
}

// Get current user ID
$user_id = $_SESSION['id_user'];
$where_conditions = "mb.status != 'Deleted'"; // Show all except deleted

// Get current user ID
$user_id = $_SESSION['id_user'];

// Apply user-based filtering (Super Admin sees all)
if ($auth->isSuperAdmin()) {
    // Super Admin sees all budgets - no additional filter
} else {
    // Regular users only see budgets they created
    $where_conditions .= " AND mb.created_by = '" . mysqli_real_escape_string($conn, $user_id) . "'";
}

$budget_query = "SELECT 
    mb.id_budget,
    mb.period_year,
    mb.period_month,
    mb.budget_name,
    mb.status,
    mb.created_at,
    mb.file_excel,
    u.nama_user as creator_name,
    u.departemen,
    u.divisi,
    mc.nama_cabang,
    SUM(mbi.allocation) as master_allocation,
    SUM(mbi.remaining) as total_remaining,
    SUM(mbi.allocation - mbi.remaining) as total_used,
    CASE 
        WHEN SUM(mbi.allocation) > 0 
        THEN ROUND((SUM(mbi.allocation - mbi.remaining) / SUM(mbi.allocation) * 100), 2)
        ELSE 0 
    END as usage_percentage
FROM ppx_monthly_budget mb
LEFT JOIN user_dfm u ON mb.created_by = u.id_user
LEFT JOIN divisi creator_div_name ON u.divisi = creator_div_name.id_divisi
LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
LEFT JOIN ppx_monthly_budget_item mbi ON mb.id_budget = mbi.id_budget
WHERE $where_conditions
GROUP BY mb.id_budget, mb.period_year, mb.period_month, mb.budget_name, mb.status, mb.created_at, mb.file_excel, u.nama_user, u.departemen, u.divisi, mc.nama_cabang
ORDER BY mb.period_year DESC, mb.period_month DESC";

$budget_result = mysqli_query($conn, $budget_query);

// Month names
$month_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Anggaran Bulanan</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    
    <style>
        .budget-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .budget-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .budget-header {
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .budget-header:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .budget-body {
            padding: 0 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s ease, opacity 0.3s ease;
            opacity: 0;
            background: transparent;
        }
        
        .budget-body.show {
            max-height: 5000px; /* Increased for many items */
            padding: 15px;
            opacity: 1;
            background: white;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s ease, opacity 0.3s ease 0.1s, background 0.2s ease;
        }
        
        .category-row {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .category-row:last-child {
            border-bottom: none;
        }
        
        .progress-sm {
            height: 8px;
        }
        
        .toggle-icon {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .toggle-icon.rotate {
            transform: rotate(180deg);
        }
        
        /* Prevent layout shift */
        .budget-card {
            will-change: auto;
            position: relative;
            overflow: hidden;
        }
        
        .page-wrapper {
            overflow-x: hidden;
        }
        
        /* Ensure clean collapse */
        .budget-body:not(.show) {
            padding: 0 15px !important;
            background: transparent !important;
            visibility: hidden;
        }
        
        .budget-body.show {
            visibility: visible;
        }
        
        /* Smooth table rendering for large data */
        .budget-body .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .budget-body tbody {
            display: table-row-group;
        }
    </style>
</head>

<?php include_once('../layouts/navbar.php'); ?>

<div class="page-wrapper">
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Dashboard Anggaran Bulanan</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Anggaran Bulanan</li>
                    </ul>
                </div>

            </div>
        </div>

        <!-- Monthly Budget List -->
        <div class="row">
            <div class="col-md-12">
                <?php if (mysqli_num_rows($budget_result) > 0): ?>
                    <?php while ($budget = mysqli_fetch_assoc($budget_result)): ?>
                        <div class="budget-card" data-budget-id="<?= $budget['id_budget'] ?>">
                            <div class="budget-header" onclick="toggleBudget(<?= $budget['id_budget'] ?>)">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?= $month_names[$budget['period_month']] ?> <?= $budget['period_year'] ?>
                                    </h5>
                                    <small><?= htmlspecialchars($budget['budget_name']) ?> | PIC: <?= htmlspecialchars($budget['creator_name']) ?> | Cabang: <?= htmlspecialchars($budget['nama_cabang'] ?? '-') ?></small>
                                </div>
                                <div class="text-right">
                                    <div class="mb-1">
                                        <strong>Rp <?= number_format($budget['master_allocation'], 0, ',', '.') ?></strong>
                                    </div>
                                    <small>Sisa: Rp <?= number_format($budget['total_remaining'], 0, ',', '.') ?></small>
                                    <br>
                                        <?php
                                        $badge_cls = 'badge-info';
                                        if ($budget['status'] === 'Active') {
                                            $badge_cls = $budget['usage_percentage'] > 80 ? 'badge-danger' : ($budget['usage_percentage'] > 50 ? 'badge-warning' : 'badge-success');
                                        } elseif ($budget['status'] === 'Revisi') {
                                            $badge_cls = 'badge-danger';
                                        }
                                        ?>
                                     <small class="badge <?= $badge_cls ?>">
                                        <?php
                                        switch($budget['status']) {
                                            case 'Kacab': echo 'Menunggu Kacab'; break;
                                            case 'Manager Finance': echo 'Menunggu Manager'; break;
                                            case 'Finance (Realisasi)': echo 'Menunggu Realisasi'; break;
                                            case 'Active': echo 'Terpakai: ' . number_format($budget['usage_percentage'], 1) . '%'; break;
                                            case 'Rejected': echo 'Ditolak'; break;
                                            case 'Revisi': echo '⚠ Perlu Revisi'; break;
                                            default: echo $budget['status'];
                                        }
                                        ?>
                                    </small>
                                    <i class="fas fa-chevron-down toggle-icon ml-3" id="icon-<?= $budget['id_budget'] ?>"></i>
                                </div>
                            </div>
                            
                            <div class="budget-body" id="body-<?= $budget['id_budget'] ?>">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <small class="text-muted">Total Alokasi</small>
                                        <h5 class="text-primary">Rp <?= number_format($budget['master_allocation'], 0, ',', '.') ?></h5>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Terpakai</small>
                                        <h5 class="text-danger">Rp <?= number_format($budget['total_used'], 0, ',', '.') ?></h5>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Sisa</small>
                                        <h5 class="text-success">Rp <?= number_format($budget['total_remaining'], 0, ',', '.') ?></h5>
                                    </div>
                                </div>
                                
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar <?= $budget['usage_percentage'] > 80 ? 'bg-danger' : 'bg-primary' ?>" 
                                         style="width: <?= min($budget['usage_percentage'], 100) ?>%">
                                        <?= number_format($budget['usage_percentage'], 1) ?>%
                                    </div>
                                </div>
                                
                                <?php if (!empty($budget['file_excel'])): ?>
                                    <div class="mb-3 d-inline-block mr-2">
                                        <a href="../uploads/ppx_excel/<?= htmlspecialchars($budget['file_excel']) ?>" class="btn btn-sm btn-outline-success" target="_blank" download>
                                            <i class="fas fa-download"></i> Download Lampiran Excel
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3 d-inline-block">
                                    <a href="export_excel.php?id=<?= $budget['id_budget'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-file-excel"></i> Export Laporan ke Excel
                                    </a>
                                </div>
                                
                                <?php if ($budget['status'] === 'Revisi'): ?>
                                    <div class="alert alert-warning border-left border-danger mb-3" style="border-left-width:4px!important">
                                        <strong><i class="fas fa-exclamation-triangle"></i> Anggaran Ini Perlu Revisi</strong><br>
                                        <small>Manager Finance telah mengembalikan anggaran ini. Silakan tinjau catatan di bawah dan ajukan kembali.</small>
                                        <div class="mt-2">
                                            <a href="revisi_submit.php?id=<?= $budget['id_budget'] ?>"
                                               class="btn btn-warning btn-sm">
                                                <i class="fas fa-paper-plane"></i> Submit Ulang ke Manager Finance
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <h6 class="mb-3"><i class="fas fa-list"></i> Detail Per Kategori:</h6>
                                
                                <?php
                                // Get items for this budget
                                $items_query = "SELECT 
                                    mbi.id_budget_item,
                                    mbi.id_category,
                                    mbi.allocation,
                                    (mbi.allocation - mbi.remaining) as used_amount,
                                    mbi.remaining,
                                    bc.category_name,
                                    bc.category_code,
                                    CASE 
                                        WHEN mbi.allocation > 0 
                                        THEN ROUND(((mbi.allocation - mbi.remaining) / mbi.allocation * 100), 2)
                                        ELSE 0 
                                    END as usage_percentage
                                FROM ppx_monthly_budget_item mbi
                                LEFT JOIN ppx_budget_category bc ON mbi.id_category = bc.id_category
                                WHERE mbi.id_budget = ?
                                ORDER BY bc.category_name";
                                
                                $stmt_items = mysqli_prepare($conn, $items_query);
                                mysqli_stmt_bind_param($stmt_items, 'i', $budget['id_budget']);
                                mysqli_stmt_execute($stmt_items);
                                $items_result = mysqli_stmt_get_result($stmt_items);
                                ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="5%">No</th>
                                                <th width="25%">Kategori</th>
                                                <th width="18%">Alokasi</th>
                                                <th width="18%">Terpakai</th>
                                                <th width="18%">Sisa</th>
                                                <th width="16%">Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while ($item = mysqli_fetch_assoc($items_result)): 
                                            ?>
                                                <tr class="category-row">
                                                    <td><small><?= $no++ ?></small></td>
                                                    <td>
                                                        <small><strong><?= htmlspecialchars($item['category_name']) ?></strong></small><br>
                                                        <small class="text-muted"><?= htmlspecialchars($item['category_code']) ?></small>
                                                    </td>
                                                    <td><small>Rp <?= number_format($item['allocation'], 0, ',', '.') ?></small></td>
                                                    <td><small class="text-danger">Rp <?= number_format($item['used_amount'], 0, ',', '.') ?></small></td>
                                                    <td><small class="text-success">Rp <?= number_format($item['remaining'], 0, ',', '.') ?></small></td>
                                                    <td>
                                                        <div class="progress progress-sm">
                                                            <div class="progress-bar <?= $item['usage_percentage'] > 80 ? 'bg-danger' : 'bg-info' ?>" 
                                                                 style="width: <?= min($item['usage_percentage'], 100) ?>%">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?= number_format($item['usage_percentage'], 1) ?>%</small>
                                                    </td>
                                                </tr>
                                                <?php
                                                // Fetch detail lines for this category (rincian)
                                                $details_q = "SELECT rincian, qty, satuan, harga_satuan, total FROM ppx_monthly_budget_item_detail 
                                                             WHERE id_budget = " . intval($budget['id_budget']) . " AND id_budget_item = " . intval($item['id_budget_item']) . " ORDER BY id";
                                                $dres = mysqli_query($conn, $details_q);
                                                if ($dres && mysqli_num_rows($dres) > 0):
                                                ?>
                                                    <tr>
                                                        <td colspan="6" style="background:#fafafa;padding:8px;">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width:5%">#</th>
                                                                            <th>Rincian</th>
                                                                            <th style="width:10%">Qty</th>
                                                                            <th style="width:15%">Satuan</th>
                                                                            <th style="width:20%">Harga Satuan</th>
                                                                            <th style="width:20%">Total</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php $dno = 1; while ($det = mysqli_fetch_assoc($dres)): ?>
                                                                            <tr>
                                                                                <td><small><?= $dno++ ?></small></td>
                                                                                <td><small><?= htmlspecialchars($det['rincian']) ?></small></td>
                                                                                <td><small><?= htmlspecialchars($det['qty']) ?></small></td>
                                                                                <td><small><?= htmlspecialchars($det['satuan']) ?></small></td>
                                                                                <td><small>Rp <?= number_format($det['harga_satuan'],0,',','.') ?></small></td>
                                                                                <td><small>Rp <?= number_format($det['total'],0,',','.') ?></small></td>
                                                                            </tr>
                                                                        <?php endwhile; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada anggaran bulanan yang disetujui</h5>
                            <p class="text-muted">Budget yang sudah diapprove oleh Manager Finance akan muncul di sini</p>

                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include_once('../layouts/footer.php'); ?>

    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function toggleBudget(budgetId) {
    const body = document.getElementById('body-' + budgetId);
    const icon = document.getElementById('icon-' + budgetId);
    const card = body.closest('.budget-card');
    
    if (body.classList.contains('show')) {
        // Closing - reset styles completely
        icon.classList.remove('rotate');
        body.style.maxHeight = '0';
        body.style.padding = '0 15px';
        body.style.opacity = '0';
        body.style.background = 'transparent';
        
        // Remove class after a small delay to ensure animation completes
        setTimeout(() => {
            body.classList.remove('show');
            body.style.removeProperty('max-height');
        }, 500);
    } else {
        // Close all other budgets first with clean reset
        document.querySelectorAll('.budget-body.show').forEach(b => {
            const bIcon = document.querySelector(`#icon-${b.id.replace('body-', '')}`);
            if (bIcon) bIcon.classList.remove('rotate');
            
            b.style.maxHeight = '0';
            b.style.padding = '0 15px';
            b.style.opacity = '0';
            b.style.background = 'transparent';
            
            setTimeout(() => {
                b.classList.remove('show');
                b.style.removeProperty('max-height');
            }, 500);
        });
        
        // Open clicked budget after other closes
        setTimeout(() => {
            // Calculate actual height needed (support large datasets)
            body.style.display = 'block';
            body.style.maxHeight = 'none';
            body.style.visibility = 'hidden';
            const height = body.scrollHeight;
            body.style.visibility = '';
            body.style.maxHeight = '0';
            body.style.display = '';
            
            // Force reflow
            body.offsetHeight;
            
            // Animate open with calculated height
            requestAnimationFrame(() => {
                body.classList.add('show');
                body.style.maxHeight = (height + 50) + 'px'; // Add extra 50px for safety
                body.style.padding = '15px';
                body.style.opacity = '1';
                body.style.background = 'white';
                icon.classList.add('rotate');
                
                // After animation, set to none for dynamic content
                setTimeout(() => {
                    if (body.classList.contains('show')) {
                        body.style.maxHeight = 'none';
                    }
                }, 550);
            });
            
            // Smooth scroll to card if needed
            setTimeout(() => {
                const rect = card.getBoundingClientRect();
                const isVisible = rect.top >= 0 && rect.bottom <= window.innerHeight;
                
                if (!isVisible) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 200);
        }, 100);
    }
}

// Auto open first budget on load
document.addEventListener('DOMContentLoaded', function() {
    // Wait for layout to settle
    setTimeout(() => {
        const firstBudget = document.querySelector('.budget-card');
        if (firstBudget) {
            const budgetId = firstBudget.getAttribute('data-budget-id');
            toggleBudget(budgetId);
        }
    }, 100);
});

// Clean up styles after transition ends
document.querySelectorAll('.budget-body').forEach(body => {
    body.addEventListener('transitionend', function(e) {
        // When fully closed, ensure everything is reset
        if (e.propertyName === 'max-height' && !this.classList.contains('show')) {
            this.style.removeProperty('max-height');
            this.style.removeProperty('padding');
            this.style.removeProperty('opacity');
            this.style.removeProperty('background');
        }
        // When fully open, allow unlimited height
        if (e.propertyName === 'max-height' && this.classList.contains('show')) {
            this.style.maxHeight = 'none';
        }
    });
});
</script>

</body>
</html>
