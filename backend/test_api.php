<?php
/**
 * API Test Script
 * 
 * Quick tests to verify backend is functioning correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database info
define('TEST_URL', 'http://localhost/topinv');
define('API_BASE', TEST_URL . '/api');

class APITester {
    private $baseUrl = API_BASE;
    private $token = null;
    private $testResults = [];
    
    public function run() {
        echo "=== TOPINV Backend API Tests ===\n\n";
        
        try {
            $this->testLogin();
            $this->testProducts();
            $this->testSalesWorkflow();
            $this->testPurchases();
            $this->testTransactions();
            $this->testPeriods();
            
            $this->printResults();
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function testLogin() {
        echo "[*] Testing Authentication...\n";
        
        $response = $this->request('POST', '/auth/login', [
            'username' => 'cashier1',
            'password' => 'password'
        ]);
        
        if ($response['success']) {
            $this->token = $response['data']['token'];
            $this->addResult('LOGIN', true, 'Successfully authenticated as ' . $response['data']['user']['username']);
        } else {
            $this->addResult('LOGIN', false, $response['message']);
        }
    }
    
    private function testProducts() {
        echo "[*] Testing Products...\n";
        
        // Get products
        $response = $this->request('GET', '/products');
        if ($response['success'] && count($response['data']['products']) > 0) {
            $this->addResult('LIST_PRODUCTS', true, 'Retrieved ' . count($response['data']['products']) . ' products');
        } else {
            $this->addResult('LIST_PRODUCTS', false, 'Failed to list products');
        }
        
        // Get single product
        $product_id = $response['data']['products'][0]['id'] ?? 1;
        $response = $this->request('GET', "/products/$product_id");
        if ($response['success']) {
            $this->addResult('GET_PRODUCT', true, 'Retrieved product: ' . $response['data']['name']);
        } else {
            $this->addResult('GET_PRODUCT', false, 'Failed to get product');
        }
    }
    
    private function testSalesWorkflow() {
        echo "[*] Testing Sales Workflow...\n";
        
        // Create draft sale
        $response = $this->request('POST', '/sales/draft', []);
        
        if ($response['success']) {
            $draft_id = $response['data']['draft_id'];
            $this->addResult('CREATE_DRAFT_SALE', true, "Draft ID: $draft_id");
            
            // Add item to draft
            $response = $this->request('POST', "/sales/draft/$draft_id/items", [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 25.00
            ]);
            
            if ($response['success']) {
                $this->addResult('ADD_DRAFT_ITEM', true, 'Item added to draft');
            } else {
                $this->addResult('ADD_DRAFT_ITEM', false, $response['message']);
            }
            
            // Get draft
            $response = $this->request('GET', "/sales/draft/$draft_id");
            if ($response['success']) {
                $this->addResult('GET_DRAFT_SALE', true, 'Draft with ' . $response['data']['item_count'] . ' items');
            } else {
                $this->addResult('GET_DRAFT_SALE', false, $response['message']);
            }
            
            // Commit draft
            $response = $this->request('POST', '/sales/commit', [
                'draft_id' => $draft_id,
                'period_id' => 1
            ]);
            
            if ($response['success']) {
                $this->addResult('COMMIT_SALE', true, 'Sale committed, ' . $response['data']['transaction_ids'][0] . ' created');
            } else {
                $this->addResult('COMMIT_SALE', false, $response['message']);
            }
        } else {
            $this->addResult('CREATE_DRAFT_SALE', false, $response['message']);
        }
    }
    
    private function testPurchases() {
        echo "[*] Testing Purchases...\n";
        
        $response = $this->request('POST', '/purchases', [
            'product_id' => 1,
            'quantity' => 10,
            'unit_cost' => 15.00,
            'period_id' => 1,
            'supplier' => 'Test Supplier'
        ]);
        
        if ($response['success']) {
            $this->addResult('RECORD_PURCHASE', true, 'Purchase recorded, transaction ID: ' . $response['data']['transaction_id']);
        } else {
            $this->addResult('RECORD_PURCHASE', false, $response['message']);
        }
    }
    
    private function testTransactions() {
        echo "[*] Testing Transactions...\n";
        
        $response = $this->request('GET', '/transactions?period_id=1');
        
        if ($response['success']) {
            $this->addResult('LIST_TRANSACTIONS', true, 'Retrieved ' . count($response['data']['transactions']) . ' transactions');
        } else {
            $this->addResult('LIST_TRANSACTIONS', false, $response['message']);
        }
    }
    
    private function testPeriods() {
        echo "[*] Testing Periods...\n";
        
        $response = $this->request('GET', '/periods');
        
        if ($response['success'] && count($response['data']['periods']) > 0) {
            $this->addResult('LIST_PERIODS', true, 'Retrieved ' . count($response['data']['periods']) . ' periods');
            
            // Get period summary
            $period_id = $response['data']['periods'][0]['id'];
            $response = $this->request('GET', "/periods/$period_id/summary");
            
            if ($response['success']) {
                $this->addResult('PERIOD_SUMMARY', true, $response['data']['total_transactions'] . ' transactions in period');
            } else {
                $this->addResult('PERIOD_SUMMARY', false, $response['message']);
            }
        } else {
            $this->addResult('LIST_PERIODS', false, 'No periods found');
        }
    }
    
    private function request($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->token
                ],
                'ignore_errors' => true
            ]
        ];
        
        if (!empty($data)) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        return json_decode($response, true);
    }
    
    private function addResult($test, $passed, $message = '') {
        $this->testResults[] = [
            'test' => $test,
            'passed' => $passed,
            'message' => $message
        ];
    }
    
    private function printResults() {
        echo "\n=== Test Results ===\n\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->testResults as $result) {
            $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';
            $color = $result['passed'] ? "\033[32m" : "\033[31m";
            $reset = "\033[0m";
            
            echo "{$color}[{$status}]{$reset} {$result['test']}\n";
            if ($result['message']) {
                echo "     {$result['message']}\n";
            }
            
            if ($result['passed']) $passed++;
            else $failed++;
        }
        
        echo "\n=== Summary ===\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Total:  " . ($passed + $failed) . "\n";
    }
}

// Run tests
$tester = new APITester();
$tester->run();
?>
