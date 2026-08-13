<?php
$host = "localhost";
$user = "root";
$pass = ""; // Try empty first, then 'root' if it fails
$dbname = "the_debugger";

// First connect without DB to create it
try {
    $conn = new mysqli($host, $user, $pass);
} catch (Exception $e) {
    try {
        $pass = "root"; // MAMP default on Mac
        $conn = new mysqli($host, $user, $pass);
    } catch (Exception $e2) {
        die("Connection failed: " . $e2->getMessage());
    }
}

$sql_file = __DIR__ . '/config/the_debugger_db.sql';
if (!file_exists($sql_file)) {
    die("SQL file not found.");
}

$sql = file_get_contents($sql_file);

// Execute the multi query
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Database setup completed successfully! Password used: " . ($pass === "" ? "empty" : $pass) . "\n";
    
    // Update db_conn.php with correct password if it was 'root'
    if ($pass === "root") {
        $db_conn_file = __DIR__ . '/config/db_conn.php';
        $content = file_get_contents($db_conn_file);
        $content = str_replace('$pass = "";', '$pass = "root";', $content);
        file_put_contents($db_conn_file, $content);
        echo "Updated db_conn.php with password 'root'\n";
    }
} else {
    echo "Error executing SQL: " . $conn->error . "\n";
}

$conn->close();
?>
