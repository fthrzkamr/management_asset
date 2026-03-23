<?php
/**
 * CENTRAL PERMISSION MODULES CONFIG
 * File ini adalah SATU-SATUNYA tempat mendefinisikan modul dan aksi yang dikelola.
 * Perubahan di sini akan otomatis berlaku di seluruh fitur Administrator.
 */

$PERMISSION_MODULES = [
    // === PENGAJUAN ===
    'pengajuan_asset' => [
        'label' => 'Pengajuan Aset',
        'icon'  => 'fas fa-clipboard-list',
        'group' => 'Pengajuan',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'pengajuan_renovasi' => [
        'label' => 'Pengajuan Renovasi',
        'icon'  => 'fa-solid fa-house-circle-exclamation',
        'group' => 'Pengajuan',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'pengajuan_service' => [
        'label' => 'Service Aset',
        'icon'  => 'fa-solid fa-screwdriver-wrench',
        'group' => 'Pengajuan',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    // === MANAJEMEN ASET ===
    'management_asset' => [
        'label' => 'Manajemen Aset (IT)',
        'icon'  => 'fas fa-desktop',
        'group' => 'Manajemen Aset',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'management_asset_ga' => [
        'label' => 'Manajemen Aset (GA)',
        'icon'  => 'fas fa-boxes',
        'group' => 'Manajemen Aset',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    // === PPX ===
    'ppx' => [
        'label' => 'Pengajuan PPX',
        'icon'  => 'fa-solid fa-money-bill-1-wave',
        'group' => 'PPX',
        'actions' => ['create', 'read', 'update', 'delete', 'approve', 'process'],
    ],
    'report_ppx' => [
        'label' => 'Laporan Anggaran Bulanan',
        'icon'  => 'fa-solid fa-file-export',
        'group' => 'Laporan',
        'actions' => ['read'],
    ],
    'report_ppx_all' => [
        'label' => 'Laporan PPX (Regular & Monthly)',
        'icon'  => 'fa-solid fa-file-invoice-dollar',
        'group' => 'Laporan',
        'actions' => ['read', 'export'],
    ],
    'approval_ppx_leader' => [
        'label' => 'Approval PPX (Leader)',
        'icon'  => 'fas fa-check-circle',
        'group' => 'PPX Approval',
        'actions' => ['approve'],
    ],
    'approval_ppx_kacab' => [
        'label' => 'Approval PPX (Kepala Cabang)',
        'icon'  => 'fas fa-user-tie',
        'group' => 'PPX Approval',
        'actions' => ['approve'],
    ],
    'approval_ppx_manager' => [
        'label' => 'Approval PPX (Manager Finance)',
        'icon'  => 'fas fa-user-cog',
        'group' => 'PPX Approval',
        'actions' => ['approve'],
    ],
    'approval_ppx_hr' => [
        'label' => 'Approval PPX (HR)',
        'icon'  => 'fas fa-user-check',
        'group' => 'PPX Approval',
        'actions' => ['approve'],
    ],
    'approval_ppx_ga' => [
        'label' => 'Approval PPX (GA)',
        'icon'  => 'fas fa-warehouse',
        'group' => 'PPX Approval',
        'actions' => ['approve', 'process'],
    ],
    'approval_ppx_finance' => [
        'label' => 'Approval PPX (Finance AP)',
        'icon'  => 'fas fa-money-check-alt',
        'group' => 'PPX Approval',
        'actions' => ['approve', 'process'],
    ],
    'approval_ppx_operasional' => [
        'label' => 'Approval PPX (Operasional)',
        'icon'  => 'fas fa-tasks',
        'group' => 'PPX Approval',
        'actions' => ['approve'],
    ],
    'approval_ppx_direktur' => [
        'label' => 'Approval PPX (Direktur)',
        'icon'  => 'fas fa-signature',
        'group' => 'PPX Approval',
        'actions' => ['approve'],
    ],
    'approval_ppx_asmen' => [
        'label'   => 'Approval PPX (Asmen Finance)',
        'icon'    => 'fas fa-user-tie',
        'group'   => 'PPX Approval',
        'actions' => ['approve'],
    ],
    // === ANGGARAN ===
    'ppx_budget' => [
        'label' => 'Anggaran Bulanan PPX',
        'icon'  => 'fas fa-wallet',
        'group' => 'Anggaran',
        'actions' => ['create', 'read', 'update', 'delete', 'approve', 'manage_category'],
    ],
    'budget_besar' => [
        'label' => 'Anggaran Bulanan Besar',
        'icon'  => 'fas fa-money-bill-wave',
        'group' => 'Anggaran',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_budget' => [
        'label' => 'Master Anggaran',
        'icon'  => 'fas fa-coins',
        'group' => 'Anggaran',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'approval_budget_kacab' => [
        'label' => 'Approval Anggaran (Kacab)',
        'icon'  => 'fas fa-user-tie',
        'group' => 'Anggaran Approval',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_asmen' => [
        'label' => 'Approval Anggaran (Asmen Finance)',
        'icon'  => 'fas fa-user-check',
        'group' => 'Anggaran Approval',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_manager' => [
        'label' => 'Approval Anggaran (Manager Finance)',
        'icon'  => 'fas fa-user-cog',
        'group' => 'Anggaran Approval',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_manajer_ops' => [
        'label' => 'Approval Anggaran (Manajer Ops)',
        'icon'  => 'fas fa-user-shield',
        'group' => 'Anggaran Approval',
        'actions' => ['read', 'approve'],
    ],
    'approval_budget_finance' => [
        'label' => 'Approval Anggaran (Finance)',
        'icon'  => 'fas fa-coins',
        'group' => 'Anggaran Approval',
        'actions' => ['read', 'approve', 'process'],
    ],
    'budget_routing' => [
        'label' => 'Routing Approval Anggaran',
        'icon'  => 'fas fa-route',
        'group' => 'Anggaran',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'ppx_routing' => [
        'label' => 'Routing Approval PPX',
        'icon'  => 'fas fa-route',
        'group' => 'PPX',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    // === LAINNYA ===
    // === INVENTORY ATK ===
    'inventory_atk_master' => [
        'label' => 'Master Inventory (Barang)',
        'icon'  => 'fas fa-boxes',
        'group' => 'Inventory ATK',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'inventory_atk_stok' => [
        'label' => 'Stok Gudang ATK',
        'icon'  => 'fas fa-warehouse',
        'group' => 'Inventory ATK',
        'actions' => ['read', 'update'],
    ],
    'inventory_atk_po' => [
        'label' => 'Pengajuan PO ATK',
        'icon'  => 'fas fa-shopping-cart',
        'group' => 'Inventory ATK',
        'actions' => ['create', 'read', 'update', 'delete', 'approve'],
    ],
    'inventory_atk_penerimaan' => [
        'label' => 'Penerimaan Barang ATK',
        'icon'  => 'fas fa-download',
        'group' => 'Inventory ATK',
        'actions' => ['create', 'read', 'process'],
    ],
    'inventory_atk_pengambilan' => [
        'label' => 'Pengambilan ATK (User)',
        'icon'  => 'fas fa-hand-holding',
        'group' => 'Inventory ATK',
        'actions' => ['create', 'read', 'approve', 'reject', 'process'],
    ],
    // === PT BST ===
    'pt_bst_inventory' => [
        'label' => 'Inventory PT BST',
        'icon'  => 'fas fa-boxes',
        'group' => 'Modul PT BST',
        'actions' => ['read', 'update'],
    ],
    'pt_bst_po_masuk' => [
        'label' => 'PO Masuk (BST)',
        'icon'  => 'fas fa-file-import',
        'group' => 'Modul PT BST',
        'actions' => ['read', 'process'],
    ],
    'pt_bst_po_pembelian' => [
        'label' => 'PO Pembelian (BST)',
        'icon'  => 'fas fa-shopping-bag',
        'group' => 'Modul PT BST',
        'actions' => ['create', 'read', 'update', 'delete', 'process'],
    ],
    'analytics' => [
        'label' => 'Dashboard Analitik',
        'icon'  => 'fas fa-chart-pie',
        'group' => 'Lainnya',
        'actions' => ['read'],
    ],
    'master_data' => [
        'label' => 'Dashboard Data Master',
        'icon'  => 'fas fa-database',
        'group' => 'Data Master',
        'actions' => ['read'],
    ],
    'master_akun' => [
        'label' => 'Master Akun (COA)',
        'icon'  => 'fas fa-file-invoice-dollar',
        'group' => 'Data Master',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_cabang' => [
        'label' => 'Master Cabang',
        'icon'  => 'fas fa-map-marker-alt',
        'group' => 'Data Master',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_divisi' => [
        'label' => 'Master Divisi',
        'icon'  => 'fas fa-building',
        'group' => 'Data Master',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_departemen' => [
        'label' => 'Master Departemen',
        'icon'  => 'fas fa-briefcase',
        'group' => 'Data Master',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_kategori_asset' => [
        'label' => 'Master Kategori Asset',
        'icon'  => 'fas fa-tags',
        'group' => 'Data Master',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'master_lokasi_asset' => [
        'label' => 'Master Lokasi Asset',
        'icon'  => 'fas fa-map-signs',
        'group' => 'Data Master',
        'actions' => ['create', 'read', 'update', 'delete'],
    ],
    'user_management' => [
        'label' => 'Manajemen Pengguna',
        'icon'  => 'fas fa-users',
        'group' => 'Lainnya',
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
    'approve'         => 'Setujui',
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
