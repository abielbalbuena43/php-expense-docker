<?php
ob_start();
session_start();
include "connection.php";
include "header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

/* -------------------------------
   HANDLE PASSWORD CHANGE
--------------------------------*/
if (isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Fetch current password
    $pwStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $pwStmt->bind_param("i", $userId);
    $pwStmt->execute();
    $pwResult = $pwStmt->get_result();
    $pwRow = $pwResult->fetch_assoc();
    $pwStmt->close();

    if (!password_verify($oldPassword, $pwRow['password'])) {
        $message = 'Old password is incorrect.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePwStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updatePwStmt->bind_param("si", $hashedPassword, $userId);

        if ($updatePwStmt->execute()) {
            $message = 'Password changed successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error changing password. Please try again.';
            $messageType = 'error';
        }
        $updatePwStmt->close();
    }
}
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
<div class="container-fluid">
<div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
<div class="span12">

<?php if ($message): ?>
<div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="widget-box" style="max-width:800px; margin:0 auto;">
    <div class="widget-title">
        <h5><i class="icon icon-lock"></i> Change Password</h5>
    </div>
    <div class="widget-content" style="padding:20px;">
        <form method="post" class="form-horizontal">

            <div class="control-group">
                <label class="control-label">Old Password:</label>
                <div class="controls">
                    <input type="password" class="span11" name="old_password"
                           placeholder="Enter current password" required>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">New Password:</label>
                <div class="controls">
                    <input type="password" class="span11" name="new_password"
                           placeholder="Minimum 8 characters" required minlength="8">
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Confirm New Password:</label>
                <div class="controls">
                    <input type="password" class="span11" name="confirm_password"
                           placeholder="Confirm new password" required minlength="8">
                </div>
            </div>

            <div class="form-actions action-buttons">
                <button type="submit" name="change_password" class="btn btn-success">
                    Change Password
                </button>
                <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
            </div>

        </form>
    </div>
</div>

</div>
</div>
</div>
</div>

<?php include "footer.php"; ?>