<?php
/**
 * User Management Page
 * School Finance Management System
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/helpers.php';

require_master(); // Enforces Master permission

$error = '';
$success = '';

// Handle Delete Action
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $current_user_id = get_user_id();
    
    if ($delete_id === $current_user_id) {
        $error = "You cannot delete your own account!";
    } else {
        // Verify user exists and get role/username
        $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $user_to_delete = $res->fetch_assoc();
            
            // Delete user
            $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $del_stmt->bind_param("i", $delete_id);
            if ($del_stmt->execute()) {
                $success = "User '" . htmlspecialchars($user_to_delete['username']) . "' has been deleted successfully!";
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
            $del_stmt->close();
        } else {
            $error = "User not found!";
        }
        $stmt->close();
    }
}

// Handle Edit Form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'edit') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize_input($_POST['role'] ?? '');
        $current_user_id = get_user_id();
        
        if (empty($username) || empty($role)) {
            $error = "Username and Role are required!";
        } elseif (!in_array($role, ['master', 'finance', 'admission'])) {
            $error = "Invalid role selected!";
        } else {
            // Check if username exists on another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $error = "Username already exists for another user!";
            } else {
                // If this is the current logged-in user, prevent changing their own role to something else
                if ($user_id === $current_user_id && $role !== 'master') {
                    $error = "You cannot change your own role from Master to prevent lockout!";
                } else {
                    // Prepare update query
                    if (!empty($password)) {
                        // Password is provided, update it as well
                        $up_stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                        $up_stmt->bind_param("sssi", $username, $password, $role, $user_id);
                    } else {
                        // Password is empty, do not change it
                        $up_stmt = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                        $up_stmt->bind_param("ssi", $username, $role, $user_id);
                    }
                    
                    if ($up_stmt->execute()) {
                        $success = "User details updated successfully!";
                        // If current logged-in user updated their own username, update session
                        if ($user_id === $current_user_id) {
                            $_SESSION['username'] = $username;
                            $_SESSION['role'] = $role;
                        }
                    } else {
                        $error = "Error updating user: " . $conn->error;
                    }
                    $up_stmt->close();
                }
            }
            $stmt->close();
        }
    }
}

// Handle Freeze/Unfreeze Toggle Action
if (isset($_GET['toggle_freeze'])) {
    $toggle_id = intval($_GET['toggle_freeze']);
    $current_user_id = get_user_id();
    
    if ($toggle_id === $current_user_id) {
        $error = "You cannot freeze your own account!";
    } else {
        // Get current frozen status
        $stmt = $conn->prepare("SELECT is_frozen, username FROM users WHERE id = ?");
        $stmt->bind_param("i", $toggle_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $user_row = $res->fetch_assoc();
            $new_frozen_status = intval($user_row['is_frozen']) === 1 ? 0 : 1;
            $new_frozen_until = $new_frozen_status === 1 ? date('Y-m-d 00:00:00', strtotime('tomorrow')) : null;
            
            $update_stmt = $conn->prepare("UPDATE users SET is_frozen = ?, frozen_until = ? WHERE id = ?");
            $update_stmt->bind_param("isi", $new_frozen_status, $new_frozen_until, $toggle_id);
            if ($update_stmt->execute()) {
                $status_txt = $new_frozen_status === 1 ? 'frozen' : 'unfrozen';
                $success = "User '" . htmlspecialchars($user_row['username']) . "' has been " . $status_txt . " successfully!";
            } else {
                $error = "Error updating user freeze status: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            $error = "User not found!";
        }
        $stmt->close();
    }
}

// Fetch user if editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $edit_user = $res->fetch_assoc();
    }
    $stmt->close();
}

// Fetch all users for listing
$users_result = $conn->query("SELECT id, username, password, role, is_frozen, frozen_until, created_at FROM users ORDER BY id ASC");
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper feature-shell">
        <main class="main-content">
            <div class="topbar">
                <div class="topbar-left d-flex align-items-center gap-3">
                    <a href="dashboard.php"><?php echo render_system_logo('topbar-logo'); ?></a>
                    <div class="panel-brand">
                        <h2>User Management</h2>
                        <span>Principal Panel</span>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> <?php echo get_username(); ?>
                    </span>
                    <a href="../logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <div class="module-nav-panel">
                    <div class="module-nav-row">
                        <a href="dashboard.php" class="module-nav-btn">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                        <a href="add_student.php" class="module-nav-btn">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                        <a href="student_record.php" class="module-nav-btn">
                            <i class="fas fa-address-book"></i> Student Record
                        </a>
                        <a href="fee_schedule.php" class="module-nav-btn">
                            <i class="fas fa-calendar-alt"></i> Fee Schedule
                        </a>
                        <a href="fee_management.php" class="module-nav-btn">
                            <i class="fas fa-money-bill-wave"></i> Fee Management
                        </a>
                        <a href="defaulter_list.php" class="module-nav-btn">
                            <i class="fas fa-list"></i> Pending List
                        </a>
                        <a href="payment_analytics.php" class="module-nav-btn">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        <a href="expenses.php" class="module-nav-btn">
                            <i class="fas fa-wallet"></i> Expenses
                        </a>
                        <a href="promotion.php" class="module-nav-btn">
                            <i class="fas fa-arrow-up"></i> Promotion
                        </a>
                        <a href="drop_student.php" class="module-nav-btn">
                            <i class="fas fa-trash"></i> Drop Student
                        </a>
                        <a href="users.php" class="module-nav-btn active">
                            <i class="fas fa-users-cog"></i> Users
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="table-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Existing System Users</h4>
                                <span class="badge bg-secondary" style="padding: 8px 12px; font-size: 0.9rem;">Total: <?php echo count($users); ?></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Password (Plain)</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($users) > 0): ?>
                                            <?php foreach ($users as $u): ?>
                                                <tr class="<?php echo $u['id'] == get_user_id() ? 'fw-bold' : ''; ?>" style="<?php echo $u['id'] == get_user_id() ? 'background-color: rgba(31, 95, 70, 0.05);' : ''; ?>">
                                                    <td><?php echo $u['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                                        <?php if ($u['id'] == get_user_id()): ?>
                                                            <span class="badge bg-success ms-1" style="font-size: 0.75rem;">You</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($u['role'] === 'master'): ?>
                                                            <span class="badge bg-danger" style="font-size: 0.8rem;"><i class="fas fa-shield-alt"></i> Master</span>
                                                        <?php elseif ($u['role'] === 'finance'): ?>
                                                            <span class="badge bg-primary" style="font-size: 0.8rem;"><i class="fas fa-calculator"></i> Finance</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info text-dark" style="font-size: 0.8rem;"><i class="fas fa-user-edit"></i> Admission</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($u['password']); ?></code>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $is_f = intval($u['is_frozen'] ?? 0);
                                                        $f_until = $u['frozen_until'] ?? null;
                                                        if ($is_f === 1 && !empty($f_until) && strtotime('now') >= strtotime($f_until)) {
                                                            $is_f = 0; // Display active
                                                        }
                                                        
                                                        if ($is_f === 1): 
                                                        ?>
                                                            <span class="badge bg-danger" style="font-size: 0.8rem;" title="Frozen until: <?php echo $f_until; ?>"><i class="fas fa-lock"></i> Frozen</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success" style="font-size: 0.8rem;"><i class="fas fa-check-circle"></i> Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo date('d-M-Y h:i A', strtotime($u['created_at'])); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?php if ($u['id'] != get_user_id()): ?>
                                                            <?php if ($is_f === 1): ?>
                                                                <a href="users.php?toggle_freeze=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-success me-1" style="padding: 4px 10px; font-size: 0.85rem;" title="Unfreeze Account">
                                                                    <i class="fas fa-unlock"></i> Unfreeze
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="users.php?toggle_freeze=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-warning me-1" style="padding: 4px 10px; font-size: 0.85rem;" title="Freeze Account">
                                                                    <i class="fas fa-ban"></i> Freeze
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <a href="users.php?edit=<?php echo $u['id']; ?>" class="btn-action" style="text-decoration: none; padding: 4px 10px; font-size: 0.85rem; border-radius: 4px; display: inline-block; margin-right: 5px;">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No users found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <?php if ($edit_user): ?>
                            <div class="form-section">
                                <h4 class="mb-3"><i class="fas fa-user-edit text-success"></i> Edit User Details</h4>
                                <p class="text-muted small">Update login details for <strong><?php echo htmlspecialchars($edit_user['username']); ?></strong>.</p>
                                <form method="POST" action="users.php">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label" for="username">Username *</label>
                                        <input type="text" id="username" name="username" class="form-control" 
                                               value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label" for="password">Password *</label>
                                        <input type="text" id="password" name="password" class="form-control" 
                                               placeholder="Enter password" value="<?php echo htmlspecialchars($edit_user['password']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label" for="role">Role *</label>
                                        <select id="role" name="role" class="form-select" required>
                                            <option value="admission" <?php echo $edit_user['role'] === 'admission' ? 'selected' : ''; ?>>Admission User</option>
                                            <option value="finance" <?php echo $edit_user['role'] === 'finance' ? 'selected' : ''; ?>>Finance User</option>
                                            <option value="master" <?php echo $edit_user['role'] === 'master' ? 'selected' : ''; ?>>Master Admin</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn-primary w-100" style="padding: 10px;">
                                            <i class="fas fa-save"></i> Update
                                        </button>
                                        <a href="users.php" class="btn-secondary w-100 text-center" style="padding: 10px; text-decoration: none;">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="card h-100 border-0 shadow-sm d-flex align-items-center justify-content-center text-center p-4" style="background: #fafafa; border-radius: 8px; min-height: 250px;">
                                <div>
                                    <i class="fas fa-user-cog fa-2x text-muted mb-3" style="opacity: 0.5;"></i>
                                    <p class="text-muted mb-0">Select any user's <strong>Edit</strong> button from the table to modify their details here.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>