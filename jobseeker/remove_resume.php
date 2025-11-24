<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

$sql = "SELECT resume FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jobseeker_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user && !empty($user['resume'])) {
    $resumePath = '../uploads/' . $user['resume'];

    if (file_exists($resumePath)) {
        unlink($resumePath);
    }

    $sql = "UPDATE users SET resume = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jobseeker_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Resume removed successfully.";
    } else {
        $_SESSION['error'] = "Failed to update database.";
    }
    $stmt->close();
} else {
    $_SESSION['error'] = "No resume found.";
}

header("Location: profile.php");
exit();
?>
