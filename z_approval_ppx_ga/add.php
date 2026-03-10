<?php
// filepath: c:\xampp\htdocs\asset_dfm\pengajuan_asset\add.php
include('../dist/config/config.php');

// Cek session login

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit();
}

// // Get data cabang untuk dropdown
// $cabang_query = "SELECT DISTINCT cabang FROM user_dfm WHERE cabang IS NOT NULL ORDER BY cabang";
// $cabang_result = mysqli_query($conn, $cabang_query);
// ?>

<!DOCTYPE html>
<html lang="en">
<link rel="icon" href="../assets/img/dua.png" type="image/png">

<head>
    <style>
        .required {
            color: #dc3545;
        }

        .form-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .priority-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .priority-rendah {
            background: #d4edda;
            color: #155724;
        }

        .priority-sedang {
            background: #fff3cd;
            color: #856404;
        }

        .priority-tinggi {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-urgent {
            background: #721c24;
            color: white;
        }

        .detail-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .btn-remove-item {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <?php include '../layouts/navbar.php'; ?>

        <div class="page-wrapper">
            <div class="content container-fluid px-2 px-md-4">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="page-title h4 h3-md">Tambah Pengajuan PPX</h3>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard.php">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="dashboard.php">Pengajuan PPX</a>
                                </li>
                                <li class="breadcrumb-item active">Tambah Pengajuan</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body px-3 px-md-4">
                                <form action="add_insert.php" method="POST" enctype="multipart/form-data" id="formPengajuan">

                                    <!-- SECTION 1: Data Pengaju -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-user"></i> Data Pengaju
                                        </h5>
                                        <div class="row">
                                            <!-- Nama PIC -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nama PIC <span class="required">*</span></label>
                                                    <input type="text" name="nama_pic" class="form-control"
                                                        value="<?= htmlspecialchars($_SESSION['nama_user']) ?>"
                                                        placeholder="Nama Person In Charge" required>
                                                </div>
                                            </div>

                                            <!-- Jabatan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Jabatan <span class="required">*</span></label>
                                                    <select name="jabatan" class="form-control" required>
                                                        <option value="">-- Pilih Jabatan --</option>
                                                        <option value="Direktur">Direktur</option>
                                                        <option value="Manager">Manager</option>
                                                        <option value="Assistant Manager">Assistant Manager</option>
                                                        <option value="Supervisor">Supervisor</option>
                                                        <option value="Team Leader">Team Leader</option>
                                                        <option value="Senior Staff">Senior Staff</option>
                                                        <option value="Staff">Staff</option>
                                                        <option value="Junior Staff">Junior Staff</option>
                                                        <option value="Admin">Admin</option>
                                                        <option value="Secretary">Secretary</option>
                                                        <option value="Operator">Operator</option>
                                                        <option value="Teknisi">Teknisi</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Divisi -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Divisi <span class="required">*</span></label>
                                                    <select name="divisi" class="form-control" required>
                                                        <option value="">-- Pilih Divisi --</option>
                                                        <option value="IT">IT</option>
                                                        <option value="Finance & Accounting">Finance & Accounting</option>
                                                        <option value="Human Resources">Human Resources</option>
                                                        <option value="Marketing">Marketing</option>
                                                        <option value="Sales">Sales</option>
                                                        <option value="Operations">Operations</option>
                                                        <option value="Production">Production</option>
                                                        <option value="Quality Control">Quality Control</option>
                                                        <option value="Purchasing">Purchasing</option>
                                                        <option value="Warehouse">Warehouse</option>
                                                        <option value="R&D">Research & Development</option>
                                                        <option value="Customer Service">Customer Service</option>
                                                        <option value="Legal">Legal</option>
                                                        <option value="Administration">Administration</option>
                                                        <option value="Security">Security</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Cabang -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Cabang <span class="required">*</span></label>
                                                    <select name="cabang" class="form-control" required>
                                                        <option value="">-- Pilih Cabang --</option>
                                                        <option value="Jakarta Pusat">Jakarta Pusat</option>
                                                        <option value="Jakarta Utara">Jakarta Utara</option>
                                                        <option value="Jakarta Selatan">Jakarta Selatan</option>
                                                        <option value="Jakarta Timur">Jakarta Timur</option>
                                                        <option value="Jakarta Barat">Jakarta Barat</option>
                                                        <option value="Bogor">Bogor</option>
                                                        <option value="Depok">Depok</option>
                                                        <option value="Tangerang">Tangerang</option>
                                                        <option value="Bekasi">Bekasi</option>
                                                        <option value="Bandung">Bandung</option>
                                                        <option value="Surabaya">Surabaya</option>
                                                        <option value="Medan">Medan</option>
                                                        <option value="Semarang">Semarang</option>
                                                        <option value="Yogyakarta">Yogyakarta</option>
                                                        <option value="Malang">Malang</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Nomor PPX (Auto) -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Nomor PPX</label>
                                                    <input type="text" name="no_ppx" class="form-control"
                                                        value="PPX-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>"
                                                        readonly>
                                                    <small class="text-muted">Otomatis dibuat sistem</small>
                                                </div>
                                            </div>

                                            <!-- Tanggal Pengajuan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tanggal Pengajuan</label>
                                                    <input type="date" name="tanggal_pengajuan" class="form-control"
                                                        value="<?= date('Y-m-d') ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 2: Detail Item PPX -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-list"></i> Detail Item PPX
                                        </h5>
                                        
                                        <div id="detailContainer">
                                            <!-- Item pertama -->
                                            <div class="detail-item position-relative" data-index="0">
                                                <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeDetailItem(0)" style="display: none;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                
                                                <div class="row">
                                                    <!-- No Akun -->
                                                    <div class="col-12 col-md-4 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">No. Akun <span class="required">*</span></label>
                                                            <input type="text" name="detail[0][no_akun]" class="form-control"
                                                                placeholder="Contoh: 5101001" required>
                                                        </div>
                                                    </div>

                                                    <!-- Rincian -->
                                                    <div class="col-12 col-md-8 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Rincian Item <span class="required">*</span></label>
                                                            <input type="text" name="detail[0][rincian]" class="form-control"
                                                                placeholder="Nama/deskripsi item yang dibutuhkan" required>
                                                        </div>
                                                    </div>

                                                    <!-- Jumlah -->
                                                    <div class="col-12 col-md-3 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Jumlah <span class="required">*</span></label>
                                                            <input type="number" name="detail[0][jumlah]" class="form-control"
                                                                min="1" placeholder="1" required onchange="calculateTotal(0)">
                                                        </div>
                                                    </div>

                                                    <!-- Satuan -->
                                                    <div class="col-12 col-md-3 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Satuan <span class="required">*</span></label>
                                                            <select name="detail[0][satuan]" class="form-control" required>
                                                                <option value="">-- Pilih Satuan --</option>
                                                                <option value="Pcs">Pcs</option>
                                                                <option value="Unit">Unit</option>
                                                                <option value="Set">Set</option>
                                                                <option value="Buah">Buah</option>
                                                                <option value="Paket">Paket</option>
                                                                <option value="Kg">Kg</option>
                                                                <option value="Meter">Meter</option>
                                                                <option value="Liter">Liter</option>
                                                                <option value="Box">Box</option>
                                                                <option value="Roll">Roll</option>
                                                                <option value="Lembar">Lembar</option>
                                                                <option value="Dus">Dus</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Harga Satuan -->
                                                    <div class="col-12 col-md-3 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Harga Satuan <span class="required">*</span></label>
                                                            <input type="text" name="detail[0][harga_satuan]" class="form-control harga-input"
                                                                placeholder="Rp 0" required onkeyup="formatRupiah(this); calculateTotal(0)">
                                                        </div>
                                                    </div>

                                                    <!-- Total Harga -->
                                                    <div class="col-12 col-md-3 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Total Harga</label>
                                                            <input type="text" name="detail[0][total_harga]" class="form-control total-harga"
                                                                placeholder="Rp 0" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Deskripsi -->
                                                    <div class="col-12 mb-3">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold">Deskripsi Detail</label>
                                                            <textarea name="detail[0][deskripsi]" class="form-control" rows="2"
                                                                placeholder="Deskripsi tambahan untuk item ini..."></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tombol Tambah Item (pindah di atas Total Nominal PPX) -->
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-start">
                                                    <button type="button" class="btn btn-sm btn-success" onclick="addDetailItem()">
                                                        <i class="fas fa-plus"></i> Tambah Item
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Total Keseluruhan -->
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Total Nominal PPX:</strong>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h5 id="grandTotal" class="mb-0">Rp 0</h5>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 3: Justifikasi -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-clipboard-list"></i> Justifikasi & Kepentingan
                                        </h5>
                                        <div class="row">
                                            <!-- Alasan Pengajuan -->
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Alasan Pengajuan <span class="required">*</span></label>
                                                    <textarea name="alasan_pengajuan" class="form-control" rows="4"
                                                        placeholder="Jelaskan alasan mengapa PPX ini dibutuhkan..." required></textarea>
                                                </div>
                                            </div>

                                            <!-- Tingkat Kepentingan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tingkat Kepentingan <span class="required">*</span></label>
                                                    <select name="tingkat_kepentingan" class="form-control" required onchange="updatePriorityBadge()">
                                                        <option value="">-- Pilih Tingkat Kepentingan --</option>
                                                        <option value="Rendah">Rendah - Bisa ditunda</option>
                                                        <option value="Sedang">Sedang - Diperlukan dalam 1-3 bulan</option>
                                                        <option value="Tinggi">Tinggi - Diperlukan segera</option>
                                                        <option value="Urgent">Urgent - Sangat mendesak</option>
                                                    </select>
                                                    <div id="priorityBadge" class="mt-2"></div>
                                                </div>
                                            </div>

                                            <!-- Tanggal Dibutuhkan -->
                                            <div class="col-12 col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Tanggal Dibutuhkan <span class="required">*</span></label>
                                                    <input type="date" name="tanggal_dibutuhkan" class="form-control"
                                                        min="<?= date('Y-m-d') ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECTION 4: File Upload -->
                                    <div class="form-section">
                                        <h5 class="mb-3 h6 h5-md">
                                            <i class="fas fa-paperclip"></i> Lampiran
                                        </h5>
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">Upload File Pendukung</label>
                                                    <input type="file" name="upload_file" class="form-control"
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                                    <small class="text-muted">
                                                        Format: PDF, DOC, XLS, JPG, PNG. Maksimal 5MB.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden fields -->
                                    <input type="hidden" name="user_id" value="<?= $_SESSION['id_user'] ?>">
                                    <input type="hidden" name="status" value="Leader">

                                    <!-- Submit Buttons -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex flex-column flex-sm-row justify-content-end">
                                                <a href="dashboard.php" class="btn btn-secondary mb-2 mb-sm-0 mr-sm-2">
                                                    <i class="fas fa-times"></i> Batal
                                                </a>
                                                <button type="submit" name="submit_pengajuan" class="btn btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Submit Pengajuan
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

    <script>
        let detailIndex = 1;

        // Format Rupiah
        function formatRupiah(input) {
            let value = input.value.replace(/[^,\d]/g, "").toString();
            let split = value.split(",");
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? "." : "";
                rupiah += separator + ribuan.join(".");
            }

            rupiah = split[1] != undefined ? rupiah + "," + split[1] : rupiah;
            input.value = rupiah ? "Rp " + rupiah : "";
        }

        // Calculate total for each item
        function calculateTotal(index) {
            const jumlah = document.querySelector(`input[name="detail[${index}][jumlah]"]`).value || 0;
            const hargaInput = document.querySelector(`input[name="detail[${index}][harga_satuan]"]`);
            const totalInput = document.querySelector(`input[name="detail[${index}][total_harga]"]`);
            
            // Extract number from formatted price
            let harga = hargaInput.value.replace(/[^,\d]/g, "") || 0;
            let total = parseInt(jumlah) * parseInt(harga);
            
            // Format total as Rupiah
            let formattedTotal = total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            totalInput.value = total > 0 ? "Rp " + formattedTotal : "Rp 0";
            
            // Calculate grand total
            calculateGrandTotal();
        }

        // Calculate grand total
        function calculateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll('.total-harga').forEach(function(input) {
                let value = input.value.replace(/[^,\d]/g, "") || 0;
                grandTotal += parseInt(value);
            });
            
            let formattedGrandTotal = grandTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            document.getElementById('grandTotal').textContent = grandTotal > 0 ? "Rp " + formattedGrandTotal : "Rp 0";
        }

        // Add new detail item
        function addDetailItem() {
            const container = document.getElementById('detailContainer');
            const newItem = document.createElement('div');
            newItem.className = 'detail-item position-relative';
            newItem.setAttribute('data-index', detailIndex);
            
            newItem.innerHTML = `
                <button type="button" class="btn btn-sm btn-danger btn-remove-item" onclick="removeDetailItem(${detailIndex})">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="row">
                    <div class="col-12 col-md-4 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">No. Akun <span class="required">*</span></label>
                            <input type="text" name="detail[${detailIndex}][no_akun]" class="form-control"
                                placeholder="Contoh: 5101001" required>
                        </div>
                    </div>
                    <div class="col-12 col-md-8 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Rincian Item <span class="required">*</span></label>
                            <input type="text" name="detail[${detailIndex}][rincian]" class="form-control"
                                placeholder="Nama/deskripsi item yang dibutuhkan" required>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Jumlah <span class="required">*</span></label>
                            <input type="number" name="detail[${detailIndex}][jumlah]" class="form-control"
                                min="1" placeholder="1" required onchange="calculateTotal(${detailIndex})">
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Satuan <span class="required">*</span></label>
                            <select name="detail[${detailIndex}][satuan]" class="form-control" required>
                                <option value="">-- Pilih Satuan --</option>
                                <option value="Pcs">Pcs</option>
                                <option value="Unit">Unit</option>
                                <option value="Set">Set</option>
                                <option value="Buah">Buah</option>
                                <option value="Paket">Paket</option>
                                <option value="Kg">Kg</option>
                                <option value="Meter">Meter</option>
                                <option value="Liter">Liter</option>
                                <option value="Box">Box</option>
                                <option value="Roll">Roll</option>
                                <option value="Lembar">Lembar</option>
                                <option value="Dus">Dus</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Harga Satuan <span class="required">*</span></label>
                            <input type="text" name="detail[${detailIndex}][harga_satuan]" class="form-control harga-input"
                                placeholder="Rp 0" required onkeyup="formatRupiah(this); calculateTotal(${detailIndex})">
                        </div>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Total Harga</label>
                            <input type="text" name="detail[${detailIndex}][total_harga]" class="form-control total-harga"
                                placeholder="Rp 0" readonly>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Deskripsi Detail</label>
                            <textarea name="detail[${detailIndex}][deskripsi]" class="form-control" rows="2"
                                placeholder="Deskripsi tambahan untuk item ini..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(newItem);
            detailIndex++;
            
            // Show remove button for all items if more than 1
            updateRemoveButtons();
        }

        // Remove detail item
        function removeDetailItem(index) {
            const item = document.querySelector(`[data-index="${index}"]`);
            if (item) {
                item.remove();
                calculateGrandTotal();
                updateRemoveButtons();
            }
        }

        // Update remove buttons visibility
        function updateRemoveButtons() {
            const items = document.querySelectorAll('.detail-item');
            items.forEach(function(item, index) {
                const removeBtn = item.querySelector('.btn-remove-item');
                if (items.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }

        // --- ADDED: event delegation for remove (X) button ---
        document.getElementById('detailContainer').addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const item = btn.closest('.detail-item');
            if (item) {
                item.remove();
                calculateGrandTotal();
                updateRemoveButtons();
            }
        });

        // Update Priority Badge
        function updatePriorityBadge() {
            const select = document.querySelector('select[name="tingkat_kepentingan"]');
            const badge = document.getElementById('priorityBadge');
            const value = select.value;

            let badgeClass = '';
            let text = '';

            switch (value) {
                case 'Rendah':
                    badgeClass = 'priority-rendah';
                    text = '🟢 RENDAH - Bisa ditunda';
                    break;
                case 'Sedang':
                    badgeClass = 'priority-sedang';
                    text = '🟡 SEDANG - Diperlukan dalam 1-3 bulan';
                    break;
                case 'Tinggi':
                    badgeClass = 'priority-tinggi';
                    text = '🟠 TINGGI - Diperlukan segera';
                    break;
                case 'Urgent':
                    badgeClass = 'priority-urgent';
                    text = '🔴 URGENT - Sangat mendesak';
                    break;
                default:
                    badge.innerHTML = '';
                    return;
            }

            badge.innerHTML = `<span class="priority-badge ${badgeClass}">${text}</span>`;
        }

        // Form validation
        document.getElementById('formPengajuan').addEventListener('submit', function(e) {
            const tanggal = new Date(document.querySelector('input[name="tanggal_dibutuhkan"]').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            tanggal.setHours(0, 0, 0, 0);

            if (tanggal < today) {
                e.preventDefault();
                alert('Tanggal dibutuhkan tidak boleh kurang dari hari ini!');
                return false;
            }

            // Validate at least one detail item
            const detailItems = document.querySelectorAll('.detail-item');
            if (detailItems.length === 0) {
                e.preventDefault();
                alert('Minimal harus ada satu item detail!');
                return false;
            }
        });
    </script>
</body>

</html>