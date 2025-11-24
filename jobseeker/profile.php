<?php
session_start();
include '../includes/config.php'; // Ensure this path is correct

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jobseeker' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$jobseeker_id = $_SESSION['user_id'];

// Initial fetch of user data (will be used if no updates occur or for pre-filling)
$sql_initial_user = "SELECT name, email, resume, profile_picture, address, contact_number FROM users WHERE id = ?";
$stmt_initial_user = $conn->prepare($sql_initial_user);
if (!$stmt_initial_user) {
    error_log("Prepare failed (initial user fetch): " . $conn->error);
    // Critical error, can't proceed without user data
    die("Error loading user data. Please try again later.");
}
$stmt_initial_user->bind_param("i", $jobseeker_id);
$stmt_initial_user->execute();
$result_initial_user = $stmt_initial_user->get_result();
$user = $result_initial_user->fetch_assoc();
$stmt_initial_user->close();

if (!$user) {
    $_SESSION['error'] = "User not found. Please log in again."; // Use a general error session var
    header("Location: ../login.php");
    exit();
}

// Initialize page-level messages
$page_success_message = "";
$page_error_message = ""; // This will hold errors from pic/resume actions


// Define upload directory relative to this script's location
$base_upload_dir = "../uploads/"; // Physical path from this script
$profile_pic_dir_relative = "profile_pics/"; // Relative for DB and web path
$resume_dir_relative = "resumes/";     // Relative for DB and web path

$profile_pic_dir_absolute = rtrim($base_upload_dir, '/') . '/' . $profile_pic_dir_relative;
$resume_dir_absolute = rtrim($base_upload_dir, '/') . '/' . $resume_dir_relative;

// Ensure upload directories exist
if (!is_dir($profile_pic_dir_absolute)) {
    if (!mkdir($profile_pic_dir_absolute, 0775, true) && !is_dir($profile_pic_dir_absolute)) {
        // Set error message but don't exit yet, page might still be usable for other things
        $page_error_message = "Critical Error: Could not create profile picture directory. Please contact support.";
    }
}
if (!is_dir($resume_dir_absolute)) {
    if (!mkdir($resume_dir_absolute, 0775, true) && !is_dir($resume_dir_absolute)) {
        $page_error_message = (empty($page_error_message) ? "" : $page_error_message . " ") . "Critical Error: Could not create resume directory. Please contact support.";
    }
}


// --- Handle Profile Picture Upload ---
// This logic should run if the form in profile.php (not the modal) for profile picture is submitted
if (empty($page_error_message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
    $allowed_pic_types = ["jpg", "jpeg", "png", "gif"];
    $pic_file_name_original = basename($_FILES["profile_picture"]["name"]);
    $pic_file_ext = strtolower(pathinfo($pic_file_name_original, PATHINFO_EXTENSION));
    // Use only the relative filename for DB storage
    $new_pic_file_name_relative = "profile_" . $jobseeker_id . "_" . time() . "." . $pic_file_ext;
    $pic_upload_path_absolute = $profile_pic_dir_absolute . $new_pic_file_name_relative;

    if (in_array($pic_file_ext, $allowed_pic_types) && $_FILES["profile_picture"]["size"] <= 2 * 1024 * 1024) { // Max 2MB
        // Delete old picture if exists
        if (!empty($user['profile_picture']) && file_exists($profile_pic_dir_absolute . $user['profile_picture'])) {
            @unlink($profile_pic_dir_absolute . $user['profile_picture']);
        }
        // Move the new picture
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $pic_upload_path_absolute)) {
            $update_sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_pic_file_name_relative, $jobseeker_id);
            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Profile picture uploaded successfully."; // Use general session success
                header("Location: profile.php"); exit(); // Redirect to refresh
            } else { $page_error_message = "Database update failed for profile picture: " . $update_stmt->error; }
            $update_stmt->close();
        } else { $page_error_message = "Profile picture upload failed. Error: " . $_FILES["profile_picture"]["error"]; }
    } else { $page_error_message = "Invalid file type or file too large for profile picture. Allowed: JPG, JPEG, PNG, GIF (Max: 2MB)."; }
}

// --- Handle Profile Picture Removal ---
if (empty($page_error_message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_profile_picture"])) {
    if (!empty($user['profile_picture'])) {
        $pic_path_absolute = $profile_pic_dir_absolute . $user['profile_picture'];
        if (file_exists($pic_path_absolute)) { @unlink($pic_path_absolute); }
        
        $sql_remove_pic = "UPDATE users SET profile_picture = NULL WHERE id = ?";
        $stmt_remove_pic = $conn->prepare($sql_remove_pic);
        $stmt_remove_pic->bind_param("i", $jobseeker_id);
        if ($stmt_remove_pic->execute()) {
            $_SESSION['success'] = "Profile picture removed successfully.";
            header("Location: profile.php"); exit(); // Redirect to refresh
        } else { $page_error_message = "Failed to remove profile picture from database: " . $stmt_remove_pic->error; }
        $stmt_remove_pic->close();
    } else { $page_error_message = "No profile picture found to remove."; }
}

// --- Handle Resume Upload ---
if (empty($page_error_message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["resume"]) && $_FILES["resume"]["error"] == 0) {
    $allowed_resume_types = ["pdf", "doc", "docx"];
    $resume_file_name_original = basename($_FILES["resume"]["name"]);
    $resume_file_ext = strtolower(pathinfo($resume_file_name_original, PATHINFO_EXTENSION));
    $new_resume_file_name_relative = "resume_" . $jobseeker_id . "_" . time() . "." . $resume_file_ext;
    $resume_upload_path_absolute = $resume_dir_absolute . $new_resume_file_name_relative;

    if (in_array($resume_file_ext, $allowed_resume_types) && $_FILES["resume"]["size"] <= 2 * 1024 * 1024) { // Max 2MB
        if (!empty($user['resume']) && file_exists($resume_dir_absolute . $user['resume'])) {
            @unlink($resume_dir_absolute . $user['resume']);
        }
        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $resume_upload_path_absolute)) {
            $update_sql = "UPDATE users SET resume = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_resume_file_name_relative, $jobseeker_id);
            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Resume uploaded successfully.";
                header("Location: profile.php"); exit(); // Redirect to refresh
            } else { $page_error_message = "Database update failed for resume: " . $update_stmt->error; }
            $update_stmt->close();
        } else { $page_error_message = "Resume file upload failed. Error: " . $_FILES["resume"]["error"]; }
    } else { $page_error_message = "Invalid file type or file too large for resume. Allowed: PDF, DOC, DOCX (Max: 2MB)."; }
}

// --- Handle Resume Removal ---
if (empty($page_error_message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove_resume"])) {
    if (!empty($user['resume'])) {
        $resume_path_absolute = $resume_dir_absolute . $user['resume'];
        if (file_exists($resume_path_absolute)) { @unlink($resume_path_absolute); }

        $sql_remove_resume = "UPDATE users SET resume = NULL WHERE id = ?";
        $stmt_remove_resume = $conn->prepare($sql_remove_resume);
        $stmt_remove_resume->bind_param("i", $jobseeker_id);
        if ($stmt_remove_resume->execute()) {
            $_SESSION['success'] = "Resume removed successfully.";
            header("Location: profile.php"); exit(); // Redirect to refresh
        } else { $page_error_message = "Failed to remove resume from database: " . $stmt_remove_resume->error; }
        $stmt_remove_resume->close();
    } else { $page_error_message = "No resume found to remove."; }
}
// --- End File Handling ---


// Retrieve general session messages AFTER potential redirects from file handling
if (isset($_SESSION['success'])) {
    $page_success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
// If $page_error_message is already set by file handling, don't overwrite with a generic session error
if (empty($page_error_message) && isset($_SESSION['error'])) {
    $page_error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}


// Specific messages from edit profile modal submission (handled separately)
$edit_profile_success_message = "";
$edit_profile_error_message = "";
$form_data_edit_profile = [];

if (isset($_SESSION['edit_profile_success'])) {
    $edit_profile_success_message = $_SESSION['edit_profile_success'];
    unset($_SESSION['edit_profile_success']);
}
if (isset($_SESSION['edit_profile_error'])) {
    $edit_profile_error_message = $_SESSION['edit_profile_error'];
    unset($_SESSION['edit_profile_error']);
    if (isset($_SESSION['form_data_edit_profile'])) {
        $form_data_edit_profile = $_SESSION['form_data_edit_profile'];
        unset($_SESSION['form_data_edit_profile']);
    }
}

// Re-fetch user data if any update (profile text edit, pic, or resume) occurred and there was no error during THAT process
// This ensures the $user array is fresh for display.
$should_refresh_user = (!empty($page_success_message) && empty($page_error_message)) || 
                       (!empty($edit_profile_success_message) && empty($edit_profile_error_message));

if ($should_refresh_user) {
    $stmt_refresh_user = $conn->prepare("SELECT name, email, resume, profile_picture, address, contact_number FROM users WHERE id = ?");
    if ($stmt_refresh_user) {
        $stmt_refresh_user->bind_param("i", $jobseeker_id);
        $stmt_refresh_user->execute();
        $refreshed_user_result = $stmt_refresh_user->get_result();
        if ($refreshed_user_data = $refreshed_user_result->fetch_assoc()) {
            $user = $refreshed_user_data; // Overwrite $user with fresh data
        }
        $stmt_refresh_user->close();
    }
}


// Determine display paths for profile picture and resume
$web_uploads_path = "../uploads/"; // Path for browser to access files
$default_profile_pic_path = '../assets/images/default_avatar.png'; // Default if no pic
$profile_picture_to_display = $default_profile_pic_path;

if (!empty($user['profile_picture'])) {
    // Check if the actual file exists using absolute server path
    $potential_pic_server_path = $profile_pic_dir_absolute . $user['profile_picture'];
    if (file_exists($potential_pic_server_path)) {
        $profile_picture_to_display = rtrim($web_uploads_path, '/') . '/' . $profile_pic_dir_relative . htmlspecialchars($user['profile_picture']);
    } else {
        // File in DB but not on server, log this or handle as an issue
        error_log("Profile picture {$user['profile_picture']} for user {$jobseeker_id} not found at {$potential_pic_server_path}");
    }
}

$resume_web_path = null;
if(!empty($user['resume'])) {
    $potential_resume_server_path = $resume_dir_absolute . $user['resume'];
    if(file_exists($potential_resume_server_path)){
        $resume_web_path = rtrim($web_uploads_path, '/') . '/' . $resume_dir_relative . htmlspecialchars($user['resume']);
    } else {
        error_log("Resume {$user['resume']} for user {$jobseeker_id} not found at {$potential_resume_server_path}");
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ... (your head content remains the same) ... -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name'] ?? 'User'); ?>'s Profile - Job Portal</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ... (Your existing CSS from profile.php - ensure modal CSS is there too) ... */
         :root {
            --primary-color: #1D3557;
            --secondary-color: #457B9D;
            --accent-color: #A8DADC;
            --light-bg: #F1F6F9;
            --card-bg: #FFFFFF;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --green-accent: #28a745;
            --red-accent: #dc3545;
            --blue-accent: #007bff;
            --input-border-color: #ced4da;
            --input-focus-border: #457B9D; 
            --input-focus-shadow: rgba(69, 123, 157, 0.25); 
        }
        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; background-color: var(--light-bg); color: var(--text-dark); line-height: 1.7; }

        nav.top-nav { background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); padding: 15px 0; display: flex; justify-content: center; align-items: center; gap: 25px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 999; }
        nav.top-nav a { color: white; text-decoration: none; font-weight: 500; padding: 10px 18px; border-radius: 6px; transition: background-color 0.25s ease-out, transform 0.2s ease-out; display: inline-flex; align-items: center; }
        nav.top-nav a i { margin-right: 8px; font-size: 1.1em; }
        nav.top-nav a:hover, nav.top-nav a.active { background-color: rgba(255,255,255,0.15); transform: translateY(-2px); }

        .container { width: 90%; max-width: 900px; margin: 30px auto; }
        .page-header { text-align: center; margin-bottom: 30px; }
        .page-header h2 { color: var(--primary-color); font-size: 2.2em; font-weight: 600; }
        .page-header h2 i { margin-right: 12px; }

        .session-message { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; font-size: 0.95em; }
        .session-message.success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;}
        .session-message.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7;}

        .profile-layout { display: flex; flex-wrap: wrap; gap: 30px; }
        .profile-sidebar { flex: 0 0 280px; }
        .profile-main-content { flex: 1; min-width: 0; }

        .profile-card, .content-card { background-color: var(--card-bg); padding: 25px 30px; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.07); margin-bottom: 30px;}

        .profile-picture-section { text-align: center; }
        .profile-picture-container { width: 180px; height: 180px; border-radius: 50%; overflow: hidden; margin: 0 auto 20px auto; border: 5px solid var(--card-bg); box-shadow: 0 0 0 5px var(--secondary-color), 0 4px 15px rgba(0,0,0,0.15); }
        .profile-picture-container img { width: 100%; height: 100%; object-fit: cover; }
        .profile-name { font-size: 1.6em; font-weight: 600; color: var(--primary-color); margin-bottom: 5px; }
        .profile-email { font-size: 1em; color: var(--text-light); margin-bottom: 20px; }

        .profile-picture-actions { padding-top: 15px; }
        .profile-pic-upload-form div { margin-bottom: 10px; }
        .file-input-label { display: block; font-weight: 500; color: var(--text-dark); font-size: 0.9em; margin-bottom: 5px; }
        .styled-file-input { display: block; width: 100%; padding: 8px 10px; font-size: 0.9em; border: 1px solid var(--input-border-color); border-radius: 6px; background-color: #f8f9fa; cursor: pointer; }
        .styled-file-input::file-selector-button { margin-right: 10px; border: none; background: var(--secondary-color); padding: 8px 12px; border-radius: 4px; color: #fff; cursor: pointer; transition: background-color .15s ease-in-out; }
        .styled-file-input::file-selector-button:hover { background: var(--primary-color); }
        .profile-picture-actions .btn { width: 100%; margin-bottom: 8px; }
        .profile-picture-actions .btn:last-child { margin-bottom: 0; }


        .details-section h3, .resume-section h3 { color: var(--primary-color); font-size: 1.4em; margin-top: 0; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; font-weight: 600; }
        .details-section h3 i, .resume-section h3 i { margin-right: 10px; color: var(--secondary-color); }
        .info-item { margin-bottom: 18px; font-size: 1em; display: flex; }
        .info-item strong { color: var(--text-dark); font-weight: 600; min-width: 120px; flex-shrink: 0; }
        .info-item span { color: var(--text-light); word-break: break-all; }
        .edit-profile-link-container { margin-top: 25px; text-align: right; }

        .resume-status { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; font-size: 1em;}
        .resume-status i { font-size: 1.2em; }
        .resume-status .success { color: var(--green-accent); }
        .resume-status .error { color: var(--red-accent); }
        .resume-actions { margin-bottom: 20px; }
        .resume-actions form, .resume-actions a { margin-right: 10px; margin-bottom: 10px; display: inline-block; }
        .resume-upload-form input[type="file"] { display: block; margin-bottom: 10px; font-size: 0.9em; }
        .upload-note { font-size:0.85em; color:var(--text-light); margin-top:5px; }

        .btn { padding: 9px 16px; border: none; cursor: pointer; border-radius: 6px; font-size: 0.9em; font-weight: 500; transition: background-color 0.3s, transform 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn i { margin-right: 7px; }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background-color: var(--blue-accent); color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: var(--secondary-color); color: white; }
        .btn-secondary:hover { background-color: var(--primary-color); }
        .btn-danger { background-color: var(--red-accent); color: white; }
        .btn-danger:hover { background-color: #b02a37; }
        .btn-success { background-color: var(--green-accent); color: white; }
        .btn-success:hover { background-color: #1e7e34; }

        footer.page-footer { text-align: center; padding: 20px 0; margin-top: 40px; background-color: var(--primary-color); color: #bdc3c7; font-size: 0.9em; }
        footer.page-footer a { color: var(--accent-color); text-decoration: none; }
        footer.page-footer a:hover { text-decoration: underline; }


        /* --- MODAL STYLES --- */
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.5); 
            padding-top: 50px;
        }
        .modal-content {
            background-color: var(--card-bg); margin: 5% auto; padding: 25px 30px;
            border: 1px solid #ddd; width: 90%; max-width: 600px; 
            border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-header {
            padding-bottom: 15px; border-bottom: 1px solid #eee; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; color: var(--primary-color); font-size: 1.5em; }
        .modal-header .close-button {
            color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;
            line-height: 1; padding: 0 5px;
        }
        .modal-header .close-button:hover, .modal-header .close-button:focus {
            color: var(--text-dark); text-decoration: none;
        }
        .modal-body { padding-bottom: 10px; }
        .modal-body .form-group { margin-bottom: 18px; }
        .modal-body .form-group label {
            font-size: 0.9em; font-weight: 600; color: var(--text-dark);
            margin-bottom: 6px; display: flex; align-items: center;
        }
        .modal-body .form-group label i { margin-right: 7px; color: var(--secondary-color); }

        .modal-body .form-group input[type="text"],
        .modal-body .form-group input[type="email"],
        .modal-body .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--input-border-color);
            border-radius: 6px;
            font-size: 0.95em;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        .modal-body .form-group textarea { min-height: 80px; resize: vertical; }

        .modal-body .form-group input[type="text"]:focus,
        .modal-body .form-group input[type="email"]:focus,
        .modal-body .form-group textarea:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 0.2rem var(--input-focus-shadow);
        }

        .modal-footer {
            padding-top: 15px; border-top: 1px solid #eee; margin-top: 20px;
            text-align: right;
        }
        .modal-footer .btn { margin-left: 10px; }
        .btn-modal-cancel { background-color: #6c757d; color: white; }
        .btn-modal-cancel:hover { background-color: #545b62; }
        .btn-modal-save { background-color: var(--green-accent); color: white; }
        .btn-modal-save:hover { background-color: #1e7e34; }
    </style>
</head>
<body>
    <!-- ... (Navbar) ... -->
    <nav class="top-nav">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="profile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-id-badge"></i> My Profile Dashboard</h2>
        </div>

        <!-- Display page-level messages (for pic/resume upload, and general errors) -->
        <?php if (!empty($page_success_message)) : ?>
            <p class="session-message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($page_success_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($page_error_message)) : ?>
            <p class="session-message error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($page_error_message); ?></p>
        <?php endif; ?>
        
        <!-- Display messages specifically from edit profile modal submission -->
        <?php if (!empty($edit_profile_success_message)) : ?>
            <p class="session-message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($edit_profile_success_message); ?></p>
        <?php endif; ?>
        <!-- Error from edit profile modal is handled inside the modal or by JS to reopen it -->


        <div class="profile-layout">
            <aside class="profile-sidebar">
                 <div class="profile-card profile-picture-section">
                    <div class="profile-picture-container">
                        <img src="<?php echo htmlspecialchars($profile_picture_to_display); ?>" alt="Profile Picture">
                    </div>
                    <h3 class="profile-name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h3>
                    <p class="profile-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    <div class="profile-picture-actions">
                        <!-- Profile Picture Upload Form -->
                        <form action="profile.php" method="POST" enctype="multipart/form-data" class="profile-pic-upload-form">
                            <div>
                                <label for="profile_pic_upload_input" class="file-input-label">Change profile photo:</label>
                                <input type="file" name="profile_picture" id="profile_pic_upload_input" class="styled-file-input" accept="image/jpeg,image/png,image/gif">
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload"></i> Upload New Photo
                            </button>
                        </form>
                        <!-- Remove Profile Picture Form -->
                        <?php if (!empty($user['profile_picture']) && $profile_picture_to_display !== $default_profile_pic_path): ?>
                        <form action="profile.php" method="POST" style="margin-top: 8px;">
                            <button type="submit" name="remove_profile_picture" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Remove Current Photo
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <main class="profile-main-content">
                <section class="content-card details-section">
                    <h3><i class="fas fa-info-circle"></i> Account Details</h3>
                    <div class="info-item">
                        <strong>Full Name:</strong> <span><?php echo htmlspecialchars($user['name'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Email:</strong> <span><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Contact Number:</strong> 
                        <span><?php echo !empty($user['contact_number']) ? htmlspecialchars($user['contact_number']) : '<em>Not provided</em>'; ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Address:</strong> 
                        <span><?php echo !empty($user['address']) ? nl2br(htmlspecialchars($user['address'])) : '<em>Not provided</em>'; ?></span>
                    </div>
                    <div class="edit-profile-link-container">
                        <button type="button" id="openEditProfileModalBtn" class="btn btn-primary"><i class="fas fa-user-edit"></i> Edit Profile</button>
                    </div>
                </section>

                <section class="content-card resume-section">
                    <h3><i class="fas fa-file-alt"></i> My Resume</h3>
                    <div class="resume-status">
                        <?php if (!empty($user['resume']) && $resume_web_path) : ?>
                            <i class="fas fa-check-circle success"></i>
                            <span>Current resume: <strong><?php echo htmlspecialchars(basename($user['resume'])); ?></strong></span>
                        <?php else : ?>
                            <i class="fas fa-times-circle error"></i>
                            <span>No resume uploaded yet.</span>
                        <?php endif; ?>
                    </div>
                    <div class="resume-actions">
                        <?php if (!empty($user['resume']) && $resume_web_path) : ?>
                            <a href="<?php echo htmlspecialchars($resume_web_path); ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Resume
                            </a>
                            <!-- Remove Resume Form -->
                            <form action="profile.php" method="POST" style="display: inline-block;">
                                <button type="submit" name="remove_resume" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> Remove Resume
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <!-- Resume Upload Form -->
                    <form action="profile.php" method="POST" enctype="multipart/form-data" class="resume-upload-form" style="margin-top:15px;">
                        <label for="resume_file_upload" style="font-weight:500; display:block; margin-bottom:8px;">
                            <?php echo (!empty($user['resume']) && $resume_web_path) ? 'Upload New Resume (Replaces Current)' : 'Upload Your Resume'; ?>:
                        </label>
                        <input type="file" id="resume_file_upload" name="resume" class="styled-file-input" accept=".pdf,.doc,.docx">
                        <button type="submit" class="btn btn-success" style="margin-top:10px;">
                            <i class="fas fa-upload"></i> <?php echo (!empty($user['resume']) && $resume_web_path) ? 'Upload & Replace' : 'Upload Resume'; ?>
                        </button>
                        <p class="upload-note">Allowed: PDF, DOC, DOCX (Max: 2MB)</p>
                    </form>
                </section>
            </main>
        </div>
    </div>

    <!-- THE EDIT PROFILE MODAL (Content remains the same as your last version) -->
    <div id="editProfileModal" class="modal">
        <!-- ... (Modal HTML from your last version) ... -->
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit Your Profile</h3>
                <span class="close-button">×</span>
            </div>
            <div class="modal-body">
                <?php if (!empty($edit_profile_error_message) && !empty($form_data_edit_profile)) : ?>
                    <p class="session-message error" style="margin-bottom: 15px; text-align:left;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($edit_profile_error_message); ?>
                    </p>
                <?php endif; ?>

                <form action="edit_profile.php" method="post" id="editProfileFormInModal">
                    <div class="form-group">
                        <label for="modal_name"><i class="fas fa-user"></i> Full Name:</label>
                        <input type="text" name="name" id="modal_name" 
                               value="<?php echo htmlspecialchars($form_data_edit_profile['name'] ?? $user['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_email"><i class="fas fa-envelope"></i> Email Address:</label>
                        <input type="email" name="email" id="modal_email" 
                               value="<?php echo htmlspecialchars($form_data_edit_profile['email'] ?? $user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_contact_number"><i class="fas fa-phone"></i> Contact Number:</label>
                        <input type="text" name="contact_number" id="modal_contact_number" 
                               value="<?php echo htmlspecialchars($form_data_edit_profile['contact_number'] ?? $user['contact_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="modal_address"><i class="fas fa-map-marker-alt"></i> Address:</label>
                        <textarea name="address" id="modal_address" rows="3"><?php echo htmlspecialchars($form_data_edit_profile['address'] ?? $user['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-modal-cancel" id="cancelEditProfileModalBtn">
                            <i class="fas fa-times-circle"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-modal-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ... (JavaScript for modal remains the same) ... -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('editProfileModal');
        const openModalBtn = document.getElementById('openEditProfileModalBtn');
        const closeModalSpan = modal.querySelector('.close-button');
        const cancelModalBtn = document.getElementById('cancelEditProfileModalBtn'); 
        
        function openModal() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; 
        }

        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; 
        }

        if (openModalBtn) {
            openModalBtn.onclick = function() {
                openModal();
            }
        }
        if (closeModalSpan) {
            closeModalSpan.onclick = function() {
                closeModal();
            }
        }
        if (cancelModalBtn) {
            cancelModalBtn.onclick = function() {
                closeModal();
            }
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape" && modal.style.display === 'block') {
                closeModal();
            }
        });

        <?php if (!empty($edit_profile_error_message) && !empty($form_data_edit_profile)): ?>
        openModal(); // Re-open modal if there was an error from edit_profile.php
        <?php endif; ?>
    });
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>