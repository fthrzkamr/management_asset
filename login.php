<?php
// filepath: c:\xampp\htdocs\asset_dfm\login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DF GROUP</title>
    <link href="assets/css/login.css" rel="stylesheet" type="text/css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.7/dist/sweetalert2.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="d-flex justify-content-center align-items-center min-vh-100">
    <div class="container-fluid px-2 px-sm-3 px-md-4 px-lg-5" style="max-width: 1200px;">
      <div class="card card0 border-0 w-100 shadow-lg">
        <div class="row no-gutters">
          <!-- PERBAIKAN: Tampilkan gambar di desktop -->
          <div class="col-lg-6 d-none d-lg-block">
            <div class="card1 pb-5 h-100">
              <div class="row px-3 justify-content-center align-items-center h-100">
                <div class="text-center">
                  <!-- PERBAIKAN: Perbesar gambar untuk desktop -->
                  <img src="https://i.imgur.com/uNGdWHi.png" class="img-fluid" style="max-width: 500px; width: 100%;">
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-12 col-lg-6">
            <div class="card2 card border-0 px-3 px-sm-4 py-4 py-sm-5">
              
              <!-- PERBAIKAN: Tampilkan gambar di mobile (di atas form) -->
              <div class="text-center mb-4 d-block d-lg-none">
                <img src="https://i.imgur.com/uNGdWHi.png" class="img-fluid" style="max-width: 150px; width: auto;">
              </div>
              
              <!-- 2 baris teks di tengah dengan responsive font -->
              <div class="text-center mb-4">
                <h4 class="mb-1 d-none d-sm-block">Asset & Budget Management System</h4>
                <h4 class="mb-0 d-none d-sm-block">DF GROUP</h4>
                <!-- Mobile version dengan font lebih kecil -->
                <h5 class="mb-1 d-block d-sm-none">Asset & Budget Management System</h5>
                <h5 class="mb-0 d-block d-sm-none">DF GROUP</h5>
              </div>

              <?php if (isset($_GET['err'])): ?>
                <script>
                  document.addEventListener('DOMContentLoaded', function () {
                    <?php if ($_GET['err'] == 'empty'): ?>
                      Swal.fire({
                        icon: 'warning',
                        title: 'Oops!',
                        text: 'Username dan password harus diisi.'
                      });
                    <?php elseif ($_GET['err'] == 'wrongpass'): ?>
                      Swal.fire({
                        icon: 'error',
                        title: 'Login Gagal',
                        text: 'Username atau password salah.'
                      });
                    <?php elseif ($_GET['err'] == 'nouser'): ?>
                      Swal.fire({
                        icon: 'error',
                        title: 'Akun Tidak Ditemukan',
                        text: 'Silakan cek kembali username Anda.'
                      });
                    <?php elseif ($_GET['err'] == 'dberror'): ?>
                      Swal.fire({
                        icon: 'error',
                        title: 'Database Error',
                        text: 'Terjadi masalah pada koneksi database. Hubungi IT Admin.'
                      });
                    <?php endif; ?>
                  });
                </script>
              <?php endif; ?>

              <form action="login_auth.php" method="POST">
                <div class="form-group px-0 px-sm-3">
                  <label for="username" class="mb-1"><h6 class="mb-0">Username</h6></label>
                  <input class="form-control" type="text" id="username" name="username" placeholder="Masukan Username" required>
                </div>

                <div class="form-group px-0 px-sm-3">
                  <label for="password" class="mb-1"><h6 class="mb-0">Password</h6></label>
                  <input class="form-control" type="password" id="password" name="password" placeholder="Masukan Password" required>
                </div>

                <div class="d-flex justify-content-between align-items-center px-0 px-sm-3 mb-4">
                  <div class="custom-control custom-checkbox">
                    <input id="chk1" type="checkbox" name="remember" class="custom-control-input"> 
                    <label for="chk1" class="custom-control-label small">Remember me</label>
                  </div>
                </div>

                <div class="px-0 px-sm-3 mb-3">
                  <button type="submit" name="login" class="btn btn-primary btn-block py-2">
                    <i class="fa fa-sign-in mr-2"></i>Login
                  </button>
                </div>



              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</html>