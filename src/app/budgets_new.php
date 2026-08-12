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
    header("Location: budgets.php");
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

// Fetch companies scoped by role
if ($isSuperAdmin) {
    $companiesResult = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC");
} elseif (!empty($assignedCompanyIds)) {
    $placeholders = implode(',', array_fill(0, count($assignedCompanyIds), '?'));
    $compStmt = $conn->prepare("SELECT company_id, company_name FROM companies WHERE company_id IN ($placeholders) ORDER BY company_name ASC");
    $compStmt->bind_param(str_repeat('i', count($assignedCompanyIds)), ...$assignedCompanyIds);
    $compStmt->execute();
    $companiesResult = $compStmt->get_result();
} else {
    $companiesResult = false;
}

$companyRows = [];
if ($companiesResult) {
    while ($row = $companiesResult->fetch_assoc()) {
        $companyRows[] = $row;
    }
}
$preselectedCompany = count($companyRows) === 1 ? $companyRows[0]['company_id'] : null;

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
   CSRF TOKEN
--------------------------------*/
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------------------
   HANDLE FORM SUBMISSION
--------------------------------*/
$monthsArr = [
    1=>"January",2=>"February",3=>"March",4=>"April",
    5=>"May",6=>"June",7=>"July",8=>"August",
    9=>"September",10=>"October",11=>"November",12=>"December"
];

if (isset($_POST['submit_budget'])) {

    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $amount = floatval($_POST['amount']);

    if ($isSuperAdmin) {
        $company_id = intval($_POST['company_id']);
    } else {
        $submittedCompanyId = intval($_POST['company_id']);
        $company_id = in_array($submittedCompanyId, $assignedCompanyIds)
            ? $submittedCompanyId
            : (!empty($assignedCompanyIds) ? intval($assignedCompanyIds[0]) : 0);
    }

    if ($company_id === 0) {
        setAlert('error', 'No valid company selected.');
        header("Location: budgets_new.php");
        exit();
    }

    // Prevent duplicate month/year for the same company
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM budgets WHERE month = ? AND year = ? AND company_id = ?");
    $checkStmt->bind_param("iii", $month, $year, $company_id);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {

        setAlert('error', 'A budget for ' . $monthsArr[$month] . ' ' . $year . ' already exists for this company.');

    } else {

        $insertStmt = $conn->prepare("
            INSERT INTO budgets (month, year, amount, company_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insertStmt->bind_param("iidi", $month, $year, $amount, $company_id);

        if ($insertStmt->execute()) {

            // Log creation
            $budget_id = $insertStmt->insert_id;
            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            // Fetch company name for a readable log entry
            $companyNameStmt = $conn->prepare("SELECT company_name FROM companies WHERE company_id = ?");
            $companyNameStmt->bind_param("i", $company_id);
            $companyNameStmt->execute();
            $companyNameRow = $companyNameStmt->get_result()->fetch_assoc();
            $companyNameStmt->close();

            $companyName = mysqli_real_escape_string($conn, $companyNameRow['company_name'] ?? 'Unknown Company');
            $periodLabel = $monthsArr[$month] . ' ' . $year;

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('Budget created', '$username', 'Period: $periodLabel, Company: $companyName (Budget ID: $budget_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            header("Location: budgets.php?success=added");
            exit();

        } else {

            setAlert('error', 'Database error: ' . $conn->error);

        }

        $insertStmt->close();
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

                <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>

                <?php endif; ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">

                    <div class="widget-title">
                        <h5>Budget Information</h5>
                    </div>

                    <div class="widget-content" style="padding:20px;">

                        <form method="post" class="form-horizontal">

                            <!-- Company -->
                            <div class="control-group">
                                <label class="control-label">Company:</label>
                                <div class="controls">
                                    <select name="company_id" class="span11" required>
                                        <?php if (!$preselectedCompany): ?>
                                        <option value="" disabled selected>Select Company</option>
                                        <?php endif; ?>
                                        <?php foreach ($companyRows as $row): ?>
                                            <option value="<?= $row['company_id'] ?>" <?= $preselectedCompany == $row['company_id'] ? 'selected' : '' ?>>
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
                                        $monthsArr = [
                                            1=>"January",2=>"February",3=>"March",4=>"April",
                                            5=>"May",6=>"June",7=>"July",8=>"August",
                                            9=>"September",10=>"October",11=>"November",12=>"December"
                                        ];
                                        foreach ($monthsArr as $num=>$name) {
                                            echo "<option value='$num'>$name</option>";
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
                                        for($y=$currentYear;$y>=$currentYear-10;$y--){
                                            echo "<option value='$y'>$y</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="control-group">
                                <label class="control-label">Amount:</label>
                                <div class="controls">
                                    <input type="number" class="span11" name="amount" step="0.01" placeholder="0.00" required>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="form-actions action-buttons">
                                <button type="submit" name="submit_budget" class="btn btn-success">
                                    Save Budget
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