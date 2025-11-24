<?php
session_start(); 
include 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role_path = $_SESSION['role'] ?? 'index';
    if ($role_path === 'admin' || $role_path === 'employer' || $role_path === 'jobseeker') {
        // Adjust path if your admin folder is outside the current directory's parent
        if ($role_path === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: {$role_path}/dashboard.php");
        }
    } else {
        header("Location: index.php");
    }
    exit();
}


$success = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password_input = $_POST['password']; 
    $role = $_POST['role'];

    $business_name = ($role === 'employer' && isset($_POST['business_name'])) ? trim($_POST['business_name']) : null;
    $business_id = ($role === 'employer' && isset($_POST['business_id'])) ? trim($_POST['business_id']) : null;
    $contact_number = ($role === 'employer' && isset($_POST['contact_number'])) ? trim($_POST['contact_number']) : null;

    // --- Validations ---
    if (empty($name) || empty($email) || empty($password_input) || empty($role)) {
        $error = "All required fields (Name, Email, Password, Role) must be filled.";
    } elseif (strtolower($name) === 'admin') {
        $error = "The username 'admin' is not allowed. Please choose a different name.";
    } elseif (strlen($password_input) < 6) { 
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format. Please enter a valid email address.";
    }
    elseif ($role === 'employer' && (empty($business_name) || empty($business_id) || empty($contact_number))) {
        $error = "For employers, Business Name, Business ID, and Contact Number are required.";
    } else {
        $password = password_hash($password_input, PASSWORD_DEFAULT);

        if ($role !== 'admin') { 
            $stmt_check_name = $conn->prepare("SELECT id FROM users WHERE LOWER(name) = LOWER(?) AND role != 'admin'");
            $stmt_check_name->bind_param("s", $name);
            $stmt_check_name->execute();
            if ($stmt_check_name->get_result()->num_rows > 0) {
                $error = "The name '".htmlspecialchars($name)."' is already taken. Please choose another.";
            }
            $stmt_check_name->close();
        }

        if (!$error) { 
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $error = "This email address is already registered. <a href='login.php'>Login here</a>.";
            }
            $stmt_check_email->close();
        }

        if (!$error) {
            if ($role === 'employer') {
                $sql = "INSERT INTO users (name, email, password, role, business_name, business_id, contact_number, is_approved)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0)"; 
                $stmt_insert = $conn->prepare($sql);
                $stmt_insert->bind_param("sssssss", $name, $email, $password, $role, $business_name, $business_id, $contact_number);
            } else { 
                $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql);
                $stmt_insert->bind_param("ssss", $name, $email, $password, $role);
            }

            if ($stmt_insert->execute()) {
                $newly_inserted_user_id = $stmt_insert->insert_id; 

                if ($role === 'employer') {
                    $success = "Employer account registered! Please wait for admin approval before you can log in.";

                    // --- START: ADD NOTIFICATION FOR ADMINS ---
                    $admin_ids = [];
                    $sql_get_admins = "SELECT id FROM users WHERE role = 'admin'";
                    $result_admins = $conn->query($sql_get_admins);
                    if ($result_admins && $result_admins->num_rows > 0) {
                        while ($admin_row = $result_admins->fetch_assoc()) {
                            $admin_ids[] = $admin_row['id'];
                        }
                    }

                    if (!empty($admin_ids)) {
                        $employer_identifier = !empty($business_name) ? $business_name : $name;
                        $notification_message = "New employer registered: " . htmlspecialchars($employer_identifier) . " (ID: {$newly_inserted_user_id}). Pending approval.";
                        
                        $sql_insert_admin_notification = "INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)";
                        $stmt_admin_notif = $conn->prepare($sql_insert_admin_notification);
                        
                        if ($stmt_admin_notif) {
                            foreach ($admin_ids as $admin_id_for_notification) { 
                                $stmt_admin_notif->bind_param("is", $admin_id_for_notification, $notification_message);
                                if (!$stmt_admin_notif->execute()) {
                                    error_log("Failed to insert admin notification for admin ID {$admin_id_for_notification}: " . $stmt_admin_notif->error);
                                }
                            }
                            $stmt_admin_notif->close();
                        } else {
                            error_log("Failed to prepare admin notification statement: " . $conn->error);
                        }
                    }
                    // --- END: ADD NOTIFICATION FOR ADMINS ---

                } else { // Jobseeker
                    $success = "Registration successful! <a href='login.php'>You can now login here</a>.";
                }
                $_POST = array();
            } else {
                $error = "Registration failed. Please try again. DB Error: " . $conn->error;
                error_log("Registration Error: " . $conn->error . " SQL: " . $sql);
            }
            $stmt_insert->close();
        }
    }
}
// $conn->close(); // Moved to the end of the script
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Online Job Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #1D3557;
            --secondary-color: #457B9D;
            --accent-color: #A8DADC; 
            --light-color: #F1FAEE;
            --success-color: #1B998B; 
            --success-bg-color: #E7F9F5; 
            --danger-color: #E63946; 
            --danger-bg-color: #F8D7DA; 
            --input-border-color: #A8DADC; 
            --input-focus-border: #457B9D; 
            --input-focus-shadow: rgba(69, 123, 157, 0.25); 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 30px 20px; overflow-y: auto; }
        .register-container { background: #fff; padding: 35px 40px; width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); text-align: center; transition: transform 0.3s ease-in-out; }
        .register-container:hover { transform: translateY(-5px); }
        .register-header h2 { color: var(--primary-color); margin-bottom: 10px; font-size: 1.8em; font-weight: 600; }
        .register-header p { color: #6c757d; margin-bottom: 25px; font-size: 0.95em; }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em; display: flex; align-items: center; justify-content: center; gap: 8px; text-align: left; }
        .message.success { color: var(--success-color); background: var(--success-bg-color); border: 1px solid var(--success-color); }
        .message.error { color: var(--danger-color); background: var(--danger-bg-color); border: 1px solid var(--danger-color); }
        .message a { color: inherit; font-weight: 600; text-decoration: underline; }
        .message a:hover { opacity: 0.8; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #495057; font-size: 0.9em; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"], .form-group select { width: 100%; padding: 14px 18px; border: 1px solid var(--input-border-color); border-radius: 8px; font-size: 1em; transition: border-color 0.2s ease, box-shadow 0.2s ease; background-color: #fff; }
        .form-group select { background-color: var(--light-color); cursor: pointer; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--input-focus-border); box-shadow: 0 0 0 0.2rem var(--input-focus-shadow); }
        .btn-register { background: var(--primary-color); color: #fff; padding: 14px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 1.05em; font-weight: 500; width: 100%; transition: background-color 0.3s ease, transform 0.2s ease; margin-top: 10px; }
        .btn-register:hover { background: var(--secondary-color); transform: translateY(-2px); }
        .btn-register:active { transform: translateY(0); }
        .login-link { margin-top: 25px; font-size: 0.9em; color: #555; }
        .login-link a { color: var(--secondary-color); text-decoration: none; font-weight: 500; }
        .login-link a:hover { text-decoration: underline; color: var(--primary-color); }
        #employer_fields { display: none; }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-header">
        <h2>Create Your Account</h2>
        <p>Join our platform to find jobs or hire talent.</p>
    </div>

    <?php if (isset($success) && !empty($success)): ?>
        <div class='message success'><i class="fas fa-check-circle"></i> <span><?php echo $success; ?></span></div>
    <?php endif; ?>
    <?php if (isset($error) && !empty($error)): ?>
        <div class='message error'><i class="fas fa-exclamation-circle"></i> <span><?php echo $error; ?></span></div>
    <?php endif; ?>

    <form id="registerForm" method="POST" action="register.php" onsubmit="return validateForm()">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" placeholder="e.g., John Doe" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="form-group">
            <label for="password">Password (min. 6 characters)</label>
            <input type="password" id="password" name="password" placeholder="Create a strong password" required>
        </div>
        <div class="form-group">
            <label for="role">Select Your Role</label>
            <select name="role" id="role" onchange="toggleFields()" required>
                <option value="">-- Select Role --</option>
                <option value="jobseeker" <?php echo (isset($_POST['role']) && $_POST['role'] == 'jobseeker') ? 'selected' : ''; ?>>Job Seeker</option>
                <option value="employer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'employer') ? 'selected' : ''; ?>>Employer</option>
            </select>
        </div>
        <div id="employer_fields" style="<?php echo (isset($_POST['role']) && $_POST['role'] == 'employer') ? 'display: block;' : 'display: none;'; ?>">
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <p style="font-size: 0.9em; color: #555; margin-bottom: 15px;">Employer Information (Required):</p>
            <div class="form-group">
                <label for="business_name">Business Name</label>
                <input type="text" id="business_name" name="business_name" placeholder="Your Company LLC" value="<?php echo isset($_POST['business_name']) ? htmlspecialchars($_POST['business_name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="business_id">Business License Number</label>
                <input type="text" id="business_id" name="business_id" placeholder="e.g., 123456789" value="<?php echo isset($_POST['business_id']) ? htmlspecialchars($_POST['business_id']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" placeholder="e.g., +1-555-1234" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
            </div>
            <hr style="margin: 15px 0 20px 0; border: 0; border-top: 1px solid #eee;">
        </div>
        <button type="submit" class="btn-register">Register Account</button>
    </form>
    <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
</div>

<script>
    function toggleFields() {
        var roleSelect = document.getElementById("role");
        var employerFieldsDiv = document.getElementById("employer_fields");
        var businessNameInput = document.getElementById("business_name");
        var businessIdInput = document.getElementById("business_id");
        var contactNumberInput = document.getElementById("contact_number");
        if (roleSelect.value === "employer") {
            employerFieldsDiv.style.display = "block";
            businessNameInput.required = true; businessIdInput.required = true; contactNumberInput.required = true;
        } else {
            employerFieldsDiv.style.display = "none";
            businessNameInput.required = false; businessIdInput.required = false; contactNumberInput.required = false;
        }
    }
    document.addEventListener('DOMContentLoaded', function() { toggleFields(); });
    function validateForm() {
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const role = document.getElementById('role').value;
        if (!name || !email || !password || !role) { alert('Please fill in all required fields: Name, Email, Password, and Role.'); return false; }
        if (password.length < 6) { alert('Password must be at least 6 characters long.'); return false; }
        if (role === "employer") {
            const businessName = document.getElementById("business_name").value.trim();
            const businessId = document.getElementById("business_id").value.trim();
            const contactNumber = document.getElementById("contact_number").value.trim();
            if (!businessName || !businessId || !contactNumber) { alert('For employers, Business Name, Business ID, and Contact Number are required.'); return false; }
        }
        return true; 
    }
</script>

</body>
</html>
<?php
// Close connection if it was opened and is still active
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>