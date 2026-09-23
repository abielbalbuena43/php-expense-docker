<?php
session_start();
include "header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role = $_SESSION['role'];
$isSuperAdmin = $role === 'super_admin';
$isAdmin = $role === 'admin';

if (!$isSuperAdmin) {
    header("Location: companies.php");
    exit();
}

// Check if company ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'Invalid company ID.'];
    header("Location: companies.php");
    exit();
}

$company_id = intval($_GET['id']);

// Fetch company details for confirmation
$query = "
    SELECT 
        company_id,
        company_name
    FROM companies
    WHERE company_id = '$company_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'Company not found.'];
    header("Location: companies.php");
    exit();
}

$company = mysqli_fetch_assoc($result);

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
     $delete_query = "DELETE FROM companies WHERE company_id = $company_id";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $companyName = mysqli_real_escape_string($conn, $company['company_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Company deleted', '$username', 'Company ID: $company_id, Name: $companyName', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = ['type' => 'success', 'message' => 'Company deleted successfully!'];
        header("Location: companies.php");
        exit();
    } else {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'Error: Unable to delete company.'];
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
                <?php if ($alert): ?>
                    <?php
                    $alertType = is_array($alert) ? ($alert['type'] ?? 'info') : 'success';
                    $alertMessage = is_array($alert) ? ($alert['message'] ?? '') : $alert;
                    ?>
                    <div class="alert alert-<?= $alertType ?>">
                        <?= htmlspecialchars($alertMessage) ?>
                    </div>
                <?php endif; ?>

                <!-- Delete Confirmation Form -->
                <div class="widget-box" style="max-width: 600px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Delete Company Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following company?</p>

                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($company['company_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?= htmlspecialchars($company['company_name']) ?></td>
                            </tr>
                        </table>

                        <form method="post">
                        <div class="form-actions action-buttons">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="icon-trash"></i> Confirm Delete
                            </button>
                            <a href="companies.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>