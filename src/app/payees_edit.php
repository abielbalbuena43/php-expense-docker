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
    header("Location: payees.php");
    exit();
}

$payee_id = intval($_GET['id']);

// Fetch current payee record
$payee_query = mysqli_query($conn, "SELECT * FROM payees WHERE payee_id = '$payee_id' LIMIT 1");
if (mysqli_num_rows($payee_query) === 0) {
    echo "<div class='alert alert-danger'>Payee record not found.</div>";
    exit();
}
$payee = mysqli_fetch_assoc($payee_query);

// Handle update form submission
if (isset($_POST['update_payee'])) {
    $name = mysqli_real_escape_string($conn, $_POST['payee_name']);
    $type = mysqli_real_escape_string($conn, $_POST['payee_type']);
    $tin = !empty($_POST['payee_tin']) ? mysqli_real_escape_string($conn, $_POST['payee_tin']) : NULL;
    $category = !empty($_POST['payee_category']) ? mysqli_real_escape_string($conn, $_POST['payee_category']) : NULL;
    $address1 = !empty($_POST['payee_address1']) ? mysqli_real_escape_string($conn, $_POST['payee_address1']) : NULL;
    $address2 = !empty($_POST['payee_address2']) ? mysqli_real_escape_string($conn, $_POST['payee_address2']) : NULL;

    $query = "
        UPDATE payees SET
            payee_name = '$name',
            payee_type = '$type',
            payee_tin = " . ($tin ? "'$tin'" : "NULL") . ",
            payee_category = " . ($category ? "'$category'" : "NULL") . ",
            payee_address1 = " . ($address1 ? "'$address1'" : "NULL") . ",
            payee_address2 = " . ($address2 ? "'$address2'" : "NULL") . ",
            payee_updated_at = NOW()
        WHERE payee_id = '$payee_id'
    ";

    if (mysqli_query($conn, $query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Payee updated', '$username', 'Payee: $name (Payee ID: $payee_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Payee updated successfully!";
        header("Location: payees.php");
        exit();
    } else {
        $_SESSION['alert'] = "error_update";
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

                <?php if ($alert == "Payee updated successfully!") { ?>
                    <div class="alert alert-success">Payee updated successfully!</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update payee.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit Payee Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Payee Name -->
                            <div class="control-group">
                                <label class="control-label">Name:</label>
                                <div class="controls">
                                    <input type="text" name="payee_name" class="span11" 
                                           value="<?= htmlspecialchars($payee['payee_name']) ?>" required>
                                </div>
                            </div>

                            <!-- Payee Type -->
                            <div class="control-group">
                                <label class="control-label">Type:</label>
                                <div class="controls">
                                    <input type="text" name="payee_type" class="span11" 
                                           value="<?= htmlspecialchars($payee['payee_type']) ?>" required>
                                </div>
                            </div>

                            <!-- Payee TIN -->
                            <div class="control-group">
                                <label class="control-label">TIN:</label>
                                <div class="controls">
                                    <input type="text" name="payee_tin" class="span11" 
                                           value="<?= htmlspecialchars($payee['payee_tin'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Payee Category -->
                            <div class="control-group">
                                <label class="control-label">Category:</label>
                                <div class="controls">
                                    <input type="text" name="payee_category" class="span11" 
                                           value="<?= htmlspecialchars($payee['payee_category'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Address 1 -->
                            <div class="control-group">
                                <label class="control-label">Address 1:</label>
                                <div class="controls">
                                    <input type="text" name="payee_address1" class="span11" 
                                           value="<?= htmlspecialchars($payee['payee_address1'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Address 2 -->
                            <div class="control-group">
                                <label class="control-label">Address 2:</label>
                                <div class="controls">
                                    <input type="text" name="payee_address2" class="span11" 
                                           value="<?= htmlspecialchars($payee['payee_address2'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_payee" class="btn btn-success">Update Payee</button>
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
