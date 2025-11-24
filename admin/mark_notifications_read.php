<?php
// Make sure no output before this line
ini_set('display_errors', 0); // For production, use 0 and log errors
error_reporting(0);        // For production

session_start(); // MUST be at the top
include '../includes/config.php'; 

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        throw new Exception('User not authenticated.');
    }

    // For admin "mark all as read", we use the admin's own ID from session
    // to update notifications where they are the recipient (user_id in notifications table)
    if ($_SESSION['role'] !== 'admin') {
         throw new Exception('Unauthorized action for this role.');
    }
    
    $recipient_user_id = $_SESSION['user_id']; // This is the admin's ID

    if (!$conn) {
        throw new Exception('Database connection failed.');
    }

    // Mark all unread notifications for this admin as read
    // You might add a type filter here if admin has different types of notifications, e.g., AND type = 'new_employer_registered'
    $sql_update = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql_update);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $recipient_user_id);
    
    if ($stmt->execute()) {
        // $affected_rows = $stmt->affected_rows; // Number of notifications marked as read
        $response['success'] = true;
        $response['message'] = 'All notifications marked as read.';
    } else {
        throw new Exception('Failed to update notifications: ' . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    // For production, log detailed error to a file
    error_log("MarkAllRead Error (Admin): " . $e->getMessage() . " | Admin ID: " . ($_SESSION['user_id'] ?? 'N/A'));
}

if (isset($conn) && $conn instanceof mysqli && $conn->ping()) { // Check if connection is still active
    $conn->close();
}

echo json_encode($response);
exit();
?>