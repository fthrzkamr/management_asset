<?php
// filepath: c:\xampp\htdocs\asset_dfm\service_asset\service_add.php
include('../dist/config/config.php');
include('../dist/config/auth_helper.php');

// Cek session login
$auth = new AuthHelper($conn);

if (!$auth->isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Cek permission - harus punya izin create
$auth->requirePermission('pengajuan_service', 'create');


// PERBAIKAN: Query dengan filter asset yang sedang service
$qAsset = mysqli_query($conn, "
    SELECT 
        a.kode_aset, 
        a.nama_aset, 
        a.merk, 
        a.spesifikasi,
        a.kategori_aset, 
        a.lokasi, 
        a.lokasi_detail,
        a.kondisi,
        a.user,
        'IT' as divisi,
        CASE 
            WHEN s.kode_aset IS NOT NULL THEN 1 
            ELSE 0 
        END as is_service,
        s.status_service,
        s.no_service
    FROM asset_dfm a
    LEFT JOIN service_asset s ON a.kode_aset = s.kode_aset AND s.status_service = 'Service'

    UNION ALL

    SELECT 
        a.kode_aset, 
        a.nama_aset, 
        a.merk, 
        a.spesifikasi,
        a.kategori_aset, 
        a.lokasi, 
        a.lokasi_detail,
        a.kondisi,
        a.user,
        'GA' as divisi,
        CASE 
            WHEN s.kode_aset IS NOT NULL THEN 1 
            ELSE 0 
        END as is_service,
        s.status_service,
        s.no_service
    FROM asset_ga a
    LEFT JOIN service_asset s ON a.kode_aset = s.kode_aset AND s.status_service = 'Service'

    ORDER BY divisi DESC, is_service ASC, nama_aset ASC
");

// Ambil data teknisi
$qTeknisi = mysqli_query($conn, "
    SELECT DISTINCT user 
    FROM asset_dfm 
    WHERE user IS NOT NULL AND user != ''
    ORDER BY user ASC
");

// Hitung jumlah asset yang sedang service
$countServiceQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM service_asset WHERE status_service = 'Service'");
$countService = mysqli_fetch_assoc($countServiceQuery)['total'];

// Simpan data asset dalam array untuk JavaScript
mysqli_data_seek($qAsset, 0);
$assetData = [];
while ($asset = mysqli_fetch_assoc($qAsset)) {
    $assetData[] = [
        'kode_aset' => $asset['kode_aset'],
        'nama_aset' => $asset['nama_aset'],
        'merk' => $asset['merk'],
        'spesifikasi' => $asset['spesifikasi'],
        'kategori_aset' => $asset['kategori_aset'],
        'lokasi' => $asset['lokasi'],
        'lokasi_detail' => $asset['lokasi_detail'],
        'kondisi' => $asset['kondisi'],
        'user' => $asset['user'],
        'divisi' => $asset['divisi'],
        'is_service' => $asset['is_service']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Service Asset - DFM</title>
    <link rel="icon" href="../assets/img/dua.png" type="image/png">
    <!-- CSS akan di-load dari navbar -->

    <!-- PERBAIKAN: CSS untuk asset yang sedang service -->
    <style>
        .asset-service-option {
            background-color: #fff3cd !important;
            color: #856404 !important;
            font-style: italic;
        }

        /* .asset-available-option {
            background-color: #d4edda !important;
            color: #155724 !important;
        } */

        .service-alert {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .available-alert {
            border-left: 4px solid #28a745;
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .select2-container--default .select2-results__option[aria-disabled=true] {
            background-color: #fff3cd !important;
            color: #856404 !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-disabled=true] {
            background-color: #ffeaa7 !important;
            color: #856404 !important;
        }

        .readonly-field {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }
        /* Custom Select2 styling for consistent height */
        .select2-container .select2-selection--single {
            height: 38px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
        /* Make dropdown wider than the input for more info */
        @media (min-width: 768px) {
            .select2-dropdown {
                min-width: 500px !important;
            }
        }
    </style>
    
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <!-- PERBAIKAN: Responsive container -->
            <div class="content container-fluid px-2 px-md-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <!-- PERBAIKAN: Responsive title -->
                            <h3 class="page-title h4 h3-md">Tambah Service Asset</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Service Asset</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Service</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- PERBAIKAN: Responsive alerts -->
                <?php if ($countService > 0): ?>
                    <div class="alert service-alert" role="alert">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle fa-lg mr-2 mt-1"></i>
                            <div>
                                <strong>Perhatian!</strong> Saat ini ada <strong><?= $countService ?></strong> asset yang sedang dalam status service.
                                <!-- PERBAIKAN: Responsive text -->
                                <br><small class="d-block d-sm-inline">Asset yang sedang service akan ditandai dan tidak dapat dipilih untuk service baru.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="available-alert alert" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle fa-lg mr-2 mt-1"></i>
                        <div>
                            <strong>Info:</strong> Hanya asset yang tidak sedang service yang dapat dipilih untuk service baru.
                            <!-- PERBAIKAN: Responsive text -->
                            <br><small class="d-block d-sm-inline">⚠️ Kuning = Sedang service</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <!-- PERBAIKAN: Responsive card body -->
                            <div class="card-body px-3 px-md-4">
                                <form action="service_insert.php" method="POST" enctype="multipart/form-data" id="formService">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <!-- PERBAIKAN: Responsive form title -->
                                            <h5 class="form-title mb-3 h6 h5-md">
                                                <span>Informasi Service Asset</span>
                                            </h5>
                                        </div>

                                        <!-- Daftar Asset untuk Service -->
                                        <div class="col-12 mb-3">
                                            <h6 class="font-weight-bold mb-2">
                                                <i class="fas fa-boxes"></i> Daftar Asset yang Akan di Service <span class="text-danger">*</span>
                                            </h6>
                                        </div>
                                    </div>

                                    <!-- Asset Items Container -->
                                    <div id="assetItemsContainer">
                                        <!-- Item Asset Pertama -->
                                        <div class="asset-item-row border rounded p-3 mb-3 position-relative" data-index="0">
                                            <button type="button" class="btn btn-sm btn-danger btn-remove-asset" data-index="0" style="position: absolute; top: 10px; right: 10px; z-index: 100;" title="Hapus asset">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            
                                            <div class="row">
                                                <div class="col-12 col-md-6 mb-3">
                                                    <label class="font-weight-bold small">Kode Asset <span class="text-danger">*</span></label>
                                                    <select name="assets[0][kode_aset]" class="form-control form-control-sm asset-select" required data-index="0">
                                                        <option value="">-- Pilih Asset --</option>
                                                        <?php 
                                                        mysqli_data_seek($qAsset, 0); 
                                                        $currentDivisi = '';
                                                        while ($asset = mysqli_fetch_assoc($qAsset)): 
                                                            if ($asset['divisi'] !== $currentDivisi) {
                                                                if ($currentDivisi !== '') echo '</optgroup>';
                                                                $currentDivisi = $asset['divisi'];
                                                                echo '<optgroup label="Aset ' . htmlspecialchars($currentDivisi) . '">';
                                                            }
                                                        ?>
                                                            <?php if ($asset['is_service']): ?>
                                                                <option value="<?= htmlspecialchars($asset['kode_aset']) ?>" disabled class="asset-service-option"
                                                                    data-nama="<?= htmlspecialchars($asset['nama_aset']) ?>"
                                                                    data-merk="<?= htmlspecialchars($asset['merk']) ?>"
                                                                    data-spesifikasi="<?= htmlspecialchars($asset['spesifikasi']) ?>"
                                                                    data-kategori="<?= htmlspecialchars($asset['kategori_aset']) ?>"
                                                                    data-lokasi="<?= htmlspecialchars($asset['lokasi']) ?>"
                                                                    data-lokasi_detail="<?= htmlspecialchars($asset['lokasi_detail']) ?>"
                                                                    data-kondisi="<?= htmlspecialchars($asset['kondisi']) ?>"
                                                                    data-user="<?= htmlspecialchars($asset['user']) ?>"
                                                                    data-service="true">
                                                                    <?= htmlspecialchars($asset['kode_aset']) ?> - <?= htmlspecialchars($asset['nama_aset']) ?> - <?= htmlspecialchars($asset['merk']) ?> - <?= htmlspecialchars($asset['lokasi_detail']) ?> - <?= htmlspecialchars($asset['spesifikasi']) ?> - SEDANG SERVICE
                                                                </option>
                                                            <?php else: ?>
                                                                <option value="<?= htmlspecialchars($asset['kode_aset']) ?>"
                                                                    data-nama="<?= htmlspecialchars($asset['nama_aset']) ?>"
                                                                    data-merk="<?= htmlspecialchars($asset['merk']) ?>"
                                                                    data-spesifikasi="<?= htmlspecialchars($asset['spesifikasi']) ?>"
                                                                    data-kategori="<?= htmlspecialchars($asset['kategori_aset']) ?>"
                                                                    data-lokasi="<?= htmlspecialchars($asset['lokasi']) ?>"
                                                                    data-lokasi_detail="<?= htmlspecialchars($asset['lokasi_detail']) ?>"
                                                                    data-kondisi="<?= htmlspecialchars($asset['kondisi']) ?>"
                                                                    data-user="<?= htmlspecialchars($asset['user']) ?>"
                                                                    data-service="false">
                                                                    <?= htmlspecialchars($asset['kode_aset']) ?> - <?= htmlspecialchars($asset['nama_aset']) ?> - <?= htmlspecialchars($asset['merk']) ?> - <?= htmlspecialchars($asset['lokasi_detail']) ?> - <?= htmlspecialchars($asset['spesifikasi']) ?>
                                                                </option>
                                                            <?php endif; ?>
                                                        <?php endwhile; ?>
                                                        <?php if ($currentDivisi !== '') echo '</optgroup>'; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-3 mb-3">
                                                    <label class="font-weight-bold small">Nama Asset</label>
                                                    <input type="text" class="form-control form-control-sm readonly-field asset-nama" readonly>
                                                </div>
                                                <div class="col-12 col-md-3 mb-3">
                                                    <label class="font-weight-bold small">Kategori</label>
                                                    <input type="text" class="form-control form-control-sm readonly-field asset-kategori" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tombol Tambah Asset -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-success btn-sm" id="btnTambahAsset">
                                                <i class="fas fa-plus"></i> Tambah Asset
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row">

                                        <!-- PERBAIKAN: Responsive form grid -->
                                        <!-- Nomor Service (Auto Generate) -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="no_service" class="font-weight-bold">Nomor Service</label>
                                                <input type="text" name="no_service" id="no_service" class="form-control"
                                                    value="SVC-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>"
                                                    readonly>
                                                <small class="text-muted">Nomor service otomatis dibuat</small>
                                            </div>
                                        </div>

                                        <!-- Tanggal Service -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="tanggal_service" class="font-weight-bold">Tanggal Service <span class="text-danger">*</span></label>
                                                <input type="date" name="tanggal_service" id="tanggal_service" class="form-control"
                                                    value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>

                                        <!-- Jenis Service -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="jenis_service" class="font-weight-bold">Jenis Service <span class="text-danger">*</span></label>
                                                <select name="jenis_service" id="jenis_service" class="form-control" required>
                                                    <option value="">-- Pilih Jenis Service --</option>
                                                    <option value="Maintenance Rutin">Maintenance Rutin</option>
                                                    <option value="Perbaikan">Perbaikan</option>
                                                    <option value="Kalibrasi">Kalibrasi</option>
                                                    <option value="Upgrade">Upgrade</option>
                                                    <option value="Pembersihan">Pembersihan</option>
                                                    <option value="Penggantian Part">Penggantian Part</option>
                                                    <option value="Emergency">Emergency</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Penanggung Jawab/Pengguna (readonly) -->
                                        <div class="col-12 col-md-6 mb-3">
                                            <div class="form-group">
                                                <label for="penanggung_jawab" class="font-weight-bold">
                                                    <span class="d-none d-sm-inline">Penanggung Jawab/Pengguna</span>
                                                    <span class="d-inline d-sm-none">PJ/Pengguna</span>
                                                </label>
                                                <input type="text" name="penanggung_jawab" id="penanggung_jawab" class="form-control readonly-field"
                                                    placeholder="Akan terisi otomatis berdasarkan asset yang dipilih"
                                                    readonly>
                                                <small class="form-text text-muted">
                                                    <i class="fas fa-info-circle"></i>
                                                    <span class="d-none d-sm-inline">Field ini terisi otomatis berdasarkan pengguna/PJ dari asset yang dipilih</span>
                                                    <span class="d-inline d-sm-none">Otomatis terisi dari asset</span>
                                                </small>
                                            </div>
                                        </div>

                                        <!-- Keluhan/Masalah -->
                                        <div class="col-12 mb-3">
                                            <div class="form-group">
                                                <label for="keluhan" class="font-weight-bold">Keluhan/Masalah <span class="text-danger">*</span></label>
                                                <textarea name="keluhan" id="keluhan" class="form-control" rows="3"
                                                    placeholder="Deskripsikan keluhan atau masalah yang dialami..." required></textarea>
                                            </div>
                                        </div>

                                        <!-- Tindakan Service -->
                                        <div class="col-12 mb-3">
                                            <div class="form-group">
                                                <label for="tindakan" class="font-weight-bold">Tindakan Service</label>
                                                <textarea name="tindakan" id="tindakan" class="form-control" rows="3"
                                                    placeholder="Deskripsikan tindakan yang akan/sudah dilakukan..."></textarea>
                                            </div>
                                        </div>

                                        <!-- Upload Dokumentasi -->
                                        <div class="col-12 mb-3">
                                            <div class="form-group">
                                                <label for="dokumentasi" class="font-weight-bold">Upload Dokumentasi Service</label>
                                                <input type="file" name="dokumentasi" id="dokumentasi" class="form-control"
                                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                                <small class="form-text text-muted">
                                                    Format: JPG, JPEG, PNG, GIF, WEBP. Maksimal 5MB. (Opsional)
                                                </small>
                                                <!-- Preview dokumentasi -->
                                                <div id="dokumentasiPreview" class="mt-2" style="display: none;">
                                                    <img id="preview" src="" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PERBAIKAN: Responsive submit buttons -->
                                        <div class="col-12">
                                            <div class="d-flex flex-column flex-sm-row justify-content-end">
                                                <a href="dashboard.php" class="btn btn-secondary mb-2 mb-sm-0 mr-sm-2">
                                                    <i class="fas fa-times d-sm-none"></i>
                                                    <span class="d-none d-sm-inline">Batal</span>
                                                    <span class="d-inline d-sm-none">Batal</span>
                                                </a>
                                                <button type="submit" name="submit" class="btn btn-primary" id="btnSubmit">
                                                    <i class="fas fa-save mr-1"></i> 
                                                    <span class="d-none d-sm-inline">Simpan Service</span>
                                                    <span class="d-inline d-sm-none">Simpan</span>
                                                </button>
                                            </div>
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

    <!-- Select2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <!-- JavaScript -->
    <script>
        var assetItemIndex = 1;
        var assetData = <?= json_encode($assetData) ?>;
        var assetOptionsHtml = '';
        var currentDivisiJs = '';
        
        // Generate options HTML dari data JSON
        assetData.forEach(function(asset) {
            if (asset.divisi !== currentDivisiJs) {
                if (currentDivisiJs !== '') assetOptionsHtml += '</optgroup>';
                currentDivisiJs = asset.divisi;
                assetOptionsHtml += '<optgroup label="Aset ' + currentDivisiJs + '">';
            }
            if (asset.is_service == 1) {
                assetOptionsHtml += '<option value="' + asset.kode_aset + '" disabled class="asset-service-option" ' +
                    'data-nama="' + asset.nama_aset + '" ' +
                    'data-merk="' + asset.merk + '" ' +
                    'data-spesifikasi="' + asset.spesifikasi + '" ' +
                    'data-kategori="' + asset.kategori_aset + '" ' +
                    'data-lokasi="' + asset.lokasi + '" ' +
                    'data-lokasi_detail="' + asset.lokasi_detail + '" ' +
                    'data-kondisi="' + asset.kondisi + '" ' +
                    'data-user="' + asset.user + '" ' +
                    'data-service="true">' +
                    asset.kode_aset + ' - ' + asset.nama_aset + ' - ' + asset.merk + ' - ' + asset.lokasi_detail + ' - ' + asset.spesifikasi + ' - SEDANG SERVICE</option>';
            } else {
                assetOptionsHtml += '<option value="' + asset.kode_aset + '" ' +
                    'data-nama="' + asset.nama_aset + '" ' +
                    'data-merk="' + asset.merk + '" ' +
                    'data-spesifikasi="' + asset.spesifikasi + '" ' +
                    'data-kategori="' + asset.kategori_aset + '" ' +
                    'data-lokasi="' + asset.lokasi + '" ' +
                    'data-lokasi_detail="' + asset.lokasi_detail + '" ' +
                    'data-kondisi="' + asset.kondisi + '" ' +
                    'data-user="' + asset.user + '" ' +
                    'data-service="false">' +
                    asset.kode_aset + ' - ' + asset.nama_aset + ' - ' + asset.merk + ' - ' + asset.lokasi_detail + ' - ' + asset.spesifikasi + '</option>';
            }
        });
        if (currentDivisiJs !== '') assetOptionsHtml += '</optgroup>';

        $(document).ready(function() {
            // Select2 initialization for asset-select
            initSelect2();

            // Initialize remove button visibility
            updateRemoveButtons();

            // Preview dokumentasi
            $('#dokumentasi').change(function(event) {
                const file = event.target.files[0];
                const preview = $('#preview');
                const previewDiv = $('#dokumentasiPreview');

                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 5MB.');
                        this.value = '';
                        previewDiv.hide();
                        return;
                    }

                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan: JPG, JPEG, PNG, GIF, atau WEBP');
                        this.value = '';
                        previewDiv.hide();
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.attr('src', e.target.result);
                        previewDiv.show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewDiv.hide();
                }
            });

            // Event untuk tombol tambah asset
            $(document).on('click', '#btnTambahAsset', function(e) {
                e.preventDefault();
                addAssetItem();
            });

            // Global click listener for remove buttons (catches clicks on icon/children)
            $(document).on('click', function(e) {
                var $btn = $(e.target).closest('.btn-remove-asset');
                if ($btn.length) {
                    e.preventDefault();
                    e.stopPropagation();
                    var index = parseInt($btn.attr('data-index'));
                    removeAssetItem(index);
                    return false;
                }
            });

            // Event untuk asset select change
            $(document).on('change', '.asset-select', function() {
                const index = parseInt($(this).attr('data-index'));
                loadAssetInfo(index);
            });

            // Validasi form submission
            $('#formService').on('submit', function(e) {
                const selectedAssets = [];
                let hasDuplicate = false;
                let hasServiceAsset = false;

                $('.asset-select').each(function() {
                    const val = $(this).val();
                    const selectedOption = $(this).find('option:selected');
                    
                    if (val) {
                        if (selectedAssets.includes(val)) {
                            hasDuplicate = true;
                            return false;
                        }
                        if (selectedOption.data('service') === 'true' || selectedOption.data('service') === true) {
                            hasServiceAsset = true;
                            return false;
                        }
                        selectedAssets.push(val);
                    }
                });

                if (hasDuplicate) {
                    e.preventDefault();
                    alert('Asset yang sama tidak boleh dipilih lebih dari sekali!');
                    return false;
                }

                if (hasServiceAsset) {
                    e.preventDefault();
                    alert('Asset yang sedang service tidak dapat dipilih!');
                    return false;
                }

                if (selectedAssets.length === 0) {
                    e.preventDefault();
                    alert('Pilih minimal 1 asset untuk service!');
                    return false;
                }
            });
        });

        function initSelect2() {
            $('.asset-select').select2({
                width: '100%',
                placeholder: "-- Cari & Pilih Asset --",
                allowClear: true
            });
        }

        function loadAssetInfo(index) {
            const selectElement = $(`select[name="assets[${index}][kode_aset]"]`);
            const selectedOption = selectElement.find('option:selected');
            const row = $(`.asset-item-row[data-index="${index}"]`);

            if (selectedOption.val()) {
                // Cek apakah asset sedang service
                if (selectedOption.data('service') === 'true' || selectedOption.data('service') === true) {
                    alert('Asset ini sedang dalam status service dan tidak dapat dipilih!');
                    selectElement.val('').trigger('change');
                    return;
                }

                // Cek duplikasi
                const selectedValue = selectedOption.val();
                let isDuplicate = false;

                // Gunakan .asset-select (native selects) untuk mengecek duplikasi pada semua baris
                $('.asset-select').each(function() {
                    if ($(this).data('index') !== index && $(this).val() === selectedValue) {
                        isDuplicate = true;
                        return false;
                    }
                });

                if (isDuplicate) {
                    alert('Asset ini sudah dipilih! Pilih asset yang berbeda.');
                    selectElement.val('').trigger('change');
                    return;
                }

                // Fill data
                row.find('.asset-nama').val(selectedOption.data('nama') || '-');
                row.find('.asset-kategori').val(selectedOption.data('kategori') || '-');
                // Update global penanggung jawab field based on all selected assets
                updatePenanggungJawab();
            } else {
                row.find('.asset-nama').val('');
                row.find('.asset-kategori').val('');
                updatePenanggungJawab();
            }
        }

        // Update the #penanggung_jawab input based on selected asset(s)
        function updatePenanggungJawab() {
            var users = [];
            $('.asset-select').each(function() {
                var val = $(this).val();
                if (val) {
                    var user = $(this).find('option:selected').data('user');
                    if (user && String(user).trim() !== '') {
                        users.push(String(user).trim());
                    }
                }
            });
            // unique users
            users = users.filter(function(item, pos) {
                return users.indexOf(item) === pos;
            });
            var out = users.length ? users.join(', ') : '';
            $('#penanggung_jawab').val(out);
        }

        function addAssetItem() {
            var container = $('#assetItemsContainer');
            var newIndex = assetItemIndex;

            var itemHtml = '<div class="asset-item-row border rounded p-3 mb-3 position-relative" data-index="' + newIndex + '">' +
                '<button type="button" class="btn btn-sm btn-danger btn-remove-asset" data-index="' + newIndex + '" style="position: absolute; top: 10px; right: 10px; z-index: 100;" title="Hapus asset">' +
                '<i class="fas fa-times"></i>' +
                '</button>' +
                '<div class="row">' +
                '<div class="col-12 col-md-6 mb-3">' +
                '<label class="font-weight-bold small">Kode Asset <span class="text-danger">*</span></label>' +
                '<select name="assets[' + newIndex + '][kode_aset]" class="form-control form-control-sm asset-select" required data-index="' + newIndex + '">' +
                '<option value="">-- Pilih Asset --</option>' +
                assetOptionsHtml +
                '</select>' +
                '</div>' +
                '<div class="col-12 col-md-3 mb-3">' +
                '<label class="font-weight-bold small">Nama Asset</label>' +
                '<input type="text" class="form-control form-control-sm readonly-field asset-nama" readonly>' +
                '</div>' +
                '<div class="col-12 col-md-3 mb-3">' +
                '<label class="font-weight-bold small">Kategori</label>' +
                '<input type="text" class="form-control form-control-sm readonly-field asset-kategori" readonly>' +
                '</div>' +
                '</div>';

            container.append(itemHtml);

            // Menginisialisasi Select2 pada element yang baru saja ditambah
            $('#assetItemsContainer select[name="assets[' + newIndex + '][kode_aset]"]').select2({
                width: '100%',
                placeholder: "-- Cari & Pilih Asset --",
                allowClear: true
            });

            assetItemIndex++;
            updateRemoveButtons();
            // Ensure penanggung_jawab is up-to-date (new row may be empty)
            updatePenanggungJawab();
        }

        function removeAssetItem(index) {
            const item = $(`.asset-item-row[data-index="${index}"]`);
            if ($('.asset-item-row').length <= 1) {
                alert('Minimal harus ada 1 asset!');
                return;
            }
            
            if (item.length > 0) {
                // remove item (no Select2 destroy needed for native selects)
                item.remove();
                updateRemoveButtons();
                // Update penanggung_jawab after removal
                updatePenanggungJawab();
            }
        }

        function updateRemoveButtons() {
            const items = $('.asset-item-row');
            const buttons = $('.btn-remove-asset');
            if (items.length > 1) {
                buttons.show();
            } else {
                buttons.hide();
            }
        }
    </script>
</body>
</html>