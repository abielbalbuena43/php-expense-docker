<?php
ob_start();
session_start();
include "connection.php";
include "mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$messageType = '';

if (isset($_POST['send_reset'])) {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id, fullname, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Save token to DB
        $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE user_id = ?");
        $updateStmt->bind_param("ssi", $token, $expires, $user['user_id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Build reset link
        $resetLink = APP_URL . "/reset_password.php?token=" . $token;

        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($user['email'], $user['fullname']);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request — ITW Expense Management System';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #4e54c8;'>Password Reset Request</h2>
                    <p>Hi {$user['fullname']},</p>
                    <p>We received a request to reset your password. Click the button below to reset it:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' 
                           style='background: linear-gradient(to right, #4e54c8, #8f94fb); 
                                  color: white; padding: 15px 30px; 
                                  text-decoration: none; border-radius: 10px; 
                                  font-weight: bold; font-size: 16px;'>
                            Reset Password
                        </a>
                    </div>
                    <p style='color: #888; font-size: 13px;'>This link expires in 1 hour.</p>
                    <p style='color: #888; font-size: 13px;'>If you didn't request this, you can safely ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='color: #aaa; font-size: 12px;'>ITW Expense Management System</p>
                </div>
            ";
            $mail->AltBody = "Reset your password here: $resetLink (expires in 1 hour)";

            $mail->send();
            $message = 'Password reset link sent. Please check your email.';
            $messageType = 'success';

        } catch (Exception $e) {
            $message = 'Failed to send email: ' . $mail->ErrorInfo;
            $messageType = 'error';
        }

    } else {
        $stmt->close();
        // Don't reveal if email exists or not for security
        $message = 'If that email is registered, a reset link has been sent.';
        $messageType = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Forgot Password — Expense Tracker</title>
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
        <h3>Forgot Password</h3>
        <p>Enter your email and we'll send you a reset link</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <div class="input-group">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="email" placeholder="Enter your email address" required>
        </div>

        <button type="submit" name="send_reset" class="btn-submit">
            Send Reset Link
        </button>
    </form>

    <div class="back-link">
        <a href="login.php">← Back to Login</a>
    </div>
</div>

</body>
</html>