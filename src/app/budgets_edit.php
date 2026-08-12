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
    header("Location: budgets.php");
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
   VALIDATE ID
--------------------------------*/
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: budgets.php");
    exit();
}

$budget_id = intval($_GET['id']);

/* -------------------------------
   FETCH RECORD
--------------------------------*/
$budget_query = mysqli_query($conn, "SELECT * FROM budgets WHERE budget_id = '$budget_id' LIMIT 1");

if (mysqli_num_rows($budget_query) === 0) {
    setAlert('error', 'Budget record not found.');
    header("Location: budgets.php");
    exit();
}

$budget = mysqli_fetch_assoc($budget_query);

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

    if (!in_array($budget['company_id'], $assignedCompanyIds)) {
        setAlert('error', 'You are not authorized to edit this budget.');
        header("Location: budgets.php");
        exit();
    }
}

// Fetch companies scoped by role for the dropdown
if ($isSuperAdmin) {
    $companiesResult = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC");
} else {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $compStmt = $conn->prepare("SELECT company_id, company_name FROM companies WHERE company_id IN ($placeholders) ORDER BY company_name ASC");
    $compStmt->bind_param(str_repeat('i', count($assignedCompanyIds)), ...$assignedCompanyIds);
    $compStmt->execute();
    $companiesResult = $compStmt->get_result();
}

$companyRows = [];
while ($row = $companiesResult->fetch_assoc()) {
    $companyRows[] = $row;
}

/* -------------------------------
   HANDLE UPDATE
--------------------------------*/
if (isset($_POST['update_budget'])) {

    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $amount = floatval($_POST['amount']);

    if ($isSuperAdmin) {
        $company_id = intval($_POST['company_id']);
    } else {
        $submittedCompanyId = intval($_POST['company_id']);
        $company_id = in_array($submittedCompanyId, $assignedCompanyIds)
            ? $submittedCompanyId
            : intval($budget['company_id']);
    }

    $checkStmt = $conn->prepare("
        SELECT budget_id 
        FROM budgets 
        WHERE month = ? 
        AND year = ? 
        AND company_id = ?
        AND budget_id != ?
    ");
    $checkStmt->bind_param("iiii", $month, $year, $company_id, $budget_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();

    if ($checkResult->num_rows > 0) {

        setAlert('error', 'A budget for this period already exists for this company.');

    } else {

        $updateStmt = $conn->prepare("
            UPDATE budgets 
            SET month = ?, year = ?, amount = ?, company_id = ?
            WHERE budget_id = ?
        ");
        $updateStmt->bind_param("iidii", $month, $year, $amount, $company_id, $budget_id);

        if ($updateStmt->execute()) {

            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            $monthsArr = [
                1=>"January",2=>"February",3=>"March",4=>"April",
                5=>"May",6=>"June",7=>"July",8=>"August",
                9=>"September",10=>"October",11=>"November",12=>"December"
            ];

            // Fetch company name for a readable log entry
            $companyNameStmt = $conn->prepare("SELECT company_name FROM companies WHERE company_id = ?");
            $companyNameStmt->bind_param("i", $company_id);
            $companyNameStmt->execute();
            $companyNameRow = $companyNameStmt->get_result()->fetch_assoc();
            $companyNameStmt->close();

            $companyName = mysqli_real_escape_string($conn, $companyNameRow['company_name'] ?? 'Unknown Company');
            $periodLabel = $monthsArr[$month] . ' ' . $year;

            mysqli_query($conn, "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('Budget updated', '$username', 'Period: $periodLabel, Company: $companyName (Budget ID: $budget_id)', NOW())
            ");

            header("Location: budgets.php?success=edited");
            exit();

        } else {
            setAlert('error', 'Error updating budget: ' . $conn->error);
        }

        $updateStmt->close();
    }
}
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
<div class="container-fluid">

<div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
<div class="span12">

<?php if (isset($_SESSION['alert'])): ?>
<?php
$alert = $_SESSION['alert'];
$type = $alert['type'] ?? 'info';
$message = $alert['message'] ?? '';
unset($_SESSION['alert']);
?>
<div class="alert alert-<?= $type ?>">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- MATCHED LAYOUT -->
<div class="widget-box" style="max-width: 800px; margin: 0 auto;">

<div class="widget-title">
<h5>Edit Budget Information</h5>
</div>

<div class="widget-content" style="padding: 20px;">

<form method="post" class="form-horizontal">

<!-- Company -->
<div class="control-group">
<label class="control-label">Company:</label>
<div class="controls">
<select name="company_id" class="span11" required>
<?php foreach ($companyRows as $row): ?>
<option value="<?= $row['company_id'] ?>" <?= $budget['company_id'] == $row['company_id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($row['company_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>

<!-- Month -->
<div class="control-group">
<label class="control-label">Month:</label>
<div class="controls">

<select name="month" class="span11" required>

<?php
$months = [
1=>"January",2=>"February",3=>"March",4=>"April",
5=>"May",6=>"June",7=>"July",8=>"August",
9=>"September",10=>"October",11=>"November",12=>"December"
];

foreach($months as $num=>$name){
$sel = ($budget['month']==$num) ? 'selected' : '';
echo "<option value='$num' $sel>$name</option>";
}
?>

</select>

</div>
</div>

<!-- Year -->
<div class="control-group">
<label class="control-label">Year:</label>
<div class="controls">

<select name="year" class="span11" required>

<?php
$currentYear = date('Y');
for ($y=$currentYear; $y>=$currentYear-10; $y--){
$sel = ($budget['year']==$y) ? 'selected' : '';
echo "<option value='$y' $sel>$y</option>";
}
?>

</select>

</div>
</div>

<!-- Amount -->
<div class="control-group">
<label class="control-label">Budget Amount:</label>
<div class="controls">

<input
type="number"
step="0.01"
class="span11"
name="amount"
value="<?= htmlspecialchars($budget['amount']) ?>"
required
>

</div>
</div>

<!-- ACTIONS -->
<div class="form-actions action-buttons">

<button type="submit" name="update_budget" class="btn btn-success">
Update Budget
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