<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once '../../config/db_conn.php';
require_once '../../includes/function.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = get_json_input();
if ($data === null) {
    json_response(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
}

if (!isset($data['action'])) {
    json_response(['status' => 'error', 'message' => 'Invalid request'], 400);
}

// ----------------------------------------------------
// 1. GET STUDENT DASHBOARD DATA
// ----------------------------------------------------
if ($data['action'] === 'get_dashboard') {
    $user_id = safe_int($data['user_id']);

    try {
        $uStmt = $conn->prepare("SELECT full_name, wallet_balance FROM users WHERE user_id = ? LIMIT 1");
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $uRes = $uStmt->get_result();

        $tStmt = $conn->prepare("SELECT COUNT(task_id) as active_tasks FROM tasks WHERE assigned_student_id = ? AND status = 'taken'");
        $tStmt->bind_param('i', $user_id);
        $tStmt->execute();
        $tRes = $tStmt->get_result();

        if ($uRes && $uRes->num_rows > 0) {
            $userData = $uRes->fetch_assoc();
            $taskData = $tRes->fetch_assoc();
            json_response([
                'status' => 'success',
                'full_name' => $userData['full_name'],
                'wallet_balance' => $userData['wallet_balance'],
                'active_tasks_count' => $taskData['active_tasks']
            ]);
        } else {
            json_response(['status' => 'error', 'message' => 'User not found'], 404);
        }
    } catch (Exception $e) {
        error_log('get_dashboard error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ----------------------------------------------------
// 2. GET OPEN TASKS FOR MARKETPLACE
// ----------------------------------------------------
elseif ($data['action'] === 'get_open_tasks') {
    try {
        $stmt = $conn->prepare("SELECT task_id, title, description, required_dept, budget, created_at FROM tasks WHERE status = 'open' ORDER BY created_at DESC");
        $stmt->execute();
        $res = $stmt->get_result();
        $tasks = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        json_response(['status' => 'success', 'tasks' => $tasks]);
    } catch (Exception $e) {
        error_log('get_open_tasks error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ... (Keep the previous get_dashboard and get_open_tasks code here)

// ----------------------------------------------------
// 3. ADD A NEW SKILL
// ----------------------------------------------------
elseif ($data['action'] === 'add_skill') {
    $student_id = safe_int($data['student_id']);
    $skill_name = isset($data['skill_name']) ? $data['skill_name'] : '';
    $category = isset($data['category']) ? $data['category'] : '';
    $price = safe_float($data['price']);
    $description = isset($data['description']) ? $data['description'] : '';

    try {
        $stmt = $conn->prepare("INSERT INTO skills (student_id, skill_name, description, category, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('isssd', $student_id, $skill_name, $description, $category, $price);
        $stmt->execute();
        json_response(['status' => 'success']);
    } catch (Exception $e) {
        error_log('add_skill error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ----------------------------------------------------
// 4. GET MY SKILLS
// ----------------------------------------------------
elseif ($data['action'] === 'get_my_skills') {
    $student_id = safe_int($data['student_id']);
    try {
        $stmt = $conn->prepare("SELECT * FROM skills WHERE student_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $skills = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $skills[] = $row;
            }
        }
        json_response(['status' => 'success', 'skills' => $skills]);
    } catch (Exception $e) {
        error_log('get_my_skills error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ----------------------------------------------------
// 5. GET WALLET DATA & TRANSACTIONS
// ----------------------------------------------------
elseif ($data['action'] === 'get_wallet_data') {
    $user_id = safe_int($data['user_id']);
    try {
        $uStmt = $conn->prepare("SELECT wallet_balance FROM users WHERE user_id = ? LIMIT 1");
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        $balance = ($uRes && $uRes->num_rows > 0) ? $uRes->fetch_assoc()['wallet_balance'] : "0.00";

        $tStmt = $conn->prepare("SELECT transaction_type, category, amount, description, created_at FROM financial_transactions WHERE user_id = ? ORDER BY created_at DESC");
        $tStmt->bind_param('i', $user_id);
        $tStmt->execute();
        $transRes = $tStmt->get_result();
        $transactions = [];
        if ($transRes && $transRes->num_rows > 0) {
            while ($row = $transRes->fetch_assoc()) {
                $transactions[] = $row;
            }
        }
        json_response(['status' => 'success', 'wallet_balance' => $balance, 'transactions' => $transactions]);
    } catch (Exception $e) {
        error_log('get_wallet_data error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}
// ...
// ... (Keep previous student_api.php logic here)

// ----------------------------------------------------
// 6. ACCEPT A TASK
// ----------------------------------------------------
elseif ($data['action'] === 'accept_task') {
    $task_id = safe_int($data['task_id']);
    $student_id = safe_int($data['student_id']);
    try {
        $stmt = $conn->prepare("UPDATE tasks SET status = 'taken', assigned_student_id = ?, taken_at = CURRENT_TIMESTAMP WHERE task_id = ? AND status = 'open'");
        $stmt->bind_param('ii', $student_id, $task_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            json_response(['status' => 'success']);
        } else {
            json_response(['status' => 'error', 'message' => 'Task already taken or not found'], 400);
        }
    } catch (Exception $e) {
        error_log('accept_task error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ----------------------------------------------------
// 7. GET MY ACTIVE TASKS (TASK MANAGEMENT)
// ----------------------------------------------------
elseif ($data['action'] === 'get_my_active_tasks') {
    $student_id = safe_int($data['student_id']);
    try {
        $stmt = $conn->prepare("SELECT task_id, title, budget, DATE(taken_at) as taken_at FROM tasks WHERE assigned_student_id = ? AND status = 'taken' ORDER BY taken_at DESC");
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $tasks = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $tasks[] = $row;
            }
        }
        json_response(['status' => 'success', 'tasks' => $tasks]);
    } catch (Exception $e) {
        error_log('get_my_active_tasks error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ... (Keep previous student_api.php logic here)

// ----------------------------------------------------
// 8. GET PUBLIC STUDENT PROFILE
// ----------------------------------------------------
elseif ($data['action'] === 'get_public_profile') {
    $student_id = safe_int($data['student_id']);
    try {
        $uStmt = $conn->prepare("SELECT full_name, department, is_verified FROM users WHERE user_id = ? AND role = 'student' LIMIT 1");
        $uStmt->bind_param('i', $student_id);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRes && $uRes->num_rows > 0) {
            $user = $uRes->fetch_assoc();
            $sStmt = $conn->prepare("SELECT skill_name, description, category, price FROM skills WHERE student_id = ? AND is_active = 1 ORDER BY created_at DESC");
            $sStmt->bind_param('i', $student_id);
            $sStmt->execute();
            $sRes = $sStmt->get_result();
            $skills = [];
            if ($sRes && $sRes->num_rows > 0) {
                while ($row = $sRes->fetch_assoc()) {
                    $skills[] = $row;
                }
            }
            json_response(['status' => 'success', 'user' => $user, 'skills' => $skills]);
        } else {
            json_response(['status' => 'error', 'message' => 'Student not found'], 404);
        }
    } catch (Exception $e) {
        error_log('get_public_profile error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
    }

$conn->close();
?>