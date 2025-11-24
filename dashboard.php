<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

$sql_jobs = "SELECT * FROM jobs ORDER BY created_at DESC";
$result_jobs = $conn->query($sql_jobs);

$sql_applied = "SELECT j.title, j.company, j.location, ja.applied_at 
                FROM job_applications ja 
                JOIN jobs j ON ja.job_id = j.id 
                WHERE ja.jobseeker_id = ?";
$stmt_applied = $conn->prepare($sql_applied);
$stmt_applied->bind_param("i", $jobseeker_id);
$stmt_applied->execute();
$result_applied = $stmt_applied->get_result();
$stmt_applied->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobseeker Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="../logout.php">Logout</a>
    </nav>

    <h2>Welcome, Job Seeker!</h2>

    <section>
        <h3>Available Jobs</h3>
        <ul>
            <?php while ($job = $result_jobs->fetch_assoc()): ?>
                <li>
                    <strong><?php echo htmlspecialchars($job['title']); ?></strong> - 
                    <?php echo htmlspecialchars($job['company']); ?> - 
                    <?php echo htmlspecialchars($job['location']); ?>
                    <form method="POST" action="apply.php" style="display:inline;">
                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <input type="submit" value="Apply Now">
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </section>

    <section>
        <h3>My Applications</h3>
        <ul>
            <?php while ($applied = $result_applied->fetch_assoc()): ?>
                <li>
                    <strong><?php echo htmlspecialchars($applied['title']); ?></strong> at 
                    <?php echo htmlspecialchars($applied['company']); ?> - 
                    <?php echo htmlspecialchars($applied['location']); ?> 
                    (Applied on <?php echo date("F j, Y", strtotime($applied['applied_at'])); ?>)
                </li>
            <?php endwhile; ?>
        </ul>
    </section>

</body>
</html>
