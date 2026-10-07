<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// Form Handling: Handle new incoming pipeline elements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_lead') {
        $name = trim($_POST['customer_name']);
        $email = trim($_POST['email']);
        $source = trim($_POST['source']);
        $agent_id = $_SESSION['user_id'];

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO leads (customer_name, email, source, assigned_to) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $source, $agent_id]);
            echo "<script>alert('Lead added to tracking funnel.'); window.location.href='leads.php';</script>";
            exit;
        }
    } elseif ($_POST['action'] === 'convert_lead') {
        $lead_id = intval($_POST['lead_id']);
        
        // Fetch Lead Metadata details safely
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch();

        if ($lead && $lead['status'] !== 'Converted') {
            $pdo->beginTransaction();
            try {
                // Generate safe default credentials to keep the customer database schema completely aligned
                $default_username = 'user_' . $lead_id . '_' . bin2hex(random_bytes(2));
                $default_password_raw = 'welcome123';
                $default_password_hash = password_hash($default_password_raw, PASSWORD_BCRYPT);

                // FIXED: Explicitly mapping all required database properties to bypass insertion validation rules
                $ins = $pdo->prepare("INSERT INTO customers (full_name, email, username, password, raw_password_text, phone, assigned_agent_id, total_balance_due) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $lead['customer_name'], 
                    $lead['email'] ?: 'converted_'.$lead_id.'@danan.local',
                    $default_username,
                    $default_password_hash,
                    $default_password_raw,
                    '', // Empty string for phone initial baseline mapping
                    $lead['assigned_to'],
                    0.00 // Default ledger starting balance
                ]);
                
                // 2. Set processing state tag flag directly to original ledger item
                $upd = $pdo->prepare("UPDATE leads SET status = 'Converted' WHERE id = ?");
                $upd->execute([$lead_id]);
                
                $pdo->commit();
                echo "<script>alert('Pipeline lead successfully migrated to full client account status.'); window.location.href='customers.php';</script>";
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<script>alert('Conversion failed: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}

// Data Array Aggregations mapping
$leads = $pdo->query("SELECT l.*, u.username as agent_name FROM leads l LEFT JOIN users u ON l.assigned_to = u.id ORDER BY l.created_at DESC")->fetchAll();
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

<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h2 class="section-title" style="color: var(--primary-color);">Sales Pipeline Funnel 📈</h2>
    <p style="color: #64748b;">Ingest incoming opportunities, map structural acquisition funnels, and transition target pipelines to client accounts.</p>
</div>

<div class="action-panel" style="margin-bottom: 2rem;">
    <div class="action-panel__title" style="margin-bottom: 1rem; font-weight: 600; color: var(--primary-color);">Ingest Prospective Lead Target</div>
    <form action="leads.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) auto; gap: 1.25rem; align-items: end;">
        <input type="hidden" name="action" value="add_lead">
        
        <div class="form-group" style="margin: 0;">
            <label>Prospect Name</label>
            <input type="text" name="customer_name" class="form-control" required placeholder="e.g. Tariq Mehmood" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1;">
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label>Email Coordinate</label>
            <input type="email" name="email" class="form-control" placeholder="prospect@domain.com" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1;">
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label>Channel Source</label>
            <select name="source" class="form-control" style="cursor: pointer; width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1;">
                <option value="Direct Call">Direct Call</option>
                <option value="Website Form">Website Form</option>
                <option value="Social Media">Social Media</option>
                <option value="Reference App">Reference App</option>
            </select>
        </div>
        
        <button type="submit" class="btn btn--primary" style="height: 42px; padding: 0 1.5rem; display: inline-flex; align-items: center; justify-content: center; font-weight: 600;">Track Prospect</button>
    </form>
</div>

<!-- ==================== DESKTOP TABLE VIEW ==================== -->
<div class="data-table-wrapper desktop-view">
    <table class="data-table">
        <thead>
            <tr>
                <th class="data-table__th">Lead Code</th>
                <th class="data-table__th">Prospect Name</th>
                <th class="data-table__th">Channel Source</th>
                <th class="data-table__th">Status Flag</th>
                <th class="data-table__th">Pipeline Officer</th>
                <th class="data-table__th" style="text-align: center;">Pipeline Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($leads) > 0): foreach($leads as $lead): ?>
            <tr class="data-table__tr">
                <td class="data-table__td" style="font-weight: 600;">#LE-<?php echo $lead['id']; ?></td>
                <td class="data-table__td"><strong><?php echo htmlspecialchars($lead['customer_name']); ?></strong></td>
                <td class="data-table__td"><?php echo htmlspecialchars($lead['source']); ?></td>
                <td class="data-table__td">
                    <span style="font-weight:600; padding: 0.35rem 0.65rem; border-radius: 4px; font-size: 0.8rem; display: inline-block; text-align: center; background-color: <?php echo $lead['status'] === 'Converted' ? '#e8f8f5; color:#2ecc71;' : '#ebf5fb; color:#3498db;'; ?>">
                        <?php echo htmlspecialchars($lead['status']); ?>
                    </span>
                </td>
                <td class="data-table__td"><?php echo htmlspecialchars($lead['agent_name'] ?: 'Unassigned'); ?></td>
                <td class="data-table__td" style="text-align: center;">
                    <?php if($lead['status'] !== 'Converted'): ?>
                        <form action="leads.php" method="POST" style="display:inline-block; margin: 0;">
                            <input type="hidden" name="action" value="convert_lead">
                            <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                            <button type="submit" class="btn btn--sm" style="background-color: var(--success-color); color: white; border: none; padding: 0.45rem 1rem; font-size: 0.8rem; font-weight: 600; border-radius: 4px;">Convert Client</button>
                        </form>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-size:0.85rem; font-weight:600; display: inline-flex; align-items: center; gap: 0.25rem;">
                            🟢 Account Activated
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="6" class="data-table__td" style="text-align:center; padding: 3rem; color:#64748b; font-size: 0.95rem;">No prospective leads discovered inside pipeline metrics.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ==================== MOBILE CARD VIEW ==================== -->
<div class="mobile-view">
    <?php if(count($leads) > 0): foreach($leads as $lead): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">#LE-<?php echo $lead['id']; ?></span>
                <span style="font-weight:600; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; display: inline-block; text-align: center; background-color: <?php echo $lead['status'] === 'Converted' ? '#e8f8f5; color:#2ecc71;' : '#ebf5fb; color:#3498db;'; ?>">
                    <?php echo htmlspecialchars($lead['status']); ?>
                </span>
            </div>
            <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Prospect Name</span>
                    <strong style="color: #1e293b;"><?php echo htmlspecialchars($lead['customer_name']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Channel Source</span>
                    <span style="color: #334155;"><?php echo htmlspecialchars($lead['source']); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Pipeline Officer</span>
                    <span style="color: #334155; font-weight: 600;">👤 <?php echo htmlspecialchars($lead['agent_name'] ?: 'Unassigned'); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; margin-top: 0.25rem;">
                    <span style="color: #64748b; font-size: 0.85rem;">Pipeline Action</span>
                    <div>
                        <?php if($lead['status'] !== 'Converted'): ?>
                            <form action="leads.php" method="POST" style="display:inline-block; margin: 0;">
                                <input type="hidden" name="action" value="convert_lead">
                                <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                <button type="submit" class="btn btn--sm" style="background-color: var(--success-color); color: white; border: none; padding: 0.45rem 1rem; font-size: 0.8rem; font-weight: 600; border-radius: 4px;">Convert Client</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#94a3b8; font-size:0.85rem; font-weight:600; display: inline-flex; align-items: center; gap: 0.25rem;">
                                🟢 Account Activated
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; else: ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; text-align: center; color: #64748b;">
            No prospective leads discovered inside pipeline metrics.
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>