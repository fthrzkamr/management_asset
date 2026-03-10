<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
   header("Location: login.php");
   exit();
}

// Check if user has permission to create assets
$auth->requirePermission('management_asset', 'create');

$allowed_limits = [5,10,25,50,100];
// Pagination settings - allow user to choose via GET `limit`
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowed_limits) ? (int)$_GET['limit'] : 5; // Data per halaman (default 5)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Sorting settings
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Allowed columns for sorting (security)
$allowed_sort = ['user','kode_aset', 'nama_aset', 'kategori_aset', 'lokasi', 'merk', 'jumlah', 'kondisi', 'total_nilai_pengadaan', 'created_at'];
if (!in_array($sort_column, $allowed_sort)) {
   $sort_column = 'created_at';
}

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = '';

// Show all assets for Management Asset (no per-user filter).
// Build search clause if user supplied a query.
if (!empty($search)) {
   $search_sql = "(kode_aset LIKE '%$search%' 
                   OR nama_aset LIKE '%$search%' 
                   OR kategori_aset LIKE '%$search%' 
                   OR lokasi LIKE '%$search%' 
                   OR merk LIKE '%$search%')";
   $where_clause = "WHERE " . $search_sql;
}

// Tambahan Filter Cabang dari Navbar Dropdown
$filter_cabang = isset($_GET['cabang']) ? mysqli_real_escape_string($conn, $_GET['cabang']) : '';
if (!empty($filter_cabang)) {
   $cab_sql = "lokasi LIKE '%$filter_cabang%'";
   if (empty($where_clause)) {
      $where_clause = "WHERE " . $cab_sql;
   } else {
      $where_clause .= " AND " . $cab_sql;
   }
}

// Count total records
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_dfm $where_clause");
$total_records = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_records / $limit);

// Main query with pagination and sorting
$query = mysqli_query($conn, "
   SELECT kode_aset, `user`, nama_aset, kategori_aset, lokasi, merk, 
         jumlah, satuan, kondisi, total_nilai_pengadaan
   FROM asset_dfm 
   $where_clause
   ORDER BY $sort_column $sort_order
   LIMIT $limit OFFSET $offset
");

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
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Dashboard - Asset Management</title>
   <link rel="icon" href="../assets/img/dua.png" type="image/png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>
      /* Make the Action column sticky so buttons remain visible while scrolling */
      .table-responsive { position: relative; }
      .table .action-col {
         position: -webkit-sticky;
         position: sticky;
         right: 0;
         background: #ffffff;
         z-index: 5;
         box-shadow: -6px 0 6px -6px rgba(0,0,0,0.08);
         vertical-align: middle;
      }
      .table thead .action-col { z-index: 6; }
      .action-buttons { display: inline-flex; gap: 6px; align-items: center; }
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
                     <h3 class="page-title">Dashboard Menambah Asset!</h3>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item active">Dashboard</li>
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
                                 <h3 class="page-title mb-2">Data Asset</h3>
                                 
                                 <!-- Button Group - Berjejer Horizontal -->
                                 <div class="d-flex align-items-center" style="gap: 10px;">
                                    <!-- Button Tambah Asset -->
                                    <a href="aset_add.php" class="btn btn-primary btn-sm">
                                       <i class="fas fa-plus fa-sm"></i> Tambah Asset
                                    </a>
                                    
                                    <!-- Button Download Excel -->
                                    <a href="aset_excel.php<?= !empty($filter_cabang) ? '?cabang=' . urlencode($filter_cabang) : '' ?>" class="btn btn-success btn-sm">
                                       <i class="fas fa-download fa-sm"></i> Download Excel
                                    </a>
                                 </div>
                              </div>
                           </div>
                        </div>

                        <!-- Search Bar -->
                        <div class="row mb-3">
                           <div class="col-md-6">
                              <form method="GET" action="">
                                 <div class="d-flex">
                                    <div style="flex:1;">
                                       <div class="input-group">
                                          <input type="text" name="search" class="form-control" placeholder="Cari asset..." value="<?= htmlspecialchars($search) ?>">
                                          <!-- Preserve sort parameters & cabang -->
                                          <?php if (isset($_GET['cabang'])): ?>
                                             <input type="hidden" name="cabang" value="<?= htmlspecialchars($_GET['cabang']) ?>">
                                          <?php endif; ?>
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

                                    <div style="margin-left:10px; width:120px;">
                                       <select name="limit" class="form-control form-control-sm" onchange="this.form.submit()">
                                          <?php foreach([5,10,25,50,100] as $l): ?>
                                             <option value="<?= $l ?>" <?= $l == $limit ? 'selected' : '' ?>><?= $l ?></option>
                                          <?php endforeach; ?>
                                       </select>
                                    </div>
                                 </div>
                              </form>
                           </div>
                           <div class="col-md-6 text-right">
                              <small class="text-muted">
                                 Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> data
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
                                    <th>
                                       <a href="<?= getSortUrl('jumlah', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Jumlah <?= getSortIcon('jumlah', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th>
                                       <a href="<?= getSortUrl('kondisi', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Kondisi <?= getSortIcon('kondisi', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th>
                                       <a href="<?= getSortUrl('total_nilai_pengadaan', $sort_column, $sort_order) ?>" class="text-dark text-decoration-none">
                                          Total Nilai <?= getSortIcon('total_nilai_pengadaan', $sort_column, $sort_order) ?>
                                       </a>
                                    </th>
                                    <th class="text-center action-col">Action</th>
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
                                       echo "<td>" . number_format($row['jumlah'], 0, ',', '.') . " " . htmlspecialchars($row['satuan']) . "</td>";

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

                                       echo "<td class='text-right'><strong>Rp " . number_format($row['total_nilai_pengadaan'], 0, ',', '.') . "</strong></td>";
                                       echo "<td class='text-center action-col'>
                                                <div class='action-buttons'>
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
                                       <div class="input-group-append">
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

            <!-- Floating Action Button removed as requested -->
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

         // Auto-submit search after 500ms delay
         let searchTimeout;
         $('input[name="search"]').on('input', function() {
            clearTimeout(searchTimeout);
            const form = $(this).closest('form');
            searchTimeout = setTimeout(function() {
               form.submit();
            }, 500);
         });

         // Tooltip for action buttons
         $('[title]').tooltip();
      });
   </script>
</body>

</html>