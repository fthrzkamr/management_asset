<?php
// Script untuk sync quantity dari barang.sql (inventory lama) ke inventory_atk (inventory baru)
include('../dist/config/config.php');

// Pastikan hanya bisa diakses via browser (untuk keamanan)
if (php_sapi_name() === 'cli') {
    die("Script ini harus dijalankan via browser\n");
}

echo "<h2>Sync Quantity: Inventory Lama (barang.sql) → Inventory Baru (inventory_atk)</h2>";
echo "<p>Membaca data dari barang.sql dan update quantity ke inventory_atk...</p>";
echo "<hr>";

// Data barang dari barang.sql dengan format: nama => qty
$data_barang = [
    'Pembersih Kaca Semprot (Mr. Muscle)' => 0,
    'Pembersih Toilet (Harpic)' => 1,
    'Pembersihan Kaca Mobil (Multi)' => 0,
    'Toilet Ball' => 2,
    'Spons Cuci Piring' => 4,
    'Keset' => 4,
    'Goodie Bag' => 21,
    'Kain Pel' => 2,
    'Sunlight' => 1,
    'Body Wash' => -4,
    'Hand Wash' => 0,
    'Pembersih Kaca Cair (Mr. Muscle 400 ml)' => 13,
    'Tisu Kotak' => -73,
    'Pengharum Ruangan Gantung' => 0,
    'Pengharum Ruangan Semprot' => -14,
    'Pembersih Porselen' => 5,
    'Tisu Bulat' => 17,
    'Masker' => 11,
    'Sticky Notes' => -6,
    'Index & Marks Notes' => 6,
    'Lampu Pengecek Uang (Handheld Black Light)' => 1,
    'Stam pad' => 5,
    'Stam Pad Ink (Biru)' => 0,
    'Stam Pad Ink (Merah)' => 0,
    'Stapler (Kecil)' => -1,
    'Name Tag Meja' => 0,
    'Pulpen Joyko' => 44,
    'Remot AC' => 1,
    'Spidol Non Permanent' => 23,
    'Paper Clip No. 1 (Kecil)' => 13,
    'Paper Clip No. 5 (Besar)' => 18,
    'Binder Clips (25 mm)' => -28,
    'Lem Fox ' => 1,
    'Binder Clips (32 mm)' => -3,
    'Binder Clips (41 mm)' => -15,
    'Binder Clips (51 mm)' => 0,
    'Binder Clips (75 mm)' => 3,
    'Penghapus Papan Tulis' => 0,
    'Temperatur Data Logger' => 0,
    'Stem Push' => 0,
    'Key Ring' => 58,
    'Pensil' => 17,
    'Lem Tembak' => 0,
    'Penghapus' => 0,
    'Lem Stick' => 6,
    'Stabilo' => 0,
    'Refill Ink' => 0,
    'Amplop Putih (Kecil)' => 260,
    'Amplop Putih (Besar)' => 275,
    'Kop Surat A4' => -1,
    'Kop Surat F4' => 0,
    'Kertas Faktur (Continuous Form)' => -10,
    'Rompi ' => 8,
    'Bindex Besar (F4)' => 33,
    'Bindex Kecil (A5)' => 7,
    'Plastik Bening Kecil' => 98,
    'Tali' => -1,
    'Lakban Fragile' => 33,
    'Lakban Bening' => 54,
    'Lakban Cokelat' => -36,
    'Double Tip Tipis' => -1,
    'Lakban Kertas' => 3,
    'Double Tip Tebal' => 4,
    'Lakban Berwarna' => 0,
    'Copy Paper F4' => -1,
    'Copy Paper A4' => -41,
    'Toner Cartidge' => 0,
    'Ribbon Cartidge' => 0,
    'Business File' => 0,
    'Kertas Concorde Putih' => 0,
    'Kertas Concorde Merah' => 0,
    'Kertas Concorde Hijau' => 0,
    'Sheet Protector/PP Pocket' => -250,
    'Notebook Sedang' => 0,
    'Notebook Kecil' => 0,
    'Amplop Cokelat (Polos)' => 0,
    'Amplop Cokelat (Motif)' => 0,
    'Map Kuning' => -2,
    'Map Hijau' => -5,
    'Map Biru' => -2,
    'Map Merah' => -2,
    'Karet' => 4,
    'Plastik Gulung' => 1,
    'Wadah Lampu' => 26,
    'Lampu 18 W' => 0,
    'Lampu 9W' => 0,
    'Lampu 12 W' => 0,
    'Lampu 15 W' => 0,
    'Kertas Laminating' => 0,
    'Map Clear Sleeve/MyFolder' => 0,
    'Correction Tape' => 5,
    'Notebook Besar' => 0,
    'Baterai A3' => -2,
    'Baterai A2' => -5,
    'Kalkulator' => 3,
    'Wifi' => 2,
    'TV Box' => 1,
    'Laptop' => 3,
    'Hardisk' => 1,
    'Flashdisk' => 2,
    'Pen Elektronik' => 1,
    'Dashcam' => 1,
    'Baterai Jam' => 0,
    'Kunci Gembok' => 2,
    'Mesin Lem Tembak' => 1,
    'Mesin Laminting' => 1,
    'Keyboard' => 3,
    'Plastik UK 35' => 269,
    'Plastik UK 50' => 210,
    'Bubble Wrap ' => 5,
    'HDD Upgrade Kit' => 4,
    'HDD FFC Pack' => 7,
    'DDR4 3200 (4 GB)' => 2,
    'SSD 256 GB' => 1,
    'DDR RAM ' => 1,
    'Kabel LAN' => 1,
    'Barel Coupler' => 3,
    'Mouse ' => 7,
    'Document Tray' => 0,
    'Kotak Kecil' => 2319,
    'Kotak Besar' => 1397,
    'Display Vision Blue Gabus' => 11,
    'Display Vision Blue Mika' => 9,
    'Gantungan Kunci Kulit' => 63,
    'Isi Stapler (Besar)' => 1,
    'Isi Stapler (Kecil)' => 62,
    'Kotak Kecil Cendo' => 21,
    'Magnetic Button' => 0,
    'Pembolong Kertas (Besar)' => 1,
    'Binder clips (15 mm)' => 0,
    'Penggaris Besi' => 7,
    'Plastik UK 17' => 100,
    'Jas Hujan' => 0,
    'Jarum Pentul' => 3,
    'Push Pin' => 9,
    'Super Pel' => 9,
    'Spons Besar' => 1,
    'Cutter' => -2,
    'Ribbon Pack' => 0,
    'Notebook Panjang' => 0,
    'Spidol Snowman Permanent' => 21,
    'Date Stamp' => 0,
    'Sign Pen' => 0,
    'Kantong Plastik Merah' => 10,
    'Trash Bag' => 68,
    'Binder Clips 19mm' => 4,
    'Kabel Roll' => 4,
    'Kartu Stock (Kuning)' => 8425,
    'Kartu Stock (Biru)' => -1750,
    'Kartu Stock (Putih)' => 433,
    'Kartu Stock (Pink)' => 1000,
    'Refill Ink Spidol Permanent' => 0,
    'Gunting' => 2,
    'Kotak Kunci' => 0,
    'Lampu 20W' => 0,
    'Lampu 7W' => 0,
    'Pembolong Kertas (Kecil)' => 4,
    'Serutan Kecil' => 9,
    'Set Desk' => -1,
    'Box File' => 8,
    'Isolasi Hitam' => 5,
    'Lakban Hitam (Kecil)' => 10,
    'Lakban Hitam (Besar)' => 6,
    'Handle Galon' => 6,
    'Label Tom & Jerry' => -5,
    'ID Card' => 80,
    'Styrofoam' => 27
];

// Update inventory_atk satu per satu
$updated = 0;
$not_found = 0;
$errors = [];

foreach ($data_barang as $nama => $qty) {
    $nama_escaped = mysqli_real_escape_string($conn, $nama);
    
    // Update query
    $sql = "UPDATE inventory_atk 
            SET stok_aktual = $qty, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE TRIM(LOWER(nama_barang)) = TRIM(LOWER('$nama_escaped'))";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        if (mysqli_affected_rows($conn) > 0) {
            $updated++;
        } else {
            $not_found++;
        }
    } else {
        $errors[] = "Error updating '$nama': " . mysqli_error($conn);
    }
}

// Tampilkan hasil
if (count($errors) > 0) {
    echo "<div style='padding:15px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; color:#721c24; margin-bottom:15px;'>";
    echo "<strong>✗ Ada error:</strong><br>";
    foreach ($errors as $error) {
        echo "- $error<br>";
    }
    echo "</div>";
}

echo "<div style='padding:15px; background:#d4edda; border:1px solid #c3e6cb; border-radius:5px; color:#155724; margin-bottom:15px;'>";
echo "<strong>✓ Proses Selesai!</strong><br>";
echo "Total items berhasil diupdate: <strong>$updated</strong><br>";
echo "Total items tidak ditemukan di inventory_atk: <strong>$not_found</strong><br>";
echo "Timestamp: " . date('Y-m-d H:i:s');
echo "</div>";

// Tampilkan sample data yang diupdate
echo "<h3>Sample Data yang Berhasil Diupdate (10 Terakhir):</h3>";
$sample = mysqli_query($conn, "
    SELECT kode_barang, nama_barang, stok_aktual, stok_minimal, updated_at
    FROM inventory_atk
    WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY updated_at DESC
    LIMIT 10
");

if ($sample && mysqli_num_rows($sample) > 0) {
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; margin-bottom:20px;'>";
    echo "<tr style='background:#f8f9fa;'>";
    echo "<th>Kode</th><th>Nama Barang</th><th>Stok Aktual (Baru)</th><th>Stok Minimal</th><th>Updated At</th>";
    echo "</tr>";
    while ($row = mysqli_fetch_assoc($sample)) {
        $alert_class = $row['stok_aktual'] <= $row['stok_minimal'] ? 'style="background:#fff3cd;"' : '';
        echo "<tr $alert_class>";
        echo "<td>{$row['kode_barang']}</td>";
        echo "<td>{$row['nama_barang']}</td>";
        echo "<td style='text-align:center;'><strong>{$row['stok_aktual']}</strong></td>";
        echo "<td style='text-align:center;'>{$row['stok_minimal']}</td>";
        echo "<td>{$row['updated_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><em>Tidak ada data yang baru diupdate dalam 5 menit terakhir.</em></p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php' style='padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>← Kembali ke Dashboard</a></p>";
?>
