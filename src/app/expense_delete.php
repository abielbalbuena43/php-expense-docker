<?php
ob_start();
session_start();
include "header.php";  // Moved to the top, before any logic
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$role = $_SESSION['role'];
$isAdmin = $role === 'admin';
$isSuperAdmin = $role === 'super_admin';

// Fetch assigned companies for admin/user
$assignedCompanyIds = [];
if (!$isSuperAdmin) {
    $ucStmt = $conn->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
    $ucStmt->bind_param("i", $_SESSION['user_id']);
    $ucStmt->execute();
    $ucResult = $ucStmt->get_result();
    while ($ucRow = $ucResult->fetch_assoc()) {
        $assignedCompanyIds[] = $ucRow['company_id'];
    }
    $ucStmt->close();
}

// Check if an expense ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: expenses.php");
    exit();
}

$expense_id = intval($_GET['id']);

// Fetch the expense details for confirmation
$query = "
    SELECT 
        e.expense_id,
        e.expense_or_number,
        e.expense_date,
        c.company_name,
        p.payee_name,
        cat.category_name,
        e.expense_service_charge,
        e.expense_services,
        e.expense_capital_goods,
        e.expense_goods_other_than_capital,
        e.expense_exempt,
        e.expense_zero_rated,
        e.expense_vat_rate,
        e.expense_total_input_tax,
        e.expense_total_purchases,
        e.expense_total_receipt_amount,
        e.expense_taxable_net_vat,
e.expense_company_id,
        e.expense_remarks,
        e.expense_created_by
    FROM expenses e
    INNER JOIN companies c ON e.expense_company_id = c.company_id
    INNER JOIN payees p ON e.expense_payee_id = p.payee_id
    INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
    WHERE e.expense_id = $expense_id
";
$result = mysqli_query($conn, $query);

// If no expense found, redirect
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: expenses.php");
    exit();
}

$expense = mysqli_fetch_assoc($result);

// Company scope guard
if (!$isSuperAdmin) {
    if (!in_array($expense['expense_company_id'], $assignedCompanyIds)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'You are not authorized to delete this record.'];
        header("Location: expenses.php");
        exit();
    }
}

if (!$isSuperAdmin && !$isAdmin && intval($expense['expense_created_by']) !== intval($_SESSION['user_id'])) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'You are not authorized to delete this record.'];
    header("Location: expenses.php");
    exit();
}

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM expenses WHERE expense_id = $expense_id";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $payeeName = mysqli_real_escape_string($conn, $expense['payee_name']);
        $companyName = mysqli_real_escape_string($conn, $expense['company_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Expense deleted', '$username', 'Payee: $payeeName, Company: $companyName (Expense ID: $expense_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Expense deleted successfully!";
        header("Location: expenses.php");
        exit();
    } else {
        $_SESSION['alert'] = "error";
    }
}

// Alert messages
$alert = $_SESSION['alert'] ?? null;
$alertType = 'info';
$alertMessage = '';
if (is_array($alert)) {
    $alertType = $alert['type'] ?? 'info';
    $alertMessage = $alert['message'] ?? '';
} else {
    $alertMessage = $alert ?? '';
}
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css" />

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <?php if ($alertMessage): ?>
                <div class="alert alert-<?= $alertType === 'error' ? 'danger' : $alertType ?>">
                    <?= htmlspecialchars($alertMessage) ?>
                </div>
                <?php endif; ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-trash"></i></span>
                        <h5>Delete Expense Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following expense?</p>

                        <!-- Display expense details in a table (similar to form layout in expense_new.php) -->
                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($expense['expense_id']) ?></td>
                            </tr>
                            <tr>
                                <th>OR Number</th>
                                <td><?= htmlspecialchars($expense['expense_or_number']) ?></td>
                            </tr>
                            <tr>
                                <th>Date</th>
                                <td><?= date('M d, Y', strtotime($expense['expense_date'])) ?></td>
                            </tr>
                            <tr>
                                <th>Company</th>
                                <td><?= htmlspecialchars($expense['company_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Payee</th>
                                <td><?= htmlspecialchars($expense['payee_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td><?= htmlspecialchars($expense['category_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Service Charge</th>
                                <td>₱<?= number_format($expense['expense_service_charge'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Services</th>
                                <td>₱<?= number_format($expense['expense_services'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Capital Goods</th>
                                <td>₱<?= number_format($expense['expense_capital_goods'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Goods Other Than Capital</th>
                                <td>₱<?= number_format($expense['expense_goods_other_than_capital'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Exempt</th>
                                <td>₱<?= number_format($expense['expense_exempt'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Zero Rated</th>
                                <td>₱<?= number_format($expense['expense_zero_rated'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>VAT Rate (%)</th>
                                <td><?= htmlspecialchars($expense['expense_vat_rate']) ?>%</td>
                            </tr>
                            <tr>
                                <th>Total Purchases</th>
                                <td>₱<?= number_format($expense['expense_total_purchases'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Taxable (Net of VAT)</th>
                                <td>₱<?= number_format($expense['expense_taxable_net_vat'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Total Input Tax</th>
                                <td>₱<?= number_format($expense['expense_total_input_tax'], 2) ?></td>
                            </tr>
                            <tr style="font-weight:bold; font-size:16px;">
                                <th>Total Receipt Amount</th>
                                <td>₱<?= number_format($expense['expense_total_receipt_amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <th>Remarks</th>
                                <td><?= htmlspecialchars($expense['expense_remarks'] ?? '-') ?></td>
                            </tr>
                        </table>

                        <!-- Delete confirmation form (similar to submit button in expense_new.php) -->
                        <form action="" method="post" style="margin-top: 20px;">
                            <div class="form-actions action-buttons">
                                <button type="submit" name="confirm_delete" class="btn btn-danger">Confirm Delete</button>
                                <a href="expenses.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>