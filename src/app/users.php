<?php
session_start();
include "header.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$current_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$isSuperAdmin = $current_role === 'super_admin';

/* ============================================
   ALERT HELPER
============================================ */
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

/* ============================================
   HANDLE SUCCESS REDIRECTS
============================================ */
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        setAlert('success', 'User added successfully!');
    }
    if ($_GET['success'] === 'edited') {
        setAlert('success', 'User updated successfully!');
    }
    if ($_GET['success'] === 'deleted') {
        setAlert('success', 'User deleted successfully!');
    }
}

// ============================================
// FETCH USERS BASED ON ROLE
// ============================================
include "connection.php";

$sql = "
    SELECT 
        user_id,
        username,
        fullname,
        role,
        created_at
    FROM users
    ORDER BY created_at DESC
";
$result = $conn->query($sql);
$is_admin_view = true;
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

    <title><?= $is_admin_view ? 'Users List' : 'My Profile' ?></title>
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
                <?php if ($is_admin_view): ?>
                    <h3>Users List</h3>
                    <a href="users_new.php" class="btn btn-success">
                        <i class="icon-plus"></i>
                        Create New User
                    </a>
                <?php else: ?>
                    <h3>My Profile</h3>
                <?php endif; ?>
            </div>

            <!-- Main Table -->
            <div class="table-container">
                <div class="table-header">
                    <span class="table-stats">
                        <?php if ($is_admin_view): ?>
                            All System Users
                        <?php else: ?>
                            Your Account Details
                        <?php endif; ?>
                        - Showing <?= $result->num_rows ?? 0 ?> record(s)
                    </span>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Date Created</th>
                                <?php if ($is_admin_view): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr <?php if ($is_admin_view): ?>
                                            class="clickable-row"
                                            data-href="users_view.php?id=<?= $row['user_id'] ?>"
                                        <?php else: ?>
                                            class="clickable-row"
                                            data-href="users_edit.php?id=<?= $row['user_id'] ?>"
                                        <?php endif; ?>
                                    >
                                        <td><?= htmlspecialchars($row['username']) ?></td>
                                        <td><?= htmlspecialchars($row['fullname']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $row['role'] ?>">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['role']))) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>

                                        <?php if ($is_admin_view): ?>
                                            <td>
                                                <a href="users_edit.php?id=<?= $row['user_id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="users_delete.php?id=<?= $row['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $is_admin_view ? 5 : 4 ?>" style="text-align:center;">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).on("click", ".clickable-row", function() {
            const url = $(this).data("href");
            if (url) {
                window.location.href = url;
            }
        });
    </script>
</body>
</html>