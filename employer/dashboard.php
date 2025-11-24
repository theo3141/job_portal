<?php
session_start();
include_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'employer' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php");
    exit();
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$employer_id = $_SESSION['user_id'];

// Fetch dashboard data
$sql_jobs_count = "SELECT COUNT(*) as count FROM jobs WHERE employer_id = ?";
$stmt_jobs_count = $conn->prepare($sql_jobs_count);
$stmt_jobs_count->bind_param("i", $employer_id);
$stmt_jobs_count->execute();
$result_jobs_count = $stmt_jobs_count->get_result();
$total_jobs = $result_jobs_count->fetch_assoc()['count'];
$stmt_jobs_count->close();


$sql_apps = "SELECT COUNT(*) AS total_apps FROM applications WHERE job_id IN (SELECT id FROM jobs WHERE employer_id = ?)";
$stmt_apps = $conn->prepare($sql_apps);
$stmt_apps->bind_param("i", $employer_id);
$stmt_apps->execute();
$result_apps = $stmt_apps->get_result();
$total_apps = $result_apps->fetch_assoc()['total_apps'] ?? 0;
$stmt_apps->close();

$sql_applicants = "SELECT applications.id, users.name AS applicant_name, jobs.title AS job_title, applications.status, applications.applied_at
                   FROM applications
                   JOIN users ON applications.jobseeker_id = users.id
                   JOIN jobs ON applications.job_id = jobs.id
                   WHERE jobs.employer_id = ?
                   ORDER BY applications.applied_at DESC
                   LIMIT 5";
$stmt_applicants = $conn->prepare($sql_applicants);
$stmt_applicants->bind_param("i", $employer_id);
$stmt_applicants->execute();
$result_applicants = $stmt_applicants->get_result();
$stmt_applicants->close();

// For "Your Job Postings" section
$sql_jobs_list = "SELECT * FROM jobs WHERE employer_id = ? ORDER BY created_at DESC LIMIT 5"; // Limit for dashboard view
$stmt_jobs_list = $conn->prepare($sql_jobs_list);
$stmt_jobs_list->bind_param("i", $employer_id);
$stmt_jobs_list->execute();
$result_jobs_list = $stmt_jobs_list->get_result();
$stmt_jobs_list->close();


// --- PHP Variables for the Modal Form Dropdowns ---
$job_type_options_for_modal = ["Full-time", "Part-time", "Contract", "Internship", "Temporary"];
$experience_level_options_for_modal = ["Entry Level", "Associate", "Mid-Senior level", "Director", "Executive"];
$education_level_options_for_modal = ["High School Diploma", "Vocational Training", "Associate Degree", "Bachelor's Degree", "Master's Degree", "Doctorate", "Not Required"];

// Check for messages from post_job.php
$success_message = null;
$error_message = null;
$notification_info_message = null;

if (isset($_SESSION['post_job_success'])) {
    $success_message = $_SESSION['post_job_success'];
    unset($_SESSION['post_job_success']);
}
if (isset($_SESSION['post_job_error'])) {
    $error_message = $_SESSION['post_job_error'];
    unset($_SESSION['post_job_error']);
}
if (isset($_SESSION['post_job_notification_info'])) {
    $notification_info_message = $_SESSION['post_job_notification_info'];
    unset($_SESSION['post_job_notification_info']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard</title>
    <link rel="stylesheet" href="../styles.css"> <!-- Assuming this has general Poppins font, etc. -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        :root {
            --theme-primary: #00796B; /* Teal */
            --theme-primary-dark: #004D40; /* Darker Teal */
            --theme-secondary: #009688; /* Lighter Teal for gradients or secondary elements */
            --theme-accent: #FFC107; /* Amber for contrasting accents (optional) */
            
            --background-main: #F4F6F8; /* Very light gray */
            --background-card: #FFFFFF;
            --background-list-item: #E8F5E9; /* Light mint green */

            --text-primary: #263238; /* Dark Slate Gray */
            --text-secondary: #546E7A; /* Lighter Slate Gray */
            --text-on-primary: #FFFFFF; /* Text on dark backgrounds */
            --text-placeholder: #78909C;

            --border-light: #CFD8DC; /* Light Blue Gray */
            --border-medium: #B0BEC5;
            
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
            font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background: var(--background-main);
            color: var(--text-primary);
        }
        .container { width: 90%; max-width: 1200px; margin: 20px auto; }
        h2, h3 { color: var(--theme-primary); text-align: center; }
        
        nav {
            background: linear-gradient(135deg, var(--theme-secondary), var(--theme-primary)); 
            padding: 12px 0;
            display: flex; justify-content: center; gap: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        nav a {
            color: var(--text-on-primary); text-decoration: none; font-weight: bold;
            padding: 10px 20px; border-radius: 5px; transition: background 0.3s;
        }
        nav a:hover, nav a.modal-trigger:hover { background: var(--theme-primary-dark); cursor: pointer;}
        
        .dashboard { display: flex; justify-content: space-around; margin-top: 30px; gap: 20px; }
        .card {
            background: var(--background-card); padding: 25px; border-radius: 12px; text-align: center;
            box-shadow: 0px 4px 12px var(--shadow-color); width: 45%;
            border-left: 4px solid var(--theme-primary);
        }
        .card h3 { font-size: 20px; margin-bottom: 10px; color: var(--text-primary); }
        .card p { font-size: 28px; font-weight: bold; color: var(--theme-primary); }
        
        section {
            background: var(--background-card); padding: 25px; margin: 20px 0; border-radius: 12px;
            box-shadow: 0px 4px 12px var(--shadow-color);
        }
        section h3 { margin-top: 0; }
        ul { list-style: none; padding: 0; }
        li {
            background: var(--background-list-item); 
            color: var(--text-primary);
            padding: 15px; margin: 10px 0; border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.05);
            border-left: 3px solid var(--theme-secondary);
        }
        .empty-state { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px 0; }
        
        .btn {
            padding: 10px 15px; border: none; cursor: pointer; border-radius: 8px;
            font-size: 14px; font-weight: bold; transition: 0.3s;
        }
        .btn-view { background: var(--theme-secondary); color: var(--text-on-primary); }
        .btn-view:hover { background: var(--theme-primary); }

        /* --- MODAL STYLING --- */
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6); /* Slightly darker overlay */
            padding-top: 30px;
        }
        .modal-content {
            background-color: var(--background-card); margin: 3% auto; padding: 25px 30px;
            border: 1px solid var(--border-medium); width: 90%; max-width: 700px;
            border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            position: relative; overflow-y: auto; max-height: 90vh;
        }
        .modal-header {
            padding-bottom: 15px; border-bottom: 1px solid var(--border-light); margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { margin: 0; color: var(--theme-primary); font-size: 1.7em; text-align: left; }
        .close-button {
            color: var(--text-secondary); font-size: 30px; font-weight: bold; cursor: pointer;
            padding: 0 5px; line-height: 1;
        }
        .close-button:hover, .close-button:focus { color: var(--text-primary); text-decoration: none; }

        .modal-content .form-group { margin-bottom: 18px; }
        .modal-content label {
            font-weight: 600; margin-bottom: 6px; color: var(--text-primary);
            display: block; font-size: 0.95em;
        }
        .modal-content input[type="text"],
        .modal-content input[type="number"],
        .modal-content textarea,
        .modal-content select {
            padding: 12px 15px; border: 1px solid var(--border-light); border-radius: 8px;
            width: 100%; font-size: 1em; box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #fff; /* Ensure inputs have a white background */
            color: var(--text-primary);
        }
        .modal-content input::placeholder,
        .modal-content textarea::placeholder {
            color: var(--text-placeholder);
        }
        .modal-content textarea { min-height: 100px; resize: vertical; }
        .modal-content input:focus,
        .modal-content textarea:focus,
        .modal-content select:focus {
            outline: none; border-color: var(--theme-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25); /* Focus ring using primary color */
        }
        .modal-content select { cursor: pointer; background-color: #f8f9fa; } /* Keep select slightly different or make it white */
        
        .modal-content .btn-submit-modal {
            background-color: var(--theme-primary); color: var(--text-on-primary); padding: 12px 20px;
            border: none; border-radius: 8px; font-size: 1.1em;
            cursor: pointer; transition: background-color 0.3s, transform 0.2s;
            margin-top: 15px; font-weight: 500;
        }
        .modal-content .btn-submit-modal:hover { background-color: var(--theme-primary-dark); transform: translateY(-2px); }
        .modal-content .small-text {font-size: 0.85em; color: var(--text-secondary); margin-top: 5px; margin-bottom: 10px;}
        /* --- END MODAL STYLING --- */

        /* Alert messages for dashboard page */
        .dashboard-alerts .alert {
            padding: 12px 15px; border-radius: 8px; margin: 15px 0;
            text-align: center; font-size: 0.95em; border: 1px solid transparent;
        }
        .dashboard-alerts .alert-success { background-color: var(--alert-success-bg); color: var(--alert-success-text); border-color: var(--alert-success-border);}
        .dashboard-alerts .alert-error { background-color: var(--alert-error-bg); color: var(--alert-error-text); border-color: var(--alert-error-border);}
        .dashboard-alerts .alert-info { background-color: var(--alert-info-bg); color: var(--alert-info-text); border-color: var(--alert-info-border);}
    </style>
</head>
<body>

    <nav>
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a id="openPostJobModalBtn" class="modal-trigger"><i class="fas fa-plus"></i> Post Job</a>
        <a href="manage_jobs.php"><i class="fas fa-edit"></i> Manage Jobs</a>
        <a href="view_applications.php"><i class="fas fa-users"></i> View Applications</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="container">
        <h2><i class="fas fa-tachometer-alt"></i> Employer Dashboard</h2> <!-- Changed icon for variety -->

        <div class="dashboard-alerts">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($notification_info_message): ?>
                <div class="alert alert-info"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($notification_info_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
        </div>

        <div class="dashboard">
            <div class="card">
                <h3><i class="fas fa-briefcase"></i> Total Jobs Posted</h3>
                <p><?php echo htmlspecialchars($total_jobs); ?></p>
            </div>
            <div class="card">
                <h3><i class="fas fa-file-alt"></i> Total Applications</h3>
                <p><?php echo htmlspecialchars($total_apps); ?></p>
            </div>
        </div>
        <section>
            <h3><i class="fas fa-user-clock"></i> Recent Job Applications</h3> <!-- Changed icon -->
            <ul>
                <?php if ($result_applicants->num_rows > 0): ?>
                    <?php while ($applicant = $result_applicants->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($applicant['applicant_name']); ?></strong>
                            applied for <strong><?php echo htmlspecialchars($applicant['job_title']); ?></strong> <br>
                            <small>Status: <?php echo htmlspecialchars($applicant['status']); ?> |
                            Applied: <?php echo htmlspecialchars(date("M d, Y h:i A", strtotime($applicant['applied_at']))); ?></small>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">No recent applications found.</p>
                <?php endif; ?>
            </ul>
        </section>
        <section>
            <h3><i class="fas fa-list-alt"></i> Your Job Postings (Recent 5)</h3> <!-- Changed icon -->
            <ul>
                <?php if ($result_jobs_list->num_rows > 0): ?>
                    <?php while ($job = $result_jobs_list->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($job['title']); ?></strong> <br>
                            <small><?php echo htmlspecialchars($job['location']); ?> |
                            Salary: ₱<?php echo (intval($job['salary']) == $job['salary']) ? number_format($job['salary'], 0) : number_format($job['salary'], 2); ?></small>
                            <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-view" style="float:right; margin-left: 5px; margin-top: -5px;"><i class="fas fa-edit"></i> Edit</a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">No job postings yet. <a href="#" id="postFirstJobLink" class="modal-trigger" style="color: var(--theme-primary); font-weight: 500;">Post your first job now!</a></p>
                <?php endif; ?>
            </ul>
        </section>
    </div>

    <!-- The Modal for Posting a Job -->
    <div id="postJobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Post a New Job</h2> <!-- Changed icon -->
                <span class="close-button">×</span>
            </div>

            <form id="postJobModalForm" method="POST" action="post_job.php">
                <input type="hidden" name="source" value="modal">

                <div class="form-group">
                    <label for="modal_title">Job Title <span style="color:var(--theme-primary);">*</span></label>
                    <input type="text" id="modal_title" name="title" placeholder="e.g., Senior Marketing Manager" required>
                </div>
                <div class="form-group">
                    <label for="modal_description">Job Description <span style="color:var(--theme-primary);">*</span></label>
                    <textarea id="modal_description" name="description" placeholder="Describe the role, responsibilities, and requirements..." required></textarea>
                </div>
                <div class="form-group">
                    <label for="modal_location">Location <span style="color:var(--theme-primary);">*</span></label>
                    <input type="text" id="modal_location" name="location" placeholder="e.g., Makati, Metro Manila or 'Remote (Philippines)'" required>
                </div>
                <div class="form-group">
                    <label for="modal_salary">Salary (PHP) <span style="color:var(--theme-primary);">*</span></label>
                    <input type="number" id="modal_salary" name="salary" placeholder="e.g., 75000" required step="0.01" min="1">
                </div>
                <hr style="margin: 20px 0; border-color: var(--border-light);">
                <div class="form-group">
                    <label for="modal_job_type">Job Type</label>
                    <select id="modal_job_type" name="job_type">
                        <option value="">-- Select Job Type --</option>
                        <?php foreach ($job_type_options_for_modal as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal_experience_level">Experience Level</label>
                    <select id="modal_experience_level" name="experience_level">
                        <option value="">-- Select Experience Level --</option>
                        <?php foreach ($experience_level_options_for_modal as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal_education_level">Education Level</label>
                    <select id="modal_education_level" name="education_level">
                        <option value="">-- Select Education Level --</option>
                        <?php foreach ($education_level_options_for_modal as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal_skills_required">Skills Required</label>
                    <textarea id="modal_skills_required" name="skills_required" placeholder="e.g., SEO, Content Creation, Project Management"></textarea>
                    <p class="small-text">Enter skills separated by commas.</p>
                </div>
                <button type="submit" class="btn-submit-modal"><i class="fas fa-paper-plane"></i> Post Job</button>
            </form>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        var modal = $("#postJobModal");
        var btnOpen = $("#openPostJobModalBtn");
        var btnOpenEmpty = $("#postFirstJobLink"); // Corrected selector if needed
        var spanClose = $(".close-button"); // Ensure this targets the modal's close button
        var form = $("#postJobModalForm");

        function openModal() {
            // form[0].reset(); // Optional: reset form fields when opening
            modal.css("display", "block");
            $('body').css('overflow', 'hidden'); // Prevent background scroll
        }
        
        function closeModal() {
            modal.css("display", "none");
            $('body').css('overflow', 'auto'); // Restore background scroll
        }

        btnOpen.click(function(e) {
            e.preventDefault(); 
            openModal();
        });
        
        // Ensure this click handler is correctly attached if #postFirstJobLink is dynamically added or needs delegation
        $(document).on('click', "#postFirstJobLink", function(e){ // Using event delegation for potentially dynamic elements
            e.preventDefault();
            openModal();
        });

        spanClose.click(function() {
            closeModal();
        });

        $(window).click(function(event) {
            if (event.target == modal[0]) { // If click is on the modal backdrop
                closeModal();
            }
        });
        
        // Close modal on ESC key
        $(document).keydown(function(event) {
            if (event.key === "Escape" && modal.css("display") === "block") {
                closeModal();
            }
        });

        $('#modal_salary').on('input', function(event) {
            this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
        });
    });
    </script>
</body>
</html>