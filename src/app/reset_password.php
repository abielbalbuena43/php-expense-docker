<?php
ob_start();
session_start();
include "connection.php";
include "mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$messageType = '';
$validToken = false;
$user = null;

// Validate token from URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);

    $stmt = $conn->prepare("
        SELECT user_id, fullname, email, reset_token_expires 
        FROM users 
        WHERE reset_token = ? AND reset_token_expires > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $validToken = true;
    } else {
        $message = 'This reset link is invalid or has expired. Please request a new one.';
        $messageType = 'error';
    }
    $stmt->close();
} else {
    $message = 'Invalid reset link.';
    $messageType = 'error';
}

// Handle password reset form submission
if (isset($_POST['reset_password']) && $validToken) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = trim($_POST['token']);

    if (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Re-validate token before updating
        $checkStmt = $conn->prepare("
            SELECT user_id FROM users 
            WHERE reset_token = ? AND reset_token_expires > NOW()
        ");
        $checkStmt->bind_param("s", $token);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $checkRow = $checkResult->fetch_assoc();
            $checkStmt->close();

            // Update password and clear token
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_token_expires = NULL 
                WHERE user_id = ?
            ");
            $updateStmt->bind_param("si", $hashedPassword, $checkRow['user_id']);
            $updateStmt->execute();
            $updateStmt->close();

            $message = 'Password reset successfully. You can now log in with your new password.';
            $messageType = 'success';
            $validToken = false;

        } else {
            $checkStmt->close();
            $message = 'Reset link expired. Please request a new one.';
            $messageType = 'error';
            $validToken = false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password — Expense Tracker</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e54c8;
            --secondary-color: #8f94fb;
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
        .card {
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
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 6px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
        }
        .card-title {
            text-align: center;
            margin-bottom: 25px;
        }
        .card-title h3 {
            margin: 0;
            font-weight: 600;
            color: #333;
            font-size: 22px;
        }
        .card-title p {
            color: #888;
            font-size: 13px;
            margin-top: 5px;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        .input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(78, 84, 200, 0.1);
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 18px;
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
        .btn-submit {
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
            margin-top: 5px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 84, 200, 0.3);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #888;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="card-title">
        <h3>Reset Password</h3>
        <p>Enter your new password below</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
        <?php if ($messageType === 'error'): ?>
        <br><br><a href="forgot_password.php" style="color: inherit; font-weight:600;">Request a new reset link</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($validToken && $user): ?>
    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">

        <!-- New Password -->
        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="new_password" id="new_password"
                   placeholder="New password (min. 8 characters)" required minlength="8">
            <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password', this)"></i>
        </div>

        <!-- Confirm Password -->
        <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="confirm_password" id="confirm_password"
                   placeholder="Confirm new password" required minlength="8">
            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
        </div>

        <button type="submit" name="reset_password" class="btn-submit">
            Reset Password
        </button>
    </form>
    <?php endif; ?>

    <?php if ($messageType === 'success'): ?>
    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
    <?php endif; ?>

    <?php if ($messageType === 'error' || !$validToken && !$message): ?>
    <div class="back-link">
        <a href="forgot_password.php">← Request new reset link</a>
    </div>
    <?php endif; ?>
</div>

<script>
function togglePassword(fieldId, icon) {
    var field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>