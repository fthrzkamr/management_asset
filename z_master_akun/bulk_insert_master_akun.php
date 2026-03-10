<?php
// Bulk insert script for master_akun
// Run: C:\xampp\php\php.exe -f c:\xampp\htdocs\asset_dfm\z_master_akun\bulk_insert_master_akun.php

include_once __DIR__ . '/../dist/config/config.php';

$rows = [
    ['1101-11-01','Kas','Kas Besar Jakarta'],
    ['1101-11-02','Kas','Kas Besar Bekasi'],
    ['1101-11-03','Kas','Kas Besar Cibinong'],
    ['1101-11-04','Kas','Kas Besar Bali'],
    ['1101-11-05','Kas','Kas Besar Medan'],
    ['1101-11-06','Kas','Kas Besar Surabaya'],
    ['1101-11-07','Kas','Kas Besar Malang'],
    ['1101-11-08','Kas','Kash Besar Banjarmasin'],
    ['1101-14-08','Kas','Kas Operasional'],
    ['1101-12-01','Kas','Kas Kecil Jakarta'],
    ['1101-13-01','Kas','Kas Kecil HRGA'],
    ['1422-11-01','Uang Muka Biaya','Uang Muka Biaya - Jakarta'],
    ['1422-11-02','Uang Muka Biaya','Uang Muka Biaya - Bekasi'],
    ['1422-11-03','Uang Muka Biaya','Uang Muka Biaya - Cibinong'],
    ['1422-11-04','Uang Muka Biaya','Uang Muka Biaya - Bali'],
    ['1422-11-05','Uang Muka Biaya','Uang Muka Biaya - Medan'],
    ['1422-11-06','Uang Muka Biaya','Uang Muka Biaya - Surabaya'],
    ['1422-11-07','Uang Muka Biaya','Uang Muka Biaya - Malang'],
    ['1422-11-08','Uang Muka Biaya','Uang Muka Biaya - Banjarmasin'],
    ['1422-21-01','Uang Muka Pembelian','Uang Muka Pembelian - Jakarta'],
    ['1422-21-02','Uang Muka Pembelian','Uang Muka Pembelian - Bekasi'],
    ['1422-21-03','Uang Muka Pembelian','Uang Muka Pembelian - Cibinong'],
    ['1422-21-04','Uang Muka Pembelian','Uang Muka Pembelian - Bali'],
    ['1422-21-05','Uang Muka Pembelian','Uang Muka Pembelian - Medan'],
    ['1422-21-07','Uang Muka Pembelian','Uang Muka Pembelian - Malang'],
    ['1529-22-01','Pembelian Aset','Peralatan Usaha'],
    ['1529-23-01','Pembelian Aset','Peralatan Kantor'],
    ['1529-24-01','Pembelian Aset','Peralatan Komputer'],
    ['1529-25-01','Pembelian Aset','Peralatan Lainnya'],
    ['2102-11-00','Pembayaran Utang Usaha','Utang Usaha Tiga A'],
    ['2102-12-00','Pembayaran Utang Usaha','Utang Usaha DPE'],
    ['2102-13-00','Pembayaran Utang Usaha','Utang Usaha PIM'],
    ['2102-14-00','Pembayaran Utang Usaha','Utang Usaha TBT'],
    ['2102-15-00','Pembayaran Utang Usaha','Utang Usaha Supplier Lain'],
    ['5311-11-01','Biaya SDM','Gaji Karyawan'],
    ['5311-21-01','Biaya SDM','THR, Bonus, dan Insentif'],
    ['5312-11-01','Biaya SDM','Tunjangan BPJS Kesehatan'],
    ['5312-21-01','Biaya SDM','Tunjangan BPJS Ketenagakerjaan'],
    ['5312-31-01','Biaya SDM','Tunjangan Lainnya'],
    ['5312-41-00','Biaya SDM','Internal Training'],
    ['5312-42-00','Biaya SDM','Biaya Pelatihan'],
    ['5312-51-00','Biaya SDM','Fasilitas Akomodasi'],
    ['5312-52-00','Biaya SDM','Fasilitas Kesehatan'],
    ['5312-53-00','Biaya SDM','Acara Karyawan'],
    ['5312-54-00','Biaya SDM','Upah Freelance'],
    ['5312-55-00','Biaya SDM','Biaya SDM Lain-lain'],
    ['5313-11-01','BBM','BBM - Jakarta'],
    ['5313-11-02','BBM','BBM - Bekasi'],
    ['5313-11-03','BBM','BBM - Cibinong'],
    ['5313-11-04','BBM','BBM - Bali'],
    ['5313-11-05','BBM','BBM - Medan'],
    ['5313-11-06','BBM','BBM - Surabaya'],
    ['5313-11-07','BBM','BBM - Malang'],
    ['5313-11-07','BBM','BBM - Banjarmasin'],
    ['5313-21-01','Iuran Parkir','Iuran Parkir - Jakarta'],
    ['5313-21-02','Iuran Parkir','Iuran Parkir - Bekasi'],
    ['5313-21-03','Iuran Parkir','Iuran Parkir - Cibinong'],
    ['5313-21-04','Iuran Parkir','Iuran Parkir - Bali'],
    ['5313-21-05','Iuran Parkir','Iuran Parkir - Medan'],
    ['5313-31-01','Parkir','Parkir - Jakarta'],
    ['5313-31-02','Parkir','Parkir - Bekasi'],
    ['5313-31-03','Parkir','Parkir - Cibinong'],
    ['5313-31-04','Parkir','Parkir - Bali'],
    ['5313-31-05','Parkir','Parkir - Medan'],
    ['5313-31-07','Parkir','Parkir - Malang'],
    ['5313-41-01','Toll','Toll - Jakarta'],
    ['5313-41-02','Toll','Toll - Bekasi'],
    ['5313-41-03','Toll','Toll - Cibinong'],
    ['5313-41-04','Toll','Toll - Bali'],
    ['5313-41-05','Toll','Toll - Medan'],
    ['5313-51-01','Ekspedisi','Ekspedisi - Jakarta'],
    ['5313-51-02','Ekspedisi','Ekspedisi - Bekasi'],
    ['5313-51-03','Ekspedisi','Ekspedisi - Cibinong'],
    ['5313-51-04','Ekspedisi','Ekspedisi - Bali'],
    ['5313-51-05','Ekspedisi','Ekspedisi - Medan'],
    ['5313-61-01','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Jakarta'],
    ['5313-61-02','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Bekasi'],
    ['5313-61-03','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Cibinong'],
    ['5313-61-04','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Bali'],
    ['5313-61-05','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Medan'],
    ['5313-61-06','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Surabaya'],
    ['5313-61-07','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Malang'],
    ['5313-61-08','Biaya Transportasi Lainnya','Biaya Transportasi Lainnya - Banjarmasin'],
    ['5315-11-01','Biaya Sewa Gedung','Biaya Sewa Gedung - Jakarta'],
    ['5315-11-02','Biaya Sewa Gedung','Biaya Sewa Gedung - Bekasi'],
    ['5315-11-03','Biaya Sewa Gedung','Biaya Sewa Gedung - Cibinong'],
    ['5315-11-04','Biaya Sewa Gedung','Biaya Sewa Gedung - Bali'],
    ['5315-11-05','Biaya Sewa Gedung','Biaya Sewa Gedung - Medan'],
    ['5315-11-06','Biaya Sewa Gedung','Biaya Sewa Gedung - Malang'],
    ['5315-21-01','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan'],
    ['5315-21-02','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan - Bekasi'],
    ['5315-21-03','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan - Cibinong'],
    ['5315-21-04','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan - Bali'],
    ['5315-21-05','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan - Medan'],
    ['5315-21-06','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan - Surabaya'],
    ['5315-21-08','Biaya Sewa Kendaraan','Biaya Sewa Kendaraan - Banjarmasin'],
    ['5315-31-01','Biaya Sewa Peralatan','Biaya Sewa Peralatan - Jakarta'],
    ['5315-31-02','Biaya Sewa Peralatan','Biaya Sewa Peralatan - Bekasi'],
    ['5315-31-03','Biaya Sewa Peralatan','Biaya Sewa Peralatan - Cibinong'],
    ['5315-31-04','Biaya Sewa Peralatan','Biaya Sewa Peralatan - Bali'],
    ['5315-31-05','Biaya Sewa Peralatan','Biaya Sewa Peralatan - Medan'],
    ['5320-11-01','Biaya Promosi','Biaya Promosi - Jakarta'],
    ['5320-11-02','Biaya Promosi','Biaya Promosi - Bekasi'],
    ['5320-11-03','Biaya Promosi','Biaya Promosi - Cibinong'],
    ['5320-11-04','Biaya Promosi','Biaya Promosi - Bali'],
    ['5320-11-05','Biaya Promosi','Biaya Promosi - Medan'],
    ['5321-11-01','Biaya Entertain','Biaya Entertain - Jakarta'],
    ['5321-11-02','Biaya Entertain','Biaya Entertain - Bekasi'],
    ['5321-11-03','Biaya Entertain','Biaya Entertain - Cibinong'],
    ['5321-11-04','Biaya Entertain','Biaya Entertain - Bali'],
    ['5321-11-05','Biaya Entertain','Biaya Entertain - Medan'],
    ['5321-11-06','Biaya Entertain','Biaya Entertain - Surabaya'],
    ['5321-11-08','Biaya Entertain','Biaya Entertain - Banjarmasin'],
    ['5322-101-00','Pemeliharaan dan Perbaikan Kendaraan','Service Rutin Kendaraan'],
    ['5322-102-00','Pemeliharaan dan Perbaikan Kendaraan','Perbaikan Kendaraan'],
    ['5322-103-00','Pemeliharaan dan Perbaikan Kendaraan','Pembelian Parts Kendaraan'],
    ['5322-104-00','Pemeliharaan dan Perbaikan Kendaraan','Klaim Asuransi'],
    ['5322-11-01','ATK','ATK - Jakarta'],
    ['5322-11-02','ATK','ATK - Bekasi'],
    ['5322-11-03','ATK','ATK - Cibinong'],
    ['5322-11-04','ATK','ATK - Bali'],
    ['5322-11-05','ATK','ATK - Medan'],
    ['5322-11-06','ATK','ATK - Surabaya'],
    ['5322-11-07','ATK','ATK - Malang'],
    ['5322-11-08','ATK','ATK - Banjarmasin'],
    ['5322-111-00','Pemeliharaan dan Perbaikan Peralatan','Pemeliharaan Rutin Peralatan Usaha'],
    ['5322-112-00','Pemeliharaan dan Perbaikan Peralatan','Perbaikan Peralatan Usaha'],
    ['5322-113-00','Pemeliharaan dan Perbaikan Peralatan','Pembelian Parts Peralatan Usaha'],
    ['5322-121-00','Pemeliharaan dan Perbaikan Peralatan','Pemeliharaan Rutin Peralatan Kantor'],
    ['5322-122-00','Pemeliharaan dan Perbaikan Peralatan','Perbaikan Peralatan Kantor'],
    ['5322-123-00','Pemeliharaan dan Perbaikan Peralatan','Pembelian Parts Peralatan Kantor'],
    ['5322-141-00','Biaya Umum','Beban Pajak Penghasilan'],
    ['5322-142-00','Biaya Umum','Beban PBB'],
    ['5322-143-00','Biaya Umum','Beban Pajak Kendaraan'],
    ['5322-144-00','Biaya Umum','Beban Pajak Lainnya'],
    ['5322-151-00','Biaya Umum','Biaya Bank'],
    ['5322-152-00','Biaya Umum','Biaya Transaksi'],
    ['5322-153-00','Biaya Umum','Biaya Asuransi'],
    ['5322-154-00','Biaya Umum','Biaya Legal'],
    ['5322-155-00','Biaya Umum','Biaya Konsultan'],
    ['5322-156-00','Biaya Umum','Biaya Keamanan'],
    ['5322-157-00','Biaya Umum','Biaya Rapat'],
    ['5322-158-00','Biaya Umum','Biaya IT'],
    ['5322-159-00','Biaya Umum','Biaya Umum Lain-Lain'],
    ['5322-21-01','Air','Air - Jakarta'],
    ['5322-21-02','Air','Air - Bekasi'],
    ['5322-21-03','Air','Air - Cibinong'],
    ['5322-21-04','Air','Air - Bali'],
    ['5322-21-05','Air','Air - Medan'],
    ['5322-21-06','Air','Air - Malang'],
    ['5322-31-01','Listrik','Listrik - Jakarta'],
    ['5322-31-02','Listrik','Listrik - Bekasi'],
    ['5322-31-03','Listrik','Listrik - Cibinong'],
    ['5322-31-04','Listrik','Listrik - Bali'],
    ['5322-31-05','Listrik','Listrik - Medan'],
    ['5322-31-07','Listrik','Listrik -  Malang'],
    ['5322-41-01','Telpon & Internet','Telepon & Internet - Jakarta'],
    ['5322-41-02','Telpon & Internet','Telepon & Internet - Bekasi'],
    ['5322-41-03','Telpon & Internet','Telepon & Internet - Cibinong'],
    ['5322-41-04','Telpon & Internet','Telepon & Internet - Bali'],
    ['5322-41-05','Telpon & Internet','Telepon & Internet - Medan'],
    ['5322-41-06','Telpon & Internet','Telepon & Internet - Malang'],
    ['5322-51-01','IPL','IPL - Jakarta'],
    ['5322-51-02','IPL','IPL - Bekasi'],
    ['5322-51-03','IPL','IPL - Cibinong'],
    ['5322-51-04','IPL','IPL - Bali'],
    ['5322-51-05','IPL','IPL - Medan'],
    ['5322-51-07','IPL','IPL - Malang'],
    ['5322-61-01','Perlengkapan Kantor','Perlengkapan Kantor - Jakarta'],
    ['5322-61-02','Perlengkapan Kantor','Perlengkapan Kantor - Bekasi'],
    ['5322-61-03','Perlengkapan Kantor','Perlengkapan Kantor - Cibinong'],
    ['5322-61-04','Perlengkapan Kantor','Perlengkapan Kantor - Bali'],
    ['5322-61-05','Perlengkapan Kantor','Perlengkapan Kantor - Medan'],
    ['5322-61-06','Perlengkapan Kantor','Perlengkapan Kantor - Surabaya'],
    ['5322-61-07','Perlengkapan Kantor','Perlengkapan Kantor - Malang'],
    ['5322-61-08','Perlengkapan Kantor','Perlengkapan Kantor - Banjarmasin'],
    ['5322-71-01','Biaya Konsumsi','Biaya Konsumsi - Jakarta'],
    ['5322-71-02','Biaya Konsumsi','Biaya Konsumsi - Bekasi'],
    ['5322-71-03','Biaya Konsumsi','Biaya Konsumsi - Cibinong'],
    ['5322-71-04','Biaya Konsumsi','Biaya Konsumsi - Bali'],
    ['5322-71-05','Biaya Konsumsi','Biaya Konsumsi - Medan'],
    ['5322-71-06','Biaya Konsumsi','Biaya Konsumsi - Surabaya'],
    ['5322-71-07','Biaya Konsumsi','Biaya Konsumsi - Malang'],
    ['5322-71-08','Biaya Konsumsi','Biaya Konsumsi - Banjarmasin'],
    ['5322-81-00','Biaya Perjalanan Dinas','Biaya Perjalanan Dinas'],
    ['5322-91-00','Pemeliharaan dan Perbaikan Bangunan','Biaya Pemeliharaan Rutin Bangunan'],
    ['5322-92-00','Pemeliharaan dan Perbaikan Bangunan','Biaya Perbaikan Bangunan'],
    ['5322-93-00','Pemeliharaan dan Perbaikan Bangunan','Biaya Renovasi Bangunan'],
    ['5322-94-00','Pemeliharaan dan Perbaikan Bangunan','Pest Control'],
];

$inserted = 0;
$skipped = 0;
$errors = 0;

// get current max numeric part of id_ma
$res = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(id_ma,4) AS UNSIGNED)) AS max_num FROM master_akun");
$row = mysqli_fetch_assoc($res);
$nextNum = ($row && $row['max_num']) ? intval($row['max_num']) + 1 : 1;

$stmt = mysqli_prepare($conn, "INSERT INTO master_akun (id_ma, no_akun, nama_akun, kategori_akun, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo "Prepare failed: " . mysqli_error($conn) . PHP_EOL;
    exit(1);
}

foreach ($rows as $r) {
    list($no_akun, $kategori, $nama_akun) = $r;
    $no_akun = trim($no_akun);
    $kategori = trim($kategori);
    $nama_akun = trim($nama_akun);

    // skip if no_akun already exists
    $cek = mysqli_query($conn, "SELECT 1 FROM master_akun WHERE no_akun = '" . mysqli_real_escape_string($conn, $no_akun) . "' LIMIT 1");
    if (mysqli_num_rows($cek) > 0) {
        echo "SKIP: no_akun already exists: $no_akun\n";
        $skipped++;
        continue;
    }

    $id_ma = sprintf('MAX%04d', $nextNum);
    $created_at = date('Y-m-d');
    $created_by = 'bulk-import';

    mysqli_stmt_bind_param($stmt, 'ssssss', $id_ma, $no_akun, $nama_akun, $kategori, $created_at, $created_by);
    if (mysqli_stmt_execute($stmt)) {
        echo "INSERTED: $id_ma | $no_akun | $kategori | $nama_akun\n";
        $inserted++;
        $nextNum++;
    } else {
        echo "ERROR inserting $no_akun: " . mysqli_stmt_error($stmt) . "\n";
        $errors++;
    }
}

mysqli_stmt_close($stmt);

echo PHP_EOL;
echo "Summary: inserted=$inserted, skipped=$skipped, errors=$errors\n";

?>