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

// Validate category ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Category ID.</div>";
    exit();
}

$category_id = intval($_GET['id']);

// Fetch category details
$query = "
    SELECT * 
    FROM expense_categories 
    WHERE category_id = '$category_id'
    LIMIT 1
";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>Category record not found.</div>";
    exit();
}

$category = mysqli_fetch_assoc($result);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
            <div class="span12">

                <!-- View Category -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Category Information</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form class="form-horizontal">

                            <!-- Category Name -->
                            <div class="control-group">
                                <label class="control-label">Category Name:</label>
                                <div class="controls">
                                    <input type="text" class="span11" 
                                           value="<?= htmlspecialchars($category['category_name']) ?>" disabled>
                                </div>
                            </div>

                            <!-- Created At -->
                            <div class="control-group">
                                <label class="control-label">Created At:</label>
                                <div class="controls">
                                    <input type="text" class="span11" 
                                           value="<?= date('M d, Y h:i A', strtotime($category['category_created_at'])) ?>" disabled>
                                </div>
                            </div>

                            <!-- Updated At -->
                            <div class="control-group">
                                <label class="control-label">Last Updated:</label>
                                <div class="controls">
                                    <input type="text" class="span11" 
                                           value="<?= isset($category['category_updated_at']) ? date('M d, Y h:i A', strtotime($category['category_updated_at'])) : 'Never' ?>" disabled>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <?php if ($isSuperAdmin || $isAdmin): ?>
                                <a href="categories_edit.php?id=<?= $category['category_id'] ?>" class="btn btn-primary">Edit Category</a>
                                <a href="categories_delete.php?id=<?= $category['category_id'] ?>" class="btn btn-danger">Delete Category</a>
                                <?php endif; ?>
                                <a href="categories.php" class="btn btn-secondary">Back</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>