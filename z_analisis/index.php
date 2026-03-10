<?php
// filepath: c:\xampp\htdocs\asset_dfm\index.php
include('../dist/config/config.php');
// tambahkan ini langsung setelah include untuk menampilkan error saat development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Aset per kategori - Departemen IT
$assets_by_category_it = mysqli_query($conn, "
    SELECT kategori_aset, COUNT(*) as jumlah_aset, SUM(total_nilai_pengadaan) as total_nilai, AVG(total_nilai_pengadaan) as rata_nilai
    FROM asset_dfm 
    GROUP BY kategori_aset 
    ORDER BY jumlah_aset DESC
");

// Aset per kategori - Departemen GA
$assets_by_category_ga = mysqli_query($conn, "
    SELECT ag.kategori_aset as kategori_aset, COUNT(*) as jumlah_aset, SUM(ag.total_nilai_pengadaan) as total_nilai, AVG(ag.total_nilai_pengadaan) as rata_nilai
    FROM asset_ga ag
    GROUP BY ag.kategori_aset
    ORDER BY jumlah_aset DESC
");

// Menghitung total untuk kalkulasi persentase pada tabel kategori
$total_assets_it_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_dfm");
$total_assets_it = $total_assets_it_query ? mysqli_fetch_assoc($total_assets_it_query)['total'] : 0;

$total_assets_ga_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_ga");
$total_assets_ga = $total_assets_ga_query ? mysqli_fetch_assoc($total_assets_ga_query)['total'] : 0;
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
                                <i class="fas fa-chart-pie"></i> Dashboard Analitik
                            </h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard Analitik</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Bagian Widget Statistik dan Chart Kondisi Dihapus Atas Permintaan Pengguna -->

                <div class="row">
                    <div class="col-md-12">
                        <!-- Category Table - IT -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-table"></i> Detail Aset - Departemen IT
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Kategori</th>
                                                <th class="text-center">Jumlah</th>
                                                <th class="text-right">Total Nilai</th>
                                                <th class="text-right">Rata-rata Nilai</th>
                                                <th class="text-center">Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($assets_by_category_it && mysqli_num_rows($assets_by_category_it) > 0) {
                                                mysqli_data_seek($assets_by_category_it, 0);
                                                while ($row = mysqli_fetch_assoc($assets_by_category_it)):
                                                    $percentage = ($total_assets_it > 0) ? round(($row['jumlah_aset'] / $total_assets_it) * 100, 1) : 0;
                                            ?>
                                                    <tr>
                                                        <td>
                                                            <a href="javascript:void(0)" class="view-category-detail text-info" data-kategori="<?= htmlspecialchars($row['kategori_aset']) ?>" data-departemen="IT">
                                                                <strong><i class="fas fa-folder-open mr-1"></i> <?= htmlspecialchars($row['kategori_aset']) ?></strong>
                                                            </a>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-info"><?= number_format($row['jumlah_aset']) ?></span>
                                                        </td>
                                                        <td class="text-right">
                                                            <strong>Rp <?= number_format($row['total_nilai'], 0, ',', '.') ?></strong>
                                                        </td>
                                                        <td class="text-right">
                                                            Rp <?= number_format($row['rata_nilai'], 0, ',', '.') ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar bg-info" role="progressbar"
                                                                    style="width: <?= $percentage ?>%"
                                                                    aria-valuenow="<?= $percentage ?>"
                                                                    aria-valuemin="0" aria-valuemax="100">
                                                                    <?= $percentage ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                            <?php
                                                endwhile;
                                            } else {
                                                echo '<tr><td colspan="5" class="text-center text-muted">Tidak ada data untuk Departemen IT</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Category Table - GA -->
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-table"></i> Detail Aset - Departemen GA
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Kategori</th>
                                                <th class="text-center">Jumlah</th>
                                                <th class="text-right">Total Nilai</th>
                                                <th class="text-right">Rata-rata Nilai</th>
                                                <th class="text-center">Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($assets_by_category_ga && mysqli_num_rows($assets_by_category_ga) > 0) {
                                                mysqli_data_seek($assets_by_category_ga, 0);
                                                while ($row = mysqli_fetch_assoc($assets_by_category_ga)):
                                                    $percentage = ($total_assets_ga > 0) ? round(($row['jumlah_aset'] / $total_assets_ga) * 100, 1) : 0;
                                            ?>
                                                    <tr>
                                                        <td>
                                                            <a href="javascript:void(0)" class="view-category-detail text-success" data-kategori="<?= htmlspecialchars($row['kategori_aset']) ?>" data-departemen="GA">
                                                                <strong><i class="fas fa-folder-open mr-1"></i> <?= htmlspecialchars($row['kategori_aset']) ?></strong>
                                                            </a>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-success"><?= number_format($row['jumlah_aset']) ?></span>
                                                        </td>
                                                        <td class="text-right">
                                                            <strong>Rp <?= number_format($row['total_nilai'], 0, ',', '.') ?></strong>
                                                        </td>
                                                        <td class="text-right">
                                                            Rp <?= number_format($row['rata_nilai'], 0, ',', '.') ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar bg-success" role="progressbar"
                                                                    style="width: <?= $percentage ?>%"
                                                                    aria-valuenow="<?= $percentage ?>"
                                                                    aria-valuemin="0" aria-valuemax="100">
                                                                    <?= $percentage ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                            <?php
                                                endwhile;
                                            } else {
                                                echo '<tr><td colspan="5" class="text-center text-muted">Tidak ada data untuk Departemen GA</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <?php include '../layouts/footer.php'; ?>
                </div>

            </div>
        </div>
    </div>


    <!-- Modal Detail Kategori Aset -->
    <div class="modal fade" id="kategoriModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header text-white" id="modalKategoriHeader" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-list-ul mr-2"></i> Rincian Aset: <span id="modalKategoriName"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat daftar aset...</p>
                    </div>
                    <div id="modalContent" style="display: none; max-height: 60vh; overflow-y: auto;">
                        <table class="table table-hover table-striped table-bordered mb-0" id="detailKategoriTable">
                            <thead class="thead-dark sticky-top">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">Kode Aset</th>
                                    <th>Nama Aset</th>
                                    <th width="20%">Lokasi</th>
                                    <th width="12%" class="text-center">Kondisi</th>
                                    <th width="15%" class="text-right">Nilai Pengadaan</th>
                                </tr>
                            </thead>
                            <tbody id="detailKategoriBody">
                                <!-- Ajax Content Here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tambahkan library DataTable untuk modal jika belum ada global (opsional) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
    $(document).ready(function() {
        // Trigger saat nama kategori diklik
        $('.view-category-detail').on('click', function(e) {
            e.preventDefault();
            var kategori = $(this).data('kategori');
            var departemen = $(this).data('departemen');
            
            // Atur gaya header sesuai departemen
            if (departemen === 'IT') {
                $('#modalKategoriHeader').css('background', 'linear-gradient(135deg, #17a2b8 0%, #117a8b 100%)');
            } else {
                $('#modalKategoriHeader').css('background', 'linear-gradient(135deg, #28a745 0%, #1e7e34 100%)');
            }

            $('#modalKategoriName').text(kategori + " (" + departemen + ")");
            $('#modalContent').hide();
            $('#modalLoading').show();
            $('#kategoriModal').modal('show');
            
            // Hancurkan datatable lama sebelum render baru (reset)
            if ($.fn.DataTable.isDataTable('#detailKategoriTable')) {
                $('#detailKategoriTable').DataTable().destroy();
            }
            $('#detailKategoriBody').empty();

            // Panggil API untuk mendapatkan rincian
            $.ajax({
                url: 'get_detail_aset_kategori.php',
                type: 'POST',
                data: {
                    kategori: kategori,
                    departemen: departemen
                },
                success: function(response) {
                    $('#modalLoading').hide();
                    $('#detailKategoriBody').html(response);
                    $('#modalContent').fadeIn();
                    
                    // Inisialisasi DataTables untuk interaktivitas modal
                    $('#detailKategoriTable').DataTable({
                        "language": {
                            "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                        },
                        "pageLength": 10,
                        "lengthMenu": [5, 10, 25, 50],
                        "autoWidth": false
                    });
                },
                error: function() {
                    $('#modalLoading').hide();
                    $('#detailKategoriBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data dari server.</td></tr>');
                    $('#modalContent').fadeIn();
                }
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
</body>
</html>