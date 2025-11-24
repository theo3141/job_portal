<?php
session_start();
include '../includes/config.php'; // Adjust path as needed

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

// Pagination variables
$limit = 10; // Notifications per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of notifications for the user (for pagination)
$sql_total = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $jobseeker_id);
$stmt_total->execute();
$total_notifications_result = $stmt_total->get_result()->fetch_assoc();
$total_notifications = $total_notifications_result['total'];
$total_pages = ceil($total_notifications / $limit);
$stmt_total->close();

// Fetch notifications for the current page
$sql_all_notifications = "SELECT id, message, date, is_read
                          FROM notifications
                          WHERE user_id = ?
                          ORDER BY date DESC
                          LIMIT ? OFFSET ?";
$stmt_all_notifications = $conn->prepare($sql_all_notifications);
$stmt_all_notifications->bind_param("iii", $jobseeker_id, $limit, $offset);
$stmt_all_notifications->execute();
$result_all_notifications = $stmt_all_notifications->get_result();
$all_notifications_list = [];
while ($row = $result_all_notifications->fetch_assoc()) {
    $all_notifications_list[] = $row;
}
$stmt_all_notifications->close();

// (Optional: Auto mark as read logic - currently commented out)
/*
if (!empty($all_notifications_list)) {
    $ids_to_mark_read = array_column($all_notifications_list, 'id');
    if (!empty($ids_to_mark_read)) {
        $placeholders = implode(',', array_fill(0, count($ids_to_mark_read), '?'));
        $types = str_repeat('i', count($ids_to_mark_read));
        $sql_mark_displayed_read = "UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?";
        $stmt_mark_displayed_read = $conn->prepare($sql_mark_displayed_read);

        $params = $ids_to_mark_read;
        $params[] = $jobseeker_id;

        $stmt_mark_displayed_read->bind_param($types . 'i', ...$params);
        $stmt_mark_displayed_read->execute();
        $stmt_mark_displayed_read->close();
    }
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications</title>
    <link rel="stylesheet" href="../styles.css"> <!-- Your main shared styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #F1F6F9;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            /* padding-top: 20px;  Removed fixed padding, nav will push content */
        }

        nav.top-nav {
            background: linear-gradient(135deg, #457B9D, #1D3557);
            padding: 15px 0;    /* ADJUST THIS VALUE (e.g., 12px 0, 15px 0) TO MATCH OTHER NAV BARS */
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center; /* Vertically aligns items in the nav bar */
            gap: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        nav.top-nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px; /* Padding for the link elements themselves */
            border-radius: 5px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
        }
        nav.top-nav a i {
            margin-right: 8px;
        }
        nav.top-nav a:hover {
            background: #0056b3;
        }


        .container {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            margin-bottom: 20px; /* Added margin at the bottom of container */
        }

        h2.page-title {
            text-align: center;
            margin-bottom: 25px;
            color: #1D3557;
        }
        
        .alert { /* General alert styling for session messages */
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.95em;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .notifications-list-container {
            margin-bottom: 20px;
        }

        .notification-item {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 5px solid #457B9D;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .notification-item.unread {
            background-color: #e9f5ff;
            border-left-color: #1D3557;
        }
        .notification-item.unread p.message{
            font-weight: 600;
            color: #1D3557;
        }

        .notification-item p.message {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #333;
            line-height: 1.5;
        }

        .notification-item small.date {
            font-size: 12px;
            color: #777;
            display: block;
        }

        .empty-state {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 30px 0;
            font-size: 1.1em;
        }

        .pagination {
            text-align: center;
            margin-top: 30px;
            padding-bottom: 10px;
        }

        .pagination a, .pagination span {
            color: #1D3557;
            margin: 0 4px;
            padding: 8px 14px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s;
            font-size: 14px;
        }

        .pagination a:hover {
            background-color: #457B9D;
            color: white;
            border-color: #457B9D;
        }

        .pagination .current-page {
            background-color: #1D3557;
            color: white;
            border-color: #1D3557;
            font-weight: bold;
        }
        .pagination .disabled {
            color: #aaa;
            pointer-events: none;
            border-color: #eee;
        }

        .back-link-footer {
            display: block;
            margin-top: 25px;
            text-decoration: none;
            color: #1D3557;
            font-weight: bold;
            transition: color 0.3s;
            text-align: center;
        }
        .back-link-footer:hover {
            color: #457B9D;
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="container">
        <h2 class="page-title"><i class="fas fa-bell"></i> All My Notifications</h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                 <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="notifications-list-container">
            <?php if (count($all_notifications_list) > 0): ?>
                <?php foreach ($all_notifications_list as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                        <p class="message"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <small class="date"><i class="far fa-clock"></i> <?php echo date("F j, Y, g:i a", strtotime($notification['date'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty-state"><i class="fas fa-folder-open"></i> You have no notifications yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1 && count($all_notifications_list) > 0): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="all_notifications.php?page=<?php echo $page - 1; ?>">« Prev</a>
            <?php else: ?>
                <span class="disabled">« Prev</span>
            <?php endif; ?>

            <?php
            $start_loop = max(1, $page - 2);
            $end_loop = min($total_pages, $page + 2);

            if ($start_loop > 1) {
                echo '<a href="all_notifications.php?page=1">1</a>';
                if ($start_loop > 2) {
                    echo '<span>...</span>';
                }
            }

            for ($i = $start_loop; $i <= $end_loop; $i++): ?>
                <a href="all_notifications.php?page=<?php echo $i; ?>" class="<?php echo ($page == $i) ? 'current-page' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php
            if ($end_loop < $total_pages) {
                if ($end_loop < $total_pages - 1) {
                    echo '<span>...</span>';
                }
                echo '<a href="all_notifications.php?page='.$total_pages.'">'.$total_pages.'</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="all_notifications.php?page=<?php echo $page + 1; ?>">Next »</a>
            <?php else: ?>
                <span class="disabled">Next »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link-footer"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    </div>
    <?php $conn->close(); ?>
</body>
</html>