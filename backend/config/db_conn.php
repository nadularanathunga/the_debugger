<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "the_debugger";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>