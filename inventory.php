<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

// Access Control
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager' && $_SESSION['role'] !== 'Modifier') {
    echo "<div class='action-panel' style='max-width: 500px; margin: 4rem auto; text-align: center; border-top: 4px solid var(--danger-color);'>";
    echo "<h2 style='color: var(--danger-color); margin-bottom: 0.5rem;'>Access Denied</h2>";
    echo "<p style='color: #64748b;'>You do not possess the required privileges to inspect warehouse stock assets.</p>";
    echo "<a href='dashboard.php' class='btn btn--primary' style='margin-top: 1.5rem; display: inline-flex;'>Return to Dashboard</a>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit;
}

// Ensure stock_movements log table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        movement_type ENUM('IN', 'OUT') NOT NULL,
        quantity INT NOT NULL,
        reference_note VARCHAR(255) DEFAULT NULL,
        performed_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table creation error handler
}

$msg = ''; $err = '';
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$edit_data = null;

if ($edit_id > 0) {
    $e_stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $e_stmt->execute([$edit_id]);
    $edit_data = $e_stmt->fetch();
}

// --- LOGIC DISPATCHER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Action 1: Add Brand New Product
    if ($_POST['action'] === 'add_product') {
        $product_name = trim($_POST['product_name']);
        $sku = trim($_POST['sku']);
        $stock = intval($_POST['available_stock']);
        $price = floatval($_POST['unit_price']);
        $performed_by = $_SESSION['username'] ?? 'System';

        if (!empty($product_name) && !empty($sku)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO inventory (product_name, sku, available_stock, unit_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$product_name, $sku, $stock, $price]);
                $new_product_id = $pdo->lastInsertId();

                // Log Movement History if initial stock > 0
                if ($stock > 0) {
                    $log_stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, reference_note, performed_by) VALUES (?, 'IN', ?, 'Initial Stock Intake', ?)");
                    $log_stmt->execute([$new_product_id, $stock, $performed_by]);
                }

                $pdo->commit();
                $msg = "New asset item saved successfully.";
            } catch (Exception $e) { 
                $pdo->rollBack();
                $err = "Error adding product: " . $e->getMessage(); 
            }
        }
    }

    // Action 2: Adjust Existing Stock Quantity (Add / Remove)
    if ($_POST['action'] === 'adjust_stock') {
        $product_id = intval($_POST['product_id']);
        $type = $_POST['type'] === 'OUT' ? 'OUT' : 'IN';
        $quantity = intval($_POST['quantity']);
        $note = trim($_POST['reference_note']) ?: 'Manual Inventory Adjustment';
        $performed_by = $_SESSION['username'] ?? 'System';

        if ($product_id > 0 && $quantity > 0) {
            try {
                $pdo->beginTransaction();

                // Fetch current stock
                $p_stmt = $pdo->prepare("SELECT available_stock FROM inventory WHERE id = ?");
                $p_stmt->execute([$product_id]);
                $current_stock = $p_stmt->fetchColumn();

                if ($type === 'OUT' && $quantity > $current_stock) {
                    throw new Exception("Cannot remove more stock than currently available ({$current_stock} units).");
                }

                // Calculate updated quantity
                $new_stock = ($type === 'IN') ? ($current_stock + $quantity) : ($current_stock - $quantity);

                // Update product table
                $u_stmt = $pdo->prepare("UPDATE inventory SET available_stock = ? WHERE id = ?");
                $u_stmt->execute([$new_stock, $product_id]);

                // Record history movement
                $log_stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, reference_note, performed_by) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute([$product_id, $type, $quantity, $note, $performed_by]);

                $pdo->commit();
                $msg = "Stock quantity adjusted successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $err = $e->getMessage();
            }
        }
    }

    // Action 3: Edit Full Product Details
    if ($_POST['action'] === 'update_product') {
        $id = intval($_POST['id']);
        $product_name = trim($_POST['product_name']);
        $sku = trim($_POST['sku']);
        $stock = intval($_POST['available_stock']);
        $price = floatval($_POST['unit_price']);

        if ($id > 0 && !empty($product_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE inventory SET product_name = ?, sku = ?, available_stock = ?, unit_price = ? WHERE id = ?");
                $stmt->execute([$product_name, $sku, $stock, $price, $id]);
                $msg = "Product details updated perfectly.";
                echo "<script>window.location.href='inventory.php';</script>";
                exit;
            } catch (Exception $e) { $err = "Error saving asset changes: " . $e->getMessage(); }
        }
    }
}

// Fetch Inventory Items
$inventory = $pdo->query("SELECT * FROM inventory ORDER BY id DESC")->fetchAll();

// Fetch Stock Movement History with Product Names
$movements = $pdo->query("SELECT sm.*, i.product_name, i.sku 
                          FROM stock_movements sm 
                          LEFT JOIN inventory i ON sm.product_id = i.id 
                          ORDER BY sm.created_at DESC LIMIT 50")->fetchAll();
?>

<style>
/* Responsive Display Switcher Styling */
.desktop-view { display: block; }
.desktop-view .data-table { display: table; width: 100%; }
.mobile-view { display: none; }

@media (max-width: 768px) {
    .desktop-view { display: none !important; }
    .mobile-view { display: flex !important; flex-direction: column; gap: 1rem; }
}
</style>

<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h2 class="section-title" style="color: var(--primary-color);">Warehouse Stocks Asset Monitor 📦</h2>
    <p style="color: #64748b;">Manage core structural warehouse reserves, track physical product SKUs, and monitor unit asset evaluations.</p>
</div>

<?php if (!empty($msg)): ?>
    <div style="background:#e8f8f5; color:#2ecc71; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #d1f2eb; font-weight: 500;">
        🟢 <?php echo $msg; ?>
    </div>
<?php endif; ?>

<?php if (!empty($err)): ?>
    <div style="background:#fde8e8; color:#e74c3c; padding:1rem; border-radius:6px; margin-bottom:1.5rem; border:1px solid #fecdcd; font-weight: 500;">
        ❌ <?php echo $err; ?>
    </div>
<?php endif; ?>

<?php if (!$edit_data): ?>
<div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    
    <!-- 1. Provision New Product -->
    <div class="action-panel">
        <div class="action-panel__title" style="font-weight: 700; margin-bottom: 1rem;">➕ Provision New Material Product</div>
        <form action="inventory.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: end;">
            <input type="hidden" name="action" value="add_product">
            
            <div class="form-group" style="margin: 0;">
                <label>Product Title</label>
                <input type="text" name="product_name" class="form-control" required placeholder="e.g. Premium Milk Pack">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Item SKU / Barcode</label>
                <input type="text" name="sku" class="form-control" required placeholder="e.g. MILK-PRM-01">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Initial Units Count</label>
                <input type="number" name="available_stock" class="form-control" required value="0" min="0">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Base Rate Price (PKR)</label>
                <input type="number" step="0.01" name="unit_price" class="form-control" required value="0.00" min="0">
            </div>
            
            <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="btn btn--primary" style="width: 100%; max-width: 200px; height: 42px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">Add Product</button>
            </div>
        </form>
    </div>

    <!-- 2. Quick Stock In / Out Adjustment Panel -->
    <div class="action-panel" style="border-top: 4px solid #3b82f6;">
        <div class="action-panel__title" style="font-weight: 700; margin-bottom: 1rem; color: #1e40af;">🔄 Adjust Stock Quantities (Incoming / Outgoing)</div>
        <form action="inventory.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: end;">
            <input type="hidden" name="action" value="adjust_stock">
            
            <div class="form-group" style="margin: 0;">
                <label>Select Product</label>
                <select name="product_id" class="form-control" required>
                    <option value="">-- Choose Product --</option>
                    <?php foreach ($inventory as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>">
                            <?php echo htmlspecialchars($prod['product_name']); ?> (Curr: <?php echo $prod['available_stock']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label>Movement Type</label>
                <select name="type" class="form-control" required>
                    <option value="IN">🟢 Stock In (+ Add)</option>
                    <option value="OUT">🔴 Stock Out (- Remove)</option>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label>Quantity Units</label>
                <input type="number" name="quantity" class="form-control" min="1" required value="1">
            </div>

            <div class="form-group" style="margin: 0;">
                <label>Reason / Reference Note</label>
                <input type="text" name="reference_note" class="form-control" placeholder="e.g. Shipment Intake / Damage Writeoff">
            </div>

            <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                <button type="submit" class="btn" style="width: 100%; max-width: 220px; height: 42px; font-weight: 600; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                    Update Stock Quantity
                </button>
            </div>
        </form>
    </div>

</div>
<?php endif; ?>

<!-- ==================== CURRENT INVENTORY SECTION ==================== -->
<div style="margin-top:2rem;">
    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--primary-color);">📦 Current Warehouse Assets</h3>

    <!-- DESKTOP TABLE VIEW -->
    <div class="data-table-wrapper desktop-view">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="data-table__th">ID</th>
                    <th class="data-table__th">Product Label</th>
                    <th class="data-table__th">SKU Tracking Code</th>
                    <th class="data-table__th">Stock Volume Remaining</th>
                    <th class="data-table__th">Unit Price Rate</th>
                    <th class="data-table__th" style="text-align:center;">Action Configuration Links</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($inventory) > 0): foreach ($inventory as $item): ?>
                <tr class="data-table__tr" <?php echo ($edit_id === $item['id']) ? 'style="background: #fffbeb;"' : ''; ?>>
                    <td class="data-table__td" style="font-weight: 600;">#PROD-<?php echo $item['id']; ?></td>
                    <td class="data-table__td"><strong><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></strong></td>
                    <td class="data-table__td" style="font-family:monospace; color:#64748b; font-weight: 600;"><?php echo htmlspecialchars($item['sku'] ?? ''); ?></td>
                    <td class="data-table__td">
                        <span style="font-weight: 700; color: <?php echo $item['available_stock'] > 10 ? 'inherit' : 'var(--danger-color)'; ?>">
                            <?php echo $item['available_stock']; ?> Units
                        </span>
                    </td>
                    <td class="data-table__td" style="font-weight: 600; color: var(--primary-color);">Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="data-table__td" style="text-align:center;">
                        <a href="inventory.php?edit_id=<?php echo $item['id']; ?>" class="btn btn--sm" style="background: var(--warning-color); color: black; font-weight: 600; padding: 0.45rem 0.85rem; border-radius: 4px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                            ✏️ Edit Asset
                        </a>
                    </td>
                </tr>
                
                <?php if ($edit_data && $edit_data['id'] == $item['id']): ?>
                <tr>
                    <td colspan="6" style="background: #fffbeb; padding: 1.75rem; border-left: 4px solid #f59e0b; border-right: 4px solid #f59e0b; border-bottom: 4px solid #f59e0b;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem;">
                            <span style="font-size: 1.15rem;">⚙️</span>
                            <h4 style="margin: 0; color:#b45309; font-weight: 700; font-size: 1rem;">Modify Physical Stock Parameter Settings</h4>
                        </div>
                        <form action="inventory.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 1.25rem; align-items: end;">
                            <input type="hidden" name="action" value="update_product">
                            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                            
                            <div class="form-group" style="margin: 0;">
                                <label style="color:#b45309; font-weight: 600;">Product Title</label>
                                <input type="text" name="product_name" class="form-control" style="border-color: #f59e0b; background: #fff;" required value="<?php echo htmlspecialchars($edit_data['product_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label style="color:#b45309; font-weight: 600;">SKU / Barcode</label>
                                <input type="text" name="sku" class="form-control" style="border-color: #f59e0b; background: #fff;" required value="<?php echo htmlspecialchars($edit_data['sku'] ?? ''); ?>">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label style="color:#b45309; font-weight: 600;">Units Count</label>
                                <input type="number" name="available_stock" class="form-control" style="border-color: #f59e0b; background: #fff;" required value="<?php echo $edit_data['available_stock']; ?>" min="0">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label style="color:#b45309; font-weight: 600;">Base Price (PKR)</label>
                                <input type="number" step="0.01" name="unit_price" class="form-control" style="border-color: #f59e0b; background: #fff;" required value="<?php echo $edit_data['unit_price']; ?>" min="0">
                            </div>
                            <div style="display:flex; gap:0.5rem;">
                                <button type="submit" class="btn" style="height:42px; color:white; background:#d97706; font-weight:600; padding: 0 1.25rem; border: none; border-radius:4px; cursor:pointer;">Save Changes</button>
                                <a href="inventory.php" class="btn" style="height:42px; background:#cbd5e1; color:black; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; padding:0 1.25rem; border-radius:4px; font-weight:600;">Cancel</a>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="data-table__td" style="text-align:center; padding: 3rem; color:#64748b; font-size: 0.95rem;">No products or active physical logistics logged inside inventory infrastructure.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARD VIEW -->
    <div class="mobile-view">
        <?php if (count($inventory) > 0): foreach ($inventory as $item): ?>
            <?php if ($edit_data && $edit_data['id'] == $item['id']): ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-top: 4px solid #f59e0b;">
                    <h4 style="margin: 0 0 1rem 0; color: #b45309; font-size: 1rem; font-weight: 700;">Modify #PROD-<?php echo $edit_data['id']; ?></h4>
                    <form action="inventory.php" method="POST" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <input type="hidden" name="action" value="update_product">
                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                        
                        <div>
                            <span style="font-size: 0.8rem; color: #64748b; display: block;">Product Title</span>
                            <input type="text" name="product_name" class="form-control" style="background: #fff;" required value="<?php echo htmlspecialchars($edit_data['product_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: #64748b; display: block;">SKU / Barcode</span>
                            <input type="text" name="sku" class="form-control" style="background: #fff;" required value="<?php echo htmlspecialchars($edit_data['sku'] ?? ''); ?>">
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: #64748b; display: block;">Units Count</span>
                            <input type="number" name="available_stock" class="form-control" style="background: #fff;" required value="<?php echo $edit_data['available_stock']; ?>" min="0">
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: #64748b; display: block;">Base Price (PKR)</span>
                            <input type="number" step="0.01" name="unit_price" class="form-control" style="background: #fff;" required value="<?php echo $edit_data['unit_price']; ?>" min="0">
                        </div>
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.5rem;">
                            <button type="submit" class="btn" style="background: #d97706; color: white; padding: 0.5rem 1rem; font-weight: 600; border-radius: 4px; border: none;">Save</button>
                            <a href="inventory.php" class="btn" style="background: #cbd5e1; color: black; padding: 0.5rem 1rem; font-weight: 600; border-radius: 4px; text-decoration: none;">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;">
                        <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">#PROD-<?php echo $item['id']; ?></span>
                        <span style="background: #eff6ff; color: #1d4ed8; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem;"><?php echo $item['available_stock']; ?> Units</span>
                    </div>
                    <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Product Label</span>
                            <strong style="color: #1e293b;"><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">SKU Tracking Code</span>
                            <span style="font-family: monospace; color: #334155; font-weight: 600;"><?php echo htmlspecialchars($item['sku'] ?? ''); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Unit Price Rate</span>
                            <strong style="color: var(--primary-color);">Rs. <?php echo number_format($item['unit_price'], 2); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; margin-top: 0.25rem;">
                            <span style="color: #64748b;">Action Configuration</span>
                            <a href="inventory.php?edit_id=<?php echo $item['id']; ?>" class="btn btn--sm" style="background: var(--warning-color); color: black; font-weight: 600; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                ✏️ Edit Asset
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; else: ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; text-align: center; color: #64748b;">
                No products or active physical logistics logged inside inventory infrastructure.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== STOCK MOVEMENTS HISTORY SECTION ==================== -->
<div style="margin-top: 3rem; margin-bottom: 2rem;">
    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #1e293b;">📜 Stock Audit & Movement History</h3>

    <!-- DESKTOP TABLE VIEW -->
    <div class="data-table-wrapper desktop-view">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="data-table__th">Date / Time</th>
                    <th class="data-table__th">Product</th>
                    <th class="data-table__th">Movement</th>
                    <th class="data-table__th">Quantity</th>
                    <th class="data-table__th">Note / Reason</th>
                    <th class="data-table__th">Performed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($movements) > 0): foreach ($movements as $m): ?>
                <tr class="data-table__tr">
                    <td class="data-table__td" style="font-size: 0.85rem; color: #64748b;">
                        <?php echo date('d M Y, h:i A', strtotime($m['created_at'])); ?>
                    </td>
                    <td class="data-table__td">
                        <strong><?php echo htmlspecialchars($m['product_name'] ?? 'Deleted Product'); ?></strong>
                        <span style="font-size:0.75rem; color:#94a3b8; display:block;"><?php echo htmlspecialchars($m['sku'] ?? ''); ?></span>
                    </td>
                    <td class="data-table__td">
                        <?php if ($m['movement_type'] === 'IN'): ?>
                            <span style="background: #dcfce7; color: #15803d; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                                STOCK IN
                            </span>
                        <?php else: ?>
                            <span style="background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                                STOCK OUT
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="data-table__td" style="font-weight: 700;">
                        <?php echo ($m['movement_type'] === 'IN' ? '+' : '-') . $m['quantity']; ?> Units
                    </td>
                    <td class="data-table__td" style="color: #475569; font-size: 0.9rem;">
                        <?php echo htmlspecialchars($m['reference_note'] ?? 'N/A'); ?>
                    </td>
                    <td class="data-table__td" style="font-weight: 600; color: #334155;">
                        👤 <?php echo htmlspecialchars($m['performed_by'] ?? 'System'); ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="data-table__td" style="text-align:center; padding: 2rem; color:#64748b;">No stock activity logged yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARD VIEW -->
    <div class="mobile-view">
        <?php if (count($movements) > 0): foreach ($movements as $m): ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <strong style="color: #1e293b; font-size: 0.95rem; display: block;"><?php echo htmlspecialchars($m['product_name'] ?? 'Deleted Product'); ?></strong>
                        <span style="font-size: 0.75rem; color: #94a3b8; font-family: monospace;"><?php echo htmlspecialchars($m['sku'] ?? ''); ?></span>
                    </div>
                    <div>
                        <?php if ($m['movement_type'] === 'IN'): ?>
                            <span style="background: #dcfce7; color: #15803d; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                                +<?php echo $m['quantity']; ?> Units
                            </span>
                        <?php else: ?>
                            <span style="background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                                -<?php echo $m['quantity']; ?> Units
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.6rem; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Movement Type</span>
                        <span>
                            <?php if ($m['movement_type'] === 'IN'): ?>
                                <span style="background: #dcfce7; color: #15803d; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">STOCK IN</span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #b91c1c; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">STOCK OUT</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Note / Reason</span>
                        <span style="color: #475569; text-align: right;"><?php echo htmlspecialchars($m['reference_note'] ?? 'N/A'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Performed By</span>
                        <strong style="color: #334155;">👤 <?php echo htmlspecialchars($m['performed_by'] ?? 'System'); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; font-size: 0.85rem; color: #64748b;">
                        <span>Date / Time</span>
                        <span><?php echo date('d M Y, h:i A', strtotime($m['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; text-align: center; color: #64748b;">
                No stock activity logged yet.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>