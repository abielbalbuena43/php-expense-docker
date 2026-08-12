<?php
ob_start();
session_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

include "connection.php";  // Assuming this is your database connection file
if (!isset($_GET['export'])) {
    include "header.php";  // UI only, not for Excel export
}

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

// Fetch data for dropdowns
if ($isSuperAdmin) {
    $companiesResult = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC");
} else {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $compStmt = $conn->prepare("SELECT company_id, company_name FROM companies WHERE company_id IN ($placeholders) ORDER BY company_name ASC");
    $compStmt->bind_param(str_repeat('i', count($assignedCompanyIds)), ...$assignedCompanyIds);
    $compStmt->execute();
    $companiesResult = $compStmt->get_result();
}

$categoriesQuery = "SELECT category_id, category_name FROM expense_categories ORDER BY category_name ASC";
$categoriesResult = mysqli_query($conn, $categoriesQuery);

$productsQuery = "SELECT product_id, product_name FROM expense_products ORDER BY product_name ASC";
$productsResult = mysqli_query($conn, $productsQuery);

// Handle the export request
if (isset($_GET['export']) && $_GET['export'] == 1) {
    // Get filters from the form
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    $company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
    $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $payee_id = isset($_GET['payee_id']) ? intval($_GET['payee_id']) : 0;

    // Validate dates
    if (empty($start_date) || empty($end_date)) {
        die("Please provide both start and end dates for filtering.");
    }
    if (strtotime($start_date) > strtotime($end_date)) {
        die("Start date must be before end date.");
    }

    // Build the query with filters - now including more detailed fields for richer export
    $query = "
        SELECT 
            e.expense_id,
            e.expense_or_number,
            e.expense_date,
            e.expense_remarks,
            e.expense_service_charge,
            e.expense_services,
            e.expense_capital_goods,
            e.expense_goods_other_than_capital,
            e.expense_exempt,
            e.expense_zero_rated,
            e.expense_vat_rate,
            e.expense_total_input_tax,
            e.expense_total_purchases,
            e.expense_taxable_net_vat,
            e.expense_total_receipt_amount,
            c.company_name,
            p.payee_name,
            cat.category_name
        FROM expenses e
        INNER JOIN companies c ON e.expense_company_id = c.company_id
        INNER JOIN payees p ON e.expense_payee_id = p.payee_id
        INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
        WHERE e.expense_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    $types = "ss";

    if ($company_id > 0) {
        $query .= " AND e.expense_company_id = ?";
        $params[] = $company_id;
        $types .= "i";
    }
    if ($category_id > 0) {
        $query .= " AND e.expense_category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    if ($product_id > 0) {
        $query .= " AND e.expense_product_id = ?";
        $params[] = $product_id;
        $types .= "i";
    }
    if ($payee_id > 0) {
        $query .= " AND e.expense_payee_id = ?";
        $params[] = $payee_id;
        $types .= "i";
    }

    if (!$isSuperAdmin) {
        if (empty($assignedCompanyIds)) {
            $query .= " AND 1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
            $query .= " AND e.expense_company_id IN ($placeholders)";
            foreach ($assignedCompanyIds as $cid) {
                $params[] = $cid;
                $types .= "i";
            }
        }
    }

    $query .= " ORDER BY e.expense_date DESC, e.expense_id DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Calculate summary totals for export (include in a separate section)
    $totalExpenses = 0;
    $totalAmount = 0.00;
    $categoryTotals = [];
    $companyTotals = [];
    $data = []; // Store rows for summary calc and output

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $totalExpenses++;
        $totalAmount += $row['expense_total_receipt_amount'];
        
        $cat = $row['category_name'];
        if (!isset($categoryTotals[$cat])) {
            $categoryTotals[$cat] = 0.00;
        }
        $categoryTotals[$cat] += $row['expense_total_receipt_amount'];
        
        $comp = $row['company_name'];
        if (!isset($companyTotals[$comp])) {
            $companyTotals[$comp] = 0.00;
        }
        $companyTotals[$comp] += $row['expense_total_receipt_amount'];
    }

    // PhpSpreadsheet export
    require_once '../vendor/autoload.php';

    $spreadsheet = new Spreadsheet();

    // Sheet 1: Summary
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Summary');
    $sheet1->setCellValue('A1', 'Expense Report Summary');
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet1->mergeCells('A1:B1');
    $sheet1->setCellValue('A3', 'Period:');
    $sheet1->setCellValue('B3', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)));
    $sheet1->setCellValue('A4', 'Total Records:');
    $sheet1->setCellValue('B4', $totalExpenses);
    $sheet1->setCellValue('A5', 'Total Amount:');
    $sheet1->setCellValue('B5', $totalAmount);
    $sheet1->getStyle('B5')->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet1->getStyle('A3:A5')->getFont()->setBold(true);

    $row = 7;
    if (!empty($categoryTotals)) {
        $sheet1->setCellValue('A' . $row, 'By Category');
        $sheet1->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($categoryTotals as $cat => $amt) {
            $sheet1->setCellValue('A' . $row, $cat);
            $sheet1->setCellValue('B' . $row, $amt);
            $sheet1->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }
        $row++;
    }
    if (!empty($companyTotals)) {
        $sheet1->setCellValue('A' . $row, 'By Company');
        $sheet1->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($companyTotals as $comp => $amt) {
            $sheet1->setCellValue('A' . $row, $comp);
            $sheet1->setCellValue('B' . $row, $amt);
            $sheet1->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }
    }
    $sheet1->getColumnDimension('A')->setAutoSize(true);
    $sheet1->getColumnDimension('B')->setAutoSize(true);

    // Sheet 2: Expense Details
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Expense Details');

    $headers = [
        'Expense ID', 'OR Number', 'Date', 'Remarks',
        'Service Charge', 'Services', 'Capital Goods',
        'Goods Other Than Capital', 'Exempt', 'Zero Rated',
        'VAT Rate (%)', 'Total Input Tax', 'Total Purchases',
        'Taxable Net VAT', 'Total Receipt Amount',
        'Company', 'Payee', 'Category'
    ];

    $col = 'A';
    foreach ($headers as $header) {
        $sheet2->setCellValue($col . '1', $header);
        $col++;
    }

    $sheet2->getStyle('A1:R1')->getFont()->setBold(true);
    $sheet2->getStyle('A1:R1')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('A9A9A9');
    $sheet2->getStyle('A1:R1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum = 2;
    foreach ($data as $expRow) {
        $sheet2->setCellValue('A' . $rowNum, $expRow['expense_id']);
        $sheet2->setCellValue('B' . $rowNum, $expRow['expense_or_number']);
        $sheet2->setCellValue('C' . $rowNum, date('M d, Y', strtotime($expRow['expense_date'])));
        $sheet2->setCellValue('D' . $rowNum, $expRow['expense_remarks']);
        $sheet2->setCellValue('E' . $rowNum, $expRow['expense_service_charge']);
        $sheet2->setCellValue('F' . $rowNum, $expRow['expense_services']);
        $sheet2->setCellValue('G' . $rowNum, $expRow['expense_capital_goods']);
        $sheet2->setCellValue('H' . $rowNum, $expRow['expense_goods_other_than_capital']);
        $sheet2->setCellValue('I' . $rowNum, $expRow['expense_exempt']);
        $sheet2->setCellValue('J' . $rowNum, $expRow['expense_zero_rated']);
        $sheet2->setCellValue('K' . $rowNum, $expRow['expense_vat_rate']);
        $sheet2->setCellValue('L' . $rowNum, $expRow['expense_total_input_tax']);
        $sheet2->setCellValue('M' . $rowNum, $expRow['expense_total_purchases']);
        $sheet2->setCellValue('N' . $rowNum, $expRow['expense_taxable_net_vat']);
        $sheet2->setCellValue('O' . $rowNum, $expRow['expense_total_receipt_amount']);
        $sheet2->setCellValue('P' . $rowNum, $expRow['company_name']);
        $sheet2->setCellValue('Q' . $rowNum, $expRow['payee_name']);
        $sheet2->setCellValue('R' . $rowNum, $expRow['category_name']);

        foreach (['E','F','G','H','I','J','L','M','N','O'] as $currCol) {
            $sheet2->getStyle($currCol . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $rowNum++;
    }

    foreach (range('A', 'R') as $col) {
        $sheet2->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'expense_report_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    $stmt->close();
    exit();
}

// For display: Get filters and show the report on page (like previous reports.php)
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$payee_id = isset($_GET['payee_id']) ? intval($_GET['payee_id']) : 0;

// Build query for display (basic columns for table, but calculate summary)
$query = "
    SELECT 
        e.expense_id,
        e.expense_or_number,
        e.expense_date,
        e.expense_total_receipt_amount,
        c.company_name,
        p.payee_name,
        cat.category_name
    FROM expenses e
    INNER JOIN companies c ON e.expense_company_id = c.company_id
    INNER JOIN payees p ON e.expense_payee_id = p.payee_id
    INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
    WHERE e.expense_date BETWEEN ? AND ?
";
$params = [$start_date, $end_date];
$types = "ss";

if ($company_id > 0) {
    $query .= " AND e.expense_company_id = ?";
    $params[] = $company_id;
    $types .= "i";
}
if ($category_id > 0) {
    $query .= " AND e.expense_category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}
if ($product_id > 0) {
    $query .= " AND e.expense_product_id = ?";
    $params[] = $product_id;
    $types .= "i";
}
if ($payee_id > 0) {
    $query .= " AND e.expense_payee_id = ?";
    $params[] = $payee_id;
    $types .= "i";
}

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        // No companies assigned - show nothing
        $query .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $query .= " AND e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $params[] = $cid;
            $types .= "i";
        }
    }
}

$query .= " ORDER BY e.expense_date DESC, e.expense_id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$payee_id = isset($_GET['payee_id']) ? intval($_GET['payee_id']) : 0;

// Calculate summary for display
$totalExpenses = 0;
$totalAmount = 0.00;
$categoryTotals = [];
$companyTotals = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $totalExpenses++;
        $totalAmount += $row['expense_total_receipt_amount'];
        
        $cat = $row['category_name'];
        if (!isset($categoryTotals[$cat])) {
            $categoryTotals[$cat] = 0.00;
        }
        $categoryTotals[$cat] += $row['expense_total_receipt_amount'];
        
        $comp = $row['company_name'];
        if (!isset($companyTotals[$comp])) {
            $companyTotals[$comp] = 0.00;
        }
        $companyTotals[$comp] += $row['expense_total_receipt_amount'];
    }
    $result->data_seek(0); // Reset for table display
}

$reportTitle = "Expense Report from " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date));
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <h5><?php echo htmlspecialchars($reportTitle); ?></h5>
                        <div class="buttons" style="float: right;">
                            <a href="javascript:window.print();" class="btn btn-primary btn-mini"><i class="icon-print"></i> Print Report</a>
                            <a href="expenses.php" class="btn btn-secondary btn-mini"><i class="icon-arrow-left"></i> Back to Expenses</a>
                        </div>
                    </div>
                    <div class="widget-content nopadding">
                        <!-- Filter Form -->
                        <div style="padding: 20px; background: #f8f9fa; border-bottom: 1px solid #ddd;">
                            <form method="get" id="filterForm">
                                <label for="start_date" style="margin-right: 10px; font-weight: bold; font-size: 14px;">Start Date:</label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" style="margin-right: 10px; padding: 5px; font-size: 14px;">
                                
                                <label for="end_date" style="margin-right: 10px; font-weight: bold; font-size: 14px;">End Date:</label>
                                <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" style="margin-right: 10px; padding: 5px; font-size: 14px;">
                                
                                <label for="company_id" style="margin-right: 10px; font-weight: bold; font-size: 14px;">Company:</label>
                                <select name="company_id" id="company_id" style="margin-right: 10px; padding: 5px; font-size: 14px;">
                                    <option value="0">All Companies</option>
                                    <?php mysqli_data_seek($companiesResult, 0); while ($row = mysqli_fetch_assoc($companiesResult)) { ?>
                                        <option value="<?php echo $row['company_id']; ?>" <?php if ($row['company_id'] == $company_id) echo 'selected'; ?>><?php echo htmlspecialchars($row['company_name']); ?></option>
                                    <?php } ?>
                                </select>
                                
                                <label for="category_id" style="margin-right: 10px; font-weight: bold; font-size: 14px;">Category:</label>
                                <select name="category_id" id="category_id" style="margin-right: 10px; padding: 5px; font-size: 14px;">
                                    <option value="0">All Categories</option>
                                    <?php mysqli_data_seek($categoriesResult, 0); while ($row = mysqli_fetch_assoc($categoriesResult)) { ?>
                                        <option value="<?php echo $row['category_id']; ?>" <?php if ($row['category_id'] == $category_id) echo 'selected'; ?>><?php echo htmlspecialchars($row['category_name']); ?></option>
                                    <?php } ?>
                                </select>
                                
                                <label for="payee_id" style="margin-right: 10px; font-weight: bold; font-size: 14px;">Payee:</label>
                                <select name="payee_id" id="payee_id" style="margin-right: 10px; padding: 5px; font-size: 14px;">
                                    <option value="0">All Payees</option>
                                    <?php
                                    $payeesResult = $conn->query("SELECT payee_id, payee_name FROM payees ORDER BY payee_name ASC");
                                    while ($row = $payeesResult->fetch_assoc()) { ?>
                                        <option value="<?php echo $row['payee_id']; ?>" <?php if ($row['payee_id'] == $payee_id) echo 'selected'; ?>><?php echo htmlspecialchars($row['payee_name']); ?></option>
                                    <?php } ?>
                                </select>

                                <label for="product_id" style="margin-right: 10px; font-weight: bold; font-size: 14px;">Product:</label>
                                <select name="product_id" id="product_id" style="margin-right: 10px; padding: 5px; font-size: 14px;">
                                    <option value="0">All Products</option>
                                    <?php mysqli_data_seek($productsResult, 0); while ($row = mysqli_fetch_assoc($productsResult)) { ?>
                                        <option value="<?php echo $row['product_id']; ?>" <?php if ($row['product_id'] == $product_id) echo 'selected'; ?>><?php echo htmlspecialchars($row['product_name']); ?></option>
                                    <?php } ?>
                                </select>
                                
                                <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 14px;">Apply Filters</button>
                                <button type="submit" name="export" value="1" class="btn btn-success" style="padding: 5px 10px; font-size: 14px; margin-left: 10px;">Export to Excel</button>
                            </form>
                        </div>

                        <!-- Summary Section -->
                        <div style="padding:20px; background:#f8f9fa; border-bottom:1px solid #ddd;">
                            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:15px;">
                                <div style="background:#fff; border:1px solid #ddd; border-radius:6px; padding:15px 25px; min-width:160px; text-align:center;">
                                    <div style="font-size:12px; color:#666; margin-bottom:4px;">TOTAL RECORDS</div>
                                    <div style="font-size:22px; font-weight:700; color:#333;"><?= $totalExpenses ?></div>
                                </div>
                                <div style="background:#fff; border:1px solid #ddd; border-radius:6px; padding:15px 25px; min-width:160px; text-align:center;">
                                    <div style="font-size:12px; color:#666; margin-bottom:4px;">TOTAL AMOUNT</div>
                                    <div style="font-size:22px; font-weight:700; color:#10b981;">₱<?= number_format($totalAmount, 2) ?></div>
                                </div>
                            </div>
                            <div style="display:flex; gap:30px; flex-wrap:wrap;">
                                <?php if (!empty($categoryTotals)): ?>
                                <div>
                                    <h5 style="margin:0 0 8px 0; font-size:13px; color:#555; text-transform:uppercase;">By Category</h5>
                                    <?php foreach ($categoryTotals as $cat => $amt): ?>
                                        <div style="display:flex; justify-content:space-between; gap:20px; padding:4px 0; border-bottom:1px solid #eee; font-size:13px;">
                                            <span><?= htmlspecialchars($cat) ?></span>
                                            <span style="font-weight:600;">₱<?= number_format($amt, 2) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($companyTotals)): ?>
                                <div>
                                    <h5 style="margin:0 0 8px 0; font-size:13px; color:#555; text-transform:uppercase;">By Company</h5>
                                    <?php foreach ($companyTotals as $comp => $amt): ?>
                                        <div style="display:flex; justify-content:space-between; gap:20px; padding:4px 0; border-bottom:1px solid #eee; font-size:13px;">
                                            <span><?= htmlspecialchars($comp) ?></span>
                                            <span style="font-weight:600;">₱<?= number_format($amt, 2) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Expenses Table -->
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>OR Number</th>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th>Payee</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['expense_id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['expense_or_number']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($row['expense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['payee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                            <td>₱<?php echo number_format($row['expense_total_receipt_amount'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding: 20px; font-size: 14px;">
                                            No expenses found for the selected period.
                                        </td>
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<?php
if (!isset($_GET['export'])) {
    include "footer.php";
}
?>


<script>
$(document).ready(function() {
    $('#reportTable').DataTable({
        "scrollX": true,
        "pageLength": 50,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "dom": 'Bfrtip',
        "buttons": []
    });
});
</script>