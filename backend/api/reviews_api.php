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
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($student_id > 0) {
        try {
            $sql = "SELECT r.*, u.full_name as client_name FROM reviews r JOIN users u ON r.reviewer_id = u.user_id WHERE r.reviewee_id = $student_id ORDER BY r.created_at DESC";
            $result = $conn->query($sql);
            $reviews = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $reviews[] = $row;
                }
            }
            json_response(['status' => 'success', 'data' => $reviews]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    } else {
        json_response(['status' => 'error', 'message' => 'Missing student_id'], 400);
    }
} elseif ($method === 'POST') {
    $data = get_json_input();
    if ($data === null) {
        json_response(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
    }
    
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'create') {
        $task_id = isset($data['task_id']) ? (int)$data['task_id'] : 0;
        $reviewer_id = isset($data['client_id']) ? (int)$data['client_id'] : (isset($data['reviewer_id']) ? (int)$data['reviewer_id'] : 0);
        $reviewee_id = isset($data['student_id']) ? (int)$data['student_id'] : (isset($data['reviewee_id']) ? (int)$data['reviewee_id'] : 0);
        $rating = isset($data['rating']) ? (int)$data['rating'] : 5;
        $comment = isset($data['comment']) ? trim($data['comment']) : '';
        
        if ($task_id === 0 || $reviewer_id === 0 || $reviewee_id === 0) {
            json_response(['status' => 'error', 'message' => 'Missing or invalid fields'], 400);
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO reviews (task_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('iiiis', $task_id, $reviewer_id, $reviewee_id, $rating, $comment);
            $stmt->execute();
            
            // Optionally update task status to 'completed' if not already
            $conn->query("UPDATE tasks SET status = 'completed' WHERE task_id = $task_id AND status != 'completed'");
            
            json_response(['status' => 'success', 'message' => 'Review submitted successfully']);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
?>
