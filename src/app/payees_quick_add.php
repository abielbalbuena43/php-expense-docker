<?php
session_start();
include "connection.php";

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['role'];
$isSuperAdmin = $role === 'super_admin';
$isAdmin = $role === 'admin';

if (!$isSuperAdmin && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);

$name = mysqli_real_escape_string($conn, trim($data['name'] ?? ''));
$type = mysqli_real_escape_string($conn, trim($data['type'] ?? ''));
$tin = mysqli_real_escape_string($conn, trim($data['tin'] ?? ''));
$address1 = mysqli_real_escape_string($conn, trim($data['address1'] ?? ''));
$address2 = mysqli_real_escape_string($conn, trim($data['address2'] ?? ''));

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Payee name is required']);
    exit();
}

$query = "
    INSERT INTO payees (payee_name, payee_type, payee_tin, payee_address1, payee_address2, payee_created_at)
    VALUES ('$name', '$type', '$tin', '$address1', '$address2', NOW())
";

if (mysqli_query($conn, $query)) {
    $new_id = mysqli_insert_id($conn);

    // Log the action
    $username = mysqli_real_escape_string($conn, $_SESSION['username']);
    mysqli_query($conn, "
        INSERT INTO logs (log_action, log_user, log_details, log_date)
        VALUES ('Payee created', '$username', 'Payee: $name (Payee ID: $new_id)', NOW())
    ");

    echo json_encode([
        'success' => true,
        'payee_id' => $new_id,
        'payee_name' => $name,
        'payee_tin' => $tin
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}