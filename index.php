<?php
include('dist/config/config.php');
include('dist/config/auth_helper.php');

$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_role = $auth->getUserRole();
$user_name = $_SESSION['nama_user'] ?? 'User';
$user_id = (int) ($_SESSION['id_user'] ?? 0);
$user_divisi = (int) ($_SESSION['divisi'] ?? 0);
$norm_role = strtolower(str_replace(' ', '_', $user_role));


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Dashboard - DFM</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/dua.png">
    <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .widget-preview { max-height: 56px; overflow: hidden; margin-top: 0.5rem; padding-left: 0; }
        .widget-preview li { font-size: 0.9rem; line-height: 1.1; list-style: none; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
        .section-title { margin-top: 1rem; margin-bottom: .5rem; font-weight: 600; }
        .widget-card { border-radius: .5rem; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include 'layouts/navbar.php'; ?>
        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row">
                        <div class="col-sm-12">
                            <h3 class="page-title">Dashboard</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Selamat Datang</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5>
                                            <i class="fas fa-user-circle text-primary"></i>
                                            <?= htmlspecialchars($user_name) ?>
                                        </h5>
                                        <p>Sistem Manajemen Asset Digital Farmasi - Dua Farma Groups</p>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <p class="text-muted">
                                            <i class="fas fa-clock"></i>
                                            <?= date('d F Y, H:i') ?> WIB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include 'layouts/footer.php'; ?>
            </div>
        </div>
    </div>
    <script>
        setInterval(function() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const timeString = now.toLocaleDateString('id-ID', options) + ' WIB';
            const clockElem = document.querySelector('.text-muted .fas.fa-clock');
            if (clockElem && clockElem.parentNode) {
                clockElem.parentNode.innerHTML = '<i class="fas fa-clock"></i> ' + timeString;
            }
        }, 1000);
    </script>
</body>
</html>
