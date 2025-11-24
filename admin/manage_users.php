<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
$admin_name = $_SESSION['user_name'] ?? 'Admin';

if (!$conn) {
    die("Database connection failed. Check includes/config.php");
}

// --- SEARCH PARAMETERS ---
$search_term = $_GET['search_term'] ?? '';
$search_role = $_GET['search_role'] ?? '';
$search_reg_date_from = $_GET['search_reg_date_from'] ?? ''; // ADDED
$search_reg_date_to = $_GET['search_reg_date_to'] ?? '';   // ADDED

// --- SORTING LOGIC ---
$sort_column_param = $_GET['sort'] ?? 'created_at'; 
$sort_order_param = $_GET['order'] ?? 'DESC';      
$allowed_sort_columns = [
    'name' => 'name', 'email' => 'email',
    'role' => 'role', 'created_at' => 'created_at'
];
if (!array_key_exists($sort_column_param, $allowed_sort_columns)) {
    $db_sort_column = 'created_at'; $sort_column_param = 'created_at'; 
} else { $db_sort_column = $allowed_sort_columns[$sort_column_param]; }
$sort_order_param = strtoupper($sort_order_param);
if ($sort_order_param !== 'ASC' && $sort_order_param !== 'DESC') { $sort_order_param = 'DESC'; }
// --- END SORTING LOGIC ---

// --- BUILD SQL QUERY ---
$sql_base = "SELECT id, name, email, role, created_at FROM users WHERE role != 'admin'";
$sql_conditions = [];
$sql_params_values = []; 
$sql_params_types = "";   

if (!empty($search_term)) {
    $sql_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $sql_params_types .= "ss";
    $like_search_term = "%" . $search_term . "%";
    $sql_params_values[] = $like_search_term;
    $sql_params_values[] = $like_search_term;
}
if (!empty($search_role)) {
    $sql_conditions[] = "role = ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $search_role;
}
// ADDED: Date range search for registration date
if (!empty($search_reg_date_from)) {
    $sql_conditions[] = "DATE(created_at) >= ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $search_reg_date_from;
}
if (!empty($search_reg_date_to)) {
    $sql_conditions[] = "DATE(created_at) <= ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $search_reg_date_to;
}


$sql_where_clause = "";
if (!empty($sql_conditions)) {
    $sql_where_clause = " AND (" . implode(" AND ", $sql_conditions) . ")";
}

$sql_order_by = " ORDER BY " . $db_sort_column . " " . $sort_order_param;
$sql = $sql_base . $sql_where_clause . $sql_order_by;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error . " | SQL: " . $sql);
}

if (!empty($sql_params_types)) {
    $stmt->bind_param($sql_params_types, ...$sql_params_values);
}

$stmt->execute();
$result = $stmt->get_result();
// --- END BUILD SQL QUERY ---

function get_admin_sort_link($column_param, $display_text, $current_sort_column_param, $current_sort_order_param, $current_search_params = []) {
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
if (!empty($search_term)) $current_search_params_for_links['search_term'] = $search_term;
if (!empty($search_role)) $current_search_params_for_links['search_role'] = $search_role;
if (!empty($search_reg_date_from)) $current_search_params_for_links['search_reg_date_from'] = $search_reg_date_from; // ADDED
if (!empty($search_reg_date_to)) $current_search_params_for_links['search_reg_date_to'] = $search_reg_date_to;     // ADDED

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <style>
        :root { /* Using your Admin Dashboard's color palette */
            --primary-color: #1D3557; --secondary-color: #457B9D; --accent-color: #A8DADC;
            --light-bg-color: #F8F9FA; --card-bg-color: #FFFFFF; --text-color: #343A40;
            --light-text-color: #F1FAEE; --box-bg-color: #F1FAEE; --theme-primary-dark: #004D40;
            --border-light: #dee2e6; --border-medium: #ced4da; --border-table-row: #e9ecef;
            --shadow-color: rgba(0, 0, 0, 0.07); --button-delete-bg: #e74c3c; 
            --button-delete-hover-bg: #c0392b;
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

        .main-content-wrapper { flex-grow: 1; width: 100%; padding-bottom: 30px; /* Space for content above potential implicit footer */ }
        .admin-container { margin: 25px auto; max-width: 1300px; padding: 25px 30px; background-color: var(--card-bg-color); border-radius: 10px; box-shadow: 0 5px 15px var(--shadow-color); }
        .page-header { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-light); }
        .page-header .page-title-icon { font-size: 1.8em; color: var(--primary-color); margin-right: 15px; padding: 10px; background-color: var(--box-bg-color); border-radius: 8px; }
        .page-header h2.page-main-title { color: var(--primary-color); font-size: 1.7em; font-weight: 600; margin: 0; }

        /* Search Bar Styling */
        .admin-search-bar {
            background-color: var(--box-bg-color); padding: 18px 22px; margin-bottom: 25px;
            border-radius: 8px; border: 1px solid var(--border-light);
            display: grid; /* Changed to grid for better alignment */
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Responsive columns */
            gap: 18px; align-items: flex-end;
        }
        .admin-search-bar .form-group { display: flex; flex-direction: column; }
        .admin-search-bar label { font-size: 0.85em; font-weight: 500; color: var(--text-color); margin-bottom: 6px; }
        .admin-search-bar input[type="text"],
        .admin-search-bar input[type="date"],
        .admin-search-bar select {
            padding: 10px 12px; border: 1px solid var(--border-medium); border-radius: 5px;
            font-size: 0.9em; background-color: var(--card-bg-color); color: var(--text-color);
        }
        .admin-search-bar input[type="text"]::placeholder { color: #999; }
        .admin-search-bar input:focus, .admin-search-bar select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(29, 53, 87, 0.15); }
        .admin-search-bar .search-actions { 
            display: flex; gap: 10px; align-items: flex-end;
            grid-column: span 2; /* Make actions span more columns if needed, or adjust grid-template-columns */
            justify-self: flex-start; /* Align actions to the start of their grid area */
        }
         @media (min-width: 992px) { /* Adjust breakpoint as needed */
            .admin-search-bar { grid-template-columns: 2fr 1fr 1fr 1fr auto; } /* More specific columns for wider screens */
            .admin-search-bar .search-actions { grid-column: auto; justify-self: flex-end; }
        }

        .admin-search-bar .btn { font-weight: 500; padding: 10px 18px; font-size: 0.9em; }
        .admin-search-bar .btn-search-users { background-color: var(--primary-color); }
        .admin-search-bar .btn-search-users:hover { background-color: var(--secondary-color); }
        .admin-search-bar .btn-clear-users-search { background-color: var(--text-secondary); color: var(--light-text-color); }
        .admin-search-bar .btn-clear-users-search:hover { background-color: #5a6268; }


        #result-msg { padding: 12px 18px; margin-bottom: 20px; border-radius: 6px; text-align: center; font-weight: 500; display: none; font-size: 0.95em; }
        #result-msg.success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;}
        #result-msg.error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7;}

        .table-responsive-wrapper { overflow-x: auto; border: 1px solid var(--border-light); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; border-bottom: 1px solid var(--border-table-row); text-align: left; vertical-align: middle; font-size: 0.9em; }
        td { color: var(--text-secondary); }
        td.col-name strong { color: var(--text-color); font-weight: 500; }

        th.col-name, td.col-name { width: 28%; } /* Adjusted widths */
        th.col-email, td.col-email { width: 32%; }
        th.col-role, td.col-role { width: 12%; text-align: center; }
        th.col-created, td.col-created { width: 18%; text-align: center; }
        th.col-action, td.col-action { width: 10%; text-align: center; }

        thead th { background-color: var(--light-bg-color); color: var(--text-color); font-weight: 600; font-size: 0.85em; letter-spacing: 0.5px; text-transform: uppercase; border-bottom: 2px solid var(--border-medium); white-space: nowrap; position: sticky; top: 0; z-index: 1;}
        th a { color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; }
        th a:hover { color: var(--primary-color); }
        th .fas { font-size: 0.9em; margin-left: 6px; opacity: 0.7; }
        th .fa-sort { opacity: 0.4; }

        tbody tr:nth-child(even) { background-color: var(--box-bg-color); }
        tbody tr:hover { background-color: #e9f5ff; } 

        .role-badge { padding: 4px 10px; border-radius: 15px; font-size: 0.8em; font-weight: 500; display: inline-block; text-transform: capitalize; }
        .role-employer { background-color: var(--secondary-color); color: var(--light-text-color); }
        .role-jobseeker { background-color: var(--accent-color); color: var(--text-color); }

        .btn { padding: 7px 12px; border: none; cursor: pointer; border-radius: 5px; font-size: 0.82em; font-weight: 500; transition: background-color 0.2s, transform 0.1s, box-shadow 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; margin: 0; color: var(--light-text-color); }
        .btn i { margin-right: 6px; font-size: 0.9em; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-delete { background-color: var(--button-delete-bg); }
        .btn-delete:hover { background-color: var(--button-delete-hover-bg); }

        .back-link-container { margin-top: 30px; text-align: left; }
        .back-link { color: var(--primary-color); text-decoration: none; font-weight: 500; padding: 10px 20px; border-radius: 6px; border: 1px solid var(--primary-color); transition: background-color 0.2s, color 0.2s; display: inline-flex; align-items: center; }
        .back-link i { margin-right: 8px; }
        .back-link:hover { background-color: var(--primary-color); color: var(--light-text-color); }

        .empty-state { text-align: center; padding: 40px 20px; font-size: 1.05em; color: var(--text-secondary); border: 1px dashed var(--border-light); border-radius: 6px; margin-top: 20px;}
        .empty-state i { font-size: 2.2em; display: block; margin-bottom: 15px; color: var(--secondary-color); }

        /* Footer is removed by not including its HTML */
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
                <span class="page-title-icon"><i class="fas fa-users"></i></span>
                <h2 class="page-main-title">Manage User Accounts</h2>
            </div>

            <!-- ADDED: Search Bar Form with Date Range -->
            <div class="admin-search-bar">
                <form action="manage_users.php" method="GET" style="display: contents;">
                    <div class="form-group">
                        <label for="search_term">Search Name/Email:</label>
                        <input type="text" name="search_term" id="search_term" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Name or email...">
                    </div>
                    <div class="form-group">
                        <label for="search_role">Filter by Role:</label>
                        <select name="search_role" id="search_role">
                            <option value="">All (Non-Admin)</option>
                            <option value="jobseeker" <?php echo ($search_role === 'jobseeker') ? 'selected' : ''; ?>>Jobseeker</option>
                            <option value="employer" <?php echo ($search_role === 'employer') ? 'selected' : ''; ?>>Employer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search_reg_date_from">Registered From:</label>
                        <input type="date" name="search_reg_date_from" id="search_reg_date_from" value="<?php echo htmlspecialchars($search_reg_date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label for="search_reg_date_to">Registered To:</label>
                        <input type="date" name="search_reg_date_to" id="search_reg_date_to" value="<?php echo htmlspecialchars($search_reg_date_to); ?>">
                    </div>
                    
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column_param); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order_param); ?>">
                    
                    <div class="search-actions">
                        <button type="submit" class="btn btn-search-users"><i class="fas fa-search"></i> Search</button>
                        <a href="manage_users.php?sort=<?php echo htmlspecialchars($sort_column_param); ?>&order=<?php echo htmlspecialchars($sort_order_param); ?>" class="btn btn-clear-users-search"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </form>
            </div>

            <div id="result-msg"></div>

            <div class="table-responsive-wrapper">
                <table id="userTable">
                    <thead>
                        <tr>
                            <th class="col-name"><?php echo get_admin_sort_link('name', 'Name', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-email"><?php echo get_admin_sort_link('email', 'Email', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-role"><?php echo get_admin_sort_link('role', 'Role', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-created"><?php echo get_admin_sort_link('created_at', 'Registered', $sort_column_param, $sort_order_param, $current_search_params_for_links); ?></th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($user = $result->fetch_assoc()) {
                                $role_class = 'role-' . strtolower(htmlspecialchars($user['role']));
                                echo "<tr id='row_{$user['id']}'>";
                                // ID data cell is removed
                                echo "  <td class='col-name'><strong>" . htmlspecialchars($user['name']) . "</strong></td>
                                        <td class='col-email'>" . htmlspecialchars($user['email']) . "</td>
                                        <td class='col-role'><span class='role-badge {$role_class}'>" . ucfirst(htmlspecialchars($user['role'])) . "</span></td>
                                        <td class='col-created'>" . htmlspecialchars(date("d M Y, H:i", strtotime($user['created_at']))) . "</td>
                                        <td class='col-action'>
                                            <button class='btn btn-delete' onclick='deleteUser({$user['id']}, \"" . htmlspecialchars(addslashes($user['name']), ENT_QUOTES) . "\")'><i class='fas fa-trash-alt'></i> Delete</button>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            $colspan_count = 5; 
                            echo "<tr><td colspan='{$colspan_count}' class='empty-state'><i class='fas fa-user-slash'></i>";
                            if (!empty($search_term) || !empty($search_role) || !empty($search_reg_date_from) || !empty($search_reg_date_to)) {
                                echo "No users found matching your search criteria. <a href='manage_users.php?sort=".htmlspecialchars($sort_column_param)."&order=".htmlspecialchars($sort_order_param)."'>Clear search</a>.";
                            } else {
                                echo "No users found (excluding other admins).";
                            }
                            echo "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="back-link-container">
                <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Main Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Footer HTML is REMOVED -->

    <script>
        function deleteUser(userId, userName) { 
            if (confirm("Are you sure you want to delete user '" + userName + "' (ID: " + userId + ")? This action cannot be undone.")) {
                var xhr = new XMLHttpRequest();
                var resultMsgDiv = document.getElementById("result-msg");
                xhr.open("POST", "delete_user.php", true); 
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhr.onload = function () {
                    resultMsgDiv.style.display = 'block'; 
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (xhr.status === 200 && response.success) {
                            resultMsgDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + response.message;
                            resultMsgDiv.className = 'success'; 
                            var row = document.getElementById("row_" + userId);
                            if (row) {
                                row.style.transition = 'opacity 0.5s ease-out';
                                row.style.opacity = '0';
                                setTimeout(function() { 
                                    row.remove();
                                    if (document.getElementById("userTable").getElementsByTagName("tbody")[0].rows.length === 0) {
                                        var tbody = document.getElementById("userTable").getElementsByTagName("tbody")[0];
                                        var colspanCount = document.getElementById("userTable").getElementsByTagName("thead")[0].rows[0].cells.length;
                                        tbody.innerHTML = "<tr><td colspan='" + colspanCount + "' class='empty-state'><i class='fas fa-user-slash'></i>No users found.</td></tr>";
                                    }
                                 }, 500);
                            }
                        } else {
                            resultMsgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error: ' + (response.message || 'Could not delete user.');
                            resultMsgDiv.className = 'error';
                        }
                    } catch (e) {
                        resultMsgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error processing server response.';
                        resultMsgDiv.className = 'error';
                        console.error("JSON Parse Error:", e, "Server Response:", xhr.responseText);
                    }
                     setTimeout(function() { resultMsgDiv.style.display = 'none'; resultMsgDiv.className=''; }, 6000);
                };
                 xhr.onerror = function() {
                    resultMsgDiv.style.display = 'block';
                    resultMsgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Network error occurred.';
                    resultMsgDiv.className = 'error';
                    setTimeout(function() { resultMsgDiv.style.display = 'none'; resultMsgDiv.className='';}, 6000);
                };
                xhr.send("id=" + userId + "&action=delete_user"); 
            }
        }
    </script>

</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>