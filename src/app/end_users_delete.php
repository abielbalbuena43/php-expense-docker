<?php
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

// Check if End User ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: end_users.php");
    exit();
}

$end_user_id = intval($_GET['id']);

// Fetch End User details for confirmation
$query = "
    SELECT 
        end_user_id,
        end_user_name
    FROM expense_end_users
    WHERE end_user_id = '$end_user_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: end_users.php");
    exit();
}

$end_user = mysqli_fetch_assoc($result);

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM expense_end_users WHERE end_user_id = '$end_user_id'";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $endUserName = mysqli_real_escape_string($conn, $end_user['end_user_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('End User deleted', '$username', 'End User: $endUserName (End User ID: $end_user_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "End User deleted successfully!";
        header("Location: end_users.php");
        exit();
    } else {
        $_SESSION['alert'] = "error";
    }
}

// Alert messages
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 300px; padding: 20px;">
            <div class="span12">

                <!-- Display alert messages -->
                <?php if ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to delete End User.</div>
                <?php } elseif ($alert == "invalid") { ?>
                    <div class="alert alert-warning">Invalid End User ID.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">End User not found.</div>
                <?php } elseif ($alert == "End User deleted successfully!") { ?>
                    <div class="alert alert-success">End User deleted successfully!</div>
                <?php } ?>

                <!-- Delete Confirmation Form -->
                <div class="widget-box" style="max-width: 600px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Delete End User Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following End User?</p>

                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($end_user['end_user_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?= htmlspecialchars($end_user['end_user_name']) ?></td>
                            </tr>
                        </table>

                        <form action="" method="post">
                        <div class="form-actions action-buttons">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="icon-trash"></i> Confirm Delete
                            </button>
                            <a href="end_users.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>