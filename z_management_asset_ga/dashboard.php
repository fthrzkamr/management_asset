<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
   header("Location: login.php");
   exit();
}

// Check if user has permission
if (!$auth->hasPermission('management_asset_ga', 'read')) {
    $_SESSION['error'] = 'Akses ditolak untuk menu ini!';
    header("Location: ../index.php");
    exit();
}

// Additional check: ensure user with GA division is accessing correct dashboard
$user_divisi = $auth->getUserDivisi();
if ($auth->isKoordinator() && $user_divisi !== 6) {
   // Koordinator non-GA tidak boleh akses dashboard GA
   $_SESSION['error'] = 'Anda bukan divisi GA, Akses ditolak untuk menu ini!';
   header("Location: ../index.php");
   exit();
}

$allowed_limits = [5,10,25,50,100];
// Pagination settings - allow user to choose via GET `limit`
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 5; // Data per halaman (default 5)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Sorting settings
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Allowed columns for sorting (security)
$allowed_sort = ['user','kode_aset', 'nama_aset', 'kategori_aset', 'lokasi', 'merk', 'jumlah', 'kondisi', 'created_at'];
if (!in_array($sort_column, $allowed_sort)) {
   $sort_column = 'created_at';
}

// Fetch Cabang List
$cabang_list = [];
$q_cabg = mysqli_query($conn, "SELECT nama_cabang FROM master_cabang ORDER BY id_cabang ASC");
if($q_cabg) {
    while($row_c = mysqli_fetch_assoc($q_cabg)) {
        $cabang_list[] = $row_c['nama_cabang'];
    }
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = ''; // No filter - show all assets for GA department view

// Build search clause if user supplied a query.
if (!empty($search)) {
   $search_sql = "(kode_aset LIKE '%$search%' 
                   OR nama_aset LIKE '%$search%' 
                   OR `user` LIKE '%$search%'
                   OR kategori_aset LIKE '%$search%' 
                   OR lokasi LIKE '%$search%' 
                   OR merk LIKE '%$search%')";
   $where_clause = "WHERE " . $search_sql;
}

// Tambahan Filter Cabang dari Navbar Dropdown
$filter_cabang = isset($_GET['cabang']) ? mysqli_real_escape_string($conn, $_GET['cabang']) : '';
if (!empty($filter_cabang) && $filter_cabang !== 'Semua Cabang') {
   $cab_sql = "lokasi LIKE '%$filter_cabang%'";
   if (empty($where_clause)) {
      $where_clause = "WHERE " . $cab_sql;
   } else {
      $where_clause .= " AND " . $cab_sql;
   }
}

// Count total records
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_ga $where_clause");
if ($count_query) {
    $total_records = mysqli_fetch_assoc($count_query)['total'] ?? 0;
} else {
    $total_records = 0;
    // error_log("Error in count_query: " . mysqli_error($conn));
}
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

// Main query with pagination and sorting
$main_sql = "
   SELECT kode_aset, `user`, nama_aset, kategori_aset, lokasi, merk,
         jumlah, satuan, kondisi, status_barang, status_kepemilikan
   FROM asset_ga
   $where_clause
   ORDER BY $sort_column $sort_order
   LIMIT $limit OFFSET $offset
";
$query = mysqli_query($conn, $main_sql);

if (!$query) {
    // Tangani error query jika terjadi agar page tidak blank/warning error
    die("Error in main query: " . mysqli_error($conn));
}


// Function to create sort URL
function getSortUrl($column, $current_sort, $current_order)
{
   $new_order = ($current_sort == $column && $current_order == 'DESC') ? 'asc' : 'desc';
   $params = $_GET;
   $params['sort'] = $column;
   $params['order'] = $new_order;
   unset($params['page']); // Reset to first page when sorting
   return '?' . http_build_query($params);
}

// Function to get sort icon
function getSortIcon($column, $current_sort, $current_order)
{
   if ($current_sort == $column) {
      return $current_order == 'DESC' ? '<i class="fas fa-sort-down"></i>' : '<i class="fas fa-sort-up"></i>';
   }
   return '<i class="fas fa-sort"></i>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
   <title>Manajemen Aset - Departemen GA</title>
   <link rel="icon" href="../assets/img/dua.png" type="image/png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   
   <style>
      /* Mobile Responsive Styles */
      @media (max-width: 768px) {
         /* Page Header */
         .page-header h3.page-title {
            font-size: 1.2rem;
         }
         
         .breadcrumb {
            font-size: 0.85rem;
         }
         
         /* Button Group */
         .d-flex[style*="gap"] {
            flex-wrap: wrap;
         }
         
         .btn-sm {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
         }
         
         /* Search Bar */
         .input-group {
            flex-wrap: nowrap;
         }
         
         .form-control {
            font-size: 0.9rem;
         }
         
         /* Hide some columns on mobile */
         .table thead th:nth-child(3),  /* User */
         .table tbody td:nth-child(3),
         .table thead th:nth-child(4),  /* Kategori */
         .table tbody td:nth-child(4),
         .table thead th:nth-child(6),  /* Merk */
         .table tbody td:nth-child(6),
         .table thead th:nth-child(7),  /* Jumlah */
         .table tbody td:nth-child(7) {
            display: none;
         }
         
         /* Adjust visible columns */
         .table thead th,
         .table tbody td {
            font-size: 0.75rem;
            padding: 0.5rem 0.25rem;
            white-space: nowrap;
         }
         
         .table thead th:nth-child(2),  /* Nama Asset */
         .table tbody td:nth-child(2) {
            min-width: 120px;
            white-space: normal;
         }
         
         /* Action buttons */
         .btn-sm i {
            font-size: 0.8rem;
         }
         
         /* Pagination */
         .pagination {
            flex-wrap: wrap;
            font-size: 0.8rem;
         }
         
         .page-link {
            padding: 0.4rem 0.6rem;
         }
         
         /* Hide pagination text on very small screens */
         @media (max-width: 576px) {
            .pagination .page-link i + .d-inline-block {
               display: none !important;
            }
         }
         
         /* Modal */
         .modal-dialog {
            margin: 0.5rem;
         }
         
         .modal-lg {
            max-width: calc(100% - 1rem);
         }
         
         /* Cards */
         .card {
            margin-bottom: 1rem;
         }
         
         .card-body {
            padding: 0.75rem;
         }
         
         /* Form controls in search */
         .col-md-6.text-right {
            text-align: left !important;
            margin-top: 0.5rem;
         }
         
         /* Limit selector */
         select[name="limit"] {
            font-size: 0.85rem;
         }
         
         /* Quick page jump */
         .input-group-sm {
            width: 100% !important;
         }
         
         /* Table responsive wrapper */
         .table-responsive {
            -webkit-overflow-scrolling: touch;
         }
         
         /* Badge adjustments */
         .badge {
            font-size: 0.7rem;
            padding: 0.25em 0.5em;
         }
         
         /* Number column */
         .table tbody td:first-child {
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 1;
         }
         
         .table thead th:first-child {
            position: sticky;
            left: 0;
            background: #f8f9fa;
            z-index: 2;
         }
      }
      
      /* Tablet adjustments */
      @media (min-width: 769px) and (max-width: 991px) {
         .table thead th,
         .table tbody td {
            font-size: 0.85rem;
            padding: 0.6rem;
         }
      }
      
      /* Very small screens */
      @media (max-width: 576px) {
         .page-header h3.page-title {
            font-size: 1rem;
         }
         
         .btn-sm {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
         }
         
         /* Stack search and limit selector */
         .d-flex > div[style*="margin-left"] {
            margin-left: 0 !important;
            margin-top: 0.5rem;
            width: 100% !important;
         }
         
         .d-flex {
            flex-direction: column;
         }
         
         /* Hide total nilai on very small screens - removed column anyway */
      }
   </style>
</head>

<body>
   <div class="main-wrapper">
      <?php include '../layouts/navbar.php'; ?>

      <div class="page-wrapper">
         <div class="content container-fluid">
            <div class="page-header">
               <div class="row">
                  <div class="col-sm-12">
                     <h3 class="page-title">Manajemen Aset - Departemen GA</h3>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Manajemen Aset GA</li>
                     </ul>
                  </div>
               </div>
            </div>

            <!-- Tabel Asset -->
            <div class="row">
               <div class="col-sm-12">
                  <div class="card card-table">
                     <div class="card-body">
                        <div class="page-header">
                           <div class="row align-items-center">
                              <div class="col-md-12">
                                 <h3 class="page-title mb-2">Data Asset Departemen GA</h3>
                                 
                                 <!-- Button Group - Responsive -->
                                 <div class="d-flex flex-wrap align-items-center" style="gap: 10px;">
                                    <!-- Button Tambah Asset -->
                                    <a href="aset_add.php" class="btn btn-primary btn-sm">
                                       <i class="fas fa-plus fa-sm"></i> <span class="d-none d-sm-inline">Tambah </span>Asset
                                    </a>
                                    
                                    <!-- Button Download Excel -->
                                    <a href="aset_excel.php<?= !empty($filter_cabang) ? '?cabang=' . urlencode($filter_cabang) : '' ?>" class="btn btn-success btn-sm">
                                       <i class="fas fa-download fa-sm"></i> <span class="d-none d-sm-inline">Download </span>Excel
                                    </a>
                                 </div>
                              </div>
                           </div>
                        </div>

                        <!-- Search Bar - Responsive -->
                        <div class="row mb-3">
                           <div class="col-12 col-md-6 mb-2 mb-md-0">
                              <form method="GET" action="">
                                 <div class="d-flex flex-column flex-sm-row">
                                     <div class="flex-grow-1 d-flex align-items-center mb-2 mb-sm-0">
                                        <select name="cabang" class="form-control form-control-sm mr-2" style="max-width: 150px;" onchange="this.form.submit()">
                                           <option value="">Semua Cabang</option>
                                           <?php foreach($cabang_list as $cbg): ?>
                                              <option value="<?= htmlspecialchars($cbg) ?>" <?= $filter_cabang === $cbg ? 'selected' : '' ?>>
                                                 <?= htmlspecialchars($cbg) ?>
                                              </option>
                                           <?php endforeach; ?>
                                        </select>
                                        <div class="input-group input-group-sm">
                                           <input type="text" name="search" class="form-control" placeholder="Cari asset..." value="<?= htmlspecialchars($search) ?>">
                                           <?php if (isset($_GET['sort'])): ?>
                                              <input type="hidden" name="sort" value="<?= htmlspecialchars($_GET['sort']) ?>">
                                           <?php endif; ?>
                                           <?php if (isset($_GET['order'])): ?>
                                              <input type="hidden" name="order" value="<?= htmlspecialchars($_GET['order']) ?>">
                                           <?php endif; ?>
                                           <div class="input-group-append">
                                              <button type="submit" class="btn btn-outline-secondary">
                                                 <i class="fas fa-search"></i>
                                              </button>
                                              <?php if (!empty($search)): ?>
                                                 <a href="dashboard.php" class="btn btn-outline-danger">
                                                    <i class="fas fa-times"></i>
                                                 </a>
                                              <?php endif; ?>
                                           </div>
                                        </div>
                                     </div>
                                     <div class="ml-0 ml-sm-2" style="max-width: 80px;">
                                        <select name="limit" class="form-control form-control-sm" onchange="this.form.submit()">
                                           <?php foreach([5,10,25,50,100] as $l): ?>
                                              <option value="<?= $l ?>" <?= $l == $limit ? 'selected' : '' ?>><?= $l ?></option>
                                           <?php endforeach; ?>
                                        </select>
                                     </div>
                                  </div>
                              </form>
                           </div>
                           <div class="col-12 col-md-6 text-left text-md-right">
                              <small class="text-muted">
                                 <span class="d-inline d-sm-none">
                                    <?= $offset + 1 ?>-<?= min($offset + $limit, $total_records) ?>/<?= $total_records ?>
                                 </span>
                                 <span class="d-none d-sm-inline">
                                    Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> data
                                 </span>
                              </small>
                           </div>
                        </div>

                        <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
                           <div id="update-alert" class="alert alert-success fade show" role="alert">
                              Data berhasil diupdate!
                           </div>
                           <script>
                              setTimeout(function() {
                                 const alertBox = document.getElementById('update-alert');
                                 if (alertBox) {
                                    alertBox.classList.remove('show');
                                    alertBox.classList.add('fade');
                                    setTimeout(() => alertBox.remove(), 500);
                                 }
                              }, 3000);
                           </script>
                        <?php endif; ?>

                        <div class="table-responsive">
                           <table class="table table-hover table-center mb-0">
                              <thead class="thead-light">
                                 <tr>
                                    <th>No</th>
                                    <th>
                                       <a href="<?= getSortUrl('nama_aset', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Nama Asset <?= getSortIcon('nama_aset', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th>
                                       <a href="<?= getSortUrl('user', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          User <?= getSortIcon('user', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th>
                                       <a href="<?= getSortUrl('kategori_aset', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Kategori <?= getSortIcon('kategori_aset', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th>
                                       <a href="<?= getSortUrl('lokasi', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Lokasi <?= getSortIcon('lokasi', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th>
                                       <a href="<?= getSortUrl('merk', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Merk <?= getSortIcon('merk', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Kepemilikan</th>
                                    <th>
                                       <a href="<?= getSortUrl('kondisi', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Kondisi <?= getSortIcon('kondisi', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                     <th class="text-center">Action</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <?php
                                 $no = $offset + 1;
                                 if (mysqli_num_rows($query) > 0) {
                                    while ($row = mysqli_fetch_assoc($query)) {
                                       echo "<tr>";
                                       echo "<td>" . $no++ . "</td>";
                                       echo "<td>" . htmlspecialchars($row['nama_aset']) . "</td>";
                                       echo "<td>" . htmlspecialchars($row['user'] ?? '-') . "</td>";
                                       echo "<td>" . htmlspecialchars($row['kategori_aset']) . "</td>";
                                       echo "<td>" . htmlspecialchars($row['lokasi']) . "</td>";
                                       echo "<td>" . htmlspecialchars($row['merk'] ?: '-') . "</td>";
                                       // Badge status barang
                                       $status_val = $row['status_barang'] ?? 'Asset';
                                       $status_cl = ($status_val === 'Sewa') ? 'badge-warning' : 'badge-primary';
                                       echo "<td class='text-center'><span class='badge {$status_cl}'>" . htmlspecialchars($status_val) . "</span></td>";

                                       // Badge kepemilikan
                                       $kepemilikan_val = $row['status_kepemilikan'] ?? '';
                                       if (empty($kepemilikan_val)) {
                                          echo "<td class='text-center text-muted font-weight-bold'>-</td>";
                                       } else {
                                          $kep_cl = 'badge-info';
                                          switch ($kepemilikan_val) {
                                             case 'DFM': $kep_cl = 'badge-info'; break;
                                             case 'MPF': $kep_cl = 'badge-success'; break;
                                             case 'PLM': $kep_cl = 'badge-warning'; break;
                                             case 'DUI': $kep_cl = 'badge-primary'; break;
                                             default: $kep_cl = 'badge-secondary'; break;
                                          }
                                          echo "<td class='text-center'><span class='badge {$kep_cl}'>" . htmlspecialchars($kepemilikan_val) . "</span></td>";
                                       }

                                       // Badge kondisi dengan warna
                                       $kondisi_class = '';
                                       switch ($row['kondisi']) {
                                          case 'Baik':
                                             $kondisi_class = 'badge-success';
                                             break;
                                          case 'Rusak Ringan':
                                             $kondisi_class = 'badge-warning';
                                             break;
                                          case 'Rusak Berat':
                                             $kondisi_class = 'badge-danger';
                                             break;
                                          case 'Hilang':
                                             $kondisi_class = 'badge-dark';
                                             break;
                                          case 'Dihapus':
                                             $kondisi_class = 'badge-secondary';
                                             break;
                                          default:
                                             $kondisi_class = 'badge-secondary';
                                       }
                                       echo "<td><span class='badge {$kondisi_class}'>" . htmlspecialchars($row['kondisi']) . "</span></td>";



                                       echo "<td class='text-center'>
                                                <div >
                                                   <a href='#viewModal' 
                                                      data-toggle='modal' 
                                                      data-id='" . htmlspecialchars($row['kode_aset']) . "' 
                                                      class='btn btn-sm btn-info mr-1 view-asset'
                                                      title='Lihat Detail'>
                                                      <i class='fa-solid fa-eye'></i>
                                                   </a>
                                                   <a href='aset_edit.php?id=" . urlencode($row['kode_aset']) . "' 
                                                      class='btn btn-sm btn-warning'
                                                      title='Edit Asset'>
                                                      <i class='fa-solid fa-pen-to-square'></i>
                                                   </a>
                                                </div>
                                             </td>";
                                       echo "</tr>";
                                    }
                                 } else {
                                     echo "<tr><td colspan='10' class='text-center py-4'>";
                                    echo "<i class='fas fa-inbox fa-3x text-muted mb-3'></i><br>";
                                    echo "<span class='text-muted'>Tidak ada data yang ditemukan</span>";
                                    if (!empty($search)) {
                                       echo "<br><small>Coba ubah kata kunci pencarian</small>";
                                    }
                                    echo "</td></tr>";
                                 }
                                 ?>
                              </tbody>
                           </table>
                        </div>

                        <!-- Pagination - SELALU TAMPIL -->
                        <div class="row mt-3">
                           <div class="col-md-6">
                              <small class="text-muted">
                                 <i class="fas fa-info-circle"></i>
                                 Total <strong><?= $total_records ?></strong> data,
                                 halaman <strong><?= $page ?></strong> dari <strong><?= max(1, $total_pages) ?></strong>
                              </small>
                           </div>
                           <div class="col-md-6">
                              <nav aria-label="Page navigation">
                                 <ul class="pagination justify-content-end mb-0">
                                    <!-- Previous Button -->
                                    <?php if ($page > 1): ?>
                                       <?php
                                       $prev_params = $_GET;
                                       $prev_params['page'] = $page - 1;
                                       ?>
                                       <li class="page-item">
                                          <a class="page-link" href="?<?= http_build_query($prev_params) ?>" title="Halaman Sebelumnya">
                                             <i class="fas fa-chevron-left"></i> Previous
                                          </a>
                                       </li>
                                    <?php else: ?>
                                       <li class="page-item disabled">
                                          <span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span>
                                       </li>
                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <?php if ($total_pages > 0): ?>
                                       <?php
                                       $start_page = max(1, $page - 2);
                                       $end_page = min($total_pages, $page + 2);

                                       // First page
                                       if ($start_page > 1) {
                                          $first_params = $_GET;
                                          $first_params['page'] = 1;
                                          echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($first_params) . '">1</a></li>';
                                          if ($start_page > 2) {
                                             echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                          }
                                       }

                                       // Page range
                                       for ($i = $start_page; $i <= $end_page; $i++) {
                                          $page_params = $_GET;
                                          $page_params['page'] = $i;
                                          if ($i == $page) {
                                             echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                          } else {
                                             echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($page_params) . '">' . $i . '</a></li>';
                                          }
                                       }

                                       // Last page
                                       if ($end_page < $total_pages) {
                                          if ($end_page < $total_pages - 1) {
                                             echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                          }
                                          $last_params = $_GET;
                                          $last_params['page'] = $total_pages;
                                          echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($last_params) . '">' . $total_pages . '</a></li>';
                                       }
                                       ?>
                                    <?php else: ?>
                                       <li class="page-item active">
                                          <span class="page-link">1</span>
                                       </li>
                                    <?php endif; ?>

                                    <!-- Next Button -->
                                    <?php if ($page < $total_pages && $total_pages > 0): ?>
                                       <?php
                                       $next_params = $_GET;
                                       $next_params['page'] = $page + 1;
                                       ?>
                                       <li class="page-item">
                                          <a class="page-link" href="?<?= http_build_query($next_params) ?>" title="Halaman Selanjutnya">
                                             Next <i class="fas fa-chevron-right"></i>
                                          </a>
                                       </li>
                                    <?php else: ?>
                                       <li class="page-item disabled">
                                          <span class="page-link">Next <i class="fas fa-chevron-right"></i></span>
                                       </li>
                                    <?php endif; ?>
                                 </ul>
                              </nav>
                           </div>
                        </div>

                        <!-- Quick Page Jump -->
                        <?php if ($total_pages > 5): ?>
                           <div class="row mt-2">
                              <div class="col-md-12 text-center">
                                 <form method="GET" class="d-inline-block">
                                    <!-- Preserve other parameters -->
                                    <?php foreach ($_GET as $key => $value): ?>
                                       <?php if ($key !== 'page'): ?>
                                          <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                       <?php endif; ?>
                                    <?php endforeach; ?>

                                    <div class="input-group input-group-sm" style="width: 200px;">
                                       <div class="input-group-prepend">
                                          <span class="input-group-text">Ke halaman:</span>
                                       </div>
                                       <input type="number" name="page" class="form-control"
                                          min="1" max="<?= $total_pages ?>"
                                          value="<?= $page ?>"
                                          placeholder="<?= $page ?>">
                                       </div><div class="input-group-append">
                                          <button type="submit" class="btn btn-outline-secondary">Go</button>
                                       </div>
                                    </div>
                                 </form>
                              </div>
                           </div>
                        <?php endif; ?>

                     </div>
                  </div>
               </div>
            </div>

            <!-- Modal View Detail -->
            <div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
               <div class="modal-dialog modal-lg" role="document">
                  <div class="modal-content">
                     <div class="modal-header">
                        <h5 class="modal-title" id="viewModalLabel">
                           <i class="fas fa-info-circle"></i> Detail Asset
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                           <span aria-hidden="true">&times;</span>
                        </button>
                     </div>
                     <div class="modal-body" id="modalContent">
                        <!-- Content akan diload via AJAX -->
                        <div class="text-center">
                           <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
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
         // Debug Bootstrap components
         // Library checks removed

         // Handle view modal
         $(document).on('click', '.view-asset', function(e) {
            e.preventDefault();
            var assetId = $(this).data('id');

            $('#modalContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br><small class="text-muted mt-2">Memuat data...</small></div>');

            $.ajax({
               url: 'aset_detail.php',
               type: 'GET',
               data: {
                  id: assetId
               },
               success: function(response) {
                  $('#modalContent').html(response);
               },
               error: function(xhr, status, error) {
                  $('#modalContent').html(
                     '<div class="alert alert-danger">' +
                     '<i class="fas fa-exclamation-triangle"></i> ' +
                     'Gagal memuat detail asset.<br>' +
                     '<small>Error: ' + error + '</small>' +
                     '</div>'
                  );
               }
            });
         });

         // Auto-submit search after 2000ms delay
         let searchTimeout;
         $('input[name="search"]').on('input', function() {
            clearTimeout(searchTimeout);
            const form = $(this).closest('form');
            searchTimeout = setTimeout(function() {
               form.submit();
            }, 2000);
         });

         // Restore focus to search input and set cursor to the end
         const searchInput = $('input[name="search"]');
         if (searchInput.length && searchInput.val().length > 0) {
            searchInput.focus();
            const val = searchInput.val();
            searchInput.val('');
            searchInput.val(val);
         }

         // Tooltip for action buttons
         $('[title]').tooltip();
      });
   </script>
</body>

</html>
