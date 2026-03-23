<?php
include('dist/config/config.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Checking asset_dfm count: ";
$res = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_dfm");
$row = mysqli_fetch_assoc($res);
echo $row['total'] . "\n";

$sql = "
    SELECT 
    kategori_aset, 'Semua Cabang' as lokasi, COUNT(*) as jumlah_aset,
    SUM(total_nilai_pengadaan) as total_nilai,
    AVG(nilai_pengadaan_satuan) as rata_satuan,
    SUM(CASE WHEN informasi_barang = 'Baru' OR kondisi = 'Baru' THEN 1 ELSE 0 END) as baru,
    SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as baik,
    SUM(CASE WHEN informasi_barang = 'Second' OR kondisi = 'Second' THEN 1 ELSE 0 END) as second,
    SUM(CASE WHEN kondisi = 'Rusak Ringan' THEN 1 ELSE 0 END) as rusak_ringan,
    SUM(CASE WHEN kondisi = 'Rusak Berat' THEN 1 ELSE 0 END) as rusak_berat
    FROM asset_dfm 
    GROUP BY kategori_aset
";

$assets = mysqli_query($conn, $sql);
if (!$assets) {
    echo "SQL Error: " . mysqli_error($conn) . "\n";
} else {
    echo "Rows found: " . mysqli_num_rows($assets) . "\n";
    while($r = mysqli_fetch_assoc($assets)) {
        print_r($r);
    }
}
?>
