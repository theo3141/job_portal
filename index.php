<?php
session_start();
include 'includes/config.php';

$sql = "SELECT * FROM jobs ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Job Portal</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #E9F1FC;
            color: #1F2A40;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container {
            width: 90%;
            margin: auto;
            max-width: 1200px;
            flex: 1;
        }

        nav {
            background: #1F2A40;
            padding: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: #fff;
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
        }

        .menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .right-menu {
            margin-left: auto;
            display: flex;
            gap: 20px;
        }

        .menu a {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 8px;
            transition: 0.3s;
        }

        .menu a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .logout {
            background: #E74C3C;
            border-radius: 8px;
        }

        header {
            background: linear-gradient(135deg, #3498DB, #1F2A40);
            text-align: center;
            padding: 80px 0;
            color: #fff;
            border-radius: 0 0 40px 40px;
        }

        header h1 {
            font-size: 42px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        header p {
            font-size: 18px;
            margin-bottom: 25px;
            opacity: 0.9;
        }

        .btn {
            background: #E74C3C;
            color: #fff;
            padding: 12px 30px;
            text-decoration: none;
            font-size: 18px;
            font-weight: bold;
            border-radius: 50px;
            transition: 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn:hover {
            background: linear-gradient(135deg, #3498DB, #E74C3C);
            transform: translateY(-4px);
        }

        .video-section {
            text-align: center;
            padding: 60px 0;
            background-color: #F3F6FA;
        }

        .video-section h2 {
            font-size: 30px;
            color: #1F2A40;
            margin-bottom: 15px;
        }

        .video-section p {
            font-size: 16px;
            color: #555;
            margin-bottom: 25px;
        }

        .video-box {
            max-width: 300px;
            width: 100%;
            margin: auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .video-box video {
            width: 100%;
            height: auto;
            border-radius: 12px;
        }

        .jobs-section {
            padding: 60px 0;
            text-align: center;
            background-color: #E9F1FC;
        }

        .jobs-section h2 {
            margin-bottom: 40px;
            color: #1F2A40;
            font-size: 30px;
        }

        .job-list {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .job-item {
            background: #3498DB;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
            text-align: left;
            position: relative;
        }

        .job-item:hover {
            transform: translateY(-5px);
            background: #1F2A40;
            color: #fff;
        }

        .job-item h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .job-item p {
            color: #fff;
            font-size: 15px;
        }

        .job-item .btn {
            display: inline-block;
            margin-top: 15px;
            font-size: 16px;
            padding: 10px 20px;
            background: #E74C3C;
        }

        .job-item .btn:hover {
            background: #2C3E50;
        }

        /* Scroll-to-top button */
        .scroll-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3498DB;
            color: #fff;
            padding: 12px 15px;
            border-radius: 50%;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            display: none;
            transition: 0.3s;
        }

        .scroll-top:hover {
            background: #E74C3C;
        }

        /* Footer design */
        footer {
            background: #1F2A40;
            color: #fff;
            padding: 20px 0;
            text-align: center;
            margin-top: 40px;
        }

        footer p {
            margin: 0;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <nav>
        <div class="container">
            <a href="index.php" class="logo"><i class="fas fa-briefcase"></i> Blue Job</a>
            <div class="menu">
                <a href="browse_jobs.php"><i class="fas fa-search"></i> Browse Jobs</a>

                <?php if (isset($_SESSION['role'])): ?>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="admin_dashboard.php"><i class="fas fa-user-shield"></i> Admin Panel</a>
                    <?php else: ?>
                        <a href="dashboard.php"><i class="fas fa-user"></i> Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <div class="right-menu">
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <header>
        <div class="container">
            <h1>Find Your Dream Job In BlueJob</h1>
            <p>Connect with top employers and explore exciting job opportunities.</p>
            <a href="browse_jobs.php" class="btn"><i class="fas fa-briefcase"></i> Browse Jobs</a>
        </div>
    </header>

    <section class="video-section">
        <div class="container">
            <h2>Learn How to Get Hired</h2>
            <p>Watch this video to get tips on landing your dream job.</p>
            <div class="video-box">
                <video controls>
                    <source src="videos/job_tips.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </section>

    <section class="jobs-section">
        <div class="container">
            <h2><i class="fas fa-briefcase"></i> Latest Job Postings</h2>
            <ul class="job-list">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <li class="job-item">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['location']); ?></p>
                        <p><i class="fas fa-money-bill-wave"></i> Salary: ₱<?php echo number_format($row['salary'], 2); ?></p>
                        <a href="job_details.php?id=<?php echo $row['id']; ?>" class="btn"><i class="fas fa-eye"></i> View Details</a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </section>

    <!-- Scroll to Top Button -->
    <div class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- JavaScript for Scroll-to-Top Button -->
    <script>
        const scrollBtn = document.getElementById('scrollTop');

        window.onscroll = () => {
            if (document.documentElement.scrollTop > 300) {
                scrollBtn.style.display = 'block';
            } else {
                scrollBtn.style.display = 'none';
            }
        };

        scrollBtn.onclick = () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
    </script>

</body>

</html>
