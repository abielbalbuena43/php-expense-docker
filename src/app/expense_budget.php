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

// Selected year
$selectedYear = intval($_GET['year'] ?? date('Y'));

// ---------- Monthly Expenses ----------
$expenseData = array_fill(1, 12, 0);

if ($isSuperAdmin) {
    $expenseStmt = $conn->prepare("
        SELECT MONTH(expense_date) AS month,
               SUM(expense_total_receipt_amount) AS total
        FROM expenses
        WHERE YEAR(expense_date) = ?
        GROUP BY MONTH(expense_date)
    ");
    $expenseStmt->bind_param("i", $selectedYear);
    $expenseStmt->execute();
    $expenseResult = $expenseStmt->get_result();
    while ($row = $expenseResult->fetch_assoc()) {
        $expenseData[$row['month']] = floatval($row['total']);
    }
    $expenseStmt->close();
} elseif (!empty($assignedCompanyIds)) {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $expenseStmt = $conn->prepare("
        SELECT MONTH(expense_date) AS month,
               SUM(expense_total_receipt_amount) AS total
        FROM expenses
        WHERE YEAR(expense_date) = ?
        AND expense_company_id IN ($placeholders)
        GROUP BY MONTH(expense_date)
    ");
    $expenseParams = array_merge([$selectedYear], $assignedCompanyIds);
    $expenseTypes = 'i' . str_repeat('i', count($assignedCompanyIds));
    $expenseStmt->bind_param($expenseTypes, ...$expenseParams);
    $expenseStmt->execute();
    $expenseResult = $expenseStmt->get_result();
    while ($row = $expenseResult->fetch_assoc()) {
        $expenseData[$row['month']] = floatval($row['total']);
    }
    $expenseStmt->close();
}
// If no companies assigned, $expenseData stays all zeros

// ---------- Monthly Budgets ----------
$budgetStmt = $conn->prepare("
    SELECT month, amount
    FROM budgets
    WHERE year = ?
");
$budgetStmt->bind_param("i", $selectedYear);
$budgetStmt->execute();
$budgetResult = $budgetStmt->get_result();

$budgetData = array_fill(1, 12, 0);

while ($row = $budgetResult->fetch_assoc()) {
    $budgetData[$row['month']] = floatval($row['amount']);
}

$budgetStmt->close();
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        
        <!-- Filter Section -->
        <div class="filter-section" style="margin-bottom: 30px;">
            <h4>
                <i class="icon-filter"></i>
                Filter by Year
            </h4>
            <form method="get" id="filterForm">
                <div class="period-row">
                    <select name="year">
                        <?php
                        $currentYear = date("Y");
                        for ($y=$currentYear; $y>=$currentYear-10; $y--) {
                            $selected = ($y==$selectedYear) ? "selected":"";
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Chart Container - White Form Style -->
        <div class="chart-container-white">
            <div class="chart-header">
                <h3>Expense vs Budget Summary (<?= htmlspecialchars($selectedYear) ?>)</h3>
            </div>
            
            <div class="chart-responsive">
                <canvas id="expenseBudgetChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const months = [
    "Jan","Feb","Mar","Apr","May","Jun",
    "Jul","Aug","Sep","Oct","Nov","Dec"
];

const expenses = <?= json_encode(array_values($expenseData)) ?>;
const budgets = <?= json_encode(array_values($budgetData)) ?>;

const selectedYear = <?= $selectedYear ?>;

const ctx = document.getElementById('expenseBudgetChart').getContext('2d');

const expenseChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            {
                label: "Expenses",
                data: expenses,
                backgroundColor: "#e74c3c"
            },
            {
                label: "Budget",
                data: budgets,
                backgroundColor: "#2ecc71"
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: {
                position: "top"
            }
        },
        onHover: function(event, elements) {
            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
        },
        onClick: function(evt, elements) {
            if (elements.length > 0) {
                const index = elements[0].index;
                const month = index + 1;
                window.location.href = "expenses.php?month=" + month + "&year=" + selectedYear;
            }
        }
    }
});
</script>

<style>
/* White Form Container for Chart */
.chart-container-white {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    overflow: hidden;
    margin-bottom: 30px;
}

.chart-header {
    padding: 25px 25px 0 25px;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 25px;
}

.chart-header h3 {
    margin: 0;
    color: #333;
    font-size: 24px;
    font-weight: 600;
}

.chart-responsive {
    position: relative;
    height: 500px;
    padding: 0 25px 25px 25px;
}

.chart-responsive canvas {
    max-height: 100%;
}

/* Filter Section Styling */
.filter-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
    padding: 25px;
    margin-bottom: 30px;
}

.filter-section h4 {
    margin: 0 0 20px 0;
    color: #333;
    font-weight: 600;
}

.period-row {
    margin-bottom: 20px;
}

.period-row select {
    width: 150px;
    padding: 10px 15px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 14px;
}

.filter-actions {
    text-align: right;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .chart-responsive {
        height: 400px;
    }
    
    .chart-header h3 {
        font-size: 20px;
    }
    
    .filter-section {
        padding: 20px;
    }
}
</style>

<?php ob_end_flush(); ?>