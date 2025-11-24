<?php
// NO WHITESPACE OR CHARACTERS BEFORE THIS LINE
session_start();
include '../includes/config.php'; // Ensure this file does not output anything

header('Content-Type: application/json'); // Crucial: Set content type to JSON
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer' || !isset($_SESSION['user_id'])) {
    $response['message'] = 'Error: Unauthorized access. Please log in again.';
    echo json_encode($response);
    exit();
}

$employer_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['application_id']) && isset($_POST['status'])) {
        $application_id = filter_var($_POST['application_id'], FILTER_VALIDATE_INT);
        $new_status = $_POST['status']; // e.g., 'accepted', 'rejected', 'pending'

        // Validate inputs
        if (!in_array($new_status, ['accepted', 'rejected', 'pending'])) {
            $response['message'] = 'Error: Invalid status value provided.';
        } elseif ($application_id === false || $application_id <= 0) {
            $response['message'] = 'Error: Invalid application ID.';
        } else {
            // Proceed with database operations
            $conn->begin_transaction();
            try {
                // Verify this application belongs to a job posted by this employer
                $sql_verify = "SELECT a.job_id, a.jobseeker_id, j.title as job_title
                               FROM applications a
                               JOIN jobs j ON a.job_id = j.id
                               WHERE a.id = ? AND j.employer_id = ?";
                $stmt_verify = $conn->prepare($sql_verify);
                if (!$stmt_verify) throw new Exception("Prepare statement failed (verify): " . $conn->error);
                $stmt_verify->bind_param("ii", $application_id, $employer_id);
                $stmt_verify->execute();
                $result_verify = $stmt_verify->get_result();

                if ($result_verify->num_rows > 0) {
                    $application_details = $result_verify->fetch_assoc();
                    $jobseeker_id_to_notify = $application_details['jobseeker_id'];
                    $job_title = $application_details['job_title'];
                    $stmt_verify->close();

                    // Update application status
                    // Reset 'notified' flag; will be set to 1 if notification is successfully sent
                    $sql_update = "UPDATE applications SET status = ?, notified = 0 WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if (!$stmt_update) throw new Exception("Prepare statement failed (update): " . $conn->error);
                    $stmt_update->bind_param("si", $new_status, $application_id);

                    if ($stmt_update->execute()) {
                        $notification_sent_successfully = true; // Assume true, set to false on failure
                        // Prepare notification message if status is 'accepted' or 'rejected'
                        $notification_message_text = "";
                        if ($new_status == 'accepted') {
                            $notification_message_text = "Congratulations! Your application for the job \"".htmlspecialchars($job_title)."\" has been accepted.";
                        } elseif ($new_status == 'rejected') {
                            $notification_message_text = "We regret to inform you that your application for \"".htmlspecialchars($job_title)."\" was not successful at this time.";
                        }

                        if (!empty($notification_message_text)) {
                            $sql_notify = "INSERT INTO notifications (user_id, message, date, is_read) VALUES (?, ?, NOW(), 0)";
                            $stmt_notify = $conn->prepare($sql_notify);
                            if ($stmt_notify) {
                                $stmt_notify->bind_param("is", $jobseeker_id_to_notify, $notification_message_text);
                                if ($stmt_notify->execute()) {
                                    // Mark the application as 'notified' in the applications table
                                    $sql_mark_notified = "UPDATE applications SET notified = 1 WHERE id = ?";
                                    $stmt_mark_notified = $conn->prepare($sql_mark_notified);
                                    if($stmt_mark_notified) {
                                        $stmt_mark_notified->bind_param("i", $application_id);
                                        $stmt_mark_notified->execute();
                                        $stmt_mark_notified->close();
                                    } else {
                                         error_log("Failed to prepare statement to mark application as notified for app ID {$application_id}: " . $conn->error);
                                         // Continue, main update succeeded.
                                    }
                                } else {
                                    $notification_sent_successfully = false;
                                    error_log("Failed to insert notification for app ID {$application_id}: " . $stmt_notify->error);
                                }
                                $stmt_notify->close();
                            } else {
                                $notification_sent_successfully = false;
                                error_log("Failed to prepare notification statement for app ID {$application_id}: " . $conn->error);
                            }
                        }

                        $conn->commit();
                        $response['success'] = true;
                        $response['message'] = "Application status updated to '" . htmlspecialchars($new_status) . "'.";
                        if (!empty($notification_message_text) && !$notification_sent_successfully) {
                            $response['message'] .= " (Notification to jobseeker may have failed)";
                        }
                        $response['new_status_display'] = ucfirst(htmlspecialchars($new_status)); // For UI update
                    } else {
                        throw new Exception("Database error: Could not update application status. " . $stmt_update->error);
                    }
                    $stmt_update->close();
                } else {
                    $stmt_verify->close(); // Close verify statement if no rows found
                    throw new Exception("Error: Application not found or you do not have permission to modify it.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Exception in update_application_status.php: " . $e->getMessage()); // Log detailed error
                $response['message'] = $e->getMessage(); // Send specific error message
            }
        }
    } else {
        $response['message'] = 'Error: Required parameters are missing.';
    }
} else {
    $response['message'] = 'Error: Invalid request method.';
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

echo json_encode($response);
exit();