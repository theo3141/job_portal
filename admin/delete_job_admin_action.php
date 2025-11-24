<?php
// Make sure no output before this line
ini_set('display_errors', 0); // Turn off for production, use error logging
error_reporting(0);        // Turn off for production

session_start();
include '../includes/config.php'; 

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access.');
    }

    if (!$conn) {
        throw new Exception('Database connection failed.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id']) && isset($_POST['action']) && $_POST['action'] === 'delete_job') {
        $jobId = intval($_POST['job_id']);

        if ($jobId <= 0) {
            throw new Exception('Invalid job ID.');
        }

        $conn->begin_transaction();

        // First, delete associated applications
        $sql_delete_apps = "DELETE FROM applications WHERE job_id = ?";
        $stmt_apps = $conn->prepare($sql_delete_apps);
        if (!$stmt_apps) throw new Exception("Prepare failed (applications): " . $conn->error);
        $stmt_apps->bind_param("i", $jobId);
        if (!$stmt_apps->execute()) throw new Exception("Execute failed (applications): " . $stmt_apps->error);
        $stmt_apps->close();

        // Then, delete the job
        $sql_delete_job = "DELETE FROM jobs WHERE id = ?";
        $stmt_job = $conn->prepare($sql_delete_job);
        if (!$stmt_job) throw new Exception("Prepare failed (job): " . $conn->error);
        $stmt_job->bind_param("i", $jobId);
        
        if ($stmt_job->execute()) {
            if ($stmt_job->affected_rows > 0) {
                $conn->commit();
                $response['success'] = true;
                $response['message'] = "Job (ID: {$jobId}) and its applications deleted successfully.";
            } else {
                // It's possible applications were deleted but the job itself wasn't found (e.g., already deleted by another process)
                // If applications were deleted, a commit might still be okay if that's desired behavior.
                // For strictness, if the job isn't deleted, we might rollback.
                $conn->rollback(); 
                $response['message'] = "Job not found or already deleted (or no change made).";
            }
        } else {
            throw new Exception("Error executing job delete: " . $stmt_job->error);
        }
        $stmt_job->close();

    } else {
        throw new Exception('Invalid request method or missing parameters.');
    }

} catch (Exception $e) {
    if ($conn && $conn->connect_errno === 0 && $conn->in_transaction) { // Check if in transaction before rollback
        $conn->rollback();
    }
    $response['message'] = "Error: " . $e->getMessage();
    // For production, you would log this error to a file instead of echoing details directly in the message
    // error_log("Admin Delete Job Error: " . $e->getMessage() . " | Job ID: " . ($jobId ?? 'N/A'));
}

if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
    $conn->close();
}

echo json_encode($response);
exit();
?>