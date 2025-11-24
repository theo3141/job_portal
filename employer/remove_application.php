<?php
session_start();
include '../includes/config.php'; // Adjust path as necessary

header('Content-Type: application/json'); // We'll respond with JSON

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer' || !isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit();
}

$employer_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['application_id'])) {
    $application_id = intval($_POST['application_id']);

    if ($application_id > 0) {
        // Verify that this application belongs to a job posted by the current employer
        // This is an important security check
        $sql_verify = "SELECT COUNT(*) 
                       FROM applications a
                       JOIN jobs j ON a.job_id = j.id
                       WHERE a.id = ? AND j.employer_id = ?";
        $stmt_verify = $conn->prepare($sql_verify);
        if ($stmt_verify) {
            $stmt_verify->bind_param("ii", $application_id, $employer_id);
            $stmt_verify->execute();
            $stmt_verify->bind_result($count);
            $stmt_verify->fetch();
            $stmt_verify->close();

            if ($count > 0) {
                // Application belongs to the employer, proceed with deletion
                $sql_delete = "DELETE FROM applications WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                if ($stmt_delete) {
                    $stmt_delete->bind_param("i", $application_id);
                    if ($stmt_delete->execute()) {
                        if ($stmt_delete->affected_rows > 0) {
                            $response['success'] = true;
                            $response['message'] = 'Application removed successfully.';
                        } else {
                            $response['message'] = 'Application not found or already removed.';
                        }
                    } else {
                        $response['message'] = 'Error removing application: ' . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                } else {
                    $response['message'] = 'Error preparing delete statement: ' . $conn->error;
                }
            } else {
                $response['message'] = 'Permission denied. You can only remove applications for your own job postings.';
            }
        } else {
             $response['message'] = 'Error preparing verification: ' . $conn->error;
        }
    } else {
        $response['message'] = 'Invalid application ID.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

echo json_encode($response);
exit();
?>