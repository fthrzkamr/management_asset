<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Determine base path for includes
$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

// Create auth instance
$auth = new AuthHelper($conn);

// Check login
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check permission for ATK Approval
$auth->requirePermission('inventory_atk_pengambilan', 'approve');

$page_title = "Dashboard Pengajuan ATK";

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$sql = "SELECT 
            p.id_pengambilan,
            p.nomor_pengambilan,
            p.nama_pengambil,
            p.divisi,
            p.tanggal_pengambilan,
            p.status,
            COUNT(pd.id_detail) as total_items
        FROM atk_pengambilan p
        LEFT JOIN atk_pengambilan_detail pd ON p.id_pengambilan = pd.id_pengambilan
        WHERE 1=1";

if ($status_filter != 'All') {
    $sql .= " AND p.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

if (!empty($search)) {
    $sql .= " AND (p.nomor_pengambilan LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' 
              OR p.nama_pengambil LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'
              OR p.divisi LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
}

$sql .= " GROUP BY p.id_pengambilan 
          ORDER BY p.tanggal_pengambilan DESC";

$result = mysqli_query($conn, $sql);

// Check for query errors
if (!$result) {
    die("Error in main query: " . mysqli_error($conn));
}

// Get statistics
$stats_sql = "SELECT 
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected
              FROM atk_pengambilan";
$stats_result = mysqli_query($conn, $stats_sql);

// Check for query errors
if (!$stats_result) {
    die("Error in stats query: " . mysqli_error($conn));
}

$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - DFM</title>

    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/dua.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/plugins/datatables/datatables.min.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">

                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Dashboard Pengajuan ATK</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Pengajuan ATK</li>
                            </ul>
                        </div>
                        <div class="col-auto">
                            <a href="form_public.php" class="btn btn-primary" target="_blank">
                                <i class="fas fa-plus"></i> Form Public
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-1">Menunggu Approval</h6>
                                        <h2 class="text-white mb-0"><?= $stats['pending'] ?></h2>
                                    </div>
                                    <div class="avatar-lg">
                                        <i class="fas fa-clock fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-1">Disetujui</h6>
                                        <h2 class="text-white mb-0"><?= $stats['approved'] ?></h2>
                                    </div>
                                    <div class="avatar-lg">
                                        <i class="fas fa-check-circle fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white mb-1">Ditolak</h6>
                                        <h2 class="text-white mb-0"><?= $stats['rejected'] ?></h2>
                                    </div>
                                    <div class="avatar-lg">
                                        <i class="fas fa-times-circle fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control" onchange="this.form.submit()">
                                            <option value="All" <?= $status_filter == 'All' ? 'selected' : '' ?>>Semua Status</option>
                                            <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pencarian</label>
                                        <input type="text" name="search" class="form-control"
                                            placeholder="Cari nomor, nama, atau divisi..."
                                            value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-search"></i> Cari
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="tablePengajuan">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal</th>
                                                <th>Nama Pemohon</th>
                                                <th>Divisi</th>
                                                <th>Total Item</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            while ($row = mysqli_fetch_assoc($result)):
                                                $badge_class = '';
                                                switch ($row['status']) {
                                                    case 'Pending':
                                                        $badge_class = 'badge-warning';
                                                        break;
                                                    case 'Approved':
                                                        $badge_class = 'badge-success';
                                                        break;
                                                    case 'Rejected':
                                                        $badge_class = 'badge-danger';
                                                        break;
                                                }
                                            ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal_pengambilan'])) ?></td>
                                                    <td><?= $row['nama_pengambil'] ?></td>
                                                    <td><span class="badge badge-info"><?= $row['divisi'] ?></span></td>
                                                    <td>
                                                        <?= $row['total_items'] ?> item
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= $badge_class ?>">
                                                            <?= $row['status'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="detail.php?id=<?= $row['id_pengambilan'] ?>"
                                                            class="btn btn-sm btn-info" title="Detail">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($row['status'] == 'Pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" onclick="approveNow(<?= $row['id_pengambilan'] ?>, 'Approved')" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                onclick="approveNow(<?= $row['id_pengambilan'] ?>, 'Rejected')"
                                                                title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
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

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>

    </div>

    <!-- <script src="../assets/js/jquery-3.6.0.min.js"></script> -->
    <!-- <script src="../assets/js/popper.min.js"></script> -->
    <!-- <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script> dihapus karena sudah ada di footer -->
    <!-- <script src="../assets/js/script.js"></script> -->
    <script src="../assets/plugins/datatables/datatables.min.js"></script>

    <!-- script moved below modals to ensure modal elements exist before initialization -->

    <script>
        $(document).ready(function() {
            $('#tablePengajuan').DataTable({
                "pageLength": 25,
                "order": [
                    [2, "desc"]
                ]
            });
        });

        // Direct approve without modal
        window.approveNow = function(id, status) {
            const data = new URLSearchParams();
            data.append('id_pengambilan', id);
            data.append('status', status);

            console.log('approveNow sending', id, status);
            fetch('process_approve.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: data.toString()
            })
            .then(async res => {
                console.log('response status:', res.status, 'ok:', res.ok);
                const text = await res.text();
                console.log('response text:', text);
                try {
                    const j = JSON.parse(text);
                    if (j.success) {
                        // reload to reflect changes
                        window.location.href = 'dashboard.php';
                    } else {
                        alert('Gagal: ' + (j.message || text));
                    }
                } catch (e) {
                    if (res.ok) window.location.href = 'dashboard.php';
                    else alert('Terjadi kesalahan. Cek console.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Gagal menghubungi server. Cek console.');
            });
        }
    </script>
</body>

</html>