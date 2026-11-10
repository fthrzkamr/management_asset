<?php
// filepath: c:\xampp\htdocs\asset_dfm\dist\config\auth_helper.php

class AuthHelper
{
    private $conn;
    private $user_permissions = null;
    private $user_role = null;
    private $user_has_custom = false; // apakah user punya custom_permissions

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    // Cek apakah user login
    public function isLoggedIn()
    {
        return isset($_SESSION['id_user']);
    }

    // Get user permissions
    // Priority: custom_permissions (per-user) > role permissions
    public function getUserPermissions()
    {
        if ($this->user_permissions === null) {
            $user_id = $_SESSION['id_user'] ?? 0;

            // Pastikan kolom custom_permissions ada (auto-create jika belum)
            $col_check = mysqli_query($this->conn, "SHOW COLUMNS FROM user_dfm LIKE 'custom_permissions'");
            if ($col_check && mysqli_num_rows($col_check) === 0) {
                mysqli_query($this->conn,
                    "ALTER TABLE user_dfm ADD COLUMN custom_permissions LONGTEXT DEFAULT NULL");
            }

            // Query dengan custom_permissions
            $query  = "SELECT u.custom_permissions, r.permissions AS role_permissions, r.nama_role
                       FROM user_dfm u
                       JOIN roles r ON u.id_role = r.id_role
                       WHERE u.id_user = $user_id";
            $result = mysqli_query($this->conn, $query);

            // Fallback: jika query gagal (misal kolom belum siap), gunakan query lama
            if ($result === false) {
                $query  = "SELECT r.permissions AS role_permissions, r.nama_role
                           FROM user_dfm u
                           JOIN roles r ON u.id_role = r.id_role
                           WHERE u.id_user = $user_id";
                $result = mysqli_query($this->conn, $query);
            }

            $data = $result ? mysqli_fetch_assoc($result) : null;

            if ($data) {
                $this->user_role = $data['nama_role'];

                // Cek apakah user punya custom_permissions yang valid
                $custom_raw = $data['custom_permissions'] ?? null;
                $has_custom = !empty($custom_raw) && $custom_raw !== 'null' && $custom_raw !== '{}';

                if ($has_custom) {
                    $decoded = json_decode($custom_raw, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $this->user_permissions = $decoded;
                        $this->user_has_custom  = true;
                    } else {
                        $this->user_permissions = json_decode($data['role_permissions'], true) ?? [];
                        $this->user_has_custom  = false;
                    }
                } else {
                    $this->user_permissions = json_decode($data['role_permissions'], true) ?? [];
                    $this->user_has_custom  = false;
                }
            } else {
                $this->user_permissions = [];
                $this->user_role        = 'guest';
                $this->user_has_custom  = false;
            }
        }

        return $this->user_permissions;
    }

    // Apakah user menggunakan custom permission (bukan dari role)?
    public function hasCustomPermissions()
    {
        $this->getUserPermissions(); // pastikan sudah di-load
        return $this->user_has_custom;
    }

    // ============== CORE PERMISSION METHOD ==============
    public function hasPermission($module, $action)
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->getUserPermissions();
        return isset($permissions[$module]) && in_array($action, $permissions[$module]);
    }

    // Get user role
    public function getUserRole()
    {
        if ($this->user_role === null) {
            $this->getUserPermissions(); // Load permissions first
        }
        return $this->user_role;
    }

    // ============== ROLE CHECK METHODS ==============
    public function isSuperAdmin()
    {
        return $this->getUserRole() === 'super_admin';
    }

    public function isHr()
    {
        return $this->getUserRole() === 'hr';
    }

    public function isKoordinator()
    {
        return $this->getUserRole() === 'koordinator';
    }

    public function isGA()
    {
        return $this->getUserRole() === 'GA';
    }

    public function isKacab()
    {
        return $this->getUserRole() === 'kacab';
    }

    public function isFinance()
    {
        return $this->getUserRole() === 'finance';
    }

    public function isFinanceAP()
    {
        return $this->getUserRole() === 'finance_ap';
    }

    public function isGudang()
    {
        return $this->getUserRole() === 'gudang';
    }

    public function isManagerFinance()
    {
        return $this->getUserRole() === 'manag_finance';
    }

    public function isDirektur()
    {
        return $this->getUserRole() === 'direktur';
    }

    public function isOperationalManager()
    {
        return $this->getUserRole() === 'manag_operasional';
    }

    /**
     * Check if user is IT role
     * @return bool
     */
    public function isIT() {
        return $this->getUserRole() === 'it'; // Pastikan huruf kecil 'it'
    }

    // ============== MODULE-SPECIFIC PERMISSION METHODS ==============

    // PENGAJUAN ASSET - SESUAI DATABASE PERMISSIONS
    public function canCreateAsset()
    {
        return $this->hasPermission('pengajuan_asset', 'create');
    }

    public function canReadAsset()
    {
        return $this->hasPermission('pengajuan_asset', 'read')
            || $this->hasPermission('pengajuan_asset', 'create')
            || $this->hasPermission('pengajuan_asset', 'update')
            || $this->hasPermission('approval_asset_it', 'read')
            || $this->hasPermission('approval_asset_ga', 'read')
            || $this->canApproveAsset();
    }

    public function canUpdateAsset()
    {
        return $this->hasPermission('pengajuan_asset', 'update');
    }

    public function canDeleteAsset()
    {
        return $this->hasPermission('pengajuan_asset', 'delete');
    }

    /**
     * Check if user can approve asset by category
     * IT users can only approve IT category assets
     * @param string $kategori_asset Optional - kategori asset yang akan diapprove
     * @return bool
     */
    public function canApproveAsset($kategori_asset = null) {
        // Berdasarkan PERMISSION (Kelola Role / Permission User), bukan nama role.
        $scope = $this->getAssetApprovalScope();
        if (!$scope['any']) {
            return false;
        }

        // Jika kategori tidak dispecify, izinkan akses menu
        if ($kategori_asset === null) {
            return true;
        }

        // Approver penuh (global / IT+GA) boleh semua kategori
        if ($scope['all']) {
            return true;
        }

        // Ambil sub_kategori (IT/GA) dari kategori yang diminta
        $sub = null;
        if (is_numeric($kategori_asset)) {
            $query = "SELECT sub_kategori FROM kategori_asset WHERE id_kategori = ?";
            $stmt = mysqli_prepare($this->conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $kategori_asset);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($data = mysqli_fetch_assoc($result)) {
                $sub = $data['sub_kategori'];
            }
            mysqli_stmt_close($stmt);
        } else {
            $sub = strtoupper(trim($kategori_asset));
        }

        return $this->canApproveAssetSubKategori($sub);
    }

    /**
     * Scope approval Pengajuan Aset berdasarkan PERMISSION (bukan role):
     *  - Super Admin                         => semua (IT + GA)
     *  - approval_asset_it / approval_asset_ga (spesifik) => hanya sub kategori yang dicentang
     *    (permission spesifik SELALU menang atas permission umum)
     *  - hanya pengajuan_asset:approve (umum) => semua sub kategori (approver global)
     *  - selain itu                          => tidak bisa approve
     *
     * @return array ['all' => bool, 'IT' => bool, 'GA' => bool, 'any' => bool]
     */
    public function getAssetApprovalScope()
    {
        if ($this->isSuperAdmin()) {
            return ['all' => true, 'IT' => true, 'GA' => true, 'any' => true];
        }

        $it      = $this->hasPermission('approval_asset_it', 'approve');
        $ga      = $this->hasPermission('approval_asset_ga', 'approve');
        $general = $this->hasPermission('pengajuan_asset', 'approve');

        if ($it || $ga) {
            return ['all' => ($it && $ga), 'IT' => $it, 'GA' => $ga, 'any' => true];
        }
        if ($general) {
            return ['all' => true, 'IT' => true, 'GA' => true, 'any' => true];
        }
        return ['all' => false, 'IT' => false, 'GA' => false, 'any' => false];
    }

    /**
     * Apakah user boleh approve / kelola distribusi pengajuan dengan sub kategori tertentu?
     * @param string|null $sub_kategori 'IT' atau 'GA'
     */
    public function canApproveAssetSubKategori($sub_kategori)
    {
        $scope = $this->getAssetApprovalScope();
        if (!$scope['any']) return false;
        if ($scope['all']) return true;
        $sub = strtoupper(trim((string)$sub_kategori));
        return isset($scope[$sub]) ? (bool)$scope[$sub] : false;
    }

    /**
     * Kondisi SQL (tanpa kata WHERE) untuk membatasi data asset_request sesuai permission.
     * Return '' jika boleh melihat semua. Memakai alias tabel asset_request ($ar) dan kategori_asset ($ka)
     * (query pemanggil wajib sudah JOIN kategori_asset).
     */
    public function getAssetRequestVisibilityCondition($ar = 'ar', $ka = 'ka')
    {
        $scope  = $this->getAssetApprovalScope();
        $userId = (int)($_SESSION['id_user'] ?? 0);
        $own    = "$ar.user_id = $userId";

        if ($scope['all']) return '';
        if (!$scope['any']) return $own;

        $filters = [];
        if ($scope['IT']) $filters[] = "$ka.sub_kategori = 'IT'";
        if ($scope['GA']) $filters[] = "$ka.sub_kategori = 'GA'";
        return '(' . implode(' OR ', $filters) . " OR $own)";
    }

    // PENGAJUAN RENOVASI - SESUAI DATABASE PERMISSIONS
    public function canCreateRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'create');
    }

    public function canReadRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'read');
    }

    public function canViewRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'read');
    }

    public function canUpdateRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'update');
    }

    public function canEditRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'update');
    }

    public function canDeleteRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'delete');
    }

    public function canApproveRenovasi()
    {
        return $this->hasPermission('pengajuan_renovasi', 'approve');
    }

    // PENGAJUAN SERVICE - SESUAI DATABASE PERMISSIONS
    public function canCreateService()
    {
        return $this->hasPermission('pengajuan_service', 'create');
    }

    public function canReadService()
    {
        return $this->hasPermission('pengajuan_service', 'read');
    }

    public function canUpdateService()
    {
        return $this->hasPermission('pengajuan_service', 'update');
    }

    public function canDeleteService()
    {
        return $this->hasPermission('pengajuan_service', 'delete');
    }

    public function canApproveService()
    {
        return $this->hasPermission('pengajuan_service', 'approve');
    }

    // MANAGEMENT ASSET - SESUAI DATABASE PERMISSIONS
    public function canCreateManagementAsset()
    {
        return $this->hasPermission('management_asset', 'create');
    }

    public function canReadManagementAsset()
    {
        // Berdasarkan permission (Super Admin sudah otomatis di hasPermission)
        return $this->hasPermission('management_asset', 'read');
    }

    public function canUpdateManagementAsset()
    {
        return $this->hasPermission('management_asset', 'update');
    }

    public function canDeleteManagementAsset()
    {
        return $this->hasPermission('management_asset', 'delete');
    }

    // PPX - SESUAI DATABASE PERMISSIONS
    public function canCreatePPX()
    {
        return $this->hasPermission('ppx', 'create');
    }

    public function canReadPPX()
    {
        return $this->hasPermission('ppx', 'read');
    }

    public function canUpdatePPX()
    {
        return $this->hasPermission('ppx', 'update');
    }

    public function canDeletePPX()
    {
        return $this->hasPermission('ppx', 'delete');
    }

    public function canApprovePPX()
    {
        return $this->hasPermission('ppx', 'approve');
    }

    public function canProcessPPX()
    {
        return $this->hasPermission('ppx', 'process');
    }

    // PPX BUDGET - PERMISSION METHODS
    public function canCreateBudget()
    {
        return $this->hasPermission('ppx_budget', 'create');
    }

    public function canReadBudget()
    {
        return $this->hasPermission('ppx_budget', 'read');
    }

    public function canUpdateBudget()
    {
        return $this->hasPermission('ppx_budget', 'update');
    }

    public function canDeleteBudget()
    {
        return $this->hasPermission('ppx_budget', 'delete');
    }

    public function canApproveBudget()
    {
        return $this->hasPermission('ppx_budget', 'approve');
    }

    public function canManageCategory()
    {
        return $this->hasPermission('ppx_budget', 'manage_category');
    }

    // ============== GENERIC ACCESS CHECKERS ==============
    public function checkAccess($module, $action)
    {
        switch ($module) {
            case 'pengajuan_asset':
                switch ($action) {
                    case 'create':
                        return $this->canCreateAsset();
                    case 'read':
                        return $this->canReadAsset();
                    case 'update':
                        return $this->canUpdateAsset();
                    case 'delete':
                        return $this->canDeleteAsset();
                    case 'approve':
                        return $this->canApproveAsset();
                    default:
                        return false;
                }

            case 'pengajuan_renovasi':
                switch ($action) {
                    case 'create':
                        return $this->canCreateRenovasi();
                    case 'read':
                        return $this->canReadRenovasi();
                    case 'update':
                        return $this->canUpdateRenovasi();
                    case 'delete':
                        return $this->canDeleteRenovasi();
                    case 'approve':
                        return $this->canApproveRenovasi();
                    default:
                        return false;
                }

            case 'ppx':
                switch ($action) {
                    case 'create':
                        return $this->canCreatePPX();
                    case 'read':
                        return $this->canReadPPX();
                    case 'update':
                        return $this->canUpdatePPX();
                    case 'delete':
                        return $this->canDeletePPX();
                    case 'approve':
                        return $this->canApprovePPX();
                    case 'process':
                        return $this->canProcessPPX();
                    default:
                        return false;
                }

            case 'pengajuan_service':
                switch ($action) {
                    case 'create':
                        return $this->canCreateService();
                    case 'read':
                        return $this->canReadService();
                    case 'update':
                        return $this->canUpdateService();
                    case 'delete':
                        return $this->canDeleteService();
                    case 'approve':
                        return $this->canApproveService();
                    default:
                        return false;
                }

            case 'management_asset':
                switch ($action) {
                    case 'create':
                        return $this->canCreateManagementAsset();
                    case 'read':
                        return $this->canReadManagementAsset();
                    case 'update':
                        return $this->canUpdateManagementAsset();
                    case 'delete':
                        return $this->canDeleteManagementAsset();
                    default:
                        return false;
                }

            default:
                return $this->hasPermission($module, $action);
        }
    }

    // COMPATIBILITY METHODS - UPDATED TO USE PERMISSIONS
    public function canApprove()
    {
        // Dipakai oleh seluruh modul approval PPX. Hanya dihitung dari permission PPX
        // (bukan approval aset/renovasi/service, supaya role lain tidak ikut lolos).
        if ($this->canApprovePPX()) {
            return true;
        }
        foreach (['approval_ppx_leader', 'approval_ppx_kacab', 'approval_ppx_hr', 'approval_ppx_asmen',
                  'approval_ppx_finance', 'approval_ppx_operasional', 'approval_ppx_direktur',
                  'approval_ppx_ga', 'approval_ppx_manager'] as $mod) {
            if ($this->hasPermission($mod, 'approve')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apakah user boleh membaca / mencetak / export data PPX (baik sebagai pengaju maupun approver/processor)?
     */
    public function canReadOrApprovePPX()
    {
        return $this->canReadPPX()
            || $this->canApprove()
            || $this->canProcessPPX()
            || $this->hasPermission('approval_ppx_ga', 'process')
            || $this->hasPermission('approval_ppx_finance', 'process');
    }

    /**
     * Apakah user memiliki visibilitas penuh ke seluruh Anggaran Bulanan lintas cabang/divisi?
     */
    public function hasFullMonthlyBudgetVisibility()
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('ppx_budget', 'approve')
            || $this->hasPermission('approval_budget_finance', 'approve')
            || $this->hasPermission('approval_budget_finance', 'read')
            || $this->hasPermission('approval_budget_manager', 'approve')
            || $this->hasPermission('approval_budget_asmen', 'approve')
            || $this->hasPermission('master_budget', 'read');
    }

    /**
     * Kondisi SQL (tanpa kata WHERE) untuk visibilitas ppx_monthly_budget sesuai permission.
     * Return '' jika user berhak melihat seluruh anggaran bulanan.
     */
    public function getMonthlyBudgetVisibilityCondition($mbAlias = 'mb', $userAlias = 'u', $divAlias = 'creator_div_name', $userCabangId = 0)
    {
        if ($this->hasFullMonthlyBudgetVisibility()) {
            return '';
        }

        $userId = (int)($_SESSION['id_user'] ?? 0);
        $ors    = ["$mbAlias.created_by = $userId"];

        if ($this->hasPermission('approval_budget_kacab', 'approve') && (int)$userCabangId > 0) {
            $ors[] = "$userAlias.id_cabang = " . (int)$userCabangId;
        }
        if ($this->hasPermission('approval_budget_manajer_ops', 'approve') || $this->hasPermission('approval_budget_manajer_ops', 'read')) {
            $ors[] = "$divAlias.id_divisi IN (2, 4, 17)";
        }
        if ($this->hasPermission('approval_ppx_hr', 'approve')) {
            $ors[] = "$divAlias.id_divisi IN (1, 6, 7)";
        }

        return count($ors) === 1 ? $ors[0] : '(' . implode(' OR ', $ors) . ')';
    }

    /**
     * Cek apakah user boleh mengakses satu record ppx_monthly_budget tertentu berdasarkan permission.
     */
    public function canAccessMonthlyBudgetRecord($creatorId, $creatorDivId = 0, $creatorCabangId = 0, $userCabangId = 0)
    {
        if ($this->hasFullMonthlyBudgetVisibility()) {
            return true;
        }

        $userId = (int)($_SESSION['id_user'] ?? 0);
        if ((int)$creatorId === $userId) {
            return true;
        }
        if ($this->hasPermission('approval_budget_kacab', 'approve')
            && (int)$userCabangId > 0
            && (int)$creatorCabangId === (int)$userCabangId) {
            return true;
        }
        if (($this->hasPermission('approval_budget_manajer_ops', 'approve') || $this->hasPermission('approval_budget_manajer_ops', 'read'))
            && in_array((int)$creatorDivId, [2, 4, 17], true)) {
            return true;
        }
        if ($this->hasPermission('approval_ppx_hr', 'approve')
            && in_array((int)$creatorDivId, [1, 6, 7], true)) {
            return true;
        }

        return false;
    }

    public function canViewFinancialData()
    {
        return $this->isFinanceTeam()
            || $this->hasPermission('approval_ppx_direktur', 'approve')
            || $this->hasPermission('analytics', 'read')
            || $this->isSuperAdmin();
    }

    public function checkRenovasiAccess($action)
    {
        return $this->checkAccess('pengajuan_renovasi', $action);
    }

    public function checkPPXAccess($action)
    {
        return $this->checkAccess('ppx', $action);
    }

    // ============== ROLE GROUPS (PERMISSION-AWARE) ==============
    public function isManagement()
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('analytics', 'read')
            || $this->hasPermission('approval_ppx_direktur', 'approve')
            || $this->hasPermission('approval_ppx_manager', 'approve')
            || $this->hasPermission('approval_ppx_operasional', 'approve')
            || $this->hasPermission('approval_ppx_hr', 'approve')
            || $this->hasPermission('approval_ppx_kacab', 'approve');
    }

    public function isFinanceTeam()
    {
        return $this->isSuperAdmin()
            || $this->hasPermission('approval_ppx_finance', 'approve')
            || $this->hasPermission('approval_ppx_manager', 'approve')
            || $this->hasPermission('approval_ppx_asmen', 'approve')
            || $this->hasPermission('approval_budget_finance', 'approve')
            || $this->hasPermission('approval_budget_manager', 'approve')
            || $this->hasPermission('master_budget', 'read');
    }

    public function isApprover()
    {
        return $this->canApproveAsset() || $this->canApproveRenovasi() || $this->canApprovePPX() || $this->canApproveService();
    }

    // Redirect jika tidak ada permission dengan pesan alert
    public function requirePermission($module, $action, $redirect = null)
    {
        if (!$this->checkAccess($module, $action)) {
            // Tentukan halaman kembali
            $back_url = $redirect ?: ($_SERVER['HTTP_REFERER'] ?? '../index.php');
            
            // Set pesan error di session
            $_SESSION['error_title'] = "Akses Ditolak!";
            $_SESSION['error'] = "Maaf, Anda tidak memiliki izin untuk mengakses fitur ini ($module:$action).";
            
            // Jika ini request AJAX, kirim JSON
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $_SESSION['error']]);
                exit;
            }

            // Redirect ke halaman sebelumnya atau index dengan membawa pesan
            header("Location: $back_url");
            exit();
        }
    }

    // Get menu berdasarkan PERMISSIONS - BUKAN HARDCODE ROLE
    public function getMenuItems($base_path = '../')
    {
        $menus = [];

        // Menu berdasarkan permissions dari database
        if ($this->canReadAsset()) {
            $menus[] = [
                'title' => 'Pengajuan Asset',
                'url' => $base_path . 'z_pengajuan_asset/dashboard.php',
                'icon' => 'fas fa-clipboard-list'
            ];
        }

        if ($this->canReadRenovasi()) {
            $menus[] = [
                'title' => 'Pengajuan Renovasi',
                'url' => $base_path . 'z_pengajuan_renovasi/dashboard.php',
                'icon' => 'fa-solid fa-house-circle-exclamation'
            ];
        }

        if ($this->canReadPPX()) {
            $menus[] = [
                'title' => 'Pengajuan PPX',
                'url' => $base_path . 'z_pengajuan_ppx/dashboard.php',
                'icon' => 'fa-solid fa-money-bill-1-wave'
            ];
        }

        if ($this->canReadService()) {
            $menus[] = [
                'title' => 'Service Asset',
                'url' => $base_path . 'z_service_asset/dashboard.php',
                'icon' => 'fas fa-wrench'
            ];
        }

        if ($this->canReadManagementAsset()) {
            $menus[] = [
                'title' => 'Management Asset',
                'url' => $base_path . 'z_tambah_asset/dashboard.php',
                'icon' => 'fas fa-boxes'
            ];
        }

        // Super Admin specific menus
        if ($this->isSuperAdmin()) {
            $menus[] = [
                'title' => 'User Management',
                'url' => $base_path . 'z_users/dashboard.php',
                'icon' => 'fas fa-users'
            ];
            $menus[] = [
                'title' => 'Master Data',
                'url' => $base_path . 'z_master_data/dashboard.php',
                'icon' => 'fas fa-database'
            ];
            $menus[] = [
                'title' => 'System Settings',
                'url' => $base_path . 'z_settings/dashboard.php',
                'icon' => 'fas fa-cogs'
            ];
        }

        // Analytics menu berdasarkan permission 'analytics'
        if ($this->hasPermission('analytics', 'read')) {
            $menus[] = [
                'title' => 'Analisis Management',
                'url' => $base_path . 'z_analisis/index.php',
                'icon' => 'fas fa-chart-bar'
            ];
        }

        return $menus;
    }

    // Get default landing page berdasarkan permissions (bukan hardcode nama role)
    public function getDefaultLandingPage()
    {
        if ($this->canReadAsset() || $this->canApproveAsset()) {
            return 'z_pengajuan_asset/dashboard.php';
        }
        if ($this->canReadPPX()) {
            return 'z_pengajuan_ppx/dashboard.php';
        }
        if ($this->canReadBudget()) {
            return 'z_ppx_budget/dashboard.php';
        }
        return 'index.php';
    }

    // ============== INVENTORY ATK PERMISSIONS ==============
    // Berdasarkan permission dari Kelola Role / Permission User (bukan nama role)
    public function canManageInventoryATK()
    {
        return $this->hasPermission('inventory_atk_master', 'create')
            || $this->hasPermission('inventory_atk_master', 'update')
            || $this->hasPermission('inventory_atk_stok', 'update');
    }

    public function canApproveATK()
    {
        return $this->hasPermission('inventory_atk_pengambilan', 'approve')
            || $this->hasPermission('inventory_atk_po', 'approve');
    }

    public function canViewInventoryATK()
    {
        return $this->hasPermission('inventory_atk_master', 'read')
            || $this->hasPermission('inventory_atk_stok', 'read')
            || $this->hasPermission('inventory_atk_po', 'read')
            || $this->hasPermission('inventory_atk_penerimaan', 'read')
            || $this->hasPermission('inventory_atk_pengambilan', 'read');
    }

    // ============== USER DIVISION & DEPARTMENT METHODS ==============
    /**
     * Get user's division ID
     * @return int|null Division ID or null if not found
     */
    public function getUserDivisi()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $user_id = $_SESSION['id_user'];
        $query = "SELECT divisi FROM user_dfm WHERE id_user = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($data = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return (int)$data['divisi'];
        }
        
        mysqli_stmt_close($stmt);
        return null;
    }

    /**
     * Get user's department ID
     * @return int|null Department ID or null if not found
     */
    public function getUserDepartemen()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $user_id = $_SESSION['id_user'];
        $query = "SELECT departemen FROM user_dfm WHERE id_user = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($data = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return (int)$data['departemen'];
        }
        
        mysqli_stmt_close($stmt);
        return null;
    }

    /**
     * Check if user belongs to GA division (divisi_id = 6)
     * @return bool
     */
    public function isGADivision()
    {
        return $this->getUserDivisi() === 6;
    }

    /**
     * Check if user belongs to IT division (divisi_id = 1)
     * @return bool
     */
    public function isITDivision()
    {
        return $this->getUserDivisi() === 1;
    }

    /**
     * Check if user can access Management Asset based on their division
     * IT division -> access z_tambah_asset (IT assets)
     * GA division -> access z_management_asset_ga (GA assets)
     * @return bool
     */
    public function canAccessManagementAsset()
    {
        // Berdasarkan permission: boleh jika punya akses baca Manajemen Aset IT atau GA
        return $this->hasPermission('management_asset', 'read')
            || $this->hasPermission('management_asset_ga', 'read');
    }

    /**
     * Get the appropriate Management Asset dashboard URL berdasarkan PERMISSION user
     * @return string|null Dashboard URL, atau null jika tidak ada akses / punya akses keduanya (tampilkan menu pilihan)
     */
    public function getManagementAssetDashboard()
    {
        $it = $this->hasPermission('management_asset', 'read');
        $ga = $this->hasPermission('management_asset_ga', 'read');

        if ($it && !$ga) {
            return 'z_tambah_asset/dashboard.php';
        }
        if ($ga && !$it) {
            return 'z_management_asset_ga/dashboard.php';
        }

        // Tidak punya akses, atau punya keduanya (menu akan menampilkan dua pilihan)
        return null;
    }

    // =====================================================================
    // DYNAMIC PERMISSION CHECKS - membaca dari JSON permissions di tabel roles
    // Semua modul dari permissions_config.php di-handle method generik ini
    // =====================================================================

    /**
     * Cek apakah user boleh melakukan $action pada $module.
     * Method ini sudah ada di atas dan tetap digunakan.
     */

    // --- Shortcut methods untuk modul-modul baru ---

    public function canAccessInventoryAtk($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        foreach (['inventory_atk_master', 'inventory_atk_stok', 'inventory_atk_po', 'inventory_atk_penerimaan', 'inventory_atk_pengambilan'] as $mod) {
            if ($this->hasPermission($mod, $action)) {
                return true;
            }
        }
        return false;
    }

    public function canAccessManagementAssetGA($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('management_asset_ga', $action);
    }

    public function canAccessMasterBudget($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('master_budget', $action);
    }

    public function canAccessApprovalBudget($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        foreach (['approval_budget_kacab', 'approval_budget_manajer_ops', 'approval_budget_asmen', 'approval_budget_finance', 'approval_budget_manager'] as $mod) {
            if ($this->hasPermission($mod, $action) || $this->hasPermission($mod, 'approve')) {
                return true;
            }
        }
        return false;
    }

    public function canAccessAnalytics($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('analytics', $action);
    }

    public function canAccessMasterData($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('master_data', $action);
    }

    public function canAccessUserManagement($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('user_management', $action);
    }

    // Approval PPX per level
    public function canApproveAsPPXLeader()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('approval_ppx_leader', 'approve');
    }

    public function canApproveAsPPXHR()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('approval_ppx_hr', 'approve');
    }

    public function canApproveAsPPXFinance()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('approval_ppx_finance', 'approve');
    }

    public function canApproveAsPPXOperasional()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('approval_ppx_operasional', 'approve');
    }

    public function canApproveAsPPXDirektur()
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('approval_ppx_direktur', 'approve');
    }

    public function canAccessManagementAssetIT($action = 'read')
    {
        if ($this->isSuperAdmin()) return true;
        return $this->hasPermission('management_asset', $action);
    }
}

// Helper functions
function getAuth($conn)
{
    return new AuthHelper($conn);
}

function initAuth($conn)
{
    return new AuthHelper($conn);
}
