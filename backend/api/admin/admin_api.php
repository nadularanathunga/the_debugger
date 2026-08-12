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
// GET ADMIN DASHBOARD STATS
// ----------------------------------------------------
if ($data['action'] === 'get_admin_stats') {
    try {
        $usersCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        $tasksCount = $conn->query("SELECT COUNT(*) as count FROM tasks")->fetch_assoc()['count'];
        $skillsCount = $conn->query("SELECT COUNT(*) as count FROM skills")->fetch_assoc()['count'];
        json_response(['status' => 'success', 'users' => $usersCount, 'tasks' => $tasksCount, 'skills' => $skillsCount]);
    } catch (Exception $e) {
        error_log('get_admin_stats error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}

$conn->close();
?>