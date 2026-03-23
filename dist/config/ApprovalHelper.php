<?php
/**
 * ApprovalHelper.php
 * Routing approval anggaran dengan dukungan per-cabang override dan per-divisi (DB-driven).
 *
 * Prioritas resolusi step:
 * 1. ppx_budget_routing_divisi dengan id_cabang spesifik (paling prioritas)
 * 2. ppx_budget_routing_divisi dengan id_cabang = NULL (global semua cabang)
 * 3. ppx_budget_routing_cabang — aturan per cabang (skip=1 / approver_id override)
 * 4. Global default — is_optional, ho_only, hasApproverInBranch
 */
class ApprovalHelper {
    private $conn;
    private $HO_NAME_KEYWORD = 'jakarta';

    // Cache aturan divisi agar tidak query berulang
    // Format: [id_divisi]['specific'][id_cabang][id_routing_step]
    //         [id_divisi]['global'][id_routing_step]
    private $divisiRulesCache = null;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Helper: cek keberadaan tabel / kolom
    // ────────────────────────────────────────────────────────────────────────────
    private function hasCabangTable() {
        $r = mysqli_query($this->conn, "SHOW TABLES LIKE 'ppx_budget_routing_cabang'");
        return $r && mysqli_num_rows($r) > 0;
    }

    private function hasDivisiTable() {
        $r = mysqli_query($this->conn, "SHOW TABLES LIKE 'ppx_budget_routing_divisi'");
        return $r && mysqli_num_rows($r) > 0;
    }

    private function columnExists($table, $column) {
        $r = mysqli_query($this->conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $r && mysqli_num_rows($r) > 0;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Load & cache aturan divisi dari DB
    // Supports kolom id_cabang (nullable):
    //   - NULL  → berlaku semua cabang (global)
    //   - value → berlaku hanya cabang itu (spesifik, prioritas lebih tinggi)
    // ────────────────────────────────────────────────────────────────────────────
    private function getDivisiRules() {
        if ($this->divisiRulesCache !== null) return $this->divisiRulesCache;
        $this->divisiRulesCache = [];
        if (!$this->hasDivisiTable()) return $this->divisiRulesCache;

        // Cek apakah kolom id_cabang sudah ada di tabel ini
        $has_cabang_col = $this->columnExists('ppx_budget_routing_divisi', 'id_cabang');

        if ($has_cabang_col) {
            $res = mysqli_query($this->conn,
                "SELECT id_divisi, id_cabang, id_routing_step, skip_step 
                 FROM ppx_budget_routing_divisi WHERE is_active = 1");
        } else {
            // Kolom id_cabang belum ada (backward-compat), semua dianggap global
            $res = mysqli_query($this->conn,
                "SELECT id_divisi, NULL AS id_cabang, id_routing_step, skip_step 
                 FROM ppx_budget_routing_divisi WHERE is_active = 1");
        }

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $div = $row['id_divisi'];
                $sid = $row['id_routing_step'];
                if ($row['id_cabang'] !== null) {
                    // Aturan spesifik per-cabang
                    $this->divisiRulesCache[$div]['specific'][$row['id_cabang']][$sid] = $row;
                } else {
                    // Aturan global (berlaku semua cabang)
                    $this->divisiRulesCache[$div]['global'][$sid] = $row;
                }
            }
        }
        return $this->divisiRulesCache;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Ambil semua steps routing beserta per-cabang override-nya
    // ────────────────────────────────────────────────────────────────────────────
    private function getStepsWithOverride($id_cabang) {
        $has_ho_col     = $this->columnExists('ppx_budget_routing', 'ho_only');
        $has_cabang_tbl = $this->hasCabangTable();
        $ho_select      = $has_ho_col ? 'r.ho_only,' : '0 AS ho_only,';

        if ($has_cabang_tbl) {
            $query = "SELECT r.id, r.step_name, r.step_order, r.approver_role,
                             r.approver_id AS default_approver_id,
                             r.is_optional, r.is_final, r.is_active, {$ho_select}
                             c.approver_id AS override_approver_id,
                             c.skip_step, c.is_active AS override_active
                      FROM ppx_budget_routing r
                      LEFT JOIN ppx_budget_routing_cabang c
                          ON c.id_routing_step = r.id AND c.id_cabang = ? AND c.is_active = 1
                      WHERE r.is_active = 1
                      ORDER BY r.step_order ASC";
            $stmt = mysqli_prepare($this->conn, $query);
            mysqli_stmt_bind_param($stmt, 's', $id_cabang);
            mysqli_stmt_execute($stmt);
            return mysqli_stmt_get_result($stmt);
        } else {
            $query = "SELECT r.id, r.step_name, r.step_order, r.approver_role,
                             r.approver_id AS default_approver_id,
                             r.is_optional, r.is_final, r.is_active, {$ho_select}
                             NULL AS override_approver_id, 0 AS skip_step, NULL AS override_active
                      FROM ppx_budget_routing r
                      WHERE r.is_active = 1
                      ORDER BY r.step_order ASC";
            return mysqli_query($this->conn, $query);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Tentukan apakah step harus di-skip
    // Prioritas baru:
    //   1. Aturan divisi + cabang spesifik (id_cabang match)
    //   2. Aturan divisi global (id_cabang = NULL)
    //   3. Aturan per-cabang (ppx_budget_routing_cabang)
    //   4. Global default (is_optional, ho_only, hasApproverInBranch)
    // ────────────────────────────────────────────────────────────────────────────
    private function shouldSkipStep($step, $id_cabang, $id_divisi = null) {
        $sid = (int)$step['id'];

        // ── Prioritas 1 & 2: Aturan divisi dari database ─────────────────────
        if ($id_divisi !== null) {
            $rules = $this->getDivisiRules();

            // Prioritas 1: aturan spesifik untuk divisi + cabang ini
            if (isset($rules[$id_divisi]['specific'][$id_cabang][$sid])) {
                return (bool)$rules[$id_divisi]['specific'][$id_cabang][$sid]['skip_step'];
            }

            // Prioritas 2: aturan global untuk divisi ini (id_cabang = NULL)
            if (isset($rules[$id_divisi]['global'][$sid])) {
                return (bool)$rules[$id_divisi]['global'][$sid]['skip_step'];
            }

            // Jika divisi punya aturan terdaftar tapi step ini tidak ada aturannya
            // → jangan fallthrough ke aturan per-cabang jika divisi punya entry apapun
            // Lanjutkan ke per-cabang (fallthrough ke prioritas 3)
        }

        // ── Prioritas 3: Override per-cabang ─────────────────────────────────
        if (!empty($step['skip_step'])) return true;
        if (!empty($step['override_approver_id'])) return false;

        // ── Prioritas 4: Global default ──────────────────────────────────────
        if (!$step['is_optional']) return false;

        if (!empty($step['ho_only'])) {
            return !$this->isHOBranch($id_cabang);
        }

        if ($this->isHOBranch($id_cabang)) {
            if ($step['step_name'] == 'Kacab') return true;
        }

        return !$this->hasApproverInBranch($step['approver_role'], $id_cabang, $step['default_approver_id']);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Cek apakah cabang adalah HO (Jakarta)
    // ────────────────────────────────────────────────────────────────────────────
    private function isHOBranch($id_cabang) {
        if ($id_cabang === 'CAB001') return true;
        $stmt = mysqli_prepare($this->conn, "SELECT nama_cabang FROM master_cabang WHERE id_cabang = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $id_cabang);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            return stripos($row['nama_cabang'], $this->HO_NAME_KEYWORD) !== false;
        }
        return false;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Cek apakah ada user dengan role tertentu di cabang
    // ────────────────────────────────────────────────────────────────────────────
    private function hasApproverInBranch($role_name, $id_cabang, $approver_id = null) {
        if (!empty($approver_id)) {
            $stmt = mysqli_prepare($this->conn, "SELECT id_user FROM user_dfm WHERE id_user = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $approver_id);
            mysqli_stmt_execute($stmt);
            return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
        }
        $stmt = mysqli_prepare($this->conn,
            "SELECT u.id_user FROM user_dfm u
             JOIN roles r ON u.id_role = r.id_role
             WHERE r.nama_role = ? AND u.id_cabang = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $role_name, $id_cabang);
        mysqli_stmt_execute($stmt);
        return mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Status AWAL saat budget baru diajukan
    // ────────────────────────────────────────────────────────────────────────────
    public function getInitialStatus($id_cabang, $id_divisi = null) {
        $res = $this->getStepsWithOverride($id_cabang);
        if (!$res) return 'Kacab';

        while ($step = mysqli_fetch_assoc($res)) {
            if ($this->shouldSkipStep($step, $id_cabang, $id_divisi)) continue;
            return $step['step_name'];
        }
        return 'Kacab';
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Status BERIKUTNYA setelah step saat ini disetujui
    // ────────────────────────────────────────────────────────────────────────────
    public function getNextStatusFrom($current_status, $id_cabang, $id_divisi = null) {
        // Ambil step_order saat ini
        $stmt = mysqli_prepare($this->conn,
            "SELECT step_order FROM ppx_budget_routing WHERE step_name = ? AND is_active = 1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $current_status);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $current_order = 0;
        if ($row = mysqli_fetch_assoc($res)) $current_order = $row['step_order'];

        $has_ho_col     = $this->columnExists('ppx_budget_routing', 'ho_only');
        $has_cabang_tbl = $this->hasCabangTable();
        $ho_select      = $has_ho_col ? 'r.ho_only,' : '0 AS ho_only,';

        if ($has_cabang_tbl) {
            $query = "SELECT r.id, r.step_name, r.step_order, r.approver_role,
                             r.approver_id AS default_approver_id,
                             r.is_optional, r.is_final, r.is_active, {$ho_select}
                             c.approver_id AS override_approver_id, c.skip_step, c.is_active AS override_active
                       FROM ppx_budget_routing r
                       LEFT JOIN ppx_budget_routing_cabang c
                           ON c.id_routing_step = r.id AND c.id_cabang = ? AND c.is_active = 1
                       WHERE r.is_active = 1 AND r.step_order > ?
                       ORDER BY r.step_order ASC";
            $stmt = mysqli_prepare($this->conn, $query);
            mysqli_stmt_bind_param($stmt, 'si', $id_cabang, $current_order);
        } else {
            $query = "SELECT r.id, r.step_name, r.step_order, r.approver_role,
                             r.approver_id AS default_approver_id,
                             r.is_optional, r.is_final, r.is_active, {$ho_select}
                             NULL AS override_approver_id, 0 AS skip_step, NULL AS override_active
                       FROM ppx_budget_routing r
                       WHERE r.is_active = 1 AND r.step_order > ?
                       ORDER BY r.step_order ASC";
            $stmt = mysqli_prepare($this->conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $current_order);
        }

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        while ($step = mysqli_fetch_assoc($res)) {
            if ($this->shouldSkipStep($step, $id_cabang, $id_divisi)) continue;
            return $step['step_name'];
        }

        return 'Active';
    }
}
?>
