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

// Check if category ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: categories.php");
    exit();
}

$category_id = intval($_GET['id']);

// Fetch the category details for confirmation
$query = "
    SELECT 
        category_id,
        category_name
    FROM expense_categories
    WHERE category_id = $category_id
";
$result = mysqli_query($conn, $query);

// If no category found, redirect
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: categories.php");
    exit();
}

$category = mysqli_fetch_assoc($result);

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM expense_categories WHERE category_id = $category_id";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $categoryName = mysqli_real_escape_string($conn, $category['category_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Category deleted', '$username', 'Category: $categoryName (Category ID: $category_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Category deleted successfully!";
        header("Location: categories.php");
        exit();
    } else {
        $_SESSION['alert'] = "error";
    }
}

// Alert messages
$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 300px; padding: 20px;">
            <div class="span12">

                <!-- Alert Messages -->
                <?php if ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to delete category.</div>
                <?php } elseif ($alert == "invalid") { ?>
                    <div class="alert alert-warning">Invalid category ID.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">Category not found.</div>
                <?php } elseif ($alert == "Category deleted successfully!") { ?>
                    <div class="alert alert-success">Category deleted successfully!</div>
                <?php } ?>

                <!-- Delete Confirmation -->
                <div class="widget-box" style="max-width: 600px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Delete Category Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following category?</p>

                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($category['category_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?= htmlspecialchars($category['category_name']) ?></td>
                            </tr>
                        </table>

                        <form method="post">
                        <div class="form-actions action-buttons">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="icon-trash"></i> Confirm Delete
                            </button>
                            <a href="categories.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>