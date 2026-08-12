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

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: expenses.php");
    exit();
}

$expense_id = intval($_GET['id']);

// Fetch dropdown data
if ($isSuperAdmin) {
    $companiesResult = $conn->query("SELECT company_id, company_name, company_tin FROM companies ORDER BY company_name ASC");
} else {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $compStmt = $conn->prepare("SELECT company_id, company_name, company_tin FROM companies WHERE company_id IN ($placeholders) ORDER BY company_name ASC");
    $compStmt->bind_param(str_repeat('i', count($assignedCompanyIds)), ...$assignedCompanyIds);
    $compStmt->execute();
    $companiesResult = $compStmt->get_result();
}

$companyRows = [];
while ($row = $companiesResult->fetch_assoc()) {
    $companyRows[] = $row;
}
$payees = mysqli_query($conn, "SELECT payee_id, payee_name FROM payees ORDER BY payee_name ASC");
$categories = mysqli_query($conn, "SELECT category_id, category_name FROM expense_categories ORDER BY category_name ASC");
$resellers = mysqli_query($conn, "SELECT reseller_id, reseller_name FROM resellers ORDER BY reseller_name ASC");
$end_users = mysqli_query($conn, "SELECT end_user_id, end_user_name FROM expense_end_users ORDER BY end_user_name ASC");
$products = mysqli_query($conn, "SELECT product_id, product_name FROM expense_products ORDER BY product_name ASC");

// Fetch current expense record
$expense_query = mysqli_query($conn, "
    SELECT e.*, e.expense_created_by, c.company_tin
    FROM expenses e
    LEFT JOIN companies c ON e.expense_company_id = c.company_id
    WHERE e.expense_id = '$expense_id'
    LIMIT 1
");
if (mysqli_num_rows($expense_query) === 0) {
    echo "<div class='alert alert-danger'>Expense record not found.</div>";
    exit();
}
$expense = mysqli_fetch_assoc($expense_query);

// Company scope guard
if (!$isSuperAdmin) {
    if (!in_array($expense['expense_company_id'], $assignedCompanyIds)) {
        $_SESSION['alert'] = ['type' => 'error', 'message' => 'You are not authorized to edit this record.'];
        header("Location: expenses.php");
        exit();
    }
}

$preselectedCompany = count($companyRows) === 1 ? $companyRows[0]['company_id'] : $expense['expense_company_id'];

if (!$isSuperAdmin && !$isAdmin && intval($expense['expense_created_by']) !== intval($_SESSION['user_id'])) {
    $_SESSION['alert'] = ['type' => 'error', 'message' => 'You are not authorized to edit this record.'];
    header("Location: expenses.php");
    exit();
}

// Handle update form submission
if (isset($_POST['update_expense'])) {
    if ($isSuperAdmin) {
        $company_id = intval($_POST['expense_company_id']);
    } elseif ($isAdmin) {
        $submittedCompanyId = intval($_POST['expense_company_id']);
        if (in_array($submittedCompanyId, $assignedCompanyIds)) {
            $company_id = $submittedCompanyId;
        } else {
            $company_id = intval($expense['expense_company_id']);
        }
    } else {
        // Regular user: always forced to their assigned company, ignore submitted value
        $company_id = !empty($assignedCompanyIds) ? intval($assignedCompanyIds[0]) : intval($expense['expense_company_id']);
    }
    $payee_id = mysqli_real_escape_string($conn, $_POST['expense_payee_id']);
    $category_id = mysqli_real_escape_string($conn, $_POST['expense_category_id']);
    $reseller_id = !empty($_POST['expense_reseller_id']) ? mysqli_real_escape_string($conn, $_POST['expense_reseller_id']) : NULL;
    $or_number = mysqli_real_escape_string($conn, $_POST['expense_or_number']);
    $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    $remarks = mysqli_real_escape_string($conn, $_POST['expense_remarks']);

    // Core numeric fields
    $service_charge = floatval($_POST['expense_service_charge']);
    $services = floatval($_POST['expense_services']);
    $capital_goods = floatval($_POST['expense_capital_goods']);
    $goods_other = floatval($_POST['expense_goods_other_than_capital']);
    $exempt = floatval($_POST['expense_exempt']);
    $zero_rated = 0; // merged into exempt
    $vat_rate = floatval($_POST['expense_vat_rate']);
    $gross_taxable = 0; // no longer collected from form, retained for schema compatibility

    // ===== Sum-based calculation, recalculated server-side to prevent tampering =====
    $total_purchases = $services + $capital_goods + $goods_other;
    $taxable_net_vat = $total_purchases - $exempt;
    if ($taxable_net_vat < 0) $taxable_net_vat = 0;
    $total_input_tax = round($taxable_net_vat * ($vat_rate / 100), 2);
    $total_receipt_amount = round(
        $taxable_net_vat + $total_input_tax + $service_charge + $exempt,
        2
    );

    // Update query
    $query = "
    UPDATE expenses SET
        expense_company_id = '$company_id',
        expense_payee_id = '$payee_id',
        expense_category_id = '$category_id',
        expense_reseller_id = " . ($reseller_id ? "'$reseller_id'" : "NULL") . ",
        expense_user_id = " . (!empty($_POST['expense_user_id']) ? "'".mysqli_real_escape_string($conn, $_POST['expense_user_id'])."'" : "NULL") . ",
        expense_product_id = " . (!empty($_POST['expense_product_id']) ? "'".mysqli_real_escape_string($conn, $_POST['expense_product_id'])."'" : "NULL") . ",
        expense_or_number = '$or_number',
        expense_date = '$expense_date',
        expense_service_charge = '$service_charge',
        expense_services = '$services',
        expense_capital_goods = '$capital_goods',
        expense_goods_other_than_capital = '$goods_other',
        expense_exempt = '$exempt',
        expense_zero_rated = '$zero_rated',
        expense_taxable_net_vat = '$taxable_net_vat',
        expense_vat_rate = '$vat_rate',
        expense_total_purchases = '$total_purchases',
        expense_total_input_tax = '$total_input_tax',
        expense_total_receipt_amount = '$total_receipt_amount',
        expense_remarks = '$remarks',
        expense_updated_at = NOW()
    WHERE expense_id = '$expense_id'
";

    if (mysqli_query($conn, $query)) {
    $username = mysqli_real_escape_string($conn, $_SESSION['username']);

    // Fetch payee name and company name for a readable log entry
    $logDetailsStmt = $conn->prepare("
        SELECT p.payee_name, c.company_name
        FROM payees p, companies c
        WHERE p.payee_id = ? AND c.company_id = ?
    ");
    $logDetailsStmt->bind_param("ii", $payee_id, $company_id);
    $logDetailsStmt->execute();
    $logDetailsRow = $logDetailsStmt->get_result()->fetch_assoc();
    $logDetailsStmt->close();

    $payeeName = mysqli_real_escape_string($conn, $logDetailsRow['payee_name'] ?? 'Unknown Payee');
    $companyName = mysqli_real_escape_string($conn, $logDetailsRow['company_name'] ?? 'Unknown Company');

    $logQuery = "
        INSERT INTO logs (log_action, log_user, log_details, log_date)
        VALUES ('Expense updated', '$username', 'Payee: $payeeName, Company: $companyName (Expense ID: $expense_id)', NOW())
    ";
    mysqli_query($conn, $logQuery);

    $_SESSION['alert'] = "Expense updated successfully!";
    header("Location: expenses.php");
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
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <?php if ($alert == "Expense updated successfully!") { ?>
                    <div class="alert alert-success">Expense updated successfully!</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update expense.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit Expense Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <?php if ($isSuperAdmin || $isAdmin): ?>
                            <!-- Company -->
                            <div class="control-group">
                                <label class="control-label">Company:</label>
                                <div class="controls">
                                    <select name="expense_company_id" id="companySelect" class="span11" required>
                                        <?php foreach ($companyRows as $row): ?>
                                            <option
                                                value="<?= $row['company_id'] ?>"
                                                data-tin="<?= htmlspecialchars($row['company_tin'] ?? '') ?>"
                                                <?= $row['company_id'] == $preselectedCompany ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($row['company_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Company TIN -->
                            <div class="control-group">
                                <label class="control-label">Company TIN:</label>
                                <div class="controls">
                                    <input type="text" class="span11" id="companyTin" value="<?= htmlspecialchars($expense['company_tin'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- User role: company auto-assigned silently -->
                            <input type="hidden" name="expense_company_id" value="<?= htmlspecialchars($expense['expense_company_id']) ?>">
                            <?php endif; ?>

                            <!-- Payee TIN -->
                            <div class="control-group">
                                <label class="control-label">Payee TIN:</label>
                                <div class="controls">
                                    <input type="text" class="span11" id="payeeTinDisplay" readonly placeholder="N/A">
                                </div>
                            </div>

                            <!-- Payee -->
                            <div class="control-group">
                                <label class="control-label">Payee:</label>
                                <div class="controls">
                                    <select name="expense_payee_id" class="span11" required>
                                        <?php while ($row = mysqli_fetch_assoc($payees)) { ?>
                                            <option value="<?= $row['payee_id'] ?>" <?= $row['payee_id'] == $expense['expense_payee_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['payee_name']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="control-group">
                                <label class="control-label">Category:</label>
                                <div class="controls">
                                    <select name="expense_category_id" class="span11" required>
                                        <?php while ($row = mysqli_fetch_assoc($categories)) { ?>
                                            <option value="<?= $row['category_id'] ?>" <?= $row['category_id'] == $expense['expense_category_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['category_name']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Reseller -->
                            <div class="control-group">
                                <label class="control-label">Reseller:</label>
                                <div class="controls">
                                    <select name="expense_reseller_id" class="span11">
                                        <option value="">None</option>
                                        <?php while ($row = mysqli_fetch_assoc($resellers)) { ?>
                                            <option value="<?= $row['reseller_id'] ?>" <?= $row['reseller_id'] == $expense['expense_reseller_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['reseller_name']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- End User -->
                            <div class="control-group">
                                <label class="control-label">End User:</label>
                                <div class="controls">
                                    <select name="expense_user_id" class="span11">
                                        <option value="">None</option>
                                        <?php while ($row = mysqli_fetch_assoc($end_users)) { ?>
                                            <option value="<?= $row['end_user_id'] ?>" <?= $row['end_user_id'] == $expense['expense_user_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['end_user_name']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Product -->
                            <div class="control-group">
                                <label class="control-label">Product:</label>
                                <div class="controls">
                                    <select name="expense_product_id" class="span11">
                                        <option value="">None</option>
                                        <?php while ($row = mysqli_fetch_assoc($products)) { ?>
                                            <option value="<?= $row['product_id'] ?>" <?= $row['product_id'] == $expense['expense_product_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['product_name']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <!-- OR Number -->
                            <div class="control-group">
                                <label class="control-label">OR Number:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="expense_or_number"
                                           value="<?= htmlspecialchars($expense['expense_or_number']) ?>" required>
                                </div>
                            </div>

                            <!-- Date -->
                            <div class="control-group">
                                <label class="control-label">Date:</label>
                                <div class="controls">
                                    <input type="date" class="span11" name="expense_date"
                                           value="<?= htmlspecialchars($expense['expense_date']) ?>" required>
                                </div>
                            </div>

                            <!-- ===== Editable Numeric Fields ===== -->

                            <div class="control-group">
                                <label class="control-label">Service Charge:</label>
                                <div class="controls">
                                    <input type="number" step="0.01" class="span11 calc-field" name="expense_service_charge"
                                           value="<?= $expense['expense_service_charge'] == '0.00' ? '' : htmlspecialchars($expense['expense_service_charge']) ?>" placeholder="0.00">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Services:</label>
                                <div class="controls">
                                    <input type="number" step="0.01" class="span11 calc-field" name="expense_services"
                                           value="<?= $expense['expense_services'] == '0.00' ? '' : htmlspecialchars($expense['expense_services']) ?>" placeholder="0.00">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Capital Goods:</label>
                                <div class="controls">
                                    <input type="number" step="0.01" class="span11 calc-field" name="expense_capital_goods"
                                           value="<?= $expense['expense_capital_goods'] == '0.00' ? '' : htmlspecialchars($expense['expense_capital_goods']) ?>" placeholder="0.00">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Goods Other than Capital Goods:</label>
                                <div class="controls">
                                    <input type="number" step="0.01" class="span11 calc-field" name="expense_goods_other_than_capital"
                                           value="<?= $expense['expense_goods_other_than_capital'] == '0.00' ? '' : htmlspecialchars($expense['expense_goods_other_than_capital']) ?>" placeholder="0.00">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Exempt / Zero Rated:</label>
                                <div class="controls">
                                    <input type="number" step="0.01" class="span11 calc-field" name="expense_exempt"
                                           value="<?= $expense['expense_exempt'] == '0.00' ? '' : htmlspecialchars($expense['expense_exempt']) ?>" placeholder="0.00">
                                </div>
                            </div>

                            <!-- Zero Rated removed (merged into Exempt) -->
                            <div class="control-group">
                                <label class="control-label">VAT:</label>
                                <div class="controls">
                                    <select name="expense_vat_rate" id="vatRateInput" class="span11 calc-field">
                                        <option value="12" <?= $expense['expense_vat_rate'] == '12.00' ? 'selected' : '' ?>>VAT Applicable (12%)</option>
                                        <option value="0" <?= $expense['expense_vat_rate'] == '0.00' ? 'selected' : '' ?>>VAT Exempt / Zero Rated (0%)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- ===== Calculated Fields ===== -->

                            <div class="control-group">
                                <label class="control-label">Total Purchases:</label>
                                <div class="controls">
                                    <input type="number" class="span11" id="expense_total_purchases" readonly
                                           value="<?= htmlspecialchars($expense['expense_total_purchases']) ?>">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Taxable (Net of VAT):</label>
                                <div class="controls">
                                    <input type="number" class="span11" id="expense_taxable_net_vat" readonly
                                        value="<?= htmlspecialchars($expense['expense_taxable_net_vat']) ?>">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Total Input Tax:</label>
                                <div class="controls">
                                    <input type="number" class="span11" id="expense_total_input_tax" readonly
                                           value="<?= htmlspecialchars($expense['expense_total_input_tax']) ?>">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label" style="font-weight:bold;">Total Receipt Amount:</label>
                                <div class="controls">
                                    <input type="number" class="span11" id="expense_total_receipt_amount" readonly
                                           value="<?= htmlspecialchars($expense['expense_total_receipt_amount']) ?>" style="font-weight:bold; font-size:16px;">
                                </div>
                            </div>

                            <!-- JS Calculation Logic -->
                            <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                const fields = document.querySelectorAll(".calc-field");

                                // Get references to calculated fields
                                const totalPurchasesInput = document.getElementById("expense_total_purchases");
                                const inputTaxInput = document.getElementById("expense_total_input_tax");
                                const totalReceiptInput = document.getElementById("expense_total_receipt_amount");
                                const taxableNetVatInput = document.getElementById("expense_taxable_net_vat");

                                function recalc() {
                                    let serviceCharge = parseFloat(document.querySelector("input[name='expense_service_charge']").value) || 0;
                                    let services = parseFloat(document.querySelector("input[name='expense_services']").value) || 0;
                                    let capitalGoods = parseFloat(document.querySelector("input[name='expense_capital_goods']").value) || 0;
                                    let goodsOther = parseFloat(document.querySelector("input[name='expense_goods_other_than_capital']").value) || 0;
                                    let exempt = parseFloat(document.querySelector("input[name='expense_exempt']").value) || 0;
                                    let vatRate = parseFloat(document.querySelector("select[name='expense_vat_rate']").value) || 0;

                                    // SUM LOGIC: Total Purchases = Services + Capital Goods + Goods Other Than Capital
                                    let totalPurchases = services + capitalGoods + goodsOther;

                                    // TAXABLE NET
                                    let taxableNetVat = totalPurchases - exempt;
                                    if (taxableNetVat < 0) taxableNetVat = 0;

                                    // VAT
                                    let inputTax = taxableNetVat * (vatRate / 100);

                                    // TOTAL RECEIPT
                                    let totalReceipt = taxableNetVat + inputTax + serviceCharge + exempt;

                                    totalPurchasesInput.value = totalPurchases.toFixed(2);
                                    inputTaxInput.value = inputTax.toFixed(2);
                                    totalReceiptInput.value = totalReceipt.toFixed(2);
                                    taxableNetVatInput.value = taxableNetVat.toFixed(2);
                                }

                                // Run recalculation when any field changes
                                fields.forEach(field => field.addEventListener("input", recalc));

                                // Initialize on page load
                                recalc();

                               // Sync company TIN on dropdown change
                                const companySelect = document.getElementById('companySelect');
                                if (companySelect) {
                                    companySelect.addEventListener('change', function() {
                                        const tin = this.options[this.selectedIndex].getAttribute('data-tin') || '';
                                        document.getElementById('companyTin').value = tin;
                                    });
                                }
                            });
                            </script>

                            <script>
                            // Payee TIN display is informational only on edit (legacy data has no data-tin on payee options here)
                            // Left as a static readonly field; populate manually if payee_tin is added to the payee select's data attributes in the future.
                            </script>

                            <!-- Remarks -->
                            <div class="control-group">
                                <label class="control-label">Remarks:</label>
                                <div class="controls">
                                    <textarea name="expense_remarks" class="span11" placeholder="Enter remarks (optional)"><?= htmlspecialchars($expense['expense_remarks']) ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_expense" class="btn btn-success">Update Expense</button>
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
