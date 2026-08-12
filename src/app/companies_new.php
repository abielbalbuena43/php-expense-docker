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

if (!$isSuperAdmin) {
    header("Location: companies.php");
    exit();
}

if (isset($_POST['submit_company'])) {
    $company_name = mysqli_real_escape_string($conn, trim($_POST['company_name']));
    $company_tin = mysqli_real_escape_string($conn, preg_replace('/\D+/', '', $_POST['company_tin'] ?? ''));
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

    $query = "
        INSERT INTO companies (
            company_name, company_tin, rdo_code, branch_code, trade_name,
            substreet, street, barangay, city, province, zip_code, special_fields, company_created_at
        ) VALUES (
            '$company_name', '$company_tin', '$rdo_code', '$branch_code', '$trade_name',
            '$substreet', '$street', '$barangay', '$city', '$province', '$zip_code', '$special_fields', NOW()
        )
    ";

    if (mysqli_query($conn, $query)) {
        $new_company_id = mysqli_insert_id($conn);
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Company created', '$username', 'Company ID: $new_company_id, Name: $company_name', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Company added successfully!";
        header("Location: companies.php");
        exit();
    } else {
        $_SESSION['alert'] = "error";
    }
}

$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">
                <?php if ($alert == "Company added successfully!") { ?>
                    <div class="alert alert-success">Company added successfully!</div>
                <?php } elseif ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to save company.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Add New Company</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <div class="control-group">
                                <label class="control-label">Company Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="company_name" placeholder="Type company name" required>
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">TIN:</label>
                                <div class="controls">
                                    <input
                                        type="text"
                                        class="span11"
                                        name="company_tin"
                                        placeholder="Type TIN digits only"
                                        autocomplete="off"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">RDO Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="rdo_code" placeholder="Type RDO code">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Branch Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="branch_code" placeholder="Type branch code">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Trade Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="trade_name" placeholder="Type trade name">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Substreet:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="substreet" placeholder="Type substreet">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Street:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="street" placeholder="Type street">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Barangay:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="barangay" placeholder="Type barangay">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">City:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="city" placeholder="Type city">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Province:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="province" placeholder="Type province">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Zip Code:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="zip_code" placeholder="Type zip code">
                                </div>
                            </div>

                            <div class="control-group">
                                <label class="control-label">Special Fields:</label>
                                <div class="controls">
                                    <textarea class="span11" name="special_fields" placeholder="Type special fields"></textarea>
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="submit_company" class="btn btn-success">Save Company</button>
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