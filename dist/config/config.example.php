<?php
  if (session_status() === PHP_SESSION_NONE) {
      $session_lifetime = 28800;
      ini_set('session.gc_maxlifetime', $session_lifetime);
      ini_set('session.cookie_lifetime', $session_lifetime);
      session_set_cookie_params([
          'lifetime' => $session_lifetime,
          'path' => '/',
          'domain' => '', 
          'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), 
          'httponly' => true,
          'samesite' => 'Lax'
      ]);
      session_start();
  }

  // Set Timezone
  date_default_timezone_set('Asia/Jakarta');

  // Konfigurasi Database Hosting / cPanel
  $dbhost = "localhost";
  $dbuser = "cpanel_user";       // Ganti dengan user database cPanel Anda
  $dbpass = "password_rahasia";  // Ganti dengan password database cPanel Anda
  $dbname = "cpanel_dbname";     // Ganti dengan nama database cPanel Anda

  $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

  if (!$conn) {
      die("Tidak dapat terhubung ke database: " . mysqli_connect_error());
  }

  mysqli_set_charset($conn, 'utf8mb4');
  mysqli_query($conn, "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_general_ci'");
  mysqli_query($conn, "SET collation_connection = 'utf8mb4_general_ci'");
  mysqli_query($conn, "SET time_zone = '+07:00'");

  // Define BASE_URL globally
  if (!defined('BASE_URL')) {
      $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
      $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
      
      // Jika di root domain / subdomain utama (contoh: https://asset.namadomain.com), biarkan kosong: $path = '';
      // Jika di subfolder (contoh: https://namadomain.com/asset_dfm), isi: $path = '/asset_dfm';
      $path = ''; 
      
      define('BASE_URL', $protocol . '://' . $host . $path);
  }

  define('MAINTENANCE_MODE', 0);

  if (MAINTENANCE_MODE == 1) {
      $current_page = basename($_SERVER['PHP_SELF']);
      if ($current_page !== 'maintenance.php') {
          header("Location: " . BASE_URL . "/maintenance.php");
          exit();
      }
  }
