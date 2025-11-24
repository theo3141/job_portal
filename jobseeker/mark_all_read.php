<?php
session_start();
include '../includes/config.php'; // Adjust path as needed

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

if ($conn) {
    $sql_mark_read = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt_mark_read = $conn->prepare($sql_mark_read);

    if ($stmt_mark_read) {
        $stmt_mark_read->bind_param("i", $jobseeker_id);
        if ($stmt_mark_read->execute()) {
            if ($stmt_mark_read->affected_rows > 0) {
                $_SESSION['success'] = "Notifications marked as read.";
            } else {
                $_SESSION['success'] = "No new notifications to mark as read."; // Or just a neutral message
            }
        } else {
            $_SESSION['error'] = "Failed to mark notifications as read. Please try again.";
        }
        $stmt_mark_read->close();
    } else {
        $_SESSION['error'] = "Database error: Could not prepare statement.";
    }
    $conn->close();
} else {
    $_SESSION['error'] = "Database connection error.";
}

header("Location: dashboard.php"); // Redirect back to dashboard
exit();
?>