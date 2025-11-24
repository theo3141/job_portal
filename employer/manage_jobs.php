<?php
session_start();
include '../includes/config.php'; // Assuming config.php handles database connection ($conn)

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];
$success_message = null;
$error_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finish_job'])) {
    $job_id = intval($_POST['job_id']);
    $conn->begin_transaction();
    try {
        $sql_delete_applications = "DELETE FROM applications WHERE job_id = ?";
        $stmt_delete_apps = $conn->prepare($sql_delete_applications);
        if (!$stmt_delete_apps) throw new Exception("Prepare failed (applications): " . $conn->error);
        $stmt_delete_apps->bind_param("i", $job_id);
        if (!$stmt_delete_apps->execute()) throw new Exception("Execute failed (applications): " . $stmt_delete_apps->error);
        $stmt_delete_apps->close();

        $sql_delete_job = "DELETE FROM jobs WHERE id = ? AND employer_id = ?";
        $stmt_delete_job = $conn->prepare($sql_delete_job);
        if (!$stmt_delete_job) throw new Exception("Prepare failed (job): " . $conn->error);
        $stmt_delete_job->bind_param("ii", $job_id, $employer_id);
        
        if ($stmt_delete_job->execute()) {
            if ($stmt_delete_job->affected_rows > 0) {
                $conn->commit();
                $success_message = "Job and its applications have been successfully removed!";
            } else {
                $conn->rollback(); 
                $error_message = "Job not found or you do not have permission to remove it.";
            }
        } else {
            throw new Exception("Execute failed (job): " . $stmt_delete_job->error);
        }
        $stmt_delete_job->close();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error removing job: " . $e->getMessage(); 
    }
}

$sql_jobs = "SELECT id, title, description, location, salary, created_at FROM jobs WHERE employer_id = ? ORDER BY created_at DESC";
$stmt_jobs = $conn->prepare($sql_jobs);
if (!$stmt_jobs) {
    $error_message = "Error preparing to fetch jobs: " . $conn->error;
    $result_jobs = null; 
} else {
    $stmt_jobs->bind_param("i", $employer_id);
    $stmt_jobs->execute();
    $result_jobs = $stmt_jobs->get_result();
    $stmt_jobs->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Employer Dashboard</title>
    <link rel="stylesheet" href="../styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- No jQuery needed for this page's current functionality based on provided code -->

    <style>
        /* Color Palette (from your dashboard) */
        :root {
            --theme-primary: #00796B; 
            --theme-primary-dark: #004D40;
            --theme-secondary: #009688;
            --theme-accent: #FFC107;
            
            --background-main: #F4F6F8; 
            --background-card: #FFFFFF;
            --background-list-item: #E8F5E9; /* Used for even table rows */

            --text-primary: #263238; 
            --text-secondary: #546E7A; 
            --text-on-primary: #FFFFFF; 
            --text-placeholder: #78909C;

            --border-light: #CFD8DC; 
            --border-medium: #B0BEC5;
            --border-table-row: #e0e7ef; /* Lighter border for table rows */
            
            --shadow-color: rgba(0, 0, 0, 0.08);

            --alert-success-bg: #E0F2F1;
            --alert-success-text: #00695C;
            --alert-success-border: #A7FFEB;

            --alert-error-bg: #FFEBEE;
            --alert-error-text: #C62828;
            --alert-error-border: #FFCDD2;

            --alert-info-bg: #E3F2FD; 
            --alert-info-text: #1565C0;
            --alert-info-border: #BBDEFB;

            --border-radius-sm: 4px;
            --border-radius-md: 8px;
            --border-radius-lg: 12px;
            --transition-speed: 0.3s ease;

            /* Specific for this page's buttons */
            --button-finish-bg: #e74c3c; /* A distinct red */
            --button-finish-hover-bg: #c0392b; /* Darker red */
            --button-edit-bg: var(--theme-secondary);
            --button-edit-hover-bg: var(--theme-primary);
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0; padding: 0;
            background: var(--background-main); 
            color: var(--text-primary); 
            line-height: 1.6;
        }

        .container {
            width: 90%;
            max-width: 1100px; /* Adjusted for better table display */
            margin: 30px auto;
            padding: 0 15px;
        }

        .page-title { 
            color: var(--theme-primary);
            text-align: center;
            font-size: 2.2em; /* Slightly reduced for balance */
            margin-bottom: 35px; /* Increased bottom margin */
            font-weight: 600;
            display: flex; /* For icon alignment */
            align-items: center;
            justify-content: center;
        }
        .page-title .fas { /* Icon specific styling */
            margin-right: 12px; /* Increased space */
            color: var(--theme-secondary); 
            font-size: 0.9em; /* Relative to h2 */
        }

        /* NAVBAR STYLES (from your dashboard) */
        nav {
            background: linear-gradient(135deg, var(--theme-secondary), var(--theme-primary)); 
            padding: 12px 0; 
            display: flex; 
            justify-content: center; 
            gap: 20px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1); /* Slightly enhanced shadow */
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        nav a {
            color: var(--text-on-primary); 
            text-decoration: none; 
            font-weight: 500; /* Slightly less bold for a cleaner look */
            padding: 10px 20px; 
            border-radius: var(--border-radius-sm); 
            transition: background-color var(--transition-speed), transform 0.2s ease;
            display: inline-flex; 
            align-items: center;
        }
        nav a .fas { 
            margin-right: 8px;
            font-size: 1em; /* Consistent icon size */
        }
        nav a:hover { 
            background: var(--theme-primary-dark); 
            transform: translateY(-2px);
        }
         nav a.active { /* Explicit style for active link */
            background: var(--theme-primary-dark);
            box-shadow: inset 0 0 5px rgba(0,0,0,0.2);
        }


        .alert {
            padding: 15px 20px; /* Increased padding */
            border-radius: var(--border-radius-md); 
            margin-bottom: 25px;
            text-align: left; /* Better for readability */
            border: 1px solid transparent;
            font-size: 0.95em; 
            display: flex; 
            align-items: center;
        }
        .alert .fas {
            margin-right: 10px;
            font-size: 1.25em; /* Slightly larger icon */
        }
        .alert-success {
            background-color: var(--alert-success-bg);
            color: var(--alert-success-text);
            border-color: var(--alert-success-border);
        }
        .alert-error {
            background-color: var(--alert-error-bg);
            color: var(--alert-error-text);
            border-color: var(--alert-error-border);
        }
        
        .table-container {
            background: var(--background-card); 
            padding: 20px 25px; /* Adjusted padding */
            margin-top: 20px;
            border-radius: var(--border-radius-lg); 
            box-shadow: 0 5px 15px var(--shadow-color); /* Enhanced shadow */
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate; /* Changed for border-spacing */
            border-spacing: 0; /* Remove default spacing */
            margin-top: 0;
        }

        th, td {
            padding: 14px 16px; /* Increased padding */
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-table-row); /* Apply only bottom border to rows */
        }
        th:first-child, td:first-child { padding-left: 5px; } /* Slight padding for first cell */
        th:last-child, td:last-child { padding-right: 5px; text-align: center; } /* Center last column */

        thead th {
            background-color: var(--theme-primary); 
            color: var(--text-on-primary); 
            font-weight: 600; /* Bolder header */
            font-size: 0.9em; /* Slightly adjusted header font size */
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 2px solid var(--theme-primary-dark); /* Stronger header bottom border */
        }
        /* Remove top border for first row of headers to avoid double line with container */
        thead tr:first-child th { border-top: none; }


        tbody tr:nth-child(even) {
            background-color: var(--background-list-item); 
        }
        tbody tr:hover { 
            background-color: #dcf0ff; /* A light blue hover, adjust if needed */
        }
        td { 
            color: var(--text-primary); 
            font-size: 0.95em; /* Consistent font size for data */
        }
        td .job-title { 
            font-weight: 600; /* Bolder job title */
            color: var(--theme-primary); 
            display: block; /* Make it block for better spacing if needed */
            margin-bottom: 3px;
        }
        td .job-location, td .job-salary, td .job-posted-on { 
            font-size: 0.85em; 
            color: var(--text-secondary); 
            display: block; /* Separate lines for details */
        }
        td .job-salary {
             font-weight: 500;
        }


        .btn {
            padding: 8px 15px; /* Slightly more horizontal padding */
            border: none;
            cursor: pointer;
            border-radius: var(--border-radius-sm); /* Smaller radius for a sharper look */
            font-size: 0.875em; /* Consistent button font size */
            font-weight: 500;
            transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px; /* Space between icon and text */
            text-decoration: none;
            line-height: 1.5; /* Ensure text is centered if it wraps */
        }
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .btn-finish { 
            background: var(--button-finish-bg); 
            color: var(--text-on-primary); 
        }
        .btn-finish:hover { background: var(--button-finish-hover-bg); }

        .btn-edit { /* Keeping the Edit button consistent with dashboard */
            background: var(--button-edit-bg); 
            color: var(--text-on-primary); 
            margin-right: 8px; 
        }
        .btn-edit:hover { background: var(--button-edit-hover-bg); }

        .actions-cell {
            white-space: nowrap; /* Prevent buttons from wrapping if cell is too narrow */
        }
        .actions-cell form { 
            display: inline-block; 
            margin: 0; /* Remove default form margin */
        }
        /* If you have multiple buttons, you might add spacing: */
        /* .actions-cell > * + * { margin-left: 8px; } */


        .empty-state {
            text-align: center;
            padding: 50px 25px; /* More padding */
            background-color: var(--background-card); 
            border-radius: var(--border-radius-lg); 
            margin-top: 30px;
            color: var(--text-secondary); 
            border: 1px dashed var(--border-light); /* Dashed border for emphasis */
        }
        .empty-state .fas { 
            font-size: 3.5em; /* Larger icon */
            display: block; 
            margin-bottom: 20px; 
            color: var(--theme-secondary); 
        }
        .empty-state p { 
            font-size: 1.15em; /* Slightly larger text */
            margin-bottom: 15px; /* Space between paragraphs */
        }
        .empty-state a { 
            color: var(--theme-primary); 
            font-weight: 600; /* Bolder link */
            text-decoration: none;
            border-bottom: 1px solid var(--theme-primary);
            padding-bottom: 2px;
            transition: color 0.2s, border-color 0.2s;
        }
        .empty-state a:hover { 
            color: var(--theme-primary-dark);
            border-color: var(--theme-primary-dark);
        }
    </style>
</head>

<body>

    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage_jobs.php" class="active"><i class="fas fa-edit"></i> Manage Jobs</a>
        <a href="view_applications.php"><i class="fas fa-users"></i> View Applications</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="container">
        <h2 class="page-title"><i class="fas fa-clipboard-list"></i> Manage Your Job Postings</h2>

        <?php if (isset($success_message)) : ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error_message)) : ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <?php if ($result_jobs && $result_jobs->num_rows > 0) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Location</th>
                            <th>Salary (PHP)</th>
                            <th>Posted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($job = $result_jobs->fetch_assoc()) : ?>
                            <tr>
                                <td>
                                    <span class="job-title"><?php echo htmlspecialchars($job['title']); ?></span>
                                    <!-- You could add a snippet of description here if desired -->
                                    <!-- <span class="job-description-snippet"><?php echo htmlspecialchars(substr($job['description'], 0, 50)) . '...'; ?></span> -->
                                </td>
                                <td><span class="job-location"><?php echo htmlspecialchars($job['location']); ?></span></td>
                                <td><span class="job-salary">₱<?php echo number_format($job['salary'], 2); ?></span></td>
                                <td><span class="job-posted-on"><?php echo htmlspecialchars(date("M d, Y", strtotime($job['created_at']))); ?></span></td>
                                <td class="actions-cell">
                                    <!-- Edit button from dashboard.php style -->
                                    </a>
                                    <form method="POST" action="manage_jobs.php" onsubmit="return confirm('Are you sure you want to mark this job as finished? This will remove the job and all its applications.');">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" name="finish_job" class="btn btn-finish">
                                            <i class="fas fa-check-circle"></i> Finish
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>You haven't posted any jobs yet.</p>
                    <p><a href="dashboard.php">Go to Dashboard</a> to post your first job!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>