<?php
session_start();
include_once __DIR__ . '/../includes/config.php';

// Initialize session messages
$_SESSION['post_job_success'] = null;
$_SESSION['post_job_error'] = null;
$_SESSION['post_job_notification_info'] = null;

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'employer' && $_SESSION['role'] !== 'admin')) {
    $_SESSION['post_job_error'] = "Unauthorized access. Please log in.";
    header("Location: ../login.php");
    exit();
}

if (!$conn) {
    error_log("Database connection failed in process_edit_job.php: " . (isset($conn) ? mysqli_connect_error() : 'Connection object not found'));
    $_SESSION['post_job_error'] = "Database connection error. Please try again later.";
    header("Location: dashboard.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token_edit_job']) || !isset($_SESSION['csrf_token_edit_job']) || !hash_equals($_SESSION['csrf_token_edit_job'], $_POST['csrf_token_edit_job'])) {
        $_SESSION['post_job_error'] = "CSRF token validation failed. Please try submitting the form again.";
        unset($_SESSION['csrf_token_edit_job']); 
        header("Location: dashboard.php"); 
        exit();
    }
    // Don't unset immediately on valid token, do it after processing.

    if (!isset($_POST['job_id']) || !is_numeric($_POST['job_id'])) {
        $_SESSION['post_job_error'] = "Invalid Job ID for update.";
        header("Location: dashboard.php");
        exit();
    }
    $job_id = intval($_POST['job_id']);

    $title = isset($_POST['title_edit']) ? trim($_POST['title_edit']) : '';
    $description = isset($_POST['description_edit']) ? trim($_POST['description_edit']) : ''; 
    $location = isset($_POST['location_edit']) ? trim($_POST['location_edit']) : '';
    $salary_raw = $_POST['salary_edit'] ?? '0';
    $status = isset($_POST['status_edit']) ? trim($_POST['status_edit']) : '';
    
    $job_type = isset($_POST['job_type_edit']) ? trim($_POST['job_type_edit']) : null;
    $experience_level = isset($_POST['experience_level_edit']) ? trim($_POST['experience_level_edit']) : null;
    $education_level = isset($_POST['education_level_edit']) ? trim($_POST['education_level_edit']) : null;
    $skills_required = isset($_POST['skills_required_edit']) ? trim($_POST['skills_required_edit']) : null;

    $errors = [];
    if (empty($title)) { $errors[] = "Job Title is required."; }
    // ... (other existing validations for description, location, salary, status, job_type etc.) ...
    if (empty($description)) { $errors[] = "Job Description is required."; }
    if (empty($location)) { $errors[] = "Location is required."; }
    
    $salary = filter_var($salary_raw, FILTER_VALIDATE_FLOAT);
    if ($salary === false || $salary <= 0) {
        $errors[] = "Salary must be a valid positive number.";
    }

    $allowed_status = ['active', 'inactive'];
    if (empty($status) || !in_array($status, $allowed_status)) {
        $errors[] = "Invalid status selected. Please choose 'Active' or 'Inactive'.";
    }
    
    $job_type_options_for_modal = ["Full-time", "Part-time", "Contract", "Internship", "Temporary"];
    if (!empty($job_type) && !in_array($job_type, $job_type_options_for_modal)) { $errors[] = "Invalid Job Type."; }
    // (Similar checks for experience_level and education_level if you have predefined lists)


    if (!empty($errors)) {
        $_SESSION['post_job_error'] = implode("<br>", $errors);
        $_SESSION['edit_form_data'] = $_POST; 
        header("Location: dashboard.php?action=edit_failed&job_id=" . $job_id); 
        exit();
    }

    $sql_update = "UPDATE jobs SET 
                    title=?, description=?, location=?, salary=?, status=?, 
                    job_type=?, experience_level=?, education_level=?, skills_required=? 
                   WHERE id=? AND employer_id=?";
                   
    $stmt_update = $conn->prepare($sql_update);

    if (!$stmt_update) {
        $_SESSION['post_job_error'] = "Database error (prepare): " . $conn->error;
        error_log("Edit Job Prepare Error (Employer ID: {$employer_id}, Job ID: {$job_id}): " . $conn->error);
    } else {
        $stmt_update->bind_param("sssdsssssii", 
            $title, $description, $location, $salary, $status,
            $job_type, $experience_level, $education_level, $skills_required,
            $job_id, $employer_id
        );

        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                // MODIFIED SUCCESS MESSAGE
                $_SESSION['post_job_success'] = "Job posting '" . htmlspecialchars($title) . "' updated successfully!";
            } else {
                // Check if the job actually belongs to the employer before saying "not found"
                $check_sql = "SELECT id FROM jobs WHERE id = ? AND employer_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $job_id, $employer_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    // MODIFIED INFO MESSAGE
                    $_SESSION['post_job_notification_info'] = "No changes were made to the job posting '" . htmlspecialchars($title) . "'.";
                } else {
                    // Error message here is fine as it's a security/data integrity issue
                    $_SESSION['post_job_error'] = "Job (ID: $job_id) not found or you do not have permission to edit it.";
                }
                $check_stmt->close();
            }
            unset($_SESSION['csrf_token_edit_job']); // Consume token on successful operation or no change
        } else {
            $_SESSION['post_job_error'] = "Failed to update job posting: " . $stmt_update->error;
            error_log("Error updating job ID {$job_id} for employer ID {$employer_id}: " . $stmt_update->error);
            // Don't unset CSRF token here to allow retry from dashboard if modal reopens
            // The dashboard's GET load will handle regenerating if edit_failed is set
        }
        $stmt_update->close();
    }
} else {
    $_SESSION['post_job_error'] = "Invalid request method for editing job.";
}

if (isset($conn)) {
    $conn->close();
}
header("Location: dashboard.php"); 
exit();
?>