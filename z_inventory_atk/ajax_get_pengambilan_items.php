<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_path = dirname(dirname(__FILE__));
require_once $base_path . '/dist/config/config.php';
require_once $base_path . '/dist/config/auth_helper.php';

$auth = new AuthHelper($conn);
$auth->requirePermission('inventory_atk_pengambilan', 'read');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql_detail = "SELECT pd.id_barang, pd.qty_diminta as jumlah, i.kode_barang, i.nama_barang, i.satuan, 
                      COALESCE(s.quantity, 0) as stok_aktual
               FROM atk_pengambilan_detail pd
               JOIN inventory_atk i ON pd.id_barang = i.id_barang
               LEFT JOIN inventory_stok s ON i.id_barang = s.id_barang
               WHERE pd.id_pengambilan = ?";
$stmt_detail = mysqli_prepare($conn, $sql_detail);
mysqli_stmt_bind_param($stmt_detail, 'i', $id);
mysqli_stmt_execute($stmt_detail);
$items = mysqli_stmt_get_result($stmt_detail);

$html = '<table class="table table-striped table-sm">';
$html .= '<thead><tr><th>Kode</th><th>Barang</th><th>Satuan</th><th>Stok</th><th>Qty Diminta</th></tr></thead><tbody>';

while ($item = mysqli_fetch_assoc($items)) {
    $stokClass = $item['stok_aktual'] < $item['jumlah'] ? 'text-danger font-weight-bold' : 'text-success';
    $stokText = $item['stok_aktual'];
    if ($item['stok_aktual'] < $item['jumlah']) {
        $stokText .= ' (Kurang)';
    }

    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($item['kode_barang']) . '</td>';
    $html .= '<td>' . htmlspecialchars($item['nama_barang']) . '</td>';
    $html .= '<td>' . htmlspecialchars($item['satuan']) . '</td>';
    $html .= '<td><span class="' . $stokClass . '">' . $stokText . '</span></td>';
    $html .= '<td><input type="number" name="qty[' . $item['id_barang'] . ']" value="' . $item['jumlah'] . '" min="0" class="form-control form-control-sm qty-edit" style="width: 80px;" data-id_barang="' . $item['id_barang'] . '"></td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

echo $html;
