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
$current_user_id = $_SESSION['user_id'];

// ============================================
// VALIDATE ID
// ============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$edit_user_id = intval($_GET['id']);


/* ============================================
   ALERT HELPER
============================================ */
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

// ============================================
// FETCH USER RECORD
// ============================================
$stmt = $conn->prepare("SELECT user_id, username, fullname, email, role, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $edit_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='alert alert-danger'>User record not found.</div>";
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Fetch assigned companies
$assignedCompanies = [];
$acStmt = $conn->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
$acStmt->bind_param("i", $edit_user_id);
$acStmt->execute();
$acResult = $acStmt->get_result();
while ($acRow = $acResult->fetch_assoc()) {
    $assignedCompanies[] = $acRow['company_id'];
}
$acStmt->close();

// ============================================
// HANDLE UPDATE FORM
// ============================================
if (isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Check if email already taken by another user
    $emailCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $emailCheck->bind_param("si", $email, $edit_user_id);
    $emailCheck->execute();
    $emailCheck->store_result();
    if ($emailCheck->num_rows > 0) {
        $error = "That email address is already in use by another account.";
        $emailCheck->close();
    } else {
        $emailCheck->close();

        // Build dynamic query based on password change
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, fullname = ?, email = ?, role = ?, password = ? WHERE user_id = ?");
            $stmt->bind_param("sssssi", $username, $fullname, $email, $role, $hashed_password, $edit_user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, fullname = ?, email = ?, role = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $username, $fullname, $email, $role, $edit_user_id);
        }

        if ($stmt->execute()) {
            $stmt->close();

            // Update company assignments
            $deleteStmt = $conn->prepare("DELETE FROM user_companies WHERE user_id = ?");
            $deleteStmt->bind_param("i", $edit_user_id);
            $deleteStmt->execute();
            $deleteStmt->close();

            if (!empty($_POST['assigned_companies']) && $role !== 'super_admin') {
                $companyStmt = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                foreach ($_POST['assigned_companies'] as $company_id) {
                    $company_id = intval($company_id);
                    $companyStmt->bind_param("ii", $edit_user_id, $company_id);
                    $companyStmt->execute();
                }
                $companyStmt->close();
            }

            $adminUsername = mysqli_real_escape_string($conn, $_SESSION['username']);
            $editedUsername = mysqli_real_escape_string($conn, $username);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('User updated', '$adminUsername', 'User: $editedUsername, Role: $role (User ID: $edit_user_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            setAlert('success', 'User updated successfully!');
            header("Location: users.php?success=edited");
            exit();
        } else {
            $error = "Error: Unable to update user. " . $stmt->error;
            $stmt->close();
        }
    } // close email check else
}
?>

<link rel="stylesheet" href="css/layout.css">
    <div id="content">
        <div class="container-fluid">
            <div class="row-fluid" style="background-color: white; min-height: 600px; padding: 20px;">
                <div class="span12">
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['alert'])): ?>
                        <?php
                        $alert = $_SESSION['alert'];
                        $type = is_array($alert) ? ($alert['type'] ?? 'info') : 'success';
                        $message = is_array($alert) ? ($alert['message'] ?? 'Something happened.') : $alert;
                        unset($_SESSION['alert']);
                        ?>
                        <div class="alert alert-<?= $type ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                        <div class="widget-title">
                            <h5>
                                <i class="fas fa-user-edit"></i> Edit User
                            </h5>
                        </div>

                        <div class="widget-content" style="padding: 20px;">
                            <form action="" method="post" class="form-horizontal">
                                <!-- Username -->
                                <div class="control-group">
                                    <label class="control-label">Username:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" name="username" 
                                               value="<?= htmlspecialchars($user['username']) ?>" required>
                                    </div>
                                </div>

                                <!-- Full Name -->
                                <div class="control-group">
                                    <label class="control-label">Full Name:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" name="fullname" 
                                               value="<?= htmlspecialchars($user['fullname']) ?>" required>
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="control-group">
                                    <label class="control-label">Email Address:</label>
                                    <div class="controls">
                                        <input type="email" class="span11" name="email"
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                                               placeholder="Enter email address">
                                    </div>
                                </div>

                                <!-- Password -->
                                <div class="control-group">
                                    <label class="control-label">
                                        New Password:
                                    </label>
                                    <div class="controls">
                                        <input type="password" class="span11" name="password" 
                                               placeholder="Leave blank to keep current">
                                        <?php if ($current_user_id === $edit_user_id): ?>
                                            <p class="help-block" style="font-size: 11px; color: #666;">
                                                Leave blank if you don't want to change your password.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Role -->
                                <div class="control-group">
                                    <label class="control-label">Role:</label>
                                    <div class="controls">
                                        <select name="role" id="roleSelect" class="span11" required>
                                            <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>User</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Company Assignment -->
                                <div class="control-group">
                                    <label class="control-label">Assign Companies:</label>
                                    <div class="controls">
                                        <?php
                                        $companiesResult = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC");
                                        while ($c = $companiesResult->fetch_assoc()):
                                        $checked = in_array($c['company_id'], $assignedCompanies) ? 'checked' : '';
                                        ?>
                                        <label style="display:block; margin-bottom:5px;">
                                            <input type="checkbox" name="assigned_companies[]" value="<?= $c['company_id'] ?>" <?= $checked ?>>
                                            <?= htmlspecialchars($c['company_name']) ?>
                                        </label>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <!-- Created At (Read Only) -->
                                <div class="control-group">
                                    <label class="control-label">Created At:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" 
                                               value="<?= date('M d, Y H:i', strtotime($user['created_at'])) ?>" 
                                               disabled>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="form-actions action-buttons">
                                    <button type="submit" name="update_user" class="btn btn-success">
                                        <i class="fas fa-save"></i> Update User
                                    </button>
                                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('roleSelect').addEventListener('change', function() {
        const companyDiv = document.querySelector('.control-group:has([name="assigned_companies[]"])');
        if (companyDiv) {
            companyDiv.style.display = this.value === 'super_admin' ? 'none' : 'block';
        }
    });

    // Run on page load
    const roleSelect = document.getElementById('roleSelect');
    const companyDiv = document.querySelector('.control-group:has([name="assigned_companies[]"])');
    if (companyDiv && roleSelect) {
        companyDiv.style.display = roleSelect.value === 'super_admin' ? 'none' : 'block';
    }
    </script>

    <?php include "footer.php"; ?>