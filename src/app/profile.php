<?php
ob_start();
session_start();
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

$message = '';
$messageType = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT user_id, username, fullname, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userProfile = $result->fetch_assoc();
$stmt->close();

if (!$userProfile) {
    header("Location: login.php");
    exit();
}

/* -------------------------------
   HANDLE PROFILE UPDATE
--------------------------------*/
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);

    // Check if email is already taken by another user
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $checkStmt->bind_param("si", $email, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();

    if ($checkResult->num_rows > 0) {
        $message = 'That email is already in use by another account.';
        $messageType = 'error';
    } elseif (empty($fullname) || empty($email)) {
        $message = 'Full name and email are required.';
        $messageType = 'error';
    } else {
        $updateStmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE user_id = ?");
        $updateStmt->bind_param("ssi", $fullname, $email, $userId);

        if ($updateStmt->execute()) {
            $_SESSION['username'] = $userProfile['username'];
            $userProfile['fullname'] = $fullname;
            $userProfile['email'] = $email;
            $message = 'Profile updated successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error updating profile. Please try again.';
            $messageType = 'error';
        }
        $updateStmt->close();
    }
}


include "header.php";
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

<!-- Profile Information -->
<div class="widget-box" style="max-width:800px; margin:0 auto 30px auto;">
    <div class="widget-title">
        <h5>My Profile</h5>
    </div>
    <div class="widget-content" style="padding:20px;">
        <form method="post" class="form-horizontal">

            <div class="control-group">
                <label class="control-label">Full Name:</label>
                <div class="controls">
                    <input type="text" class="span11" name="fullname"
                           value="<?= htmlspecialchars($userProfile['fullname']) ?>" required>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Email Address:</label>
                <div class="controls">
                    <input type="email" class="span11" name="email"
                           value="<?= htmlspecialchars($userProfile['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Username:</label>
                <div class="controls">
                    <input type="text" class="span11"
                           value="<?= htmlspecialchars($userProfile['username']) ?>" disabled>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label">Role:</label>
                <div class="controls">
                    <input type="text" class="span11"
                           value="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $userProfile['role']))) ?>" disabled>
                </div>
            </div>

            <div class="form-actions action-buttons">
                <button type="submit" name="update_profile" class="btn btn-success">Save Changes</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>

        </form>
    </div>
</div>

<!-- Change Password Link -->
<div style="max-width:800px; margin:0 auto;">
    <a href="change_password.php" class="btn btn-primary">
        <i class="icon icon-lock"></i> Change Password
    </a>
</div>

</div>
</div>
</div>
</div>

<?php include "footer.php"; ?>