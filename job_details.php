<?php
session_start();
include 'includes/config.php'; 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php"); 
    exit();
}

$job_id = intval($_GET['id']); 

$sql = "SELECT * FROM jobs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details | <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #D1F8EF;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 80%;
            margin: auto;
            max-width: 1000px;
        }
        nav {
            background: #3674B5;
            padding: 15px;
            text-align: center;
        }
        nav a {
            color: white;
            margin: 0 15px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
        }
        .job-details {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        .job-details h2 {
            color: #3674B5;
            margin-bottom: 10px;
        }
        .job-details p {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 20px;
            background: #3674B5;
            color: white;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px;
            transition: 0.3s;
        }
        .btn:hover {
            background: #2c5ea0;
        }
    </style>
</head>
<body>

    <nav>
        <a href="index.php">Home</a>
        <a href="browse_jobs.php">Browse Jobs</a>
        <?php if (isset($_SESSION['role'])): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>

    <div class="container">
        <div class="job-details">
            <h2><?php echo htmlspecialchars($job['title']); ?></h2>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
            <p><strong>Salary:</strong> $<?php echo number_format($job['salary'], 2); ?></p>
            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($job['description'])); ?></p>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'jobseeker'): ?>
                <form action="apply.php" method="POST">
                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                    <button type="submit" class="btn">Apply Now</button>
                </form>
            <?php else: ?>
                <p style="color: red;">Login as a jobseeker to apply.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
