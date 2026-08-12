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
// 1. CREATE OPPORTUNITY (POST A TASK)
// ----------------------------------------------------
if ($data['action'] === 'create_task') {
    $creator_id = safe_int($data['creator_id']);
    $title = isset($data['title']) ? $data['title'] : '';
    $description = isset($data['description']) ? $data['description'] : '';
    $required_dept = isset($data['required_dept']) ? $data['required_dept'] : '';
    $budget = safe_float($data['budget']);

    try {
        $stmt = $conn->prepare("INSERT INTO tasks (creator_id, title, description, required_dept, budget, status) VALUES (?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param('isssd', $creator_id, $title, $description, $required_dept, $budget);
        $stmt->execute();
        json_response(['status' => 'success']);
    } catch (Exception $e) {
        error_log('create_task error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

// ----------------------------------------------------
// 2. GET CLIENT DASHBOARD DATA
// ----------------------------------------------------
elseif ($data['action'] === 'get_client_dashboard') {
    $client_id = safe_int($data['client_id']);

    // Get Wallet Balance
    $userQ = $conn->prepare("SELECT wallet_balance FROM users WHERE user_id = ?");
    $userQ->bind_param('i', $client_id);
    $userQ->execute();
    $uRes = $userQ->get_result();
    $balance = ($uRes && $uRes->num_rows > 0) ? $uRes->fetch_assoc()['wallet_balance'] : "0.00";

    // Get My Posted Tasks
    $taskQ = $conn->prepare("SELECT task_id, title, budget, status, DATE(created_at) as created_at FROM tasks WHERE creator_id = ? ORDER BY created_at DESC");
    $taskQ->bind_param('i', $client_id);
    $taskQ->execute();
    $tRes = $taskQ->get_result();
    $tasks = [];
    if ($tRes && $tRes->num_rows > 0) {
        while($row = $tRes->fetch_assoc()) {
            $tasks[] = $row;
        }
    }

    json_response([
        'status' => 'success',
        'wallet_balance' => $balance,
        'tasks' => $tasks
    ]);
}

// ... (Keep the previous create_task and get_client_dashboard code here)

// ----------------------------------------------------
// 3. SUBMIT REVIEW
// ----------------------------------------------------
elseif ($data['action'] === 'submit_review') {
    $task_id = safe_int($data['task_id']);
    $reviewer_id = safe_int($data['reviewer_id']);
    $reviewee_id = safe_int($data['reviewee_id']);
    $rating = safe_int($data['rating']);
    $comment = isset($data['comment']) ? $data['comment'] : '';

    try {
        $stmt = $conn->prepare("INSERT INTO reviews (task_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiis', $task_id, $reviewer_id, $reviewee_id, $rating, $comment);
        $stmt->execute();
        json_response(['status' => 'success']);
    } catch (Exception $e) {
        error_log('submit_review error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

$conn->close();
?>