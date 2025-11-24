<?php
session_start();
include '../includes/config.php'; // Ensure this path is correct and $conn is established

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
$admin_id = $_SESSION['user_id']; // Get the logged-in admin's ID
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// --- Fetch Admin Notifications (from the 'notifications' table for this admin) ---
$admin_notifications_list = [];
$unread_admin_notification_count = 0;
$pending_employer_direct_count = 0; // For the separate display of pending employers

if ($conn) {
    // Fetch unread notifications for the logged-in admin
    $sql_admin_notifications = "SELECT id, message, date, is_read 
                                FROM notifications 
                                WHERE user_id = ?
                                ORDER BY date DESC LIMIT 7"; 
    $stmt_admin_notif = $conn->prepare($sql_admin_notifications);
    
    if ($stmt_admin_notif) {
        $stmt_admin_notif->bind_param("i", $admin_id);
        $stmt_admin_notif->execute();
        $result_admin_notif_data = $stmt_admin_notif->get_result();
        while ($row = $result_admin_notif_data->fetch_assoc()) {
            $admin_notifications_list[] = $row;
        }
        $stmt_admin_notif->close();
    } else {
        error_log("Admin Dashboard: Error fetching admin notifications: " . $conn->error);
    }

    // Count unread notifications for the admin
    $sql_admin_unread_count = "SELECT COUNT(*) as unread_count 
                               FROM notifications 
                               WHERE user_id = ? AND is_read = 0";
    $stmt_admin_unread = $conn->prepare($sql_admin_unread_count);
    if ($stmt_admin_unread) {
        $stmt_admin_unread->bind_param("i", $admin_id);
        $stmt_admin_unread->execute();
        $result_admin_unread_data = $stmt_admin_unread->get_result()->fetch_assoc();
        $unread_admin_notification_count = $result_admin_unread_data['unread_count'] ?? 0;
        $stmt_admin_unread->close();
    } else {
        error_log("Admin Dashboard: Error counting unread admin notifications: " . $conn->error);
    }
    
    // Direct count of unapproved employers (for dashboard display, separate from notification messages)
    $sql_direct_count = "SELECT COUNT(*) as count FROM users WHERE role = 'employer' AND is_approved = 0";
    $res_direct_count = $conn->query($sql_direct_count);
    if ($res_direct_count) {
        $pending_employer_direct_count = $res_direct_count->fetch_assoc()['count'] ?? 0;
    }

} else {
    error_log("Admin Dashboard: Database connection not available.");
}
// --- End Fetch Notification Data ---

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Job Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        :root { 
            --primary-color: #1D3557; --secondary-color: #457B9D; --accent-color: #A8DADC;
            --light-bg-color: #F8F9FA; --card-bg-color: #FFFFFF; --text-color: #333;
            --light-text-color: #F1FAEE; --box-bg-color: #F1FAEE;
            --notification-badge-bg: #E63946; 
            --border-light: #dee2e6; --shadow-color: rgba(0,0,0,0.08);
            --text-dark-js: #2c3e50; --text-light-js: #7f8c8d; --blue-accent-js: #007bff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg-color); color: var(--text-color); line-height: 1.6; }

        .admin-header {
            background-color: var(--primary-color); color: var(--light-text-color); padding: 18px 30px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12); position: sticky; top:0; z-index: 1001;
        }
        .admin-header .header-content { 
            width: 95%; max-width: 1400px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
        }
        .admin-header .logo-title { font-size: 1.6em; font-weight: 600; color: var(--light-text-color); text-decoration: none; }
        .admin-header .logo-title i { margin-right: 10px; color: var(--accent-color); }
        
        .admin-nav-items { display: flex; align-items: center; gap: 20px; }
        
        .admin-nav-items nav ul { list-style: none; margin: 0; padding: 0; display: flex; gap: 10px; }
        .admin-nav-items nav ul li a {
            color: var(--light-text-color); text-decoration: none; font-size: 0.95em;
            font-weight: 500; padding: 9px 16px; border-radius: 5px;
            transition: background-color 0.3s; display: inline-flex; align-items: center;
        }
        .admin-nav-items nav ul li a i { margin-right: 7px; font-size: 0.9em; }
        .admin-nav-items nav ul li a:hover, 
        .admin-nav-items nav ul li a.active { 
            background-color: var(--secondary-color); 
        }
        .admin-nav-items nav ul li a.active { font-weight: 600; }

        .admin-notification-container { position: relative; display: inline-block; }
        button#adminNotificationBtn { 
            color: var(--light-text-color); text-decoration: none; font-weight: 500; 
            padding: 8px 10px; 
            border-radius: 5px; 
            transition: background-color 0.3s, color 0.3s; 
            position: relative; display: inline-flex; align-items: center;
            background: none; border: none; font-size: 1.3em; cursor: pointer;
        }
        button#adminNotificationBtn:hover { color: var(--accent-color); background-color: rgba(255,255,255,0.1); }
        .admin-notification-badge { 
            position: absolute; top: 0px; right: -2px; 
            background-color: var(--notification-badge-bg);
            color: white; border-radius: 50%; padding: 1px 5px; 
            font-size: 0.65em; font-weight: bold; line-height: 1;
            border: 1px solid var(--primary-color);
        }
        .admin-notifications-dropdown { 
            display: none; position: absolute; top: calc(100% + 10px); right: 0; 
            background-color: var(--card-bg-color); border: 1px solid var(--border-light); 
            border-radius: 8px; box-shadow: 0px 8px 16px 0px var(--shadow-color); 
            min-width: 340px; max-width: 400px; max-height: 400px; 
            overflow-y: auto; z-index: 1002; color: var(--text-dark-js); 
        }
        .admin-notifications-dropdown.show { display: block; }
        .admin-notifications-dropdown .notification-header { 
            padding: 12px 15px; border-bottom: 1px solid #eee; 
            display: flex; justify-content: space-between; align-items: center; 
            background-color: #f8f9fa; 
        }
        .admin-notifications-dropdown .notification-header h4 { margin: 0; color: var(--primary-color); font-size: 1.1em; font-weight: 600; }
        .admin-notifications-dropdown .admin-mark-all-read { 
            font-size: 0.8em; color: var(--blue-accent-js); text-decoration: none;
            padding: 3px 6px; border-radius: 4px;
            border: 1px solid transparent; cursor: pointer;
        }
        .admin-notifications-dropdown .admin-mark-all-read:hover { text-decoration: underline; }
        .admin-notifications-dropdown ul { list-style: none; padding: 0; margin: 0; }
        .admin-notifications-dropdown li { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; background: #fff; cursor: default; }
        .admin-notifications-dropdown li.unread-notification p { font-weight: 600; color: var(--primary-color); }
        .admin-notifications-dropdown li:hover { background-color: var(--box-bg-color); } /* Using var from admin palette */
        .admin-notifications-dropdown li:last-child { border-bottom: none; }
        .admin-notifications-dropdown li p { margin: 0 0 3px 0; font-size: 0.9em; color: var(--text-dark-js); line-height: 1.5; }
        .admin-notifications-dropdown li small { font-size: 0.75em; color: var(--text-light-js); }
        .admin-notifications-dropdown .no-notifications { padding: 20px 15px; text-align: center; color: var(--text-light-js); font-size: 0.9em; }
        .admin-notifications-dropdown .notification-footer { 
            padding: 10px 15px; text-align: center; 
            border-top: 1px solid #eee; background-color: #f8f9fa; 
        }
        .admin-notifications-dropdown .notification-footer a { color: var(--primary-color); text-decoration: none; font-weight: 500; font-size: 0.9em; }
        .admin-notifications-dropdown .notification-footer a:hover { text-decoration: underline; }

        .admin-container { margin: 30px auto; max-width: 1200px; padding: 30px; background-color: var(--card-bg-color); border-radius: 12px; box-shadow: 0 6px 20px var(--shadow-color); }
        .admin-container h2.welcome-title { color: var(--primary-color); font-size: 2em; margin-bottom: 8px; font-weight: 600; text-align: center; }
        .admin-container p.welcome-subtext { font-size: 1.1em; color: #555; margin-bottom: 15px; text-align: center; }
        .admin-container p.pending-count-display { font-size: 0.95em; color: var(--text-secondary); text-align: center; margin-bottom: 35px;}
        .admin-container p.pending-count-display a { color: var(--primary-color); font-weight: bold; text-decoration: none;}
        .admin-container p.pending-count-display a:hover { text-decoration: underline;}

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .dashboard-card { background: var(--box-bg-color); padding: 30px 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 12px var(--shadow-color); transition: transform 0.3s ease, box-shadow 0.3s ease; border-left: 5px solid var(--primary-color); display: flex; flex-direction: column; justify-content: space-between; }
        .dashboard-card:hover { transform: translateY(-7px); box-shadow: 0 10px 20px rgba(0,0,0,0.12); }
        .dashboard-card i.card-icon { font-size: 3em; color: var(--primary-color); margin-bottom: 18px; }
        .dashboard-card h3 { color: var(--primary-color); font-size: 1.35em; margin-bottom: 12px; font-weight: 600; }
        .dashboard-card p.card-description { font-size: 0.95em; color: #666; margin-bottom: 25px; flex-grow: 1; }
        .btn-manage { display: inline-block; padding: 12px 30px; background-color: var(--primary-color); color: var(--light-text-color); border-radius: 6px; text-decoration: none; font-weight: 500; transition: background-color 0.3s; font-size: 1em; margin-top: auto; }
        .btn-manage:hover { background-color: var(--secondary-color); }
        .btn-manage i { margin-right: 8px; }
    </style>
</head>
<body>

    <header class="admin-header">
        <div class="header-content">
            <a href="dashboard.php" class="logo-title"><i class="fas fa-user-shield"></i> Admin Panel</a>
            <div class="admin-nav-items">
                <nav>
                    <ul>
                        <li><a href="../logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </nav>
                <div class="admin-notification-container">
                    <button id="adminNotificationBtn" class="notification-button" title="Admin Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_admin_notification_count > 0): ?>
                            <span class="admin-notification-badge" id="adminNotificationBadgeCount"><?php echo $unread_admin_notification_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="admin-notifications-dropdown" id="adminNotificationsDropdown">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <?php if ($unread_admin_notification_count > 0): ?>
                                <a href="#" class="admin-mark-all-read" id="adminMarkAllReadLink">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <ul id="adminNotificationListUL">
                            <?php if (!empty($admin_notifications_list)): ?>
                                <?php foreach ($admin_notifications_list as $notification): ?>
                                    <li class="<?php echo $notification['is_read'] == 0 ? 'unread-notification' : ''; ?>">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small><i class="far fa-clock"></i> <?php echo date("M d, Y h:i A", strtotime($notification['date'])); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-notifications"><i class="fas fa-bell-slash"></i> No new notifications.</p>
                            <?php endif; ?>
                        </ul>
                        <div class="notification-footer">
                            <a href="all_admin_notifications.php">View all notifications</a> 
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-container">
        <h2 class="welcome-title">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h2>
        <p class="welcome-subtext">Manage job listings, user accounts, and employer approvals from here.</p>
        <p class="pending-count-display">
            Currently, there are <a href="manage_employers.php"><?php echo $pending_employer_direct_count; ?> employers</a> pending approval.
        </p>

        <div class="dashboard-grid">
            <div class="dashboard-card"><div> <i class="fas fa-users-cog card-icon"></i><h3>Manage Users</h3><p class="card-description">View, edit, or delete jobseeker and other user accounts.</p></div><a href="manage_users.php" class="btn-manage"><i class="fas fa-arrow-circle-right"></i> Manage Users</a></div>
            <div class="dashboard-card"><div><i class="fas fa-briefcase card-icon"></i><h3>Manage Jobs</h3><p class="card-description">Oversee all jobs posted. Edit, delete, or feature job listings.</p></div><a href="manage_jobs.php" class="btn-manage"><i class="fas fa-arrow-circle-right"></i> Manage Jobs</a></div>
            <div class="dashboard-card"><div><i class="fas fa-user-tie card-icon"></i><h3>Manage Employers</h3><p class="card-description">Approve pending registrations and manage existing employer accounts.</p></div><a href="manage_employers.php" class="btn-manage"><i class="fas fa-arrow-circle-right"></i> Manage Employers</a></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const adminNotificationBtn = document.getElementById('adminNotificationBtn');
        const adminNotificationsDropdown = document.getElementById('adminNotificationsDropdown');
        const adminMarkAllReadLink = document.getElementById('adminMarkAllReadLink');
        const adminNotificationBadgeCount = document.getElementById('adminNotificationBadgeCount');
        const adminNotificationListUL = document.getElementById('adminNotificationListUL');

        if (adminNotificationBtn && adminNotificationsDropdown) {
            adminNotificationBtn.addEventListener('click', function(event) {
                // event.stopPropagation(); // Removed for potentially better link clickability inside
                adminNotificationsDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(event) {
                if (adminNotificationBtn && !adminNotificationBtn.contains(event.target) && 
                    adminNotificationsDropdown && !adminNotificationsDropdown.contains(event.target) &&
                    adminNotificationsDropdown.classList.contains('show')) {
                    adminNotificationsDropdown.classList.remove('show');
                }
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === "Escape" && adminNotificationsDropdown && adminNotificationsDropdown.classList.contains("show")) {
                     adminNotificationsDropdown.classList.remove('show');
                }
            });
        }

        if (adminMarkAllReadLink) {
            adminMarkAllReadLink.addEventListener('click', function(event) {
                event.preventDefault(); 
                event.stopPropagation(); // Prevent dropdown from closing due to document click

                fetch('mark_notifications_read.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' },
                    // This body assumes mark_notifications_read.php uses $_SESSION['user_id'] for the admin
                    // If it strictly needs a user_id in the body, send it:
                    // body: JSON.stringify({ user_id: <?php echo $admin_id; ?> }) 
                    body: JSON.stringify({}) // Sending empty JSON body, PHP script will use session admin_id
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { throw new Error('Server responded with ' + response.status + ': ' + text); });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if (adminNotificationBadgeCount) {
                            adminNotificationBadgeCount.textContent = '0';
                            adminNotificationBadgeCount.style.display = 'none';
                        }
                        if(adminNotificationListUL) {
                            const listItems = adminNotificationListUL.querySelectorAll('li.unread-notification');
                            listItems.forEach(item => {
                                item.classList.remove('unread-notification');
                                // item.style.fontWeight = 'normal'; 
                            });
                        }
                        if (adminMarkAllReadLink) { 
                           adminMarkAllReadLink.style.display = 'none';
                        }
                        // Consider not closing the dropdown immediately to show the "read" state
                        // setTimeout(function() {
                        //    if (adminNotificationsDropdown) adminNotificationsDropdown.classList.remove('show');
                        // }, 1000); 
                    } else {
                        console.error('Failed to mark admin notifications as read:', data.message);
                        alert('Could not mark notifications as read: ' + (data.message || 'Unknown error from server.')); 
                    }
                })
                .catch(error => {
                    console.error('Error in admin mark all read AJAX:', error);
                    alert('An error occurred while marking notifications as read. Check console for details.');
                });
            });
        }
    });
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>