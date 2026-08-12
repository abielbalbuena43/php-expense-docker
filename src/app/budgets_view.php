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

/* -------------------------------
   VALIDATE ID
--------------------------------*/
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-error'>Invalid Budget ID.</div>";
    exit();
}

$budget_id = intval($_GET['id']);

/* -------------------------------
   FETCH BUDGET
--------------------------------*/
$query = "SELECT * FROM budgets WHERE budget_id = '$budget_id' LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-error'>Budget record not found.</div>";
    exit();
}

$budget = mysqli_fetch_assoc($result);

// Company scope guard
if (!$isSuperAdmin) {
    $assignedCompanyIds = [];
    $ucStmt = $conn->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
    $ucStmt->bind_param("i", $_SESSION['user_id']);
    $ucStmt->execute();
    $ucResult = $ucStmt->get_result();
    while ($ucRow = $ucResult->fetch_assoc()) {
        $assignedCompanyIds[] = $ucRow['company_id'];
    }
    $ucStmt->close();

    if (!in_array($budget['company_id'], $assignedCompanyIds)) {
        echo "<div class='alert alert-error'>You are not authorized to view this budget.</div>";
        exit();
    }
}

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

                <!-- View Budget -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Budget Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <!-- Company -->
                            <div class="control-group">
                                <label class="control-label">Company:</label>
                                <div class="controls">
                                    <input 
                                        type="text" 
                                        class="span11"
                                        value="<?php
                                            if (!empty($budget['company_id'])) {
                                                $compStmt = $conn->prepare("SELECT company_name FROM companies WHERE company_id = ?");
                                                $compStmt->bind_param("i", $budget['company_id']);
                                                $compStmt->execute();
                                                $compRow = $compStmt->get_result()->fetch_assoc();
                                                echo htmlspecialchars($compRow['company_name'] ?? 'Unknown');
                                                $compStmt->close();
                                            } else {
                                                echo 'Unassigned';
                                            }
                                        ?>"
                                        disabled
                                    >
                                </div>
                            </div>

                            <!-- Month -->
                            <div class="control-group">
                                <label class="control-label">Month:</label>
                                <div class="controls">
                                    <input 
                                        type="text" 
                                        class="span11"
                                        value="<?= htmlspecialchars($months[$budget['month']] ?? $budget['month']) ?>"
                                        disabled
                                    >
                                </div>
                            </div>

                            <!-- Year -->
                            <div class="control-group">
                                <label class="control-label">Year:</label>
                                <div class="controls">
                                    <input 
                                        type="text" 
                                        class="span11"
                                        value="<?= htmlspecialchars($budget['year']) ?>"
                                        disabled
                                    >
                                </div>
                            </div>

                            <!-- Budget Amount -->
                            <div class="control-group">
                                <label class="control-label">Budget Amount:</label>
                                <div class="controls">
                                    <input 
                                        type="text" 
                                        class="span11"
                                        value="₱<?= number_format($budget['amount'], 2) ?>"
                                        disabled
                                    >
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <?php if ($isSuperAdmin || $isAdmin): ?>
                                <a href="budgets_edit.php?id=<?= $budget['budget_id'] ?>" class="btn btn-primary">
                                    Edit Budget
                                </a>
                                <?php endif; ?>
                                <?php if ($isSuperAdmin): ?>
                                <a href="budgets_delete.php?id=<?= $budget['budget_id'] ?>" 
                                class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to delete this budget?');">
                                    Delete Budget
                                </a>
                                <?php endif; ?>

                                <a href="budgets.php" class="btn btn-secondary">
                                    Back
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