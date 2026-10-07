<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

// Session details
$current_role = $_SESSION['role'] ?? 'Support'; 
$username     = $_SESSION['username'] ?? 'User';
$user_id      = $_SESSION['user_id'] ?? 0;

$is_admin_or_manager = ($current_role === 'Admin' || $current_role === 'Manager');

$message = '';
$interactions = [];

// Form Handling: Record interaction submission cleanly via safe PDO mappings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_interaction') {
    $customer_id = intval($_POST['customer_id']);
    $type = trim($_POST['type']);
    $notes = trim($_POST['notes']);
    $staff_id = $user_id;

    if ($customer_id > 0 && !empty($notes)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO interactions (customer_id, staff_id, type, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$customer_id, $staff_id, $type, $notes]);
            echo "<script>alert('Interaction log added successfully.'); window.location.href='interactions.php';</script>";
            exit;
        } catch (Exception $e) {
            $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Failed to add interaction: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// --- DATA MATRIX RETRIEVAL (ROLE-BASED ISOLATION) ---
try {
    if ($is_admin_or_manager) {
        // Admins & Managers see all interactions
        $sql = "SELECT i.*, 
                       COALESCE(c.full_name, c.username, 'Guest/System') as customer_name, 
                       COALESCE(u.username, 'System') as staff_name 
                FROM interactions i 
                LEFT JOIN customers c ON i.customer_id = c.id 
                LEFT JOIN users u ON i.staff_id = u.id 
                ORDER BY i.interaction_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $interactions = $stmt->fetchAll();
    } else {
        // Step 1: Resolve valid customer_id for logged-in user
        $cust_stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? OR username = ? LIMIT 1");
        $cust_stmt->execute([$user_id, $username]);
        $customer_id = $cust_stmt->fetchColumn();

        if ($customer_id) {
            // Step 2: Fetch ONLY the logged-in user's own interactions
            $sql = "SELECT i.*, 
                           COALESCE(c.full_name, c.username, 'Me') as customer_name, 
                           COALESCE(u.username, 'Management') as staff_name 
                    FROM interactions i 
                    LEFT JOIN customers c ON i.customer_id = c.id 
                    LEFT JOIN users u ON i.staff_id = u.id 
                    WHERE i.customer_id = ? 
                    ORDER BY i.interaction_date DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$customer_id]);
            $interactions = $stmt->fetchAll();
        } else {
            $interactions = [];
        }
    }
} catch (Exception $e) {
    $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Error loading history: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Fetch active customer dropdown register (Only needed for Admins / Managers submitting manual logs)
$customers = [];
if ($is_admin_or_manager) {
    try {
        $customers = $pdo->query("SELECT id, COALESCE(full_name, username) as display_name FROM customers ORDER BY display_name ASC")->fetchAll();
    } catch (Exception $e) {
        // Fallback
    }
}
?>

<style>
/* Strict Viewport Layout Switcher */
.desktop-view { 
    display: block !important; 
}
.desktop-view .data-table { 
    display: table !important; 
    width: 100% !important; 
}
.mobile-view { 
    display: none !important; 
}

@media (max-width: 768px) {
    .desktop-view { 
        display: none !important; 
    }
    .mobile-view { 
        display: flex !important; 
        flex-direction: column !important; 
        gap: 1rem !important; 
    }
}
</style>

<?php if (!empty($message)) echo $message; ?>

<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h2 class="section-title" style="color: var(--primary-color);">
        <?php echo $is_admin_or_manager ? 'Interaction Log Registries 💬' : 'My Communication History 💬'; ?>
    </h2>
    <p style="color: #64748b;">
        <?php echo $is_admin_or_manager ? 'Record conversational touchpoints, track follow-up summaries, and monitor direct customer communication histories.' : 'View updates, approval notes, and conversation logs regarding your requests.'; ?>
    </p>
</div>

<?php if ($is_admin_or_manager): ?>
<div class="action-panel" style="margin-bottom: 2rem;">
    <div class="action-panel__title" style="margin-bottom: 1rem; font-weight: 600; color: var(--primary-color);">Record New Customer Touchpoint</div>
    <form action="interactions.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto; gap: 1.25rem; align-items: end;">
        <input type="hidden" name="action" value="add_interaction">
        
        <div class="form-group" style="margin: 0;">
            <label>Target Client Profile</label>
            <select name="customer_id" class="form-control" required style="cursor: pointer; width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="">-- Select Client --</option>
                <?php foreach($customers as $cust): ?>
                    <option value="<?php echo $cust['id']; ?>"><?php echo htmlspecialchars($cust['display_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin: 0;">
            <label>Communication Type</label>
            <select name="type" class="form-control" required style="cursor: pointer; width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="Call">Call</option>
                <option value="Email">Email</option>
                <option value="Meeting">Meeting</option>
                <option value="Shipment Approval">Shipment Approval</option>
            </select>
        </div>

        <div class="form-group" style="margin: 0; min-width: 280px;">
            <label>Discussion Notes / Summary</label>
            <input type="text" name="notes" class="form-control" required placeholder="e.g., Discussed pricing for seasonal packages." style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1;">
        </div>

        <button type="submit" class="btn btn--primary" style="height: 42px; padding: 0 1.5rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">Save Entry Log</button>
    </form>
</div>
<?php endif; ?>

<!-- ==================== DESKTOP TABLE VIEW ==================== -->
<div class="data-table-wrapper desktop-view">
    <table class="data-table">
        <thead>
            <tr>
                <th class="data-table__th">Log ID</th>
                <?php if ($is_admin_or_manager): ?>
                    <th class="data-table__th">Client Profile Name</th>
                <?php endif; ?>
                <th class="data-table__th">Touchpoint</th>
                <th class="data-table__th">Discussion Summary Notes</th>
                <th class="data-table__th">Logged By Officer</th>
                <th class="data-table__th">Date / Time Stamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($interactions) > 0): foreach($interactions as $log): ?>
            <tr class="data-table__tr">
                <td class="data-table__td" style="font-weight: 600;">#INT-<?php echo $log['id']; ?></td>
                <?php if ($is_admin_or_manager): ?>
                    <td class="data-table__td"><strong><?php echo htmlspecialchars($log['customer_name']); ?></strong></td>
                <?php endif; ?>
                <td class="data-table__td">
                    <span style="font-weight:600; padding: 0.35rem 0.65rem; border-radius: 4px; font-size: 0.8rem; display: inline-block; text-align: center;
                        background-color: <?php echo $log['type'] === 'Call' ? '#eaf2f8; color:#2980b9;' : ($log['type'] === 'Email' ? '#e8f8f5; color:#2ecc71;' : '#fef9e7; color:#f39c12;'); ?>">
                        <?php echo htmlspecialchars($log['type']); ?>
                    </span>
                </td>
                <td class="data-table__td"><?php echo nl2br(htmlspecialchars($log['notes'])); ?></td>
                <td class="data-table__td" style="color:#64748b; font-weight: 500;"><?php echo htmlspecialchars($log['staff_name']); ?></td>
                <td class="data-table__td" style="font-size:0.85rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($log['interaction_date'])); ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="<?php echo $is_admin_or_manager ? '6' : '5'; ?>" class="data-table__td" style="text-align:center; padding: 3rem; color:#64748b; font-size: 0.95rem;">
                    No customer touchpoint interactions logged yet.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ==================== MOBILE CARD VIEW ==================== -->
<div class="mobile-view">
    <?php if(count($interactions) > 0): foreach($interactions as $log): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">#INT-<?php echo $log['id']; ?></span>
                <span style="font-weight:600; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; display: inline-block; text-align: center;
                    background-color: <?php echo $log['type'] === 'Call' ? '#eaf2f8; color:#2980b9;' : ($log['type'] === 'Email' ? '#e8f8f5; color:#2ecc71;' : '#fef9e7; color:#f39c12;'); ?>">
                    <?php echo htmlspecialchars($log['type']); ?>
                </span>
            </div>
            <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
                <?php if ($is_admin_or_manager): ?>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Client Profile</span>
                    <strong style="color: #1e293b;"><?php echo htmlspecialchars($log['customer_name']); ?></strong>
                </div>
                <?php endif; ?>
                <div style="display: flex; flex-direction: column; gap: 0.25rem; padding-bottom: 0.4rem; border-bottom: 1px solid #f8fafc;">
                    <span style="color: #64748b; font-size: 0.8rem;">Discussion Summary Notes</span>
                    <p style="margin: 0; color: #334155; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($log['notes'])); ?></p>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Logged By Officer</span>
                    <span style="color: #334155; font-weight: 600;">👤 <?php echo htmlspecialchars($log['staff_name']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; font-size: 0.85rem; color: #64748b;">
                    <span>Date / Time Stamp</span>
                    <span><?php echo date('M d, Y h:i A', strtotime($log['interaction_date'])); ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; else: ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; text-align: center; color: #64748b;">
            No customer touchpoint interactions logged yet.
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>