<?php
session_start();
include 'includes/config.php'; // Assuming config.php is in 'includes' directory relative to this login.php

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'employer':
            header("Location: employer/dashboard.php");
            break;
        case 'jobseeker':
            header("Location: jobseeker/dashboard.php");
            break;
        default:
            // If role is unknown but session exists, maybe logout or redirect to a generic page
            header("Location: index.php"); // Or logout.php
    }
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $error = null; // Initialize error variable

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            // Log this error for the admin, show a generic message to the user
            error_log("SQL prepare error: " . $conn->error);
            $error = "An unexpected error occurred. Please try again later.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    if ($user['role'] == 'employer' && $user['is_approved'] != 1) {
                        $error = "Your employer account is pending approval by the admin.";
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['user_name'] = $user['name']; // Store name for welcome messages

                        // Regenerate session ID for security
                        session_regenerate_id(true);

                        switch ($_SESSION['role']) {
                            case 'admin':
                                header("Location: admin/dashboard.php");
                                break;
                            case 'employer':
                                header("Location: employer/dashboard.php");
                                break;
                            case 'jobseeker':
                                header("Location: jobseeker/dashboard.php");
                                break;
                            default:
                                header("Location: index.php"); // Fallback
                        }
                        exit();
                    }
                } else {
                    $error = "Invalid email or password!";
                }
            } else {
                $error = "No account found with this email address.";
            }
            $stmt->close();
        }
    }
}
$conn->close(); // Close connection if it was opened
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Online Job Portal</title>
    <!-- <link rel="stylesheet" href="styles.css"> Assuming styles.css is for global styles not login specific -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #1D3557;
            --secondary-color: #457B9D;
            --accent-color: #A8DADC;
            --light-color: #F1FAEE; /* A very light, almost white color */
            --danger-color: #E63946;
            --danger-bg-color: #fdd8d8;
            --input-border-color: #ced4da; /* Softer border color */
            --input-focus-border: #86b7fe; /* Bootstrap-like focus color */
            --input-focus-shadow: rgba(13, 110, 253, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto; /* Allow scroll if content overflows on small screens */
        }

        .login-container {
            background: #fff;
            padding: 35px 40px; /* Increased padding */
            width: 100%;
            max-width: 420px; /* Slightly wider */
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease-in-out;
        }

        .login-container:hover {
            transform: translateY(-5px); /* Subtle hover effect */
        }

        .login-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1.8em; /* Larger title */
            font-weight: 600;
        }
        .login-header p {
            color: #6c757d; /* Subtitle color */
            margin-bottom: 25px;
            font-size: 0.95em;
        }


        .error-message {
            color: var(--danger-color);
            background: var(--danger-bg-color);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            border: 1px solid var(--danger-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left; /* Align labels to the left */
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #495057;
            font-size: 0.9em;
        }

        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 14px 18px; /* Increased padding for better touch targets */
            border: 1px solid var(--input-border-color);
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem var(--secondary-color_rgba, rgba(69, 123, 157, 0.25));
        }

        .btn-login {
            background: var(--primary-color);
            color: #fff;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.05em;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 10px; /* Space above button */
        }

        .btn-login:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        .btn-login:active {
            transform: translateY(0);
        }


        .register-link {
            margin-top: 25px;
            font-size: 0.9em;
            color: #555;
        }

        .register-link a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
            color: var(--primary-color);
        }

        /* Loading Overlay (Spinner) */
        .loading-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.85);
            z-index: 10000; /* Ensure it's on top */
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 50px; /* Adjusted size */
            height: 50px;
            border: 5px solid var(--accent-color);
            border-top-color: var(--secondary-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<div class="login-container">
    <div class="login-header">
        <h2>Welcome Back!</h2>
        <p>Login to access your job portal account.</p>
    </div>

    <?php if (isset($error) && !empty($error)): ?>
        <p class='error-message'><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form id="loginForm" method="POST" action="login.php" onsubmit="showLoading()">
        <!-- Action attribute added for clarity -->
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-login">Login</button>
    </form>
    <p class="register-link">Don't have an account? <a href="register.php">Register Here</a></p>
</div>

<script>
    function showLoading() {
        // Optional: Basic client-side validation before showing loader
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        if (email.trim() === '' || password.trim() === '') {
            // If you have a way to show inline errors, do that instead of alert
            // alert('Please fill in all fields.');
            return false; // Prevent form submission and loader
        }
        document.getElementById('loadingOverlay').style.display = 'flex';
        return true; // Allow form submission
    }
</script>

</body>
</html>