<?php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

/**
 * Format number safely, handling empty or non-numeric values
 * @param mixed $value Value to format
 * @param int $decimals Number of decimal points
 * @return string Formatted number
 */
function safeNumberFormat($value, $decimals = 0)
{
    // Jika nilai null atau string kosong
    if ($value === null || $value === '') {
        return number_format(0, $decimals, ',', '.');
    }

    // Jika nilai tidak numerik, coba konversi
    if (!is_numeric($value)) {
        // Bersihkan nilai dari karakter non-numerik
        $cleanValue = preg_replace('/[^0-9.]/', '', $value);
        if (!is_numeric($cleanValue)) {
            return number_format(0, $decimals, ',', '.');
        }
        $value = $cleanValue;
    }

    return number_format((float)$value, $decimals, ',', '.');
}

$auth = new AuthHelper($conn);

// Cek login dan permission
if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}
$auth->requirePermission('pengajuan_asset', 'read');

// Cek apakah ada parameter no_ppx untuk print detail spesifik
$no_ppx = $_GET['no_ppx'] ?? '';

if (!empty($no_ppx)) {
    // PRINT DETAIL PPX SPESIFIK (seperti detail.php)
    $no_ppx = trim($no_ppx);
    $no_ppx_safe = mysqli_real_escape_string($conn, $no_ppx);

    // Query untuk mendapatkan data PPX Request
    $query_ppx = "SELECT pr.*, u.nama_user as nama_pengaju 
                  FROM ppx_request pr
                  LEFT JOIN user_dfm u ON pr.user_id = u.id_user
                  WHERE pr.no_ppx = '$no_ppx_safe'
                  LIMIT 1";

    $result_ppx = mysqli_query($conn, $query_ppx);

    if (!$result_ppx || mysqli_num_rows($result_ppx) === 0) {
        echo "<script>alert('Data PPX tidak ditemukan!'); window.close();</script>";
        exit();
    }

    $ppx_data = mysqli_fetch_assoc($result_ppx);

    // Query untuk mendapatkan detail items
    $query_detail = "SELECT * FROM ppx_request_detail 
                     WHERE no_ppx = '$no_ppx_safe'
                     ORDER BY no_ppx_detail";

    $result_detail = mysqli_query($conn, $query_detail);
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detail PPX - <?= htmlspecialchars($no_ppx) ?></title>
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }

                @page {
                    margin: 1cm;
                    size: A4;
                }
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                margin: 20px;
                color: #000;
            }

            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
            }

            .company-name {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .document-title {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
            }

            .print-info {
                font-size: 11px;
                color: #666;
            }

            .info-section {
                margin-bottom: 25px;
            }

            .section-title {
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 15px;
                padding: 8px 0;
                border-bottom: 1px solid #ccc;
                background-color: #f5f5f5;
                padding-left: 10px;
            }

            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }

            /* Border utama untuk cell (default) */
            .info-table td {
                border: 1px solid #000;
                padding: 8px;
                vertical-align: top;
            }

            /* Hindari border ganda pada tabel internal (inner table) */
            .info-table td table td {
                border: none;
                padding: 6px 8px;
            }

            /* Tipiskan border sisi kiri dan kanan pada cell terluar agar tidak terlihat terlalu tebal */
            .info-table>tr>td:first-child,
            .info-table tr>td:first-child {
                border-left-width: 0.6px;
            }

            .info-table>tr>td:last-child,
            .info-table tr>td:last-child {
                border-right-width: 0.6px;
            }

            /* Kurangi jarak antara label dan isi pada tabel info (mis. Alasan Pengajuan) */
            .info-table td table td:first-child {
                width: 140px;
                /* tetapkan lebar kolom label */
                font-weight: bold;
                padding-right: 8px;
                /* jarak label -> isi */
                vertical-align: top;
            }

            .info-table td table td:last-child {
                padding-left: 6px;
                /* kurangi padding kiri untuk isi agar tidak terlalu jauh */
            }

            .detail-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }

            .detail-table th,
            .detail-table td {
                border: 1px solid #000;
                padding: 6px;
                text-align: left;
                font-size: 11px;
            }

            .detail-table th {
                font-weight: bold;
                text-align: center;
                background-color: #f5f5f5;
            }

            .text-center {
                text-align: center;
            }

            .text-right {
                text-align: right;
            }

            .text-bold {
                font-weight: bold;
            }

            .total-row {
                background-color: #f0f8ff;
                font-weight: bold;
            }

            .footer {
                margin-top: 30px;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 10px;
            }

            .btn-print {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin: 10px;
            }

            .btn-print:hover {
                background-color: #0056b3;
            }

            .btn-close {
                background-color: #6c757d;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin: 10px;
            }

            .btn-close:hover {
                background-color: #545b62;
            }

            .print-buttons {
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
    </head>

    <body>
        <div class="print-buttons no-print">
            <button class="btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> Print Laporan
            </button>
            <button type="button" class="btn-close" onclick="tutupHalaman()">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>

        <div class="header">
            <div class="company-name">DF GROUP</div>
            <div class="document-title">DETAIL PENGAJUAN PPX</div>
        </div>

        <div class="info-section">
            <div class="section-title">INFORMASI PPX : <?= htmlspecialchars($ppx_data['no_ppx']) ?></div>

            <!-- layout dua kolom: kiri = nama PIC, jabatan, divisi, cabang, tgl pengajuan, tgl dibutuhkan
             kanan = tingkat kepentingan, status, uang muka, nominal realisasi, total nominal, dibuat -->
            <table class="info-table">
                <tr>
                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td class="info-label">Nama :</td>
                                <td><?= htmlspecialchars($ppx_data['nama_pic']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Jabatan :</td>
                                <td><?= htmlspecialchars($ppx_data['jabatan']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Divisi :</td>
                                <td><?= htmlspecialchars($ppx_data['divisi']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Cabang :</td>
                                <td><?= htmlspecialchars($ppx_data['cabang']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Tanggal Pengajuan :</td>
                                <td><?= $ppx_data['tanggal_pengajuan'] ? date('d/m/Y', strtotime($ppx_data['tanggal_pengajuan'])) : '-' ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Tanggal Dibutuhkan :</td>
                                <td><?= $ppx_data['tanggal_dibutuhkan'] ? date('d/m/Y', strtotime($ppx_data['tanggal_dibutuhkan'])) : '-' ?></td>
                            </tr>
                        </table>
                    </td>

                    <td style="width:50%; vertical-align: top; padding: 0;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td class="info-label">Tingkat Kepentingan :</td>
                                <td><?= htmlspecialchars($ppx_data['tingkat_kepentingan']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Status :</td>
                                <td><?= htmlspecialchars($ppx_data['status'] === 'Leader' ? 'Kacap/Manager' : $ppx_data['status']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Uang Muka :</td>
                                <td>Rp <?= safeNumberFormat($ppx_data['uang_muka']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Nominal Realisasi :</td>
                                <td>Rp <?= safeNumberFormat($ppx_data['nominal_realisasi']) ?></td>
                            </tr>
                            <tr>
                                <td class="info-label">Total Nominal :</td>
                                <td>Rp <?= safeNumberFormat($ppx_data['nominal_ppx']) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="vertical-align: top;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td class="info-label">Alasan Pengajuan :</td>
                                <td><?= nl2br(htmlspecialchars($ppx_data['alasan_pengajuan'])) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="info-section">
            <div class="section-title">DETAIL ITEM PPX (<?= mysqli_num_rows($result_detail) ?> item)</div>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="12%">No. Akun</th>
                        <th width="30%">Rincian Item</th>
                        <th width="15%">Deskripsi</th>
                        <th width="8%">Jumlah</th>
                        <th width="10%">Satuan</th>
                        <th width="15%">Harga Satuan</th>
                        <th width="15%">Total Harga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result_detail) > 0): ?>
                        <?php $total_all = 0;
                        $no = 1; ?>
                        <?php while ($detail = mysqli_fetch_assoc($result_detail)): ?>
                            <?php $total_all += $detail['total_harga']; ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($detail['no_akun']) ?></td>
                                <td><?= htmlspecialchars($detail['rincian']) ?></td>
                                <td><?= !empty($detail['deskripsi']) ? htmlspecialchars($detail['deskripsi']) : '-' ?></td>
                                <td class="text-right"><?= safeNumberFormat($detail['jumlah'], 0) ?></td>
                                <td class="text-center"><?= htmlspecialchars($detail['satuan']) ?></td>
                                <td class="text-right">Rp <?= safeNumberFormat($detail['harga_satuan']) ?></td>
                                <td class="text-right text-bold">Rp <?= safeNumberFormat($detail['total_harga']) ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <tr class="total-row">
                            <td colspan="7" class="text-right text-bold">TOTAL :</td>
                            <td class="text-right text-bold">Rp <?= safeNumberFormat($total_all) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada detail item untuk PPX ini</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>Dokumen dicetak pada: <?= date('d/m/Y H:i:s') ?> oleh: <?= htmlspecialchars($_SESSION['nama_user']) ?></p>
            <p>Sistem Pengajuan PPX - DF GROUP</p>
        </div>

        <script>
            // Auto print dialog saat halaman selesai load
            window.addEventListener('load', function() {
                // Delay sedikit untuk memastikan halaman benar-benar siap
                setTimeout(function() {
                    window.print();
                }, 500);
            });

            // Tutup window setelah print selesai (opsional)
            window.addEventListener('afterprint', function() {
                // Uncomment baris di bawah jika ingin otomatis tutup setelah print
                window.close();
            });

            function tutupHalaman() {
                // Coba tutup jendela
                window.open('', '_self', ''); // trik agar diizinkan di beberapa browser
                if (window.close) {
                    window.close();
                }

                // Fallback: kalau tidak bisa ditutup, kembali ke halaman sebelumnya
                setTimeout(() => {
                    if (!window.closed) {
                        history.back();
                    }
                }, 200);
            }
        </script>
    </body>

    </html>

<?php
} else {
    // PRINT SUMMARY SEMUA PPX (tanpa detail item)

    // Query berdasarkan role
    $whereClause = "";
    if ($auth->isKoordinator()) {
        $whereClause = "WHERE pr.user_id = " . (int)$_SESSION['id_user'];
    }

    // Query summary per PPX
    $query = "SELECT 
        pr.no_ppx,
        pr.tanggal_pengajuan,
        pr.nama_pic,
        pr.jabatan,
        pr.divisi,
        pr.cabang,
        pr.alasan_pengajuan,
        pr.tingkat_kepentingan,
        pr.tanggal_dibutuhkan,
        pr.status,
        pr.nominal_ppx,
        pr.uang_muka,
        pr.nominal_realisasi,
        u.nama_user as nama_pengaju,
        COUNT(prd.no_ppx_detail) as jumlah_item,
        COALESCE(SUM(prd.total_harga), 0) as total_nominal_detail
    FROM ppx_request pr
    LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
    LEFT JOIN user_dfm u ON pr.user_id = u.id_user
    $whereClause
    GROUP BY pr.no_ppx
    ORDER BY pr.tanggal_pengajuan DESC";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query Error: " . mysqli_error($conn));
    }
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Pengajuan PPX</title>
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }

                @page {
                    margin: 0.5cm;
                    size: A4 landscape;
                }
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                line-height: 1.3;
                margin: 15px;
                color: #000;
            }

            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
            }

            .company-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .document-title {
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 8px;
            }

            .print-info {
                font-size: 10px;
                color: #666;
            }

            table {
                border-collapse: collapse;
                width: 100%;
                font-size: 9px;
            }

            th,
            td {
                border: 1px solid #000;
                padding: 4px;
                text-align: left;
            }

            th {
                font-weight: bold;
                background-color: #f5f5f5;
                text-align: center;
            }

            .center {
                text-align: center;
            }

            .right {
                text-align: right;
            }

            .currency {
                text-align: right;
            }

            .summary-section {
                margin-top: 20px;
                page-break-inside: avoid;
            }

            .summary-title {
                font-size: 12px;
                font-weight: bold;
                margin-bottom: 10px;
            }

            .summary-table {
                width: 50%;
            }

            .footer {
                margin-top: 20px;
                font-size: 9px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 10px;
            }

            .btn-print {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin: 10px;
            }

            .btn-print:hover {
                background-color: #0056b3;
            }

            .btn-close {
                background-color: #6c757d;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin: 10px;
            }

            .btn-close:hover {
                background-color: #545b62;
            }

            .print-buttons {
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
    </head>

    <body>
        <div class="print-buttons no-print">
            <button class="btn-print" onclick="window.print();">
                <i class="fas fa-print"></i> Print Laporan
            </button>
            <button class="btn-close" onclick="window.close();">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>

        <div class="header">
            <div class="company-name">DF GROUP</div>
            <div class="document-title">LAPORAN PENGAJUAN PPX</div>
            <div class="print-info">Dicetak: <?= date('d/m/Y H:i:s') ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>No. PPX</th>
                    <th>Tanggal Pengajuan</th>
                    <th>Nama PIC</th>
                    <th>Jabatan</th>
                    <th>Divisi</th>
                    <th>Cabang</th>
                    <th>Jumlah Item</th>

                    <!-- added columns -->
                    <th>Uang Muka</th>
                    <th>Nominal Realisasi</th>

                    <th>Total Nominal (Detail)</th>
                    <th>Nominal PPX (Field)</th>
                    <th>Tingkat Kepentingan</th>
                    <th>Tanggal Dibutuhkan</th>
                    <th>Alasan Pengajuan</th>
                    <th>Status</th>
                    <th>Nama Pengaju</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td class="center"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['no_ppx']) ?></td>
                        <td class="center"><?= $row['tanggal_pengajuan'] ? date('d/m/Y', strtotime($row['tanggal_pengajuan'])) : '-' ?></td>
                        <td><?= htmlspecialchars($row['nama_pic']) ?></td>
                        <td><?= htmlspecialchars($row['jabatan']) ?></td>
                        <td><?= htmlspecialchars($row['divisi']) ?></td>
                        <td><?= htmlspecialchars($row['cabang']) ?></td>
                        <td class="center"><?= (int)$row['jumlah_item'] ?></td>

                        <!-- display uang_muka and nominal_realisasi -->
                        <td class="currency">Rp <?= safeNumberFormat($row['uang_muka']) ?></td>
                        <td class="currency">Rp <?= safeNumberFormat($row['nominal_realisasi']) ?></td>

                        <td class="currency">Rp <?= safeNumberFormat($row['total_nominal_detail']) ?></td>
                        <td class="currency">Rp <?= safeNumberFormat($row['nominal_ppx']) ?></td>
                        <td><?= htmlspecialchars($row['tingkat_kepentingan']) ?></td>
                        <td class="center"><?= $row['tanggal_dibutuhkan'] ? date('d/m/Y', strtotime($row['tanggal_dibutuhkan'])) : '-' ?></td>
                        <td><?= htmlspecialchars($row['alasan_pengajuan']) ?></td>
                        <td><?= htmlspecialchars($row['status'] === 'Leader' ? 'Kacap/Manager' : $row['status']) ?></td>
                        <td><?= htmlspecialchars($row['nama_pengaju']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php
        // Ringkasan
        $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN pr.status IN ('Kacap/Manager','Leader') THEN 1 ELSE 0 END) as pending_leader,
        SUM(CASE WHEN pr.status = 'Manager' THEN 1 ELSE 0 END) as pending_manager,
        SUM(CASE WHEN pr.status = 'Finance' THEN 1 ELSE 0 END) as pending_finance,
        SUM(CASE WHEN pr.status = 'Direktur' THEN 1 ELSE 0 END) as pending_direktur,
        SUM(CASE WHEN pr.status = 'Done' THEN 1 ELSE 0 END) as done,
        SUM(CASE WHEN pr.status = 'Revisi' THEN 1 ELSE 0 END) as revisi,
        COALESCE(SUM(prd.total_harga),0) as total_all_detail,
        COALESCE(SUM(pr.uang_muka),0) as total_uang_muka,
        COALESCE(SUM(pr.nominal_realisasi),0) as total_nominal_realisasi
    FROM ppx_request pr
    LEFT JOIN ppx_request_detail prd ON pr.no_ppx = prd.no_ppx
    $whereClause";

        $stats_result = mysqli_query($conn, $stats_query);
        $stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [];
        ?>

        <div class="summary-section">
            <div class="summary-title">RINGKASAN</div>
            <table class="summary-table">
                <tr>
                    <td>Total Pengajuan</td>
                    <td class="right"><?= (int)($stats['total'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Pending (Kacap/Manager)</td>
                    <td class="right"><?= (int)($stats['pending_leader'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Pending (Manager)</td>
                    <td class="right"><?= (int)($stats['pending_manager'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Pending (Finance)</td>
                    <td class="right"><?= (int)($stats['pending_finance'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Disetujui (Done)</td>
                    <td class="right"><?= (int)($stats['done'] ?? 0) ?></td>
                </tr>
                <tr>
                    <td>Revisi</td>
                    <td class="right"><?= (int)($stats['revisi'] ?? 0) ?></td>
                </tr>

                <!-- summary tambahan -->
                <tr>
                    <td>Total Uang Muka</td>
                    <td class="right currency">Rp <?= safeNumberFormat(($stats['total_uang_muka'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Total Nominal Realisasi</td>
                    <td class="right currency">Rp <?= safeNumberFormat(($stats['total_nominal_realisasi'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Total Semua Nominal (detail)</td>
                    <td class="right currency">Rp <?= safeNumberFormat(($stats['total_all_detail'] ?? 0)) ?></td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Dokumen dicetak pada: <?= date('d/m/Y H:i:s') ?> oleh: <?= htmlspecialchars($_SESSION['nama_user']) ?></p>
            <p>Sistem Pengajuan PPX - DF GROUP</p>
        </div>

        <script>
            // Auto print dialog saat halaman selesai load
            window.addEventListener('load', function() {
                // Delay sedikit untuk memastikan halaman benar-benar siap
                setTimeout(function() {
                    window.print();
                }, 500);
            });

            // Tutup window setelah print selesai (opsional)
            window.addEventListener('afterprint', function() {
                // Uncomment baris di bawah jika ingin otomatis tutup setelah print
                window.close();
            });
        </script>
    </body>

    </html>

<?php
}

mysqli_close($conn);
?>
