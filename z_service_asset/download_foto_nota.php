<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\download_foto_nota.php
include('../dist/config/config.php');

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID service tidak ditemukan!');
}

$service_id = mysqli_real_escape_string($conn, $_GET['id']);

// Query untuk mendapatkan info foto nota
$query = mysqli_query($conn, "
    SELECT 
        s.id_service,
        s.no_service,
        s.foto_nota,
        s.status_service,
        a.nama_aset
    FROM service_asset s
    LEFT JOIN asset_dfm a ON s.kode_aset = a.kode_aset
    WHERE s.id_service = '$service_id' 
    AND s.status_service = 'Selesai'
    AND s.foto_nota IS NOT NULL 
    AND s.foto_nota != ''
    LIMIT 1
");

if (!$query || mysqli_num_rows($query) == 0) {
    die('Data foto nota tidak ditemukan atau service belum selesai!');
}

$service = mysqli_fetch_assoc($query);

// Path file foto nota
$foto_nota_path = '../uploads/nota/' . $service['foto_nota'];

// Cek apakah file exists
if (!file_exists($foto_nota_path)) {
    die('File foto nota tidak ditemukan di server!');
}

// Get file info
$file_info = pathinfo($foto_nota_path);
$file_extension = strtolower($file_info['extension']);
$file_size = filesize($foto_nota_path);

// Validasi ekstensi file
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
if (!in_array($file_extension, $allowed_extensions)) {
    die('Format file tidak didukung!');
}

// Generate nama file untuk download
$download_filename = 'Nota_' . $service['no_service'] . '_' . $service['nama_aset'] . '.' . $file_extension;
$download_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $download_filename); // Clean filename

// Set content type berdasarkan ekstensi
$content_types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'bmp'  => 'image/bmp',
    'webp' => 'image/webp'
];

$content_type = $content_types[$file_extension] ?? 'application/octet-stream';

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers untuk download
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $download_filename . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=0, no-cache, must-revalidate');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

// Flush system output buffer
flush();

// Read file dan kirim ke browser
if ($file_size > 0) {
    $file_handle = fopen($foto_nota_path, 'rb');
    if ($file_handle) {
        // Read file in chunks untuk file besar
        while (!feof($file_handle) && connection_status() == 0) {
            echo fread($file_handle, 8192); // 8KB chunks
            flush();
        }
        fclose($file_handle);
    } else {
        die('Tidak dapat membaca file!');
    }
} else {
    die('File kosong!');
}

// Log download activity (optional)
$log_query = mysqli_query($conn, "
    INSERT INTO activity_log (user_id, activity, description, created_at) 
    VALUES (
        '{$_SESSION['id_user']}', 
        'Download Foto Nota', 
        'Download foto nota untuk service: {$service['no_service']}', 
        NOW()
    )
");

exit();
?>