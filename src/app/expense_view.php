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

// Validate expense ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Expense ID.</div>";
    exit();
}

$expense_id = intval($_GET['id']);

// Fetch expense details
$query = "
    SELECT e.*,
           e.expense_created_by,
           c.company_name,
           c.company_tin,
           p.payee_name, 
           p.payee_tin,
           cat.category_name,
           r.reseller_name,
           eu.end_user_name,
           pr.product_name
    FROM expenses e
    INNER JOIN companies c ON e.expense_company_id = c.company_id
    INNER JOIN payees p ON e.expense_payee_id = p.payee_id
    INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
    LEFT JOIN resellers r ON e.expense_reseller_id = r.reseller_id
    LEFT JOIN expense_end_users eu ON e.expense_user_id = eu.end_user_id
    LEFT JOIN expense_products pr ON e.expense_product_id = pr.product_id
    WHERE e.expense_id = '$expense_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>Expense record not found.</div>";
    exit();
}

$expense = mysqli_fetch_assoc($result);

// Company scope guard
if (!$isSuperAdmin) {
    if (!in_array($expense['expense_company_id'], $assignedCompanyIds)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'You are not authorized to view this record.'];
        header("Location: expenses.php");
        exit();
    }
}
?>

<link rel="stylesheet" href="css/layout.css" />

<div id="content">

    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- View Expense -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">

                        <h5>Expense Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <?php if ($isSuperAdmin || $isAdmin): ?>
                            <!-- Company -->
                            <div class="control-group">
                                <label class="control-label">Company:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['company_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Company TIN -->
                            <div class="control-group">
                                <label class="control-label">Company TIN:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['company_tin']) ?>" disabled>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Payee TIN -->
                            <div class="control-group">
                                <label class="control-label">Payee TIN:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['payee_tin'] ?? 'N/A') ?>" disabled>
                                </div>
                            </div>

                            <!-- Payee -->
                            <div class="control-group">
                                <label class="control-label">Payee:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['payee_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="control-group">
                                <label class="control-label">Category:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['category_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Reseller -->
                            <div class="control-group">
                                <label class="control-label">Reseller:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['reseller_name'] ?? 'None') ?>" disabled>
                                </div>
                            </div>

                            <!-- End User -->
                                <div class="control-group">
                                    <label class="control-label">End User:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" value="<?= htmlspecialchars($expense['end_user_name'] ?? 'None') ?>" disabled>
                                    </div>
                                </div>

                                <!-- Product -->
                                <div class="control-group">
                                    <label class="control-label">Product:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" value="<?= htmlspecialchars($expense['product_name'] ?? 'None') ?>" disabled>
                                    </div>
                                </div>

                            <!-- OR Number -->
                            <div class="control-group">
                                <label class="control-label">OR Number:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['expense_or_number']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Date -->
                            <div class="control-group">
                                <label class="control-label">Date:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= date('M d, Y', strtotime($expense['expense_date'])) ?>" disabled>
                                </div>
                            </div>

                            <!-- ===== New Fields Added Below ===== -->

                            <!-- Service Charge -->
                            <div class="control-group">
                                <label class="control-label">Service Charge:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_service_charge'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- Services -->
                            <div class="control-group">
                                <label class="control-label">Services:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_services'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- Capital Goods -->
                            <div class="control-group">
                                <label class="control-label">Capital Goods:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_capital_goods'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- Goods Other than Capital Goods -->
                            <div class="control-group">
                                <label class="control-label">Goods Other than Capital Goods:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_goods_other_than_capital'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- Exempt -->
                            <div class="control-group">
                                <label class="control-label">Exempt:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_exempt'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- VAT Rate -->
                            <div class="control-group">
                                <label class="control-label">VAT Rate (%):</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($expense['expense_vat_rate']) ?>" disabled>
                                </div>
                            </div>

                            <!-- ===== End of Added Fields ===== -->

                            <!-- Total Purchases -->
                            <div class="control-group">
                                <label class="control-label">Total Purchases:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_total_purchases'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- Taxable (Net of VAT) -->
                            <div class="control-group">
                                <label class="control-label">Taxable (Net of VAT):</label>
                                <div class="controls">
                                    <input type="text" class="span11" 
                                        value="<?= number_format($expense['expense_taxable_net_vat'], 2) ?>" 
                                        disabled>
                                </div>
                            </div>

                            <!-- Total Input Tax -->
                            <div class="control-group">
                                <label class="control-label">Total Input Tax:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_total_input_tax'], 2) ?>" disabled>
                                </div>
                            </div>

                            <!-- Total Receipt Amount -->
                            <div class="control-group">
                                <label class="control-label" style="font-weight:bold;">Total Receipt Amount:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= number_format($expense['expense_total_receipt_amount'], 2) ?>" disabled style="font-weight:bold; font-size:16px;">
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="control-group">
                                <label class="control-label">Remarks:</label>
                                <div class="controls">
                                    <textarea class="span11" disabled><?= htmlspecialchars($expense['expense_remarks']) ?></textarea>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <?php if ($isSuperAdmin || $isAdmin || intval($expense['expense_created_by']) === intval($_SESSION['user_id'])): ?>
                                <a href="expense_edit.php?id=<?= $expense['expense_id'] ?>" class="btn btn-primary">Edit Expense</a>
                                <a href="expense_delete.php?id=<?= $expense['expense_id'] ?>" class="btn btn-danger">Delete Expense</a>
                                <?php endif; ?>
                                <a href="expenses.php" class="btn btn-secondary">Back</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
