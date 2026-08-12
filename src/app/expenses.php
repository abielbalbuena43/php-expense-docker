<?php
session_start();
include "connection.php";
include "header.php";

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

/* -------------------------------
   PAGINATION
--------------------------------*/
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

/* -------------------------------
   SEARCH
--------------------------------*/
$searchQuery = trim($_GET['search'] ?? '');

/* -------------------------------
   ALERT HELPER
--------------------------------*/
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

/* -------------------------------
   SAMPLE COMMENT
--------------------------------*/
if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        setAlert('success', 'Expense added successfully!');
    }

    if ($_GET['success'] === 'edited') {
        setAlert('success', 'Expense updated successfully!');
    }

}

/* -------------------------------
   PERIOD FILTER LOGIC
--------------------------------*/
$selectedPeriods = [];
$showAll = isset($_GET['all']);

if (isset($_GET['month']) && isset($_GET['year'])) {
    $months = (array)$_GET['month'];
    $years = (array)$_GET['year'];
    $count = min(count($months), count($years));

    for ($i = 0; $i < $count; $i++) {
        $m = intval($months[$i]);
        $y = intval($years[$i]);

        if ($m >= 1 && $m <= 12 && $y > 0) {
            $selectedPeriods[] = [
                'month' => $m,
                'year' => $y
            ];
        }
    }
}

$hasActivePeriodFilter = !empty($selectedPeriods);
$hasActiveDateRange = !empty($_GET['date_from']) || !empty($_GET['date_to']);

// Default to current month/year unless "Show All" or a date range was explicitly requested
if (empty($selectedPeriods) && !$showAll && !$hasActiveDateRange) {
    $selectedPeriods[] = [
        'month' => intval(date('m')),
        'year' => intval(date('Y'))
    ];
}

/* -------------------------------
   DATE RANGE FILTER LOGIC
--------------------------------*/
function parseFilterDate($value) {
    if (empty($value)) {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : null;
}

$selectedDateFrom = parseFilterDate($_GET['date_from'] ?? '');
$selectedDateTo = parseFilterDate($_GET['date_to'] ?? '');

/* -------------------------------
   HANDLE DELETE
--------------------------------*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_id']) &&
    $_POST['csrf_token'] === $_SESSION['csrf_token']
) {

    $deleteId = intval($_POST['delete_id']);

    $ownerStmt = $conn->prepare("SELECT expense_created_by FROM expenses WHERE expense_id = ?");
    $ownerStmt->bind_param("i", $deleteId);
    $ownerStmt->execute();
    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if (!$isAdmin && !$isSuperAdmin && intval($ownerRow['expense_created_by']) !== intval($_SESSION['user_id'])) {
        setAlert('error', 'You are not authorized to delete this record.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $deleteStmt = $conn->prepare("DELETE FROM expenses WHERE expense_id = ?");
    $deleteStmt->bind_param("i", $deleteId);

    if ($deleteStmt->execute()) {

        $username = mysqli_real_escape_string($conn, $_SESSION['username'] ?? 'Unknown');

        $logStmt = $conn->prepare("
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES (?, ?, ?, NOW())
        ");

        $logAction = "Expense deleted";
        $logDetails = "Expense ID: $deleteId";

        $logStmt->bind_param("sss", $logAction, $username, $logDetails);
        $logStmt->execute();
        $logStmt->close();

        setAlert('success', 'Expense deleted successfully!');

    } else {

        setAlert('error', 'Failed to delete expense.');

    }

    $deleteStmt->close();

    header("Location: " . $_SERVER['PHP_SELF'] . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit();
}

/* -------------------------------
   CSRF TOKEN
--------------------------------*/
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------------------
   TOTAL COUNT FOR PAGINATION
--------------------------------*/
$countSql = "
    SELECT COUNT(*) as total
    FROM expenses e
    INNER JOIN companies c ON e.expense_company_id = c.company_id
    INNER JOIN payees p ON e.expense_payee_id = p.payee_id
    INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
";

$countParams = [];
$countTypes = "";
$countWhereClauses = [];

if (!empty($selectedPeriods)) {
    $conditions = [];
    foreach ($selectedPeriods as $p) {
        $conditions[] = "(MONTH(e.expense_date) = ? AND YEAR(e.expense_date) = ?)";
        $countParams[] = $p['month'];
        $countParams[] = $p['year'];
        $countTypes .= "ii";
    }
    $countWhereClauses[] = "(" . implode(" OR ", $conditions) . ")";
}

if ($selectedDateFrom && $selectedDateTo) {
    $countWhereClauses[] = "DATE(e.expense_date) BETWEEN ? AND ?";
    $countParams[] = $selectedDateFrom;
    $countParams[] = $selectedDateTo;
    $countTypes .= "ss";
} elseif ($selectedDateFrom) {
    $countWhereClauses[] = "DATE(e.expense_date) >= ?";
    $countParams[] = $selectedDateFrom;
    $countTypes .= "s";
} elseif ($selectedDateTo) {
    $countWhereClauses[] = "DATE(e.expense_date) <= ?";
    $countParams[] = $selectedDateTo;
    $countTypes .= "s";
}

if (!empty($searchQuery)) {
    $countWhereClauses[] = "(e.expense_or_number LIKE ? OR c.company_name LIKE ? OR p.payee_name LIKE ? OR cat.category_name LIKE ?)";
    $searchParam = "%" . $searchQuery . "%";
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countTypes .= "ssss";
}

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $countWhereClauses[] = "1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $countWhereClauses[] = "e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $countParams[] = $cid;
            $countTypes .= "i";
        }
    }
}

if (!empty($countWhereClauses)) {
    $countSql .= " WHERE " . implode(" AND ", $countWhereClauses);
}

$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$totalRecords = $countRow['total'];
$countStmt->close();

$totalPages = ceil($totalRecords / $recordsPerPage);

/* -------------------------------
   SQL QUERY
--------------------------------*/
$sql = "
SELECT 
    e.expense_id,
    e.expense_or_number,
    e.expense_date,
    e.expense_total_receipt_amount,
    e.expense_created_by,
    c.company_name,
    p.payee_name,
    cat.category_name
FROM expenses e
INNER JOIN companies c ON e.expense_company_id = c.company_id
INNER JOIN payees p ON e.expense_payee_id = p.payee_id
INNER JOIN expense_categories cat ON e.expense_category_id = cat.category_id
";

$params = [];
$types = "";
$whereClauses = [];

if (!empty($selectedPeriods)) {

    $conditions = [];

    foreach ($selectedPeriods as $p) {
        $conditions[] = "(MONTH(e.expense_date) = ? AND YEAR(e.expense_date) = ?)";
        $params[] = $p['month'];
        $params[] = $p['year'];
        $types .= "ii";
    }

    $whereClauses[] = "(" . implode(" OR ", $conditions) . ")";
}

if ($selectedDateFrom && $selectedDateTo) {
    $whereClauses[] = "DATE(e.expense_date) BETWEEN ? AND ?";
    $params[] = $selectedDateFrom;
    $params[] = $selectedDateTo;
    $types .= "ss";
} elseif ($selectedDateFrom) {
    $whereClauses[] = "DATE(e.expense_date) >= ?";
    $params[] = $selectedDateFrom;
    $types .= "s";
} elseif ($selectedDateTo) {
    $whereClauses[] = "DATE(e.expense_date) <= ?";
    $params[] = $selectedDateTo;
    $types .= "s";
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(e.expense_or_number LIKE ? OR c.company_name LIKE ? OR p.payee_name LIKE ? OR cat.category_name LIKE ?)";
    $searchParam = "%" . $searchQuery . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $whereClauses[] = "1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $whereClauses[] = "e.expense_company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $params[] = $cid;
            $types .= "i";
        }
    }
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY e.expense_date DESC, e.expense_id DESC LIMIT ? OFFSET ?";

$params[] = $recordsPerPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

/* -------------------------------
   PERIOD LABEL
--------------------------------*/
$periodLabel = !empty($selectedPeriods)
    ? implode(', ', array_map(function($p) {
        return date('F Y', mktime(0, 0, 0, $p['month'], 1, $p['year']));
    }, $selectedPeriods))
    : 'All Time';

$dateRangeLabel = 'All Dates';

if ($selectedDateFrom && $selectedDateTo) {
    $dateRangeLabel = date('M d, Y', strtotime($selectedDateFrom)) . ' to ' . date('M d, Y', strtotime($selectedDateTo));
} elseif ($selectedDateFrom) {
    $dateRangeLabel = 'From ' . date('M d, Y', strtotime($selectedDateFrom));
} elseif ($selectedDateTo) {
    $dateRangeLabel = 'Until ' . date('M d, Y', strtotime($selectedDateTo));
}

$activeLabels = [];

if (!empty($selectedPeriods)) {
    $activeLabels[] = $periodLabel;
}

if ($selectedDateFrom || $selectedDateTo) {
    $activeLabels[] = $dateRangeLabel;
}

$filterLabel = !empty($activeLabels)
    ? implode(' | ', $activeLabels)
    : 'All Time';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<link rel="stylesheet" href="css/bootstrap.min.css" />
<link rel="stylesheet" href="css/layout.css" />

<link href="font-awesome/css/font-awesome.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<title>Expenses List</title>

</head>

<body>

<div id="content">

<?php if (isset($_SESSION['alert'])): ?>

<?php
$alert = $_SESSION['alert'];

if (is_array($alert)) {
    $type = $alert['type'] ?? 'info';
    $message = $alert['message'] ?? 'Something happened.';
} else {
    // fallback for old string alerts
    $type = 'success';
    $message = $alert;
}

unset($_SESSION['alert']);
?>

<div class="alert alert-<?= $type ?>">
    <?= htmlspecialchars($message) ?>
</div>

<?php endif; ?>


<div class="container-fluid">

<!-- Header Actions -->

<div class="header-actions">
    <a href="expense_new.php" class="btn btn-success">
        <i class="icon-plus"></i>
        Create New Expense
    </a>
</div>


<!-- Filter Section -->

<div class="filter-section">

<h4>
    <i class="icon-filter"></i>
    Filter by Periods (<?= htmlspecialchars($periodLabel) ?>)
</h4>

<form method="get" id="filterForm">

<?php if ($selectedDateFrom): ?>
<input type="hidden" name="date_from" value="<?= htmlspecialchars($selectedDateFrom) ?>">
<?php endif; ?>

<?php if ($selectedDateTo): ?>
<input type="hidden" name="date_to" value="<?= htmlspecialchars($selectedDateTo) ?>">
<?php endif; ?>

<div id="periodContainer">

<?php if (!empty($selectedPeriods)): ?>
<?php foreach ($selectedPeriods as $p): ?>

<div class="period-row">

<select name="month[]">

<?php
$months = [
    1=>"January",2=>"February",3=>"March",4=>"April",
    5=>"May",6=>"June",7=>"July",8=>"August",
    9=>"September",10=>"October",11=>"November",12=>"December"
];

foreach ($months as $num=>$name):
$sel = ($p['month']==$num) ? "selected" : "";
?>

<option value="<?= $num ?>" <?= $sel ?>>
<?= $name ?>
</option>

<?php endforeach; ?>

</select>


<select name="year[]">

<?php
$currentYear = date('Y');

for ($y=$currentYear;$y>=$currentYear-10;$y--):
$sel = ($p['year']==$y) ? "selected" : "";
?>

<option value="<?= $y ?>" <?= $sel ?>>
<?= $y ?>
</option>

<?php endfor; ?>

</select>

<button
type="button"
onclick="removePeriod(this)"
class="btn btn-danger-small"
>
<i class="icon-remove"></i>
</button>

</div>

<?php endforeach; ?>
<?php endif; ?>

</div>


<div class="filter-actions">

<button type="button" id="addPeriod" class="btn btn-primary">
<i class="icon-plus"></i>
Add Period
</button>

<button type="submit" class="btn btn-primary">
Apply Filter
</button>

<?php if ($showAll): ?>
<a href="expenses.php" class="btn btn-secondary">Back to This Month</a>
<?php elseif ($hasActivePeriodFilter): ?>
<button type="button" id="clearFilter" class="btn btn-secondary">Clear Filter</button>
<?php else: ?>
<a href="?all=1" class="btn btn-secondary">View All Records</a>
<?php endif; ?>

</div>

</form>

<h4 style="margin-top: 20px;">
    <i class="icon-calendar"></i>
    Filter by Date Range (<?= htmlspecialchars($dateRangeLabel) ?>)
</h4>

<form method="get" id="rangeFilterForm">

<?php if (!empty($selectedPeriods)): ?>
<?php foreach ($selectedPeriods as $p): ?>
<input type="hidden" name="month[]" value="<?= (int)$p['month'] ?>">
<input type="hidden" name="year[]" value="<?= (int)$p['year'] ?>">
<?php endforeach; ?>
<?php endif; ?>

<div class="period-row">

<input
type="date"
name="date_from"
value="<?= htmlspecialchars($selectedDateFrom ?? '') ?>"
>

<input
type="date"
name="date_to"
value="<?= htmlspecialchars($selectedDateTo ?? '') ?>"
>

</div>

<div class="filter-actions">

<button type="submit" class="btn btn-primary">
Apply Date Range
</button>

<button type="button" id="clearDateFilter" class="btn btn-secondary">
Clear Date Range
</button>

</div>

</form>

</div>

<!-- Main Table -->

<div class="table-container">

<div class="table-header">

<h3>
Expenses List (<?= htmlspecialchars($filterLabel) ?>)
</h3>

<span class="table-stats">
    Showing <?= min($offset + 1, $totalRecords) ?> to <?= min($offset + $recordsPerPage, $totalRecords) ?> of <?= number_format($totalRecords) ?> records
</span>

</div>

<!-- Search Bar -->
<form method="get" id="searchForm" style="margin-bottom: 15px;">

    <?php if ($showAll): ?>
    <input type="hidden" name="all" value="1">
    <?php else: ?>
    <?php if (!empty($selectedPeriods)): ?>
    <?php foreach ($selectedPeriods as $p): ?>
    <input type="hidden" name="month[]" value="<?= (int)$p['month'] ?>">
    <input type="hidden" name="year[]" value="<?= (int)$p['year'] ?>">
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($selectedDateFrom): ?>
    <input type="hidden" name="date_from" value="<?= htmlspecialchars($selectedDateFrom) ?>">
    <?php endif; ?>

    <?php if ($selectedDateTo): ?>
    <input type="hidden" name="date_to" value="<?= htmlspecialchars($selectedDateTo) ?>">
    <?php endif; ?>

    <div style="display:flex; gap:10px; align-items:center; max-width:500px;">
        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($searchQuery) ?>"
            placeholder="Search OR Number, Company, Payee, Category..."
            style="flex:1; padding:15px 12px; border:1px solid #ccc; border-radius:3px; font-family:inherit; font-size:14px;"
        >
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if (!empty($searchQuery)): ?>
        <a href="<?= '?' . http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </div>

</form>


<div class="table-responsive">

<table>

<thead>
<tr>
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

<tr
class="clickable-row"
data-href="expense_view.php?id=<?= $row['expense_id'] ?>"
>

<td><?= htmlspecialchars($row['expense_or_number']) ?></td>
<td><?= date('M d, Y', strtotime($row['expense_date'])) ?></td>
<td><?= htmlspecialchars($row['company_name']) ?></td>
<td><?= htmlspecialchars($row['payee_name']) ?></td>
<td><?= htmlspecialchars($row['category_name']) ?></td>

<td class="amount-col">
&#8369;<?= number_format($row['expense_total_receipt_amount'], 2) ?>
</td>


</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="8">

<div class="empty-state">

<i class="icon-inbox"></i>

<h4>No expenses found</h4>

<p>
No expenses match your current filter.
Try adjusting the date range or create a new expense.
</p>

</div>

</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

<?php
// Build base URL preserving existing filters
$paginationParams = $_GET;
unset($paginationParams['page']);
$baseUrl = '?' . http_build_query($paginationParams);
$baseUrl .= $paginationParams ? '&' : '';
?>

<?php if ($totalPages > 1): ?>
<div class="table-footer">
    <div class="pagination-wrapper">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li><a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="pagination-prev">« Previous</a></li>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);

            if ($startPage > 1) {
                echo '<li><a href="' . $baseUrl . 'page=1">1</a></li>';
                if ($startPage > 2) echo '<li class="disabled"><span>...</span></li>';
            }

            for ($i = $startPage; $i <= $endPage; $i++) {
                $activeClass = $i == $page ? 'active' : '';
                echo '<li class="' . $activeClass . '"><a href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a></li>';
            }

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<li class="disabled"><span>...</span></li>';
                echo '<li><a href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
            }
            ?>

            <?php if ($page < $totalPages): ?>
                <li><a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="pagination-next">Next »</a></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

</div>

</div>

</div>

</div>


<script>

$(document).on("click", ".clickable-row", function() {

const url = $(this).data("href");

if (url) {
    window.location.href = url;
}

});

$(document).ready(function(){

    $("#addPeriod").click(function(){

        const currentYear = new Date().getFullYear();

        let html = `
        <div class="period-row">

            <select name="month[]">
                <option value="1">January</option>
                <option value="2">February</option>
                <option value="3">March</option>
                <option value="4">April</option>
                <option value="5">May</option>
                <option value="6">June</option>
                <option value="7">July</option>
                <option value="8">August</option>
                <option value="9">September</option>
                <option value="10">October</option>
                <option value="11">November</option>
                <option value="12">December</option>
            </select>

            <select name="year[]">`;

        for(let y = currentYear; y >= currentYear - 10; y--){
            html += `<option value="${y}">${y}</option>`;
        }

        html += `</select>

            <button type="button" onclick="removePeriod(this)" class="btn btn-danger-small">
                <i class="icon-remove"></i>
            </button>

        </div>`;

        $("#periodContainer").append(html);
    });

    $("#clearFilter").click(function(){
        const url = new URL(window.location.href);
        url.searchParams.delete("month[]");
        url.searchParams.delete("year[]");
        url.searchParams.delete("month");
        url.searchParams.delete("year");
        window.location.href = url.pathname + (url.search ? url.search : "");
    });

    $("#clearDateFilter").click(function(){
        const url = new URL(window.location.href);
        url.searchParams.delete("date_from");
        url.searchParams.delete("date_to");
        window.location.href = url.pathname + (url.search ? url.search : "");
    });

});

function removePeriod(btn){
    $(btn).closest('.period-row').remove();
}

</script>

</body>
</html>