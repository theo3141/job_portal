<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];
$uploadDir = '../uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resume'])) {
    $file = $_FILES['resume'];
    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    if (in_array($file['type'], $allowedTypes)) {
        $fileName = 'resume_' . $jobseeker_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $sql = "UPDATE users SET resume = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $fileName, $jobseeker_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Resume uploaded successfully!";
            } else {
                $_SESSION['error'] = "Database update failed.";
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "File upload failed.";
        }
    } else {
        $_SESSION['error'] = "Invalid file type. Only PDF and Word documents are allowed.";
    }
}

header("Location: profile.php");
exit();
?>
