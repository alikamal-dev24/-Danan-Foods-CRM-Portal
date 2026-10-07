<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// Restrict page view to Admin or Manager permissions strictly
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager') {
    echo "<div class='alert alert--danger'>Access Denied. You do not have permissions to view this terminal component.</div>";
    require_once 'includes/footer.php';
    exit;
}

$message = '';
$message_type = '';

// --- HANDLE POST OPERATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. CREATE NEW USER ENGINE OPERATION
    if (isset($_POST['action']) && $_POST['action'] === 'create_user') {
        $username_input = trim($_POST['username']);
        $password_input = trim($_POST['password']);
        $role_input = $_POST['role'];

        if (!empty($username_input) && !empty($password_input)) {
            // Check global duplicate record collisions
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username_input]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Error: This username identifier is already active inside the matrix database.";
                $message_type = "danger";
            } else {
                $hashed_password = password_hash($password_input, PASSWORD_BCRYPT);
                $insert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $insert->execute([$username_input, $hashed_password, $role_input]);
                $message = "User Account for system profile context successfully initialized!";
                $message_type = "success";
            }
        } else {
            $message = "Please complete all mandatory user fields.";
            $message_type = "danger";
        }
    }

    // 2. FIXED UPDATE USER ACTION (PREVENTS COLLIDING WITH SYSTEM OWN RECORDS)
    if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
        $user_id = intval($_POST['user_id']);
        $username_input = trim($_POST['username']);
        $role_input = $_POST['role'];

        if (!empty($username_input)) {
            // FIX: Check if username is taken by anyone EXCEPT the record currently being modified
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username_input, $user_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $message = "Error: The username update requested belongs to an external user account configuration.";
                $message_type = "danger";
            } else {
                // Safely commit update mutations to table profiles
                $update = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $update->execute([$username_input, $role_input, $user_id]);
                $message = "Permissions and Profile Credentials updated successfully!";
                $message_type = "success";
            }
        }
    }
}

// Fetch all registered operators
$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC")->fetchAll();
?>

<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h2 class="section-title">Access Permissions Terminal 🔐</h2>
    <p style="color: #64748b;">Manage security clearings, roles, and administrative profiles across Danan Foods.</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert--<?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; align-items: start;">
    
    <!-- SYSTEM USER MANAGEMENT DIRECTORY -->
    <div class="action-panel">
        <h3 class="action-panel__title">Active Core Profiles</h3>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="data-table__th">Username</th>
                        <th class="data-table__th">Assigned Role</th>
                        <th class="data-table__th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <form method="POST" action="roles.php">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                
                                <td class="data-table__td">
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($u['username']); ?>" style="padding: 0.35rem; font-size:0.875rem;">
                                </td>
                                <td class="data-table__td">
                                    <select class="form-control" name="role" style="padding: 0.35rem; font-size:0.875rem;">
                                        <option value="Admin" <?php echo $u['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="Manager" <?php echo $u['role'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Modifier" <?php echo $u['role'] === 'Support' ? 'selected' : ''; ?>>Modifier</option>
                                        <option value="Distributor" <?php echo $u['role'] === 'Sales' ? 'selected' : ''; ?>>Distributor</option>
                                    </select>
                                </td>
                                <td class="data-table__td">
                                    <button type="submit" class="btn btn--primary btn--sm">Save Changes</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- INITIALIZE NEW PROFILE SCHEDULER -->
    <div class="action-panel">
        <h3 class="action-panel__title">Provision New Account Access</h3>
        <form method="POST" action="roles.php">
            <input type="hidden" name="action" value="create_user">
            
            <div class="form-group">
                <label>System Username Connection String</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. malik_distributors" required>
            </div>
            
            <div class="form-group">
                <label>Access Key Password String</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <div class="form-group">
                <label>Assigned Permission Clearance Level</label>
                <select name="role" class="form-control">
                    <option value="Sales">Distributor (Client Access Profile)</option>
                    <option value="Support">Modifier (Data Entry / Logistics Operator)</option>
                    <option value="Manager">Manager (Regional Operations Controller)</option>
                    <option value="Admin">Admin (Full Terminal Access Root)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn--primary" style="width: 100%; margin-top: 0.5rem;">Initialize Terminal Account</button>
        </form>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>