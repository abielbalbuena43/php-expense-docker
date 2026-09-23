<?php

$host = getenv('DB_HOST') ?: 'db';
$port = (int)(getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: 'root_password';
$db   = getenv('DB_NAME') ?: 'php_expense';

$conn = mysqli_init();

if (!$conn) {
    die("Database initialization failed.");
}

/*
 * Enable SSL when DB_SSL is set to 1.
 * This will be used for the Aiven database connection.
 */
if (getenv('DB_SSL') === '1') {
    $ssl_ca = getenv('DB_SSL_CA');

    if ($ssl_ca) {
        mysqli_ssl_set(
            $conn,
            null,
            null,
            $ssl_ca,
            null,
            null
        );
    }
}

if (!mysqli_real_connect(
    $conn,
    $host,
    $user,
    $pass,
    $db,
    $port,
    null,
    getenv('DB_SSL') === '1' ? MYSQLI_CLIENT_SSL : 0
)) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>