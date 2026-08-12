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
    echo "<div class='alert alert-danger'>Invalid End User ID.</div>";
    exit();
}

$end_user_id = intval($_GET['id']);

// Fetch End User details
$query = "SELECT * FROM expense_end_users WHERE end_user_id = '$end_user_id' LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>End User not found.</div>";
    exit();
}

$end_user = mysqli_fetch_assoc($result);

// Fetch related expenses for this End User
$expenses_query = "
    SELECT 
        e.expense_id,
        e.expense_or_number,
        e.expense_date,
        c.company_name,
        p.payee_name,
        cat.category_name
    FROM expenses e
    INNER JOIN companies c ON e.expense_company_id = c.company_id
    INNER JOIN payees p ON e.expense_payee_id = p.payee_id
    INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
    WHERE e.expense_user_id = '$end_user_id'
    ORDER BY e.expense_date DESC
";
$expenses_result = mysqli_query($conn, $expenses_query);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- View End User Details -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>End User Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <!-- End User Name -->
                            <div class="control-group">
                                <label class="control-label">End User Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($end_user['end_user_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Created At -->
                            <div class="control-group">
                                <label class="control-label">Date Added:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= date('M d, Y h:i A', strtotime($end_user['created_at'])) ?>" disabled>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <a href="end_users_edit.php?id=<?= $end_user['end_user_id'] ?>" class="btn btn-primary">Edit End User</a>
                                <a href="end_users_delete.php?id=<?= $end_user['end_user_id'] ?>" class="btn btn-danger">Delete End User</a>
                                <a href="end_users.php" class="btn btn-secondary">Back</a>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- Related Expenses Table -->
                <div class="widget-box" style="margin-top: 30px;">
                    <div class="widget-content nopadding">
                        <table class="table table-bordered table-striped" id="endUserExpensesTable">
                            <thead>
                                <tr>
                                    <th>OR Number</th>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th>Payee</th>
                                    <th>Category</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($expenses_result && mysqli_num_rows($expenses_result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($expenses_result)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['expense_or_number']) ?></td>
                                            <td><?= date('M d, Y', strtotime($row['expense_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['company_name']) ?></td>
                                            <td><?= htmlspecialchars($row['payee_name']) ?></td>
                                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                                            <td style="white-space: nowrap;">
                                                <a href="expense_view.php?id=<?= $row['expense_id'] ?>" class="btn btn-info btn-mini">View</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;">No related expenses found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<script>
$(document).ready(function() {
    $('#endUserExpensesTable').DataTable({
        "scrollX": true
    });
});
</script>