<?php
// filepath: c:\xampp\htdocs\asset_dfm\login_auth.php
include 'dist/config/config.php';
include 'dist/config/auth_helper.php';

if (isset($_POST['login'])) {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        header("Location: login.php?err=empty");
        exit();
    } else {
        $username = htmlentities(trim(strip_tags($_POST['username'])));
        $password = htmlentities(trim(strip_tags($_POST['password'])));

        // Escape input to prevent SQL injection
        $username_esc = mysqli_real_escape_string($conn, $username);
        $sql = "SELECT u.*, r.nama_role FROM user_dfm u LEFT JOIN roles r ON u.id_role = r.id_role WHERE u.username = '$username_esc'";
        $result = mysqli_query($conn, $sql);

        // Check for query errors
        if ($result === false) {
            error_log('[Login Error] Query: ' . $sql . ' | Error: ' . mysqli_error($conn));
            header("Location: login.php?err=dberror");
            exit();
        }

        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            // Gunakan pencocokan langsung karena password tidak di-hash
            if ($password === $user['password']) {
                $_SESSION['id_user'] = $user['id_user'];
                $_SESSION['nama_user'] = $user['nama_user'];
                $_SESSION['id_role'] = $user['id_role'];
                $_SESSION['nama_role'] = $user['nama_role'];
                $_SESSION['divisi'] = $user['divisi'] ?? null;
                $_SESSION['cabang'] = $user['id_cabang'] ?? null; 
                $_SESSION['id_cabang'] = $user['id_cabang'] ?? null; // Added for consistency

                // SEMUA ROLE REDIRECT KE INDEX.PHP
                $user_role = $user['nama_role'] ?? 'koordinator';
                
                switch ($user_role) {
                    case 'koordinator':
                        header("Location: index.php");
                        break;
                        
                    case 'hr':
                        header("Location: index.php");
                        break;
                        
                    case 'GA':
                        header("Location: index.php");
                        break;
                        
                    case 'kacab':
                        header("Location: index.php");
                        break;
                        
                    case 'direktur':
                        header("Location: index.php");
                        break;
                        
                    case 'finance':
                        header("Location: index.php");
                        break;
                        
                    case 'manag_finance':
                        header("Location: index.php");
                        break;
                        
                    case 'super_admin':
                        header("Location: index.php");
                        break;
                        
                    default:
                        header("Location: index.php");
                        break;
                }
                exit();
            } else {
                header("Location: login.php?err=wrongpass");
                exit();
            }
        } else {
            header("Location: login.php?err=nouser");
        }
    }
} else {
    header("Location: login.php");
    exit();
}
?>