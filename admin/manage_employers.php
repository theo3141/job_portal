<?php
session_start(); // Ensure session is started for admin check
include '../includes/config.php'; // Assuming this sets up $pdo or $conn

// Redirect if not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// DATABASE CONNECTION (using your PDO setup)
$host = 'localhost';
$db = 'job_portal';
$user_db = 'root'; // Renamed to avoid conflict with session $user
$pass_db = '';   // Renamed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user_db, $pass_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a real app, log this error and show a user-friendly message
    die("Database connection failed: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

// APPROVE EMPLOYER
if (isset($_GET['approve_id'])) {
    $approveId = filter_input(INPUT_GET, 'approve_id', FILTER_VALIDATE_INT);
    if ($approveId) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'employer' AND is_approved = 0");
            $stmt->execute([$approveId]);
            if ($stmt->rowCount() > 0) {
                $success_message = "Employer (ID: $approveId) approved successfully!";
            } else {
                $error_message = "Employer not found, already approved, or not an employer.";
            }
        } catch (PDOException $e) {
            $error_message = "Error approving employer: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid employer ID for approval.";
    }
     // Redirect back to the same page without the GET parameter to avoid re-processing on refresh
    header("Location: manage_employers.php" . ($success_message ? "?success=1" : ($error_message ? "?error=1" : "")));
    exit();
}

// Retrieve messages from redirect
if (isset($_GET['success'])) $success_message = "Employer approved successfully!"; // Generic message after redirect
if (isset($_GET['error'])) $error_message = "Could not process approval. Please check logs or try again.";


// --- SEARCH PARAMETERS ---
$search_term_employer = $_GET['search_term_employer'] ?? '';

// --- SORTING LOGIC ---
$sort_column_param = $_GET['sort'] ?? 'created_at'; 
$sort_order_param = $_GET['order'] ?? 'DESC';      
$allowed_sort_columns = [
    'name' => 'name', 
    'email' => 'email',
    'business_name' => 'business_name',
    'created_at' => 'created_at'
    // 'business_id' and 'contact_number' can be added if desired
];
if (!array_key_exists($sort_column_param, $allowed_sort_columns)) {
    $db_sort_column = 'created_at'; $sort_column_param = 'created_at'; 
} else { $db_sort_column = $allowed_sort_columns[$sort_column_param]; }
$sort_order_param = strtoupper($sort_order_param);
if ($sort_order_param !== 'ASC' && $sort_order_param !== 'DESC') { $sort_order_param = 'DESC'; }
// --- END SORTING LOGIC ---


// FETCH UNAPPROVED EMPLOYERS
$sql_base = "SELECT id, name, email, business_name, business_id, contact_number, created_at 
             FROM users 
             WHERE role = 'employer' AND is_approved = 0";
$sql_conditions = [];
$sql_params_values = [];

if (!empty($search_term_employer)) {
    $sql_conditions[] = "(name LIKE :search_term OR business_name LIKE :search_term OR email LIKE :search_term)";
    $sql_params_values[':search_term'] = "%" . $search_term_employer . "%";
}

$sql_where_clause = "";
if (!empty($sql_conditions)) {
    // If there are search conditions, they are primary. The role and is_approved are base.
    $sql_where_clause = " AND (" . implode(" AND ", $sql_conditions) . ")";
}

$sql_order_by = " ORDER BY " . $db_sort_column . " " . $sort_order_param;
$final_sql = $sql_base . $sql_where_clause . $sql_order_by;

$stmt = $pdo->prepare($final_sql);
$stmt->execute($sql_params_values); // Execute with named placeholders if any
$employers = $stmt->fetchAll();


function get_admin_employer_sort_link($column_param, $display_text, $current_sort_column_param, $current_sort_order_param, $current_search_params = []) {
    $link_order = ($current_sort_column_param == $column_param && $current_sort_order_param == 'ASC') ? 'DESC' : 'ASC';
    $arrow = '';
    global $allowed_sort_columns; 
    if (array_key_exists($column_param, $allowed_sort_columns)){
        if ($current_sort_column_param == $column_param) {
            $arrow = ($current_sort_order_param == 'ASC') ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
        } else {
            $arrow = ' <i class="fas fa-sort" style="opacity:0.4;"></i>';
        }
    }
    $search_query_string = http_build_query($current_search_params);
    $search_query_string = $search_query_string ? '&' . $search_query_string : '';
    return "<a href=\"?sort={$column_param}&order={$link_order}{$search_query_string}\" title=\"Sort by {$display_text}\">{$display_text}{$arrow}</a>";
}
$current_search_params_for_links = [];
if (!empty($search_term_employer)) $current_search_params_for_links['search_term_employer'] = $search_term_employer;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employers - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <style>
        :root { /* Palette from your Admin Dashboard */
            --primary-color: #1D3557; --secondary-color: #457B9D; --accent-color: #A8DADC;
            --light-bg-color: #F8F9FA; --card-bg-color: #FFFFFF; --text-color: #343A40;
            --light-text-color: #F1FAEE; --box-bg-color: #F1FAEE; --theme-primary-dark: #004D40;
            --border-light: #dee2e6; --border-medium: #ced4da; --border-table-row: #e9ecef;
            --shadow-color: rgba(0, 0, 0, 0.07); 
            --button-approve-bg: #28a745; --button-approve-hover-bg: #218838;
            --alert-success-bg: #d1e7dd; --alert-success-text: #0f5132; --alert-success-border: #badbcc;
            --alert-error-bg: #f8d7da; --alert-error-text: #842029; --alert-error-border: #f5c2c7;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--light-bg-color); color: var(--text-color); line-height: 1.6; display: flex; flex-direction: column; min-height: 100vh; }

        .admin-header { background-color: var(--primary-color); color: var(--light-text-color); padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12); position: sticky; top: 0; z-index: 1001; }
        .admin-header .header-content { width: 95%; max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .admin-header .logo-title { font-size: 1.6em; font-weight: 600; margin: 0; color: var(--light-text-color); text-decoration: none; }
        .admin-header .logo-title i { margin-right: 10px; color: var(--accent-color); }
        .admin-header nav ul { list-style: none; margin: 0; padding: 0; display: flex; gap: 10px;}
        .admin-header nav ul li a { color: var(--light-text-color); text-decoration: none; font-size: 0.95em; font-weight: 500; padding: 9px 16px; border-radius: 5px; transition: background-color 0.3s, color 0.3s; display: inline-flex; align-items: center; }
        .admin-header nav ul li a i { margin-right: 7px; font-size: 0.9em; }
        .admin-header nav ul li a:hover { background-color: var(--secondary-color); }
        .admin-header nav ul li a.active { background-color: var(--secondary-color); font-weight: 600;}

        .main-content-wrapper { flex-grow: 1; width: 100%; padding-bottom: 30px; }
        .admin-container { margin: 25px auto; max-width: 1400px; padding: 25px 30px; background-color: var(--card-bg-color); border-radius: 10px; box-shadow: 0 5px 15px var(--shadow-color); }
        .page-header { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-light); }
        .page-header .page-title-icon { font-size: 1.8em; color: var(--primary-color); margin-right: 15px; padding: 10px; background-color: var(--box-bg-color); border-radius: 8px; }
        .page-header h2.page-main-title { color: var(--primary-color); font-size: 1.7em; font-weight: 600; margin: 0; }
        
        /* Search Bar Styling */
        .admin-search-bar {
            background-color: var(--box-bg-color); padding: 18px 22px; margin-bottom: 25px;
            border-radius: 8px; border: 1px solid var(--border-light);
            display: flex; flex-wrap: wrap; gap: 18px; align-items: flex-end;
        }
        .admin-search-bar .form-group { display: flex; flex-direction: column; flex-grow: 1; min-width: 250px; }
        .admin-search-bar label { font-size: 0.85em; font-weight: 500; color: var(--text-color); margin-bottom: 6px; }
        .admin-search-bar input[type="text"] { padding: 10px 12px; border: 1px solid var(--border-medium); border-radius: 5px; font-size: 0.9em; background-color: var(--card-bg-color); color: var(--text-color); }
        .admin-search-bar input[type="text"]::placeholder { color: #999; }
        .admin-search-bar input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(29, 53, 87, 0.15); }
        .admin-search-bar .search-actions { display: flex; gap: 10px; align-items: flex-end; }
        .admin-search-bar .btn { font-weight: 500; padding: 10px 18px; font-size: 0.9em; }
        .admin-search-bar .btn-search-employers { background-color: var(--primary-color); color: var(--light-text-color);}
        .admin-search-bar .btn-search-employers:hover { background-color: var(--secondary-color); }
        .admin-search-bar .btn-clear-employers-search { background-color: var(--text-secondary); color: var(--light-text-color); }
        .admin-search-bar .btn-clear-employers-search:hover { background-color: #5a6268; }

        .admin-messages { margin-bottom: 20px; }
        .admin-message { padding: 12px 18px; border-radius: 6px; text-align: center; font-weight: 500; font-size: 0.95em; }
        .admin-message.success { background-color: var(--alert-success-bg); color: var(--alert-success-text); border: 1px solid var(--alert-success-border); }
        .admin-message.error { background-color: var(--alert-error-bg); color: var(--alert-error-text); border: 1px solid var(--alert-error-border); }

        .table-responsive-wrapper { overflow-x: auto; border: 1px solid var(--border-light); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; border-bottom: 1px solid var(--border-table-row); text-align: left; vertical-align: middle; font-size: 0.9em; }
        td { color: var(--text-secondary); }
        td.col-name strong, td.col-business strong { color: var(--text-color); font-weight: 500; }

        /* Column Widths */
        th.col-name, td.col-name { width: 18%; }
        th.col-email, td.col-email { width: 20%; }
        th.col-business, td.col-business { width: 20%; }
        th.col-license, td.col-license { width: 12%; text-align:center; }
        th.col-contact, td.col-contact { width: 12%; text-align:center; }
        th.col-registered, td.col-registered { width: 10%; text-align: center; }
        th.col-action, td.col-action { width: 8%; text-align: center; }

        thead th { background-color: var(--light-bg-color); color: var(--text-color); font-weight: 600; font-size: 0.85em; letter-spacing: 0.5px; text-transform: uppercase; border-bottom: 2px solid var(--border-medium); white-space: nowrap; position: sticky; top: 0; z-index: 1;}
        th a { color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; }
        th a:hover { color: var(--primary-color); }
        th .fas { font-size: 0.9em; margin-left: 6px; opacity: 0.7; }
        th .fa-sort { opacity: 0.4; }

        tbody tr:nth-child(even) { background-color: var(--box-bg-color); }
        tbody tr:hover { background-color: #e9f5ff; } 

        .btn { padding: 7px 12px; border: none; cursor: pointer; border-radius: 5px; font-size: 0.82em; font-weight: 500; transition: background-color 0.2s, transform 0.1s, box-shadow 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; margin: 0; color: var(--light-text-color); }
        .btn i { margin-right: 6px; font-size: 0.9em; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-approve { background-color: var(--button-approve-bg); }
        .btn-approve:hover { background-color: var(--button-approve-hover-bg); }
        
        .back-link-container { margin-top: 30px; text-align: left; }
        .back-link { color: var(--primary-color); text-decoration: none; font-weight: 500; padding: 10px 20px; border-radius: 6px; border: 1px solid var(--primary-color); transition: background-color 0.2s, color 0.2s; display: inline-flex; align-items: center; }
        .back-link i { margin-right: 8px; }
        .back-link:hover { background-color: var(--primary-color); color: var(--light-text-color); }

        .empty-state { text-align: center; padding: 40px 20px; font-size: 1.05em; color: var(--text-secondary); border: 1px dashed var(--border-light); border-radius: 6px; margin-top: 20px;}
        .empty-state i { font-size: 2.2em; display: block; margin-bottom: 15px; color: var(--secondary-color); }
    </style>
</head>
<body>

    <header class="admin-header">
        <div class="header-content">
            <a href="dashboard.php" class="logo-title"><i class="fas fa-user-shield"></i> Admin Panel</a>
        </div>
    </header>

    <div class="main-content-wrapper">
        <div class="admin-container">
            <div class="page-header">
                <span class="page-title-icon"><i class="fas fa-user-tie"></i></span>
                <h2 class="page-main-title">Pending Employer Approvals</h2>
            </div>

            <div class="admin-messages">
                <?php if ($success_message): ?>
                    <div class="admin-message success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="admin-message error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Search Bar for Employers -->
            <div class="admin-search-bar">
                <form action="manage_employers.php" method="GET" style="display: contents;">
                    <div class="form-group">
                        <label for="search_term_employer">Search Employer (Name, Business, Email):</label>
                        <input type="text" name="search_term_employer" id="search_term_employer" value="<?php echo htmlspecialchars($search_term_employer); ?>" placeholder="Enter name, business, or email...">
                    </div>
                     <!-- Hidden fields to preserve sort order when searching -->
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column_param); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order_param); ?>">
                    <div class="search-actions">
                        <button type="submit" class="btn btn-search-employers"><i class="fas fa-search"></i> Search</button>
                        <a href="manage_employers.php?sort=<?php echo htmlspecialchars($sort_column_param); ?>&order=<?php echo htmlspecialchars($sort_order_param); ?>" class="btn btn-clear-employers-search"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </form>
            </div>


            <div class="table-responsive-wrapper">
                <table>
                    <thead>
                        <tr>
                            <!-- ID Column Hidden -->
                            <th class="col-name"><?php echo get_admin_employer_sort_link('name', 'Contact Name', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-email"><?php echo get_admin_employer_sort_link('email', 'Email', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-business"><?php echo get_admin_employer_sort_link('business_name', 'Business Name', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-license">License #</th>
                            <th class="col-contact">Contact #</th>
                            <th class="col-registered"><?php echo get_admin_employer_sort_link('created_at', 'Registered', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($employers) > 0): ?>
                        <?php foreach ($employers as $employer): ?>
                            <tr>
                                <!-- ID data cell removed -->
                                <td class="col-name"><strong><?= htmlspecialchars($employer['name']) ?></strong></td>
                                <td class="col-email"><?= htmlspecialchars($employer['email']) ?></td>
                                <td class="col-business"><strong><?= htmlspecialchars($employer['business_name'] ?? 'N/A') ?></strong></td>
                                <td class="col-license"><?= htmlspecialchars($employer['business_id'] ?? 'N/A') ?></td>
                                <td class="col-contact"><?= htmlspecialchars($employer['contact_number'] ?? 'N/A') ?></td>
                                <td class="col-registered"><?= htmlspecialchars(date("d M Y, H:i", strtotime($employer['created_at']))) ?></td>
                                <td class="col-action">
                                    <a href="manage_employers.php?approve_id=<?= $employer['id'] ?>" class="btn btn-approve" onclick="return confirm('Are you sure you want to approve this employer?');">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php
                            $colspan_count = 6; // Name, Email, Business, License, Contact, Registered, Action (ID is hidden)
                        ?>
                        <tr>
                            <td colspan="<?= $colspan_count ?>" class="empty-state">
                                <i class="fas fa-user-check"></i>
                                <?php if (!empty($search_term_employer)): ?>
                                    No employers found matching your search criteria. <a href="manage_employers.php?sort=<?php echo htmlspecialchars($sort_column_param);?>&order=<?php echo htmlspecialchars($sort_order_param);?>">Clear search</a>.
                                <?php else: ?>
                                    No employers are currently pending approval.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="back-link-container">
                <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Footer is removed -->
    <script>
        // If you need specific JS for this page, add it here.
        // The deleteUser JS is not needed here as approval is done via GET link with confirmation.
        // If you add AJAX for approval, then JS would be needed.
    </script>
</body>
</html>
<?php
// No $conn->close() here as $pdo is used and it closes automatically or can be set to null.
$pdo = null; // Explicitly close PDO connection
?>