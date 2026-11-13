<?php
include('../dist/config/config.php');
include_once(__DIR__ . '/../dist/config/auth_helper.php');

$auth = new AuthHelper($conn);
if (!$auth->isLoggedIn()) { 
    header('Location: ../login.php'); 
    exit(); 
}
$auth->requirePermission('master_vendor', 'create', 'dashboard.php');

// Ambil nomor urut berikutnya dari database
$q_next = mysqli_query($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(kode_vendor, '-', -1) AS UNSIGNED)), 0) + 1 AS next_num FROM master_vendor");
$nextNum = 1;
if ($q_next && $r_next = mysqli_fetch_assoc($q_next)) {
    $nextNum = (int)$r_next['next_num'];
}
$initialKode = sprintf("VND-%03d", $nextNum);

// List default categories
$kategoriOptions = [
    'ATK', 
    'Cleaning Service', 
    'Ekspedisi', 
    'Elektronik', 
    'Entertaiment', 
    'Kendaraan', 
    'Perawatan Gedung', 
    'Percetakan', 
    'Pest Control', 
    'Teknisi',
    'Internet',
    'Perlengkapan'
];
$q_kat = mysqli_query($conn, "SELECT DISTINCT kategori_vendor FROM master_vendor WHERE kategori_vendor IS NOT NULL AND kategori_vendor != ''");
if ($q_kat) {
    while ($rk = mysqli_fetch_assoc($q_kat)) {
        if (!in_array($rk['kategori_vendor'], $kategoriOptions)) {
            $kategoriOptions[] = $rk['kategori_vendor'];
        }
    }
}
sort($kategoriOptions);
?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title">Tambah Vendor</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="dashboard.php">Master Vendor</a></li>
                                <li class="breadcrumb-item active">Tambah Vendor</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <form action="vendor_insert.php" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <!-- BAGIAN 1: INFORMASI UTAMA VENDOR -->
                                        <div class="col-12">
                                            <h5 class="form-title">
                                                <span>Informasi Utama Vendor</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Kode / ID Vendor <span class="text-danger">*</span> <small class="text-success font-weight-bold">(Otomatis)</small></label>
                                                <input type="text" name="kode_vendor" id="kode_vendor" class="form-control" value="<?= htmlspecialchars($initialKode) ?>" required />
                                                <small class="text-muted">Otomatis berubah sesuai Kategori.</small>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Nama Vendor / Perusahaan <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_vendor" class="form-control" placeholder="Contoh: Rentokil / Snapy Digital Printing" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Status Hukum <span class="text-danger">*</span></label>
                                                <select name="status_hukum" class="form-control select2" required>
                                                    <option value="">-- Pilih Status Hukum --</option>
                                                    <option value="PT">PT</option>
                                                    <option value="CV">CV</option>
                                                    <option value="Perorangan">Perorangan</option>
                                                    <option value="Yayasan">Yayasan</option>
                                                    <option value="Koperasi">Koperasi</option>
                                                    <option value="Lainnya">Lainnya</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Kategori Vendor <span class="text-danger">*</span></label>
                                                <select name="kategori_vendor" id="kategori_vendor" class="form-control select2" required>
                                                    <option value="">-- Pilih Kategori Vendor --</option>
                                                    <?php foreach ($kategoriOptions as $opt): ?>
                                                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Peruntukan Vendor <span class="text-danger">*</span></label>
                                                <input type="text" name="peruntukan_vendor" class="form-control" placeholder="Contoh: Pest Control, Karangan Bunga, AC, dll" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <div class="form-group">
                                                <label>Area Layanan <span class="text-danger">*</span></label>
                                                <select name="area" class="form-control select2" required>
                                                    <option value="">-- Pilih Area --</option>
                                                    <option value="HO">HO</option>
                                                    <option value="HO dan Cabang" selected>HO dan Cabang</option>
                                                    <option value="Cabang">Cabang</option>
                                                    <option value="Semua Area">Semua Area</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <div class="form-group">
                                                <label>Status Vendor <span class="text-danger">*</span></label>
                                                <select name="status_vendor" class="form-control select2" required>
                                                    <option value="Aktif" selected>Aktif</option>
                                                    <option value="Non-Aktif">Non-Aktif</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- BAGIAN 2: KONTAK & LOKASI -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Kontak & Lokasi</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Nama PIC Tim GA <span class="text-danger">*</span></label>
                                                <input type="text" name="pic_ga" class="form-control" placeholder="Contoh: Ara / Faris / Qibty" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Contact Vendor / WhatsApp <span class="text-danger">*</span></label>
                                                <input type="text" name="kontak_vendor" class="form-control" placeholder="Contoh: 0858-6060-6106" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Email Vendor</label>
                                                <input type="text" name="email" class="form-control" placeholder="Contoh: info@vendor.com" />
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label>Alamat Lengkap <span class="text-danger">*</span></label>
                                                <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat kantor, ruko, gedung, atau workshop vendor" required></textarea>
                                            </div>
                                        </div>

                                        <!-- BAGIAN 3: LEGALITAS & FINANSIAL -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Legalitas, Perpajakan & Perbankan</span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>NPWP <span class="text-danger">*</span></label>
                                                <input type="text" name="npwp" class="form-control" placeholder="Contoh: 01.000.179.0-058.000" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>Status PKP <span class="text-danger">*</span></label>
                                                <select name="status_pkp" class="form-control select2" required>
                                                    <option value="">-- Pilih Status PKP --</option>
                                                    <option value="Non PKP" selected>Non PKP</option>
                                                    <option value="PKP">PKP</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-group">
                                                <label>MoU (Memorandum of Understanding) <span class="text-danger">*</span></label>
                                                <select name="status_mou" id="status_mou" class="form-control select2" required>
                                                    <option value="">-- Pilih Status MoU --</option>
                                                    <option value="Ada">Ada</option>
                                                    <option value="Tidak Ada" selected>Tidak Ada</option>
                                                    <option value="Belum Ada">Belum Ada</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Soft File Dokumen MoU <span class="text-danger" id="file_mou_required" style="display:none;">*</span> <small class="text-muted" id="lbl_mou_opt">(Jika Status MoU: Ada)</small></label>
                                                <div class="custom-file">
                                                    <input type="file" name="file_mou" id="file_mou" class="custom-file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                    <label class="custom-file-label" for="file_mou">Pilih soft file MoU (PDF, DOC/DOCX, Gambar)...</label>
                                                </div>
                                                <small class="text-muted">Format: PDF, Word (DOC/DOCX), JPG, PNG (Maksimal 10MB)</small>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Lead Time Pengiriman / Pengerjaan <span class="text-danger">*</span></label>
                                                <input type="text" name="lead_time" class="form-control" placeholder="Contoh: 3 hari setelah order / Hari H after service" required />
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Nama Bank <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_bank" class="form-control" placeholder="Contoh: BCA / Mandiri / BNI" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>No. Rekening <span class="text-danger">*</span></label>
                                                <input type="text" name="no_rekening" class="form-control" placeholder="Contoh: 0664778700063218" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Atas Nama Rekening <span class="text-danger">*</span></label>
                                                <input type="text" name="atas_nama_rekening" class="form-control" placeholder="Contoh: PT. Rentokil Indonesia" required />
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <div class="form-group">
                                                <label>Metode Pembayaran <span class="text-danger">*</span></label>
                                                <input type="text" name="metode_pembayaran" class="form-control" placeholder="Contoh: Transfer / Cash / TOP" required />
                                            </div>
                                        </div>

                                        <!-- BAGIAN 4: KETERANGAN & CATATAN EVALUASI (OPSIONAL) -->
                                        <div class="col-12 mt-3">
                                            <h5 class="form-title">
                                                <span>Keterangan & Catatan Performa <small class="text-muted font-italic" style="font-size:0.75rem; text-transform:none;">(Tidak Wajib Diisi)</small></span>
                                            </h5>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Keterangan Tambahan <small class="text-muted font-italic">(Opsional)</small></label>
                                                <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: By PO, By Project, Ada di HR, dll"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label>Catatan Performa / Review <small class="text-muted font-italic">(Opsional)</small></label>
                                                <textarea name="review_performa" class="form-control" rows="3" placeholder="Contoh: Hasil pekerjaan rapi, harga bersahabat, pengerjaan cepat"></textarea>
                                            </div>
                                        </div>

                                        <div class="col-12 text-right mt-4">
                                            <a href="dashboard.php" class="btn btn-secondary mr-2">Batal</a>
                                            <button type="submit" class="btn btn-primary">Simpan Vendor</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include '../layouts/footer.php'; ?>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });

            const nextNum = <?= (int)$nextNum ?>;
            const padNum = String(nextNum).padStart(3, '0');

            const prefixMap = {
                'Pest Control': 'PC',
                'Cleaning Service': 'CS',
                'Entertaiment': 'ENT',
                'Entertainment': 'ENT',
                'Teknisi': 'TK',
                'Kendaraan': 'KND',
                'Elektronik': 'ELK',
                'ATK': 'ATK',
                'Perawatan Gedung': 'PG',
                'Ekspedisi': 'EKS',
                'Percetakan': 'PRC',
                'Internet': 'INT',
                'Perlengkapan': 'PLG'
            };

            $('#kategori_vendor').on('change', function() {
                const kat = $(this).val();
                if (!kat) {
                    $('#kode_vendor').val('VND-' + padNum);
                    return;
                }

                let prefix = prefixMap[kat];
                if (!prefix) {
                    prefix = kat.replace(/[^a-zA-Z]/g, '').substr(0, 3).toUpperCase();
                    if (prefix.length < 2) prefix = 'VND';
                }

                const autoKode = prefix + '-VND-' + padNum;
                $('#kode_vendor').val(autoKode);
            });

            // Update label custom-file-input saat file dipilih
            $('#file_mou').on('change', function() {
                const fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').addClass("selected").html(fileName || 'Pilih soft file MoU...');
            });

            // Tampilkan required indicator jika status MoU = Ada
            function checkMouStatus() {
                const mou = $('#status_mou').val();
                if (mou === 'Ada') {
                    $('#file_mou_required').show();
                    $('#lbl_mou_opt').html('<span class="text-danger font-weight-bold">(Wajib diunggah saat ada MoU)</span>');
                    $('#file_mou').attr('required', true);
                } else {
                    $('#file_mou_required').hide();
                    $('#lbl_mou_opt').html('(Jika Status MoU: Ada)');
                    $('#file_mou').removeAttr('required');
                }
            }
            $('#status_mou').on('change', checkMouStatus);
            checkMouStatus();
        });
    </script>
</body>
</html>
