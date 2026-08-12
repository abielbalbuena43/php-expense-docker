<?php
session_start();
include "connection.php";
include "header.php";

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
   FETCH CATEGORIES
--------------------------------*/
$sql = "
SELECT 
    category_id,
    category_name,
    category_created_at
FROM expense_categories
ORDER BY category_created_at DESC
LIMIT 100
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Core CSS -->
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/layout.css">

<!-- Icons -->
<link href="font-awesome/css/font-awesome.css" rel="stylesheet">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<title>Categories List</title>

</head>

<body>

<div id="content">

    <?php if (isset($_SESSION['alert'])): ?>

    <?php
    $alert = $_SESSION['alert'];

    if (is_array($alert)) {
        $type = $alert['type'] ?? 'info';
        $message = $alert['message'] ?? 'Something happened.';
    } else {
        // fallback for old string alerts
        $type = 'success';
        $message = $alert;
    }

    unset($_SESSION['alert']);
    ?>

    <div class="alert alert-<?= $type ?>">
        <?= htmlspecialchars($message) ?>
    </div>

    <?php endif; ?>

<div class="container-fluid">

<!-- Header Actions -->
<div class="header-actions">
    <?php if ($isSuperAdmin || $isAdmin): ?>
    <a href="categories_new.php" class="btn btn-success">
        <i class="icon-plus"></i>
        Create New Category
    </a>
<?php endif; ?>
</div>

<!-- Main Table -->
<div class="table-container">
    <div class="table-header">
        <h3>Expense Categories</h3>
        <span class="table-stats">
            Showing <?= $result->num_rows ?? 0 ?> records
        </span>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Date Created</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="clickable-row" data-href="categories_view.php?id=<?= $row['category_id'] ?>">
                        <td><?= htmlspecialchars($row['category_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['category_created_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="2">
                        <div class="empty-state">
                            <i class="icon-inbox"></i>
                            <h4>No categories found</h4>
                            <p>No categories available. Create a new category to get started.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

</div>

</div>

<script>
$(document).on("click", ".clickable-row", function(){
    const url = $(this).data("href");
    if (url) {
        window.location.href = url;
    }
});

// ✅ PREVENT ROW FROM OVERRIDING BUTTON
$(document).on("click", ".header-actions a", function(e){
    e.stopPropagation();
});
</script>

</body>
</html>