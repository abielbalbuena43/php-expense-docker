<?php
session_start();
include "header.php";
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current hashed password
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $isHashed = strlen($user['password']) > 30 && str_starts_with($user['password'], '$2y$');
    $passwordCorrect = $isHashed ? password_verify($current_password, $user['password']) : $user['password'] === $current_password;

    if (!$passwordCorrect) {
        setAlert('error', 'Current password is incorrect.');
    } elseif (strlen($new_password) < 6) {
        setAlert('error', 'New password must be at least 6 characters.');
    } elseif ($new_password !== $confirm_password) {
        setAlert('error', 'New passwords do not match.');
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        if ($stmt->execute()) {
            setAlert('success', 'Password changed successfully.');
        } else {
            setAlert('error', 'Unable to update password. Please try again.');
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/layout.css">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <title>Change Password</title>
</head>

<body>
    <div id="content">
        <div class="container-fluid">
            <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
                <div class="span12">

                    <?php if (isset($_SESSION['alert'])): ?>
                        <?php
                        $alert = $_SESSION['alert'];
                        $type = is_array($alert) ? ($alert['type'] ?? 'info') : 'success';
                        $message = is_array($alert) ? ($alert['message'] ?? '') : $alert;
                        unset($_SESSION['alert']);
                        ?>
                        <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                        <div class="widget-title">
                            <h5>Change Password</h5>
                        </div>

                        <div class="widget-content" style="padding: 20px;">
                            <form action="" method="post" class="form-horizontal">

                                <div class="control-group">
                                    <label class="control-label">Current Password:</label>
                                    <div class="controls">
                                        <input type="password" class="span11" name="current_password" required placeholder="Enter current password">
                                    </div>
                                </div>

                                <div class="control-group">
                                    <label class="control-label">New Password:</label>
                                    <div class="controls">
                                        <input type="password" class="span11" name="new_password" required placeholder="Enter new password">
                                    </div>
                                </div>

                                <div class="control-group">
                                    <label class="control-label">Confirm New Password:</label>
                                    <div class="controls">
                                        <input type="password" class="span11" name="confirm_password" required placeholder="Confirm new password">
                                    </div>
                                </div>

                                <div class="form-actions action-buttons">
                                    <button type="submit" name="change_password" class="btn btn-success">
                                        Save Password
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                </div>

                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php include "footer.php"; ?>
</body>
</html>