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

// Fetch all resellers and count how many expenses are linked to each
$sql = "
    SELECT 
        r.reseller_id,
        r.reseller_name,
        r.reseller_created_at,
        COUNT(e.expense_id) AS total_expenses
    FROM resellers r
    LEFT JOIN expenses e ON r.reseller_id = e.expense_reseller_id
    GROUP BY r.reseller_id
    ORDER BY r.reseller_name ASC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resellers List</title>
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/layout.css">

    <!-- Icons -->
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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

<a href="resellers_new.php" class="btn btn-success" >
<i class="icon-plus"></i>
Create New Reseller
</a>

</div>

<!-- Main Table -->

<div class="table-container">

<div class="table-header">

<h3>
Resellers List
</h3>

<span class="table-stats">
Showing <?= $result->num_rows ?? 0 ?> records
</span>

</div>

<div class="table-responsive">

<table>

<thead>

<tr>
<th>Reseller Name</th>
<th>Total Linked Expenses</th>
<th>Date Created</th>
</tr>

</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr
class="clickable-row"
data-href="resellers_view.php?id=<?= $row['reseller_id'] ?>"
>

<td><?= htmlspecialchars($row['reseller_name']) ?></td>
<td><?= $row['total_expenses'] ?></td>
<td><?= date('M d, Y', strtotime($row['reseller_created_at'])) ?></td>
</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="4">

<div class="empty-state">

<i class="icon-inbox"></i>

<h4>No resellers found</h4>

<p>
No resellers available.
Create a new reseller to get started.
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