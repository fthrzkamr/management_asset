<?php
// lists.php - API endpoint untuk widget preview data
session_start();
include_once(__DIR__ . '/dist/config/config.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$widget = isset($_GET['widget']) ? $_GET['widget'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'preview';

$response = ['items' => []];

try {
    switch ($widget) {
        case 'asset':
            // Get latest 3 asset requests
            $query = "SELECT nama_barang, tanggal_pengajuan 
                     FROM pengajuan_asset 
                     ORDER BY tanggal_pengajuan DESC 
                     LIMIT 3";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $response['items'][] = [
                    'label' => $row['nama_barang'] . ' (' . date('d/m/Y', strtotime($row['tanggal_pengajuan'])) . ')'
                ];
            }
            break;

        case 'service_asset':
            // Get latest 3 service requests
            $query = "SELECT nama_barang, tanggal_service 
                     FROM service_asset 
                     ORDER BY tanggal_service DESC 
                     LIMIT 3";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $response['items'][] = [
                    'label' => $row['nama_barang'] . ' (' . date('d/m/Y', strtotime($row['tanggal_service'])) . ')'
                ];
            }
            break;

        case 'ppx_pending':
            // Get latest 3 pending PPX requests
            $query = "SELECT keterangan, tanggal_pengajuan 
                     FROM ppx_request 
                     WHERE status_approval_manager = 'pending' 
                     ORDER BY tanggal_pengajuan DESC 
                     LIMIT 3";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $response['items'][] = [
                    'label' => $row['keterangan'] . ' (' . date('d/m/Y', strtotime($row['tanggal_pengajuan'])) . ')'
                ];
            }
            break;

        case 'renovation':
            // Get latest 3 renovation requests
            $query = "SELECT nama_barang, tanggal_pengajuan 
                     FROM pengajuan_renovasi 
                     ORDER BY tanggal_pengajuan DESC 
                     LIMIT 3";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $response['items'][] = [
                    'label' => $row['nama_barang'] . ' (' . date('d/m/Y', strtotime($row['tanggal_pengajuan'])) . ')'
                ];
            }
            break;

        case 'ppx_uang':
            // Get latest 3 reimbursement PPX
            $query = "SELECT keterangan, tanggal_pengajuan 
                     FROM ppx_request 
                     WHERE jenis_ppx = 'Uang' 
                     ORDER BY tanggal_pengajuan DESC 
                     LIMIT 3";
            $result = mysqli_query($conn, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $response['items'][] = [
                    'label' => $row['keterangan'] . ' (' . date('d/m/Y', strtotime($row['tanggal_pengajuan'])) . ')'
                ];
            }
            break;

        default:
            $response['items'] = [];
            break;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
