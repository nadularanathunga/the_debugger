<?php
// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once '../config/db_conn.php';
require_once '../includes/function.php';

// Accept preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = get_json_input();
if ($data === null) {
    json_response(['status' => 'error', 'message' => 'Invalid JSON input'], 400);
}

if (!isset($data['action'])) {
    json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
}

$action = $data['action'];

// ----------------------------------------------------
// SIGNUP
// ----------------------------------------------------
if ($action === 'signup') {
    $full_name = isset($data['full_name']) ? trim($data['full_name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $role = isset($data['role']) ? trim($data['role']) : '';
    $department = isset($data['department']) && $data['department'] !== null ? trim($data['department']) : 'NONE';
    $student_id_number = isset($data['student_id_number']) && $data['student_id_number'] !== null ? trim($data['student_id_number']) : null;

    if ($full_name === '' || $email === '' || $password === '' || $role === '') {
        json_response(['status' => 'error', 'message' => 'All fields are required'], 400);
    }

    $allowed_roles = ['student', 'client', 'lecturer', 'admin'];
    if (!in_array($role, $allowed_roles, true)) {
        json_response(['status' => 'error', 'message' => 'Invalid role'], 400);
    }

    $allowed_departments = ['ET', 'ICT', 'BST', 'NONE'];
    if (!in_array($department, $allowed_departments, true)) {
        $department = 'NONE';
    }

    if ($role !== 'student') {
        $department = 'NONE';
        $student_id_number = null;
    } elseif ($student_id_number === '') {
        $student_id_number = null;
    }

    try {
        // Check for an existing account with this email first
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();
        if ($checkRes && $checkRes->num_rows > 0) {
            json_response(['status' => 'error', 'message' => 'An account with this email already exists'], 409);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, department, student_id_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $full_name, $email, $hashed_password, $role, $department, $student_id_number);
        $stmt->execute();

        json_response([
            'status' => 'success',
            'message' => 'Account created successfully',
            'user_id' => $conn->insert_id,
            'full_name' => $full_name
        ]);
    } catch (Exception $e) {
        error_log('Auth API signup error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    } finally {
        $conn->close();
    }
}

// ----------------------------------------------------
// LOGIN
// ----------------------------------------------------
if ($action === 'login') {
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    if ($email === '' || $password === '') {
        json_response(['status' => 'error', 'message' => 'All fields are required'], 400);
    }

    try {
        $stmt = $conn->prepare("SELECT user_id, full_name, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $stored = $user['password'];

            $ok = false;
            if (password_needs_rehash($stored, PASSWORD_DEFAULT) || password_verify($password, $stored)) {
                // hashed password path
                $ok = password_verify($password, $stored);
            } else {
                // fallback: plain text compare
                $ok = ($password === $stored);
            }

            if ($ok) {
                json_response([
                    'status' => 'success',
                    'message' => 'Login successful',
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]);
            } else {
                json_response(['status' => 'error', 'message' => 'Invalid credentials'], 401);
            }
        } else {
            json_response(['status' => 'error', 'message' => 'User not found or role mismatch'], 404);
        }
    } catch (Exception $e) {
        error_log('Auth API error: ' . $e->getMessage());
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    } finally {
        $conn->close();
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
?>