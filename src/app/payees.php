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

    if ($_GET['success'] === 'Payee added successfully!') {
        setAlert('success', 'Payee added successfully!');
    }

    if ($_GET['success'] === 'Payee updated successfully!') {
        setAlert('success', 'Payee updated successfully!');
    }

    if ($_GET['success'] === 'Payee deleted successfully!') {
        setAlert('success', 'Payee deleted successfully!');
    }

}

/* -------------------------------
   FETCH PAYEES
--------------------------------*/
$sql = "
SELECT 
    payee_id,
    payee_name,
    payee_type,
    payee_tin,
    payee_category,
    payee_address1,
    payee_address2,
    payee_created_at
FROM payees
ORDER BY payee_created_at DESC
LIMIT 100
";

$result = $conn->query($sql);
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

<title>Payees List</title>

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
<a href="payees_new.php" class="btn btn-success">
<i class="icon-plus"></i>
Create New Payee
</a>
<?php endif; ?>

</div>


<!-- Main Table -->

<div class="table-container">

<div class="table-header">

<h3>
Payees List
</h3>

<span class="table-stats">
Showing <?= $result->num_rows ?? 0 ?> records
</span>

</div>


<div class="table-responsive">

<table>

<thead>

<tr>
<th>Payee Name</th>
<th>Type</th>
<th>TIN</th>
<th>Category</th>
<th>Address</th>
<th>Date Created</th>
</tr>

</thead>


<tbody>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr
class="clickable-row"
data-href="payees_view.php?id=<?= $row['payee_id'] ?>"
>

<td><?= htmlspecialchars($row['payee_name']) ?></td>
<td><?= htmlspecialchars($row['payee_type']) ?></td>
<td><?= htmlspecialchars($row['payee_tin']) ?></td>
<td><?= htmlspecialchars($row['payee_category']) ?></td>
<td>
<?= htmlspecialchars($row['payee_address1']) ?>
<?= !empty($row['payee_address2']) ? ', ' . htmlspecialchars($row['payee_address2']) : '' ?>
</td>
<td><?= date('M d, Y', strtotime($row['payee_created_at'])) ?></td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="6">

<div class="empty-state">

<i class="icon-inbox"></i>

<h4>No payees found</h4>

<p>
No payees available.
Create a new payee to get started.
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