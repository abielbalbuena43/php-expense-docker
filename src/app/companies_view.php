<?php
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

// Check if company ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Company ID.</div>";
    exit();
}

$company_id = intval($_GET['id']);

// Fetch company details
$query = "
    SELECT * 
    FROM companies
    WHERE company_id = '$company_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>Company record not found.</div>";
    exit();
}

$company = mysqli_fetch_assoc($result);

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

    if (!in_array($company['company_id'], $assignedCompanyIds)) {
        echo "<div class='alert alert-danger'>You are not authorized to view this company.</div>";
        exit();
    }
}
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- Company Information -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Company Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <!-- Company / TIN -->
                            <div class="control-group">
                                <label class="control-label">TIN / Company:</label>
                                <div class="controls">
                                    <input
                                        type="text"
                                        class="span11"
                                        value="<?= htmlspecialchars($company['company_tin'] . ' - ' . $company['company_name']) ?>"
                                        disabled
                                    >
                                </div>
                            </div>

                            <!-- RDO Code -->
                            <div class="control-group">
                                <label class="control-label">RDO Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['rdo_code']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Branch Code -->
                            <div class="control-group">
                                <label class="control-label">Branch Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['branch_code']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Trade Name -->
                            <div class="control-group">
                                <label class="control-label">Trade Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['trade_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="control-group">
                                <label class="control-label">Substreet:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['substreet']) ?>" disabled>
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Street:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['street']) ?>" disabled>
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Barangay:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['barangay']) ?>" disabled>
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">City:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['city']) ?>" disabled>
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Province:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['province']) ?>" disabled>
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Zip Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= htmlspecialchars($company['zip_code']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Special Fields -->
                            <div class="control-group">
                                <label class="control-label">Special Fields:</label>
                                <div class="controls">
                                    <textarea class="span11" disabled><?= htmlspecialchars($company['special_fields']) ?></textarea>
                                </div>
                            </div>

                            <!-- Created At -->
                            <div class="control-group">
                                <label class="control-label">Created At:</label>
                                <div class="controls">
                                    <input type="text" class="span11" value="<?= date('M d, Y H:i:s', strtotime($company['company_created_at'])) ?>" disabled>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <?php if ($isSuperAdmin || $isAdmin): ?>
                                <a href="companies_edit.php?id=<?= $company['company_id'] ?>" class="btn btn-primary">Edit Company</a>
                                <?php endif; ?>
                                <?php if ($isSuperAdmin): ?>
                                <a href="companies_delete.php?id=<?= $company['company_id'] ?>" class="btn btn-danger">Delete Company</a>
                                <?php endif; ?>
                                <a href="companies.php" class="btn btn-secondary">Back</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
