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

// Check if a product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = "invalid";
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

// Fetch the product details for confirmation
$query = "
    SELECT 
        product_id,
        product_name,
        created_at
    FROM expense_products
    WHERE product_id = $product_id
";

$result = mysqli_query($conn, $query);

// If no product found, redirect
if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['alert'] = "not_found";
    header("Location: products.php");
    exit();
}

$product = mysqli_fetch_assoc($result);

// Handle delete confirmation
if (isset($_POST['confirm_delete'])) {
    $delete_query = "DELETE FROM expense_products WHERE product_id = $product_id";

    if (mysqli_query($conn, $delete_query)) {
        $username = mysqli_real_escape_string($conn, $_SESSION['username']);
        $productName = mysqli_real_escape_string($conn, $product['product_name']);

        $logQuery = "
            INSERT INTO logs (log_action, log_user, log_details, log_date)
            VALUES ('Product deleted', '$username', 'Product: $productName (Product ID: $product_id)', NOW())
        ";
        mysqli_query($conn, $logQuery);

        $_SESSION['alert'] = "Product deleted successfully!";
        header("Location: products.php");
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
        <div class="row-fluid" style="background-color: white; min-height: 400px; padding: 20px;">
            <div class="span12">

                <!-- Alert Messages -->
                <?php if ($alert == "error") { ?>
                    <div class="alert alert-danger">Error: Unable to delete product.</div>
                <?php } elseif ($alert == "invalid") { ?>
                    <div class="alert alert-warning">Invalid product ID.</div>
                <?php } elseif ($alert == "not_found") { ?>
                    <div class="alert alert-warning">Product not found.</div>
                <?php } ?>

                <!-- Delete Confirmation -->
                <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                    <div class="widget-title">
                        <h5>Delete Product Confirmation</h5>
                    </div>

                    <div class="widget-content" style="padding: 20px;">
                        <p>Are you sure you want to delete the following product?</p>

                        <table class="table table-bordered table-striped">
                            <tr>
                                <th>ID</th>
                                <td><?= htmlspecialchars($product['product_id']) ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Created At</th>
                                <td><?= date('M d, Y', strtotime($product['created_at'])) ?></td>
                            </tr>
                        </table>

                        <form action="" method="post">
                        <div class="form-actions action-buttons">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="icon-trash"></i> Confirm Delete
                            </button>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                        </div>
                        </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>