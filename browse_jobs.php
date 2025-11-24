<?php 
session_start();
include 'includes/config.php'; 

$sql_jobs = "SELECT j.id, j.title, j.description, j.location, j.salary, u.name AS employer_name 
             FROM jobs j
             JOIN users u ON j.employer_id = u.id
             ORDER BY j.created_at DESC";

$result_jobs = $conn->query($sql_jobs);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        /* General Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: #F1F6F9;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
        }

        h2 {
            color: #1D3557;
            text-align: center;
            margin-top: 20px;
        }

        /* Navbar */
        nav {
            background: linear-gradient(135deg, #457B9D, #1D3557);
            padding: 12px 0;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        nav a:hover {
            background: #0056b3;
        }

        /* Job Cards */
        .job-listings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .job-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .job-card:hover {
            transform: translateY(-5px);
        }

        .job-card h3 {
            color: #1D3557;
            margin: 0;
        }

        .job-info {
            margin-top: 10px;
            color: #333;
        }

        .job-info p {
            margin: 5px 0;
        }

        /* Apply Button */
        .apply-btn {
            display: inline-block;
            margin-top: 12px;
            background: #28a745;
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }

        .apply-btn:hover {
            background: #218838;
        }

        .apply-btn.disabled {
            background: #6c757d;
            pointer-events: none;
        }

        /* Footer */
        footer {
            background: #1D3557;
            color: white;
            text-align: center;
            padding: 12px 0;
            margin-top: 30px;
            border-radius: 8px 8px 0 0;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="browse_jobs.php"><i class="fas fa-briefcase"></i> Browse Jobs</a>
        <?php if (isset($_SESSION['role'])) : ?>
            <a href="dashboard.php"><i class="fas fa-user"></i> Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else : ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
    </nav>

    <!-- Page Header -->
    <h2><i class="fas fa-briefcase"></i> Browse Available Jobs</h2>

    <!-- Job Listings -->
    <div class="container">
        <section class="job-listings">
            <?php if ($result_jobs->num_rows > 0) : ?>
                <?php while ($job = $result_jobs->fetch_assoc()) : ?>
                    <div class="job-card">
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <div class="job-info">
                            <p><strong>Employer:</strong> <?php echo htmlspecialchars($job['employer_name']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                            <p><strong>Salary:</strong> ₱<?php echo number_format($job['salary'], 2); ?></p>
                            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($job['description'])); ?></p>

                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'jobseeker') : ?>
                                <form method="POST" action="jobseeker/apply.php" style="display:inline;">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <button type="submit" class="apply-btn"><i class="fas fa-paper-plane"></i> Apply Now</button>
                                </form>
                            <?php else : ?>
                                <a href="login.php" class="apply-btn disabled"><i class="fas fa-lock"></i> Log in to Apply</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else : ?>
                <p style="text-align: center; margin-top: 20px;">No job listings available at the moment.</p>
            <?php endif; ?>
        </section>
    </div>

</body>

</html>
