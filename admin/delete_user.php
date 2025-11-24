<?php
session_start();
include '../includes/config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $userId = intval($_POST['id']);

    if ($userId > 0) {
        // Ensure admin is not trying to delete themselves or another admin (if you have multiple)
        $sql_check_role = "SELECT role FROM users WHERE id = ?";
        $stmt_check_role = $conn->prepare($sql_check_role);
        $stmt_check_role->bind_param("i", $userId);
        $stmt_check_role->execute();
        $result_role = $stmt_check_role->get_result();
        if ($user_to_delete = $result_role->fetch_assoc()) {
            if ($user_to_delete['role'] === 'admin') {
                $response['message'] = 'Admins cannot be deleted through this interface.';
                echo json_encode($response);
                exit();
            }
        } else {
            $response['message'] = 'User not found.';
            echo json_encode($response);
            exit();
        }
        $stmt_check_role->close();


        // Optional: Delete related data first if there are foreign key constraints
        // e.g., if a user is an employer, delete their jobs and applications first
        // For now, assuming ON DELETE CASCADE handles related job/application data for employers.
        // If a jobseeker has applications, those might need to be handled or allowed to be orphaned depending on your design.
        // For simplicity, we'll just delete the user. Add more complex logic if needed.

        $sql = "DELETE FROM users WHERE id = ? AND role != 'admin'"; // Double check role here
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = "User (ID: {$userId}) deleted successfully.";
                } else {
                    $response['message'] = "User not found, already deleted, or is an admin.";
                }
            } else {
                $response['message'] = "Error executing delete: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = "Error preparing delete statement: " . $conn->error;
        }
    } else {
        $response['message'] = "Invalid user ID.";
    }
} else {
    $response['message'] = "Invalid request method or missing parameters.";
}

$conn->close();
echo json_encode($response);
?>