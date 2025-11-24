<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['job_id'])) {
    $job_id = intval($_POST['job_id']);
    $jobseeker_id = $_SESSION['user_id'];
    $jobseeker_name = $_SESSION['user_name'] ?? 'A jobseeker'; // Get jobseeker's name from session

    // Check if the user has already applied for this job
    $check_sql = "SELECT id FROM applications WHERE jobseeker_id = ? AND job_id = ?";
    $stmt_check = $conn->prepare($check_sql);

    if (!$stmt_check) {
        $_SESSION['error'] = "Database error (check application). Please try again.";
        error_log("Prepare failed (check_sql): " . $conn->error);
        header("Location: dashboard.php");
        exit();
    }

    $stmt_check->bind_param("ii", $jobseeker_id, $job_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $_SESSION['error'] = "You have already applied for this job.";
    } else {
        $conn->begin_transaction();
        try {
            // Insert the application
            $sql_apply = "INSERT INTO applications (jobseeker_id, job_id, status, applied_at) VALUES (?, ?, 'pending', NOW())";
            $stmt_apply = $conn->prepare($sql_apply);

            if (!$stmt_apply) {
                throw new Exception("Database error (apply). Please try again. Prepare failed: " . $conn->error);
            }

            $stmt_apply->bind_param("ii", $jobseeker_id, $job_id);

            // --- TEMPORARY LOGGING: Before Insertion ---
            error_log("APPLY_DEBUG: Attempting to insert application for jobseeker_id=" . $jobseeker_id . " and job_id=" . $job_id);
            // --- END TEMPORARY LOGGING ---

            if ($stmt_apply->execute()) {
                $new_application_id = $stmt_apply->insert_id; // Get the new application ID
                $_SESSION['success'] = "Application submitted successfully!";

                // --- TEMPORARY LOGGING: Successful Insertion ---
                error_log("APPLY_DEBUG: Successfully inserted application with ID=" . $new_application_id);
                // --- END TEMPORARY LOGGING ---

                // --- START: Notify Employer ---
                // 1. Get employer_id and job_title for the notification
                $sql_job_info = "SELECT employer_id, title FROM jobs WHERE id = ?";
                $stmt_job_info = $conn->prepare($sql_job_info);
                if ($stmt_job_info) {
                    $stmt_job_info->bind_param("i", $job_id);
                    $stmt_job_info->execute();
                    $result_job_info = $stmt_job_info->get_result();
                    if ($job_data = $result_job_info->fetch_assoc()) {
                        $employer_to_notify_id = $job_data['employer_id'];
                        $job_title_for_notification = $job_data['title'];

                        $notification_message = htmlspecialchars($jobseeker_name) . " has applied for your job: \"" . htmlspecialchars($job_title_for_notification) . "\".";
                        // Optional: Add a link to view the application
                        // $link_to_application = "employer/view_applications.php#application-" . $new_application_id; // Example link structure

                        $sql_insert_notification = "INSERT INTO notifications (user_id, message, date, is_read, related_item_id, related_item_type) VALUES (?, ?, NOW(), 0, ?, 'application')";
                        $stmt_employer_notification = $conn->prepare($sql_insert_notification);
                        if ($stmt_employer_notification) {
                            $stmt_employer_notification->bind_param("isi", $employer_to_notify_id, $notification_message, $new_application_id);
                            if (!$stmt_employer_notification->execute()) {
                                error_log("Failed to send notification to employer for new application. App ID: {$new_application_id}, Employer ID: {$employer_to_notify_id} - Error: " . $stmt_employer_notification->error);
                                // Don't let notification failure stop the application success
                            }
                            $stmt_employer_notification->close();
                        } else {
                             error_log("Failed to prepare employer notification statement. App ID: {$new_application_id} - Error: " . $conn->error);
                        }
                    }
                    $stmt_job_info->close();
                } else {
                    error_log("Failed to prepare job info statement for employer notification. Job ID: {$job_id} - Error: " . $conn->error);
                }
                // --- END: Notify Employer ---
                $conn->commit();
            } else {
                // --- TEMPORARY LOGGING: Failed Insertion ---
                error_log("APPLY_DEBUG: Failed to execute application insertion. Error: " . $stmt_apply->error);
                // --- END TEMPORARY LOGGING ---
                throw new Exception("Error submitting application: " . $stmt_apply->error);
            }
            $stmt_apply->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
            error_log("Exception in apply.php: " . $e->getMessage());
        }
    }

    $stmt_check->close();
    $conn->close();

    header("Location: dashboard.php");
    exit();

} else {
    $_SESSION['error'] = "Invalid request or missing job ID.";
    header("Location: dashboard.php");
    exit();
}
?>