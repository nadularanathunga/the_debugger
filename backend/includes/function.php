<?php
// Common helper functions for API responses and input handling

function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    return $data;
}

function safe_int($val) {
    return isset($val) ? (int)$val : 0;
}

function safe_float($val) {
    return isset($val) ? (float)$val : 0.0;
}
