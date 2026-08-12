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

// Handle form submission
if (isset($_POST['submit_end_user'])) {
    $end_user_name = mysqli_real_escape_string($conn, $_POST['end_user_name']);

    if (!empty($end_user_name)) {
        $query = "INSERT INTO expense_end_users (end_user_name, created_at) VALUES ('$end_user_name', NOW())";

        if (mysqli_query($conn, $query)) {
            $new_end_user_id = mysqli_insert_id($conn);
            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('End User created', '$username', 'End User: $end_user_name (End User ID: $new_end_user_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            $_SESSION['alert'] = "End User created successfully!";
            header("Location: end_users.php");
            exit();
        } else {
            $_SESSION['alert'] = "error";
        }
    } else {
        $_SESSION['alert'] = "empty";
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
                <?php if ($alert == "success") { ?>
                    <div class="alert alert-success">End User created successfully!</div>
                <?php } elseif ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to create End User.</div>
                <?php } elseif ($alert == "empty") { ?>
                    <div class="alert alert-warning">Error: End User name cannot be empty.</div>
                <?php } ?>

                <!-- New End User Form -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Create New End User</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- End User Name -->
                            <div class="control-group">
                                <label class="control-label">End User Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="end_user_name" placeholder="Type end user name" required>
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="submit_end_user" class="btn btn-success">Create End User</button>
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