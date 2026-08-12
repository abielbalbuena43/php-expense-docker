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

// Fetch company details for the given ID
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
        echo "<div class='alert alert-danger'>You are not authorized to edit this company.</div>";
        exit();
    }
}

// Handle the form submission for updating company details
if (isset($_POST['update_company'])) {
    // Retrieve form data
    $company_lookup = trim($_POST['company_lookup'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $company_tin = preg_replace('/\D+/', '', $_POST['company_tin'] ?? '');

    if (($company_name === '' || $company_tin === '') && preg_match('/^(\d+)\s*-\s*(.+)$/', $company_lookup, $matches)) {
        $company_tin = trim($matches[1]);
        $company_name = trim($matches[2]);
    }

    $company_name = mysqli_real_escape_string($conn, $company_name);
    $company_tin = mysqli_real_escape_string($conn, $company_tin);
    $rdo_code = mysqli_real_escape_string($conn, trim($_POST['rdo_code']));
    $branch_code = mysqli_real_escape_string($conn, trim($_POST['branch_code']));
    $trade_name = mysqli_real_escape_string($conn, trim($_POST['trade_name']));
    $substreet = mysqli_real_escape_string($conn, trim($_POST['substreet']));
    $street = mysqli_real_escape_string($conn, trim($_POST['street']));
    $barangay = mysqli_real_escape_string($conn, trim($_POST['barangay']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $province = mysqli_real_escape_string($conn, trim($_POST['province']));
    $zip_code = mysqli_real_escape_string($conn, trim($_POST['zip_code']));
    $special_fields = mysqli_real_escape_string($conn, trim($_POST['special_fields']));

    if ($company_name === '' || $company_tin === '') {
        $_SESSION['alert'] = "invalid_company_lookup";
    } else {
        // Update company data in the database
        $update_query = "
            UPDATE companies SET
                company_name = '$company_name',
                company_tin = '$company_tin',
                rdo_code = '$rdo_code',
                branch_code = '$branch_code',
                trade_name = '$trade_name',
                substreet = '$substreet',
                street = '$street',
                barangay = '$barangay',
                city = '$city',
                province = '$province',
                zip_code = '$zip_code',
                special_fields = '$special_fields'
            WHERE company_id = '$company_id'
        ";

        if (mysqli_query($conn, $update_query)) {
            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('Company updated', '$username', 'Company ID: $company_id, Name: $company_name', NOW())
            ";
            mysqli_query($conn, $logQuery);

            $_SESSION['alert'] = "Company updated successfully!";
            header("Location: companies.php");
            exit();
        } else {
            $_SESSION['alert'] = "error_update";
        }
    }
}

$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);

$companies = [];
$companyOptionsResult = mysqli_query($conn, "
    SELECT company_name, company_tin
    FROM companies
    WHERE company_id != '$company_id'
    ORDER BY company_tin ASC, company_name ASC
");

if ($companyOptionsResult) {
    while ($companyRow = mysqli_fetch_assoc($companyOptionsResult)) {
        $companies[] = [
            'company_name' => $companyRow['company_name'],
            'company_tin' => preg_replace('/\D+/', '', $companyRow['company_tin'])
        ];
    }
}
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- Display success or error alerts -->
                <?php if ($alert == "Company updated successfully!") { ?>
                    <div class="alert alert-success">Company updated successfully!</div>
                <?php } elseif ($alert == "invalid_company_lookup") { ?>
                    <div class="alert alert-danger">Enter the company as TIN - Company Name or choose one from the suggestions.</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update company.</div>
                <?php } ?>

                <!-- Edit Company Form -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit Company Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Company / TIN -->
                            <div class="control-group">
                                <label class="control-label">TIN / Company:</label>
                                <div class="controls">
                                    <input type="hidden" name="company_name" id="company_name" value="<?= htmlspecialchars($company['company_name']) ?>">
                                    <input type="hidden" name="company_tin" id="company_tin" value="<?= htmlspecialchars(preg_replace('/\D+/', '', $company['company_tin'])) ?>">
                                    <input
                                        type="text"
                                        class="span11"
                                        name="company_lookup"
                                        id="company_lookup"
                                        list="companySuggestions"
                                        value="<?= htmlspecialchars(preg_replace('/\D+/', '', $company['company_tin']) . ' - ' . $company['company_name']) ?>"
                                        placeholder="Type TIN digits, then select or enter as 123456 - Company Name"
                                        autocomplete="off"
                                        required
                                    >
                                    <datalist id="companySuggestions"></datalist>
                                </div>
                            </div>

                            <!-- RDO Code -->
                            <div class="control-group">
                                <label class="control-label">RDO Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="rdo_code" value="<?= htmlspecialchars($company['rdo_code']) ?>" placeholder="Type RDO code" required>
                                </div>
                            </div>

                            <!-- Branch Code -->
                            <div class="control-group">
                                <label class="control-label">Branch Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="branch_code" value="<?= htmlspecialchars($company['branch_code']) ?>" placeholder="Type branch code" required>
                                </div>
                            </div>

                            <!-- Trade Name -->
                            <div class="control-group">
                                <label class="control-label">Trade Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="trade_name" value="<?= htmlspecialchars($company['trade_name']) ?>" placeholder="Type trade name" required>
                                </div>
                            </div>

                            <!-- Address Fields -->
                            <div class="control-group">
                                <label class="control-label">Substreet:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="substreet" value="<?= htmlspecialchars($company['substreet']) ?>" placeholder="Type substreet">
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Street:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="street" value="<?= htmlspecialchars($company['street']) ?>" placeholder="Type street">
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Barangay:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="barangay" value="<?= htmlspecialchars($company['barangay']) ?>" placeholder="Type barangay">
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">City:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="city" value="<?= htmlspecialchars($company['city']) ?>" placeholder="Type city">
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Province:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="province" value="<?= htmlspecialchars($company['province']) ?>" placeholder="Type province">
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Zip Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="zip_code" value="<?= htmlspecialchars($company['zip_code']) ?>" placeholder="Type zip code">
                                </div>
                            </div>

                            <!-- Special Fields -->
                            <div class="control-group">
                                <label class="control-label">Special Fields:</label>
                                <div class="controls">
                                    <textarea class="span11" name="special_fields" placeholder="Type special fields"><?= htmlspecialchars($company['special_fields']) ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_company" class="btn btn-success">Update Company</button>
                                <a href="companies.php" class="btn btn-secondary">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<script>
const companies = <?= json_encode($companies) ?>;

function updateCompanySuggestions() {
    const lookupInput = document.getElementById('company_lookup');
    const datalist = document.getElementById('companySuggestions');
    const hiddenTin = document.getElementById('company_tin');
    const hiddenName = document.getElementById('company_name');

    if (!lookupInput || !datalist || !hiddenTin || !hiddenName) {
        return;
    }

    const rawValue = lookupInput.value.trim();
    const digits = rawValue.replace(/\D+/g, '');

    datalist.innerHTML = '';

    if (rawValue === '') {
        hiddenTin.value = '';
        hiddenName.value = '';
        return;
    }

    const exactCurrentValue = companies.find(function(company) {
        return rawValue === (company.company_tin + ' - ' + company.company_name);
    });

    if (exactCurrentValue) {
        hiddenTin.value = exactCurrentValue.company_tin;
        hiddenName.value = exactCurrentValue.company_name;
    } else {
        const manualMatch = rawValue.match(/^(\d+)\s*-\s*(.+)$/);

        if (manualMatch) {
            hiddenTin.value = manualMatch[1];
            hiddenName.value = manualMatch[2].trim();
        } else {
            hiddenTin.value = '';
            hiddenName.value = '';
        }
    }

    const matches = companies
        .filter(function(company) {
            return digits !== '' && company.company_tin.indexOf(digits) === 0;
        })
        .slice(0, 8);

    matches.forEach(function(company) {
        const option = document.createElement('option');
        option.value = company.company_tin + ' - ' + company.company_name;
        datalist.appendChild(option);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const lookupInput = document.getElementById('company_lookup');

    if (!lookupInput) {
        return;
    }

    lookupInput.addEventListener('input', updateCompanySuggestions);
    lookupInput.addEventListener('change', updateCompanySuggestions);
    updateCompanySuggestions();
});
</script>
