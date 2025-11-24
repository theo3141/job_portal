<?php
session_start();
include '../includes/config.php'; // Adjust path as necessary

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit;
}

$employer_id = $_SESSION['user_id'];

// Define options for dropdowns (for direct access to this page, if you keep its HTML form)
$job_type_options = ["Full-time", "Part-time", "Contract", "Internship", "Temporary"];
$experience_level_options = ["Entry Level", "Associate", "Mid-Senior level", "Director", "Executive"];
$education_level_options = ["High School Diploma", "Vocational Training", "Associate Degree", "Bachelor's Degree", "Master's Degree", "Doctorate", "Not Required"];

$is_modal_submission = isset($_POST['source']) && $_POST['source'] === 'modal';
$return_url = "dashboard.php"; // Default return URL

if ($is_modal_submission && !empty($_POST['return_url'])) {
    $allowed_pages = ['dashboard.php', 'manage_jobs.php', 'view_applications.php'];
    $posted_return_url_base = basename($_POST['return_url']); // Get filename.php
    // Simple check to prevent redirecting to completely different domains or paths
    // More robust validation might be needed depending on security requirements.
    $parsed_url_path = parse_url($_POST['return_url'], PHP_URL_PATH);
    $base_name = basename($parsed_url_path);

    if (in_array($base_name, $allowed_pages)) {
        // Reconstruct with query string if present and from a safe origin
        $query_string = parse_url($_POST['return_url'], PHP_URL_QUERY);
        $return_url = $base_name . ($query_string ? '?' . $query_string : '');
    }
}


// Initialize messages for direct page access
$page_success_message = null;
$page_error_message = null;
$page_notification_info_message = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$conn) {
        $_SESSION['post_job_error'] = "Database connection failed.";
        if ($is_modal_submission) {
            header("Location: " . $return_url . "?modal_error=db_conn");
            exit;
        } else {
            $page_error_message = "Database connection failed.";
            // Fall through to display the page with the error
        }
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $salary_input = $_POST['salary'] ?? '';
        $job_type = trim($_POST['job_type'] ?? '');
        $experience_level = trim($_POST['experience_level'] ?? '');
        $education_level = trim($_POST['education_level'] ?? '');
        $skills_required = trim($_POST['skills_required'] ?? '');

        $current_error_message = null; // For this request

        if (empty($title)) { $current_error_message = "Job Title is required."; }
        elseif (empty($description)) { $current_error_message = "Job Description is required."; }
        elseif (empty($location)) { $current_error_message = "Location is required."; }
        elseif (empty($salary_input)) { $current_error_message = "Salary is required."; }
        elseif (!is_numeric($salary_input) || floatval($salary_input) <= 0) { $current_error_message = "Invalid salary. Please enter a positive number."; }
        elseif (!empty($job_type) && !in_array($job_type, $job_type_options)) { $current_error_message = "Invalid Job Type selected."; }
        elseif (!empty($experience_level) && !in_array($experience_level, $experience_level_options)) { $current_error_message = "Invalid Experience Level selected."; }
        elseif (!empty($education_level) && !in_array($education_level, $education_level_options)) { $current_error_message = "Invalid Education Level selected."; }

        if (!$current_error_message) {
            $salary_php = floatval($salary_input);
            $conn->begin_transaction();
            try {
                $sql_insert_job = "INSERT INTO jobs (employer_id, title, description, location, salary, job_type, experience_level, education_level, skills_required, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
                $stmt_insert_job = $conn->prepare($sql_insert_job);
                if (!$stmt_insert_job) { throw new Exception("Prepare statement failed: " . $conn->error); }
                $stmt_insert_job->bind_param("isssdssss",
                    $employer_id, $title, $description, $location, $salary_php,
                    $job_type, $experience_level, $education_level, $skills_required
                );

                if ($stmt_insert_job->execute()) {
                    $new_job_id = $stmt_insert_job->insert_id;
                    $current_success_message = "Job posted successfully!";
                    $stmt_insert_job->close();

                    $current_notification_info = "";
                    $sql_get_jobseekers = "SELECT id FROM users WHERE role = 'jobseeker'";
                    $result_jobseekers = $conn->query($sql_get_jobseekers);
                    if ($result_jobseekers && $result_jobseekers->num_rows > 0) {
                        $notification_message_text = "A new job has been posted: \"" . htmlspecialchars($title) . "\". Check it out!";
                        $sql_insert_notification = "INSERT INTO notifications (user_id, message, date, is_read) VALUES (?, ?, NOW(), 0)";
                        $stmt_notification = $conn->prepare($sql_insert_notification);
                        if ($stmt_notification) {
                            $notifications_sent_count = 0;
                            // $job_link_for_notification = "../jobs/details.php?id=" . $new_job_id; // Adjust path to be relative to site root for notification link

                            while ($jobseeker = $result_jobseekers->fetch_assoc()) {
                                $stmt_notification->bind_param("is", $jobseeker['id'], $notification_message_text);
                                if ($stmt_notification->execute()) { $notifications_sent_count++; }
                                else { error_log("Failed to send notification to user {$jobseeker['id']} for job {$new_job_id}: " . $stmt_notification->error); }
                            }
                            $stmt_notification->close();
                            if ($notifications_sent_count > 0) { $current_notification_info .= "{$notifications_sent_count} jobseeker(s) notified. "; }
                        } else { $current_notification_info .= "Could not prepare to notify jobseekers. "; error_log("Failed to prepare notification statement for job {$new_job_id}: " . $conn->error); }
                    } else { $current_notification_info .= "No active jobseekers found to notify."; }
                    $conn->commit();

                    if ($is_modal_submission) {
                        $_SESSION['post_job_success'] = $current_success_message;
                        if (!empty(trim($current_notification_info))) { $_SESSION['post_job_notification_info'] = trim($current_notification_info); }
                        header("Location: " . $return_url);
                        exit;
                    } else {
                        $page_success_message = $current_success_message;
                        $page_notification_info_message = trim($current_notification_info);
                        $_POST = array(); // Clear form for direct page display
                    }
                } else { throw new Exception("Error executing job post: " . $stmt_insert_job->error); }
            } catch (Exception $e) {
                $conn->rollback();
                $current_error_message = "An error occurred: " . $e->getMessage();
                error_log("Error in post_job.php: " . $e->getMessage());
            }
        }

        // If there was an error during POST processing
        if ($current_error_message) {
            if ($is_modal_submission) {
                $_SESSION['post_job_error'] = $current_error_message;
                $_SESSION['form_data_post_job'] = $_POST; // Save form data for repopulation
                header("Location: " . $return_url . "?modal_error=validation"); // Add a query param to signal modal should open
                exit;
            } else {
                $page_error_message = $current_error_message;
            }
        }
    } // End else for $conn check
} // END of POST processing

// --- HTML for direct access to post_job.php (optional) ---
// If you want this page to still be accessible directly and show a form:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a New Job</title>
    <link rel="stylesheet" href="../styles.css"> <!-- Your main stylesheet -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Styles for direct access to post_job.php if you keep its form */
        body {
            font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background: #F1F6F9;
            display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 30px 20px;
        }
        .form-container-direct { /* Unique class for direct access form */
            background-color: #fff; padding: 30px 35px; border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); width: 100%; max-width: 650px;
        }
        .form-container-direct h2 { text-align: center; margin-bottom: 25px; color: #1D3557; font-size: 1.8em; }
        .form-container-direct .alert {
            padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.95em;
        }
        .form-container-direct .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .form-container-direct .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .form-container-direct .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;}
        .form-container-direct form { display: flex; flex-direction: column; }
        .form-container-direct .form-group { margin-bottom: 18px; }
        .form-container-direct label { font-weight: 600; margin-bottom: 6px; color: #333; display: block; font-size: 0.95em; }
        .form-container-direct input[type="text"],
        .form-container-direct input[type="number"],
        .form-container-direct textarea,
        .form-container-direct select {
            padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; width: 100%; font-size: 1em;
            box-sizing: border-box; font-family: 'Poppins', sans-serif;
        }
        .form-container-direct textarea { min-height: 100px; resize: vertical; }
        .form-container-direct input:focus, .form-container-direct textarea:focus, .form-container-direct select:focus {
            outline: none; border-color: #457B9D; box-shadow: 0 0 0 0.2rem rgba(69, 123, 157, 0.25);
        }
        .form-container-direct .btn-submit {
            background-color: #457B9D; color: #fff; padding: 12px 20px; border: none;
            border-radius: 8px; font-size: 1.1em; cursor: pointer; margin-top: 15px; font-weight: 500;
        }
        .form-container-direct .back-link { display: block; margin-top: 25px; text-align: center; }
    </style>
</head>
<body>
    <div class="form-container-direct">
        <h2><i class="fas fa-briefcase"></i> Post a New Job (Direct Access)</h2>
        <?php if ($page_success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($page_success_message); ?></div><?php endif; ?>
        <?php if ($page_notification_info_message): ?><div class="alert alert-info"><?php echo htmlspecialchars($page_notification_info_message); ?></div><?php endif; ?>
        <?php if ($page_error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($page_error_message); ?></div><?php endif; ?>

        <form method="POST" action="post_job.php">
            <!-- Form fields identical to the modal, but with $_POST for repopulation on error -->
            <div class="form-group">
                <label for="title_direct">Job Title <span style="color:red;">*</span></label>
                <input type="text" id="title_direct" name="title" placeholder="e.g., Software Engineer" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="description_direct">Job Description <span style="color:red;">*</span></label>
                <textarea id="description_direct" name="description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="location_direct">Location <span style="color:red;">*</span></label>
                <input type="text" id="location_direct" name="location" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="salary_direct">Salary (PHP) <span style="color:red;">*</span></label>
                <input type="number" id="salary_direct" name="salary" required step="0.01" min="1" value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>">
            </div>
            <hr>
            <div class="form-group">
                <label for="job_type_direct">Job Type</label>
                <select id="job_type_direct" name="job_type">
                    <option value="">-- Select --</option>
                    <?php foreach ($job_type_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] == $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Add other select dropdowns similarly if needed for direct access -->
             <div class="form-group">
                <label for="experience_level_direct">Experience Level</label>
                <select id="experience_level_direct" name="experience_level">
                    <option value="">-- Select Experience Level --</option>
                     <?php foreach ($experience_level_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] == $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="education_level_direct">Education Level</label>
                <select id="education_level_direct" name="education_level">
                    <option value="">-- Select Education Level --</option>
                    <?php foreach ($education_level_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="skills_required_direct">Skills Required</label>
                <textarea id="skills_required_direct" name="skills_required"><?php echo htmlspecialchars($_POST['skills_required'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Post Job</button>
        </form>
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</body>
</html>