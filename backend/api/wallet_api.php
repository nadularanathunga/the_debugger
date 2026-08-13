<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once '../config/db_conn.php';
require_once '../includes/function.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($user_id > 0) {
        try {
            // Get balance from users table
            $sql = "SELECT wallet_balance FROM users WHERE user_id = $user_id";
            $result = $conn->query($sql);
            $balance = 0.00;
            if ($row = $result->fetch_assoc()) {
                $balance = $row['wallet_balance'] ? (float)$row['wallet_balance'] : 0.00;
            }
            
            // Get recent transactions from financial_transactions
            $tx_sql = "SELECT * FROM financial_transactions WHERE user_id = $user_id ORDER BY created_at DESC";
            $tx_result = $conn->query($tx_sql);
            $transactions = [];
            if ($tx_result) {
                while ($t_row = $tx_result->fetch_assoc()) {
                    // Normalize to the format expected by the frontend if necessary
                    // Frontend expects `type` ('credit', 'debit') and `amount`
                    $t_row['type'] = ($t_row['transaction_type'] === 'INCOME') ? 'credit' : 'debit';
                    $transactions[] = $t_row;
                }
            }
            
            json_response(['status' => 'success', 'balance' => $balance, 'transactions' => $transactions]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    } else {
        json_response(['status' => 'error', 'message' => 'Missing user_id'], 400);
    }
} elseif ($method === 'POST') {
    $data = get_json_input();
    if ($data === null) {
        json_response(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
    }
    
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'add_funds') {
        $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0;
        $description = isset($data['description']) ? trim($data['description']) : 'Funds Added';
        
        if ($user_id === 0 || $amount <= 0) {
            json_response(['status' => 'error', 'message' => 'Invalid parameters'], 400);
        }
        
        try {
            $conn->begin_transaction();
            
            // 1. Update wallet balance
            $update_sql = "UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('di', $amount, $user_id);
            $update_stmt->execute();
            
            // 2. Insert into financial_transactions
            $insert_sql = "INSERT INTO financial_transactions (user_id, transaction_type, category, amount, description) VALUES (?, 'INCOME', 'DEPOSIT', ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param('ids', $user_id, $amount, $description);
            $insert_stmt->execute();
            
            $conn->commit();
            json_response(['status' => 'success', 'message' => 'Funds added successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
?>
