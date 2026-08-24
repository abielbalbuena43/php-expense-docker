<?php
$host = "db";               // Changed from "localhost" to Docker's database engine path
$user = "root";
$pass = "root_password";    // Changed from empty to match your docker-compose.yml file configuration
$db   = "php_expense";      // Your database name stays exactly the same

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
