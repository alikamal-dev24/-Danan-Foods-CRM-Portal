<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 0;
$current_role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';

if ($user_id === 0) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$msg = ''; $err = '';

// --- FETCH METADATA BASED ON IDENTITY PRIVILEGE ---
try {
    if ($current_role === 'Admin' || $current_role === 'Manager') {
        $stmt = $pdo->prepare("SELECT username, 'Internal Staff' as full_name, '' as phone, '' as email FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT username, full_name, phone, email, raw_password_text FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();
    }
} catch (Exception $e) {
    $err = "Failed loading configurations: " . $e->getMessage();
}

// --- CONFIGURATION SAVE OPERATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['full_name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_pass = trim($_POST['password'] ?? '');

    if (!empty($new_pass)) {
        $pdo->beginTransaction();
        try {
            $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);

            if ($current_role === 'Admin' || $current_role === 'Manager') {
                $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $up->execute([$hashed_password, $user_id]);
            } else {
                $up_cust = $pdo->prepare("UPDATE customers SET full_name = ?, phone = ?, email = ?, password = ?, raw_password_text = ? WHERE id = ?");
                $up_cust->execute([$new_name, $new_phone, $new_email, $hashed_password, $new_pass, $user_id]);

                $up_user = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                $up_user->execute([$hashed_password, $username]);
            }
            $pdo->commit();
            $msg = "System access settings updated successfully.";
            
            // Reload updated database array elements
            if ($current_role !== 'Admin' && $current_role !== 'Manager') {
                $profile['raw_password_text'] = $new_pass;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = "Save process dropped: " . $e->getMessage();
        }
    } else {
        $err = "Password field cannot be missing metrics.";
    }
}
?>

<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h2 class="section-title">Account Profiles & Settings ⚙️</h2>
    <p style="color: #64748b;">Manage security configurations and localized personalization attributes.</p>
</div>

<?php if(!empty($msg)): ?>
    <div class="alert alert--success" style="padding:1rem; margin-bottom:1.5rem;">✅ <?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>
<?php if(!empty($err)): ?>
    <div class="alert alert--danger" style="padding:1rem; margin-bottom:1.5rem;">❌ <?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>

<div class="action-panel" style="max-width: 600px; background:#fff; padding:2rem; border-radius:8px;">
    <div class="action-panel__title" style="margin-bottom:1.5rem; font-weight:700;">Update Access Parameters</div>
    
    <form action="setting.php" method="POST">
        <div class="form-group">
            <label>System Username Reference</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['username'] ?? $username); ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">
        </div>

        <?php if ($current_role !== 'Admin' && $current_role !== 'Manager'): ?>
            <div class="form-group">
                <label>Business Identity Name</label>
                <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Phone Coordinate</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Email Coordination Hub</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
            </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom: 2rem;">
            <label>New Passcode Access Token</label>
            <input type="text" name="password" class="form-control" placeholder="Change security passcode" value="<?php echo htmlspecialchars($profile['raw_password_text'] ?? ''); ?>" required>
        </div>

        <button type="submit" class="btn btn--primary" style="width:100%; padding:0.75rem;">Commit Settings Adjustments</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>