<?php
session_start();
include '../includes/config.php'; // Ensure this path is correct

// Initialize messages to avoid undefined variable notices
$_SESSION['edit_profile_error'] = null;
$_SESSION['edit_profile_success'] = null;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker' || !isset($_SESSION['user_id'])) {
    // If not logged in or wrong role, redirect to login (or profile page if that's preferred)
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']) ?: null; 
    $contact_number = trim($_POST['contact_number']) ?: null;

    $error_message_local = ""; // Local error message for this script

    if (empty($name) || empty($email)) {
        $error_message_local = "Name and Email cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message_local = "Invalid email format.";
    } elseif (!empty($contact_number) && !preg_match('/^[0-9\+\-\s()]{7,20}$/', $contact_number)) {
        $error_message_local = "Invalid contact number format.";
    } else {
        // Check if the new email is already taken by ANOTHER user
        $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check_email);
        if (!$stmt_check) {
            $error_message_local = "Database error (email check prepare): " . $conn->error;
        } else {
            $stmt_check->bind_param("si", $email, $jobseeker_id);
            $stmt_check->execute();
            $result_check_email = $stmt_check->get_result();

            if ($result_check_email->num_rows > 0) {
                $error_message_local = "This email address is already registered by another user.";
            }
            $stmt_check->close();
        }
        
        if (empty($error_message_local)) { // Proceed only if no errors so far
            // Proceed with update
            $sql_update = "UPDATE users SET name = ?, email = ?, address = ?, contact_number = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) {
                $error_message_local = "Error preparing update statement: " . $conn->error;
            } else {
                $stmt_update->bind_param("ssssi", $name, $email, $address, $contact_number, $jobseeker_id);
                if ($stmt_update->execute()) {
                    $_SESSION['edit_profile_success'] = "Profile updated successfully!";
                    $_SESSION['user_name'] = $name; // Update session name
                } else {
                    $error_message_local = "Error updating profile: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        }
    }

    if (!empty($error_message_local)) {
        $_SESSION['edit_profile_error'] = $error_message_local;
        // Store submitted data in session to re-fill form on error
        $_SESSION['form_data_edit_profile'] = $_POST;
    }

} else {
    // If not a POST request, just redirect to profile (should not happen if linked correctly)
    $_SESSION['edit_profile_error'] = "Invalid request method.";
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

header("Location: profile.php"); // Always redirect back to profile page
exit();
?>