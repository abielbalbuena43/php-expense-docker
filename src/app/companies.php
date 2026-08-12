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
        setAlert('success', 'Company added successfully!');
    }

    if ($_GET['success'] === 'edited') {
        setAlert('success', 'Company updated successfully!');
    }

    if ($_GET['success'] === 'deleted') {
        setAlert('success', 'Company deleted successfully!');
    }

}

/* -------------------------------
   FETCH COMPANIES
--------------------------------*/
if ($isSuperAdmin) {
    $sql = "
        SELECT 
            company_id, company_name, company_tin, rdo_code, branch_code,
            trade_name, street, barangay, city, province, zip_code, company_created_at
        FROM companies
        ORDER BY company_created_at DESC
        LIMIT 100
    ";
    $result = $conn->query($sql);
} elseif (!empty($assignedCompanyIds)) {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $sql = "
        SELECT 
            company_id, company_name, company_tin, rdo_code, branch_code,
            trade_name, street, barangay, city, province, zip_code, company_created_at
        FROM companies
        WHERE company_id IN ($placeholders)
        ORDER BY company_created_at DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($assignedCompanyIds)), ...$assignedCompanyIds);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Core CSS -->
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/layout.css">

<!-- Icons -->
<link href="font-awesome/css/font-awesome.css" rel="stylesheet">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<title>Companies List</title>

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

<?php if ($isSuperAdmin): ?>
<a href="companies_new.php" class="btn btn-success">
<i class="icon-plus"></i>
Create New Company
</a>
<?php endif; ?>

</div>


<!-- Main Table -->

<div class="table-container">

<div class="table-header">

<h3>
Companies List
</h3>

<span class="table-stats">
Showing <?= $result->num_rows ?? 0 ?> records
</span>

</div>


<div class="table-responsive">

<table>

<thead>

<tr>
<th>Company</th>
<th>RDO Code</th>
<th>Branch Code</th>
<th>Trade Name</th>
<th>City</th>
<th>Date Created</th>
</tr>

</thead>


<tbody>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr
class="clickable-row"
data-href="companies_view.php?id=<?= $row['company_id'] ?>"
>

<td>
    <strong><?= htmlspecialchars($row['company_name']) ?></strong><br>
    <small>TIN: <?= htmlspecialchars($row['company_tin']) ?></small>
</td>
<td><?= htmlspecialchars($row['rdo_code']) ?></td>
<td><?= htmlspecialchars($row['branch_code']) ?></td>
<td><?= htmlspecialchars($row['trade_name']) ?></td>
<td><?= htmlspecialchars($row['city']) ?></td>
<td><?= date('M d, Y', strtotime($row['company_created_at'])) ?></td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="6">

<div class="empty-state">

<i class="icon-inbox"></i>

<h4>No companies found</h4>

<p>
No companies available.
Create a new company to get started.
</p>

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

$(document).on("click", ".clickable-row", function(){

const url = $(this).data("href");

if (url) {
    window.location.href = url;
}

});

</script>

</body>
</html>
