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
        return $this->hasPermission('pengajuan_asset', 'read');
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
        // Super Admin, HR, dan GA dapat approve semua kategori
        if ($this->isSuperAdmin() || $this->isHr() || $this->isGa()) {
            return true;
        }
        
        // IT hanya dapat approve kategori IT
        if ($this->isIT()) {
            // Jika kategori tidak dispecify, izinkan akses menu
            if ($kategori_asset === null) {
                return true;
            }
            
            // Get kategori name from ID if numeric
            if (is_numeric($kategori_asset)) {
                $query = "SELECT kategori_asset FROM kategori_asset WHERE id_kategori = ?";
                $stmt = mysqli_prepare($this->conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $kategori_asset);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($data = mysqli_fetch_assoc($result)) {
                    $kategori_asset = strtolower($data['kategori_asset']);
                }
                
                mysqli_stmt_close($stmt);
            } else {
                // Convert to lowercase for case-insensitive comparison
                $kategori_asset = strtolower($kategori_asset);
            }
            
            // Check if kategori is IT related
            $it_categories = ['it', 'komputer', 'laptop', 'printer', 'elektronik', 'hardware', 'software'];
            return in_array($kategori_asset, $it_categories);
        }
        
        return false;
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
        // HR, GA, and Super Admin have explicit access
        if ($this->isHr() || $this->isGA() || $this->isSuperAdmin()) {
            return true;
        }
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
        return $this->canApproveAsset() || $this->canApproveRenovasi() || $this->canApprovePPX() || $this->canApproveService();
    }

    public function canViewFinancialData()
    {
        return $this->isFinanceTeam() || $this->isDirektur() || $this->isSuperAdmin();
    }

    public function checkRenovasiAccess($action)
    {
        return $this->checkAccess('pengajuan_renovasi', $action);
    }

    public function checkPPXAccess($action)
    {
        return $this->checkAccess('ppx', $action);
    }

    // ============== ROLE GROUPS ==============
    public function isManagement()
    {
        return in_array($this->getUserRole(), ['direktur', 'manag_finance', 'manag_operasional', 'hr', 'kacab']) || $this->isSuperAdmin();
    }

    public function isFinanceTeam()
    {
        return in_array($this->getUserRole(), ['finance', 'manag_finance']) || $this->isSuperAdmin();
    }

    public function isApprover()
    {
        return $this->canApproveAsset() || $this->canApproveRenovasi() || $this->canApprovePPX() || $this->canApproveService();
    }

    // Redirect jika tidak ada permission
    public function requirePermission($module, $action, $redirect = '../index.php')
    {
        if (!$this->checkAccess($module, $action)) {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
            header("Location: $redirect");
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

        // Analytics menu for roles that have analysis access
        if ($this->isHr() || $this->isGA() || $this->isKacab() || $this->isManagerFinance() || $this->isOperationalManager()  || $this->isDirektur() || $this->isSuperAdmin()) {
            $menus[] = [
                'title' => 'Analisis Management',
                'url' => $base_path . 'z_analisis/index.php',
                'icon' => 'fas fa-chart-bar'
            ];
        }

        return $menus;
    }

    // Get default landing page berdasarkan permissions
    public function getDefaultLandingPage()
    {
        $role = $this->getUserRole();

        // Landing page berdasarkan role sesuai requirement terbaru
        switch ($role) {
            case 'super_admin':
                return 'z_pengajuan_asset/dashboard.php';

            case 'hr':
                // HR bisa asset, management, service, ppx
                return 'z_pengajuan_asset/dashboard.php';

            case 'GA':
                // GA bisa asset, management, service, ppx
                return 'z_pengajuan_asset/dashboard.php';

            case 'koordinator':
                // Koordinator hanya pengajuan (asset, ppx, renovasi)
                return 'z_pengajuan_asset/dashboard.php';

            case 'kacab':
                // Kacab bisa asset, service, ppx (tidak ada management)
                return 'z_pengajuan_asset/dashboard.php';

            case 'direktur':
                // Direktur hanya PPX
                return 'z_pengajuan_ppx/dashboard.php';

            case 'finance':
                // Finance bisa asset, renovasi, ppx
                return 'z_pengajuan_asset/dashboard.php';

            case 'manag_finance':
                // Manager Finance bisa asset, renovasi, ppx
                return 'z_pengajuan_asset/dashboard.php';

            case 'manag_operasional':
                // Manager Operasional bisa asset, renovasi, ppx
                return 'z_pengajuan_asset/dashboard.php';

            default:
                return 'index.php';
        }
    }

    // ============== INVENTORY ATK PERMISSIONS ==============
    public function canManageInventoryATK()
    {
        // GA dan Super Admin bisa manage inventory ATK
        return $this->isGA() || $this->isSuperAdmin();
    }

    public function canApproveATK()
    {
        // GA dan Super Admin bisa approve pengambilan ATK
        return $this->isGA() || $this->isSuperAdmin();
    }

    public function canViewInventoryATK()
    {
        // GA dan Super Admin bisa view inventory ATK
        return $this->isGA() || $this->isSuperAdmin();
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
        // Super Admin, HR, IT role, GA role can always access
        if ($this->isSuperAdmin() || $this->isHr() || $this->isIT() || $this->isGA()) {
            return true;
        }

        // Koordinator with GA division can access GA management
        if ($this->isKoordinator() && $this->isGADivision()) {
            return true;
        }

        return false;
    }

    /**
     * Get the appropriate Management Asset dashboard URL based on user's division/role
     * @return string|null Dashboard URL or null if no access
     */
    public function getManagementAssetDashboard()
    {
        if (!$this->canAccessManagementAsset()) {
            return null;
        }

        // IT role or IT division -> IT asset dashboard
        if ($this->isIT() || $this->isITDivision()) {
            return 'z_tambah_asset/dashboard.php';
        }

        // GA role or GA division -> GA asset dashboard
        if ($this->isGA() || $this->isGADivision()) {
            return 'z_management_asset_ga/dashboard.php';
        }

        // Super Admin & HR can choose, default to IT
        if ($this->isSuperAdmin() || $this->isHr()) {
            return null; // Will show dropdown menu
        }

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
        return $this->hasPermission('inventory_atk', $action);
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
        return $this->hasPermission('approval_budget', $action);
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
