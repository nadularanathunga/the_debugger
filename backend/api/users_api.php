<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once '../config/db_conn.php';
require_once '../includes/function.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list_students';
    
    if ($action === 'list_students') {
        try {
            $sql = "SELECT user_id, full_name, email, role FROM users WHERE role = 'student' ORDER BY created_at DESC";
            $result = $conn->query($sql);
            $students = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Fetch top skills for this student
                    $skill_sql = "SELECT category, skill_name FROM skills WHERE student_id = " . $row['user_id'] . " AND is_active = 1 LIMIT 3";
                    $skill_res = $conn->query($skill_sql);
                    $skills = [];
                    if ($skill_res) {
                        while ($s_row = $skill_res->fetch_assoc()) {
                            $skills[] = $s_row;
                        }
                    }
                    $row['skills'] = $skills;
                    $students[] = $row;
                }
            }
            json_response(['status' => 'success', 'data' => $students]);
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    } elseif ($action === 'get_profile') {
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        
        if ($user_id === 0) {
            json_response(['status' => 'error', 'message' => 'Invalid user ID'], 400);
        }
        
        try {
            $stmt = $conn->prepare("SELECT user_id, full_name, email, role, created_at FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Fetch all skills
                $skill_sql = "SELECT * FROM skills WHERE student_id = " . $row['user_id'] . " AND is_active = 1";
                $skill_res = $conn->query($skill_sql);
                $skills = [];
                if ($skill_res) {
                    while ($s_row = $skill_res->fetch_assoc()) {
                        $skills[] = $s_row;
                    }
                }
                $row['skills'] = $skills;
                
                json_response(['status' => 'success', 'data' => $row]);
            } else {
                json_response(['status' => 'error', 'message' => 'User not found'], 404);
            }
        } catch (Exception $e) {
            json_response(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
?>
