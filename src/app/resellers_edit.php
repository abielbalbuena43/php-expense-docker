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

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: resellers.php");
    exit();
}

$reseller_id = intval($_GET['id']);

// Fetch reseller record
$query = mysqli_query($conn, "SELECT * FROM resellers WHERE reseller_id = '$reseller_id' LIMIT 1");
if (mysqli_num_rows($query) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: resellers.php");
    exit();
}

$reseller = mysqli_fetch_assoc($query);

// Handle update
if (isset($_POST['update_reseller'])) {
    $reseller_name = mysqli_real_escape_string($conn, $_POST['reseller_name']);

    if (!empty($reseller_name)) {
        $update_query = "
            UPDATE resellers 
            SET reseller_name = '$reseller_name'
            WHERE reseller_id = '$reseller_id'
        ";

        if (mysqli_query($conn, $update_query)) {
            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('Reseller updated', '$username', 'Reseller: $reseller_name (Reseller ID: $reseller_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            $_SESSION['alert'] = "Reseller updated successfully!";
            header("Location: resellers.php");
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
        <div class="row-fluid" style="background-color: white; min-height: 500px; padding: 20px;">
            <div class="span12">

                <!-- Alert Messages -->
                <?php if ($alert == "Reseller updated successfully!") { ?>
                    <div class="alert alert-success">Reseller updated successfully!</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update reseller.</div>
                <?php } elseif ($alert == "empty_fields") { ?>
                    <div class="alert alert-warning">Please fill in all required fields.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">Reseller not found.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit Reseller</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Reseller Name -->
                            <div class="control-group">
                                <label class="control-label">Reseller Name:</label>
                                <div class="controls">
                                    <input type="text" name="reseller_name" class="span11" 
                                           value="<?= htmlspecialchars($reseller['reseller_name']) ?>" required>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_reseller" class="btn btn-success">Update Reseller</button>
                                <a href="resellers.php" class="btn btn-secondary">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>