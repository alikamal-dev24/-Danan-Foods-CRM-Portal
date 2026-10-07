<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

// ONLY ADMIN ALLOWED HERE
if ($_SESSION['role'] !== 'Admin') {
    echo "<h2 style='color:#ef4444; padding:2rem;'>Access Denied. Admin clearance required.</h2>";
    require_once 'includes/footer.php';
    exit;
}

$msg = ''; $err = '';
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$edit_data = null;

if ($edit_id > 0) {
    $e_stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $e_stmt->execute([$edit_id]);
    $edit_data = $e_stmt->fetch();
}

// --- HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: ADD DISTRIBUTOR
    if ($_POST['action'] === 'add_distributor') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $username = trim($_POST['username']);
        $password_raw = trim($_POST['password']);
        $initial_balance = floatval($_POST['initial_balance'] ?? 0);

        $customer_email = empty($email) ? null : $email;

        if (!empty($full_name) && !empty($username) && !empty($password_raw)) {
            $pdo->beginTransaction();
            try {
                // Verify username doesn't exist across either access array table
                $check_user = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_user->execute([$username]);
                if ($check_user->fetch()) { throw new Exception("Username already assigned to system personnel."); }

                $check_cust = $pdo->prepare("SELECT id FROM customers WHERE username = ?");
                $check_cust->execute([$username]);
                if ($check_cust->fetch()) { throw new Exception("Username already assigned to a distributor."); }

                // Cryptographically secure password generation
                $hashed_password = password_hash($password_raw, PASSWORD_BCRYPT);

                // Insert into customers table
                $c_stmt = $pdo->prepare("INSERT INTO customers (full_name, email, username, password, raw_password_text, phone, total_balance_due) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $c_stmt->execute([$full_name, $customer_email, $username, $hashed_password, $password_raw, $phone, $initial_balance]);

                // Insert into users table with synchronized hashed password values
                $u_stmt = $pdo->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'Distributor', 'Active')");
                $u_stmt->execute([$username, $hashed_password]); 

                $pdo->commit();
                $msg = "Distributor profile generated successfully.";
            } catch (Exception $e) { 
                $pdo->rollBack(); 
                $err = "Operation failed: " . $e->getMessage(); 
            }
        }
    }

    // ACTION: UPDATE DISTRIBUTOR
    if ($_POST['action'] === 'update_distributor') {
        $id = intval($_POST['id']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $username = trim($_POST['username']);
        $password_raw = trim($_POST['password']);
        $total_balance_due = floatval($_POST['total_balance_due']);

        $customer_email = empty($email) ? null : $email;

        if ($id > 0 && !empty($full_name)) {
            $pdo->beginTransaction();
            try {
                $orig_stmt = $pdo->prepare("SELECT username FROM customers WHERE id = ?");
                $orig_stmt->execute([$id]);
                $orig = $orig_stmt->fetch();

                if ($orig) {
                    // Regenerate encrypted password securely
                    $hashed_password = password_hash($password_raw, PASSWORD_BCRYPT);

                    // Update customers table with secure password strings
                    $up_cust = $pdo->prepare("UPDATE customers SET full_name = ?, email = ?, username = ?, password = ?, raw_password_text = ?, phone = ?, total_balance_due = ? WHERE id = ?");
                    $up_cust->execute([$full_name, $customer_email, $username, $hashed_password, $password_raw, $phone, $total_balance_due, $id]);

                    // Synchronize metadata records into fallback users authentication grid directly
                    $up_user = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE username = ?");
                    $up_user->execute([$username, $hashed_password, $orig['username']]);

                    $pdo->commit();
                    echo "<script>alert('Distributor updated securely!'); window.location.href='customers.php';</script>";
                    exit;
                }
            } catch (Exception $e) { 
                $pdo->rollBack(); 
                $err = "Update failure: " . $e->getMessage(); 
            }
        }
    }

    // ACTION: SECURE PURGE/DELETE ACCOUNT
    if ($_POST['action'] === 'delete_distributor') {
        $id = intval($_POST['id']);
        $pdo->beginTransaction();
        try {
            $orig_stmt = $pdo->prepare("SELECT username FROM customers WHERE id = ?");
            $orig_stmt->execute([$id]);
            $res = $orig_stmt->fetch();

            if($res) {
                $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$res['username']]);
                $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
                $pdo->commit();
                $msg = "Account registry wiped successfully.";
            }
        } catch(Exception $e) {
            $pdo->rollBack();
            $err = "Deletion error: " . $e->getMessage();
        }
    }
}

$all_customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
?>

<style>
/* Modern Responsive Table Styles for Customers Directory */
:root {
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-muted: #64748b;
    --primary-blue: #3b82f6;
    --accent-green: #10b981;
    --accent-red: #ef4444;
}

.cust-wrapper {
    max-width: 1200px;
    margin: 0 auto;
}

.cust-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.cust-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.cust-table th {
    background: #f8fafc;
    color: var(--text-muted);
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.85rem;
    border-bottom: 1px solid var(--border-color);
    text-align: left;
}
.cust-table td {
    padding: 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.9rem;
}

/* Mobile Responsive Card-Based Layout */
@media (max-width: 868px) {
    .cust-table, .cust-table tbody, .cust-table tr, .cust-table td {
        display: block;
        width: 100%;
    }
    .cust-table thead { display: none; }
    .cust-table tr {
        margin-bottom: 1.25rem;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.5rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .cust-table td {
        text-align: right;
        padding: 0.6rem 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .cust-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.8rem;
    }
    .cust-table td:last-child {
        border-bottom: none;
        justify-content: flex-end;
    }
}
</style>

<div class="cust-wrapper">
    <div class="dashboard-header" style="margin-bottom: 1.5rem;">
        <h2 class="section-title" style="color: var(--primary-color); font-size: 1.4rem;">Customer Profile Management Directory 👥</h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.2rem;">Provision, audit, and manage active corporate distributor accounts.</p>
    </div>

    <?php if(!empty($msg)): ?>
        <div class="alert alert--success" style="margin-bottom: 1rem;">✅ <?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if(!empty($err)): ?>
        <div class="alert alert--danger" style="margin-bottom: 1rem;">❌ <?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <?php if (!$edit_data): ?>
    <div class="cust-card">
        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 1rem;">Provision New Corporate Account</div>
        <form action="customers.php" method="POST">
            <input type="hidden" name="action" value="add_distributor">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">Full Business Name</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="e.g. Malik Traders" style="margin-top: 0.3rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">Email Address (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. info@malik.com" style="margin-top: 0.3rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">Phone Number</label>
                    <input type="text" name="phone" class="form-control" required placeholder="e.g. 03001234567" style="margin-top: 0.3rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">System Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="e.g. malik_dist" style="margin-top: 0.3rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">Portal Password</label>
                    <input type="text" name="password" class="form-control" required placeholder="Assign profile password" style="margin-top: 0.3rem;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">Opening Balance (PKR)</label>
                    <input type="number" step="0.01" name="initial_balance" class="form-control" value="0.00" style="margin-top: 0.3rem;">
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                <button type="submit" class="btn btn--primary">Provision Account</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="cust-card">
        <div style="overflow-x: auto;">
            <table class="cust-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Identity Name</th>
                        <th>Phone</th>
                        <th>Username</th>
                        <th>Reference Password</th>
                        <th>Outstanding Balance</th>
                        <th style="text-align:center;">Action Controls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_customers as $c): ?>
                    <tr>
                        <td data-label="ID" style="font-weight:600;">#DIST-<?php echo $c['id']; ?></td>
                        <td data-label="Identity Name">
                            <!-- Link to Customer Details / Ledger Page -->
                            <a href="customer-details.php?id=<?php echo $c['id']; ?>" style="color: var(--primary-color); text-decoration: none;" title="Click to view full statement">
                                <strong><?php echo htmlspecialchars($c['full_name'] ?? ''); ?> 🔗</strong>
                            </a>
                            <div style="font-size:0.78rem; color:#64748b; margin-top: 0.1rem;"><?php echo htmlspecialchars($c['email'] ?? 'No email integration'); ?></div>
                        </td>
                        <td data-label="Phone"><?php echo htmlspecialchars($c['phone'] ?? ''); ?></td>
                        <td data-label="Username" style="color:var(--accent-color); font-weight:600;"><?php echo htmlspecialchars($c['username'] ?? ''); ?></td>
                        <td data-label="Reference Password"><code style="background:#f1f5f9; padding:0.2rem 0.4rem; border-radius:4px; font-size:0.82rem; color:#475569;"><?php echo htmlspecialchars($c['raw_password_text'] ?? ''); ?></code></td>
                        <td data-label="Outstanding Balance" style="color: #0f172a;"><strong>Rs. <?php echo number_format($c['total_balance_due'],2); ?></strong></td>
                        <td data-label="Actions" style="text-align:center;">
                            <div style="display:flex; gap:0.4rem; justify-content:flex-end; align-items:center; flex-wrap: wrap;">
                                <!-- Action Control: View History Button -->
                                <a href="customer-details.php?id=<?php echo $c['id']; ?>" class="btn btn--sm" style="background:#0284c7; color:white; text-decoration:none;">👁️ View</a>
                                <a href="customers.php?edit_id=<?php echo $c['id']; ?>" class="btn btn--sm" style="background:var(--warning-color); color:black; text-decoration:none;">✏️ Edit</a>
                                <form action="customers.php" method="POST" onsubmit="return confirm('Wipe this profile entirely? This cannot be undone.');" style="display:inline; margin:0;">
                                    <input type="hidden" name="action" value="delete_distributor">
                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn--primary btn--sm" style="background:var(--danger-color) !important;">🗑️ Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    
                    <?php if ($edit_data && $edit_data['id'] == $c['id']): ?>
                    <tr>
                        <td colspan="7" style="background:#fffbeb; padding:1.5rem; border-top:2px solid #f59e0b; border-bottom:2px solid #f59e0b;">
                            <h4 style="color:#b45309; margin-bottom:1rem; font-size: 1.1rem;">Modify Account Settings: #DIST-<?php echo $edit_data['id']; ?></h4>
                            <form action="customers.php" method="POST">
                                <input type="hidden" name="action" value="update_distributor">
                                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="font-size: 0.82rem; font-weight: 600; color: #b45309;">Full Name</label>
                                        <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($edit_data['full_name']); ?>" style="margin-top: 0.3rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="font-size: 0.82rem; font-weight: 600; color: #b45309;">Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>" style="margin-top: 0.3rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="font-size: 0.82rem; font-weight: 600; color: #b45309;">Phone</label>
                                        <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($edit_data['phone']); ?>" style="margin-top: 0.3rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="font-size: 0.82rem; font-weight: 600; color: #b45309;">Username</label>
                                        <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($edit_data['username']); ?>" style="margin-top: 0.3rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="font-size: 0.82rem; font-weight: 600; color: #b45309;">Password</label>
                                        <input type="text" name="password" class="form-control" required value="<?php echo htmlspecialchars($edit_data['raw_password_text']); ?>" style="margin-top: 0.3rem;">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label style="font-size: 0.82rem; font-weight: 600; color: #b45309;">Balance Due (PKR)</label>
                                        <input type="number" step="0.01" name="total_balance_due" class="form-control" value="<?php echo $edit_data['total_balance_due']; ?>" style="margin-top: 0.3rem;">
                                    </div>
                                </div>
                                
                                <div style="text-align:right; margin-top: 1rem;">
                                    <button type="submit" class="btn" style="background:#d97706; color:white;">Save Changes</button>
                                    <a href="customers.php" class="btn" style="background:#cbd5e1; color:#334155; margin-left:0.5rem; text-decoration:none;">Cancel</a>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>