<?php
/**
 * CENTRAL PERMISSION MODULES CONFIG
 * File ini adalah SATU-SATUNYA tempat mendefinisikan modul dan aksi yang dikelola.
 * Perubahan di sini akan otomatis berlaku di seluruh fitur Administrator.
 */

$PERMISSION_MODULES = [
    // ═══════════════════════════════════════════════════════════
    // 1. ASET & GENERAL AFFAIRS (GA)
    // ═══════════════════════════════════════════════════════════
    'analytics' => [
        'label' => 'Dashboard Aset (Analytics)',
        'icon'  => 'fas fa-chart-pie',
        'group' => 'Aset & General Affairs',
        'actions' => ['read'],
    ],
    'pengajuan_asset' => [
        'label' => 'Pengajuan Aset (Pengaju)',
        'hint'  => 'Akses Pengaju/Staf (Buat, lihat, & edit pengajuan milik sendiri)',
        'icon'  => 'fas fa-clipboard-list',
        'group' => 'Aset & General Affairs',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'approval_asset_it' => [
        'label' => 'Approval Aset IT (Verifikator IT)',
        'hint'  => 'Akses Verifikator IT (Setujui & tolak pengajuan aset IT)',
        'icon'  => 'fas fa-laptop-code',
        'group' => 'Aset & General Affairs',
        'actions' => ['read', 'approve'],
    ],
    'approval_asset_ga' => [
        'label' => 'Approval Aset GA (Verifikator GA)',
        'hint'  => 'Akses Verifikator GA (Setujui & tolak pengajuan aset GA)',
        'icon'  => 'fas fa-building',
        'group' => 'Aset & General Affairs',
        'actions' => ['read', 'approve'],
    ],
    'pengajuan_renovasi' => [
        'label' => 'Pengajuan Renovasi',
        'icon'  => 'fa-solid fa-house-circle-exclamation',
        'group' => 'Aset & General Affairs',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'pengajuan_service' => [
        'label' => 'Service Aset',
        'icon'  => 'fa-solid fa-screwdriver-wrench',
        'group' => 'Aset & General Affairs',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'approval_peminjaman' => [
        'label' => 'Approval Peminjaman Aset',
        'icon'  => 'fas fa-hand-holding',
        'group' => 'Aset & General Affairs',
        'actions' => ['read', 'approve'],
    ],
    'management_asset' => [
        'label' => 'Manajemen Aset (IT)',
        'icon'  => 'fas fa-desktop',
        'group' => 'Aset & General Affairs',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'management_asset_ga' => [
        'label' => 'Manajemen Aset (GA)',
        'icon'  => 'fas fa-boxes',
        'group' => 'Aset & General Affairs',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'monitoring_ga' => [
        'label' => 'Monitoring Pekerjaan GA',
        'icon'  => 'fas fa-tasks',
        'group' => 'Aset & General Affairs',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 2. PPX & REALISASI KAS
    // ═══════════════════════════════════════════════════════════
    'ppx' => [
        'label' => 'Pengajuan PPX',
        'icon'  => 'fa-solid fa-money-bill-1-wave',
        'group' => 'PPX & Realisasi Kas',
        'actions' => ['create', 'read', 'update', 'delete', 'approve', 'process'],
    ],
    'realisasi_ppx' => [
        'label' => 'Realisasi Kas BKK/BKM (User)',
        'icon'  => 'fa-solid fa-check-double',
        'group' => 'PPX & Realisasi Kas',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'approval_realisasi_asmen' => [
        'label' => 'Approval Realisasi Kas (Asmen)',
        'icon'  => 'fa-solid fa-stamp',
        'group' => 'PPX & Realisasi Kas',
        'actions' => ['read', 'approve'],
    ],
    'ppx_routing' => [
        'label' => 'Routing Approval PPX',
        'icon'  => 'fas fa-route',
        'group' => 'PPX & Realisasi Kas',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'report_ppx' => [
        'label' => 'Laporan Anggaran Bulanan',
        'icon'  => 'fa-solid fa-file-export',
        'group' => 'PPX & Realisasi Kas',
        'actions' => ['read'],
    ],
    'report_ppx_all' => [
        'label' => 'Laporan PPX (Regular & Monthly)',
        'icon'  => 'fa-solid fa-file-invoice-dollar',
        'group' => 'PPX & Realisasi Kas',
        'actions' => ['read', 'export'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 3. APPROVAL PPX
    // ═══════════════════════════════════════════════════════════
    'approval_ppx_leader' => [
        'label' => 'Approval PPX (Leader)',
        'icon'  => 'fas fa-check-circle',
        'group' => 'Approval PPX',
        'actions' => ['approve'],
    ],
    'approval_ppx_kacab' => [
        'label' => 'Approval PPX (Kepala Cabang)',
        'icon'  => 'fas fa-user-tie',
        'group' => 'Approval PPX',
        'actions' => ['approve'],
    ],
    'approval_ppx_manager' => [
        'label' => 'Approval PPX (Manager Finance)',
        'icon'  => 'fas fa-user-cog',
        'group' => 'Approval PPX',
        'actions' => ['approve'],
    ],
    'approval_ppx_hr' => [
        'label' => 'Approval PPX (HR)',
        'icon'  => 'fas fa-user-check',
        'group' => 'Approval PPX',
        'actions' => ['approve'],
    ],
    'approval_ppx_ga' => [
        'label' => 'Approval PPX (GA)',
        'icon'  => 'fas fa-warehouse',
        'group' => 'Approval PPX',
        'actions' => ['approve', 'process'],
    ],
    'approval_ppx_finance' => [
        'label' => 'Approval PPX (Finance AP)',
        'icon'  => 'fas fa-money-check-alt',
        'group' => 'Approval PPX',
        'actions' => ['approve', 'process'],
    ],
    'approval_ppx_operasional' => [
        'label' => 'Approval PPX (Operasional)',
        'icon'  => 'fas fa-tasks',
        'group' => 'Approval PPX',
        'actions' => ['approve'],
    ],
    'approval_ppx_direktur' => [
        'label' => 'Approval PPX (Direktur)',
        'icon'  => 'fas fa-signature',
        'group' => 'Approval PPX',
        'actions' => ['approve'],
    ],
    'approval_ppx_asmen' => [
        'label'   => 'Approval PPX (Asmen Finance)',
        'icon'    => 'fas fa-user-tie',
        'group'   => 'Approval PPX',
        'actions' => ['approve'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 4. ANGGARAN & ROUTING
    // ═══════════════════════════════════════════════════════════
    'ppx_budget' => [
        'label' => 'Anggaran Bulanan (Monthly Budget)',
        'icon'  => 'fas fa-wallet',
        'group' => 'Anggaran & Routing',
        'actions' => ['create', 'read', 'update', 'delete', 'approve', 'manage_category'],
    ],
    'budget_besar' => [
        'label' => 'Anggaran Bulanan Besar (Non Budget)',
        'icon'  => 'fas fa-money-bill-wave',
        'group' => 'Anggaran & Routing',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_budget' => [
        'label' => 'Master Anggaran',
        'icon'  => 'fas fa-coins',
        'group' => 'Anggaran & Routing',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'budget_routing' => [
        'label' => 'Routing Approval Anggaran',
        'icon'  => 'fas fa-route',
        'group' => 'Anggaran & Routing',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 5. APPROVAL ANGGARAN
    // ═══════════════════════════════════════════════════════════
    'approval_budget_kacab' => [
        'label' => 'Approval Anggaran (Kacab)',
        'icon'  => 'fas fa-user-tie',
        'group' => 'Approval Anggaran',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_asmen' => [
        'label' => 'Approval Anggaran (Asmen Finance)',
        'icon'  => 'fas fa-user-check',
        'group' => 'Approval Anggaran',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_manager' => [
        'label' => 'Approval Anggaran (Manager Finance)',
        'icon'  => 'fas fa-user-cog',
        'group' => 'Approval Anggaran',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_manajer_ops' => [
        'label' => 'Approval Anggaran (Manajer Ops)',
        'icon'  => 'fas fa-user-shield',
        'group' => 'Approval Anggaran',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_finance' => [
        'label' => 'Approval Anggaran (Finance)',
        'icon'  => 'fas fa-coins',
        'group' => 'Approval Anggaran',
        'actions' => ['read', 'approve', 'process'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 6. AKUNTANSI & KEUANGAN
    // ═══════════════════════════════════════════════════════════
    'master_akun' => [
        'label' => 'Master Akun (COA)',
        'icon'  => 'fas fa-file-invoice-dollar',
        'group' => 'Akuntansi & Keuangan',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'acc_mutasi_bank' => [
        'label' => 'Mutasi Kas & Bank',
        'icon'  => 'fas fa-university',
        'group' => 'Akuntansi & Keuangan',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'acc_cash_opname' => [
        'label' => 'Cash Opname Mingguan',
        'icon'  => 'fas fa-coins',
        'group' => 'Akuntansi & Keuangan',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'acc_jurnal' => [
        'label' => 'Jurnal & Buku Besar',
        'icon'  => 'fas fa-book-open',
        'group' => 'Akuntansi & Keuangan',
        'actions' => ['create', 'read', 'delete'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 7. LOGISTIK & OPERASIONAL
    // ═══════════════════════════════════════════════════════════
    'inventory_atk_master' => [
        'label' => 'Master Inventory (Barang)',
        'icon'  => 'fas fa-boxes',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'inventory_atk_stok' => [
        'label' => 'Stok Gudang ATK',
        'icon'  => 'fas fa-warehouse',
        'group' => 'Logistik & Operasional',
        'actions' => ['read', 'update'],
    ],
    'inventory_atk_po' => [
        'label' => 'Pengajuan PO ATK',
        'icon'  => 'fas fa-shopping-cart',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'inventory_atk_penerimaan' => [
        'label' => 'Penerimaan Barang ATK',
        'icon'  => 'fas fa-download',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'process'],
    ],
    'inventory_atk_pengambilan' => [
        'label' => 'Pengambilan ATK (User)',
        'icon'  => 'fas fa-hand-holding',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'approve', 'reject', 'process'],
    ],
    'pt_bst_inventory' => [
        'label' => 'Inventory PT BST',
        'icon'  => 'fas fa-boxes',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'pt_bst_po_masuk' => [
        'label' => 'PO Masuk (BST)',
        'icon'  => 'fas fa-file-import',
        'group' => 'Logistik & Operasional',
        'actions' => ['read', 'process'],
    ],
    'pt_bst_po_pembelian' => [
        'label' => 'PO Pembelian (BST)',
        'icon'  => 'fas fa-shopping-bag',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'update', 'delete', 'process'],
    ],
    'pt_bst_asset' => [
        'label' => 'Manajemen Aset (BST)',
        'icon'  => 'fas fa-laptop-medical',
        'group' => 'Logistik & Operasional',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],

    // ═══════════════════════════════════════════════════════════
    // 8. PENGATURAN SISTEM & MASTER DATA
    // ═══════════════════════════════════════════════════════════
    'master_data' => [
        'label' => 'Dashboard Data Master',
        'icon'  => 'fas fa-database',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['read'],
    ],
    'master_vendor' => [
        'label' => 'Master Vendor',
        'icon'  => 'fas fa-handshake',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_cabang' => [
        'label' => 'Master Cabang',
        'icon'  => 'fas fa-map-marker-alt',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_divisi' => [
        'label' => 'Master Divisi',
        'icon'  => 'fas fa-building',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_departemen' => [
        'label' => 'Master Departemen',
        'icon'  => 'fas fa-briefcase',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_kategori_asset' => [
        'label' => 'Master Kategori Asset',
        'icon'  => 'fas fa-tags',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_lokasi_asset' => [
        'label' => 'Master Lokasi Asset',
        'icon'  => 'fas fa-map-signs',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'user_management' => [
        'label' => 'Manajemen Pengguna',
        'icon'  => 'fas fa-users',
        'group' => 'Pengaturan Sistem & Master Data',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
];

// Semua actions yang mungkin ada (untuk render kolom header)
$ALL_ACTIONS = ['create', 'read', 'update', 'delete', 'approve', 'process', 'manage_category', 'export'];

// Label tampilan untuk setiap action
$ACTION_LABELS = [
    'create'          => 'Tambah',
    'read'            => 'Lihat',
    'update'          => 'Edit',
    'delete'          => 'Hapus',
    'approve'         => 'Approve / Tolak',
    'process'         => 'Proses',
    'manage_category' => 'Kel. Kategori',
    'export'          => 'Export',
];

/**
 * Mapping: nama_role di DB (ppx_approval_routing.approver_role) → permission module key.
 * Digunakan oleh dashboard approval PPX untuk menentukan
 * apakah user berhak approve berdasarkan routing yang dikonfigurasi.
 */
$ROLE_TO_PERMISSION_MAP = [
    'koordinator'          => 'approval_ppx_leader',
    'kacab'                => 'approval_ppx_kacab',
    'manager_operasional'  => 'approval_ppx_operasional',
    'manag_operasional'    => 'approval_ppx_operasional',
    'hr'                   => 'approval_ppx_hr',
    'asmen_finance'        => 'approval_ppx_asmen',
    'manag_finance'        => 'approval_ppx_manager',
    'finance'              => 'approval_ppx_finance',
    'finance_ap'           => 'approval_ppx_finance',
    'GA'                   => 'approval_ppx_ga',
    'direktur'             => 'approval_ppx_direktur',
    'it'                   => 'approval_ppx_hr',
    'gudang'               => 'approval_ppx_operasional',
    'super_admin'          => null, // super_admin bypass semua
];

/**
 * Mapping: status PPX di DB → permission module key.
 * Fallback jika tidak ada entry di ppx_approval_routing.
 * Status "Leader" → cek approval_ppx_leader, dst.
 */
$STATUS_TO_PERMISSION_MAP = [
    'Leader'        => 'approval_ppx_leader',
    'Kacab'         => 'approval_ppx_kacab',
    'Manager'       => 'approval_ppx_manager',
    'HR'            => 'approval_ppx_hr',
    'Asmen Finance' => 'approval_ppx_asmen',
    'Operasional'   => 'approval_ppx_operasional',
    'Finance'       => 'approval_ppx_finance',
    'GA'            => 'approval_ppx_ga',
    'Direktur'      => 'approval_ppx_direktur',
];

/**
 * Standar Template Default Permission untuk setiap Role di tabel `roles`.
 * Semua kunci modul & aksi 100% selaras dengan $PERMISSION_MODULES di atas
 * dan mencerminkan workflow standar DF GROUP tanpa key usang.
 */
$DEFAULT_ROLE_PERMISSIONS = [
    'super_admin' => array_map(function ($m) {
        return $m['actions'];
    }, $PERMISSION_MODULES),

    'hr' => [
        'pengajuan_asset'     => ['create', 'read', 'update', 'delete', 'approve'],
        'pengajuan_renovasi'  => ['create', 'read', 'update', 'delete', 'approve'],
        'pengajuan_service'   => ['create', 'read', 'update', 'delete', 'approve'],
        'approval_peminjaman' => ['read', 'approve'],
        'ppx'                 => ['create', 'read', 'update'],
        'approval_ppx_hr'     => ['approve'],
        'ppx_budget'          => ['create', 'read', 'update', 'delete'],
        'analytics'           => ['read'],
    ],

    'koordinator' => [
        'pengajuan_asset'    => ['create', 'read', 'update'],
        'pengajuan_renovasi' => ['create', 'read', 'update'],
        'pengajuan_service'  => ['create', 'read', 'update'],
        'ppx'                => ['create', 'read', 'update', 'delete'],
    ],

    'GA' => [
        'pengajuan_asset'           => ['create', 'read', 'update'],
        'approval_asset_ga'         => ['read', 'approve'],
        'pengajuan_renovasi'        => ['create', 'read', 'update', 'approve'],
        'pengajuan_service'         => ['create', 'read', 'update', 'approve'],
        'management_asset_ga'       => ['create', 'read', 'update', 'delete'],
        'monitoring_ga'             => ['create', 'read', 'update', 'delete'],
        'ppx'                       => ['create', 'read', 'update'],
        'approval_ppx_ga'           => ['approve', 'process'],
        'inventory_atk_master'      => ['create', 'read', 'update', 'delete'],
        'inventory_atk_stok'        => ['read', 'update'],
        'inventory_atk_po'          => ['create', 'read', 'update', 'delete', 'approve'],
        'inventory_atk_penerimaan'  => ['create', 'read', 'process'],
        'inventory_atk_pengambilan' => ['create', 'read', 'approve', 'reject', 'process'],
        'master_vendor'             => ['create', 'read', 'update', 'delete'],
        'analytics'                 => ['read'],
    ],

    'it' => [
        'pengajuan_asset'   => ['create', 'read', 'update'],
        'approval_asset_it' => ['read', 'approve'],
        'pengajuan_service' => ['create', 'read', 'update', 'approve'],
        'management_asset'  => ['create', 'read', 'update', 'delete'],
        'ppx'               => ['create', 'read', 'update', 'delete'],
        'analytics'         => ['read'],
    ],

    'kacab' => [
        'pengajuan_asset'       => ['create', 'read', 'update'],
        'pengajuan_renovasi'    => ['create', 'read', 'update'],
        'pengajuan_service'     => ['create', 'read'],
        'ppx'                   => ['create', 'read', 'update'],
        'approval_ppx_kacab'    => ['approve'],
        'approval_budget_kacab' => ['read', 'approve'],
        'analytics'             => ['read'],
    ],

    'finance' => [
        'pengajuan_asset'         => ['create', 'read', 'update'],
        'ppx'                     => ['create', 'read', 'update', 'approve', 'process'],
        'approval_ppx_finance'    => ['approve', 'process'],
        'ppx_budget'              => ['create', 'read', 'update', 'delete'],
        'budget_besar'            => ['create', 'read', 'update', 'delete'],
        'master_budget'           => ['create', 'read', 'update', 'delete'],
        'approval_budget_finance' => ['read', 'approve', 'process'],
        'report_ppx'              => ['read'],
        'report_ppx_all'          => ['read', 'export'],
        'analytics'               => ['read'],
    ],

    'finance_ap' => [
        'pengajuan_asset'    => ['create', 'read', 'update'],
        'pengajuan_renovasi' => ['create', 'read', 'update'],
        'ppx'                => ['create', 'read', 'update', 'delete'],
        'ppx_budget'         => ['create', 'read', 'update', 'delete'],
        'realisasi_ppx'      => ['create', 'read', 'update', 'delete'],
    ],

    'asmen_finance' => [
        'ppx'                      => ['read', 'approve'],
        'approval_ppx_asmen'       => ['approve'],
        'approval_realisasi_asmen' => ['read', 'approve'],
        'ppx_budget'               => ['read', 'approve'],
        'approval_budget_asmen'    => ['read', 'approve'],
        'report_ppx'               => ['read'],
        'report_ppx_all'           => ['read', 'export'],
        'analytics'                => ['read'],
    ],

    'manag_finance' => [
        'ppx'                     => ['read', 'approve'],
        'approval_ppx_manager'    => ['approve'],
        'ppx_budget'              => ['create', 'read', 'update', 'delete', 'approve', 'manage_category'],
        'master_budget'           => ['create', 'read', 'update', 'delete'],
        'approval_budget_manager' => ['read', 'approve'],
        'report_ppx'              => ['read'],
        'report_ppx_all'          => ['read', 'export'],
        'analytics'               => ['read'],
    ],

    'manag_operasional' => [
        'pengajuan_asset'             => ['create', 'read', 'update'],
        'ppx'                         => ['read', 'approve'],
        'approval_ppx_operasional'    => ['approve'],
        'approval_budget_manajer_ops' => ['read', 'approve'],
        'analytics'                   => ['read'],
    ],

    'direktur' => [
        'ppx'                   => ['read', 'approve'],
        'approval_ppx_direktur' => ['approve'],
        'ppx_budget'            => ['read', 'approve'],
        'report_ppx'            => ['read'],
        'report_ppx_all'        => ['read', 'export'],
        'analytics'             => ['read'],
    ],

    'gudang' => [
        'pengajuan_asset' => ['create', 'read', 'update'],
        'ppx'             => ['create', 'read', 'update'],
        'ppx_budget'      => ['create', 'read', 'update'],
    ],
];
