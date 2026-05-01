<?php
$servername = "127.0.0.1"; // or "localhost"
$username   = "root";
$password   = "rakib2002"; // empty by default in XAMPP unless you set one
$database   = "jobportalsystem";
$port       = 3307; // <-- important, match your MySQL port

$conn = new mysqli($servername, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>