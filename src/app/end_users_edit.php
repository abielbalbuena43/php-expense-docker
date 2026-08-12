<?php
ob_start();
session_start();
include "header.php";
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role = $_SESSION['role'];
$isSuperAdmin = $role === 'super_admin';
$isAdmin = $role === 'admin';

if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// Validate End User ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: end_users.php");
    exit();
}

$end_user_id = intval($_GET['id']);

// Fetch End User record
$query = mysqli_query($conn, "SELECT * FROM expense_end_users WHERE end_user_id = '$end_user_id' LIMIT 1");
if (mysqli_num_rows($query) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: end_users.php");
    exit();
}

$end_user = mysqli_fetch_assoc($query);

// Handle update
if (isset($_POST['update_end_user'])) {
    $end_user_name = mysqli_real_escape_string($conn, $_POST['end_user_name']);

    if (!empty($end_user_name)) {
        $update_query = "
            UPDATE expense_end_users
            SET end_user_name = '$end_user_name'
            WHERE end_user_id = '$end_user_id'
        ";

        if (mysqli_query($conn, $update_query)) {
            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('End User updated', '$username', 'End User: $end_user_name (End User ID: $end_user_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            $_SESSION['alert'] = "End User updated successfully!";
            header("Location: end_users.php");
            exit();
        } else {
            $_SESSION['alert'] = "error_update";
        }
    } else {
        $_SESSION['alert'] = "empty_fields";
    }
}

$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- Display success or error alerts -->
                <?php if ($alert == "End User updated successfully!") { ?>
                    <div class="alert alert-success">End User updated successfully!</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update End User.</div>
                <?php } elseif ($alert == "empty_fields") { ?>
                    <div class="alert alert-warning">Error: End User name cannot be empty.</div>
                <?php } ?>

                <!-- Edit End User Form -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit End User Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- End User Name -->
                            <div class="control-group">
                                <label class="control-label">End User Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="end_user_name" value="<?= htmlspecialchars($end_user['end_user_name']) ?>" required>
                                </div>
                            </div>

                            <!-- Created At (Readonly) -->
                            <div class="control-group">
                                <label class="control-label">Created At:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= date('M d, Y', strtotime($end_user['created_at'])) ?>" disabled>
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_end_user" class="btn btn-success">Update End User</button>
                                <a href="end_users.php" class="btn btn-secondary">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>