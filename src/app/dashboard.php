<?php
session_start();
include "connection.php";
include "header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ---------- Metrics ----------
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = intval($_GET['year'] ?? date('Y'));
$isAnnual = ($selectedMonth === 'annual');
$selectedCompanyId = intval($_GET['company_id'] ?? 0);

// Chart breakdown selection
$breakdown = $_GET['breakdown'] ?? 'end_user';
$validBreakdowns = ['end_user', 'category', 'reseller', 'payee', 'company'];
if (!in_array($breakdown, $validBreakdowns)) {
    $breakdown = 'end_user';
}

// Role and company scope
$role = $_SESSION['role'] ?? 'user';
$isSuperAdmin = $role === 'super_admin';
$isAdmin = $role === 'admin';
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

// Fetch companies for filter dropdown
if ($isSuperAdmin) {
    $companiesForFilter = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC");
} elseif (!empty($assignedCompanyIds)) {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $cfStmt = $conn->prepare("SELECT company_id, company_name FROM companies WHERE company_id IN ($placeholders) ORDER BY company_name ASC");
    $cfStmt->bind_param(str_repeat('i', count($assignedCompanyIds)), ...$assignedCompanyIds);
    $cfStmt->execute();
    $companiesForFilter = $cfStmt->get_result();
} else {
    $companiesForFilter = false;
}
$companiesForFilterRows = [];
if ($companiesForFilter) {
    while ($cfRow = $companiesForFilter->fetch_assoc()) {
        $companiesForFilterRows[] = $cfRow;
    }
}

// If a specific company is selected, override assigned company scoping
if ($selectedCompanyId > 0) {
    // Validate selected company is within scope
    $validCompany = $isSuperAdmin || in_array($selectedCompanyId, $assignedCompanyIds);
    if (!$validCompany) $selectedCompanyId = 0;
}

$expenseWhereClause = $isAnnual
    ? "YEAR(expense_date) = ?"
    : "MONTH(expense_date) = ? AND YEAR(expense_date) = ?";

$expenseWhereTypes = $isAnnual ? "i" : "ii";
$expenseWhereParams = $isAnnual
    ? [$selectedYear]
    : [intval($selectedMonth), $selectedYear];

// Total Expenses (Selected Month/Year) - Prepared for security
$totalExpensesWhere = $expenseWhereClause;
$totalExpensesTypes = $expenseWhereTypes;
$totalExpensesParams = $expenseWhereParams;

if ($selectedCompanyId > 0) {
    $totalExpensesWhere .= " AND expense_company_id = ?";
    $totalExpensesParams[] = $selectedCompanyId;
    $totalExpensesTypes .= "i";
} elseif (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $totalExpensesWhere .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $totalExpensesWhere .= " AND expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $totalExpensesParams[] = $cid;
            $totalExpensesTypes .= "i";
        }
    }
}

$totalExpensesStmt = $conn->prepare("
    SELECT COALESCE(SUM(expense_total_receipt_amount), 0) AS total
    FROM expenses
    WHERE $totalExpensesWhere
");
$totalExpensesStmt->bind_param($totalExpensesTypes, ...$totalExpensesParams);
$totalExpensesStmt->execute();
$totalExpensesResult = $totalExpensesStmt->get_result();
$totalExpensesRow = $totalExpensesResult->fetch_assoc();
$totalExpenses = floatval($totalExpensesRow['total'] ?? 0);
$totalExpensesStmt->close();

// Top Expense Category (Selected Month/Year) - Prepared
$topCategoryWhere = str_replace("expense_date", "e.expense_date", $expenseWhereClause);
$topCategoryTypes = $expenseWhereTypes;
$topCategoryParams = $expenseWhereParams;

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $topCategoryWhere .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $topCategoryWhere .= " AND e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $topCategoryParams[] = $cid;
            $topCategoryTypes .= "i";
        }
    }
}

$topCategoryStmt = $conn->prepare("
    SELECT c.category_name, COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
    FROM expenses e
    JOIN expense_categories c ON e.expense_category_id = c.category_id
    WHERE $topCategoryWhere
    GROUP BY c.category_name
    ORDER BY total DESC
    LIMIT 1
");
$topCategoryStmt->bind_param($topCategoryTypes, ...$topCategoryParams);
$topCategoryStmt->execute();
$topCategoryResult = $topCategoryStmt->get_result();
$topCategoryRow = $topCategoryResult->fetch_assoc();
$topCategory = $topCategoryRow['category_name'] ?? "None";
$topCategoryStmt->close();

// Fetch budget for selected month/year
$budgetTypes = $isAnnual ? "i" : "ii";
$budgetParams = $isAnnual ? [$selectedYear] : [intval($selectedMonth), $selectedYear];

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $budgetSql = $isAnnual
            ? "SELECT COALESCE(SUM(amount), 0) AS amount FROM budgets WHERE year = ? AND 1=0"
            : "SELECT COALESCE(SUM(amount), 0) AS amount FROM budgets WHERE month = ? AND year = ? AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $budgetSql = $isAnnual
            ? "SELECT COALESCE(SUM(amount), 0) AS amount FROM budgets WHERE year = ? AND company_id IN ($placeholders)"
            : "SELECT COALESCE(SUM(amount), 0) AS amount FROM budgets WHERE month = ? AND year = ? AND company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $budgetParams[] = $cid;
            $budgetTypes .= "i";
        }
    }
} else {
    $budgetSql = $isAnnual
        ? "SELECT COALESCE(SUM(amount), 0) AS amount FROM budgets WHERE year = ?"
        : "SELECT COALESCE(SUM(amount), 0) AS amount FROM budgets WHERE month = ? AND year = ?";
}

$budgetStmt = $conn->prepare($budgetSql);
$budgetStmt->bind_param($budgetTypes, ...$budgetParams);
$budgetStmt->execute();
$budgetResult = $budgetStmt->get_result();
$budgetRow = $budgetResult->fetch_assoc();
$budgetTotal = isset($budgetRow['amount']) ? floatval($budgetRow['amount']) : 0;
$budgetStmt->close();

// Calculate utilization if budget exists
if ($budgetTotal > 0) {
    $budgetUtilization = ($totalExpenses / $budgetTotal) * 100;
    $budgetDisplay = number_format($budgetUtilization, 2) . '%';
} else {
    $budgetDisplay = "NOT YET SET";
}

// ---------- Top Payees ----------
$topPayeesWhere = str_replace("expense_date", "e.expense_date", $expenseWhereClause);
$topPayeesTypes = $expenseWhereTypes;
$topPayeesParams = $expenseWhereParams;

if ($selectedCompanyId > 0) {
    $topPayeesWhere .= " AND e.expense_company_id = ?";
    $topPayeesParams[] = $selectedCompanyId;
    $topPayeesTypes .= "i";
} elseif (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $topPayeesWhere .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $topPayeesWhere .= " AND e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $topPayeesParams[] = $cid;
            $topPayeesTypes .= "i";
        }
    }
}

$topPayeesStmt = $conn->prepare("
    SELECT p.payee_name, COUNT(e.expense_id) AS expense_count, 
           COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
    FROM expenses e
    JOIN payees p ON e.expense_payee_id = p.payee_id
    WHERE $topPayeesWhere
    GROUP BY p.payee_name
    ORDER BY total DESC
    LIMIT 5
");
$topPayeesStmt->bind_param($topPayeesTypes, ...$topPayeesParams);
$topPayeesStmt->execute();
$topPayeesResult = $topPayeesStmt->get_result();
$topPayees = [];
while ($row = $topPayeesResult->fetch_assoc()) {
    $topPayees[] = $row;
}
$topPayeesStmt->close();

// ---------- Top Categories ----------
$topCategoriesWhere = str_replace("expense_date", "e.expense_date", $expenseWhereClause);
$topCategoriesTypes = $expenseWhereTypes;
$topCategoriesParams = $expenseWhereParams;

if ($selectedCompanyId > 0) {
    $topCategoriesWhere .= " AND e.expense_company_id = ?";
    $topCategoriesParams[] = $selectedCompanyId;
    $topCategoriesTypes .= "i";
} elseif (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $topCategoriesWhere .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $topCategoriesWhere .= " AND e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $topCategoriesParams[] = $cid;
            $topCategoriesTypes .= "i";
        }
    }
}

$topCategoriesStmt = $conn->prepare("
    SELECT c.category_name, COUNT(e.expense_id) AS expense_count,
           COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
    FROM expenses e
    JOIN expense_categories c ON e.expense_category_id = c.category_id
    WHERE $topCategoriesWhere
    GROUP BY c.category_name
    ORDER BY total DESC
    LIMIT 5
");
$topCategoriesStmt->bind_param($topCategoriesTypes, ...$topCategoriesParams);
$topCategoriesStmt->execute();
$topCategoriesResult = $topCategoriesStmt->get_result();
$topCategories = [];
while ($row = $topCategoriesResult->fetch_assoc()) {
    $topCategories[] = $row;
}
$topCategoriesStmt->close();

// ---------- Chart Data (Dynamic Breakdown) ----------
$pieLabels = [];
$pieValues = [];

$breakdownWhere = str_replace("expense_date", "e.expense_date", $expenseWhereClause);
$breakdownParams = $expenseWhereParams;
$breakdownTypes = $expenseWhereTypes;

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $breakdownWhere .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $breakdownWhere .= " AND e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $breakdownParams[] = $cid;
            $breakdownTypes .= "i";
        }
    }
}

switch ($breakdown) {
    case 'category':
        $breakdownSql = "
            SELECT c.category_name AS label, COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
            FROM expenses e
            JOIN expense_categories c ON e.expense_category_id = c.category_id
            WHERE $breakdownWhere
            GROUP BY c.category_name
            HAVING total > 0
        ";
        break;
    case 'reseller':
        $breakdownSql = "
            SELECT COALESCE(r.reseller_name, 'No Reseller') AS label, COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
            FROM expenses e
            LEFT JOIN resellers r ON e.expense_reseller_id = r.reseller_id
            WHERE $breakdownWhere
            GROUP BY r.reseller_name
            HAVING total > 0
        ";
        break;
    case 'payee':
        $breakdownSql = "
            SELECT p.payee_name AS label, COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
            FROM expenses e
            JOIN payees p ON e.expense_payee_id = p.payee_id
            WHERE $breakdownWhere
            GROUP BY p.payee_name
            HAVING total > 0
        ";
        break;
    case 'company':
        $breakdownSql = "
            SELECT c.company_name AS label, COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
            FROM expenses e
            JOIN companies c ON e.expense_company_id = c.company_id
            WHERE $breakdownWhere
            GROUP BY c.company_name
            HAVING total > 0
        ";
        break;
    case 'end_user':
    default:
        $breakdownSql = "
            SELECT COALESCE(eu.end_user_name, 'No End User') AS label, COALESCE(SUM(e.expense_total_receipt_amount), 0) AS total
            FROM expenses e
            LEFT JOIN expense_end_users eu ON e.expense_user_id = eu.end_user_id
            WHERE $breakdownWhere
            GROUP BY eu.end_user_name
            HAVING total > 0
        ";
        break;
}

$pieDataStmt = $conn->prepare($breakdownSql);
$pieDataStmt->bind_param($breakdownTypes, ...$breakdownParams);
$pieDataStmt->execute();
$pieResult = $pieDataStmt->get_result();
while ($row = $pieResult->fetch_assoc()) {
    $pieLabels[] = $row['label'];
    $pieValues[] = floatval($row['total']);
}
$pieDataStmt->close();

// ---------- Recent Activity ----------
$recentWhere = str_replace("expense_date", "e.expense_date", $expenseWhereClause);
$recentTypes = $expenseWhereTypes;
$recentParams = $expenseWhereParams;

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $recentWhere .= " AND 1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $recentWhere .= " AND e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $recentParams[] = $cid;
            $recentTypes .= "i";
        }
    }
}

$recentActivityStmt = $conn->prepare("
    SELECT e.expense_id, e.expense_or_number, e.expense_date, e.expense_total_receipt_amount,
           c.company_name, cat.category_name
    FROM expenses e
    LEFT JOIN companies c ON e.expense_company_id = c.company_id
    LEFT JOIN expense_categories cat ON e.expense_category_id = cat.category_id
    WHERE $recentWhere
    ORDER BY e.expense_date DESC
    LIMIT 5
");
$recentActivityStmt->bind_param($recentTypes, ...$recentParams);
$recentActivityStmt->execute();
$recentActivityResult = $recentActivityStmt->get_result();
$recentActivityStmt->close();

$selectedPeriodLabel = $isAnnual
    ? 'Annual ' . $selectedYear
    : date('F Y', mktime(0, 0, 0, intval($selectedMonth), 1, $selectedYear));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- CSS Files -->
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/layout.css" />

    <!-- Icons -->
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Expense Tracker Dashboard</title>

    <style>
        /* ============================================
           DASHBOARD STYLING - Clean & Proper Positioning
           ============================================ */

        :root {
            --primary-color: #4e54c8;
            --primary-dark: #3a3f9e;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --bg-card: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f4f6f9;
        }

        /* Content Wrapper */
        #content {
            --primary-color: #4e54c8;
            --primary-dark: #3a3f9e;
            --text-main: #1f2937;
            --text-muted: #6b7280;

            --border-color: #e5e7eb;
            --filter-border-color: #000000;

            --bg-card: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Content Header */
        #content-header {
            margin-bottom: 20px;
        }

        #breadcrumb {
            font-size: 14px;
            color: var(--text-muted);
        }

        #breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        #breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Container Fluid */
        .container-fluid {
            max-width: 1640px;
            margin: 0 auto;
        }

        /* Filter Section */
        .filter-section {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .filter-section form {
            display: inline-block;
        }

        .filter-section label {
            margin-right: 10px;
            font-weight: 600;
            color: var(--text-main);
        }

        .filter-section select {
            margin-right: 5px;
            padding: 5px;
            border: 1px solid var(--filter-border-color);
            border-radius: 3px;
            font-family: inherit;
        }

        .filter-section button {
            padding: 5px 10px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-weight: 500;
        }

        .filter-section button:hover {
            background: var(--primary-dark);
        }

        /* Summary Section */
        .dashboard-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-box {
            flex: 1;
            background: var(--bg-card);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-color);
        }

        .summary-box h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .summary-box p {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Chart Container */
        .chart-container {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        .chart-box, .logs-box {
            flex: 1;
            background: var(--bg-card);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .chart-box h4, .logs-box h4 {
            margin: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .chart-box {
            padding: 0;
        }

        .chart-canvas-wrap {
            width: 360px;
            height: 360px;
            margin: 20px auto;
            position: relative;
        }

        .chart-box canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        /* Logs Box */
        .widget-box {
            padding: 0;
        }

        .widget-content {
            padding: 0;
        }

        .widget-content table {
            width: 100%;
            border-collapse: collapse;
        }

        .widget-content th {
            padding: 12px 15px;
            background: #f8f9fa;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }

        .widget-content td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 14px;
        }

        .widget-content tr:last-child td {
            border-bottom: none;
        }

        .widget-content tr:hover td {
            background: #f8f9fa;
        }

        .amount-col {
            font-weight: 600;
            text-align: right;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 900px) {
            #content {
                margin-left: 0;
                padding: 70px 15px 15px;
            }

            .dashboard-summary {
                flex-direction: column;
            }

            .chart-container {
                flex-direction: column;
            }

            .chart-canvas-wrap {
                width: 100%;
                max-width: 360px;
                height: 360px;
            }
        }
    </style>
</head>

<body>

<div id="content">

    <div class="container-fluid">

            <!-- Filter Section -->
            <div class="filter-section dashboard-filter">

                <h4>
                    <i class="icon-filter"></i>
                    Filter Total Expenses
                </h4>

                <form method="get" id="filterForm">

                    <div class="period-row">

                        <select name="month">

                            <?php
                            $months = [
                                'annual' => 'Annual',
                                '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                            ];

                            foreach ($months as $val => $name) {
                                $selected = ($val == $selectedMonth) ? 'selected' : '';
                                echo "<option value='$val' $selected>$name</option>";
                            }
                            ?>

                        </select>

                        <select name="year">

                            <?php
                            $currentYear = date('Y');

                            for ($y = $currentYear; $y >= $currentYear - 9; $y--) {
                                $selected = ($y == $selectedYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>

                        </select>

                        <?php if (count($companiesForFilterRows) > 1): ?>
                        <select name="company_id">
                            <option value="0">All Companies</option>
                            <?php foreach ($companiesForFilterRows as $cfRow): ?>
                                <option value="<?= $cfRow['company_id'] ?>" <?= $selectedCompanyId == $cfRow['company_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cfRow['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                    </div>

                    <div class="filter-actions">

                        <button type="submit" class="btn btn-primary">
                            Apply
                        </button>

                    </div>

                </form>

            </div>

        <!-- Summary Section -->
        <div class="dashboard-summary">

            <div class="summary-box">
                <h4>TOTAL EXPENSES (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h4>
                <p>&#8369;<?= number_format($totalExpenses, 2) ?></p>
            </div>

            <div class="summary-box">
                <h4>BUDGET UTILIZATION</h4>
                <p><?= htmlspecialchars($budgetDisplay) ?></p>
            </div>

            <div class="summary-box">
                <h4>TOP EXPENSE CATEGORY</h4>
                <p><?= htmlspecialchars($topCategory) ?></p>
            </div>

        </div>

        <!-- Charts & Recent Activity -->
        <div class="chart-container">

            <!-- Pie Chart -->
            <div class="chart-box">
                <h4 style="margin:0 0 10px 0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    Expense Breakdown by
                    <select id="breakdownSelect" onchange="changeBreakdown(this.value)" style="padding:4px 6px; border:1px solid #ccc; border-radius:4px; font-size:14px; width:auto; font-weight:normal;">
                        <option value="end_user" <?= $breakdown === 'end_user' ? 'selected' : '' ?>>End User</option>
                        <option value="category" <?= $breakdown === 'category' ? 'selected' : '' ?>>Category</option>
                        <option value="reseller" <?= $breakdown === 'reseller' ? 'selected' : '' ?>>Reseller</option>
                        <option value="payee" <?= $breakdown === 'payee' ? 'selected' : '' ?>>Payee</option>
                        <?php if ($isSuperAdmin || !empty($assignedCompanyIds)): ?>
                        <option value="company" <?= $breakdown === 'company' ? 'selected' : '' ?>>Company</option>
                        <?php endif; ?>
                    </select>
                </h4>
                <div class="chart-canvas-wrap">
                    <canvas id="idPieChart"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="logs-box">

                <h4>Recent Activity (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h4>

                <div class="widget-box">
                    <div class="widget-content nopadding">

                        <table style="width:100%;border-collapse:collapse;">

                            <thead>
                                <tr>
                                    <th style="padding:8px;">OR Number</th>
                                    <th style="padding:8px;">Date</th>
                                    <th style="padding:8px;">Company</th>
                                    <th style="padding:8px;">Category</th>
                                    <th style="padding:8px;">Amount</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if ($recentActivityResult && $recentActivityResult->num_rows > 0) { ?>

                                <?php while ($row = $recentActivityResult->fetch_assoc()) { ?>

                                <tr>
                                    <td style="padding:8px;">
                                        <?= htmlspecialchars($row['expense_or_number'] ?? '-') ?>
                                    </td>

                                    <td style="padding:8px;">
                                        <?= date('M d, Y', strtotime($row['expense_date'])) ?>
                                    </td>

                                    <td style="padding:8px;">
                                        <?= htmlspecialchars($row['company_name'] ?? '-') ?>
                                    </td>

                                    <td style="padding:8px;">
                                        <?= htmlspecialchars($row['category_name'] ?? '-') ?>
                                    </td>

                                    <td style="padding:8px;" class="amount-col">
                                        &#8369;<?= number_format($row['expense_total_receipt_amount'], 2) ?>
                                    </td>
                                </tr>

                                <?php } ?>

                            <?php } else { ?>

                                <tr>
                                    <td colspan="5"
                                        style="padding:10px;text-align:center;"
                                        class="empty-state">
                                        No recent activity recorded.
                                    </td>
                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    </div>
                </div>

            </div>

        </div>

        <!-- Second Row -->
        <div class="chart-container" style="margin-top:20px;">

            <!-- Top Payees -->
            <div class="logs-box">

                <h4>Top Payees (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h4>

                <div class="widget-box">
                    <div class="widget-content nopadding">

                        <table style="width:100%;border-collapse:collapse;">

                            <thead>
                                <tr>
                                    <th style="padding:8px;">#</th>
                                    <th style="padding:8px;">Payee</th>
                                    <th style="padding:8px;">Transactions</th>
                                    <th style="padding:8px;">Amount</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (!empty($topPayees)): ?>
                                <?php foreach ($topPayees as $i => $payee): ?>
                                <tr>
                                    <td style="padding:8px;"><?= $i + 1 ?></td>
                                    <td style="padding:8px;"><?= htmlspecialchars($payee['payee_name']) ?></td>
                                    <td style="padding:8px;"><?= $payee['expense_count'] ?></td>
                                    <td style="padding:8px;" class="amount-col">
                                        &#8369;<?= number_format($payee['total'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4"
                                        style="padding:10px;text-align:center;"
                                        class="empty-state">
                                        No payee data for this period.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>
                </div>

            </div>

            <!-- Top Categories -->
            <div class="logs-box">

                <h4>Top Categories (<?= htmlspecialchars($selectedPeriodLabel) ?>)</h4>

                <div class="widget-box">
                    <div class="widget-content nopadding">

                        <table style="width:100%;border-collapse:collapse;">

                            <thead>
                                <tr>
                                    <th style="padding:8px;">#</th>
                                    <th style="padding:8px;">Category</th>
                                    <th style="padding:8px;">Transactions</th>
                                    <th style="padding:8px;">Amount</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (!empty($topCategories)): ?>
                                <?php foreach ($topCategories as $i => $cat): ?>
                                <tr>
                                    <td style="padding:8px;"><?= $i + 1 ?></td>
                                    <td style="padding:8px;"><?= htmlspecialchars($cat['category_name']) ?></td>
                                    <td style="padding:8px;"><?= $cat['expense_count'] ?></td>
                                    <td style="padding:8px;" class="amount-col">
                                        &#8369;<?= number_format($cat['total'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4"
                                        style="padding:10px;text-align:center;"
                                        class="empty-state">
                                        No category data for this period.
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


<!-- Chart.js Integration -->
<script>
    const chartColors = [
        '#4e54c8', '#10b981', '#f59e0b', '#ef4444',
        '#8b5cf6', '#ec4899', '#06b6d4', '#6366f1'
    ];

    const pieLabels = <?= json_encode($pieLabels) ?>;
    const pieValues = <?= json_encode($pieValues) ?>;
    const ctxPie = document.getElementById('idPieChart').getContext('2d');

    function changeBreakdown(value) {
        const url = new URL(window.location.href);
        url.searchParams.set('breakdown', value);
        window.location.href = url.toString();
    }

    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieValues,
                backgroundColor: chartColors.slice(0, pieLabels.length),
                borderColor: "#ffffff",
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 14,
                        padding: 12,
                        font: {
                            family: "'Open Sans', sans-serif",
                            size: 12
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
</script>

</body>
</html>
