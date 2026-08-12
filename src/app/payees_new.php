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

// Handle form submission
if (isset($_POST['submit_payee'])) {
    $name = mysqli_real_escape_string($conn, $_POST['payee_name']);
    $type = mysqli_real_escape_string($conn, $_POST['payee_type']);
    $tin = !empty($_POST['payee_tin']) ? mysqli_real_escape_string($conn, $_POST['payee_tin']) : NULL;
    $category = !empty($_POST['payee_category']) ? mysqli_real_escape_string($conn, $_POST['payee_category']) : NULL;
    $address1 = !empty($_POST['payee_address1']) ? mysqli_real_escape_string($conn, $_POST['payee_address1']) : NULL;
    $address2 = !empty($_POST['payee_address2']) ? mysqli_real_escape_string($conn, $_POST['payee_address2']) : NULL;

    $query = "
        INSERT INTO payees (
            payee_name,
            payee_type,
            payee_tin,
            payee_category,
            payee_address1,
            payee_address2,
            payee_created_at
        ) VALUES (
            '$name',
            '$type',
            " . ($tin ? "'$tin'" : "NULL") . ",
            " . ($category ? "'$category'" : "NULL") . ",
            " . ($address1 ? "'$address1'" : "NULL") . ",
            " . ($address2 ? "'$address2'" : "NULL") . ",
            NOW()
        )
    ";

    if (mysqli_query($conn, $query)) {
        $new_payee_id = mysqli_insert_id($conn);
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Payee created', '$username', 'Payee: $name (Payee ID: $new_payee_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Payee added successfully!";
        header("Location: payees.php");
        exit();
    } else {
        $_SESSION['alert'] = "error";
        echo "Database Error: " . mysqli_error($conn);
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

                <?php if ($alert == "Payee added successfully!") { ?>
                    <div class="alert alert-success">Payee added successfully!</div>
                <?php } elseif ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to save payee.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Payee Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Payee Name -->
                            <div class="control-group">
                                <label class="control-label">Payee Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="payee_name" placeholder="Type payee name" required />
                                </div>
                            </div>

                            <!-- Payee Type -->
                            <div class="control-group">
                                <label class="control-label">Payee Type:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="payee_type" placeholder="Type payee type" required />
                                </div>
                            </div>

                            <!-- Payee TIN -->
                            <div class="control-group">
                                <label class="control-label">TIN (Optional):</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="payee_tin" placeholder="Type TIN digits only" />
                                </div>
                            </div>

                            <!-- Payee Category -->
                            <div class="control-group">
                                <label class="control-label">Category (Optional):</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="payee_category" placeholder="Type category" />
                                </div>
                            </div>

                            <!-- Address 1 -->
                            <div class="control-group">
                                <label class="control-label">Address 1 (Optional):</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="payee_address1" placeholder="Type address line 1" />
                                </div>
                            </div>

                            <!-- Address 2 -->
                            <div class="control-group">
                                <label class="control-label">Address 2 (Optional):</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="payee_address2" placeholder="Type address line 2" />
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="submit_payee" class="btn btn-success">
                                    Save Payee
                                </button>

                                <a href="payees.php" class="btn btn-secondary">
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