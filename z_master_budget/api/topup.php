<?php
/**
 * API: Master Budget Top-Up
 * Endpoint untuk menambah dana ke master budget
 * 
 * Method: POST
 * Content-Type: application/json
 * 
 * Request Body:
 * {
 *   "master_id": "MASTER-2025-001" (optional, kosong untuk auto-generate),
 *   "amount": 1000000.00,
 *   "note": "Top-up Q1",
 *   "created_by": "admin"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Berhasil top-up Rp 1.000.000 ke MASTER-2025-001",
 *   "data": {
 *     "master_id": "MASTER-2025-001",
 *     "balance": 6000000.00
 *   }
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
$note = $input['note'] ?? '';
$created_by = $input['created_by'] ?? 'API';

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Amount harus lebih besar dari 0'
    ]);
    exit();
}

try {
    // Initialize service
    $budgetService = new MasterBudgetService($conn);
    
    // Top-up
    $description = !empty($note) ? $note : 'Top-up via API';
    $result = $budgetService->topupMasterBudget(
        $master_id,
        $amount,
        $description,
        $created_by,
        'API-' . date('YmdHis')
    );
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'master_id' => $result['master_id'],
                'balance' => $result['balance']
            ]
        ]);
    } else {
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
