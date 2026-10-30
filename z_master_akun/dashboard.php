<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('master_akun', 'read');
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row">
                        <div class="col-sm-12">
                            <h3 class="page-title">Master Akun</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Master Akun</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 d-flex">
                        <div class="card flex-fill card-table">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Daftar Akun</h5>
                                <?php if ($auth->hasPermission('master_akun', 'create')): ?>
                                <a href="master_akun_add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Akun
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div id="akun-content"></div> <!-- hasil AJAX akun -->
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script>
        function loadAkun(page = 1) {
            $.get("load_master_akun.php?page=" + page, function(data) {
                $("#akun-content").html(data);
            });
        }

        $(document).ready(function() {
            loadAkun();

            $(document).on('click', '.pagination-akun a', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                loadAkun(page);
            });
        });
    </script>

</body>
</html>