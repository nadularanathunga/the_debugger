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
        try {
            $sql = "SELECT s.*, u.full_name as student_name FROM skills s JOIN users u ON s.student_id = u.user_id WHERE s.is_active = 1";
            
            if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
                $student_id = (int)$_GET['student_id'];
                $sql .= " AND s.student_id = " . $student_id;
            }
            
            $sql .= " ORDER BY s.created_at DESC";
            
            $result = $conn->query($sql);
            $skills = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $skills[] = $row;
                }
            }
            json_response(['status' => 'success', 'data' => $skills]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
} elseif ($method === 'POST') {
    $data = get_json_input();
    if ($data === null) {
        json_response(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
    }
    
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'create') {
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
        $skill_name = isset($data['skill_name']) ? trim($data['skill_name']) : '';
        $description = isset($data['description']) ? trim($data['description']) : '';
        $category = isset($data['category']) ? trim($data['category']) : '';
        $price = isset($data['price']) ? (float)$data['price'] : 0.00;
        
        if ($student_id === 0 || $skill_name === '' || $description === '') {
            json_response(['status' => 'error', 'message' => 'Missing or invalid fields'], 400);
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO skills (student_id, skill_name, description, category, price, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param('isssd', $student_id, $skill_name, $description, $category, $price);
            $stmt->execute();
            json_response(['status' => 'success', 'message' => 'Skill listed successfully', 'skill_id' => $conn->insert_id]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
?>
