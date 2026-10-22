<?php
// filepath: c:\xampp\htdocs\asset_dfm\z_pengajuan_renovasi\dashboard.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');


$auth = new AuthHelper($conn);

// Cek login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission
$auth->requirePermission('pengajuan_renovasi', 'read');

// Buat kondisi WHERE berdasarkan permission
// User dengan 'approve' bisa lihat semua, yang lain hanya milik sendiri
$canApprove = $auth->hasPermission('pengajuan_renovasi', 'approve');
$whereClause = $canApprove ? "" : "WHERE rr.user_id = " . (int)$_SESSION['id_user'];

// Query untuk mengambil data renovasi dengan ordering berdasarkan status dan tanggal
$query = "SELECT 
    rr.*,
    u.nama_user as nama_pengaju
FROM renovation_request rr
LEFT JOIN user_dfm u ON rr.user_id = u.id_user
$whereClause
ORDER BY 
CASE rr.status 
    WHEN 'Pending' THEN 1 
    WHEN 'Ditolak' THEN 2 
    WHEN 'Disetujui' THEN 3 
    WHEN 'Selesai' THEN 4
    ELSE 5 
END,
rr.tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);

// Statistik untuk cards - hanya untuk user yang punya permission approve
$stats = [];
if ($canApprove) {
    $stats_query = "SELECT 
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as completed
    FROM renovation_request rr $whereClause";

    $stats_result = mysqli_query($conn, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);
}

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Pengajuan Renovasi - DF GROUP</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css" rel="stylesheet">

    <style>
        .btn-action {
            margin: 1px;
            padding: 4px 8px;
            font-size: 11px;
        }

        .card-stats {
            transition: all 0.2s ease;
        }

        .card-stats:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stats-icon {
            opacity: 0.6;
            font-size: 1.5rem;
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
                        <div class="col-md-12">
                            <h3 class="page-title mb-2">Dashboard Pengajuan Renovasi</h3>
                            <ul class="breadcrumb mb-2">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Pengajuan Renovasi</li>
                            </ul>
                            
                            <!-- Button Group - Berjejer Horizontal -->
                            <div class="d-flex align-items-center" style="gap: 10px;">
                                <!-- Button Tambah Pengajuan - hanya tampil jika punya izin create -->
                                <?php if ($auth->hasPermission('pengajuan_renovasi', 'create')): ?>
                                <a href="add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus fa-sm"></i> Tambah Pengajuan
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle fa-sm"></i> <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle fa-sm"></i> <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards - HANYA UNTUK HR, GA & SUPER ADMIN -->
                <!-- <?php if ($auth->isHr() || $auth->isGa() || $auth->isSuperAdmin()): ?>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-warning mb-1">Pending</h6>
                                            <h3 class="mb-0"><?= $stats['pending'] ?></h3>
                                        </div>
                                        <i class="fas fa-clock stats-icon text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-success mb-1">Disetujui</h6>
                                            <h3 class="mb-0"><?= $stats['approved'] ?></h3>
                                        </div>
                                        <i class="fas fa-check-circle stats-icon text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-danger mb-1">Ditolak</h6>
                                            <h3 class="mb-0"><?= $stats['rejected'] ?></h3>
                                        </div>
                                        <i class="fas fa-times-circle stats-icon text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card card-stats">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-info mb-1">Selesai</h6>
                                            <h3 class="mb-0"><?= $stats['completed'] ?></h3>
                                        </div>
                                        <i class="fas fa-flag-checkered stats-icon text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?> -->

                <!-- Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list fa-sm"></i> Daftar Pengajuan Renovasi
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="renovasiTable" class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>No. Pengajuan</th>
                                                <th>Tanggal</th>
                                                <th>PIC</th>
                                                <th>Kantor Cabang</th>
                                                <th>Area Renovasi</th>
                                                <th>Jenis Pekerjaan</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result)): 
                                            ?>
                                                <tr>
                                                    <td><small class="font-weight-bold"><?= $no++ ?></small></td>
                                                    <td><small class="font-weight-bold"><?= htmlspecialchars($row['no_pengajuan']) ?></small></td>
                                                    <td><small><?= date('d/m/Y', strtotime($row['tanggal_pengajuan'])) ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['nama_pic']) ?></small></td>
                                                    <td><small><?= htmlspecialchars($row['kantor_cabang']) ?></small></td>
                                                    <td>
                                                        <small>
                                                            <?= htmlspecialchars(substr($row['area_renovasi'], 0, 30)) ?>
                                                            <?= strlen($row['area_renovasi']) > 30 ? '...' : '' ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            <?= htmlspecialchars(substr($row['jenis_pekerjaan'], 0, 25)) ?>
                                                            <?= strlen($row['jenis_pekerjaan']) > 25 ? '...' : '' ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_class = 'secondary';
                                                        if ($row['status'] == 'Pending') $badge_class = 'warning';
                                                        if ($row['status'] == 'Disetujui') $badge_class = 'success';
                                                        if ($row['status'] == 'Ditolak') $badge_class = 'danger';
                                                        if ($row['status'] == 'Selesai') $badge_class = 'info';
                                                        ?>
                                                        <span class="badge badge-<?= $badge_class ?> badge-sm">
                                                            <?= htmlspecialchars($row['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <!-- Detail Button -->
                                                            <a href="detail.php?no_pengajuan=<?= urlencode($row['no_pengajuan']) ?>" class="btn btn-outline-info btn-action"
                                                                title="Detail">
                                                                <i class="fas fa-eye fa-xs"></i>
                                                            </a>

                                                            <!-- Process Action untuk user yang punya permission approve -->
                                                            <?php if ($canApprove && $row['status'] == 'Pending'): ?>
                                                                <button type="button" class="btn btn-outline-primary btn-action"
                                                                    onclick="processAction('<?= $row['no_pengajuan'] ?>')" title="Proses Pengajuan">
                                                                    <i class="fas fa-cog fa-xs"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <!-- Edit untuk user yang mengajukan sendiri jika pending -->
                                                            <?php if ($row['status'] == 'Pending' && $row['user_id'] == $_SESSION['id_user']): ?>
                                                                <a href="edit.php?id=<?= $row['no_pengajuan'] ?>"
                                                                    class="btn btn-outline-warning btn-action" title="Edit">
                                                                    <i class="fas fa-edit fa-xs"></i>
                                                                </a>
                                                            <?php endif; ?>

                                                            <!-- Delete dengan aturan role -->
                                                            <?php
                                                            $can_delete = false;
                                                            if ($auth->isSuperAdmin()) {
                                                                // Super Admin bisa hapus semua
                                                                $can_delete = true;
                                                            } elseif ($row['status'] == 'Pending') {
                                                                // Jika status Pending
                                                                if ($row['user_id'] == $_SESSION['id_user']) {
                                                                    // User yang mengajukan bisa hapus pengajuan sendiri
                                                                    $can_delete = true;
                                                                } elseif ($canApprove || $auth->isHr() || $auth->isGa()) {
                                                                    // User dengan permission approve, HR, dan GA bisa hapus pengajuan Pending
                                                                    $can_delete = true;
                                                                }
                                                            }
                                                            ?>

                                                            <?php if ($can_delete): ?>
                                                                <button type="button" class="btn btn-outline-danger btn-action"
                                                                    onclick="deleteRenovasi('<?= $row['no_pengajuan'] ?>', '<?= $row['status'] ?>')" title="Hapus">
                                                                    <i class="fas fa-trash fa-xs"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('../layouts/footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-info-circle fa-sm"></i> Detail Pengajuan</h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="detailContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times fa-xs"></i> Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Action -->
    <div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="process_approval.php">
                    <input type="hidden" name="no_pengajuan" id="action_no_pengajuan" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="actionModalLabel">Proses Pengajuan</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    
                    <div class="modal-body">
                        <p>Anda akan memproses pengajuan dengan nomor: <strong id="display_no_pengajuan"></strong></p>
                        
                        <div class="form-group">
                            <label class="small font-weight-bold">Keputusan <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-6">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="action_approve"
                                            name="decision" value="approve" required>
                                        <label class="custom-control-label text-success" for="action_approve">
                                            <i class="fas fa-check-circle fa-sm"></i> Setujui
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" class="custom-control-input" id="action_reject"
                                            name="decision" value="reject" required>
                                        <label class="custom-control-label text-danger" for="action_reject">
                                            <i class="fas fa-times-circle fa-sm"></i> Tolak
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mt-3">
                            <label for="catatan_approval" class="small font-weight-bold">
                                Catatan <span id="catatan_required_indicator" class="text-danger" style="display:none;">*</span>
                            </label>
                            <textarea class="form-control" id="catatan_approval" name="catatan" rows="3"></textarea>
                            <small id="catatan_help" class="form-text text-muted">
                                Berikan alasan atau catatan untuk keputusan Anda
                            </small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Proses</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Submit (tambahkan sebelum penutup body) -->
    <div class="modal fade" id="confirmSubmitModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">
                        <i class="fas fa-question-circle fa-sm"></i> Konfirmasi
                    </h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="confirmYes">Ya, Lanjutkan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
        // Tunggu sampai semua script dimuat
        $(window).on('load', function() {
            // DataTable initialization - Nonaktifkan default ordering
            $('#renovasiTable').DataTable({
                "order": [], // Hapus default ordering
                "ordering": false, // Nonaktifkan sorting agar sesuai urutan database
                "pageLength": 10,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });

            // Manual bind modal close events
            $(document).on('click', '.modal .close, .modal [data-dismiss="modal"]', function() {
                $(this).closest('.modal').modal('hide');
            });

            // Handle modal backdrop click
            $(document).on('click', '.modal', function(e) {
                if (e.target === this) {
                    $(this).modal('hide');
                }
            });

            // Reset form saat modal ditutup
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('form')[0]?.reset();
            });
        });

        // Function untuk show detail
        function showDetail(no_pengajuan) {
            setTimeout(function() {
                if (typeof $ !== 'undefined') {
                    window.location.href = 'detail.php?id=' + no_pengajuan;
                }
            }, 100);
        }

        // Function untuk membuka modal aksi
        function processAction(no_pengajuan) {
            $('#action_no_pengajuan').val(no_pengajuan);
            $('#display_no_pengajuan').text(no_pengajuan);
            
            // Reset form
            $('input[name="decision"]').prop('checked', false);
            $('#catatan_approval').val('');
            $('#catatan_required_indicator').hide();
            
            // Show modal
            $('#actionModal').modal('show');
        }

        // Handle perubahan decision
        $('input[name="decision"]').on('change', function() {
            const decision = $(this).val();
            if (decision === 'reject') {
                $('#catatan_required_indicator').show();
                $('#catatan_approval').attr('required', true);
            } else {
                $('#catatan_required_indicator').hide();
                $('#catatan_approval').attr('required', false);
            }
        });

        // Validasi sebelum submit
        $('#actionModal form').on('submit', function(e) {
            const decision = $('input[name="decision"]:checked').val();
            const catatan = $('#catatan_approval').val().trim();
            
            if (!decision) {
                e.preventDefault();
                alert('Silakan pilih keputusan terlebih dahulu!');
                return false;
            }
            
            if (decision === 'reject' && catatan === '') {
                e.preventDefault();
                alert('Alasan penolakan wajib diisi!');
                $('#catatan_approval').focus();
                return false;
            }
            
            return true;
        });

        // Delete function
        function deleteRenovasi(no_pengajuan, status) {
            let confirmMessage = 'Apakah Anda yakin ingin menghapus pengajuan ini?';

            // Pesan konfirmasi berdasarkan status
            if (status === 'Disetujui') {
                confirmMessage = 'Pengajuan ini sudah DISETUJUI. Yakin ingin menghapus?\n\nTindakan ini tidak dapat dibatalkan!';
            } else if (status === 'Ditolak') {
                confirmMessage = 'Pengajuan ini sudah DITOLAK. Yakin ingin menghapus?\n\nTindakan ini tidak dapat dibatalkan!';
            }

            if (confirm(confirmMessage)) {
                // Tampilkan loading
                const btn = event.target.closest('button');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin fa-xs"></i>';
                btn.disabled = true;

                // Redirect ke delete.php
                window.location.href = 'delete.php?id=' + no_pengajuan;
            }
        }

        // Validasi sebelum submit - GUNAKAN MODAL KONFIRMASI
        $('#actionModal form').on('submit', function(e) {
            e.preventDefault(); // Prevent default submit
            
            const decision = $('input[name="decision"]:checked').val();
            const catatan = $('#catatan_approval').val().trim();
            const form = $(this);

            if (!decision) {
                alert('Silakan pilih keputusan terlebih dahulu!');
                return false;
            }

            if (decision === 'reject' && catatan === '') {
                alert('Alasan penolakan wajib diisi!');
                $('#catatan_approval').focus();
                return false;
            }

            // Set pesan konfirmasi
            const confirmMsg = decision === 'approve' ?
                'Apakah Anda yakin ingin menyetujui pengajuan ini?' :
                'Apakah Anda yakin ingin menolak pengajuan ini?';
            
            $('#confirmMessage').text(confirmMsg);
            
            // Tampilkan modal konfirmasi
            $('#confirmSubmitModal').modal('show');
            
            // Handle tombol Ya
            $('#confirmYes').off('click').on('click', function() {
                $('#confirmSubmitModal').modal('hide');
                // Submit form setelah modal tertutup
                setTimeout(function() {
                    form.off('submit').submit();
                }, 300);
            });
            
            return false;
        });

        // Force close function jika modal stuck
        function forceCloseModal() {
            $('.modal').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        }

        // Fallback close modal dengan vanilla JS
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal dengan vanilla JS
            document.querySelectorAll('.modal .close, .modal [data-dismiss="modal"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                });
            });

            // ESC key close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(function(modal) {
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    });
                }
            });
        });
    </script>

</body>

</html>