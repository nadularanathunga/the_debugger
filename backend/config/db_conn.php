<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "the_debugger";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    // Don't expose internal errors to clients
    http_response_code(500);
    die('Database connection error');
}
?>