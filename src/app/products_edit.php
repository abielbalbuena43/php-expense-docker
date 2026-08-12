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
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

// Fetch product record
$query = mysqli_query($conn, "SELECT * FROM expense_products WHERE product_id = '$product_id' LIMIT 1");
if (mysqli_num_rows($query) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: products.php");
    exit();
}

$product = mysqli_fetch_assoc($query);

// Handle update
if (isset($_POST['update_product'])) {
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);

    if (!empty($product_name)) {
        $update_query = "
            UPDATE expense_products 
            SET product_name = '$product_name'
            WHERE product_id = '$product_id'
        ";

        if (mysqli_query($conn, $update_query)) {
            $username = mysqli_real_escape_string($conn, $_SESSION['username']);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('Product updated', '$username', 'Product: $product_name (Product ID: $product_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            $_SESSION['alert'] = "Product updated successfully!";
            header("Location: products.php");
            exit();
        } else {
            $_SESSION['alert'] = "error_update";
        }
    } else {
        $_SESSION['alert'] = "empty_fields";
    }
}

$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        <div class="row-fluid" style="background-color: white; min-height: 500px; padding: 20px;">
            <div class="span12">

                <!-- Alert Messages -->
                <?php if ($alert == "Product updated successfully!") { ?>
                    <div class="alert alert-success">Product updated successfully!</div>
                <?php } elseif ($alert == "error_update") { ?>
                    <div class="alert alert-danger">Error: Unable to update product.</div>
                <?php } elseif ($alert == "empty_fields") { ?>
                    <div class="alert alert-warning">Please fill in all required fields.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">Product not found.</div>
                <?php } ?>

                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Edit Product</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <form action="" method="post" class="form-horizontal">

                            <!-- Product Name -->
                            <div class="control-group">
                                <label class="control-label">Product Name:</label>
                                <div class="controls">
                                    <input type="text" name="product_name" class="span11" 
                                           value="<?= htmlspecialchars($product['product_name']) ?>" required>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions action-buttons">
                                <button type="submit" name="update_product" class="btn btn-success">Update Product</button>
                                <a href="products.php" class="btn btn-secondary">Cancel</a>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>