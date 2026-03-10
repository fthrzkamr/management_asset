<?php
/**
 * API: Realisasi (Deduct dari Master Budget)
 * Endpoint untuk memotong dana dari master budget saat realisasi PPX
 * 
 * Method: POST
 * Content-Type: application/json
 * 
 * Request Body:
 * {
 *   "master_id": "MASTER-2025-001",
 *   "amount": 500000.00,
 *   "reference_type": "PPX_REALISASI",
 *   "reference_id": "PPX-2025-0001",
 *   "note": "Realisasi PPX Marketing",
 *   "created_by": "finance"
 * }
 * 
 * Response Success:
 * {
 *   "success": true,
 *   "message": "Berhasil deduct Rp 500.000 dari MASTER-2025-001",
 *   "data": {
 *     "master_id": "MASTER-2025-001",
 *     "balance_before": 5000000.00,
 *     "balance_after": 4500000.00,
 *     "amount_deducted": 500000.00
 *   }
 * }
 * 
 * Response Error (Insufficient Balance):
 * {
 *   "success": false,
 *   "message": "Error: Saldo tidak cukup. Tersedia: Rp 100.000, Dibutuhkan: Rp 500.000"
 * }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../dist/config/config.php';
require_once __DIR__ . '/../MasterBudgetService.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST.'
    ]);
    exit();
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON: ' . json_last_error_msg()
    ]);
    exit();
}

// Validasi input
$master_id = $input['master_id'] ?? '';
$amount = floatval($input['amount'] ?? 0);
$reference_type = $input['reference_type'] ?? 'REALISASI';
$reference_id = $input['reference_id'] ?? '';
$note = $input['note'] ?? '';
$created_by = $input['created_by'] ?? 'API';

// Validasi required fields
if (empty($master_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'master_id tidak boleh kosong'
    ]);
    exit();
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Amount harus lebih besar dari 0'
    ]);
    exit();
}

if (empty($reference_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'reference_id tidak boleh kosong'
    ]);
    exit();
}

try {
    // Initialize service
    $budgetService = new MasterBudgetService($conn);
    
    // Deduct dari master
    $result = $budgetService->deductFromMaster(
        $master_id,
        $amount,
        $reference_type,
        $reference_id,
        $note,
        $created_by
    );
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'master_id' => $master_id,
                'balance_before' => $result['balance_before'],
                'balance_after' => $result['balance'],
                'amount_deducted' => $amount
            ]
        ]);
    } else {
        // Jika saldo tidak cukup atau error lain
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error: ' . $e->getMessage()
    ]);
}
