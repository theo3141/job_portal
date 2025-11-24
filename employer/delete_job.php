<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Job ID.");
}

$job_id = intval($_GET['id']);

$sql_delete = "DELETE FROM jobs WHERE id = ? AND employer_id = ?";
$stmt = $conn->prepare($sql_delete);
$stmt->bind_param("ii", $job_id, $employer_id);

if ($stmt->execute()) {
    echo "<script>alert('Job deleted successfully!'); window.location='dashboard.php';</script>";
} else {
    echo "Error deleting job: " . $stmt->error;
}

$stmt->close();
?>
