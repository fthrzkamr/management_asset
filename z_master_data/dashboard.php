<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission
$auth->requirePermission('master_data', 'read');
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>

    <body>
        <div class="main-wrapper">
            <?php include '../layouts/navbar.php'; ?>

            <div class="page-wrapper">
                <div class="content container-fluid">
                    <div class="page-header">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <h3 class="page-title mb-2">Master Data</h3>
                                <ul class="breadcrumb mb-2">
                                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Master Data</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="row">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill card-table">
                                <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                                    <div id="delete-alert" class="alert alert-success fade show" role="alert">
                                        Data berhasil dihapus!
                                    </div>

                                    <script>
                                        setTimeout(function() {
                                            const alertBox = document.getElementById('delete-alert');
                                            if (alertBox) {
                                                // Tambahkan efek fade out pelan
                                                alertBox.classList.remove('show');
                                                alertBox.classList.add('fade');
                                                // Hapus elemen setelah animasi
                                                setTimeout(() => alertBox.remove(), 500);
                                            }
                                        }, 3000); // 3000 ms = 3 detik
                                    </script>
                                <?php endif; ?>
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Kategori Asset</h5>
                                    <?php if ($auth->hasPermission('master_kategori_asset', 'create')): ?>
                                        <a href="katge_add.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah Kategori
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div id="kategori-content"></div> <!-- hasil AJAX lokasi -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lokasi -->
                    <div class="row">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Lokasi Asset</h5>
                                    <?php if ($auth->hasPermission('master_lokasi_asset', 'create')): ?>
                                        <a href="lokasi_add.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah Lokasi
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div id="lokasi-content"></div> <!-- hasil AJAX lokasi -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Divisi -->
                    <div class="row">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Divisi Asset</h5>
                                    <?php if ($auth->hasPermission('master_divisi', 'create')): ?>
                                        <a href="divisi_add.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah Divisi
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div id="divisi-content"></div> <!-- hasil AJAX divisi -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Departemen -->
                    <div class="row">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Departemen Asset</h5>
                                    <?php if ($auth->hasPermission('master_departemen', 'create')): ?>
                                        <a href="depart_add.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah Departemen
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div id="departemen-content"></div> <!-- hasil AJAX departemen -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cabang -->
                    <div class="row">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Cabang</h5>
                                    <?php if ($auth->hasPermission('master_cabang', 'create')): ?>
                                        <a href="cabang_add.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah Cabang
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div id="cabang-content"></div> <!-- hasil AJAX cabang -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kendaraan (Baru) -->
                    <div class="row">
                        <div class="col-md-12 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header d-flex justify-content-between align-items-center bg-info text-white">
                                    <h5 class="card-title mb-0 text-white"><i class="fas fa-car mr-2"></i> Master Kendaraan</h5>
                                    <a href="kendaraan_add.php" class="btn btn-light btn-sm font-weight-bold">
                                        <i class="fas fa-plus"></i> Tambah Kendaraan
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div id="kendaraan-content"></div> <!-- hasil AJAX kendaraan -->
                                </div>
                            </div>
                        </div>
                    </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
        </div>

        <script>
            function loadKategori(page = 1) {
                $.get("load_kategori_asset.php?page=" + page, function(data) {
                    $("#kategori-content").html(data);
                });
            }

            function loadLokasi(page = 1) {
                $.get("load_lokasi_asset.php?page=" + page, function(data) {
                    $("#lokasi-content").html(data);
                });
            }

            function loadDivisi(page = 1) {
                $.get("load_divisi_asset.php?page=" + page, function(data) {
                    $("#divisi-content").html(data);
                });
            }

            function loadDepartemen(page = 1) {
                $.get("load_depart_asset.php?page=" + page, function(data) {
                    $("#departemen-content").html(data);
                });
            }

            function loadCabang(page = 1) {
                $.get("load_cabang_asset.php?page=" + page, function(data) {
                    $("#cabang-content").html(data);
                });
            }

            function loadKendaraan(page = 1) {
                $.get("load_kendaraan.php?page=" + page, function(data) {
                    $("#kendaraan-content").html(data);
                });
            }

            $(document).ready(function() {
                loadKategori();
                loadLokasi();
                loadDivisi();
                loadDepartemen();
                loadCabang();
                loadKendaraan();

                $(document).on('click', '.pagination-kategori a', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadKategori(page);
                });

                $(document).on('click', '.pagination-lokasi a', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadLokasi(page);
                });

                $(document).on('click', '.pagination-divisi a', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadDivisi(page);
                });

                $(document).on('click', '.pagination-departemen a', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadDepartemen(page);
                });

                $(document).on('click', '.pagination-cabang a', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadCabang(page);
                });

                $(document).on('click', '.pagination-kendaraan a', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    loadKendaraan(page);
                });
            });
        </script>

    </body>
</body>

</html>