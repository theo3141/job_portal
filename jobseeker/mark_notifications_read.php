<?php
include '../includes/config.php';
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['user_id'])) {
    $user_id = $data['user_id'];
    $sql_update = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}
?>
