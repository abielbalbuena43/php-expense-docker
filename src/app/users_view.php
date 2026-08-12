<?php
ob_start();
session_start();
include "header.php"; 
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

// Validate user ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid User ID.</div>";
    exit();
}

$user_id = intval($_GET['id']);

// Fetch user details
$query = "
    SELECT user_id, username, fullname, role, created_at
    FROM users
    WHERE user_id = '$user_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>User record not found.</div>";
    exit();
}

$user = mysqli_fetch_assoc($result);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- User Information -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>User Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <!-- Username -->
                            <div class="control-group">
                                <label class="control-label">Username:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Fullname -->
                            <div class="control-group">
                                <label class="control-label">Full Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($user['fullname']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="control-group">
                                <label class="control-label">Role:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?>" disabled>
                                </div>
                            </div>

                            <!-- Created At -->
                            <div class="control-group">
                                <label class="control-label">Created At:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= date('M d, Y h:i A', strtotime($user['created_at'])) ?>" disabled>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <a href="users_edit.php?id=<?= $user['user_id'] ?>" class="btn btn-primary">Edit User</a>
                                <a href="users_delete.php?id=<?= $user['user_id'] ?>" class="btn btn-danger">Delete User</a>
                                <a href="users.php" class="btn btn-secondary">Back</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>