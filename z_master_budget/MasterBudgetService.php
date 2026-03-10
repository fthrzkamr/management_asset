<?php
/**
 * Master Budget Service
 * Handle semua operasi terkait master budget dengan transaction safety
 * 
 * @package Asset DFM
 * @subpackage Master Budget
 * @author System
 * @date 2025-12-05
 */

class MasterBudgetService {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Generate Master ID otomatis
     * Format: MASTER-YYYY-XXX
     * 
     * @return string Generated master_id
     */
    public function generateMasterId() {
        $year = date('Y');
        $prefix = "MASTER-{$year}-";
        
        // Ambil ID terakhir untuk tahun ini
        $query = "SELECT master_id FROM master_budget 
                  WHERE master_id LIKE ? 
                  ORDER BY master_id DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $search = $prefix . '%';
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Extract nomor urut
            $lastId = $row['master_id'];
            $parts = explode('-', $lastId);
            $lastNumber = intval($parts[2]);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        $stmt->close();
        
        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Top-up Master Budget (menambah dana)
     * 
     * @param string $master_id ID master budget (kosong untuk auto-generate)
     * @param float $amount Jumlah top-up
     * @param string $description Deskripsi
     * @param string $created_by User yang melakukan top-up
     * @param string|null $reference_id Reference ID untuk log
     * @param string|null $tanggal Tanggal master budget (Y-m-d format, default NOW)
     * @return array ['success' => bool, 'message' => string, 'master_id' => string]
     */
    public function topupMasterBudget($master_id, $amount, $description, $created_by, $reference_id = null, $tanggal = null) {
        // Validasi input
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Error: Amount harus lebih besar dari 0',
                'master_id' => null
            ];
        }
        
        // Auto-generate ID jika kosong
        $is_new = false;
        if (empty($master_id)) {
            $master_id = $this->generateMasterId();
            $is_new = true;
        }
        
        // Default tanggal ke NOW jika tidak diisi
        if (empty($tanggal)) {
            $tanggal = date('Y-m-d H:i:s');
        } else {
            // Convert Y-m-d to Y-m-d H:i:s if needed
            if (strlen($tanggal) == 10) {
                $tanggal = $tanggal . ' ' . date('H:i:s');
            }
        }
        
        try {
            // Start transaction
            $this->conn->begin_transaction();
            
            if ($is_new) {
                // Insert master budget baru dengan tanggal custom
                $query = "INSERT INTO master_budget 
                          (master_id, tanggal, amount_in, amount_out, balance, description, created_by) 
                          VALUES (?, ?, ?, 0.00, ?, ?, ?)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ssddss", $master_id, $tanggal, $amount, $amount, $description, $created_by);
                $stmt->execute();
                $stmt->close();
                
                $balance_before = 0.00;
                $balance_after = $amount;
                
            } else {
                // Update existing master budget dengan row lock
                $query = "SELECT amount_in, balance FROM master_budget 
                          WHERE master_id = ? FOR UPDATE";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("s", $master_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $balance_before = $row['balance'];
                    $new_amount_in = $row['amount_in'] + $amount;
                    $new_balance = $balance_before + $amount;
                    $stmt->close();
                    
                    // Update master budget
                    $update_query = "UPDATE master_budget 
                                     SET amount_in = ?, 
                                         balance = ?,
                                         updated_at = NOW()
                                     WHERE master_id = ?";
                    
                    $update_stmt = $this->conn->prepare($update_query);
                    $update_stmt->bind_param("dds", $new_amount_in, $new_balance, $master_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    $balance_after = $new_balance;
                    
                } else {
                    $stmt->close();
                    throw new Exception("Master ID tidak ditemukan: {$master_id}");
                }
            }
            
            // Insert log
            $log_ref_id = $reference_id ?: 'TOPUP-' . date('YmdHis');
            $note = "Top-up: {$description}";
            
            $log_query = "INSERT INTO master_budget_log 
                          (master_id, reference_type, reference_id, amount, type, 
                           balance_before, balance_after, note, created_by) 
                          VALUES (?, 'TOPUP', ?, ?, 'IN', ?, ?, ?, ?)";
            
            $log_stmt = $this->conn->prepare($log_query);
            $log_stmt->bind_param("ssdddss", 
                $master_id, $log_ref_id, $amount, 
                $balance_before, $balance_after, $note, $created_by
            );
            $log_stmt->execute();
            $log_stmt->close();
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Berhasil top-up Rp " . number_format($amount, 0, ',', '.') . " ke {$master_id}",
                'master_id' => $master_id,
                'balance' => $balance_after
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'master_id' => null
            ];
        }
    }
    
    /**
     * Deduct (kurangi) dari Master Budget secara realtime dengan row locking
     * 
     * @param string $master_id ID master budget
     * @param float $amount Jumlah yang akan dikurangi
     * @param string $reference_type Tipe referensi (PPX_REALISASI, MONTHLY_ALLOCATION, dll)
     * @param string $reference_id ID referensi (no_ppx, id_budget, dll)
     * @param string $note Catatan transaksi
     * @param string $created_by User yang melakukan transaksi
     * @return array ['success' => bool, 'message' => string, 'balance' => float]
     */
    public function deductFromMaster($master_id, $amount, $reference_type, $reference_id, $note, $created_by) {
        // Validasi input
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Error: Amount harus lebih besar dari 0',
                'balance' => 0
            ];
        }
        
        if (empty($master_id)) {
            return [
                'success' => false,
                'message' => 'Error: Master ID tidak boleh kosong',
                'balance' => 0
            ];
        }
        
        try {
            // Start transaction
            $this->conn->begin_transaction();
            
            // Lock row dan ambil data dengan SELECT ... FOR UPDATE
            $query = "SELECT amount_out, balance 
                      FROM master_budget 
                      WHERE master_id = ? 
                      FOR UPDATE";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $master_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $balance_before = $row['balance'];
                $current_amount_out = $row['amount_out'];
                $stmt->close();
                
                // Cek apakah balance cukup
                if ($balance_before < $amount) {
                    throw new Exception(
                        "Saldo tidak cukup. Tersedia: Rp " . number_format($balance_before, 0, ',', '.') . 
                        ", Dibutuhkan: Rp " . number_format($amount, 0, ',', '.')
                    );
                }
                
                // Hitung balance baru
                $new_amount_out = $current_amount_out + $amount;
                $new_balance = $balance_before - $amount;
                
                // Update master budget
                $update_query = "UPDATE master_budget 
                                 SET amount_out = ?, 
                                     balance = ?,
                                     updated_at = NOW()
                                 WHERE master_id = ?";
                
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bind_param("dds", $new_amount_out, $new_balance, $master_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Insert log untuk audit
                $log_query = "INSERT INTO master_budget_log 
                              (master_id, reference_type, reference_id, amount, type, 
                               balance_before, balance_after, note, created_by) 
                              VALUES (?, ?, ?, ?, 'OUT', ?, ?, ?, ?)";
                
                $log_stmt = $this->conn->prepare($log_query);
                $log_stmt->bind_param("sssdddss", 
                    $master_id, $reference_type, $reference_id, $amount, 
                    $balance_before, $new_balance, $note, $created_by
                );
                $log_stmt->execute();
                $log_stmt->close();
                
                // Commit transaction
                $this->conn->commit();
                
                return [
                    'success' => true,
                    'message' => "Berhasil deduct Rp " . number_format($amount, 0, ',', '.') . " dari {$master_id}",
                    'balance' => $new_balance,
                    'balance_before' => $balance_before
                ];
                
            } else {
                $stmt->close();
                throw new Exception("Master ID tidak ditemukan: {$master_id}");
            }
            
        } catch (Exception $e) {
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'balance' => 0
            ];
        }
    }
    
    /**
     * Deduct dari multiple master budget (untuk kasus pembiayaan gabungan)
     * Akan mencoba deduct secara berurutan sampai amount terpenuhi
     * 
     * @param array $master_ids Array of master_id (urutan priority)
     * @param float $total_amount Total amount yang dibutuhkan
     * @param string $reference_type Tipe referensi
     * @param string $reference_id ID referensi
     * @param string $note Catatan
     * @param string $created_by User
     * @return array ['success' => bool, 'message' => string, 'deductions' => array]
     */
    public function deductFromMultipleMasters($master_ids, $total_amount, $reference_type, $reference_id, $note, $created_by) {
        $remaining_amount = $total_amount;
        $deductions = [];
        $errors = [];
        
        foreach ($master_ids as $master_id) {
            if ($remaining_amount <= 0) {
                break;
            }
            
            try {
                // Start transaction per master
                $this->conn->begin_transaction();
                
                // Lock dan ambil balance
                $query = "SELECT balance FROM master_budget WHERE master_id = ? FOR UPDATE";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("s", $master_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $available = $row['balance'];
                    $stmt->close();
                    
                    // Tentukan berapa yang bisa di-deduct dari master ini
                    $to_deduct = min($remaining_amount, $available);
                    
                    if ($to_deduct > 0) {
                        // Deduct
                        $result = $this->deductFromMaster(
                            $master_id, 
                            $to_deduct, 
                            $reference_type, 
                            $reference_id, 
                            $note . " (Partial: {$to_deduct})", 
                            $created_by
                        );
                        
                        if ($result['success']) {
                            $deductions[] = [
                                'master_id' => $master_id,
                                'amount' => $to_deduct,
                                'balance_after' => $result['balance']
                            ];
                            
                            $remaining_amount -= $to_deduct;
                        } else {
                            $this->conn->rollback();
                            $errors[] = $result['message'];
                        }
                    }
                } else {
                    $stmt->close();
                    $this->conn->rollback();
                    $errors[] = "Master ID tidak ditemukan: {$master_id}";
                }
                
            } catch (Exception $e) {
                $this->conn->rollback();
                $errors[] = $e->getMessage();
            }
        }
        
        if ($remaining_amount > 0) {
            return [
                'success' => false,
                'message' => 'Saldo total tidak cukup. Kekurangan: Rp ' . number_format($remaining_amount, 0, ',', '.'),
                'deductions' => $deductions,
                'errors' => $errors
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Berhasil deduct dari ' . count($deductions) . ' master budget',
            'deductions' => $deductions,
            'total_deducted' => $total_amount
        ];
    }
    
    /**
     * Get Master Budget by ID
     * 
     * @param string $master_id
     * @return array|null
     */
    public function getMasterBudget($master_id) {
        $query = "SELECT * FROM master_budget WHERE master_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $master_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get All Master Budgets dengan summary
     * 
     * @return array
     */
    public function getAllMasterBudgets() {
        $query = "SELECT * FROM vw_master_budget_summary ORDER BY tanggal DESC";
        $result = $this->conn->query($query);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Get Transaction Log untuk Master Budget tertentu
     * 
     * @param string $master_id
     * @param int $limit
     * @return array
     */
    public function getTransactionLog($master_id, $limit = 100) {
        $query = "SELECT * FROM master_budget_log 
                  WHERE master_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $master_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Check if master has sufficient balance
     * 
     * @param string $master_id
     * @param float $required_amount
     * @return bool
     */
    public function hasSufficientBalance($master_id, $required_amount) {
        $query = "SELECT balance FROM master_budget WHERE master_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $master_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['balance'] >= $required_amount;
        }
        
        $stmt->close();
        return false;
    }
}
