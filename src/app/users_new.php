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
// HANDLE SUCCESS REDIRECTS
// ============================================
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        setAlert('success', 'User added successfully!');
    }
}

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if (isset($_POST['submit_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Check if email already exists
    $emailCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $emailCheck->bind_param("s", $email);
    $emailCheck->execute();
    $emailCheck->store_result();
    if ($emailCheck->num_rows > 0) {
        $error = "That email address is already in use.";
        $emailCheck->close();
    } else {
        $emailCheck->close();

        // Hash password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $username, $hashed_password, $fullname, $email, $role);

        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;

            // Save company assignments for admin and user roles
            if (!empty($_POST['assigned_companies']) && $role !== 'super_admin') {
                $companyStmt = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                foreach ($_POST['assigned_companies'] as $company_id) {
                    $company_id = intval($company_id);
                    $companyStmt->bind_param("ii", $new_user_id, $company_id);
                    $companyStmt->execute();
                }
                $companyStmt->close();
            }

            $adminUsername = mysqli_real_escape_string($conn, $_SESSION['username']);
            $newUsername = mysqli_real_escape_string($conn, $username);

            $logQuery = "
                INSERT INTO logs (log_action, log_user, log_details, log_date)
                VALUES ('User created', '$adminUsername', 'User: $newUsername, Role: $role (User ID: $new_user_id)', NOW())
            ";
            mysqli_query($conn, $logQuery);

            setAlert('success', 'User added successfully!');
            header("Location: users.php?success=added");
            exit();
        } else {
            $error = "Error: Unable to save user. " . $stmt->error;
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

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="widget-box" style="max-width: 800px; margin: 0 auto;">
                        <div class="widget-title">
                            <h5><i class="fas fa-user-plus"></i> Add New User</h5>
                        </div>

                        <div class="widget-content" style="padding: 20px;">
                            <form action="" method="post" class="form-horizontal">
                                <!-- Username -->
                                <div class="control-group">
                                    <label class="control-label">Username:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" name="username" required placeholder="Enter username">
                                    </div>
                                </div>

                                <!-- Password -->
                                <div class="control-group">
                                    <label class="control-label">Password:</label>
                                    <div class="controls">
                                        <input type="password" class="span11" name="password" required placeholder="Enter password">
                                    </div>
                                </div>

                                <!-- Fullname -->
                                <div class="control-group">
                                    <label class="control-label">Full Name:</label>
                                    <div class="controls">
                                        <input type="text" class="span11" name="fullname" required placeholder="Enter full name">
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="control-group">
                                    <label class="control-label">Email Address:</label>
                                    <div class="controls">
                                        <input type="email" class="span11" name="email" required placeholder="Enter email address">
                                    </div>
                                </div>

                                <!-- Role -->
                                <div class="control-group">
                                    <label class="control-label">Role:</label>
                                    <div class="controls">
                                        <select name="role" id="roleSelect" class="span11" required>
                                            <option value="admin" selected>Admin</option>
                                            <option value="user">User</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Company Assignment -->
                                <div class="control-group" id="companyAssignment">
                                    <label class="control-label">Assign Companies:</label>
                                    <div class="controls">
                                        <?php
                                        $companiesResult = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name ASC");
                                        while ($c = $companiesResult->fetch_assoc()):
                                        ?>
                                        <label style="display:block; margin-bottom:5px;">
                                            <input type="checkbox" name="assigned_companies[]" value="<?= $c['company_id'] ?>">
                                            <?= htmlspecialchars($c['company_name']) ?>
                                        </label>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="form-actions action-buttons">
                                    <button type="submit" name="submit_user" class="btn btn-success">
                                        <i class="fas fa-save"></i> Save User
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
    function toggleCompanySection() {
        const role = document.getElementById('roleSelect').value;
        const companyDiv = document.getElementById('companyAssignment');
        if (companyDiv) {
            companyDiv.style.display = role === 'super_admin' ? 'none' : 'block';
        }
    }

    document.getElementById('roleSelect').addEventListener('change', toggleCompanySection);
    toggleCompanySection();
    </script>

    <?php include "footer.php"; ?>