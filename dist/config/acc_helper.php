<?php
/**
 * Accounting Helper & Engine (Fase 3)
 * PT Dua Farma Mahakarsa
 *
 * Mengelola:
 * 1. Master COA (Chart of Accounts) & Hirarki Akun
 * 2. Master Rekening & Mutasi Kas/Bank
 * 3. Cash Opname (CO) Mingguan
 * 4. Jurnal Umum & Jurnal Otomatis (BKK, BKM, Mutasi, Opname)
 */

if (!function_exists('init_acc_fase3_db')) {
    function init_acc_fase3_db($conn) {
        static $initialized = false;
        if ($initialized) return;
        $initialized = true;

        // 1. Table acc_coa (Master Chart of Accounts)
        $sql_coa = "CREATE TABLE IF NOT EXISTS acc_coa (
            id_coa INT(11) NOT NULL AUTO_INCREMENT,
            kode_akun VARCHAR(50) NOT NULL UNIQUE,
            nama_akun VARCHAR(150) NOT NULL,
            kategori_akun ENUM('Aset Lancar', 'Aset Tetap', 'Liabilitas Lancar', 'Liabilitas Jangka Panjang', 'Ekuitas', 'Pendapatan', 'Beban Operasional', 'Beban Non-Operasional') NOT NULL,
            tipe_saldo ENUM('Debit', 'Kredit') NOT NULL DEFAULT 'Debit',
            level INT(2) NOT NULL DEFAULT 4,
            parent_kode VARCHAR(50) NULL,
            is_header TINYINT(1) NOT NULL DEFAULT 0,
            id_cabang_ppx INT(11) NULL,
            status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
            keterangan TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_coa),
            KEY (kode_akun),
            KEY (parent_kode),
            KEY (kategori_akun),
            KEY (id_cabang_ppx)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_coa);

        // 2. Table acc_master_rekening (Master Bank & Rekening)
        $sql_rek = "CREATE TABLE IF NOT EXISTS acc_master_rekening (
            id_rekening INT(11) NOT NULL AUTO_INCREMENT,
            nama_bank VARCHAR(100) NOT NULL,
            nomor_rekening VARCHAR(50) NOT NULL,
            atas_nama VARCHAR(150) NOT NULL,
            id_coa INT(11) NULL,
            id_cabang_ppx INT(11) NULL,
            saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            saldo_saat_ini DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            keterangan TEXT NULL,
            status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_rekening),
            KEY (id_coa),
            KEY (id_cabang_ppx)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_rek);

        // 3. Table acc_mutasi_bank (Mutasi Rekening Bank)
        $sql_mut = "CREATE TABLE IF NOT EXISTS acc_mutasi_bank (
            id_mutasi INT(11) NOT NULL AUTO_INCREMENT,
            no_mutasi VARCHAR(50) NOT NULL UNIQUE,
            id_rekening INT(11) NOT NULL,
            tanggal DATE NOT NULL,
            jenis_mutasi ENUM('Masuk', 'Keluar') NOT NULL,
            nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            kategori_mutasi ENUM('Setor Kas', 'Tarik Kas Tunai', 'Transfer Masuk', 'Transfer Keluar', 'Biaya Admin Bank', 'Bunga Bank', 'Pembayaran Beban', 'Lainnya') NOT NULL,
            id_coa_lawan INT(11) NULL,
            no_referensi VARCHAR(100) NULL,
            keterangan TEXT NOT NULL,
            bukti_file VARCHAR(255) NULL,
            saldo_setelahnya DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            id_jurnal INT(11) NULL,
            created_by INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_mutasi),
            KEY (id_rekening),
            KEY (tanggal),
            KEY (id_coa_lawan),
            KEY (id_jurnal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_mut);

        // 4. Table acc_cash_opname (Cash Opname Mingguan)
        $sql_co = "CREATE TABLE IF NOT EXISTS acc_cash_opname (
            id_opname INT(11) NOT NULL AUTO_INCREMENT,
            no_opname VARCHAR(50) NOT NULL UNIQUE,
            id_cabang_ppx INT(11) NOT NULL,
            id_coa_kas INT(11) NULL,
            tanggal_opname DATE NOT NULL,
            periode_minggu VARCHAR(50) NOT NULL,
            saldo_buku DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            saldo_fisik DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            selisih DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            keterangan_selisih TEXT NULL,
            rincian_pecahan_json LONGTEXT NULL,
            petugas_opname VARCHAR(100) NOT NULL,
            saksi_opname VARCHAR(100) NULL,
            bukti_foto VARCHAR(255) NULL,
            status ENUM('Draft', 'Disetujui', 'Perlu Penyesuaian') NOT NULL DEFAULT 'Draft',
            id_jurnal_penyesuaian INT(11) NULL,
            created_by INT(11) NOT NULL,
            approved_by INT(11) NULL,
            approved_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_opname),
            KEY (id_cabang_ppx),
            KEY (tanggal_opname),
            KEY (id_coa_kas),
            KEY (id_jurnal_penyesuaian)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_co);

        // 5. Table acc_jurnal_header (Header Jurnal Umum / Otomatis)
        $sql_jh = "CREATE TABLE IF NOT EXISTS acc_jurnal_header (
            id_jurnal INT(11) NOT NULL AUTO_INCREMENT,
            no_jurnal VARCHAR(50) NOT NULL UNIQUE,
            tanggal_jurnal DATE NOT NULL,
            tipe_jurnal ENUM('Umum', 'Penyesuaian', 'BKK', 'BKM', 'Mutasi_Bank', 'Cash_Opname') NOT NULL DEFAULT 'Umum',
            referensi_no VARCHAR(100) NULL,
            keterangan TEXT NOT NULL,
            total_debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_kredit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            is_balanced TINYINT(1) NOT NULL DEFAULT 1,
            status ENUM('Posted', 'Draft') NOT NULL DEFAULT 'Posted',
            created_by INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_jurnal),
            KEY (no_jurnal),
            KEY (tanggal_jurnal),
            KEY (tipe_jurnal),
            KEY (referensi_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_jh);

        // 6. Table acc_jurnal_detail (Detail Jurnal Debit / Kredit)
        $sql_jd = "CREATE TABLE IF NOT EXISTS acc_jurnal_detail (
            id_jurnal_detail INT(11) NOT NULL AUTO_INCREMENT,
            id_jurnal INT(11) NOT NULL,
            id_coa INT(11) NOT NULL,
            posisi ENUM('Debit', 'Kredit') NOT NULL,
            nominal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            memo VARCHAR(255) NULL,
            PRIMARY KEY (id_jurnal_detail),
            KEY (id_jurnal),
            KEY (id_coa),
            KEY (posisi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_jd);

        // Auto-seed Initial COA & Bridge existing master_akun
        seed_default_coa_and_bridge($conn);

        // Auto-seed Default Bank Accounts
        seed_default_bank_rekening($conn);
    }
}

/**
 * Seeding COA dasar & migrasi data dari master_akun ke acc_coa
 */
function seed_default_coa_and_bridge($conn) {
    $chk = mysqli_query($conn, "SELECT COUNT(*) FROM acc_coa");
    if (!$chk) return;
    $cnt = (int)mysqli_fetch_row($chk)[0];

    if ($cnt === 0) {
        // Struktur COA Standar Level 1, 2, 3
        $standard_parents = [
            // Level 1: Klasifikasi Utama
            ['1000', 'ASET', 'Aset Lancar', 'Debit', 1, null, 1],
            ['2000', 'LIABILITAS', 'Liabilitas Lancar', 'Kredit', 1, null, 1],
            ['3000', 'EKUITAS', 'Ekuitas', 'Kredit', 1, null, 1],
            ['4000', 'PENDAPATAN', 'Pendapatan', 'Kredit', 1, null, 1],
            ['5000', 'BEBAN OPERASIONAL', 'Beban Operasional', 'Debit', 1, null, 1],
            ['6000', 'BEBAN NON-OPERASIONAL', 'Beban Non-Operasional', 'Debit', 1, null, 1],
            ['7000', 'PENDAPATAN LAIN-LAIN', 'Pendapatan', 'Kredit', 1, null, 1],

            // Level 2: Sub-Klasifikasi
            ['1100', 'Aset Lancar', 'Aset Lancar', 'Debit', 2, '1000', 1],
            ['1200', 'Aset Tetap', 'Aset Tetap', 'Debit', 2, '1000', 1],
            ['2100', 'Liabilitas Jangka Pendek', 'Liabilitas Lancar', 'Kredit', 2, '2000', 1],
            ['2200', 'Liabilitas Jangka Panjang', 'Liabilitas Jangka Panjang', 'Kredit', 2, '2000', 1],
            ['3100', 'Modal & Saldo Laba', 'Ekuitas', 'Kredit', 2, '3000', 1],
            ['4100', 'Pendapatan Operasional', 'Pendapatan', 'Kredit', 2, '4000', 1],
            ['5100', 'Beban SDM & Karyawan', 'Beban Operasional', 'Debit', 2, '5000', 1],
            ['5200', 'Beban Operasional Kantor', 'Beban Operasional', 'Debit', 2, '5000', 1],
            ['5300', 'Beban Transport & Kendaraan', 'Beban Operasional', 'Debit', 2, '5000', 1],

            // Level 3: Header Akun Buku Besar
            ['1101', 'Kas', 'Aset Lancar', 'Debit', 3, '1100', 1],
            ['1102', 'Bank', 'Aset Lancar', 'Debit', 3, '1100', 1],
            ['1103', 'Piutang Usaha', 'Aset Lancar', 'Debit', 3, '1100', 1],
            ['1104', 'Uang Muka', 'Aset Lancar', 'Debit', 3, '1100', 1],
            ['1105', 'Perlengkapan Kantor', 'Aset Lancar', 'Debit', 3, '1100', 1],
            ['1201', 'Peralatan & Mesin', 'Aset Tetap', 'Debit', 3, '1200', 1],
            ['1202', 'Kendaraan', 'Aset Tetap', 'Debit', 3, '1200', 1],
            ['1209', 'Akumulasi Penyusutan', 'Aset Tetap', 'Kredit', 3, '1200', 1],
            ['2101', 'Utang Usaha', 'Liabilitas Lancar', 'Kredit', 3, '2100', 1],
            ['2102', 'Beban Akrual / Masih Harus Dibayar', 'Liabilitas Lancar', 'Kredit', 3, '2100', 1],
            ['3101', 'Modal Pemilik', 'Ekuitas', 'Kredit', 3, '3100', 0],
            ['3102', 'Laba Ditahan', 'Ekuitas', 'Kredit', 3, '3100', 0],
            ['4101', 'Pendapatan Usaha', 'Pendapatan', 'Kredit', 3, '4100', 0],
            ['4102', 'Pendapatan Lain-lain / Pengembalian', 'Pendapatan', 'Kredit', 3, '4100', 0],
            ['6101', 'Beban Administrasi Bank', 'Beban Non-Operasional', 'Debit', 3, '6000', 0],
            ['6102', 'Beban Selisih Kas Kurang', 'Beban Non-Operasional', 'Debit', 3, '6000', 0],
            ['7101', 'Pendapatan Bunga Bank', 'Pendapatan', 'Kredit', 3, '7000', 0],
            ['7102', 'Pendapatan Selisih Kas Lebih', 'Pendapatan', 'Kredit', 3, '7000', 0],

            // Detail Bank default
            ['1102-01', 'Bank BCA Operasional Pusat', 'Aset Lancar', 'Debit', 4, '1102', 0],
            ['1102-02', 'Bank Mandiri Operasional Pusat', 'Aset Lancar', 'Debit', 4, '1102', 0],
        ];

        foreach ($standard_parents as $p) {
            $k_parent = $p[5] ? "'{$p[5]}'" : "NULL";
            $sql = "INSERT INTO acc_coa (kode_akun, nama_akun, kategori_akun, tipe_saldo, level, parent_kode, is_header, status)
                    VALUES ('{$p[0]}', '{$p[1]}', '{$p[2]}', '{$p[3]}', {$p[4]}, $k_parent, {$p[6]}, 'Aktif')
                    ON DUPLICATE KEY UPDATE nama_akun = VALUES(nama_akun)";
            @mysqli_query($conn, $sql);
        }

        // Bridge all data from master_akun if table exists
        $ma_chk = mysqli_query($conn, "SHOW TABLES LIKE 'master_akun'");
        if ($ma_chk && mysqli_num_rows($ma_chk) > 0) {
            $ma_res = mysqli_query($conn, "SELECT * FROM master_akun ORDER BY no_akun ASC");
            if ($ma_res) {
                while ($ma = mysqli_fetch_assoc($ma_res)) {
                    $kode = trim($ma['no_akun']);
                    $nama = trim($ma['nama_akun']);
                    $kat_old = trim($ma['kategori_akun'] ?? '');

                    // Tentukan kategori & tipe saldo
                    $kategori = 'Beban Operasional';
                    $saldo = 'Debit';
                    $level = 4;
                    $parent = null;

                    if (strpos($kode, '1101') === 0) {
                        $kategori = 'Aset Lancar';
                        $saldo = 'Debit';
                        $parent = '1101';
                    } elseif (strpos($kode, '1102') === 0) {
                        $kategori = 'Aset Lancar';
                        $saldo = 'Debit';
                        $parent = '1102';
                    } elseif (strpos($kode, '1422') === 0) {
                        $kategori = 'Aset Lancar';
                        $saldo = 'Debit';
                        $parent = '1104';
                    } elseif (strpos($kode, '1529') === 0) {
                        $kategori = 'Aset Tetap';
                        $saldo = 'Debit';
                        $parent = '1201';
                    } elseif (strpos($kode, '2102') === 0 || strpos($kode, '2') === 0) {
                        $kategori = 'Liabilitas Lancar';
                        $saldo = 'Kredit';
                        $parent = '2101';
                    } elseif (strpos($kode, '3') === 0) {
                        $kategori = 'Ekuitas';
                        $saldo = 'Kredit';
                        $parent = '3100';
                    } elseif (strpos($kode, '4') === 0) {
                        $kategori = 'Pendapatan';
                        $saldo = 'Kredit';
                        $parent = '4100';
                    } elseif (strpos($kode, '5311') === 0 || strpos($kode, '5312') === 0) {
                        $kategori = 'Beban Operasional';
                        $saldo = 'Debit';
                        $parent = '5100';
                    } elseif (strpos($kode, '5313') === 0) {
                        $kategori = 'Beban Operasional';
                        $saldo = 'Debit';
                        $parent = '5300';
                    } else {
                        $kategori = 'Beban Operasional';
                        $saldo = 'Debit';
                        $parent = '5200';
                    }

                    $k_parent_sql = $parent ? "'$parent'" : "NULL";
                    $nama_esc = mysqli_real_escape_string($conn, $nama);
                    $kode_esc = mysqli_real_escape_string($conn, $kode);

                    $insert_sql = "INSERT IGNORE INTO acc_coa 
                        (kode_akun, nama_akun, kategori_akun, tipe_saldo, level, parent_kode, is_header, status, keterangan)
                        VALUES ('$kode_esc', '$nama_esc', '$kategori', '$saldo', 4, $k_parent_sql, 0, 'Aktif', 'Import dari master_akun: $kat_old')";
                    @mysqli_query($conn, $insert_sql);
                }
            }
        }
    }
}

/**
 * Seeding Default Bank Rekening
 */
function seed_default_bank_rekening($conn) {
    $chk = mysqli_query($conn, "SELECT COUNT(*) FROM acc_master_rekening");
    if (!$chk) return;
    $cnt = (int)mysqli_fetch_row($chk)[0];

    if ($cnt === 0) {
        // Ambil ID COA untuk BCA dan Mandiri
        $id_bca = 0;
        $id_mandiri = 0;
        $qbca = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '1102-01' LIMIT 1");
        if ($qbca && $rb = mysqli_fetch_assoc($qbca)) $id_bca = (int)$rb['id_coa'];

        $qman = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '1102-02' LIMIT 1");
        if ($qman && $rm = mysqli_fetch_assoc($qman)) $id_mandiri = (int)$rm['id_coa'];

        $banks = [
            ['nama_bank' => 'Bank Central Asia (BCA)', 'nomor' => '8410293847', 'atas_nama' => 'PT DUA FARMA MAHAKARSA', 'id_coa' => $id_bca, 'saldo' => 50000000.00],
            ['nama_bank' => 'Bank Mandiri', 'nomor' => '1370019283746', 'atas_nama' => 'PT DUA FARMA MAHAKARSA', 'id_coa' => $id_mandiri, 'saldo' => 25000000.00],
        ];

        foreach ($banks as $b) {
            $id_coa_val = $b['id_coa'] ?: "NULL";
            @mysqli_query($conn, "INSERT INTO acc_master_rekening 
                (nama_bank, nomor_rekening, atas_nama, id_coa, saldo_awal, saldo_saat_ini, status)
                VALUES ('{$b['nama_bank']}', '{$b['nomor']}', '{$b['atas_nama']}', $id_coa_val, {$b['saldo']}, {$b['saldo']}, 'Aktif')");
        }
    }
}

/**
 * Generate nomor mutasi bank unik
 * Format: MUT/{BANK}/{YYYY}/{MM}/{0001}
 */
function acc_generate_no_mutasi($conn, $bank_code = 'BNK') {
    $year = date('Y');
    $month = date('m');
    $clean_bank = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $bank_code), 0, 4)) ?: 'BNK';
    $prefix = "MUT/{$clean_bank}/{$year}/{$month}/";

    $q = mysqli_query($conn, "SELECT no_mutasi FROM acc_mutasi_bank WHERE no_mutasi LIKE '{$prefix}%' ORDER BY id_mutasi DESC LIMIT 1");
    $seq = 1;
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $parts = explode('/', $r['no_mutasi']);
        $last_num = (int)end($parts);
        $seq = $last_num + 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate nomor Cash Opname unik
 * Format: CO/{KODE_CABANG}/{YYYY}/{MM}/{0001}
 */
function acc_generate_no_opname($conn, $kode_cabang = 'JKT') {
    $year = date('Y');
    $month = date('m');
    $clean_cab = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $kode_cabang), 0, 4)) ?: 'JKT';
    $prefix = "CO/{$clean_cab}/{$year}/{$month}/";

    $q = mysqli_query($conn, "SELECT no_opname FROM acc_cash_opname WHERE no_opname LIKE '{$prefix}%' ORDER BY id_opname DESC LIMIT 1");
    $seq = 1;
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $parts = explode('/', $r['no_opname']);
        $last_num = (int)end($parts);
        $seq = $last_num + 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate nomor Jurnal unik
 * Format: JU/{YYYY}/{MM}/{0001} atau JO/{TIPE}/{YYYY}/{MM}/{0001}
 */
function acc_generate_no_jurnal($conn, $tipe = 'JU') {
    $year = date('Y');
    $month = date('m');
    $prefix = "{$tipe}/{$year}/{$month}/";

    $q = mysqli_query($conn, "SELECT no_jurnal FROM acc_jurnal_header WHERE no_jurnal LIKE '{$prefix}%' ORDER BY id_jurnal DESC LIMIT 1");
    $seq = 1;
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $parts = explode('/', $r['no_jurnal']);
        $last_num = (int)end($parts);
        $seq = $last_num + 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Membuat entri Jurnal Lengkap (Header + Detail)
 * Memastikan Double-Entry Bookkeeping (Total Debit == Total Kredit)
 *
 * $header = [
 *   'tipe_jurnal' => 'Umum'|'Penyesuaian'|'BKK'|'BKM'|'Mutasi_Bank'|'Cash_Opname',
 *   'tanggal_jurnal' => 'YYYY-MM-DD',
 *   'referensi_no' => '...',
 *   'keterangan' => '...',
 *   'created_by' => 1
 * ]
 * $details = [
 *   ['id_coa' => 12, 'posisi' => 'Debit', 'nominal' => 100000, 'memo' => '...'],
 *   ['id_coa' => 5,  'posisi' => 'Kredit', 'nominal' => 100000, 'memo' => '...'],
 * ]
 */
function acc_create_jurnal($conn, $header, $details) {
    if (empty($details)) {
        return ['success' => false, 'message' => 'Detail jurnal tidak boleh kosong.'];
    }

    $total_debit = 0;
    $total_kredit = 0;

    foreach ($details as $d) {
        $nom = (float)$d['nominal'];
        if ($nom < 0) return ['success' => false, 'message' => 'Nominal jurnal tidak boleh negatif.'];
        if ($d['posisi'] === 'Debit') {
            $total_debit += $nom;
        } elseif ($d['posisi'] === 'Kredit') {
            $total_kredit += $nom;
        }
    }

    // Cek keseimbangan (toleransi 0.01 untuk pembulatan mata uang)
    $diff = abs($total_debit - $total_kredit);
    $is_balanced = ($diff < 0.01) ? 1 : 0;

    $tipe = $header['tipe_jurnal'] ?? 'Umum';
    $prefix = ($tipe === 'Umum') ? 'JU' : 'JO';
    $no_jurnal = acc_generate_no_jurnal($conn, $prefix);

    $tanggal = $header['tanggal_jurnal'] ?? date('Y-m-d');
    $ref_no  = mysqli_real_escape_string($conn, $header['referensi_no'] ?? '');
    $ket     = mysqli_real_escape_string($conn, $header['keterangan'] ?? '');
    $uid     = (int)($header['created_by'] ?? ($_SESSION['id_user'] ?? 1));
    $status  = $header['status'] ?? 'Posted';

    $sql_h = "INSERT INTO acc_jurnal_header 
        (no_jurnal, tanggal_jurnal, tipe_jurnal, referensi_no, keterangan, total_debit, total_kredit, is_balanced, status, created_by)
        VALUES ('$no_jurnal', '$tanggal', '$tipe', '$ref_no', '$ket', $total_debit, $total_kredit, $is_balanced, '$status', $uid)";
    
    if (!mysqli_query($conn, $sql_h)) {
        return ['success' => false, 'message' => 'Gagal membuat header jurnal: ' . mysqli_error($conn)];
    }

    $id_jurnal = mysqli_insert_id($conn);

    foreach ($details as $d) {
        $id_coa = (int)$d['id_coa'];
        $posisi = ($d['posisi'] === 'Debit') ? 'Debit' : 'Kredit';
        $nom = (float)$d['nominal'];
        $memo = mysqli_real_escape_string($conn, $d['memo'] ?? '');

        $sql_d = "INSERT INTO acc_jurnal_detail (id_jurnal, id_coa, posisi, nominal, memo)
                  VALUES ($id_jurnal, $id_coa, '$posisi', $nom, '$memo')";
        mysqli_query($conn, $sql_d);
    }

    return [
        'success' => true,
        'id_jurnal' => $id_jurnal,
        'no_jurnal' => $no_jurnal,
        'is_balanced' => $is_balanced,
        'total_debit' => $total_debit,
        'total_kredit' => $total_kredit
    ];
}

/**
 * Cari Akun Kas COA untuk Cabang tertentu
 */
function acc_find_kas_coa($conn, $id_cabang_ppx = null, $kode_cabang = '') {
    if ($kode_cabang) {
        $qc = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kategori_akun = 'Aset Lancar' AND kode_akun LIKE '1101%' AND (nama_akun LIKE '%$kode_cabang%' OR keterangan LIKE '%$kode_cabang%') LIMIT 1");
        if ($qc && $rc = mysqli_fetch_assoc($qc)) return (int)$rc['id_coa'];
    }
    // Fallback akun kas umum
    $qf = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun LIKE '1101%' AND is_header = 0 LIMIT 1");
    if ($qf && $rf = mysqli_fetch_assoc($qf)) return (int)$rf['id_coa'];
    return 1;
}

/**
 * Otomatis generate Jurnal saat BKK disetujui
 * Debit: Beban Biaya (COA)
 * Kredit: Kas Operasional Cabang
 */
function acc_post_bkk_journal($conn, $id_real_detail) {
    $q = mysqli_query($conn, "
        SELECT rd.*, mb.budget_name, mb.id_budget, u.id_cabang, mc.nama_cabang, pmc.kode_cabang AS ppx_kode,
               mbd.rincian AS nama_item, bc.category_name, bc.category_code
        FROM ppx_realisasi_detail2 rd
        JOIN ppx_monthly_budget mb ON rd.id_budget = mb.id_budget
        JOIN user_dfm u ON mb.created_by = u.id_user
        LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
        LEFT JOIN ppx_master_cabang pmc ON (pmc.id_cabang_asset = u.id_cabang OR pmc.nama_cabang = mc.nama_cabang)
        LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
        LEFT JOIN ppx_budget_category bc ON mbd.id_category = bc.id_category
        WHERE rd.id_real_detail = " . (int)$id_real_detail . " LIMIT 1
    ");
    if (!$q || mysqli_num_rows($q) === 0) return false;
    $r = mysqli_fetch_assoc($q);

    $nom = (float)$r['nominal_realisasi'];
    if ($nom <= 0) return false;

    $kode_cab = $r['ppx_kode'] ?: 'JKT';
    $id_coa_kas = acc_find_kas_coa($conn, null, $r['nama_cabang'] ?? $kode_cab);

    // Cari akun beban yang sesuai kategori atau deskripsi
    $id_coa_beban = 0;
    $cat_name = mysqli_real_escape_string($conn, $r['category_name'] ?? '');
    $qcoa = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kategori_akun = 'Beban Operasional' AND is_header = 0 AND (nama_akun LIKE '%$cat_name%' OR keterangan LIKE '%$cat_name%') LIMIT 1");
    if ($qcoa && $rcoa = mysqli_fetch_assoc($qcoa)) {
        $id_coa_beban = (int)$rcoa['id_coa'];
    } else {
        // Fallback akun Beban Operasional Umum (5200 / akun beban pertama)
        $qfb = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kategori_akun = 'Beban Operasional' AND is_header = 0 LIMIT 1");
        if ($qfb && $rfb = mysqli_fetch_assoc($qfb)) $id_coa_beban = (int)$rfb['id_coa'];
    }

    $header = [
        'tipe_jurnal'    => 'BKK',
        'tanggal_jurnal' => $r['tanggal_realisasi'] ?: date('Y-m-d'),
        'referensi_no'   => $r['no_realisasi'],
        'keterangan'     => "Realisasi BKK {$r['no_realisasi']} - {$r['keperluan']} ({$r['nama_item']})",
        'created_by'     => $r['approved_by'] ?: ($_SESSION['id_user'] ?? 1),
    ];

    $details = [
        ['id_coa' => $id_coa_beban, 'posisi' => 'Debit',  'nominal' => $nom, 'memo' => "Beban: " . ($r['keperluan'] ?: $r['nama_item'])],
        ['id_coa' => $id_coa_kas,   'posisi' => 'Kredit', 'nominal' => $nom, 'memo' => "Kas Cabang {$kode_cab}"],
    ];

    return acc_create_jurnal($conn, $header, $details);
}

/**
 * Otomatis generate Jurnal saat BKM disetujui (Pengembalian Sisa Belanja Kas)
 * Debit: Kas Operasional Cabang
 * Kredit: Pengembalian Biaya / Pendapatan Lain-lain
 */
function acc_post_bkm_journal($conn, $id_real_detail) {
    $q = mysqli_query($conn, "
        SELECT rd.*, mb.budget_name, mb.id_budget, u.id_cabang, mc.nama_cabang, pmc.kode_cabang AS ppx_kode,
               mbd.rincian AS nama_item, bc.category_name
        FROM ppx_realisasi_detail2 rd
        JOIN ppx_monthly_budget mb ON rd.id_budget = mb.id_budget
        JOIN user_dfm u ON mb.created_by = u.id_user
        LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
        LEFT JOIN ppx_master_cabang pmc ON (pmc.id_cabang_asset = u.id_cabang OR pmc.nama_cabang = mc.nama_cabang)
        LEFT JOIN ppx_monthly_budget_item_detail mbd ON rd.id_detail_budget = mbd.id
        LEFT JOIN ppx_budget_category bc ON mbd.id_category = bc.id_category
        WHERE rd.id_real_detail = " . (int)$id_real_detail . " LIMIT 1
    ");
    if (!$q || mysqli_num_rows($q) === 0) return false;
    $r = mysqli_fetch_assoc($q);

    $nom = (float)($r['uang_kembali'] ?: $r['nominal_realisasi']);
    if ($nom <= 0) return false;

    $kode_cab = $r['ppx_kode'] ?: 'JKT';
    $id_coa_kas = acc_find_kas_coa($conn, null, $r['nama_cabang'] ?? $kode_cab);

    // Akun kredit: Akun Pendapatan Lain-lain / Pengembalian (4102) atau akun beban terkait
    $id_coa_kredit = 0;
    $qkred = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '4102' LIMIT 1");
    if ($qkred && $rk = mysqli_fetch_assoc($qkred)) {
        $id_coa_kredit = (int)$rk['id_coa'];
    } else {
        $qfb = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kategori_akun = 'Pendapatan' AND is_header = 0 LIMIT 1");
        if ($qfb && $rfb = mysqli_fetch_assoc($qfb)) $id_coa_kredit = (int)$rfb['id_coa'];
    }

    $header = [
        'tipe_jurnal'    => 'BKM',
        'tanggal_jurnal' => $r['tanggal_realisasi'] ?: date('Y-m-d'),
        'referensi_no'   => $r['no_realisasi'],
        'keterangan'     => "Penerimaan BKM {$r['no_realisasi']} - {$r['keperluan']} ({$r['dibayar_kepada']})",
        'created_by'     => $r['approved_by'] ?: ($_SESSION['id_user'] ?? 1),
    ];

    $details = [
        ['id_coa' => $id_coa_kas,    'posisi' => 'Debit',  'nominal' => $nom, 'memo' => "Kas Cabang {$kode_cab}"],
        ['id_coa' => $id_coa_kredit, 'posisi' => 'Kredit', 'nominal' => $nom, 'memo' => "Pengembalian: " . ($r['keperluan'] ?: $r['nama_item'])],
    ];

    return acc_create_jurnal($conn, $header, $details);
}

/**
 * Otomatis generate Jurnal saat Mutasi Bank disimpan
 */
function acc_post_mutasi_journal($conn, $id_mutasi) {
    $q = mysqli_query($conn, "
        SELECT m.*, r.nama_bank, r.nomor_rekening, r.id_coa AS id_coa_bank
        FROM acc_mutasi_bank m
        JOIN acc_master_rekening r ON m.id_rekening = r.id_rekening
        WHERE m.id_mutasi = " . (int)$id_mutasi . " LIMIT 1
    ");
    if (!$q || mysqli_num_rows($q) === 0) return false;
    $m = mysqli_fetch_assoc($q);

    $nom = (float)$m['nominal'];
    $id_bank_coa = (int)$m['id_coa_bank'];
    $id_lawan_coa = (int)$m['id_coa_lawan'];

    if (!$id_bank_coa || !$id_lawan_coa || $nom <= 0) return false;

    $header = [
        'tipe_jurnal'    => 'Mutasi_Bank',
        'tanggal_jurnal' => $m['tanggal'],
        'referensi_no'   => $m['no_mutasi'],
        'keterangan'     => "Mutasi {$m['jenis_mutasi']} {$m['nama_bank']} - {$m['keterangan']}",
        'created_by'     => $m['created_by'],
    ];

    if ($m['jenis_mutasi'] === 'Masuk') {
        // Uang Masuk ke Bank: Debit Bank, Kredit Akun Lawan
        $details = [
            ['id_coa' => $id_bank_coa,  'posisi' => 'Debit',  'nominal' => $nom, 'memo' => "Penerimaan Bank {$m['nama_bank']}"],
            ['id_coa' => $id_lawan_coa, 'posisi' => 'Kredit', 'nominal' => $nom, 'memo' => $m['keterangan']],
        ];
    } else {
        // Uang Keluar dari Bank: Debit Akun Lawan / Beban, Kredit Bank
        $details = [
            ['id_coa' => $id_lawan_coa, 'posisi' => 'Debit',  'nominal' => $nom, 'memo' => $m['keterangan']],
            ['id_coa' => $id_bank_coa,  'posisi' => 'Kredit', 'nominal' => $nom, 'memo' => "Pengeluaran Bank {$m['nama_bank']}"],
        ];
    }

    $res = acc_create_jurnal($conn, $header, $details);
    if ($res && !empty($res['id_jurnal'])) {
        mysqli_query($conn, "UPDATE acc_mutasi_bank SET id_jurnal = " . (int)$res['id_jurnal'] . " WHERE id_mutasi = " . (int)$id_mutasi);
    }
    return $res;
}

/**
 * Otomatis generate Jurnal Penyesuaian saat Cash Opname memiliki selisih
 */
function acc_post_opname_adjustment_journal($conn, $id_opname) {
    $q = mysqli_query($conn, "
        SELECT co.*, pmc.nama_cabang, pmc.kode_cabang
        FROM acc_cash_opname co
        LEFT JOIN ppx_master_cabang pmc ON co.id_cabang_ppx = pmc.id_cabang_ppx
        WHERE co.id_opname = " . (int)$id_opname . " LIMIT 1
    ");
    if (!$q || mysqli_num_rows($q) === 0) return false;
    $co = mysqli_fetch_assoc($q);

    $selisih = (float)$co['selisih'];
    if (abs($selisih) < 0.01) return true; // Klop, tidak perlu jurnal penyesuaian

    $id_kas_coa = (int)$co['id_coa_kas'] ?: acc_find_kas_coa($conn, $co['id_cabang_ppx'], $co['kode_cabang']);
    $abs_nom = abs($selisih);

    $header = [
        'tipe_jurnal'    => 'Penyesuaian',
        'tanggal_jurnal' => $co['tanggal_opname'],
        'referensi_no'   => $co['no_opname'],
        'keterangan'     => "Penyesuaian Selisih Cash Opname {$co['no_opname']} ({$co['periode_minggu']}) - Cabang {$co['nama_cabang']}",
        'created_by'     => $co['approved_by'] ?: ($_SESSION['id_user'] ?? 1),
    ];

    if ($selisih < 0) {
        // Selisih Kurang (Fisik < Buku) -> Beban Selisih Kas (Debit), Akun Kas (Kredit)
        $q_beban = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '6102' LIMIT 1");
        $id_beban = ($q_beban && $rb = mysqli_fetch_assoc($q_beban)) ? (int)$rb['id_coa'] : 0;
        if (!$id_beban) {
            $q_fb = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kategori_akun = 'Beban Non-Operasional' LIMIT 1");
            if ($q_fb && $rfb = mysqli_fetch_assoc($q_fb)) $id_beban = (int)$rfb['id_coa'];
        }

        $details = [
            ['id_coa' => $id_beban,   'posisi' => 'Debit',  'nominal' => $abs_nom, 'memo' => "Beban Selisih Kas Kurang - {$co['no_opname']}"],
            ['id_coa' => $id_kas_coa, 'posisi' => 'Kredit', 'nominal' => $abs_nom, 'memo' => "Penyesuaian Kas Fisik Kurang"],
        ];
    } else {
        // Selisih Lebih (Fisik > Buku) -> Akun Kas (Debit), Pendapatan Selisih Kas (Kredit)
        $q_rev = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kode_akun = '7102' LIMIT 1");
        $id_rev = ($q_rev && $rr = mysqli_fetch_assoc($q_rev)) ? (int)$rr['id_coa'] : 0;
        if (!$id_rev) {
            $q_fb = mysqli_query($conn, "SELECT id_coa FROM acc_coa WHERE kategori_akun = 'Pendapatan' LIMIT 1");
            if ($q_fb && $rfb = mysqli_fetch_assoc($q_fb)) $id_rev = (int)$rfb['id_coa'];
        }

        $details = [
            ['id_coa' => $id_kas_coa, 'posisi' => 'Debit',  'nominal' => $abs_nom, 'memo' => "Penyesuaian Kas Fisik Lebih"],
            ['id_coa' => $id_rev,     'posisi' => 'Kredit', 'nominal' => $abs_nom, 'memo' => "Pendapatan Selisih Kas Lebih - {$co['no_opname']}"],
        ];
    }

    $res = acc_create_jurnal($conn, $header, $details);
    if ($res && !empty($res['id_jurnal'])) {
        mysqli_query($conn, "UPDATE acc_cash_opname SET id_jurnal_penyesuaian = " . (int)$res['id_jurnal'] . " WHERE id_opname = " . (int)$id_opname);
    }
    return $res;
}
