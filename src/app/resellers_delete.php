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

// Check if a reseller ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: resellers.php");
    exit();
}

$reseller_id = intval($_GET['id']);

// Fetch the reseller details for confirmation
$query = "
    SELECT 
        r.reseller_id,
        r.reseller_name,
        r.reseller_created_at
    FROM resellers r
    WHERE r.reseller_id = $reseller_id
";

$result = mysqli_query($conn, $query);

// If no reseller found, redirect
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: resellers.php");
    exit();
}

$reseller = mysqli_fetch_assoc($result);

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM resellers WHERE reseller_id = $reseller_id";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $resellerName = mysqli_real_escape_string($conn, $reseller['reseller_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Reseller deleted', '$username', 'Reseller: $resellerName (Reseller ID: $reseller_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Reseller deleted successfully!";
        header("Location: resellers.php");
        exit();
    } else {
        $_SESSION['alert'] = "error";
    }
}

// Alert messages
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
} else {
    $alert = null;
}
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 400px; padding: 20px;">
            <div class="span12">

                <!-- Alert Messages -->
                <?php if ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to delete reseller.</div>
                <?php } elseif ($alert == "invalid") { ?>
                    <div class="alert alert-warning">Invalid reseller ID.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">Reseller not found.</div>
                <?php } ?>

                <!-- Delete Confirmation -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Delete Reseller Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following reseller?</p>

                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($reseller['reseller_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?= htmlspecialchars($reseller['reseller_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Date Created</th>
                                <td><?= htmlspecialchars($reseller['reseller_created_at']) ?></td>
                            </tr>
                        </table>

                        <form action="" method="post">
                        <div class="form-actions action-buttons">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="icon-trash"></i> Confirm Delete
                            </button>
                            <a href="resellers.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>