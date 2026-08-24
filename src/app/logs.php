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

if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

// Search filter
$searchQuery = trim($_GET['search'] ?? '');

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Build WHERE clause
$whereClause = "";
$searchParam = "";
if (!empty($searchQuery)) {
    $whereClause = "WHERE log_action LIKE ? OR log_user = ? OR log_details LIKE ?";
    $searchParam = "%" . $searchQuery . "%";
    $exactParam = $searchQuery;
}

// Get total number of records
if (!empty($searchQuery)) {
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM logs $whereClause");
    $totalStmt->bind_param("sss", $searchParam, $exactParam, $searchParam);
} else {
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM logs");
}
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalStmt->close();

// Calculate total pages
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch paginated logs
if (!empty($searchQuery)) {
    $logsStmt = $conn->prepare("
        SELECT log_id, log_action, log_user, log_details, log_date
        FROM logs
        $whereClause
        ORDER BY log_date DESC
        LIMIT ? OFFSET ?
    ");
    $logsStmt->bind_param("sssii", $searchParam, $exactParam, $searchParam, $recordsPerPage, $offset);
} else {
    $logsStmt = $conn->prepare("
        SELECT log_id, log_action, log_user, log_details, log_date
        FROM logs
        ORDER BY log_date DESC
        LIMIT ? OFFSET ?
    ");
    $logsStmt->bind_param("ii", $recordsPerPage, $offset);
}
$logsStmt->execute();
$logsResult = $logsStmt->get_result();
$logsStmt->close();
?>

<link rel="stylesheet" href="css/layout.css">

<div id="content">
    <div class="container-fluid">
        
        <!-- Main Table Container -->
        <div class="table-container">
            
            <div class="table-header">
                <h3>System Logs</h3>
                <span class="table-stats">
                    Showing <?= min($offset + 1, $totalRecords) ?> to <?= min($offset + $recordsPerPage, $totalRecords) ?> of <?= number_format($totalRecords) ?> records
                </span>
            </div>

            <!-- Search Bar -->
            <form method="get" style="margin-bottom:15px;">
                <div style="display:flex; gap:10px; align-items:center; max-width:500px;">
                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        placeholder="Search by action, user, or details..."
                        style="flex:1; padding:10px 12px; border:1px solid #ccc; border-radius:3px; font-size:14px;"
                    >
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($searchQuery)): ?>
                    <a href="logs.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>User</th>
                            <th>Details</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logsResult && $logsResult->num_rows > 0) { ?>
                            <?php while ($row = $logsResult->fetch_assoc()) { ?>
                                <?php
                                $action = strtolower($row['log_action']);
                                $badgeColor = '#6c757d'; // default gray
                                if (str_contains($action, 'created')) $badgeColor = '#10b981';
                                if (str_contains($action, 'updated')) $badgeColor = '#3b82f6';
                                if (str_contains($action, 'deleted')) $badgeColor = '#ef4444';
                                ?>
                                <tr>
                                    <td>
                                        <span style="background:<?= $badgeColor ?>; color:#fff; padding:3px 8px; border-radius:4px; font-size:12px; white-space:nowrap;">
                                            <?= htmlspecialchars($row['log_action']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['log_user']) ?></td>
                                    <td><?= htmlspecialchars($row['log_details'] ?? '-') ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($row['log_date'])) ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="icon-inbox"></i>
                                        <h4>No logs found</h4>
                                        <p>No system logs recorded yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination - Centered at bottom within form -->
            <?php if ($totalPages > 1) { ?>
                <div class="table-footer">
                    <div class="pagination-wrapper">
                        <ul class="pagination">
                            <?php if ($page > 1) { ?>
                                                                <li><a href="?page=<?= $page - 1 ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="pagination-prev">« Previous</a></li>
                            <?php } ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            $sq = !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '';

                            if ($startPage > 1) {
                                echo '<li><a href="?page=1' . $sq . '">1</a></li>';
                                if ($startPage > 2) echo '<li class="disabled"><span>...</span></li>';
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $activeClass = $i == $page ? 'active' : '';
                                echo '<li class="' . $activeClass . '"><a href="?page=' . $i . $sq . '">' . $i . '</a></li>';
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) echo '<li class="disabled"><span>...</span></li>';
                                echo '<li><a href="?page=' . $totalPages . $sq . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages) { ?>
                                <li><a href="?page=<?= $page + 1 ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" class="pagination-next">Next »</a></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<style>
/* Table Container Styles */
.table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 25px;
    margin-bottom: 30px;
    border: 1px solid #e9ecef;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.table-header h3 {
    margin: 0;
    color: #333;
    font-size: 24px;
    font-weight: 600;
}

.table-stats {
    color: #666;
    font-size: 14px;
}

.table-responsive {
    margin-bottom: 25px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    overflow: hidden;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    font-size: 48px;
    display: block;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Pagination - Centered at bottom within form */
.table-footer {
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

.pagination-wrapper {
    display: inline-block;
}

.pagination {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination li {
    display: inline-block;
}

.pagination li a,
.pagination li span {
    display: block;
    padding: 8px 12px;
    text-decoration: none;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    color: #007cba;
    font-weight: 500;
    transition: all 0.2s ease;
    min-width: 38px;
    text-align: center;
}

.pagination li a:hover {
    background: #007cba;
    color: white;
    border-color: #007cba;
    transform: translateY(-1px);
}

.pagination li.active a {
    background: #007cba;
    color: white;
    border-color: #007cba;
    font-weight: 600;
}

.pagination li.disabled span {
    color: #6c757d;
    background: #f8f9fa;
    cursor: not-allowed;
}

.pagination li.prev a,
.pagination li.next a {
    font-weight: 600;
    min-width: auto;
}

.pagination-prev::before,
.pagination-next::after {
    content: '';
}
</style>

<?php include "footer.php"; ?>
<?php ob_end_flush(); ?>