<?php
ob_start();
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if user is not logged in, but allow login page
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page != 'login.php') {
    header("Location: login.php");
    exit();
}

// Include local connection.php (inside the same folder)
include "connection.php";

// Get username from session or default to Guest
$username = isset($_SESSION['username']) ? $_SESSION['username'] : "Guest";
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$isAdmin = $role === 'admin';
$isSuperAdmin = $role === 'super_admin';

// Default user status
$status = "Active"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>PHP Expense Tracker</title>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css"/>
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet"/>
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>

    <style>
        :root {
            --header-height: 70px;
            --sidebar-width: 250px;
            --primary-color: #4e54c8;
            --primary-dark: #3a3f9e;
            --header-bg: #28282B;
            --sidebar-bg: #28282B;
            --sidebar-hover: #3a3a3e;
            --text-light: #ccc;
            --text-white: #fff;
            --danger-color: #ef4444;
            --border-color: #444;
        }

        #header {
            border-color: transparent;
        }

        #header a,
        #header button {
            border-color: transparent;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Open Sans', sans-serif;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* ============================================
           HEADER
        ============================================ */
        #header {
            background-color: var(--header-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            height: var(--header-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        /* NEW: toggle button */
        .menu-toggle {
            display: none;
            background: transparent;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            margin-right: 10px;
        }

        #header .logo {
            color: var(--text-white);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #header .user-info {
            color: var(--text-light);
        }

        /* ============================================
           SIDEBAR
        ============================================ */
        #sidebar {
            background: var(--sidebar-bg);
            width: var(--sidebar-width);
            position: fixed;
            top: var(--header-height);
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 999;
            transition: left 0.3s ease;
        }

        /* ============================================
           MOBILE SIDEBAR (ADDED)
        ============================================ */
        @media (max-width: 900px) {

            .menu-toggle {
                display: block;
            }

            #sidebar {
                left: -260px;
            }

            #sidebar.open {
                left: 0;
            }

            #content {
                margin-left: 0 !important;
            }

            body.sidebar-open::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }
        }
        /* ============================================
           HEADER STYLING - Clean & Proper Positioning
           ============================================ */
        
        :root {
            --header-height: 70px;
            --sidebar-width: 250px;
            --primary-color: #4e54c8;
            --primary-dark: #3a3f9e;
            --header-bg: #28282B;
            --sidebar-bg: #28282B;
            --sidebar-hover: #3a3a3e;
            --text-light: #ccc;
            --text-white: #fff;
            --danger-color: #ef4444;
            --border-color: #444;
        }

        #header {
    border-color: transparent;
}

#header a,
#header button {
    border-color: transparent;
}

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Open Sans', sans-serif;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* ============================================
           TOP HEADER BAR
           ============================================ */
        #header {
            background-color: var(--header-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            height: var(--header-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        #header .logo {
            color: var(--text-white);
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #header .logo i {
            font-size: 20px;
            color: var(--primary-color);
        }

        #header .user-info {
            color: var(--text-light);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        #header .user-info i {
            color: var(--primary-color);
        }

        /* ============================================
           SIDEBAR MENU
           ============================================ */
        #sidebar {
            background: var(--sidebar-bg);
            width: var(--sidebar-width);
            position: fixed;
            top: var(--header-height);
            left: 0;
            bottom: 0;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 2px 0 10px rgba(0,0,0,0.15);
            z-index: 999;
        }

        #sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #sidebar li {
            border-bottom: 1px solid var(--border-color);
        }

        /* User Info Section */
        #sidebar .user-profile {
            padding: 20px;
            text-align: center;
            background-color: #333;
            color: white;
            border-bottom: 1px solid #444;
        }

        #sidebar .user-profile .user-icon {
            width: 60px;
            height: 60px;
            background-color: #555;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        #sidebar .user-profile .user-icon i {
            font-size: 30px;
            color: white;
        }

        #sidebar .user-profile .username {
            display: block;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        #sidebar .user-profile .status {
            font-size: 12px;
            display: block;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        #sidebar .user-profile .status.inactive {
            color: #FFA500;
        }

        #sidebar .user-profile .status.active {
            color: #0f0;
        }

        /* Navigation Links */
        #sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            position: relative;
        }

        #sidebar a:hover {
            background: var(--sidebar-hover);
            color: var(--text-white);
            padding-left: 25px;
        }

        #sidebar a.active {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            color: var(--text-white);
            border-left: 4px solid var(--text-white);
        }

        #sidebar a i {
            width: 25px;
            margin-right: 10px;
            font-size: 16px;
            text-align: center;
        }

        #sidebar a span {
            flex: 1;
        }

        /* Dropdown Submenus */
        #sidebar .submenu > a {
            cursor: pointer;
            justify-content: space-between;
        }

        #sidebar .submenu > a::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        #sidebar .submenu.open > a::after {
            transform: rotate(180deg);
        }

        #sidebar .submenu ul {
            display: none;
            background: #222;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        #sidebar .submenu.open ul {
            display: block;
        }

        #sidebar .submenu ul li a {
            padding: 12px 20px 12px 50px;
            font-size: 13px;
            border-left: 3px solid transparent;
        }

        #sidebar .submenu ul li a:hover {
            background: #2a2a2a;
            border-left: 3px solid var(--primary-color);
        }

        #sidebar .submenu ul li a.active {
            background: #2a2a2a;
            border-left: 3px solid var(--primary-color);
            color: var(--text-white);
        }


        /* ============================================
           MAIN CONTENT AREA
           ============================================ */
        #content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 0;               
            min-height: 100vh;
        }

        

        /* ============================================
           RESPONSIVE DESIGN
           ============================================ */
         /*@media (max-width: 768px) {
            #sidebar {
                width: 100%;
                position: relative;
                top: 0;
            }



            #header {
                padding: 0 15px;
            }
        }*/

        /* ============================================
           SCROLLBAR STYLING
           ============================================ */
        #sidebar::-webkit-scrollbar {
            width: 6px;
        }

        #sidebar::-webkit-scrollbar-track {
            background: #222;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 3px;
        }

        #sidebar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        @media (max-width: 900px) {
            #sidebar {
                left: -260px !important;
            }

            #sidebar.open {
                left: 0 !important;
            }
        }

    </style>
</head>

<body>

<!-- Top gray bar (no title text) -->
<div id="header">
    <button id="menu-toggle" class="menu-toggle">
        <i class="icon icon-reorder"></i>
    </button>
    <a href="dashboard.php" class="logo">
        <i class="icon icon-bar-chart"></i> Expense Tracker
    </a>
    <div class="user-info" id="profileToggle" style="cursor:pointer; position:relative;">
        <i class="icon icon-user"></i>
        <?php echo htmlspecialchars($username); ?>
        <span style="font-size:10px; background:#4e54c8; color:white; padding:2px 7px; border-radius:10px; margin-left:6px; text-transform:uppercase; letter-spacing:0.5px;">
            <?php echo $isSuperAdmin ? 'Super Admin' : ($isAdmin ? 'Admin' : 'User'); ?>
        </span>
        <i class="icon icon-caret-down" style="margin-left:6px; font-size:11px;"></i>

        <div id="profileDropdown" style="
            display:none;
            position:absolute;
            top:calc(100% + 10px);
            right:0;
            background:#1e1e21;
            border:1px solid #444;
            border-radius:8px;
            min-width:180px;
            box-shadow:0 8px 24px rgba(0,0,0,0.4);
            z-index:2000;
            overflow:hidden;
        ">
            <div style="padding:12px 16px; border-bottom:1px solid #333; color:#aaa; font-size:12px;">
                Signed in as <strong style="color:white;"><?php echo htmlspecialchars($username); ?></strong>
            </div>
            <a href="change_password.php" style="
                display:flex;
                align-items:center;
                gap:10px;
                padding:12px 16px;
                color:#ccc;
                text-decoration:none;
                font-size:13px;
                transition:background 0.2s;
            " onmouseover="this.style.background='#2a2a2e'" onmouseout="this.style.background='transparent'">
                <i class="icon icon-lock"></i> Change Password
            </a>
            <form action="logout.php" method="post" style="margin:0;">
                <button type="submit" style="
                    width:100%;
                    display:flex;
                    align-items:center;
                    gap:10px;
                    padding:12px 16px;
                    background:transparent;
                    border:none;
                    color:#ef4444;
                    font-size:13px;
                    cursor:pointer;
                    text-align:left;
                    transition:background 0.2s;
                " onmouseover="this.style.background='#2a2a2e'" onmouseout="this.style.background='transparent'">
                    <i class="icon icon-share-alt"></i> Log Out
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Sidebar Menu -->
<div id="sidebar">
    <ul>
        <!-- User Info Section -->
        <li class="user-profile">
            <!-- User Icon -->
            <div class="user-icon">
                <i class="icon icon-user"></i>
            </div>

            <!-- Username -->
            <strong class="username"><?php echo htmlspecialchars($username); ?></strong>

            <!-- User Status -->
            <?php $status_color = ($status == 'Inactive') ? 'inactive' : 'active'; ?>
            <span class="status <?php echo $status_color; ?>">
                <?php echo htmlspecialchars($status); ?>
            </span>
        </li>

        <!-- Navigation Links -->
        <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="dashboard.php">
                <i class="icon icon-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="<?php echo ($current_page == 'expenses.php') ? 'active' : ''; ?>">
            <a href="expenses.php">
                <i class="icon icon-money"></i>
                <span>Expenses</span>
            </a>
        </li>

        <?php if ($isAdmin || $isSuperAdmin): ?>

        <li class="<?php echo ($current_page == 'budgets.php') ? 'active' : ''; ?>">
            <a href="budgets.php">
                <i class="icon icon-money"></i>
                <span>Budgets</span>
            </a>
        </li>

        <li class="<?php echo ($current_page == 'payees.php') ? 'active' : ''; ?>">
            <a href="payees.php">
                <i class="icon icon-briefcase"></i>
                <span>Payees</span>
            </a>
        </li>

        <li class="<?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
            <a href="categories.php">
                <i class="icon icon-tags"></i>
                <span>Categories</span>
            </a>
        </li>

        <li class="<?php echo ($current_page == 'companies.php') ? 'active' : ''; ?>">
            <a href="companies.php">
                <i class="icon icon-building"></i>
                <span>Companies</span>
            </a>
        </li>

        <?php if ($isSuperAdmin): ?>
        <li class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
            <a href="users.php">
                <i class="icon icon-user"></i>
                <span>Users</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($isSuperAdmin): ?>
        <li class="<?php echo ($current_page == 'logs.php') ? 'active' : ''; ?>">
            <a href="logs.php">
                <i class="icon icon-user"></i>
                <span>Logs</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Reports Dropdown -->
        <li class="submenu <?php echo (in_array($current_page, ['reports.php','expense_budget.php'])) ? 'active open' : ''; ?>">
            <a href="javascript:void(0)">
                <i class="icon icon-bar-chart"></i>
                <span>Reports</span>
            </a>
            <ul>
                <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <a href="reports.php">Expense Reports</a>
                </li>
                <li class="<?php echo ($current_page == 'expense_budget.php') ? 'active' : ''; ?>">
                    <a href="expense_budget.php">Expense vs Budget</a>
                </li>
            </ul>
        </li>

        <!-- Special Fields Dropdown -->
        <li class="submenu <?php echo (in_array($current_page, ['resellers.php','end_users.php','products.php'])) ? 'active open' : ''; ?>">
            <a href="javascript:void(0)">
                <i class="icon icon-tags"></i>
                <span>Special Fields</span>
            </a>
            <ul>
                <li class="<?php echo ($current_page == 'resellers.php') ? 'active' : ''; ?>">
                    <a href="resellers.php">Resellers</a>
                </li>
                <li class="<?php echo ($current_page == 'end_users.php') ? 'active' : ''; ?>">
                    <a href="end_users.php">End Users</a>
                </li>
                <li class="<?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
                    <a href="products.php">Products</a>
                </li>
            </ul>
        </li>

        <?php endif; ?>


        </ul>

    <!-- removed: logout moved to profile dropdown -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle logic
    const toggleBtn = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            document.body.classList.toggle('sidebar-open');
        });
    }

    // Click outside to close the sidebar
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });

    // Profile dropdown toggle
    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = profileDropdown.style.display === 'block';
            profileDropdown.style.display = isVisible ? 'none' : 'block';
        });

        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target)) {
                profileDropdown.style.display = 'none';
            }
        });
    }

    // Dropdown toggle logic
    const submenuLinks = document.querySelectorAll('#sidebar .submenu > a');
    submenuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.parentElement;
            const ul = parent.querySelector('ul');

            if (!ul) return;

            // Close other submenus
            document.querySelectorAll('#sidebar .submenu').forEach(other => {
                if (other !== parent) {
                    const otherUl = other.querySelector('ul');
                    if (otherUl) {
                        otherUl.style.display = 'none';
                        other.classList.remove('open');
                    }
                }
            });

            // Toggle current submenu
            if (!parent.classList.contains('open')) {
                ul.style.display = 'block';
                parent.classList.add('open');
            } else {
                ul.style.display = 'none';
                parent.classList.remove('open');
            }
        });
    });
});
</script>

