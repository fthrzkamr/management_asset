<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { exit("Unauthorized"); }
if (!$auth->hasPermission('master_departemen', 'read')) { exit("Access Denied"); }

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$totalData = mysqli_query($conn, "SELECT COUNT(*) AS total FROM departemen");
$rowTotal = mysqli_fetch_assoc($totalData);
$total = $rowTotal['total'];
$pages = ceil($total / $limit);

$query = mysqli_query($conn, "SELECT * FROM departemen ORDER BY id_departemen ASC LIMIT $start, $limit");
$no = $start + 1;
?>

<div class="table-responsive">
    <table class="table table-hover table-center mb-0">
        <thead class="thead-light">
            <tr>
                <th style="width: 1%;">No</th>
                <th class="text-center">Nama Departemen</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td class="text-center"><?= htmlspecialchars($row['nama_departemen']); ?></td>
                    <td class="text-center">
                        <?php if ($auth->hasPermission('master_departemen', 'update')): ?>
                            <a href="depart_edit.php?id=<?= $row['id_departemen']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if ($auth->hasPermission('master_departemen', 'delete')): ?>
                            <a href="depart_delete.php?id=<?= $row['id_departemen']; ?>" class="btn btn-sm btn-danger btn-delete-confirm" data-message="Yakin ingin menghapus departemen ini?"><i class="fas fa-trash-alt"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<nav class="py-1">
    <ul class="pagination justify-content-start pagination-departemen">
        <!-- <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="#" data-page="<?= $page - 1 ?>">Previous</a></li>
        <?php endif; ?> -->
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <!-- <?php if ($page < $pages): ?>
            <li class="page-item"><a class="page-link" href="#" data-page="<?= $page + 1 ?>">Next</a></li>
        <?php endif; ?> -->
    </ul>
</nav>
