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

    if ($_GET['success'] === 'added') {
        setAlert('success', 'End User added successfully!');
    }

    if ($_GET['success'] === 'edited') {
        setAlert('success', 'End User updated successfully!');
    }

    if ($_GET['success'] === 'deleted') {
        setAlert('success', 'End User deleted successfully!');
    }

}

/* -------------------------------
   FETCH END USERS
--------------------------------*/
$sql = "
    SELECT 
        eu.end_user_id,
        eu.end_user_name,
        eu.created_at,
        COUNT(e.expense_id) AS total_expenses
    FROM expense_end_users eu
    LEFT JOIN expenses e ON eu.end_user_id = e.expense_user_id
    GROUP BY eu.end_user_id
    ORDER BY eu.end_user_name ASC
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

<title>End Users List</title>

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

<a href="end_users_new.php" class="btn btn-success" >
<i class="icon-plus"></i>
Create New End User
</a>

</div>


<!-- Main Table -->

<div class="table-container">

<div class="table-header">

<h3>
End Users List
</h3>

<span class="table-stats">
Showing <?= $result->num_rows ?? 0 ?> records
</span>

</div>


<div class="table-responsive">

<table>

<thead>

<tr>
    <th>ID</th>
    <th>End User Name</th>
    <th>Total Linked Expenses</th>
    <th>Date Created</th>
</tr>

</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr
class="clickable-row"
data-href="end_users_view.php?id=<?= $row['end_user_id'] ?>"
>

<td><?= htmlspecialchars($row['end_user_id']) ?></td>
<td><?= htmlspecialchars($row['end_user_name']) ?></td>
<td><?= htmlspecialchars($row['total_expenses']) ?></td>
<td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="4">

<div class="empty-state">

<i class="icon-inbox"></i>

<h4>No end users found</h4>

<p>
No end users available.
Create a new end user to get started.
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