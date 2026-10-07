<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager') {
    echo "<h2 style='color:#e74c3c; padding:2rem;'>Access Denied. Audit security protocols clearance required.</h2>";
    require_once 'includes/footer.php';
    exit;
}

// Retrieve complete security trail matrix matching corresponding user execution runs
$logs = $pdo->query("SELECT l.*, u.username FROM security_logs l 
                     LEFT JOIN users u ON l.user_id = u.id 
                     ORDER BY l.logged_at DESC LIMIT 100")->fetchAll();
?>

<h2 style="margin-bottom: 0.5rem; color: var(--primary-color);">System Protection Audit Trails</h2>
<p style="color:#7f8c8d; margin-bottom: 1.5rem;">Monitoring backend state actions, transaction signatures, and IP logs.</p>

<div class="data-table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th class="data-table__th" style="width: 80px;">Log ID</th>
                <th class="data-table__th" style="width: 140px;">Operator</th>
                <th class="data-table__th">Action / System Changes Executed</th>
                <th class="data-table__th" style="width: 130px;">Network IP Address</th>
                <th class="data-table__th" style="width: 180px;">Timestamp Signature</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($logs) > 0): foreach($logs as $log): ?>
            <tr class="data-table__tr">
                <td class="data-table__td" style="color:#95a5a6;">#SEC-<?php echo $log['id']; ?></td>
                <td class="data-table__td"><strong style="color:#2c3e50;"><?php echo htmlspecialchars($log['username'] ?? 'System Sync'); ?></strong></td>
                <td class="data-table__td" style="font-family:Consolas, monospace; font-size:0.9rem; color:#27ae60;"><?php echo htmlspecialchars($log['action_performed']); ?></td>
                <td class="data-table__td"><code style="background:#f1f5f9; padding:0.2rem 0.4rem; border-radius:4px; font-size:0.85rem; color:#e67e22;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                <td class="data-table__td" style="font-size:0.85rem; color:#7f8c8d;"><?php echo date('M d, Y h:i A', strtotime($log['logged_at'])); ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="5" class="data-table__td" style="text-align:center; padding:2rem; color:#7f8c8d;">No tracking system security trails exist.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>