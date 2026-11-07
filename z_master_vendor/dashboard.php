<?php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');
date_default_timezone_set('Asia/Jakarta');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_vendor', 'read');

$canCreate = $auth->hasPermission('master_vendor', 'create');
$canUpdate = $auth->hasPermission('master_vendor', 'update');
$canDelete = $auth->hasPermission('master_vendor', 'delete');

// Pagination settings
$allowed_limits = [5, 10, 25, 50, 100];
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filter parameters
$filter_kategori = trim($_GET['kategori'] ?? '');
$filter_area     = trim($_GET['area'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$search          = trim($_GET['search'] ?? '');

$where = [];
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $where[] = "(kode_vendor LIKE '%$s%' OR nama_vendor LIKE '%$s%' OR peruntukan_vendor LIKE '%$s%' OR pic_ga LIKE '%$s%' OR kontak_vendor LIKE '%$s%' OR email LIKE '%$s%')";
}
if (!empty($filter_kategori)) {
    $where[] = "kategori_vendor = '" . mysqli_real_escape_string($conn, $filter_kategori) . "'";
}
if (!empty($filter_area)) {
    $where[] = "area = '" . mysqli_real_escape_string($conn, $filter_area) . "'";
}
if (!empty($filter_status)) {
    $where[] = "status_vendor = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
$where_clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total records
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM master_vendor $where_clause");
$count_row = mysqli_fetch_assoc($count_query);
$total_records = (int)($count_row['total'] ?? 0);
$total_pages = ceil($total_records / $limit);

// Fetch categories & areas for filters
$kategoriList = [];
$q_kat = mysqli_query($conn, "SELECT DISTINCT kategori_vendor FROM master_vendor WHERE kategori_vendor IS NOT NULL AND kategori_vendor != '' ORDER BY kategori_vendor ASC");
if ($q_kat) {
    while ($rk = mysqli_fetch_assoc($q_kat)) {
        $kategoriList[] = $rk['kategori_vendor'];
    }
}

$areaList = [];
$q_area = mysqli_query($conn, "SELECT DISTINCT area FROM master_vendor WHERE area IS NOT NULL AND area != '' ORDER BY area ASC");
if ($q_area) {
    while ($ra = mysqli_fetch_assoc($q_area)) {
        $areaList[] = $ra['area'];
    }
}

// Fetch vendor list
$query = mysqli_query($conn, "SELECT * FROM master_vendor $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset");

$success_message = $_SESSION['success'] ?? '';
$error_message   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<head>
    <style>
        .table-responsive { position: relative; }
        .table .action-col {
            position: -webkit-sticky;
            position: sticky;
            right: 0;
            background: #ffffff;
            z-index: 5;
            box-shadow: -6px 0 6px -6px rgba(0,0,0,0.08);
            vertical-align: middle;
            text-align: center;
        }
        .table thead .action-col { z-index: 6; background-color: #f8fafc; }
        .action-buttons { display: inline-flex; gap: 5px; align-items: center; justify-content: center; }
        .modal-detail-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .modal-detail-val {
            font-size: 13.5px;
            color: #1e293b;
            font-weight: 500;
            margin-bottom: 12px;
            word-break: break-word;
        }
        .modal-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 6px;
            margin-bottom: 12px;
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row">
                        <div class="col-sm-12">
                            <h3 class="page-title">Master Vendor</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Data Master</li>
                                <li class="breadcrumb-item active">Master Vendor</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Table Card -->
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card card-table">
                            <div class="card-body">
                                
                                <div class="page-header mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-12">
                                            <div class="d-flex align-items-center" style="gap: 10px;">
                                                <?php if ($canCreate): ?>
                                                <a href="vendor_add.php" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-plus fa-sm"></i> Tambah Vendor
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php
                                                $excel_params = $_GET;
                                                $excel_url = 'export_excel.php' . (!empty($excel_params) ? '?' . http_build_query($excel_params) : '');
                                                ?>
                                                <a href="<?= $excel_url ?>" class="btn btn-success btn-sm" target="_blank">
                                                    <i class="fas fa-file-excel fa-sm"></i> Download Excel
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filter Bar -->
                                <div class="row mb-3 align-items-center">
                                    <div class="col-12 col-xl-10 mb-2 mb-xl-0">
                                        <form method="GET" action="" id="filterForm">
                                            <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                                                
                                                <!-- Filter Kategori -->
                                                <select name="kategori" class="form-control form-control-sm" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
                                                    <option value="">Semua Kategori</option>
                                                    <?php foreach ($kategoriList as $kat): ?>
                                                        <option value="<?= htmlspecialchars($kat) ?>" <?= $filter_kategori === $kat ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($kat) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <!-- Filter Area -->
                                                <select name="area" class="form-control form-control-sm" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                                                    <option value="">Semua Area</option>
                                                    <?php foreach ($areaList as $ar): ?>
                                                        <option value="<?= htmlspecialchars($ar) ?>" <?= $filter_area === $ar ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($ar) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <!-- Filter Status -->
                                                <select name="status" class="form-control form-control-sm" style="width: auto; min-width: 130px;" onchange="this.form.submit()">
                                                    <option value="">Semua Status</option>
                                                    <option value="Aktif" <?= $filter_status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                                                    <option value="Non-Aktif" <?= $filter_status === 'Non-Aktif' ? 'selected' : '' ?>>Non-Aktif</option>
                                                </select>

                                                <!-- Search Box -->
                                                <div class="input-group input-group-sm flex-grow-1" style="min-width: 180px; max-width: 260px;">
                                                    <input type="text" name="search" class="form-control" placeholder="Cari vendor..." value="<?= htmlspecialchars($search) ?>">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="fas fa-search"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Reset Button -->
                                                <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_area) || !empty($filter_status)): ?>
                                                    <a href="dashboard.php" class="btn btn-secondary btn-sm" title="Reset Filter">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Data per page -->
                                    <div class="col-12 col-xl-2 text-xl-right">
                                        <div class="d-inline-flex align-items-center">
                                            <small class="text-muted mr-2">Tampil:</small>
                                            <select class="form-control form-control-sm" style="width: 70px;" onchange="location = this.value;">
                                                <?php foreach ($allowed_limits as $l): ?>
                                                    <?php
                                                    $lim_params = $_GET;
                                                    $lim_params['limit'] = $l;
                                                    $lim_params['page'] = 1;
                                                    ?>
                                                    <option value="?<?= http_build_query($lim_params) ?>" <?= $limit == $l ? 'selected' : '' ?>><?= $l ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Data Table -->
                                <div class="table-responsive">
                                    <table class="table table-hover table-center mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="4%" class="text-center">No</th>
                                                <th>ID Vendor</th>
                                                <th>Nama Vendor</th>
                                                <th>Kategori</th>
                                                <th>Peruntukan</th>
                                                <th>Area</th>
                                                <th>PIC GA</th>
                                                <th>Kontak</th>
                                                <th>Bank & Rekening</th>
                                                <th class="text-center">Status</th>
                                                <th class="action-col">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if ($query && mysqli_num_rows($query) > 0) {
                                                $no = $offset + 1;
                                                while ($row = mysqli_fetch_assoc($query)) {
                                                    $isAktif = ($row['status_vendor'] === 'Aktif');
                                                    echo "<tr>";
                                                    echo "<td class='text-center'>" . $no++ . "</td>";
                                                    echo "<td><strong>" . htmlspecialchars($row['kode_vendor'] ?: '-') . "</strong></td>";
                                                    echo "<td>
                                                            <div><strong>" . htmlspecialchars($row['nama_vendor']) . "</strong></div>";
                                                    if (!empty($row['status_hukum'])) {
                                                        echo "<span class='badge badge-light border text-muted small'>" . htmlspecialchars($row['status_hukum']) . "</span> ";
                                                    }
                                                    if (!empty($row['status_pkp'])) {
                                                        echo "<span class='badge badge-light border text-muted small'>" . htmlspecialchars($row['status_pkp']) . "</span> ";
                                                    }
                                                    if (!empty($row['file_mou'])) {
                                                        echo "<a href='../uploads/vendor_mou/" . htmlspecialchars($row['file_mou']) . "' target='_blank' class='badge badge-primary small' title='Lihat Dokumen MoU'><i class='fas fa-file-download mr-1'></i>MoU</a>";
                                                    }
                                                    echo "</td>";
                                                    echo "<td><span class='badge badge-info'>" . htmlspecialchars($row['kategori_vendor'] ?: '-') . "</span></td>";
                                                    echo "<td>" . htmlspecialchars($row['peruntukan_vendor'] ?: '-') . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['area'] ?: '-') . "</td>";
                                                    echo "<td>" . htmlspecialchars($row['pic_ga'] ?: '-') . "</td>";
                                                    echo "<td>
                                                            <div>" . htmlspecialchars($row['kontak_vendor'] ?: '-') . "</div>";
                                                    if (!empty($row['email'])) {
                                                        echo "<small class='text-muted'>" . htmlspecialchars($row['email']) . "</small>";
                                                    }
                                                    echo "</td>";
                                                    echo "<td>";
                                                    if (!empty($row['nama_bank'])) {
                                                        echo "<div><strong>" . htmlspecialchars($row['nama_bank']) . "</strong></div>";
                                                        echo "<small class='text-muted'>" . htmlspecialchars($row['no_rekening'] ?: '-') . "</small>";
                                                    } else {
                                                        echo "<span class='text-muted'>-</span>";
                                                    }
                                                    echo "</td>";
                                                    echo "<td class='text-center'>
                                                            <span class='badge " . ($isAktif ? 'badge-success' : 'badge-danger') . "'>" . htmlspecialchars($row['status_vendor']) . "</span>
                                                          </td>";
                                                    echo "<td class='action-col'>
                                                            <div class='action-buttons'>
                                                                <button type='button' class='btn btn-sm btn-info btn-detail' data-id='" . $row['id'] . "' title='Lihat Detail'>
                                                                    <i class='fa-solid fa-eye'></i>
                                                                </button>";
                                                    if ($canUpdate) {
                                                        echo "<a href='vendor_edit.php?id=" . $row['id'] . "' class='btn btn-sm btn-warning' title='Edit Vendor'>
                                                                <i class='fa-solid fa-pen-to-square'></i>
                                                              </a>";
                                                    }
                                                    if ($canDelete) {
                                                        echo "<a href='vendor_delete.php?id=" . $row['id'] . "' class='btn btn-sm btn-danger btn-delete-confirm' 
                                                                 data-message='Yakin ingin menghapus vendor " . htmlspecialchars(addslashes($row['nama_vendor'])) . "?' title='Hapus Vendor'>
                                                                <i class='fa-solid fa-trash'></i>
                                                              </a>";
                                                    }
                                                    echo "</div>
                                                          </td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='11' class='text-center py-4'>";
                                                echo "<i class='fas fa-inbox fa-3x text-muted mb-3'></i><br>";
                                                echo "<span class='text-muted'>Tidak ada data vendor yang tersedia</span>";
                                                if (!empty($search)) {
                                                    echo "<br><small class='text-muted'>Coba ubah kata kunci pencarian</small>";
                                                }
                                                echo "</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i>
                                            Total <strong><?= $total_records ?></strong> vendor,
                                            halaman <strong><?= $page ?></strong> dari <strong><?= max(1, $total_pages) ?></strong>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end mb-0">
                                                <?php if ($page > 1): ?>
                                                    <?php
                                                    $prev_params = $_GET;
                                                    $prev_params['page'] = $page - 1;
                                                    ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?= http_build_query($prev_params) ?>">Previous</a>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="page-item disabled"><span class="page-link">Previous</span></li>
                                                <?php endif; ?>

                                                <?php 
                                                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): 
                                                    $p_params = $_GET;
                                                    $p_params['page'] = $i;
                                                ?>
                                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                        <a class="page-link" href="?<?= http_build_query($p_params) ?>"><?= $i ?></a>
                                                    </li>
                                                <?php endfor; ?>

                                                <?php if ($page < $total_pages): ?>
                                                    <?php
                                                    $next_params = $_GET;
                                                    $next_params['page'] = $page + 1;
                                                    ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?= http_build_query($next_params) ?>">Next</a>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="page-item disabled"><span class="page-link">Next</span></li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Detail Vendor -->
                <div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="detailModalTitle">Detail Vendor</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="detailContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <div class="mt-2 text-muted small">Memuat data vendor...</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        $('.btn-detail').on('click', function() {
            var vendorId = $(this).data('id');
            $('#modalDetail').modal('show');
            $('#detailContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted small">Memuat data vendor...</div></div>');

            $.ajax({
                url: 'vendor_detail_ajax.php',
                type: 'GET',
                data: { id: vendorId },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        var v = res.data;
                        $('#detailModalTitle').text(v.nama_vendor || 'Detail Vendor');
                        
                        var html = `
                        <div class="row">
                            <div class="col-12"><div class="modal-section-title">1. Informasi Utama & Legalitas</div></div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Kode Vendor</div>
                                <div class="modal-detail-val">${v.kode_vendor || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Nama Vendor / Perusahaan</div>
                                <div class="modal-detail-val font-weight-bold text-primary">${v.nama_vendor || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Status Hukum</div>
                                <div class="modal-detail-val">${v.status_hukum || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Kategori Vendor</div>
                                <div class="modal-detail-val">${v.kategori_vendor || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Peruntukan Vendor</div>
                                <div class="modal-detail-val">${v.peruntukan_vendor || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Area Layanan</div>
                                <div class="modal-detail-val">${v.area || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Status Vendor</div>
                                <div class="modal-detail-val"><span class="badge ${v.status_vendor === 'Aktif' ? 'badge-success' : 'badge-danger'}">${v.status_vendor || '-'}</span></div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">NPWP</div>
                                <div class="modal-detail-val">${v.npwp || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Status PKP</div>
                                <div class="modal-detail-val">${v.status_pkp || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Dokumen MoU</div>
                                <div class="modal-detail-val">
                                    ${v.status_mou || '-'}
                                    ${v.file_mou ? ` <a href="../uploads/vendor_mou/${encodeURIComponent(v.file_mou)}" target="_blank" class="btn btn-xs btn-outline-primary ml-1 py-0 px-2" style="font-size: 0.72rem;"><i class="fas fa-file-download mr-1"></i>Unduh MoU</a>` : ''}
                                </div>
                            </div>

                            <div class="col-12 mt-2"><div class="modal-section-title">2. Kontak & Lokasi</div></div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">PIC Tim General Affair</div>
                                <div class="modal-detail-val">${v.pic_ga || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Kontak Vendor</div>
                                <div class="modal-detail-val">${v.kontak_vendor || '-'}</div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="modal-detail-label">Email Vendor</div>
                                <div class="modal-detail-val">${v.email || '-'}</div>
                            </div>
                            <div class="col-12">
                                <div class="modal-detail-label">Alamat Lengkap</div>
                                <div class="modal-detail-val">${v.alamat || '-'}</div>
                            </div>

                            <div class="col-12 mt-2"><div class="modal-section-title">3. Pembayaran & Perbankan</div></div>
                            <div class="col-md-3 col-sm-6">
                                <div class="modal-detail-label">Nama Bank</div>
                                <div class="modal-detail-val">${v.nama_bank || '-'}</div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="modal-detail-label">Nomor Rekening</div>
                                <div class="modal-detail-val">${v.no_rekening || '-'}</div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="modal-detail-label">Atas Nama Rekening</div>
                                <div class="modal-detail-val">${v.atas_nama_rekening || '-'}</div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="modal-detail-label">Metode Pembayaran</div>
                                <div class="modal-detail-val">${v.metode_pembayaran || '-'}</div>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <div class="modal-detail-label">Lead Time Pengiriman</div>
                                <div class="modal-detail-val">${v.lead_time || '-'}</div>
                            </div>

                            <div class="col-12 mt-2"><div class="modal-section-title">4. Catatan & Evaluasi</div></div>
                            <div class="col-md-6">
                                <div class="modal-detail-label">Keterangan Tambahan</div>
                                <div class="modal-detail-val">${v.keterangan || '-'}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="modal-detail-label">Catatan Performa / Review</div>
                                <div class="modal-detail-val">${v.review_performa || '-'}</div>
                            </div>
                        </div>
                        `;
                        $('#detailContent').html(html);
                    } else {
                        $('#detailContent').html('<div class="alert alert-danger mb-0">' + (res.message || 'Gagal mengambil detail vendor') + '</div>');
                    }
                },
                error: function() {
                    $('#detailContent').html('<div class="alert alert-danger mb-0">Terjadi kesalahan pada server saat mengambil data.</div>');
                }
            });
        });
    });
    </script>
</body>
</html>
