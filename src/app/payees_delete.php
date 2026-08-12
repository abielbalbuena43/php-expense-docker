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

// Check if a payee ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: payees.php");
    exit();
}

$payee_id = intval($_GET['id']);

// Fetch the payee details for confirmation
$query = "
    SELECT 
        payee_id,
        payee_name,
        payee_type,
        payee_tin,
        payee_category,
        payee_address1,
        payee_address2,
        payee_created_at
    FROM payees
    WHERE payee_id = $payee_id
";
$result = mysqli_query($conn, $query);

// If no payee found, redirect
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: payees.php");
    exit();
}

$payee = mysqli_fetch_assoc($result);

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM payees WHERE payee_id = $payee_id";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $payeeName = mysqli_real_escape_string($conn, $payee['payee_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Payee deleted', '$username', 'Payee: $payeeName (Payee ID: $payee_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Payee deleted successfully!";
        header("Location: payees.php");
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
                    <div class="alert alert-danger">Error: Unable to delete payee.</div>
                <?php } elseif ($alert == "invalid") { ?>
                    <div class="alert alert-warning">Invalid payee ID.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">Payee not found.</div>
                <?php } ?>

                <!-- Delete Confirmation -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Delete Payee Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following payee?</p>

                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($payee['payee_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?= htmlspecialchars($payee['payee_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Type</th>
                                <td><?= htmlspecialchars($payee['payee_type']) ?></td>
                            </tr>
                            <tr>
                                <th>TIN</th>
                                <td><?= htmlspecialchars($payee['payee_tin']) ?></td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td><?= htmlspecialchars($payee['payee_category']) ?></td>
                            </tr>
                            <tr>
                                <th>Address 1</th>
                                <td><?= htmlspecialchars($payee['payee_address1']) ?></td>
                            </tr>
                            <tr>
                                <th>Address 2</th>
                                <td><?= htmlspecialchars($payee['payee_address2']) ?></td>
                            </tr>
                            <tr>
                                <th>Created At</th>
                                <td><?= date('M d, Y', strtotime($payee['payee_created_at'])) ?></td>
                            </tr>
                        </table>

                        <form method="post">
                        <div class="form-actions action-buttons">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="icon-trash"></i> Confirm Delete
                            </button>
                            <a href="payees.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
