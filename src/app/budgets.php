<?php
session_start();
include "connection.php";
include "header.php";


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

// Fetch assigned companies for admin
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
   ALERT HELPER
--------------------------------*/
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

/* -------------------------------
   HANDLE SUCCESS REDIRECTS
--------------------------------*/
if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        setAlert('success', 'Budget added successfully!');
    }

    if ($_GET['success'] === 'edited') {
        setAlert('success', 'Budget updated successfully!');
    }

}

/* -------------------------------
   FLEXIBLE FILTER LOGIC
--------------------------------*/
$selectedMonths = isset($_GET['month']) ? array_filter((array)$_GET['month']) : [];
$selectedYears  = isset($_GET['year']) ? array_filter((array)$_GET['year']) : [];

$conditions = [];
$params = [];
$types = "";

/* Month filter */
if (!empty($selectedMonths)) {
    $placeholders = implode(',', array_fill(0, count($selectedMonths), '?'));
    $conditions[] = "month IN ($placeholders)";
    foreach ($selectedMonths as $m) {
        $params[] = intval($m);
        $types .= "i";
    }
}

/* Year filter */
if (!empty($selectedYears)) {
    $placeholders = implode(',', array_fill(0, count($selectedYears), '?'));
    $conditions[] = "year IN ($placeholders)";
    foreach ($selectedYears as $y) {
        $params[] = intval($y);
        $types .= "i";
    }
}

/* -------------------------------
   CSRF TOKEN
--------------------------------*/
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------------------
   SQL QUERY
--------------------------------*/
$sql = "SELECT b.*, c.company_name FROM budgets b LEFT JOIN companies c ON b.company_id = c.company_id";

if (!$isSuperAdmin) {
    if (empty($assignedCompanyIds)) {
        $conditions[] = "1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
        $conditions[] = "b.company_id IN ($placeholders)";
        foreach ($assignedCompanyIds as $cid) {
            $params[] = $cid;
            $types .= "i";
        }
    }
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY year DESC, month DESC LIMIT 100";

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
if (!empty($selectedMonths) && !empty($selectedYears)) {
    $periodLabel = "Filtered by Month & Year";
} elseif (!empty($selectedMonths)) {
    $periodLabel = "Filtered by Month";
} elseif (!empty($selectedYears)) {
    $periodLabel = "Filtered by Year";
} else {
    $periodLabel = "All Time";
}

$months = [
    1=>"January",2=>"February",3=>"March",4=>"April",
    5=>"May",6=>"June",7=>"July",8=>"August",
    9=>"September",10=>"October",11=>"November",12=>"December"
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/layout.css">
<link href="font-awesome/css/font-awesome.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<title>Budgets List</title>
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
    <?php if ($isSuperAdmin || $isAdmin): ?>
    <a href="budgets_new.php" class="btn btn-success">
        <i class="icon-plus"></i>
        Create New Budget
    </a>
<?php endif; ?>
</div>


<!-- Filter Section -->
<div class="filter-section">

<h4>
<i class="icon-filter"></i>
Filter by Period (<?= htmlspecialchars($periodLabel) ?>)
</h4>

<form method="get" id="filterForm">

<div id="periodContainer">
<div class="period-row">

<select name="month[]">
<option value="">All Months</option>
<?php foreach ($months as $num=>$name): ?>
<option value="<?= $num ?>" <?= in_array($num, $selectedMonths) ? "selected" : "" ?>>
<?= $name ?>
</option>
<?php endforeach; ?>
</select>

<select name="year[]">
<option value="">All Years</option>
<?php
$currentYear = date('Y');
for ($y=$currentYear;$y>=$currentYear-10;$y--):
?>
<option value="<?= $y ?>" <?= in_array($y, $selectedYears) ? "selected" : "" ?>>
<?= $y ?>
</option>
<?php endfor; ?>
</select>

</div>
</div>

<div class="filter-actions">

<button type="submit" class="btn btn-primary">
Apply Filter
</button>

<button type="button" id="clearFilter" class="btn btn-secondary">
Clear Filter
</button>

</div>

</form>

</div>


<!-- Table -->
<div class="table-container">

<div class="table-header">
<h3>Budgets List (<?= htmlspecialchars($periodLabel) ?>)</h3>
<span class="table-stats">
Showing <?= $result->num_rows ?? 0 ?> records
</span>
</div>

<div class="table-responsive">
<table>

<thead>
<tr>
<th>Company</th>
<th>Month</th>
<th>Year</th>
<th>Budget Amount</th>
</tr>
</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr class="clickable-row"
    data-href="budgets_view.php?id=<?= $row['budget_id'] ?>">

<td><?= htmlspecialchars($row['company_name'] ?? 'Unassigned') ?></td>
<td><?= htmlspecialchars($months[$row['month']] ?? $row['month']) ?></td>
<td><?= htmlspecialchars($row['year']) ?></td>
<td class="amount-col">₱<?= number_format($row['amount'], 2) ?></td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="4">
<div class="empty-state">
<i class="icon-inbox"></i>
<h4>No budgets found</h4>
<p>No budgets match your current filter.</p>
</div>
</td>
</tr>

<?php endif; ?>

</tbody>

</table>
</div>

</div>

</div>

</div>


<script>
$(document).ready(function(){
    $("#clearFilter").click(function(){
        window.location.href = window.location.pathname;
    });
});

$(document).on("click", ".clickable-row", function(){
    const url = $(this).data("href");
    if (url) window.location.href = url;
});
</script>

</body>
</html>