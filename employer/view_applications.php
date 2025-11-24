<?php
session_start();
include '../includes/config.php'; // Assuming config.php sets up $conn

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer' || !isset($_SESSION['user_id'])) {
    $_SESSION['error_message_login'] = "Please log in to access this page.";
    header("Location: ../login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

if (!$conn) {
    die("Database connection failed. Check includes/config.php");
}

// --- SEARCH PARAMETERS ---
$search_applicant_name = $_GET['search_name'] ?? '';
$search_date_from = $_GET['search_date_from'] ?? '';
$search_date_to = $_GET['search_date_to'] ?? '';

// --- SORTING LOGIC ---
$db_group_sort_column = 'jobs.title';
$group_sort_order = 'ASC';
$user_sort_param = $_GET['sort'] ?? 'applied_at'; 
$user_sort_order_param = $_GET['order'] ?? 'DESC';      
$allowed_user_sort_columns = [
    'applicant_name' => 'users.name',
    'applied_at' => 'applications.applied_at',
    'status' => 'applications.status'
];
if (!array_key_exists($user_sort_param, $allowed_user_sort_columns)) {
    $db_user_sort_column = 'applications.applied_at'; 
    $user_sort_param = 'applied_at'; 
} else {
    $db_user_sort_column = $allowed_user_sort_columns[$user_sort_param];
}
$user_sort_order_param = strtoupper($user_sort_order_param);
if ($user_sort_order_param !== 'ASC' && $user_sort_order_param !== 'DESC') {
    $user_sort_order_param = 'DESC'; 
}
$db_tie_breaker_column = 'applications.id';
$tie_breaker_order = 'DESC';
// --- END SORTING LOGIC ---

// --- BUILD SQL QUERY ---
$sql_select = "SELECT applications.id, applications.job_id, applications.jobseeker_id,
               users.name AS applicant_name, users.email AS applicant_email, 
               users.resume AS applicant_resume, users.profile_picture AS applicant_profile_picture,
               jobs.title AS job_title, applications.status, applications.applied_at";
$sql_from_join = " FROM applications
                   JOIN users ON applications.jobseeker_id = users.id
                   JOIN jobs ON applications.job_id = jobs.id";
$sql_where_conditions = ["jobs.employer_id = ?"];
$sql_params = ['i', $employer_id];
if (!empty($search_applicant_name)) {
    $sql_where_conditions[] = "users.name LIKE ?";
    $sql_params[0] .= 's';
    $sql_params[] = "%" . $search_applicant_name . "%";
}
if (!empty($search_date_from)) {
    $sql_where_conditions[] = "DATE(applications.applied_at) >= ?";
    $sql_params[0] .= 's';
    $sql_params[] = $search_date_from;
}
if (!empty($search_date_to)) {
    $sql_where_conditions[] = "DATE(applications.applied_at) <= ?";
    $sql_params[0] .= 's';
    $sql_params[] = $search_date_to;
}
$sql_where_clause = " WHERE " . implode(" AND ", $sql_where_conditions);
$sql_order_by = " ORDER BY " . $db_group_sort_column . " " . $group_sort_order . ", " 
                         . $db_user_sort_column . " " . $user_sort_order_param . ", "
                         . $db_tie_breaker_column . " " . $tie_breaker_order;
$sql = $sql_select . $sql_from_join . $sql_where_clause . $sql_order_by;
$stmt = $conn->prepare($sql);
if (!$stmt) { die("Prepare failed: (" . $conn->errno . ") " . $conn->error . " | SQL: " . $sql); }
if (count($sql_params) > 1) { $stmt->bind_param(...$sql_params); }
$stmt->execute();
$result = $stmt->get_result();
$applications = [];
while ($row = $result->fetch_assoc()) { $applications[] = $row; }
$stmt->close();
// --- END BUILD SQL QUERY ---

$base_uploads_web_path = '../uploads/';
$profile_pic_dir_relative_web = "profile_pics/";
$resume_dir_relative_web = "resumes/";
$base_uploads_server_path = realpath(__DIR__ . '/../uploads'); 

function get_sort_link($column_param_for_link, $display_text, $current_user_sort_param, $current_user_sort_order_param, $current_search_params = []) {
    $link_order = ($current_user_sort_param == $column_param_for_link && $current_user_sort_order_param == 'ASC') ? 'DESC' : 'ASC';
    $arrow = '';
    if ($current_user_sort_param == $column_param_for_link) {
        $arrow = ($current_user_sort_order_param == 'ASC') ? ' <i class="fas fa-arrow-up"></i>' : ' <i class="fas fa-arrow-down"></i>';
    }
    $search_query_string = http_build_query($current_search_params);
    $search_query_string = $search_query_string ? '&' . $search_query_string : '';
    return "<a href=\"?sort={$column_param_for_link}&order={$link_order}{$search_query_string}\">{$display_text}{$arrow}</a>";
}
$current_search_params_for_links = [];
if (!empty($search_applicant_name)) $current_search_params_for_links['search_name'] = $search_applicant_name;
if (!empty($search_date_from)) $current_search_params_for_links['search_date_from'] = $search_date_from;
if (!empty($search_date_to)) $current_search_params_for_links['search_date_to'] = $search_date_to;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Applications - Employer</title>
    <link rel="stylesheet" href="../styles.css"> <!-- General Poppins font, etc. -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        /* COPIED COLOR PALETTE FROM DASHBOARD */
        :root {
            --theme-primary: #00796B; /* Teal */
            --theme-primary-dark: #004D40; /* Darker Teal */
            --theme-secondary: #009688; /* Lighter Teal for gradients or secondary elements */
            --theme-accent: #FFC107; /* Amber for contrasting accents (optional) */
            
            --background-main: #F4F6F8; /* Very light gray */
            --background-card: #FFFFFF;
            --background-list-item: #E8F5E9; /* Light mint green - for even rows */

            --text-primary: #263238; /* Dark Slate Gray */
            --text-secondary: #546E7A; /* Lighter Slate Gray */
            --text-on-primary: #FFFFFF; /* Text on dark backgrounds */
            --text-placeholder: #78909C;

            --border-light: #CFD8DC; /* Light Blue Gray */
            --border-medium: #B0BEC5;
            --border-table-row: #e0e7ef; /* Defined from previous manage_jobs styling */
            
            --shadow-color: rgba(0, 0, 0, 0.08);

            /* Alerts */
            --alert-success-bg: #E0F2F1;
            --alert-success-text: #00695C;
            --alert-success-border: #A7FFEB;

            --alert-error-bg: #FFEBEE;
            --alert-error-text: #C62828;
            --alert-error-border: #FFCDD2;

            --alert-info-bg: #E3F2FD;
            --alert-info-text: #1565C0;
            --alert-info-border: #BBDEFB;
        }

        body { 
            font-family: 'Poppins', sans-serif; margin: 0; padding: 0; 
            background: var(--background-main); color: var(--text-primary); 
        }
        /* Navbar styles (using dashboard colors) */
        nav { 
            background: linear-gradient(135deg, var(--theme-secondary), var(--theme-primary)); 
            padding: 12px 0; display: flex; justify-content: center; align-items: center; gap: 20px;
            box-shadow: 0 2px 4px var(--shadow-color); /* Using shadow-color */
        }
        nav a { 
            color: var(--text-on-primary); text-decoration: none; font-weight: bold; 
            padding: 10px 20px; border-radius: 5px; transition: background 0.3s; 
            display: inline-flex; align-items: center;
        }
        nav a i { margin-right: 8px; }
        nav a:hover, nav a.active { background: var(--theme-primary-dark); }

        .container { width: 90%; max-width: 1350px; margin: 20px auto; }
        h2.page-title { 
            color: var(--theme-primary); text-align: center; font-size: 2em; 
            margin-bottom: 25px; font-weight: 600; 
        }
        h2.page-title i { margin-right: 10px; color: var(--theme-secondary); }

        /* Search Bar Styling (using dashboard card style) */
        .search-bar-container {
            background-color: var(--background-card);
            padding: 20px 25px; margin-bottom: 20px; border-radius: 12px;
            box-shadow: 0px 2px 8px var(--shadow-color);
            display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
        }
        .search-bar-container .form-group { display: flex; flex-direction: column; flex-grow: 1; }
        .search-bar-container label { font-size: 0.85em; font-weight: 500; color: var(--text-secondary); margin-bottom: 5px; }
        .search-bar-container input[type="text"],
        .search-bar-container input[type="date"] {
            padding: 9px 12px; border: 1px solid var(--border-light); border-radius: 6px;
            font-size: 0.9em; min-width: 180px; background-color: #fff; color: var(--text-primary);
        }
        .search-bar-container input::placeholder { color: var(--text-placeholder); }
        .search-bar-container input:focus {
            outline: none; border-color: var(--theme-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25); /* Using theme primary for focus */
        }
        .search-bar-container .btn { /* General button style adapted for search */
            color: var(--text-on-primary);
            font-weight: bold;
        }
        .search-bar-container .btn-search { background-color: var(--theme-primary); }
        .search-bar-container .btn-search:hover { background-color: var(--theme-primary-dark); }
        .search-bar-container .btn-clear-search { background-color: var(--text-secondary); }
        .search-bar-container .btn-clear-search:hover { background-color: var(--text-primary); }
        .search-bar-container .search-actions { display: flex; gap: 10px; align-items: flex-end; padding-bottom: 1px; }

        /* Table Container (using dashboard card style) */
        .table-container { 
            background: var(--background-card); padding: 25px; margin: 15px 0; 
            border-radius: 12px; box-shadow: 0px 4px 12px var(--shadow-color); 
            overflow-x: auto; 
        }
        table { width: 100%; border-collapse: collapse; margin-top: 0; } /* No margin for table itself */
        th, td { 
            padding: 12px 10px; border-bottom: 1px solid var(--border-table-row); /* Only bottom border */
            text-align: left; vertical-align: middle; font-size: 0.9rem; 
        }
        th:first-child, td:first-child { padding-left: 0; } /* Remove left padding on first cell */
        th:last-child, td:last-child { padding-right: 0; } /* Remove right padding on last cell */

        th.applicant-header-cell, td.applicant-cell { text-align: left; width: 20%; }
        td.applicant-cell { display: flex; align-items: center; }
        .applicant-profile-pic-placeholder { width: 45px; height: 45px; margin-right: 12px; border-radius: 50%; background-color: var(--border-light); border: 2px solid var(--border-medium); }
        
        th.applied-on-header, td.applied-on-cell, th.status-header, td.status-cell, 
        th.update-status-header, td.update-status-cell, th.resume-header, td.resume-cell, 
        th.remove-action-header, td.remove-action-cell { text-align: center; }
        
        td.status-cell { width: 110px; }
        td.update-status-cell { width: 190px; }
        td.remove-action-cell { width: 100px; }

        thead th { /* Using theme primary for table header */
            background-color: var(--theme-primary); color: var(--text-on-primary); 
            font-weight: 600; font-size: 0.9rem; letter-spacing: 0.5px;
            text-transform: uppercase; border-bottom: 2px solid var(--theme-primary-dark);
        }
        th a { color: var(--text-on-primary); text-decoration: none; display: inline-block; }
        th a:hover { text-decoration: underline; }
        th .fas { font-size: 0.8em; margin-left: 6px; }

        tbody tr:nth-child(even) { background-color: var(--background-list-item); } 
        tbody tr:hover { background-color: #d1e7f7; } /* Light blue hover, adjust if needed */

        .applicant-profile-pic { 
            width: 45px; height: 45px; border-radius: 50%; 
            object-fit: cover; margin-right: 12px; 
            border: 2px solid var(--border-medium); background-color: var(--border-light); 
        }
        .status-badge { 
            padding: 5px 10px; border-radius: 20px; font-weight: 500; 
            font-size: 0.8rem; display: inline-block; color: var(--text-on-primary); 
            min-width: 75px; text-align: center; 
        }
        .status-badge.pending { background-color: var(--theme-accent); color: var(--text-primary); } /* Amber with dark text */
        .status-badge.accepted { background-color: #28a745; } /* Standard green */
        .status-badge.rejected { background-color: #dc3545; } /* Standard red */

        .update-status-form select { 
            padding: 7px 9px; border: 1px solid var(--border-light); border-radius: 5px; 
            margin-right: 8px; font-size: 0.85em; min-width: 110px; 
            background-color: #fff; color: var(--text-primary);
        }
        .update-status-form select:focus {
            outline: none; border-color: var(--theme-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25);
        }
        .btn { 
            padding: 8px 12px; border: none; cursor: pointer; border-radius: 6px; 
            font-size: 0.85em; font-weight: bold; /* From dashboard */ 
            transition: background-color 0.2s, transform 0.15s; 
            text-decoration: none; display: inline-flex; 
            align-items: center; justify-content: center; margin: 2px;
            color: var(--text-on-primary); /* Default button text color */
        }
        .btn i { margin-right: 5px; }
        .btn:hover { transform: translateY(-1px); }

        /* Button specific colors from dashboard theme where applicable */
        .btn-update, .btn-view-resume { background-color: var(--theme-secondary); }
        .btn-update:hover, .btn-view-resume:hover { background-color: var(--theme-primary); }
        
        .btn-remove-applicant { background-color: #e74c3c; } /* More distinct red */
        .btn-remove-applicant:hover { background-color: #c0392b; }

        .no-resume { color: var(--text-secondary); font-style: italic; font-size: 0.85em; }
        .ajax-status-msg { display: block; font-size: 0.8em; margin-top: 6px; font-weight: 500; }
        .ajax-status-msg.success { color: var(--alert-success-text); }
        .ajax-status-msg.error { color: var(--alert-error-text); }

        .empty-state { 
            text-align: center; padding: 50px 20px; font-size: 1.1em; 
            color: var(--text-secondary); background-color: var(--background-card); 
            border-radius: 8px; border: 1px dashed var(--border-light); margin-top: 20px;
        }
        .empty-state i { font-size: 2.5em; display: block; margin-bottom: 15px; color: var(--theme-secondary); }
        .empty-state a { color: var(--theme-primary); font-weight: 500;}
        .empty-state a:hover { color: var(--theme-primary-dark); }

        footer { 
            background: var(--theme-primary-dark); /* Darker footer */
            color: var(--text-on-primary); text-align: center; 
            padding: 15px 0; margin-top: 30px; 
        }
        /* Global status messages (using dashboard alert colors) */
        #global-status-message { 
            padding: 12px 15px; margin: 15px 0; border-radius: 8px; /* Consistent with dashboard alerts */
            text-align: center; font-weight: 500; display: none; font-size: 0.95em;
        }
        #global-status-message.success { background-color: var(--alert-success-bg); color: var(--alert-success-text); border: 1px solid var(--alert-success-border);}
        #global-status-message.error { background-color: var(--alert-error-bg); color: var(--alert-error-text); border: 1px solid var(--alert-error-border);}

    </style>
</head>
<body>

    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage_jobs.php"><i class="fas fa-edit"></i> Manage Jobs</a>
        <a href="view_applications.php" class="active"><i class="fas fa-users"></i> View Applications</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="container">
        <h2 class="page-title"><i class="fas fa-file-alt"></i> Job Applications Received</h2>
        
        <div class="search-bar-container">
            <form action="view_applications.php" method="GET" id="searchApplicationForm" style="display: contents;">
                <div class="form-group">
                    <label for="search_name">Applicant Name:</label>
                    <input type="text" name="search_name" id="search_name" value="<?php echo htmlspecialchars($search_applicant_name); ?>" placeholder="Search by name...">
                </div>
                <div class="form-group">
                    <label for="search_date_from">Applied From:</label>
                    <input type="date" name="search_date_from" id="search_date_from" value="<?php echo htmlspecialchars($search_date_from); ?>">
                </div>
                <div class="form-group">
                    <label for="search_date_to">Applied To:</label>
                    <input type="date" name="search_date_to" id="search_date_to" value="<?php echo htmlspecialchars($search_date_to); ?>">
                </div>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($user_sort_param); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($user_sort_order_param); ?>">

                <div class="search-actions">
                    <button type="submit" class="btn btn-search"><i class="fas fa-search"></i> Search</button>
                    <a href="view_applications.php?sort=<?php echo htmlspecialchars($user_sort_param); ?>&order=<?php echo htmlspecialchars($user_sort_order_param); ?>" class="btn btn-clear-search"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>

        <div id="global-status-message"></div>

        <div class="table-container">
            <?php if (count($applications) > 0) : ?>
                <table>
                    <thead>
                        <tr>
                            <th class="applicant-header-cell"><?php echo get_sort_link('applicant_name', 'Applicant', $user_sort_param, $user_sort_order_param, $current_search_params_for_links); ?></th>
                            <th>Email</th>
                            <th class="job-title-header">Job Title</th>
                            <th class="applied-on-header"><?php echo get_sort_link('applied_at', 'Applied On', $user_sort_param, $user_sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="status-header"><?php echo get_sort_link('status', 'Current Status', $user_sort_param, $user_sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="update-status-header">Update Status</th>
                            <th class="resume-header">Resume</th>
                            <th class="remove-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_job_title_group = null;
                        foreach ($applications as $app) : 
                            $profile_pic_web_path = null; 
                            if (!empty($app['applicant_profile_picture']) && $base_uploads_server_path) {
                                $jobseeker_pic_server_path = rtrim($base_uploads_server_path, '/') . '/' . rtrim($profile_pic_dir_relative_web, '/') . '/' . basename(htmlspecialchars($app['applicant_profile_picture']));
                                if (file_exists($jobseeker_pic_server_path)) {
                                    $profile_pic_web_path = rtrim($base_uploads_web_path, '/') . '/' . rtrim($profile_pic_dir_relative_web, '/') . '/' . basename(htmlspecialchars($app['applicant_profile_picture']));
                                }
                            }
                        ?>
                            <tr id="application-row-<?php echo $app['id']; ?>">
                                <td class="applicant-cell">
                                    <?php if ($profile_pic_web_path): ?>
                                        <img src="<?php echo $profile_pic_web_path; ?>" alt="<?php echo htmlspecialchars($app['applicant_name']); ?>'s pic" class="applicant-profile-pic">
                                    <?php else: ?>
                                        <div class="applicant-profile-pic-placeholder"></div>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($app['applicant_name']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($app['applicant_email']); ?></td>
                                <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                <td class="applied-on-cell"><?php echo date("M d, Y", strtotime($app['applied_at'])); ?></td>
                                <td class="status-cell">
                                    <span class="status-badge <?php echo strtolower(htmlspecialchars($app['status'])); ?>">
                                        <?php echo ucfirst(htmlspecialchars($app['status'])); ?>
                                    </span>
                                </td>
                                <td class="update-status-cell">
                                    <form class="update-status-form" data-application-id="<?php echo $app['id']; ?>">
                                        <select name="status" class="status-select">
                                            <option value="pending" <?php echo ($app['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="accepted" <?php echo ($app['status'] == 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                                            <option value="rejected" <?php echo ($app['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <button type="submit" class="btn btn-update"><i class="fas fa-sync-alt"></i> Update</button>
                                        <span class="ajax-status-msg" id="status-msg-<?php echo $app['id']; ?>"></span>
                                    </form>
                                </td>
                                <td class="resume-cell">
                                    <?php if (!empty($app['applicant_resume']) && $base_uploads_server_path) : ?>
                                        <?php
                                        $resume_server_path = rtrim($base_uploads_server_path, '/') . '/' . rtrim($resume_dir_relative_web, '/') . '/' . basename(htmlspecialchars($app['applicant_resume']));
                                        if (file_exists($resume_server_path)):
                                            $resume_web_path_link = rtrim($base_uploads_web_path, '/') . '/' . rtrim($resume_dir_relative_web, '/') . '/' . basename(htmlspecialchars($app['applicant_resume']));
                                        ?>
                                            <a href="<?php echo $resume_web_path_link; ?>" target="_blank" class="btn btn-view-resume">
                                                <i class="fas fa-file-pdf"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="no-resume">File Missing</span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="no-resume">Not Provided</span>
                                    <?php endif; ?>
                                </td>
                                <td class="remove-action-cell">
                                    <button class="btn btn-remove-applicant" data-application-id="<?php echo $app['id']; ?>" data-applicant-name="<?php echo htmlspecialchars($app['applicant_name']); ?>">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <?php if (!empty($search_applicant_name) || !empty($search_date_from) || !empty($search_date_to)): ?>
                        No applications found matching your search criteria. <a href="view_applications.php">Clear search</a>.
                    <?php else: ?>
                        No job applications found for your listings yet.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
       // ... (Your existing JavaScript for update status and remove applicant remains the same) ...
    $(document).ready(function () {
        $(".update-status-form").submit(function (event) {
            event.preventDefault(); 
            var form = $(this);
            var applicationId = form.data("application-id");
            var newStatus = form.find("select[name='status']").val();
            var statusMsgSpan = $("#status-msg-" + applicationId);
            var statusCell = form.closest('tr').find('.status-cell .status-badge');
            statusMsgSpan.text("Updating...").removeClass('success error').css("color", "#555");
            $.ajax({
                url: "update_application_status.php", type: "POST",
                data: { application_id: applicationId, status: newStatus },
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        statusMsgSpan.text(response.message).removeClass('error').addClass('success').css("color", "var(--alert-success-text)");
                        statusCell.text(response.new_status_display);
                        statusCell.removeClass('pending accepted rejected').addClass(newStatus.toLowerCase());
                        setTimeout(function () { statusMsgSpan.text(""); }, 3000);
                    } else {
                        statusMsgSpan.text(response.message || "Error: Could not update.").removeClass('success').addClass('error').css("color", "var(--alert-error-text)");
                    }
                },
                error: function () {
                    statusMsgSpan.text("Error: Request failed.").removeClass('success').addClass('error').css("color", "var(--alert-error-text)");
                }
            });
        });
        $(document).on('click', '.btn-remove-applicant', function() {
            var button = $(this); var applicationId = button.data("application-id");
            var applicantName = button.data("applicant-name"); var globalStatusMsg = $('#global-status-message');
            if (confirm("Are you sure you want to remove the application from " + applicantName + "? This action cannot be undone.")) {
                globalStatusMsg.hide().removeClass('success error').text('');
                $.ajax({
                    url: "remove_application.php", type: "POST",
                    data: { application_id: applicationId }, dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $("#application-row-" + applicationId).fadeOut(500, function() {
                                $(this).remove();
                                if ($("table tbody tr").length === 0) {
                                    $(".table-container").html('<div class="empty-state"><i class="fas fa-folder-open"></i>No applications found matching your search criteria. <a href="view_applications.php">Clear search</a>.</div>');
                                }
                            });
                            globalStatusMsg.text(response.message).addClass('success').fadeIn();
                        } else {
                            globalStatusMsg.text(response.message || "Error: Could not remove.").addClass('error').fadeIn();
                        }
                        setTimeout(function() { globalStatusMsg.fadeOut(); }, 5000);
                    },
                    error: function() {
                        globalStatusMsg.text("Error: Request failed. Please try again.").addClass('error').fadeIn();
                        setTimeout(function() { globalStatusMsg.fadeOut(); }, 5000);
                    }
                });
            }
        });
    });
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>