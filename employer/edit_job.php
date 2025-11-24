<?php
session_start();
include_once __DIR__ . '/../includes/config.php';

// Role and Authentication Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'employer' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php");
    exit();
}

if (!$conn) {
    error_log("Database connection failed in edit_job.php: " . mysqli_connect_error());
    die("Database connection error.");
}

$employer_id = $_SESSION['user_id'];
$page_error_message = null; // For displaying errors on this page
$page_success_message = null; // For displaying success on this page (though usually we redirect)

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Using session for error messages to persist across redirects if needed
    $_SESSION['form_submission_error'] = "Invalid Job ID specified.";
    header("Location: dashboard.php"); // Redirect to dashboard if ID is invalid
    exit();
}
$job_id = intval($_GET['id']);

// --- CSRF Token Generation for this specific form instance ---
// Generate a new token each time the form page is loaded (GET request)
// or if a POST request failed and we are re-displaying the form.
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !empty($page_error_message)) { // $page_error_message will be set if POST fails
    $_SESSION['csrf_token_edit_job_page'] = bin2hex(random_bytes(32));
}
$csrf_token_for_form = $_SESSION['csrf_token_edit_job_page'] ?? ''; // Use existing if POST failed and we re-render

// Form Submission Handling
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_edit_job_page']) || !hash_equals($_SESSION['csrf_token_edit_job_page'], $_POST['csrf_token'])) {
        $page_error_message = "CSRF token validation failed. Please refresh and try again.";
        // Invalidate the wrong/old token and prepare a new one for the re-displayed form
        $_SESSION['csrf_token_edit_job_page'] = bin2hex(random_bytes(32));
        $csrf_token_for_form = $_SESSION['csrf_token_edit_job_page'];
    } else {
        // CSRF token is valid, consume it
        unset($_SESSION['csrf_token_edit_job_page']);

        // Sanitize and retrieve ALL form fields
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $location = trim($_POST['location']);
        $salary_raw = $_POST['salary'] ?? '0';
        $status = trim($_POST['status']);
        $job_type = isset($_POST['job_type']) ? trim($_POST['job_type']) : null;
        $experience_level = isset($_POST['experience_level']) ? trim($_POST['experience_level']) : null;
        $education_level = isset($_POST['education_level']) ? trim($_POST['education_level']) : null;
        $skills_required = isset($_POST['skills_required']) ? trim($_POST['skills_required']) : null;

        $form_errors = [];
        if (empty($title)) { $form_errors[] = "Job Title is required."; }
        if (empty($description)) { $form_errors[] = "Job Description is required."; }
        // ... (other validations as in your previous full code for edit_job.php) ...
        $salary = filter_var($salary_raw, FILTER_VALIDATE_FLOAT);
        if ($salary === false || $salary <= 0) { $form_errors[] = "Salary must be a valid positive number."; }
        $allowed_status = ['active', 'inactive'];
        if (empty($status) || !in_array($status, $allowed_status)) { $form_errors[] = "Invalid status selected.";}
        
        $job_type_options_for_modal = ["Full-time", "Part-time", "Contract", "Internship", "Temporary"];
        if (!empty($job_type) && !in_array($job_type, $job_type_options_for_modal)) { $form_errors[] = "Invalid Job Type."; }
        // ... (similar validation for experience and education if you have predefined lists) ...


        if (!empty($form_errors)) {
            $page_error_message = implode("<br>", $form_errors);
            // Regenerate token for the form display on error because the previous one was consumed
            $_SESSION['csrf_token_edit_job_page'] = bin2hex(random_bytes(32));
            $csrf_token_for_form = $_SESSION['csrf_token_edit_job_page'];
        } else {
            // Fetch job again to ensure it still belongs to the employer before updating
            // This is a good check if the form was open for a long time
            $sql_check_owner = "SELECT id FROM jobs WHERE id = ? AND employer_id = ?";
            $stmt_check = $conn->prepare($sql_check_owner);
            $stmt_check->bind_param("ii", $job_id, $employer_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if($result_check->num_rows === 0){
                $page_error_message = "Job not found or permission denied.";
                // Regenerate token as well
                $_SESSION['csrf_token_edit_job_page'] = bin2hex(random_bytes(32));
                $csrf_token_for_form = $_SESSION['csrf_token_edit_job_page'];
            } else {
                // Proceed with update
                $sql_update = "UPDATE jobs SET title=?, description=?, location=?, salary=?, status=?, 
                               job_type=?, experience_level=?, education_level=?, skills_required=? 
                               WHERE id=? AND employer_id=?";
                $stmt_update = $conn->prepare($sql_update);
                if (!$stmt_update) {
                    $page_error_message = "Database error (prepare update).";
                    error_log("Prepare failed (update job): " . $conn->error);
                } else {
                    $stmt_update->bind_param("sssdsssssii", 
                        $title, $description, $location, $salary, $status,
                        $job_type, $experience_level, $education_level, $skills_required,
                        $job_id, $employer_id);

                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $_SESSION['post_job_success'] = "Job (ID: $job_id) updated successfully!";
                        } else {
                            $_SESSION['post_job_notification_info'] = "No changes were made to Job (ID: $job_id).";
                        }
                        header("Location: dashboard.php"); // Redirect to dashboard
                        exit();
                    } else {
                        $page_error_message = "Failed to update the job. Please try again.";
                        error_log("Error updating job ID {$job_id}: " . $stmt_update->error);
                    }
                    $stmt_update->close();
                }
            }
            $stmt_check->close();
        }
    }
}

// Fetch existing job data for pre-filling the form (if not a POST or if POST had errors)
// This ensures $job is always available for the form rendering
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !empty($page_error_message)) {
    $sql_job_fetch = "SELECT * FROM jobs WHERE id = ? AND employer_id = ?";
    $stmt_fetch_display = $conn->prepare($sql_job_fetch);
    if ($stmt_fetch_display) {
        $stmt_fetch_display->bind_param("ii", $job_id, $employer_id);
        $stmt_fetch_display->execute();
        $result_fetch_display = $stmt_fetch_display->get_result();
        if ($result_fetch_display->num_rows === 0) {
            // This case should ideally have been caught by the GET request check,
            // but as a fallback if URL was manipulated between load and POST.
            $_SESSION['post_job_error'] = "Job not found or permission denied after form load.";
            header("Location: dashboard.php");
            exit();
        }
        $job = $result_fetch_display->fetch_assoc();
        $stmt_fetch_display->close();
    } else {
        // Handle error preparing statement to fetch job for display
        $page_error_message = "Could not retrieve job details for editing.";
        error_log("Prepare failed (re-fetch job for display): " . $conn->error);
        // To prevent displaying an empty form:
        // You might redirect or show a more critical error.
        // For now, we'll let it try to render the form, but $job might be incomplete.
    }
}


// For populating dropdowns in the form
$job_type_options_for_modal = ["Full-time", "Part-time", "Contract", "Internship", "Temporary"];
$experience_level_options_for_modal = ["Entry Level", "Associate", "Mid-Senior level", "Director", "Executive"];
$education_level_options_for_modal = ["High School Diploma", "Vocational Training", "Associate Degree", "Bachelor's Degree", "Master's Degree", "Doctorate", "Not Required"];
$status_options_for_form = ["active", "inactive"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Posting - <?php echo isset($job['title']) ? htmlspecialchars($job['title']) : 'Job'; ?></title>
    <link rel="stylesheet" href="../styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        :root {
            --theme-primary: #00796B; --theme-primary-dark: #004D40; --theme-secondary: #009688;
            --theme-accent: #FFC107; --background-main: #F4F6F8; --background-card: #FFFFFF;
            --background-list-item: #E8F5E9; --text-primary: #263238; --text-secondary: #546E7A;
            --text-on-primary: #FFFFFF; --text-placeholder: #78909C; --border-light: #CFD8DC;
            --border-medium: #B0BEC5; --shadow-color: rgba(0, 0, 0, 0.08);
            --alert-success-bg: #E0F2F1; --alert-success-text: #00695C; --alert-success-border: #A7FFEB;
            --alert-error-bg: #FFEBEE; --alert-error-text: #C62828; --alert-error-border: #FFCDD2;
        }
        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background: var(--background-main); color: var(--text-primary); }
        nav.page-nav { background: linear-gradient(135deg, var(--theme-secondary), var(--theme-primary)); padding: 12px 0; display: flex; justify-content: center; gap: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px; position: sticky; top: 0; z-index: 1000; }
        nav.page-nav a { color: var(--text-on-primary); text-decoration: none; font-weight: bold; padding: 10px 20px; border-radius: 5px; transition: background 0.3s; display: inline-flex; align-items: center; }
        nav.page-nav a i { margin-right: 8px; }
        nav.page-nav a:hover { background: var(--theme-primary-dark); }
        nav.page-nav a.active-nav-link { background: var(--theme-primary-dark); }

        .form-container { width: 90%; max-width: 800px; margin: 0 auto 40px auto; background: var(--background-card); padding: 30px 35px; border-radius: 12px; box-shadow: 0px 5px 15px var(--shadow-color); }
        .form-container h2 { color: var(--theme-primary); text-align: center; margin-top: 0; margin-bottom: 25px; font-size: 1.8em; font-weight: 600; padding-bottom: 15px; border-bottom: 1px solid var(--border-light); }
        .form-container h2 i { margin-right: 10px; }

        .page-message { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.95em; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; gap: 8px;}
        .page-message.success { background-color: var(--alert-success-bg); color: var(--alert-success-text); border-color: var(--alert-success-border);}
        .page-message.error { background-color: var(--alert-error-bg); color: var(--alert-error-text); border-color: var(--alert-error-border);}

        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 600; margin-bottom: 6px; color: var(--text-primary); display: block; font-size: 0.95em; }
        .form-group label .required-star { color: var(--theme-primary); }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid var(--border-light); border-radius: 8px; font-size: 1em; box-sizing: border-box; background-color: #fff; color: var(--text-primary); transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--text-placeholder); }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--theme-primary); box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25); }
        .form-group select { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.9rem center; background-size: 0.9em; padding-right: 2.8rem; }
        .form-group .small-text { font-size: 0.8em; color: var(--text-secondary); margin-top: 5px; }
        
        .form-actions { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 12px 22px; border: none; cursor: pointer; border-radius: 8px; font-size: 1em; font-weight: 500; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; }
        .btn i { margin-right: 8px; }
        .btn-submit-form { background-color: var(--theme-primary); color: var(--text-on-primary); }
        .btn-submit-form:hover { background-color: var(--theme-primary-dark); transform: translateY(-2px); }
        .btn-back-link { background-color: var(--text-secondary); color: var(--text-on-primary); }
        .btn-back-link:hover { background-color: var(--text-primary); }
    </style>
</head>
<body>

    <nav class="page-nav">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    </nav>

    <div class="form-container">
        <h2><i class="fas fa-edit"></i> Edit Job Posting</h2>

        <?php if (isset($page_error_message) && !empty($page_error_message)): ?>
            <div class='page-message error'><i class="fas fa-exclamation-circle"></i> <?php echo $page_error_message; ?></div>
        <?php endif; ?>
        <?php if (isset($page_success_message) && !empty($page_success_message)): // Should not happen if redirecting ?>
            <div class='page-message success'><i class="fas fa-check-circle"></i> <?php echo $page_success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($job)): // Ensure $job is set before trying to access its members ?>
        <form method="POST" action="edit_job.php?id=<?php echo $job_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token_for_form); ?>">

            <div class="form-group">
                <label for="title">Job Title <span class="required-star">*</span></label>
                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description <span class="required-star">*</span></label>
                <textarea name="description" id="description" rows="6" required><?php echo htmlspecialchars($job['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="location">Location <span class="required-star">*</span></label>
                <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
            </div>
            <div class="form-group">
                <label for="salary">Salary (PHP) <span class="required-star">*</span></label>
                <input type="number" name="salary" id="salary" value="<?php echo htmlspecialchars($job['salary']); ?>" step="0.01" min="1" required>
            </div>
            <div class="form-group">
                <label for="status">Status <span class="required-star">*</span></label>
                <select name="status" id="status" required>
                    <?php foreach ($status_options_for_form as $option_val): ?>
                    <option value="<?php echo htmlspecialchars($option_val); ?>" <?php echo ($job['status'] === $option_val) ? 'selected' : ''; ?>>
                        <?php echo ucfirst(htmlspecialchars($option_val)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <hr style="margin: 25px 0; border:0; border-top: 1px solid var(--border-light);">
            <h3 style="font-size: 1.2em; color: var(--text-primary); margin-bottom:15px; text-align:left;">Additional Job Details</h3>

            <div class="form-group">
                <label for="job_type">Job Type</label>
                <select id="job_type" name="job_type">
                    <option value="">-- Select Job Type --</option>
                    <?php foreach ($job_type_options_for_modal as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($job['job_type']) && $job['job_type'] === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="experience_level">Experience Level</label>
                <select id="experience_level" name="experience_level">
                    <option value="">-- Select Experience Level --</option>
                    <?php foreach ($experience_level_options_for_modal as $option): ?>
                         <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($job['experience_level']) && $job['experience_level'] === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="education_level">Education Level</label>
                <select id="education_level" name="education_level">
                    <option value="">-- Select Education Level --</option>
                    <?php foreach ($education_level_options_for_modal as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (isset($job['education_level']) && $job['education_level'] === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="skills_required">Skills Required</label>
                <textarea id="skills_required" name="skills_required" placeholder="e.g., SEO, Content Creation"><?php echo htmlspecialchars($job['skills_required'] ?? ''); ?></textarea>
                <p class="small-text">Enter skills separated by commas.</p>
            </div>

            <div class="form-actions">
                <a href="dashboard.php" class="btn btn-back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <button type="submit" class="btn btn-submit-form"><i class="fas fa-save"></i> Update Job</button>
            </div>
        </form>
        <?php else: ?>
            <p class="page-message error">Could not load job details for editing.</p>
            <div class="form-actions" style="justify-content: center;">
                 <a href="dashboard.php" class="btn btn-back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Salary input validation (numeric only)
        const salaryInput = document.getElementById('salary');
        if (salaryInput) {
            salaryInput.addEventListener('input', function(event) {
                this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            });
        }

        // Simple way to make "Post Job" in nav open the dashboard modal
        // This assumes dashboard.php has JavaScript to handle #openPostJobModalBtn
        // and that your modals are styled to overlay correctly.
        const postJobLinkInEditPage = document.getElementById('openPostJobModalBtnInEditPage');
        if(postJobLinkInEditPage) {
            postJobLinkInEditPage.addEventListener('click', function(e){
                e.preventDefault();
                // If dashboard is already open in another tab, this won't open its modal.
                // This is a simple link for navigation. For true modal opening from another page,
                // you'd need more complex JS or pass parameters.
                window.location.href = 'dashboard.php#openPostJobModalBtn';
            });
        }
    </script>

</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
// Clear the CSRF token after the page is fully rendered, 
// especially if there were errors and the form is redisplayed.
// A new token will be generated on the next GET request if it's missing.
if ($_SERVER["REQUEST_METHOD"] === "GET" || !empty($page_error_message)) {
     unset($_SESSION['csrf_token_edit_page']);
}
?>