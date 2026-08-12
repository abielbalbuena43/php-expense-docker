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

// Validate payee ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Payee ID.</div>";
    exit();
}

$payee_id = intval($_GET['id']);

// Fetch payee details
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
    WHERE payee_id = '$payee_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>Payee record not found.</div>";
    exit();
}

$payee = mysqli_fetch_assoc($result);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 500px; padding: 20px;">
            <div class="span12">
                <!-- View Payee -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Payee Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <!-- Payee Name -->
                            <div class="control-group">
                                <label class="control-label">Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($payee['payee_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Payee Type -->
                            <div class="control-group">
                                <label class="control-label">Type:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($payee['payee_type']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Payee TIN -->
                            <div class="control-group">
                                <label class="control-label">TIN:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($payee['payee_tin'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>

                            <!-- Payee Category -->
                            <div class="control-group">
                                <label class="control-label">Category:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($payee['payee_category'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>

                            <!-- Address 1 -->
                            <div class="control-group">
                                <label class="control-label">Address 1:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($payee['payee_address1'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>

                            <!-- Address 2 -->
                            <div class="control-group">
                                <label class="control-label">Address 2:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($payee['payee_address2'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>

                            <!-- Created At -->
                            <div class="control-group">
                                <label class="control-label">Created At:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= date('M d, Y', strtotime($payee['payee_created_at'])) ?>" disabled>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <?php if ($isSuperAdmin || $isAdmin): ?>
                                <a href="payees_edit.php?id=<?= $payee['payee_id'] ?>" class="btn btn-primary">Edit Payee</a>
                                <a href="payees_delete.php?id=<?= $payee['payee_id'] ?>" class="btn btn-danger">Delete Payee</a>
                                <?php endif; ?>
                                <a href="payees.php" class="btn btn-secondary">Back</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
