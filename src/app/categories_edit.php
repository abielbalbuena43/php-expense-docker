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

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: categories.php");
    exit();
}

$category_id = intval($_GET['id']);

// Fetch current category record
$category_query = mysqli_query($conn, "SELECT * FROM expense_categories WHERE category_id = '$category_id' LIMIT 1");
if (mysqli_num_rows($category_query) === 0) {
    echo "<div class='alert alert-danger'>Category record not found.</div>";
    exit();
}
$category = mysqli_fetch_assoc($category_query);

// Handle update form submission
if (isset($_POST['update_category'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['category_name']));

    $query = "
        UPDATE expense_categories SET
            category_name = '$name'
        WHERE category_id = '$category_id'
    ";

    if (mysqli_query($conn, $query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Category updated', '$username', 'Category: $name (Category ID: $category_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Category updated successfully!";
        header("Location: categories.php");
        exit();
    } else {
        $_SESSION['alert'] = "error_update";
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

                <?php if ($alert == "Category updated successfully!") { ?>
                    <div class="alert alert-success">Category updated successfully!</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update category.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit Category Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Category Name -->
                            <div class="control-group">
                                <label class="control-label">Category Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" name="category_name"
                                           value="<?= htmlspecialchars($category['category_name']) ?>" required>
                                </div>
                            </div>

                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_category" class="btn btn-success">Update Category</button>
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