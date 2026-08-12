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

if (!$isSuperAdmin) {
    header("Location: budgets.php");
    exit();
}
/* -------------------------------
   VALIDATE ID
--------------------------------*/
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: budgets.php");
    exit();
}

$budget_id = intval($_GET['id']);

/* -------------------------------
   FETCH BUDGET
--------------------------------*/
$query = "SELECT * FROM budgets WHERE budget_id = $budget_id LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: budgets.php");
    exit();
}

$budget = mysqli_fetch_assoc($result);

/* -------------------------------
   HANDLE DELETE (UPDATED)
--------------------------------*/
if (isset($_POST['confirm_delete'])) {

    $deleteStmt = $conn->prepare("DELETE FROM budgets WHERE budget_id = ?");
    $deleteStmt->bind_param("i", $budget_id);

    if ($deleteStmt->execute()) {

        // Log deletion
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);

        // Fetch company name for a readable log entry
        $companyNameStmt = $conn->prepare("SELECT company_name FROM companies WHERE company_id = ?");
        $companyNameStmt->bind_param("i", $budget['company_id']);
        $companyNameStmt->execute();
        $companyNameRow = $companyNameStmt->get_result()->fetch_assoc();
        $companyNameStmt->close();

        $companyName = mysqli_real_escape_string($conn, $companyNameRow['company_name'] ?? 'Unknown Company');
        $periodLabel = $months[$budget['month']] . ' ' . $budget['year'];

        mysqli_query($conn, "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Budget deleted', '$username', 'Period: $periodLabel, Company: $companyName (Budget ID: $budget_id)', NOW())
        ");

        // MATCH expense_delete.php behavior
        $_SESSION['alert'] = "Budget deleted successfully!";

        header("Location: budgets.php");
        exit();

    } else {

        $_SESSION['alert'] = "error";

    }
    }

    $deleteStmt->close();
}

/* -------------------------------
   ALERT DISPLAY (MATCH EXPENSE)
--------------------------------*/
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

/* -------------------------------
   MONTH FORMAT
--------------------------------*/
$months = [
    1=>"January",2=>"February",3=>"March",4=>"April",
    5=>"May",6=>"June",7=>"July",8=>"August",
    9=>"September",10=>"October",11=>"November",12=>"December"
];
?>

<link rel="stylesheet" href="css/layout.css" />

<div id="content">
<div class="container-fluid">

<div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
<div class="span12">

<?php if ($alert == "Budget deleted successfully!") { ?>
    <div class="alert alert-success">Budget deleted successfully!</div>
<?php } elseif ($alert == "error") { ?>
    <div class="alert alert-danger">Error: Unable to delete budget.</div>
<?php } elseif ($alert == "invalid") { ?>
    <div class="alert alert-warning">Invalid budget ID.</div>
<?php } elseif ($alert == "not_found") { ?>
    <div class="alert alert-warning">Budget not found.</div>
<?php } ?>

<div class="widget-box" style="max-width: 800px; margin: 0 auto;">

<div class="widget-title">
<span class="icon"><i class="icon-trash"></i></span>
<h5>Delete Budget Confirmation</h5>
</div>

<div class="widget-content" style="padding: 20px;">

<p>Are you sure you want to delete this budget?</p>

<table class="table table-bordered table-striped">

<tr>
<th>ID</th>
<td><?= htmlspecialchars($budget['budget_id']) ?></td>
</tr>

<tr>
<th>Month</th>
<td><?= htmlspecialchars($months[$budget['month']] ?? $budget['month']) ?></td>
</tr>

<tr>
<th>Year</th>
<td><?= htmlspecialchars($budget['year']) ?></td>
</tr>

<tr>
<th>Amount</th>
<td>₱<?= number_format($budget['amount'], 2) ?></td>
</tr>

</table>

<form method="post" style="margin-top: 20px;">

<div class="form-actions action-buttons">

<button type="submit" name="confirm_delete" class="btn btn-danger">
Confirm Delete
</button>

<a href="budgets.php" class="btn btn-secondary">
Cancel
</a>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

</div>

<?php include "footer.php"; ?>