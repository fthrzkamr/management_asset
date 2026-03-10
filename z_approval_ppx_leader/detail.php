<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');



$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Get no_ppx from URL
$no_ppx = $_GET['no_ppx'] ?? '';

if (empty($no_ppx)) {
    $_SESSION['error'] = 'Nomor PPX tidak valid!';
    header("Location: dashboard.php");
    exit();
}

// Query untuk mendapatkan data PPX Request
$query_ppx = "SELECT pr.*, u.nama_user as nama_pengaju, d.nama_divisi 
              FROM ppx_request pr
              LEFT JOIN user_dfm u ON pr.user_id = u.id_user
              LEFT JOIN divisi d ON pr.divisi = d.id_divisi
              WHERE pr.no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "'";

$result_ppx = mysqli_query($conn, $query_ppx);

if (!$result_ppx || mysqli_num_rows($result_ppx) == 0) {
    $_SESSION['error'] = 'Data PPX tidak ditemukan!';
    header("Location: dashboard.php");
    exit();
}

$ppx_data = mysqli_fetch_assoc($result_ppx);

// Inisialisasi tampilan status: jika 'Leader' -> 'Kacap/Manager', jika 'Manager' -> 'Manager Finance'
$orig_status = trim($ppx_data['status']);
$display_status = $orig_status;
if ($orig_status === 'Leader') {
    $display_status = 'Kacap/Manager';
} elseif ($orig_status === 'Manager') {
    $display_status = 'Manager Finance';
}

// Query untuk mendapatkan detail items
$query_detail = "SELECT * FROM ppx_request_detail 
                 WHERE no_ppx = '" . mysqli_real_escape_string($conn, $no_ppx) . "'
                 ORDER BY no_ppx_detail";

$result_detail = mysqli_query($conn, $query_detail);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail PPX - <?= htmlspecialchars($no_ppx) ?></title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">

    <?php
    $layout_dir = realpath(__DIR__ . '/../layouts');
    if ($layout_dir && file_exists($layout_dir . '/header_css.php')) {
        include $layout_dir . '/header_css.php';
    } else {
        error_log('Missing layout file: ' . ($layout_dir ? $layout_dir . '/header_css.php' : __DIR__ . '/../layouts/header_css.php'));
        // Fallback minimal CSS to avoid layout break (sesuaikan path jika perlu)
        echo '<link rel="stylesheet" href="/assets/css/bootstrap.min.css">';
    }
    ?>

    <style>
        .detail-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .detail-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 8px 12px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .btn-back {
            background: #6c757d;
            border-color: #6c757d;
        }

        .btn-back:hover {
            background: #545b62;
            border-color: #4e555b;
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
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="page-title">Detail Pengajuan PPX</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Pengajuan PPX</a></li>
                                <li class="breadcrumb-item active">Detail PPX</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="dashboard.php" class="btn btn-back text-white">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                <!-- PPX Info Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt"></i>
                                    Informasi PPX: <?= htmlspecialchars($ppx_data['no_ppx']) ?>
                                </h5>
                                <div>
                                    <!-- <?php if (strtolower(trim($ppx_data['status'])) === 'revisi' && $auth->hasPermission('pengajuan', 'create')): ?>
                                        <a href="edit.php?no_ppx=<?= urlencode($ppx_data['no_ppx']) ?>" class="btn btn-warning btn-sm mr-1" title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="delete.php?no_ppx=<?= urlencode($ppx_data['no_ppx']) ?>" class="btn btn-danger btn-sm" title="Hapus"
                                           class="btn-delete-confirm" data-message="Yakin ingin menghapus PPX <?= htmlspecialchars($ppx_data['no_ppx']) ?> ?">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    <?php endif; ?> -->

                                    <!-- Tombol Revisi dipindahkan ke halaman detail -->
                                    <?php if ($auth->canApprove() && trim($ppx_data['status']) === 'Leader'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="processRevisi('<?= htmlspecialchars($ppx_data['no_ppx']) ?>')" title="Revisi">
                                            <i class="fas fa-undo-alt"></i> Revisi
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="info-label" width="40%">Nama PIC:</td>
                                                <td><?= htmlspecialchars($ppx_data['nama_pic']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Jabatan:</td>
                                                <td><?= htmlspecialchars($ppx_data['jabatan']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Divisi:</td>
                                                <td>
                                                    <?php 
                                                    // Tampilkan nama divisi dari hasil JOIN
                                                    if (!empty($ppx_data['nama_divisi'])) {
                                                        echo htmlspecialchars($ppx_data['nama_divisi']);
                                                    } else {
                                                        // Fallback ke ID divisi jika nama tidak ditemukan
                                                        echo htmlspecialchars($ppx_data['divisi']);
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Cabang:</td>
                                                <td><?= htmlspecialchars($ppx_data['cabang']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Tanggal Pengajuan:</td>
                                                <td><?= date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Tanggal Dibutuhkan:</td>
                                                <td><?= date('d/m/Y', strtotime($ppx_data['tanggal_dibutuhkan'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td class="info-label" width="40%">Tingkat Kepentingan:</td>
                                                <td>
                                                    <?php
                                                    $priority_class = '';
                                                    switch ($ppx_data['tingkat_kepentingan']) {
                                                        case 'Rendah':
                                                            $priority_class = 'success';
                                                            break;
                                                        case 'Sedang':
                                                            $priority_class = 'info';
                                                            break;
                                                        case 'Tinggi':
                                                            $priority_class = 'warning';
                                                            break;
                                                        case 'Urgent':
                                                            $priority_class = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?= $priority_class ?>">
                                                        <?= htmlspecialchars($ppx_data['tingkat_kepentingan']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Status:</td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch ($orig_status) {
                                                        case 'Leader':
                                                            $status_class = 'primary';
                                                            break;
                                                        case 'Manager':
                                                            $status_class = 'info';
                                                            break;
                                                        case 'Finance':
                                                            $status_class = 'warning';
                                                            break;
                                                        case 'Direktur':
                                                            $status_class = 'secondary';
                                                            break;
                                                        case 'Uang Muka':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'Selesai':
                                                            $status_class = 'success';
                                                            break;
                                                        case 'Revisi':
                                                            $status_class = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?= $status_class ?> status-badge">
                                                        <?= htmlspecialchars($display_status) ?>
                                                    </span>
                                                </td>
                                            </tr>

                                            <!-- Tampilkan Uang Muka dan Nominal Realisasi jika status = Uang Muka atau Selesai -->
                                            <tr>
                                                <td class="info-label">Uang Muka:</td>
                                                <td>
                                                    <strong>Rp <?= number_format(isset($ppx_data['uang_muka']) ? (int)$ppx_data['uang_muka'] : 0, 0, ',', '.') ?></strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Nominal Realisasi:</td>
                                                <td>
                                                    <strong>Rp <?= number_format(isset($ppx_data['nominal_realisasi']) ? (int)$ppx_data['nominal_realisasi'] : 0, 0, ',', '.') ?></strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="info-label">Total Nominal:</td>
                                                <td><strong>Rp <?= number_format(isset($ppx_data['nominal_ppx']) ? (int)$ppx_data['nominal_ppx'] : 0, 0, ',', '.') ?></strong></td>
                                            </tr>

                                        </table>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="info-label">Alasan Pengajuan:</h6>
                                        <p class="text-justify"><?= nl2br(htmlspecialchars($ppx_data['alasan_pengajuan'])) ?></p>
                                    </div>
                                </div>

                                <?php if (!empty($ppx_data['upload_file'])): ?>
                                    <div class="row">
                                        <div class="col-12">
                                            <h6 class="info-label">File Lampiran:</h6>
                                            <a href="../uploads/ppx/<?= htmlspecialchars($ppx_data['upload_file']) ?>"
                                                target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-download"></i> Download File
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Items Card -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i>
                                    Detail Item PPX (<?= mysqli_num_rows($result_detail) ?> item)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($result_detail) > 0): ?>
                                    <?php $total_all = 0;
                                    $no = 1; ?>
                                    <?php while ($detail = mysqli_fetch_assoc($result_detail)): ?>
                                        <?php $total_all += $detail['total_harga']; ?>
                                        <div class="detail-item">
                                            <div class="row">
                                                <div class="col-md-1">
                                                    <h6 class="text-primary">#<?= $no++ ?></h6>
                                                </div>
                                                <div class="col-md-11">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <small class="info-label">No. Akun:</small><br>
                                                            <strong><?= htmlspecialchars($detail['no_akun']) ?></strong>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small class="info-label">Rincian Item:</small><br>
                                                            <strong><?= htmlspecialchars($detail['rincian']) ?></strong>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <small class="info-label">Jumlah & Satuan:</small><br>
                                                            <strong><?= number_format($detail['jumlah']) ?> <?= htmlspecialchars($detail['satuan']) ?></strong>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-md-3">
                                                            <small class="info-label">Harga Satuan:</small><br>
                                                            Rp <?= number_format($detail['harga_satuan'], 0, ',', '.') ?>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <small class="info-label">Total Harga:</small><br>
                                                            <strong class="text-primary">Rp <?= number_format($detail['total_harga'], 0, ',', '.') ?></strong>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <?php if (!empty($detail['deskripsi'])): ?>
                                                                <small class="info-label">Deskripsi:</small><br>
                                                                <small class="text-muted"><?= htmlspecialchars($detail['deskripsi']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>

                                    <!-- Total Summary -->
                                    <div class="alert alert-info mt-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>TOTAL KESELURUHAN PPX:</strong>
                                            </div>
                                            <div class="col-md-6 text-right">
                                                <h5 class="mb-0"><strong>Rp <?= number_format($total_all, 0, ',', '.') ?></strong></h5>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tombol Kembali di bawah Total (pindah ke kiri) -->
                                    <div class="text-left mb-4">
                                        <a href="dashboard.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Kembali
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Tidak ada detail item untuk PPX ini</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include('../layouts/footer.php'); ?>
            </div>
        </div>
    </div>

    <?php
    // Prior include removed to avoid fatal warning if file missing.
    $footer_js_path = __DIR__ . '/../layouts/footer_js.php';
    if (file_exists($footer_js_path)) {
        include $footer_js_path;
    } else {
        error_log('Missing layout file: ' . $footer_js_path);
        // Fallback minimal scripts — sesuaikan path jika project Anda menempatkan asset di lokasi lain
    ?>
        <script src="/assets/plugins/jquery/jquery.min.js"></script>
        <script src="/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="/assets/js/app.js"></script>
    <?php
    }
    ?>

    <!-- Hidden form untuk submit revisi (dipakai oleh tombol Revisi di halaman detail) -->
    <form id="reviseForm" method="POST" action="process_revisi.php" style="display:none;">
        <input type="hidden" name="no_pengajuan" id="no_pengajuan_revisi_input" value="">
    </form>

    <!-- Konfirmasi Modal Revisi -->
    <div class="modal fade" id="confirmRevisiModal" tabindex="-1" role="dialog" aria-labelledby="confirmRevisiLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmRevisiLabel">Konfirmasi Revisi</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin mengembalikan pengajuan ini ke status "Revisi" atau merevisinya secara langsung?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tidak</button>
                    <!-- Tombol Revisi (Langsung) dipindahkan ke modal; akan diarahkan ke edit_keep_status.php -->
                    <a id="directReviseBtn" class="btn btn-warning" href="#" role="button">
                        <i class="fas fa-edit"></i> Revisi (Langsung)
                    </a>
                    <!-- Tombol Revisi (Langsung) dipindahkan ke modal; akan diarahkan ke edit_keep_status.php -->
                    <a id="confirmRevisiYes" class="btn btn-danger" href="#" role="button">
                        <i class="fas fa-undo-alt"></i> Revisi (Ulang)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tampilkan modal konfirmasi revisi (dipakai di halaman detail)
        function processRevisi(no_ppx) {
            // simpan no_ppx pada modal untuk tombol "Ya, Revisi" dan update href tombol "Revisi (Langsung)"
            var $modal = $('#confirmRevisiModal');
            $modal.data('no_ppx', no_ppx);
            // set link for direct revise action
            $('#directReviseBtn').attr('href', 'edit_keep_status.php?no_ppx=' + encodeURIComponent(no_ppx) + '&keep_status=1');
            $modal.modal('show');
        }

        $(function() {
            $('#confirmRevisiYes').on('click', function() {
                var no_ppx = $('#confirmRevisiModal').data('no_ppx');
                if (!no_ppx) return;
                $('#no_pengajuan_revisi_input').val(no_ppx);
                $('#reviseForm').submit();
            });
        });

        // Fallback: pastikan tombol dengan data-dismiss / data-bs-dismiss menutup modal
        $(document).on('click', '#confirmRevisiModal [data-dismiss="modal"], #confirmRevisiModal [data-bs-dismiss="modal"]', function(e) {
            var $modal = $('#confirmRevisiModal');

            // Jika jQuery + Bootstrap 4 modal plugin tersedia, pakai API
            if (typeof $modal.modal === 'function') {
                try {
                    $modal.modal('hide');
                    return;
                } catch (err) {
                    /* lanjut ke fallback */ }
            }

            // Jika Bootstrap 5 tersedia tanpa jQuery, gunakan API native
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                try {
                    var inst = bootstrap.Modal.getInstance(document.getElementById('confirmRevisiModal'));
                    if (inst) {
                        inst.hide();
                        return;
                    }
                } catch (err) {
                    /* lanjut ke fallback */ }
            }

            // Fallback manual: sembunyikan modal dan hapus backdrop / class body.
            $modal.removeClass('show').hide();
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        });
    </script>
</body>

</html>