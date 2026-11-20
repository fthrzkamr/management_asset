<?php
/**
 * PPX Helper & Fase 2 Business Logic
 * PT Dua Farma Mahakarsa
 */

if (!function_exists('init_ppx_fase2_db')) {
    function init_ppx_fase2_db($conn) {
        static $initialized = false;
        if ($initialized) return;
        $initialized = true;

        // 1. Table ppx_master_cabang
        $sql_cab = "CREATE TABLE IF NOT EXISTS ppx_master_cabang (
            id_cabang_ppx INT(11) NOT NULL AUTO_INCREMENT,
            kode_cabang VARCHAR(20) NOT NULL UNIQUE,
            nama_cabang VARCHAR(100) NOT NULL,
            id_cabang_asset VARCHAR(10) NULL,
            alamat TEXT NULL,
            telepon VARCHAR(50) NULL,
            status ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_cabang_ppx),
            KEY (kode_cabang),
            KEY (id_cabang_asset)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        @mysqli_query($conn, $sql_cab);

        // Seed default branches if empty
        $chk_seed = @mysqli_query($conn, "SELECT COUNT(*) FROM ppx_master_cabang");
        if ($chk_seed) {
            $cnt_seed = (int)mysqli_fetch_row($chk_seed)[0];
            if ($cnt_seed === 0) {
                $seed_data = [
                    ['kode' => 'JKT', 'nama' => 'Jakarta',     'asset' => 'CAB001'],
                    ['kode' => 'CBN', 'nama' => 'Cibinong',    'asset' => 'CAB002'],
                    ['kode' => 'BKS', 'nama' => 'Bekasi',      'asset' => 'CAB003'],
                    ['kode' => 'MDN', 'nama' => 'Medan',       'asset' => 'CAB004'],
                    ['kode' => 'DPS', 'nama' => 'Bali',        'asset' => 'CAB005'],
                    ['kode' => 'MLG', 'nama' => 'Malang',      'asset' => 'CAB006'],
                    ['kode' => 'SBY', 'nama' => 'Surabaya',    'asset' => 'CAB007'],
                    ['kode' => 'BJM', 'nama' => 'Banjarmasin', 'asset' => 'CAB008'],
                    ['kode' => 'GOR', 'nama' => 'Gorontalo',   'asset' => 'CAB009'],
                    ['kode' => 'PLM', 'nama' => 'Palembang',   'asset' => 'CAB010'],
                ];
                foreach ($seed_data as $s) {
                    @mysqli_query($conn, "INSERT INTO ppx_master_cabang (kode_cabang, nama_cabang, id_cabang_asset) 
                                         VALUES ('{$s['kode']}', '{$s['nama']}', '{$s['asset']}')");
                }
            }
        }

        // 2. Extra columns in ppx_realisasi_detail2
        $cols = [
            'delete_status'       => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN delete_status ENUM('None','Requested','Approved','Rejected') NOT NULL DEFAULT 'None' AFTER status",
            'delete_reason'       => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN delete_reason TEXT NULL AFTER delete_status",
            'delete_requested_by' => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN delete_requested_by INT(11) NULL AFTER delete_reason",
            'delete_requested_at' => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN delete_requested_at DATETIME NULL AFTER delete_requested_by",
            'delete_approved_by'  => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN delete_approved_by INT(11) NULL AFTER delete_requested_at",
            'delete_approved_at'  => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN delete_approved_at DATETIME NULL AFTER delete_approved_by",
            'nota_susulan_files'  => "ALTER TABLE ppx_realisasi_detail2 ADD COLUMN nota_susulan_files TEXT NULL AFTER bukti_files",
        ];
        foreach ($cols as $col => $alt_sql) {
            $c_res = @mysqli_query($conn, "SHOW COLUMNS FROM ppx_realisasi_detail2 LIKE '$col'");
            if ($c_res && mysqli_num_rows($c_res) === 0) {
                @mysqli_query($conn, $alt_sql);
            }
        }
    }
}

if (!function_exists('get_kode_cabang_ppx')) {
    function get_kode_cabang_ppx($conn, $id_budget, $user_id = null) {
        static $cache = [];
        $cache_key = "b_{$id_budget}_u_{$user_id}";
        if (isset($cache[$cache_key])) return $cache[$cache_key];

        $kode = 'JKT';
        
        $q = mysqli_query($conn, "
            SELECT u.id_cabang, mc.nama_cabang, pmc.kode_cabang AS ppx_kode
            FROM ppx_monthly_budget mb
            JOIN user_dfm u ON mb.created_by = u.id_user
            LEFT JOIN master_cabang mc ON u.id_cabang = mc.id_cabang
            LEFT JOIN ppx_master_cabang pmc ON (pmc.id_cabang_asset = u.id_cabang OR pmc.nama_cabang = mc.nama_cabang)
            WHERE mb.id_budget = " . (int)$id_budget . " LIMIT 1
        ");
        if ($q && $r = mysqli_fetch_assoc($q)) {
            if (!empty($r['ppx_kode'])) {
                $kode = strtoupper($r['ppx_kode']);
            } elseif (!empty($r['nama_cabang'])) {
                $q2 = mysqli_query($conn, "SELECT kode_cabang FROM ppx_master_cabang WHERE nama_cabang LIKE '%" . mysqli_real_escape_string($conn, $r['nama_cabang']) . "%' LIMIT 1");
                if ($q2 && $r2 = mysqli_fetch_assoc($q2)) {
                    $kode = strtoupper($r2['kode_cabang']);
                } else {
                    $kode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $r['nama_cabang']), 0, 3));
                }
            }
        }
        
        $cache[$cache_key] = $kode;
        return $kode;
    }
}

if (!function_exists('generate_no_transaksi_ppx')) {
    function generate_no_transaksi_ppx($conn, $jenis, $id_budget, $tanggal = null) {
        $jenis = in_array(strtoupper($jenis), ['BKK', 'BKM']) ? strtoupper($jenis) : 'BKK';
        $tgl   = !empty($tanggal) ? strtotime($tanggal) : time();
        $dd    = date('d', $tgl);
        $mm    = date('m', $tgl);
        $yyyy  = date('Y', $tgl);

        $kode_cabang = get_kode_cabang_ppx($conn, $id_budget);

        $like_pattern = "{$jenis}/{$kode_cabang}-%/{$yyyy}/{$mm}/%";
        $q = mysqli_query($conn, "SELECT no_realisasi FROM ppx_realisasi_detail2 WHERE no_realisasi LIKE '" . mysqli_real_escape_string($conn, $like_pattern) . "' ORDER BY id_real_detail DESC LIMIT 1");
        $seq = 1;
        if ($q && mysqli_num_rows($q) > 0) {
            $last_no = mysqli_fetch_assoc($q)['no_realisasi'];
            $parts   = explode('/', $last_no);
            $last_seq = end($parts);
            if (is_numeric($last_seq)) {
                $seq = (int)$last_seq + 1;
            }
        }
        $seq_padded = str_pad($seq, 4, '0', STR_PAD_LEFT);
        return "{$jenis}/{$kode_cabang}-{$dd}-{$mm}/{$yyyy}/{$mm}/{$seq_padded}";
    }
}

if (!function_exists('check_realisasi_threshold')) {
    /**
     * Cek apakah transaksi realisasi wajib approval Asmen:
     * 1. Nominal > 5.000.000
     * 2. Over budget (realisasi item melebihi total alokasi item atau alokasi budget)
     */
    function check_realisasi_threshold($conn, $id_budget, $items_input, $jenis = 'BKK') {
        $max_threshold = 5000000; // Rp 5.000.000
        $needs_approval = false;
        $reasons = [];

        $total_transaksi = 0;
        foreach ($items_input as $it) {
            $total_transaksi += (float)($it['nominal'] ?? 0);
        }

        // Cek threshold nominal > 5jt
        if ($total_transaksi > $max_threshold) {
            $needs_approval = true;
            $reasons[] = "Nominal transaksi (Rp " . number_format($total_transaksi, 0, ',', '.') . ") melebihi batas Rp 5.000.000";
        }

        // Cek alokasi per item jika BKK (pengeluaran)
        if ($jenis === 'BKK') {
            foreach ($items_input as $it) {
                $det_id = (int)($it['id_detail'] ?? 0);
                $nom    = (float)($it['nominal'] ?? 0);
                if ($det_id <= 0 || $nom <= 0) continue;

                $q_it = mysqli_query($conn, "SELECT total, rincian FROM ppx_monthly_budget_item_detail WHERE id = $det_id LIMIT 1");
                if ($q_it && $r_it = mysqli_fetch_assoc($q_it)) {
                    $alokasi_item = (float)$r_it['total'];

                    // Hitung realisasi saat ini
                    $q_sp = mysqli_query($conn, "
                        SELECT 
                            SUM(CASE WHEN jenis_transaksi = 'BKK' AND status = 'Disetujui' AND delete_status != 'Approved' THEN nominal_realisasi ELSE 0 END) AS spent_bkk,
                            SUM(CASE WHEN jenis_transaksi = 'BKM' AND status = 'Disetujui' AND delete_status != 'Approved' THEN (CASE WHEN uang_kembali > 0 THEN uang_kembali ELSE nominal_realisasi END) ELSE 0 END) AS back_bkm
                        FROM ppx_realisasi_detail2
                        WHERE id_detail_budget = $det_id
                    ");
                    $cur_spent = 0;
                    if ($q_sp && $r_sp = mysqli_fetch_assoc($q_sp)) {
                        $cur_spent = max(0, (float)$r_sp['spent_bkk'] - (float)$r_sp['back_bkm']);
                    }

                    if ($alokasi_item > 0 && ($cur_spent + $nom) > $alokasi_item) {
                        $needs_approval = true;
                        $reasons[] = "Item '{$r_it['rincian']}' over budget (Alokasi: Rp " . number_format($alokasi_item, 0, ',', '.') . ", Terealisasi akan jadi: Rp " . number_format($cur_spent + $nom, 0, ',', '.') . ")";
                    }
                }
            }

            // Cek total budget allocation
            $q_bg = mysqli_query($conn, "SELECT total_allocation FROM ppx_monthly_budget WHERE id_budget = " . (int)$id_budget . " LIMIT 1");
            if ($q_bg && $r_bg = mysqli_fetch_assoc($q_bg)) {
                $tot_alloc = (float)$r_bg['total_allocation'];
                $q_bg_sp = mysqli_query($conn, "
                    SELECT 
                        SUM(CASE WHEN jenis_transaksi = 'BKK' AND status = 'Disetujui' AND delete_status != 'Approved' THEN nominal_realisasi ELSE 0 END) AS spent_bkk,
                        SUM(CASE WHEN jenis_transaksi = 'BKM' AND status = 'Disetujui' AND delete_status != 'Approved' THEN (CASE WHEN uang_kembali > 0 THEN uang_kembali ELSE nominal_realisasi END) ELSE 0 END) AS back_bkm
                    FROM ppx_realisasi_detail2
                    WHERE id_budget = " . (int)$id_budget
                );
                $cur_tot_spent = 0;
                if ($q_bg_sp && $r_bg_sp = mysqli_fetch_assoc($q_bg_sp)) {
                    $cur_tot_spent = max(0, (float)$r_bg_sp['spent_bkk'] - (float)$r_bg_sp['back_bkm']);
                }
                if ($tot_alloc > 0 && ($cur_tot_spent + $total_transaksi) > $tot_alloc) {
                    $needs_approval = true;
                    $reasons[] = "Total anggaran bulanan over budget (Alokasi: Rp " . number_format($tot_alloc, 0, ',', '.') . ", Terealisasi akan jadi: Rp " . number_format($cur_tot_spent + $total_transaksi, 0, ',', '.') . ")";
                }
            }
        }

        return [
            'needs_approval' => $needs_approval,
            'reasons'        => $reasons
        ];
    }
}
