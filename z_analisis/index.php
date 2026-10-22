<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_analisis\index.php
include('../dist/config/config.php');
// tambahkan ini langsung setelah include untuk menampilkan error saat development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Filter Cabang IT
$filter_cabang_it = isset($_GET['cabang_it']) ? mysqli_real_escape_string($conn, $_GET['cabang_it']) : '';
$where_it = "";
$select_lokasi_it = "'Semua Cabang' as lokasi";
$group_by_it = "GROUP BY kategori_aset";

if (!empty($filter_cabang_it)) {
    $where_it = " WHERE lokasi LIKE '%$filter_cabang_it%' ";
    $select_lokasi_it = "lokasi";
    $group_by_it = "GROUP BY kategori_aset, lokasi";
}

// Filter Cabang GA
$filter_cabang_ga = isset($_GET['cabang_ga']) ? mysqli_real_escape_string($conn, $_GET['cabang_ga']) : '';
$where_ga = "";
$select_lokasi_ga = "'Semua Cabang' as lokasi";
$group_by_ga = "GROUP BY kategori_aset";

if (!empty($filter_cabang_ga)) {
    $where_ga = " WHERE lokasi LIKE '%$filter_cabang_ga%' ";
    $select_lokasi_ga = "lokasi";
    $group_by_ga = "GROUP BY kategori_aset, lokasi";
}

// Ambil daftar cabang untuk filter dropdown
$cabang_list = [];
$q_cabg = mysqli_query($conn, "SELECT nama_cabang FROM master_cabang ORDER BY id_cabang ASC");
if($q_cabg) {
    while($row_c = mysqli_fetch_assoc($q_cabg)) {
        $cabang_list[] = $row_c['nama_cabang'];
    }
}

// Helper untuk Query agar tidak redundan
$sql_select_tpl = "
    kategori_aset, %s, COUNT(*) as jumlah_aset,
    SUM(total_nilai_pengadaan) as total_nilai,
    AVG(nilai_pengadaan_satuan) as rata_satuan,
    SUM(CASE WHEN informasi_barang = 'Baru' OR kondisi = 'Baru' THEN 1 ELSE 0 END) as baru,
    SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as baik,
    SUM(CASE WHEN informasi_barang = 'Second' OR kondisi = 'Second' THEN 1 ELSE 0 END) as second,
    SUM(CASE WHEN kondisi = 'Rusak Ringan' THEN 1 ELSE 0 END) as rusak_ringan,
    SUM(CASE WHEN kondisi = 'Rusak Berat' THEN 1 ELSE 0 END) as rusak_berat
";

// Aset per kategori - Departemen IT
$it_select = str_replace('%s', $select_lokasi_it, $sql_select_tpl);
$it_query = "SELECT $it_select FROM asset_dfm $where_it $group_by_it ORDER BY jumlah_aset DESC";
$assets_by_category_it = mysqli_query($conn, $it_query);
if (!$assets_by_category_it) {
    error_log("Query IT Error: " . mysqli_error($conn));
}

// Aset per kategori - Departemen GA
$ga_select = str_replace('%s', $select_lokasi_ga, $sql_select_tpl);
$ga_query = "SELECT $ga_select FROM asset_ga $where_ga $group_by_ga ORDER BY jumlah_aset DESC";
$assets_by_category_ga = mysqli_query($conn, $ga_query);
if (!$assets_by_category_ga) {
    error_log("Query GA Error: " . mysqli_error($conn));
}

// Definisi Konfigurasi Progress Bar
$status_config = [
    'baik' => ['label' => 'Baik', 'color' => 'bg-success', 'short' => 'B'],
    'rusak_ringan' => ['label' => 'Rusak Ringan', 'color' => 'bg-warning text-dark', 'short' => 'RR'],
    'rusak_berat' => ['label' => 'Rusak Berat', 'color' => 'bg-danger', 'short' => 'RB']
];

$acq_config = [
    'baru' => ['label' => 'Baru', 'color' => 'bg-info', 'short' => 'BR'],
    'second' => ['label' => 'Second', 'color' => 'bg-secondary', 'short' => 'S']
];
?>
<!DOCTYPE html>
<html lang="en">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row">
                        <div class="col-sm-12">
                            <h3 class="page-title">
                                <i class="fas fa-chart-pie"></i> Dashboard Aset
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard Aset</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <!-- Category Table - IT -->
                        <div class="card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-table"></i> Detail Aset - Departemen IT
                                </h5>
                                <form method="GET" action="" class="form-inline ml-auto">
                                     <!-- Simpan status filter GA agar tidak hilang saat filter IT diubah -->
                                     <input type="hidden" name="cabang_ga" value="<?= htmlspecialchars($filter_cabang_ga) ?>">
                                     <select name="cabang_it" class="form-control form-control-sm border-0" style="background: rgba(255,255,255,0.9); font-weight: 500;" onchange="this.form.submit()">
                                         <option value="">Filter Cabang (IT)</option>
                                         <?php foreach($cabang_list as $cbg): ?>
                                             <option value="<?= htmlspecialchars($cbg) ?>" <?= $filter_cabang_it === $cbg ? 'selected' : '' ?>>
                                                 <?= htmlspecialchars($cbg) ?>
                                             </option>
                                         <?php endforeach; ?>
                                     </select>
                                 </form>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Kategori</th>
                                                <th>Cabang</th>
                                                <th class="text-center">Jumlah</th>
                                                <th class="text-center" style="width: 25%;">Status Kesehatan (%)</th>
                                                <th class="text-center" style="width: 20%;">Kondisi Barang (%)</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($assets_by_category_it && mysqli_num_rows($assets_by_category_it) > 0) {
                                                mysqli_data_seek($assets_by_category_it, 0);
                                                while ($row = mysqli_fetch_assoc($assets_by_category_it)):
                                            ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../z_tambah_asset/dashboard.php?search=<?= urlencode($row['kategori_aset']) ?>&cabang=<?= urlencode($row['lokasi']) ?>" class="text-info">
                                                                <strong><i class="fas fa-folder-open mr-1"></i> <?= htmlspecialchars($row['kategori_aset']) ?></strong>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($row['lokasi']) ?></td>
                                                        <td class="text-center">
                                                            <span class="badge badge-info"><?= number_format($row['jumlah_aset']) ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 14px; font-size: 8px; font-weight: bold;">
                                                                <?php 
                                                                $total = $row['jumlah_aset'];
                                                                foreach($status_config as $key => $cfg): 
                                                                    $count = $row[$key];
                                                                    if($count > 0):
                                                                        $perc = ($count / $total) * 100;
                                                                ?>
                                                                    <div class="progress-bar <?= $cfg['color'] ?>" role="progressbar" 
                                                                         style="width: <?= $perc ?>%" 
                                                                         title="<?= $cfg['label'] ?>: <?= $count ?> (<?= round($perc, 1) ?>%)">
                                                                         <?= round($perc) ?>%
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; ?>
                                                            </div>
                                                            <div class="d-flex flex-wrap mt-1" style="font-size: 8px; color: #666; gap: 3px;">
                                                                <?php foreach($status_config as $key => $cfg): 
                                                                    if($row[$key] > 0): ?>
                                                                    <span><b><?= $cfg['short'] ?></b>:<?= $row[$key] ?></span>
                                                                <?php endif; endforeach; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 14px; font-size: 8px; font-weight: bold;">
                                                                <?php 
                                                                foreach($acq_config as $key => $cfg): 
                                                                    $count = $row[$key];
                                                                    if($count > 0):
                                                                        $perc = ($count / $total) * 100;
                                                                ?>
                                                                    <div class="progress-bar <?= $cfg['color'] ?>" role="progressbar" 
                                                                         style="width: <?= $perc ?>%" 
                                                                         title="<?= $cfg['label'] ?>: <?= $count ?> (<?= round($perc, 1) ?>%)">
                                                                         <?= round($perc) ?>%
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; ?>
                                                            </div>
                                                            <div class="d-flex flex-wrap mt-1" style="font-size: 8px; color: #666; gap: 3px;">
                                                                <?php foreach($acq_config as $key => $cfg): 
                                                                    if($row[$key] > 0): ?>
                                                                    <span><b><?= $cfg['short'] ?></b>:<?= $row[$key] ?></span>
                                                                <?php endif; endforeach; ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-outline-info view-detail" 
                                                                    data-kategori="<?= htmlspecialchars($row['kategori_aset']) ?>" 
                                                                    data-cabang="<?= htmlspecialchars($row['lokasi']) ?>" 
                                                                    data-dept="it">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                            <?php
                                                endwhile;
                                            } else {
                                                echo '<tr><td colspan="4" class="text-center text-muted">Tidak ada data untuk Departemen IT</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Category Table - GA -->
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-table"></i> Detail Aset - Departemen GA
                                </h5>
                                <form method="GET" action="" class="form-inline ml-auto">
                                    <!-- Simpan status filter IT agar tidak hilang saat filter GA diubah -->
                                    <input type="hidden" name="cabang_it" value="<?= htmlspecialchars($filter_cabang_it) ?>">
                                    <select name="cabang_ga" class="form-control form-control-sm border-0" style="background: rgba(255,255,255,0.9); font-weight: 500;" onchange="this.form.submit()">
                                        <option value="">Filter Cabang (GA)</option>
                                        <?php foreach($cabang_list as $cbg): ?>
                                            <option value="<?= htmlspecialchars($cbg) ?>" <?= $filter_cabang_ga === $cbg ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cbg) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Kategori</th>
                                                <th>Cabang</th>
                                                <th class="text-center">Jumlah</th>
                                                <th class="text-center" style="width: 25%;">Status Kesehatan (%)</th>
                                                <th class="text-center" style="width: 20%;">Informasi Pembelian (%)</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($assets_by_category_ga && mysqli_num_rows($assets_by_category_ga) > 0) {
                                                mysqli_data_seek($assets_by_category_ga, 0);
                                                while ($row = mysqli_fetch_assoc($assets_by_category_ga)):
                                            ?>
                                                    <tr>
                                                        <td>
                                                            <a href="../z_management_asset_ga/dashboard.php?search=<?= urlencode($row['kategori_aset']) ?>&cabang=<?= urlencode($row['lokasi']) ?>" class="text-success">
                                                                <strong><i class="fas fa-folder-open mr-1"></i> <?= htmlspecialchars($row['kategori_aset']) ?></strong>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars($row['lokasi']) ?></td>
                                                        <td class="text-center">
                                                            <span class="badge badge-success"><?= number_format($row['jumlah_aset']) ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 14px; font-size: 8px; font-weight: bold;">
                                                                <?php 
                                                                $total = $row['jumlah_aset'];
                                                                foreach($status_config as $key => $cfg): 
                                                                    $count = $row[$key];
                                                                    if($count > 0):
                                                                        $perc = ($count / $total) * 100;
                                                                ?>
                                                                    <div class="progress-bar <?= $cfg['color'] ?>" role="progressbar" 
                                                                         style="width: <?= $perc ?>%" 
                                                                         title="<?= $cfg['label'] ?>: <?= $count ?> (<?= round($perc, 1) ?>%)">
                                                                         <?= round($perc) ?>%
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; ?>
                                                            </div>
                                                            <div class="d-flex flex-wrap mt-1" style="font-size: 8px; color: #666; gap: 3px;">
                                                                <?php foreach($status_config as $key => $cfg): 
                                                                    if($row[$key] > 0): ?>
                                                                    <span><b><?= $cfg['short'] ?></b>:<?= $row[$key] ?></span>
                                                                <?php endif; endforeach; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 14px; font-size: 8px; font-weight: bold;">
                                                                <?php 
                                                                foreach($acq_config as $key => $cfg): 
                                                                    $count = $row[$key];
                                                                    if($count > 0):
                                                                        $perc = ($count / $total) * 100;
                                                                ?>
                                                                    <div class="progress-bar <?= $cfg['color'] ?>" role="progressbar" 
                                                                         style="width: <?= $perc ?>%" 
                                                                         title="<?= $cfg['label'] ?>: <?= $count ?> (<?= round($perc, 1) ?>%)">
                                                                         <?= round($perc) ?>%
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; ?>
                                                            </div>
                                                            <div class="d-flex flex-wrap mt-1" style="font-size: 8px; color: #666; gap: 3px;">
                                                                <?php foreach($acq_config as $key => $cfg): 
                                                                    if($row[$key] > 0): ?>
                                                                    <span><b><?= $cfg['short'] ?></b>:<?= $row[$key] ?></span>
                                                                <?php endif; endforeach; ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-outline-success view-detail" 
                                                                    data-kategori="<?= htmlspecialchars($row['kategori_aset']) ?>" 
                                                                    data-cabang="<?= htmlspecialchars($row['lokasi']) ?>" 
                                                                    data-dept="ga">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                            <?php
                                                endwhile;
                                            } else {
                                                echo '<tr><td colspan="4" class="text-center text-muted">Tidak ada data untuk Departemen GA</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

            </div>
            <!-- Detail Modal -->
            <div class="modal fade" id="modal-detail" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detail Rincian Aset</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body" id="detail-content">
                            <!-- Loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../layouts/footer.php'; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.view-detail').on('click', function() {
        const kategori = $(this).data('kategori');
        const cabang = $(this).data('cabang');
        const dept = $(this).data('dept');
        
        $('#detail-content').html('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Memuat data...</div>');
        $('#modal-detail').modal('show');
        
        $.get('detail_modal.php', { kategori: kategori, cabang: cabang, dept: dept }, function(data) {
            $('#detail-content').html(data);
        });
    });
});
</script>




    <style>

        .dash-widget-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
            border: 2px solid;
        }

        .dash-count h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .border-left-info {
            border-left: 4px solid #007bff !important;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            margin-bottom: 1.5rem;
        }

        .progress {
            background-color: #e9ecef;
        }
    </style>
<style>
    .card-header .form-control-sm {
        height: 28px;
        padding: 2px 8px;
        border-radius: 4px;
        cursor: pointer;
    }
    .progress-bar {
        transition: width .6s ease;
    }
    .progress {
        background-color: #f0f0f0;
        box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
    }
</style>
</body>
</html>