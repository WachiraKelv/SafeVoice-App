<?php
require_once 'config/functions.php';
requireLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

switch($action) {
    case 'profile':
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['update_profile'])) {
                $data = [
                    'full_name' => sanitizeInput($_POST['full_name']),
                    'email' => sanitizeInput($_POST['email']),
                    'phone' => sanitizeInput($_POST['phone'])
                ];
                updateUser($user_id, $data);
                $_SESSION['user_name'] = $data['full_name'];
                $_SESSION['user_email'] = $data['email'];
                $success = "Profile updated successfully";
            } elseif (isset($_POST['change_password'])) {
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                    changePassword($user_id, $new_password);
                    $success = "Password changed successfully";
                } else {
                    $error = "Passwords do not match or are too short";
                }
            }
        }
        break;
        
    case 'contacts':
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_contact'])) {
            $data = [
                'name' => sanitizeInput($_POST['name']),
                'relationship' => sanitizeInput($_POST['relationship']),
                'phone' => sanitizeInput($_POST['phone']),
                'email' => sanitizeInput($_POST['email'])
            ];
            addTrustedContact($user_id, $data);
            $success = "Trusted contact added successfully";
        }
        
        if (isset($_GET['delete'])) {
            deleteTrustedContact($_GET['delete'], $user_id);
            redirect('user_management.php?action=contacts');
        }
        break;
        
    case 'list':
        if (!isAdmin()) redirect('dashboard.php');
        break;
        
    case 'add':
        if (!isAdmin()) redirect('dashboard.php');
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'full_name' => sanitizeInput($_POST['full_name']),
                'email' => sanitizeInput($_POST['email']),
                'phone' => sanitizeInput($_POST['phone']),
                'password' => $_POST['password'],
                'role' => $_POST['role'],
                'gender' => sanitizeInput($_POST['gender']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            global $pdo;
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, gender, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$data['full_name'], $data['email'], $data['phone'], $data['gender'], $hashed_password, $data['role'], $data['is_active']])) {
                $success = "User added successfully";
                redirect('user_management.php?action=list&msg=added');
            } else {
                $error = "Failed to add user";
            }
        }
        break;
        
    case 'edit':
        if (!isAdmin()) redirect('dashboard.php');
        $edit_id = isset($_GET['id']) ? $_GET['id'] : 0;
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_POST['update_user'])) {
                $data = [
                    'full_name' => sanitizeInput($_POST['full_name']),
                    'email' => sanitizeInput($_POST['email']),
                    'phone' => sanitizeInput($_POST['phone']),
                    'gender' => sanitizeInput($_POST['gender']),
                    'role' => $_POST['role'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                global $pdo;
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, gender = ?, role = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$data['full_name'], $data['email'], $data['phone'], $data['gender'], $data['role'], $data['is_active'], $edit_id])) {
                    $success = "User updated successfully";
                } else {
                    $error = "Failed to update user";
                }
            } elseif (isset($_POST['reset_password'])) {
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    global $pdo;
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $edit_id])) {
                        $success = "Password reset successfully";
                    } else {
                        $error = "Failed to reset password";
                    }
                } else {
                    $error = "Passwords do not match or are too short (minimum 6 characters)";
                }
            }
        }
        break;
        
    case 'delete':
        if (!isAdmin()) redirect('dashboard.php');
        if (isset($_GET['id'])) {
            $delete_id = $_GET['id'];
            
            if ($delete_id == $user_id) {
                redirect('user_management.php?action=list&error=self_delete');
            }
            
            global $pdo;
            
            try {
                $pdo->beginTransaction();
                
                
                $stmt = $pdo->prepare("UPDATE reports SET user_id = NULL WHERE user_id = ?");
                $stmt->execute([$delete_id]);
                
                $stmt = $pdo->prepare("UPDATE sos_alerts SET user_id = NULL WHERE user_id = ?");
                $stmt->execute([$delete_id]);
                
                $stmt = $pdo->prepare("UPDATE case_updates SET updated_by = NULL WHERE updated_by = ?");
                $stmt->execute([$delete_id]);
                
                $stmt = $pdo->prepare("UPDATE notifications SET user_id = NULL WHERE user_id = ?");
                $stmt->execute([$delete_id]);
                
                $stmt = $pdo->prepare("DELETE FROM responders WHERE user_id = ?");
                $stmt->execute([$delete_id]);
                
                $stmt = $pdo->prepare("DELETE FROM trusted_contacts WHERE user_id = ?");
                $stmt->execute([$delete_id]);
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);
                
                $pdo->commit();
                
                redirect('user_management.php?action=list&msg=deleted');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                redirect('user_management.php?action=list&error=delete_failed');
            }
        }
        break;
        
    case 'status':
        if (!isAdmin()) redirect('dashboard.php');
        if (isset($_GET['id'])) {
            $status_id = $_GET['id'];
            
            if ($status_id != $user_id) {
                global $pdo;
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$status_id]);
            }
            redirect('user_management.php?action=list');
        }
        break;
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="content-wrapper">
                <?php if(isset($_GET['msg'])): ?>
                    <?php if($_GET['msg'] == 'added'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> User added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif($_GET['msg'] == 'deleted'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> User deleted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if(isset($_GET['error'])): ?>
                    <?php if($_GET['error'] == 'self_delete'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> You cannot delete your own account!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif($_GET['error'] == 'delete_failed'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> Failed to delete user. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if($action == 'profile'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Management</h5>
                        </div>
                        <div class="card-body">
                            <?php if(isset($success)): ?>
                                <div class="alert alert-success"><?php echo $success; ?></div>
                            <?php endif; ?>
                            <?php if(isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php $user = getUser($user_id); ?>
                            
                            <form method="POST" class="mb-4">
                                <h6>Update Profile</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Full Name</label>
                                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Phone</label>
                                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                    </div>
                                </div>
                            </form>
                            
                            <form method="POST">
                                <h6>Change Password</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif($action == 'contacts'): ?>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Add Trusted Contact</h5>
                                </div>
                                <div class="card-body">
                                    <?php if(isset($success)): ?>
                                        <div class="alert alert-success"><?php echo $success; ?></div>
                                    <?php endif; ?>
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label>Full Name</label>
                                            <input type="text" name="name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Relationship</label>
                                            <input type="text" name="relationship" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Phone Number</label>
                                            <input type="tel" name="phone" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Email (Optional)</label>
                                            <input type="email" name="email" class="form-control">
                                        </div>
                                        <button type="submit" name="add_contact" class="btn btn-primary w-100">Add Contact</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">My Trusted Contacts</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Action</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php $contacts = getTrustedContacts($user_id); ?>
                                                <?php if(count($contacts) > 0): ?>
                                                    <?php foreach($contacts as $contact): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($contact['relationship']); ?></td>
                                                        <td><?php echo htmlspecialchars($contact['phone']); ?></td>
                                                        <td>
                                                            <a href="?action=contacts&delete=<?php echo $contact['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete()">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center">No trusted contacts added yet</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif($action == 'add' && isAdmin()): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Add New User</h5>
                        </div>
                        <div class="card-body">
                            <?php if(isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #f1f8f3;">Full Name</label>
                                        <input type="text" name="full_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #edf1ef;">Email Address</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #e6eee9;">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Gender</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="Female">Female</option>
                                            <option value="Male">Male</option>
                                            <option value="Other">Other</option>
                                            <option value="Prefer not to say">Prefer not to say</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #e6ede7;">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" style="color: #e3ede7;">Role</label>
                                        <select name="role" class="form-select" required>
                                            <option value="survivor">Survivor</option>
                                            <option value="responder">Responder</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12 mb-3 form-check">
                                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                                        <label class="form-label" style="color: #f9eded;" for="is_active">Active Account</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Add User</button>
                                <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif($action == 'edit' && isAdmin()): ?>
                    <?php 
                    $edit_id = isset($_GET['id']) ? $_GET['id'] : 0;
                    $user = getUser($edit_id);
                    if(!$user): 
                    ?>
                        <div class="alert alert-danger">User not found!</div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if(isset($success)): ?>
                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>
                                <?php if(isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" class="mb-4">
                                    <h6>Update User Information</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>Full Name</label>
                                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Phone Number</label>
                                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Gender</label>
                                            <select name="gender" class="form-select" required>
                                                <option value="Female" <?php echo $user['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="Male" <?php echo $user['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Other" <?php echo $user['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                <option value="Prefer not to say" <?php echo $user['gender'] == 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Role</label>
                                            <select name="role" class="form-select" required>
                                                <option value="survivor" <?php echo $user['role'] == 'survivor' ? 'selected' : ''; ?>>Survivor</option>
                                                <option value="responder" <?php echo $user['role'] == 'responder' ? 'selected' : ''; ?>>Responder</option>
                                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12 mb-3 form-check">
                                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active">Active Account</label>
                                        </div>
                                        <div class="col-md-12">
                                            <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </div>
                                </form>
                                
                                <hr>
                                
                                <form method="POST">
                                    <h6>Reset Password</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label>New Password</label>
                                            <input type="password" name="new_password" class="form-control" required>
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Confirm Password</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-12">
                                            <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif($action == 'list' && isAdmin()): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">User Management</h5>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add User
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $users = getUsers(); ?>
                                        <?php foreach($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'responder' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Suspended'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=status&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="<?php echo $user['is_active'] ? 'Suspend' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                    </a>
                                                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete()" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    return confirm('Are you sure you want to delete this user? This action will:\n\n' +
                   '• Remove the user account\n' +
                   '• Anonymize their reports (set to Anonymous)\n' +
                   '• Delete their trusted contacts\n' +
                   '• Remove their notifications\n\n' +
                   'This action cannot be undone!');
}
</script>

<?php include 'includes/footer.php'; ?>