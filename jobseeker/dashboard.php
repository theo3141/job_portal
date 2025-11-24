<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];
$jobseeker_name = $_SESSION['user_name'] ?? 'Job Seeker'; // Get user's name
$search_query = "";

// Fetch unread notifications (existing code - no change)
$sql_notifications = "SELECT id, message, date, is_read FROM notifications WHERE user_id = ? ORDER BY date DESC LIMIT 5";
$stmt_notifications = $conn->prepare($sql_notifications);
$stmt_notifications->bind_param("i", $jobseeker_id);
$stmt_notifications->execute();
$result_notifications_data = $stmt_notifications->get_result();
$notifications_list = [];
while ($row = $result_notifications_data->fetch_assoc()) {
    $notifications_list[] = $row;
}
$stmt_notifications->close();

$sql_unread_count = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt_unread_count = $conn->prepare($sql_unread_count);
$stmt_unread_count->bind_param("i", $jobseeker_id);
$stmt_unread_count->execute();
$result_unread_count_data = $stmt_unread_count->get_result()->fetch_assoc();
$unread_notification_count = $result_unread_count_data['unread_count'];
$stmt_unread_count->close();

// Fetch Jobs - UPDATED SQL to include requirement fields
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $sql_jobs = "SELECT id, title, description, location, salary, job_type, experience_level, education_level, skills_required, created_at
                 FROM jobs
                 WHERE (title LIKE ? OR location LIKE ? OR skills_required LIKE ?) AND status = 'active'
                 ORDER BY created_at DESC"; // Added skills to search
    $stmt_jobs = $conn->prepare($sql_jobs);
    $search_param = "%$search_query%";
    $stmt_jobs->bind_param("sss", $search_param, $search_param, $search_param); // 3 params now
    $stmt_jobs->execute();
    $result_jobs = $stmt_jobs->get_result();
} else {
    $sql_jobs = "SELECT id, title, description, location, salary, job_type, experience_level, education_level, skills_required, created_at
                 FROM jobs
                 WHERE status = 'active'
                 ORDER BY created_at DESC";
    $result_jobs = $conn->query($sql_jobs);
}

// Fetch Ongoing Jobs
$sql_ongoing = "SELECT j.id, j.title, j.location, j.salary, j.job_type
                FROM applications a
                JOIN jobs j ON a.job_id = j.id
                WHERE a.jobseeker_id = ? AND a.status = 'accepted'";
$stmt_ongoing = $conn->prepare($sql_ongoing);
$stmt_ongoing->bind_param("i", $jobseeker_id);
$stmt_ongoing->execute();
$result_ongoing = $stmt_ongoing->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobseeker Dashboard - <?php echo htmlspecialchars($jobseeker_name); ?></title>
    <link rel="stylesheet" href="../styles.css"> <!-- Your global styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


    <style>
        :root {
            --primary-color: #1D3557;
            --secondary-color: #457B9D;
            --accent-color: #A8DADC; /* Light teal */
            --light-bg: #F1F6F9; /* Very light blue/grey */
            --card-bg: #FFFFFF;
            --text-dark: #2c3e50; /* Darker text for better readability */
            --text-light: #7f8c8d; /* Lighter text for meta info */
            --green-accent: #28a745;
            --red-accent: #dc3545;
            --yellow-accent: #ffc107;
            --blue-accent: #007bff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.7;
        }
        .container { width: 90%; max-width: 1200px; margin: 0 auto; padding: 20px 0;} /* Removed side margin for sections to go full width */

        /* NAV BAR */
        nav.top-nav { /* Renamed for clarity */
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            padding: 15px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            position: sticky; /* Sticky nav */
            top: 0;
            z-index: 999;
        }
        nav.top-nav a {
            color: white; text-decoration: none; font-weight: 500;
            padding: 10px 18px; border-radius: 6px;
            transition: background-color 0.25s ease-out, transform 0.2s ease-out;
            display: inline-flex; align-items: center;
        }
        nav.top-nav a i { margin-right: 8px; font-size: 1.1em; }
        nav.top-nav a:hover, nav.top-nav a.active { /* Added .active class possibility */
            background-color: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

        /* Dashboard Header */
        .dashboard-header {
            text-align: center;
            padding: 30px 20px;
            background-color: var(--card-bg);
            border-radius: 0 0 12px 12px; /* Rounded bottom corners */
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .dashboard-header h2 {
            color: var(--primary-color);
            font-size: 2.2em;
            font-weight: 600;
            margin: 0;
        }
        .dashboard-header h2 i { margin-right: 12px; }


        /* Session Messages */
        .session-message {
            padding: 12px 18px; border-radius: 8px; margin: 0 0 20px 0; /* Centered in container */
            text-align: center; font-weight: 500; font-size: 0.95em;
            max-width: 800px; margin-left: auto; margin-right: auto; /* Center message box */
        }
        .session-message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .session-message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}


        /* Search Bar */
        .search-bar-section { /* New wrapper for search */
            padding: 25px 20px;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .search-bar { text-align: center; }
        .search-bar input[type="text"] {
            padding: 14px 20px; width: 70%; max-width: 500px;
            border: 1px solid #ddd; border-radius: 8px;
            font-size: 1em; outline-color: var(--secondary-color);
        }
        .search-bar button[type="submit"] {
            padding: 14px 25px; background: var(--primary-color); color: white;
            border: none; border-radius: 8px; cursor: pointer;
            font-size: 1em; font-weight: 500; margin-left: 10px;
            transition: background-color 0.3s;
        }
        .search-bar button[type="submit"]:hover { background: var(--secondary-color); }
        .search-bar .clear-search-link {
            margin-left: 15px; color: var(--secondary-color); text-decoration: none;
            font-size: 0.9em; border-bottom: 1px dotted var(--secondary-color);
        }
        .search-bar .clear-search-link:hover { color: var(--primary-color); border-bottom-color: var(--primary-color);}


        /* Section Styling */
        .dashboard-section {
            background: var(--card-bg);
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .dashboard-section h3 {
            color: var(--primary-color);
            font-size: 1.6em;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            text-align: left; /* Align section headers left */
        }
        .dashboard-section h3 i { margin-right: 10px; color: var(--secondary-color); }


        /* Job Item / Card Styling */
        ul.job-list { list-style: none; padding: 0; display: grid; gap: 20px; }
        /* For single column on smaller screens, then multi-column */
        @media (min-width: 768px) {
            ul.job-list.available-jobs { grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); }
        }

        li.job-card {
            background: #fdfdfd; /* Slightly off-white for card itself */
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.07);
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            display: flex;
            flex-direction: column;
        }
        li.job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .job-card .job-title {
            font-size: 1.25em; font-weight: 600; color: var(--primary-color);
            margin-bottom: 8px;
        }
        .job-card .job-meta {
            font-size: 0.9em; color: var(--text-light);
            margin-bottom: 5px; display: flex; align-items: center;
        }
        .job-card .job-meta i { margin-right: 7px; color: var(--secondary-color); width: 16px; /* Icon alignment */ }
        .job-card .job-meta strong { font-weight: 500; color: var(--text-dark); margin-left: 5px;} /* For salary amount */

        .job-card .job-description-preview {
            font-size: 0.95em; color: var(--text-dark);
            margin-top: 12px; margin-bottom: 15px;
            line-height: 1.6;
            /* Simple way to truncate text - more advanced would be JS */
            max-height: 6.0em; /* approx 3-4 lines */
            overflow: hidden;
            text-overflow: ellipsis;
            /* For a "Read more" link, you'd need JS or a different approach */
        }
        .job-card .job-actions { margin-top: auto; /* Pushes actions to bottom of card */ padding-top: 15px; border-top: 1px solid #f0f0f0;}

        .empty-state { text-align: center; color: var(--text-light); font-style: italic; padding: 30px 0; font-size: 1.05em;}
        .empty-state i { font-size: 2.5em; display: block; margin-bottom: 10px; color: #ced4da;}


        /* Notification Styles (Copied from previous, ensure consistency) */
        .notification-container { position: relative; display: inline-block; }
        a#notificationLink { color: white; text-decoration: none; font-weight: 500; padding: 10px 12px; border-radius: 5px; transition: background 0.3s; position: relative; display: inline-flex; align-items: center; }
        a#notificationLink:hover { background-color: rgba(255,255,255,0.1); }
        a#notificationLink i { margin-right: 0; font-size: 1.2em; }
        .notification-badge { position: absolute; top: 5px; right: 0px; background-color: var(--red-accent); color: white; border-radius: 50%; padding: 2px 5px; font-size: 0.7em; font-weight: bold; line-height: 1; border: 1px solid var(--primary-color); }
        .notifications-dropdown { /* Styles from previous answer */ display: none; position: absolute; top: calc(100% + 10px); right: 0; background-color: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.15); min-width: 340px; max-width: 400px; max-height: 400px; overflow-y: auto; z-index: 1001; color: var(--text-dark); }
        .notifications-dropdown.show { display: block; }
        .notifications-dropdown .notification-header { padding: 12px 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background-color: #f8f9fa; }
        .notifications-dropdown .notification-header h4 { margin: 0; color: var(--primary-color); font-size: 1.1em; font-weight: 600; }
        .notifications-dropdown .mark-all-read { font-size: 0.8em; color: var(--blue-accent); text-decoration: none; }
        .notifications-dropdown .mark-all-read:hover { text-decoration: underline; }
        .notifications-dropdown ul { list-style: none; padding: 0; margin: 0; }
        .notifications-dropdown li { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; background: #fff; cursor: pointer; transition: background-color 0.2s; }
        .notifications-dropdown li:hover { background-color: #f4f7f9; }
        .notifications-dropdown li:last-child { border-bottom: none; }
        .notifications-dropdown li p { margin: 0 0 3px 0; font-size: 0.9em; color: var(--text-dark); line-height: 1.5; }
        .notifications-dropdown li small { font-size: 0.75em; color: var(--text-light); }
        .notifications-dropdown li.unread-notification p { font-weight: 600; color: var(--primary-color); }
        .notifications-dropdown .no-notifications { padding: 20px 15px; text-align: center; color: var(--text-light); font-size: 0.9em; }
        .notifications-dropdown .notification-footer { padding: 10px 15px; text-align: center; border-top: 1px solid #eee; background-color: #f8f9fa; }
        .notifications-dropdown .notification-footer a { color: var(--primary-color); text-decoration: none; font-weight: 500; font-size: 0.9em; }
        .notifications-dropdown .notification-footer a:hover { text-decoration: underline; }


        /* --- MODAL STYLES (Copied and slightly adjusted for consistency) --- */
        .btn-view-requirements {
            background-color: var(--blue-accent); color: white;
            padding: 9px 16px; border: none; border-radius: 6px;
            cursor: pointer; font-size: 0.9em; margin-top: 12px;
            transition: background-color 0.3s, transform 0.2s;
            font-weight: 500; display: inline-flex; align-items: center;
        }
        .btn-view-requirements i { margin-right: 7px; }
        .btn-view-requirements:hover { background-color: #0056b3;  transform: translateY(-2px);}

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); padding-top: 50px; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 25px 30px; border: none; width: 90%; max-width: 650px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.25); position: relative; animation: fadeInModal 0.3s ease-out; }
        @keyframes fadeInModal { from {opacity: 0; transform: translateY(-30px);} to {opacity: 1; transform: translateY(0);} }
        .close-button { color: #777; float: right; font-size: 30px; font-weight: bold; position: absolute; top: 15px; right: 20px; }
        .close-button:hover, .close-button:focus { color: #333; text-decoration: none; cursor: pointer; }
        .modal-content h3#modalJobTitle { margin-top: 0; color: var(--primary-color); font-size: 1.5em; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; font-weight: 600; }
        .modal-body p { margin-bottom: 12px; line-height: 1.6; font-size: 1em; color: var(--text-dark); }
        .modal-body p strong { color: var(--primary-color); min-width: 160px; display: inline-block; font-weight: 600; }
        #modalSkillsList { list-style: none; padding-left: 0; margin-top: 5px; margin-bottom: 15px; }
        #modalSkillsList li { background-color: #e9ecef; padding: 6px 12px; border-radius: 15px; display: inline-block; margin-right: 8px; margin-bottom: 8px; font-size: 0.9em; color: #343a40; border: 1px solid #ced4da; }
        .modal-footer { margin-top: 30px; text-align: right; padding-top: 20px; border-top: 1px solid #eee; }
        .btn-apply-modal, .btn-cancel-modal { padding: 10px 22px; border-radius: 6px; font-size: 1em; cursor: pointer; transition: background-color 0.3s, transform 0.2s; margin-left: 10px; font-weight: 500; border: none; }
        .btn-apply-modal:hover, .btn-cancel-modal:hover { transform: translateY(-2px); }
        .btn-apply-modal { background-color: var(--green-accent); color: white; }
        .btn-apply-modal:hover { background-color: #1e7e34; }
        .btn-cancel-modal { background-color: #6c757d; color: white; }
        .btn-cancel-modal:hover { background-color: #545b62; }

        /* Footer Styling */
        footer.page-footer {
            background-color: var(--primary-color);
            color: #bdc3c7; /* Lighter grey for footer text */
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
            font-size: 0.9em;
        }
        footer.page-footer a {
            color: var(--accent-color);
            text-decoration: none;
        }
        footer.page-footer a:hover {
            text-decoration: underline;
        }

    </style>
</head>
<body>

    <nav class="top-nav">
        <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a> <!-- Updated Icon -->
        <div class="notification-container">
            <a href="#" id="notificationLink">
                <i class="fas fa-bell"></i>
                <?php if ($unread_notification_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_notification_count; ?></span>
                <?php endif; ?>
            </a>
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="notification-header">
                    <h4>Notifications</h4>
                    <?php if ($unread_notification_count > 0): ?>
                        <a href="mark_all_read.php" class="mark-all-read">Mark all as read</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($notifications_list)): ?>
                    <ul>
                        <?php foreach ($notifications_list as $notification): ?>
                            <li class="<?php echo $notification['is_read'] == 0 ? 'unread-notification' : ''; ?>">
                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                <small><i class="far fa-clock"></i> <?php echo date("M d, Y h:i A", strtotime($notification['date'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="no-notifications"><i class="fas fa-bell-slash"></i> No new notifications.</p>
                <?php endif; ?>
                <div class="notification-footer">
                    <a href="all_notifications.php">View all notifications</a>
                </div>
            </div>
        </div>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="dashboard-header">
         <h2><i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($jobseeker_name); ?>!</h2>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <p class="session-message success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
        <?php elseif (isset($_SESSION['error'])): ?>
            <p class="session-message error"><i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <section class="search-bar-section">
            <form class="search-bar" method="GET" action="dashboard.php">
                <input type="text" name="search" placeholder="Search by job title, skills, or location..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search Jobs</button>
                <?php if (!empty($search_query)): ?>
                    <a href="dashboard.php" class="clear-search-link">Clear Search</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="dashboard-section">
            <h3><i class="fas fa-history"></i> My Accepted Applications</h3>
            <?php if ($result_ongoing && $result_ongoing->num_rows > 0): ?>
                <ul class="job-list"> <!-- Added class -->
                    <?php while ($ongoing = $result_ongoing->fetch_assoc()): ?>
                        <li class="job-card"> <!-- Changed to job-card -->
                            <div class="job-title"><?php echo htmlspecialchars($ongoing['title']); ?></div>
                            <div class="job-meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ongoing['location']); ?></div>
                            <div class="job-meta"><i class="fas fa-money-bill-wave"></i> Salary: <strong>₱<?php echo number_format($ongoing['salary'], 2); ?></strong></div>
                            <div class="job-meta"><i class="fas fa-briefcase"></i> Type: <?php echo htmlspecialchars($ongoing['job_type'] ?? 'N/A'); ?></div>
                            <div class="job-meta" style="color: var(--green-accent); font-weight: 600; margin-top:10px;"><i class="fas fa-check-circle"></i> Status: Accepted</div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="empty-state"><i class="far fa-calendar-times"></i> You have no ongoing (accepted) job applications.</p>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <h3><i class="fas fa-briefcase"></i> Explore Available Jobs</h3>
            <?php if ($result_jobs && $result_jobs->num_rows > 0): ?>
                <ul class="job-list available-jobs"> <!-- Added classes -->
                    <?php while ($job = $result_jobs->fetch_assoc()):
                        $job_type_display = !empty($job['job_type']) ? htmlspecialchars($job['job_type']) : 'Not specified';
                        $experience_level_display = !empty($job['experience_level']) ? htmlspecialchars($job['experience_level']) : 'Not specified';
                        $education_level_display = !empty($job['education_level']) ? htmlspecialchars($job['education_level']) : 'Not specified';
                        $skills_for_data_attr = !empty($job['skills_required']) ? htmlspecialchars($job['skills_required']) : '';
                        $posted_date = new DateTime($job['created_at']);
                        $now = new DateTime();
                        $interval = $now->diff($posted_date);
                        $posted_ago = "";
                        if ($interval->y) $posted_ago = $interval->y . "y ago";
                        elseif ($interval->m) $posted_ago = $interval->m . "m ago";
                        elseif ($interval->d) $posted_ago = $interval->d . "d ago";
                        elseif ($interval->h) $posted_ago = $interval->h . "h ago";
                        elseif ($interval->i) $posted_ago = $interval->i . "m ago";
                        else $posted_ago = "Just now";

                    ?>
                        <li class="job-card">
                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                            <div class="job-meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></div>
                            <div class="job-meta"><i class="fas fa-money-bill-wave"></i> Salary: <strong>₱<?php echo number_format($job['salary'], 2); ?></strong></div>
                            <div class="job-meta"><i class="fas fa-business-time"></i> Type: <?php echo $job_type_display; ?></div>
                             <div class="job-meta"><i class="far fa-calendar-alt"></i> Posted: <?php echo $posted_ago; ?></div>

                            <p class="job-description-preview"><?php echo substr(nl2br(htmlspecialchars($job['description'])), 0, 150) . (strlen($job['description']) > 150 ? '...' : ''); ?></p>

                            <div class="job-actions">
                                <button class="btn-view-requirements"
                                        data-job-id="<?php echo $job['id']; ?>"
                                        data-job-title="<?php echo htmlspecialchars($job['title']); ?>"
                                        data-job-type="<?php echo $job_type_display; ?>"
                                        data-experience-level="<?php echo $experience_level_display; ?>"
                                        data-education-level="<?php echo $education_level_display; ?>"
                                        data-skills="<?php echo $skills_for_data_attr; ?>">
                                    <i class="fas fa-list-ul"></i> View & Apply <!-- Updated Icon & Text -->
                                </button>
                            </div>
                            <form method="POST" action="apply.php" style="display:none;" id="applyForm-<?php echo $job['id']; ?>">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                            </form>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                 <p class="empty-state">
                    <i class="fas fa-search-minus"></i>
                    <?php if (!empty($search_query)): ?>
                        No jobs found matching "<?php echo htmlspecialchars($search_query); ?>". Try different keywords.
                    <?php else: ?>
                        No jobs currently available. Please check back later!
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    </div>

    <!-- Requirements Modal HTML (from previous integration) -->
    <div id="requirementsModal" class="modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h3 id="modalJobTitle">Job Requirements</h3>
            <div class="modal-body">
                <p><strong>Job Type:</strong> <span id="modalJobType"></span></p>
                <p><strong>Experience Level:</strong> <span id="modalExperienceLevel"></span></p>
                <p><strong>Education Level:</strong> <span id="modalEducationLevel"></span></p>
                <p><strong>Skills Required:</strong></p>
                <ul id="modalSkillsList"></ul>
                <p style="margin-top:20px; font-size: 0.9em; color: #555; background-color: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #e9ecef;">
                    <i class="fas fa-info-circle"></i> Review requirements carefully. By proceeding, you confirm you understand them.
                </p>
            </div>
            <div class="modal-footer">
                <button id="proceedToApplyButton" class="btn-apply-modal" data-job-id-to-apply="">
                    <i class="fas fa-paper-plane"></i> Proceed to Apply
                </button>
                <button id="cancelModalButton" class="btn-cancel-modal">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>
            </div>
        </div>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification Dropdown Logic
        const notificationLink = document.getElementById('notificationLink');
        const notificationsDropdown = document.getElementById('notificationsDropdown');
        if (notificationLink && notificationsDropdown) {
            notificationLink.addEventListener('click', function(event) {
                event.preventDefault();
                notificationsDropdown.classList.toggle('show');
            });
            document.addEventListener('click', function(event) {
                if (!notificationLink.contains(event.target) && !notificationsDropdown.contains(event.target)) {
                    notificationsDropdown.classList.remove('show');
                }
            });
        }

        // Requirements Modal Logic
        const modal = document.getElementById('requirementsModal');
        const closeButton = modal.querySelector('.close-button');
        const cancelModalButton = document.getElementById('cancelModalButton');
        const proceedToApplyButton = document.getElementById('proceedToApplyButton');
        const modalJobTitle = document.getElementById('modalJobTitle');
        const modalJobType = document.getElementById('modalJobType');
        const modalExperienceLevel = document.getElementById('modalExperienceLevel');
        const modalEducationLevel = document.getElementById('modalEducationLevel');
        const modalSkillsList = document.getElementById('modalSkillsList');

        document.querySelectorAll('.btn-view-requirements').forEach(button => {
            button.addEventListener('click', function() {
                const jobId = this.dataset.jobId;
                modalJobTitle.textContent = this.dataset.jobTitle + ' - Requirements';
                modalJobType.textContent = this.dataset.jobType || 'Not specified';
                modalExperienceLevel.textContent = this.dataset.experienceLevel || 'Not specified';
                modalEducationLevel.textContent = this.dataset.educationLevel || 'Not specified';
                modalSkillsList.innerHTML = '';
                const skillsString = this.dataset.skills;
                if (skillsString && skillsString.trim() !== '' && skillsString.toLowerCase() !== 'not specified') {
                    const skillsArray = skillsString.split(',').map(skill => skill.trim()).filter(skill => skill);
                    if (skillsArray.length > 0) {
                        skillsArray.forEach(skill => {
                            const li = document.createElement('li');
                            li.textContent = skill;
                            modalSkillsList.appendChild(li);
                        });
                    } else {
                         const li = document.createElement('li'); li.textContent = 'Not specified';
                         li.style.backgroundColor = 'transparent'; li.style.padding = '0'; li.style.border = 'none';
                         modalSkillsList.appendChild(li);
                    }
                } else {
                    const li = document.createElement('li'); li.textContent = 'Not specified';
                    li.style.backgroundColor = 'transparent'; li.style.padding = '0'; li.style.border = 'none';
                    modalSkillsList.appendChild(li);
                }
                proceedToApplyButton.dataset.jobIdToApply = jobId;
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
        });
        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        if(closeButton) closeButton.addEventListener('click', closeModal);
        if(cancelModalButton) cancelModalButton.addEventListener('click', closeModal);
        window.addEventListener('click', function(event) {
            if (event.target === modal) closeModal();
        });
        window.addEventListener('keydown', function(event){
            if(event.key === 'Escape' && modal.style.display === 'block') closeModal();
        });
        if(proceedToApplyButton) proceedToApplyButton.addEventListener('click', function() {
            const jobIdToApply = this.dataset.jobIdToApply;
            const applyForm = document.getElementById('applyForm-' + jobIdToApply);
            if (applyForm) {
                applyForm.submit();
            } else {
                console.error('Apply form not found for job ID: ' + jobIdToApply);
                alert('An error occurred. Could not submit application.');
            }
            closeModal();
        });
    });
    </script>
</body>
</html>
<?php
// Close statements
if (isset($stmt_jobs) && $stmt_jobs instanceof mysqli_stmt) { $stmt_jobs->close(); }
if (isset($stmt_ongoing) && $stmt_ongoing instanceof mysqli_stmt) { $stmt_ongoing->close(); }
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>