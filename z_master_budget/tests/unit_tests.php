/**
 * UNIT TEST GUIDE - Master Budget Module
 * Pseudocode untuk testing concurrent requests dan race conditions
 */

// ============================================================
// TEST 1: Basic Top-Up Test
// ============================================================
function test_basic_topup() {
    $budgetService = new MasterBudgetService($conn);
    
    // Test auto-generate ID
    $result1 = $budgetService->topupMasterBudget(
        '',                             // Auto-generate
        5000000000.00,
        'Test Master Q1',
        'tester'
    );
    
    assert($result1['success'] === true);
    assert(!empty($result1['master_id']));
    assert($result1['balance'] === 5000000000.00);
    
    $master_id = $result1['master_id'];
    
    // Test top-up to existing
    $result2 = $budgetService->topupMasterBudget(
        $master_id,
        1000000000.00,
        'Additional top-up',
        'tester'
    );
    
    assert($result2['success'] === true);
    assert($result2['balance'] === 6000000000.00);
    
    echo "✓ Test Basic Top-Up: PASSED\n";
}

// ============================================================
// TEST 2: Basic Deduct Test
// ============================================================
function test_basic_deduct() {
    $budgetService = new MasterBudgetService($conn);
    
    // Create master with 10M
    $result = $budgetService->topupMasterBudget('', 10000000.00, 'Test', 'tester');
    $master_id = $result['master_id'];
    
    // Deduct 3M
    $deduct1 = $budgetService->deductFromMaster(
        $master_id,
        3000000.00,
        'TEST_DEDUCT',
        'TEST-001',
        'Test deduction 1',
        'tester'
    );
    
    assert($deduct1['success'] === true);
    assert($deduct1['balance'] === 7000000.00);
    
    // Deduct another 2M
    $deduct2 = $budgetService->deductFromMaster(
        $master_id,
        2000000.00,
        'TEST_DEDUCT',
        'TEST-002',
        'Test deduction 2',
        'tester'
    );
    
    assert($deduct2['success'] === true);
    assert($deduct2['balance'] === 5000000.00);
    
    echo "✓ Test Basic Deduct: PASSED\n";
}

// ============================================================
// TEST 3: Insufficient Balance Test
// ============================================================
function test_insufficient_balance() {
    $budgetService = new MasterBudgetService($conn);
    
    // Create master with 1M
    $result = $budgetService->topupMasterBudget('', 1000000.00, 'Test', 'tester');
    $master_id = $result['master_id'];
    
    // Try to deduct 2M (should fail)
    $deduct = $budgetService->deductFromMaster(
        $master_id,
        2000000.00,
        'TEST_DEDUCT',
        'TEST-FAIL',
        'This should fail',
        'tester'
    );
    
    assert($deduct['success'] === false);
    assert(strpos($deduct['message'], 'tidak cukup') !== false);
    
    // Verify balance unchanged
    $master = $budgetService->getMasterBudget($master_id);
    assert($master['balance'] === 1000000.00);
    
    echo "✓ Test Insufficient Balance: PASSED\n";
}

// ============================================================
// TEST 4: Concurrent Deduct Test (Race Condition)
// ============================================================
function test_concurrent_deduct_manual() {
    /**
     * MANUAL TEST PROCEDURE:
     * 
     * Setup:
     * 1. Create master budget dengan balance = 2.000.000
     * 2. Buka 2 terminal/postman tabs
     * 
     * Terminal 1:
     * curl -X POST http://localhost/asset_dfm/z_master_budget/api/realisasi.php \
     *   -H "Content-Type: application/json" \
     *   -d '{
     *     "master_id": "MASTER-2025-TEST",
     *     "amount": 1500000,
     *     "reference_type": "TEST",
     *     "reference_id": "CONCURRENT-A",
     *     "note": "Test A",
     *     "created_by": "tester_a"
     *   }' &
     * 
     * Terminal 2 (execute dalam 1 detik setelah terminal 1):
     * curl -X POST http://localhost/asset_dfm/z_master_budget/api/realisasi.php \
     *   -H "Content-Type: application/json" \
     *   -d '{
     *     "master_id": "MASTER-2025-TEST",
     *     "amount": 1500000,
     *     "reference_type": "TEST",
     *     "reference_id": "CONCURRENT-B",
     *     "note": "Test B",
     *     "created_by": "tester_b"
     *   }' &
     * 
     * Expected Result:
     * - Request A: Success (balance = 500.000)
     * - Request B: Error "Saldo tidak cukup" (karena balance tinggal 500.000)
     * 
     * Verification Query:
     * SELECT * FROM master_budget_log 
     * WHERE master_id = 'MASTER-2025-TEST' 
     * ORDER BY created_at DESC;
     * 
     * Should show:
     * - Only 1 deduction (from Request A)
     * - Balance progression consistent
     * - No negative balance
     */
    
    echo "✓ Test Concurrent Deduct: MANUAL TEST (see procedure above)\n";
}

// ============================================================
// TEST 5: Deduct from Multiple Masters Test
// ============================================================
function test_deduct_multiple_masters() {
    $budgetService = new MasterBudgetService($conn);
    
    // Create 3 masters
    $m1 = $budgetService->topupMasterBudget('', 1000000.00, 'Master 1', 'tester');
    $m2 = $budgetService->topupMasterBudget('', 2000000.00, 'Master 2', 'tester');
    $m3 = $budgetService->topupMasterBudget('', 3000000.00, 'Master 3', 'tester');
    
    $master_ids = [$m1['master_id'], $m2['master_id'], $m3['master_id']];
    
    // Try to deduct 4M (should use M1: 1M, M2: 2M, M3: 1M)
    $result = $budgetService->deductFromMultipleMasters(
        $master_ids,
        4000000.00,
        'TEST_MULTI',
        'TEST-MULTI-001',
        'Test multiple deduct',
        'tester'
    );
    
    assert($result['success'] === true);
    assert(count($result['deductions']) === 3);
    assert($result['total_deducted'] === 4000000.00);
    
    // Verify individual balances
    $master1 = $budgetService->getMasterBudget($m1['master_id']);
    $master2 = $budgetService->getMasterBudget($m2['master_id']);
    $master3 = $budgetService->getMasterBudget($m3['master_id']);
    
    assert($master1['balance'] === 0.00);      // Used 1M
    assert($master2['balance'] === 0.00);      // Used 2M
    assert($master3['balance'] === 2000000.00); // Used 1M, remaining 2M
    
    echo "✓ Test Deduct Multiple Masters: PASSED\n";
}

// ============================================================
// TEST 6: Transaction Rollback Test
// ============================================================
function test_transaction_rollback() {
    $budgetService = new MasterBudgetService($conn);
    
    // Create master with 5M
    $result = $budgetService->topupMasterBudget('', 5000000.00, 'Test', 'tester');
    $master_id = $result['master_id'];
    
    // Simulate failed transaction
    $conn->begin_transaction();
    
    try {
        // Deduct 2M
        $deduct = $budgetService->deductFromMaster(
            $master_id, 2000000.00, 'TEST', 'TEST-ROLLBACK', 'Test', 'tester'
        );
        
        // Force error
        throw new Exception('Simulated error');
        
    } catch (Exception $e) {
        $conn->rollback();
    }
    
    // Verify balance unchanged (should still be 5M)
    $master = $budgetService->getMasterBudget($master_id);
    assert($master['balance'] === 5000000.00);
    
    echo "✓ Test Transaction Rollback: PASSED\n";
}

// ============================================================
// TEST 7: Data Consistency Test
// ============================================================
function test_data_consistency() {
    /**
     * Verify:
     * 1. balance = amount_in - amount_out
     * 2. Log sum matches master amount_in/amount_out
     * 3. No negative balance
     */
    
    $query = "
        SELECT mb.master_id,
               mb.amount_in,
               mb.amount_out,
               mb.balance,
               COALESCE(SUM(CASE WHEN mbl.type = 'IN' THEN mbl.amount ELSE 0 END), 0) as log_in,
               COALESCE(SUM(CASE WHEN mbl.type = 'OUT' THEN mbl.amount ELSE 0 END), 0) as log_out,
               (mb.amount_in - mb.amount_out) as calculated_balance
        FROM master_budget mb
        LEFT JOIN master_budget_log mbl ON mb.master_id = mbl.master_id
        GROUP BY mb.master_id
        HAVING mb.balance != calculated_balance
           OR mb.amount_in != log_in
           OR mb.amount_out != log_out
           OR mb.balance < 0
    ";
    
    $result = $conn->query($query);
    $inconsistent_rows = $result->num_rows;
    
    assert($inconsistent_rows === 0, "Found {$inconsistent_rows} inconsistent rows!");
    
    echo "✓ Test Data Consistency: PASSED\n";
}

// ============================================================
// TEST 8: Performance Test (High Volume)
// ============================================================
function test_performance_high_volume() {
    $budgetService = new MasterBudgetService($conn);
    
    // Create master with 100M
    $result = $budgetService->topupMasterBudget('', 100000000.00, 'Performance Test', 'tester');
    $master_id = $result['master_id'];
    
    $start_time = microtime(true);
    
    // Perform 100 deductions of 100K each
    for ($i = 1; $i <= 100; $i++) {
        $deduct = $budgetService->deductFromMaster(
            $master_id,
            100000.00,
            'PERF_TEST',
            'PERF-' . str_pad($i, 3, '0', STR_PAD_LEFT),
            'Performance test iteration ' . $i,
            'tester'
        );
        
        assert($deduct['success'] === true);
    }
    
    $end_time = microtime(true);
    $execution_time = ($end_time - $start_time);
    
    // Verify final balance
    $master = $budgetService->getMasterBudget($master_id);
    assert($master['balance'] === 90000000.00); // 100M - (100 * 100K)
    
    echo "✓ Test Performance: PASSED\n";
    echo "  - 100 deductions completed in {$execution_time} seconds\n";
    echo "  - Average: " . ($execution_time / 100) . " seconds per deduction\n";
}

// ============================================================
// RUN ALL TESTS
// ============================================================
echo "=================================\n";
echo "MASTER BUDGET UNIT TESTS\n";
echo "=================================\n\n";

test_basic_topup();
test_basic_deduct();
test_insufficient_balance();
test_concurrent_deduct_manual();
test_deduct_multiple_masters();
test_transaction_rollback();
test_data_consistency();
test_performance_high_volume();

echo "\n=================================\n";
echo "ALL TESTS COMPLETED!\n";
echo "=================================\n";
