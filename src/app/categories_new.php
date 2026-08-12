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
   HANDLE FORM SUBMISSION
--------------------------------*/
if (isset($_POST['submit_category'])) {

    $name = mysqli_real_escape_string($conn, trim($_POST['category_name']));

    $query = "
        INSERT INTO expense_categories (
            category_name,
            category_created_at
        ) VALUES (
            '$name',
            NOW()
        )
    ";

    if (mysqli_query($conn, $query)) {

        // Log action (same style as expense_new)
        $category_id = mysqli_insert_id($conn);

        $logQuery = "INSERT INTO logs (log_action, log_user, log_details, log_date)
                     VALUES (
                        'Category created',
                        '" . mysqli_real_escape_string($conn, $_SESSION['username']) . "',
                        'Category: $name (Category ID: $category_id)',
                        NOW()
                     )";

        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Category added successfully!";
        header("Location: categories.php");
        exit();

    } else {
        $_SESSION['alert'] = "error";
    }
}

/* -------------------------------
   ALERT HANDLING
--------------------------------*/
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <?php if ($alert == "Category added successfully!") { ?>
                    <div class="alert alert-success">Category added successfully!</div>
                <?php } elseif ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to save category.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Category Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Category Name -->
                            <div class="control-group">
                                <label class="control-label">Category Name:</label>
                                <div class="controls">
                                    <input 
                                        type="text" 
                                        class="span11" 
                                        name="category_name"
                                        placeholder="Enter category name"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="submit_category" class="btn btn-success">
                                    Save Category
                                </button>

                                <a href="categories.php" class="btn btn-secondary">
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