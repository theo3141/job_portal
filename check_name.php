<?php
include 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = trim($_POST['name']);

    // Check if the name exists
    $check_name = "SELECT id FROM users WHERE name = ?";
    $stmt = $conn->prepare($check_name);
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "taken"; // Name already exists
    } else {
        echo "available"; // Name is available
    }
    $stmt->close();
}
?>
