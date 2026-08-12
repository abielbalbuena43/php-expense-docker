<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
session_start();
include "../app/connection.php";

// Note: In a real production environment, use Prepared Statements to prevent SQL Injection
if (isset($_POST['login'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {

        $user = mysqli_fetch_assoc($result);

        // Support both hashed and plaintext passwords
        $isHashed = strlen($user['password']) > 30 && str_starts_with($user['password'], '$2y$');
        if ($isHashed ? password_verify($password, $user['password']) : $user['password'] === $password) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: dashboard.php");
            exit();

        } else {
            $error = "Incorrect password.";
        }

    } else {
        $error = "Incorrect username.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Expense Tracker Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4e54c8;
            --secondary-color: #8f94fb;
            --text-color: #333;
            --bg-color: #f0f2f5;
        }

        body {
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            font-family: 'Poppins', sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            margin: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative top bar */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }

        .login-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title h3 {
            margin: 0;
            font-weight: 600;
            color: var(--text-color);
            font-size: 24px;
        }

        .login-title p {
            color: #888;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Floating Label Input Style */
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px; /* Left padding for icon */
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box; /* Ensures padding doesn't break width */
        }

        .input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(78, 84, 200, 0.1);
        }

        .input-group label {
            position: absolute;
            left: 45px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 15px;
            pointer-events: none;
            transition: all 0.3s ease;
            background: white;
            padding: 0 5px;
        }

        /* Move label up when input has focus or content */
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0;
            font-size: 12px;
            color: var(--primary-color);
            font-weight: 500;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 18px;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 20px;
            text-align: center;
            border: 1px solid #ffcdd2;
            animation: shake 0.5s;
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            font-size: 12px;
            color: #aaa;
        }

        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-title">
        <h3>Welcome Back</h3>
        <p>Please enter your details to sign in</p>
    </div>

    <form method="post" action="">

        <!-- Username Input -->
        <div class="input-group">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="username" id="username" placeholder=" " required>
            <label for="username">Username</label>
        </div>

        <!-- Password Input -->
        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="password" id="password" placeholder=" " required>
            <label for="password">Password</label>
            <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
        </div>

        <button type="submit" name="login" class="btn-login">
            Sign In
        </button>

        <?php
        if(isset($error)){
            echo "<div class='error-message'><i class='fas fa-exclamation-circle'></i> $error</div>";
        }
        ?>

    </form>

    <div class="footer-text">
        &copy; ITW Expense Management System
    </div>
</div>

<script>
    function togglePassword(){
        var field = document.getElementById("password");
        var icon = document.querySelector(".password-toggle");
        
        if(field.type === "password"){
            field.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            field.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }
</script>

</body>
</html>