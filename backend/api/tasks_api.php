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
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    
    if ($action === 'list') {
        $whereClause = "WHERE 1=1";
        $params = [];
        $types = "";
        
        if (isset($_GET['creator_id'])) {
            $whereClause .= " AND creator_id = ?";
            $params[] = (int)$_GET['creator_id'];
            $types .= "i";
        }
        if (isset($_GET['assigned_student_id'])) {
            $whereClause .= " AND assigned_student_id = ?";
            $params[] = (int)$_GET['assigned_student_id'];
            $types .= "i";
        }
        if (isset($_GET['status'])) {
            $whereClause .= " AND status = ?";
            $params[] = $_GET['status'];
            $types .= "s";
        }
        
        try {
            $sql = "SELECT t.*, u.full_name as creator_name FROM tasks t JOIN users u ON t.creator_id = u.user_id $whereClause ORDER BY t.created_at DESC";
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            json_response(['status' => 'success', 'data' => $tasks]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
} elseif ($method === 'POST') {
    $data = get_json_input();
    if ($data === null) {
        json_response(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
    }
    
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'create') {
        $creator_id = isset($data['creator_id']) ? (int)$data['creator_id'] : 0;
        $title = isset($data['title']) ? trim($data['title']) : '';
        $description = isset($data['description']) ? trim($data['description']) : '';
        $required_dept = isset($data['required_dept']) ? trim($data['required_dept']) : 'NONE';
        $budget = isset($data['budget']) ? (float)$data['budget'] : 0.00;
        
        if ($creator_id === 0 || $title === '' || $description === '' || $budget <= 0) {
            json_response(['status' => 'error', 'message' => 'Missing or invalid fields'], 400);
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO tasks (creator_id, title, description, required_dept, budget, status) VALUES (?, ?, ?, ?, ?, 'open')");
            $stmt->bind_param('isssd', $creator_id, $title, $description, $required_dept, $budget);
            $stmt->execute();
            json_response(['status' => 'success', 'message' => 'Task created successfully', 'task_id' => $conn->insert_id]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    } elseif ($action === 'accept') {
        $task_id = isset($data['task_id']) ? (int)$data['task_id'] : 0;
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
        
        if ($task_id === 0 || $student_id === 0) {
            json_response(['status' => 'error', 'message' => 'Missing task_id or student_id'], 400);
        }
        
        try {
            // Ensure task is open
            $check = $conn->prepare("SELECT status FROM tasks WHERE task_id = ?");
            $check->bind_param('i', $task_id);
            $check->execute();
            $res = $check->get_result();
            if ($res->num_rows > 0) {
                $task = $res->fetch_assoc();
                if ($task['status'] !== 'open') {
                    json_response(['status' => 'error', 'message' => 'Task is not open'], 400);
                }
            } else {
                json_response(['status' => 'error', 'message' => 'Task not found'], 404);
            }
            
            $stmt = $conn->prepare("UPDATE tasks SET status = 'taken', assigned_student_id = ?, taken_at = CURRENT_TIMESTAMP WHERE task_id = ?");
            $stmt->bind_param('ii', $student_id, $task_id);
            $stmt->execute();
            json_response(['status' => 'success', 'message' => 'Task accepted successfully']);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    } elseif ($action === 'complete_task') {
        $task_id = isset($data['task_id']) ? (int)$data['task_id'] : 0;
        
        if ($task_id === 0) {
            json_response(['status' => 'error', 'message' => 'Missing task_id'], 400);
        }
        
        try {
            // Get task details to transfer funds
            $sql = "SELECT creator_id, assigned_student_id, budget, status FROM tasks WHERE task_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $task_id);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows === 0) {
                json_response(['status' => 'error', 'message' => 'Task not found'], 404);
            }
            
            $task = $res->fetch_assoc();
            
            if ($task['status'] === 'completed') {
                json_response(['status' => 'error', 'message' => 'Task is already completed'], 400);
            }
            
            $creator_id = $task['creator_id'];
            $student_id = $task['assigned_student_id'];
            $budget = (float)$task['budget'];
            
            if (!$student_id) {
                json_response(['status' => 'error', 'message' => 'No student assigned to this task'], 400);
            }
            
            $conn->begin_transaction();
            
            // 1. Mark task as completed
            $conn->query("UPDATE tasks SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE task_id = $task_id");
            
            // 2. Deduct from client
            $conn->query("UPDATE users SET wallet_balance = wallet_balance - $budget WHERE user_id = $creator_id");
            
            // 3. Add to student
            $conn->query("UPDATE users SET wallet_balance = wallet_balance + $budget WHERE user_id = $student_id");
            
            // 4. Log transactions
            $desc_client = "Paid for task #$task_id";
            $desc_student = "Earned for task #$task_id";
            
            $conn->query("INSERT INTO financial_transactions (user_id, transaction_type, category, amount, description, task_id) VALUES ($creator_id, 'EXPENSE', 'TASK_PAYMENT', $budget, '$desc_client', $task_id)");
            
            $conn->query("INSERT INTO financial_transactions (user_id, transaction_type, category, amount, description, task_id) VALUES ($student_id, 'INCOME', 'TASK_EARNING', $budget, '$desc_student', $task_id)");
            
            $conn->commit();
            json_response(['status' => 'success', 'message' => 'Task completed and funds transferred successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            json_response(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
?>
