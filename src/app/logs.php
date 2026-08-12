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

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Get total number of records
$totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM logs");
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalStmt->close();

// Calculate total pages
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch paginated logs
$logsStmt = $conn->prepare("
    SELECT log_id, log_action, log_user, log_details, log_date
    FROM logs
    ORDER BY log_date DESC
    LIMIT ? OFFSET ?
");
$logsStmt->bind_param("ii", $recordsPerPage, $offset);
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
                                <tr>
                                    <td><?= htmlspecialchars($row['log_action']) ?></td>
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
                                <li><a href="?page=<?= $page - 1 ?>" class="pagination-prev">« Previous</a></li>
                            <?php } ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li><a href="?page=1">1</a></li>';
                                if ($startPage > 2) echo '<li class="disabled"><span>...</span></li>';
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                $activeClass = $i == $page ? 'active' : '';
                                echo '<li class="' . $activeClass . '"><a href="?page=' . $i . '">' . $i . '</a></li>';
                            }
                            
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) echo '<li class="disabled"><span>...</span></li>';
                                echo '<li><a href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $totalPages) { ?>
                                <li><a href="?page=<?= $page + 1 ?>" class="pagination-next">Next »</a></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

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

<script>
$(document).ready(function() {
    // Smooth pagination transitions
    $('.pagination a').click(function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        if (href) {
            window.location.href = href;
        }
    });
});
</script>

<?php ob_end_flush(); ?>