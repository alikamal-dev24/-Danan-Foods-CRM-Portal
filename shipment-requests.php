<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

// Get user session details
$current_role = $_SESSION['role'] ?? 'Support'; 
$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

$message = '';
$shipment_requests = [];

// Ensure shipment tracking tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS shipment_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('Pending', 'Accepted', 'Declined') DEFAULT 'Pending',
        total_amount DECIMAL(10,2) DEFAULT '0.00'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS shipment_request_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        quantity INT NOT NULL,
        price_per_unit DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (request_id) REFERENCES shipment_requests(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Database setup failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// --- CHECK ROLE PRIVILEGES ---
$is_admin_or_manager = ($current_role === 'Admin' || $current_role === 'Manager');

// --- SUBMISSION LOGIC: REGULAR USER REQUEST SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request']) && !$is_admin_or_manager) {
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    $valid_selections = false;
    foreach ($product_ids as $index => $prod_id) {
        if (!empty($prod_id) && intval($quantities[$index] ?? 0) > 0) {
            $valid_selections = true;
            break;
        }
    }

    if ($valid_selections) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO shipment_requests (user_id, username, status) VALUES (?, ?, 'Pending')");
            $stmt->execute([$user_id, $username]);
            $request_id = $pdo->lastInsertId();

            $total_amount = 0;

            foreach ($product_ids as $index => $prod_id) {
                $prod_id = intval($prod_id);
                $qty = intval($quantities[$index] ?? 0);

                if ($prod_id > 0 && $qty > 0) {
                    $p_stmt = $pdo->prepare("SELECT product_name, unit_price FROM inventory WHERE id = ?");
                    $p_stmt->execute([$prod_id]);
                    $product = $p_stmt->fetch();

                    if ($product) {
                        $prod_name = $product['product_name'];
                        $price = $product['unit_price'];
                        $item_total = $price * $qty;
                        $total_amount += $item_total;

                        $i_stmt = $pdo->prepare("INSERT INTO shipment_request_items (request_id, product_id, product_name, quantity, price_per_unit) VALUES (?, ?, ?, ?, ?)");
                        $i_stmt->execute([$request_id, $prod_id, $prod_name, $qty, $price]);
                    }
                }
            }

            $u_stmt = $pdo->prepare("UPDATE shipment_requests SET total_amount = ? WHERE id = ?");
            $u_stmt->execute([$total_amount, $request_id]);

            $pdo->commit();

            $message = "
            <div class='alert alert--success' style='border-left: 5px solid #10b981; background: #f0fdf4; padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem;'>
                <h4 style='margin: 0 0 5px 0; color: #14532d;'>🎉 Request #$request_id Submitted Successfully!</h4>
                <p style='margin: 0; font-size: 0.9rem; color: #15803d;'>Your shipment request has been logged and is awaiting approval from management.</p>
            </div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Error placing request: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='alert alert--warning' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fef3c7; color: #92400e;'>Please select at least one product with a quantity of 1 or more.</div>";
    }
}

// --- ADMINISTRATIVE DECISIONS (ACCEPT / DECLINE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_approval']) && $is_admin_or_manager) {
    $request_id = intval($_POST['req_id']);
    $action = $_POST['action_type'];
    $admin_note = trim($_POST['admin_note'] ?? '');

    if ($action === 'accept') {
        try {
            $pdo->beginTransaction();

            $items_stmt = $pdo->prepare("SELECT * FROM shipment_request_items WHERE request_id = ?");
            $items_stmt->execute([$request_id]);
            $items = $items_stmt->fetchAll();

            foreach ($items as $item) {
                $p_id = $item['product_id'];
                $qty = $item['quantity'];
                $p_name = $item['product_name'];

                $stock_chk = $pdo->prepare("SELECT available_stock FROM inventory WHERE id = ?");
                $stock_chk->execute([$p_id]);
                $warehouse_stock = $stock_chk->fetchColumn() ?: 0;

                if ($warehouse_stock < $qty) {
                    throw new Exception("Insufficient stock for '$p_name'. (In Stock: $warehouse_stock, Requested: $qty)");
                }

                $deduct = $pdo->prepare("UPDATE inventory SET available_stock = available_stock - ? WHERE id = ?");
                $deduct->execute([$qty, $p_id]);
            }

            $update_status = $pdo->prepare("UPDATE shipment_requests SET status = 'Accepted' WHERE id = ?");
            $update_status->execute([$request_id]);

            $req_stmt = $pdo->prepare("SELECT user_id, username, total_amount FROM shipment_requests WHERE id = ?");
            $req_stmt->execute([$request_id]);
            $req_data = $req_stmt->fetch();

            if ($req_data) {
                $update_balance = $pdo->prepare("UPDATE customers SET total_balance_due = total_balance_due + ? WHERE id = ? OR username = ?");
                $update_balance->execute([$req_data['total_amount'], $req_data['user_id'], $req_data['username']]);

                $cust_stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? OR username = ? LIMIT 1");
                $cust_stmt->execute([$req_data['user_id'], $req_data['username']]);
                $valid_customer_id = $cust_stmt->fetchColumn() ?: null;

                $log_details = "Shipment Request #REQ-" . $request_id . " Accepted by " . $username . " (" . $current_role . ").";
                if (!empty($admin_note)) {
                    $log_details .= " Note: " . $admin_note;
                }
                
                $int_stmt = $pdo->prepare("INSERT INTO interactions (customer_id, staff_id, type, notes) VALUES (?, ?, 'Shipment Approval', ?)");
                $int_stmt->execute([
                    $valid_customer_id,
                    $user_id,
                    $log_details
                ]);
            }

            $pdo->commit();

            $message = "
            <div class='alert alert--success' style='border-left: 5px solid #10b981; background: #f0fdf4; padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem;'>
                <h4 style='margin: 0 0 5px 0; color: #14532d;'>✅ Request #REQ-$request_id Approved!</h4>
                <p style='margin: 0; font-size: 0.9rem; color: #15803d;'>Stock updated, account balance updated, and interaction logged.</p>
            </div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Approval Failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } elseif ($action === 'decline') {
        try {
            $update_status = $pdo->prepare("UPDATE shipment_requests SET status = 'Declined' WHERE id = ?");
            $update_status->execute([$request_id]);
            
            $req_stmt = $pdo->prepare("SELECT user_id, username FROM shipment_requests WHERE id = ?");
            $req_stmt->execute([$request_id]);
            $req_data = $req_stmt->fetch();

            $valid_customer_id = null;
            if ($req_data) {
                $cust_stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? OR username = ? LIMIT 1");
                $cust_stmt->execute([$req_data['user_id'], $req_data['username']]);
                $valid_customer_id = $cust_stmt->fetchColumn() ?: null;
            }

            $log_details = "Shipment Request #REQ-" . $request_id . " Declined by " . $username . " (" . $current_role . ").";
            if (!empty($admin_note)) {
                $log_details .= " Reason: " . $admin_note;
            }
            
            $int_stmt = $pdo->prepare("INSERT INTO interactions (customer_id, staff_id, type, notes) VALUES (?, ?, 'Shipment Rejection', ?)");
            $int_stmt->execute([
                $valid_customer_id,
                $user_id,
                $log_details
            ]);

            $message = "<div class='alert alert--warning' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fef3c7; color: #92400e;'>Request #$request_id declined successfully.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Execution failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// --- FETCH LOGGED DATA ---
try {
    if ($is_admin_or_manager) {
        $shipment_requests = $pdo->query("SELECT * FROM shipment_requests ORDER BY id DESC LIMIT 20")->fetchAll();
    } else {
        $req_stmt = $pdo->prepare("SELECT * FROM shipment_requests WHERE user_id = ? ORDER BY id DESC LIMIT 10");
        $req_stmt->execute([$user_id]);
        $shipment_requests = $req_stmt->fetchAll();
    }
} catch (Exception $e) {
    $message = "<div class='alert alert--danger' style='padding: 1rem; margin-bottom: 1rem; border-radius: 6px; background-color: #fee2e2; color: #991b1b;'>Error fetching records: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Catalog list for dynamic rows
$catalog = [];
try {
    $products_query = $pdo->query("SELECT id, product_name, unit_price FROM inventory ORDER BY product_name ASC")->fetchAll();
    foreach ($products_query as $prod) {
        $catalog[$prod['id']] = [
            'name' => $prod['product_name'],
            'price' => floatval($prod['unit_price'])
        ];
    }
} catch (Exception $e) {
    // Graceful fallback
}
?>

<style>
/* Modern Fluid Responsive Overrides matching payment.php */
.shipment-container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.product-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 40px;
    gap: 15px;
    align-items: end;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px dashed #f1f5f9;
}

/* Responsive Grid Adjustments for smaller devices */
@media (max-width: 768px) {
    .product-row {
        grid-template-columns: 1fr;
        gap: 10px;
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .product-row > div {
        width: 100% !important;
    }
    
    .remove-row-btn {
        width: 100% !important;
        height: 40px !important;
        justify-content: center;
    }

    .form-submit-container {
        width: 100% !important;
    }

    .action-panel {
        padding: 1rem !important;
    }
}

/* Mobile Stacked Layout Cards for Data Tables */
.desktop-table-view {
    display: block;
}

.mobile-card-view {
    display: none;
}

@media (max-width: 768px) {
    .desktop-table-view {
        display: none !important;
    }
    .mobile-card-view {
        display: flex !important;
        flex-direction: column;
        gap: 1rem;
    }
}

.m-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.m-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.m-card-body div {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
    padding: 0.3rem 0;
}

/* Touch friendly control inputs */
.form-control, select.form-control, input.form-control {
    min-height: 44px;
    font-size: 1rem;
}
</style>

<!-- Portal Overlay and Topbar -->
<div class="portal-overlay" id="portalOverlay" onclick="toggleMobileMenu()"></div>
<header class="portal-topbar">
    <button class="menu-trigger-btn" onclick="toggleMobileMenu()" aria-label="Toggle Navigation Menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="4" y1="12" x2="20" y2="12"></line>
            <line x1="4" y1="6" x2="20" y2="6"></line>
            <line x1="4" y1="18" x2="20" y2="18"></line>
        </svg>
    </button>
    <div class="topbar-brand">🍇 Danan Foods</div>
    <div style="width: 24px;" class="desktop-hidden"></div>
</header>

<div class="shipment-container">
    <?php if (!empty($message)) echo $message; ?>

    <div class="dashboard-header" style="margin-bottom: 2rem;">
        <h2 class="section-title" style="color: var(--primary-color);">
            <?php echo $is_admin_or_manager ? '📥 Shipment Approvals Center' : '📦 Order New Stock Shipment'; ?>
        </h2>
        <p style="color: #64748b;">Manage and monitor incoming shipment orders easily.</p>
    </div>

    <?php if (!$is_admin_or_manager): ?>
        <form method="POST" action="">
            <div class="action-panel" style="margin-bottom: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #ffffff; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px;">
                    <h3 class="action-panel__title" style="color: var(--primary-color); margin: 0; font-size: 1.15rem;">
                        Manifest Cargo Allocation Item List
                    </h3>
                    <button type="button" id="addRowBtn" class="btn" style="background: #2ec4b6; color: white; border: none; font-weight: 600; padding: 0.6rem 1rem; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; min-height: 44px;">
                        + Add Another Product
                    </button>
                </div>

                <div id="productRowsContainer">
                    <div class="product-row">
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Product Asset Selection</label>
                            <select name="product_ids[]" class="form-control prod-select" style="width:100%; padding: 0.6rem; border-radius:6px; border:1px solid #cbd5e1;" required>
                                <option value="">-- Choose Stock Item --</option>
                                <?php foreach ($catalog as $id => $p): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($p['name']); ?> (Rs. <?php echo number_format($p['price'], 2); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Quantity Sent</label>
                            <input type="number" name="quantities[]" class="form-control prod-qty" min="1" value="1" style="width:100%; padding: 0.6rem; border-radius:6px; border:1px solid #cbd5e1;" required>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Item Base Cost Subtotal</label>
                            <input type="text" class="form-control prod-subtotal" readonly value="Rs. 0.00" style="width:100%; padding: 0.6rem; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; font-weight:600; color:#334155;">
                        </div>
                        <div style="padding-bottom: 4px;">
                            <button type="button" class="btn remove-row-btn" style="background: #fecaca; color: #dc2626; border: none; font-weight: bold; width: 36px; height: 36px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-bottom: 2rem;">
                <div class="form-submit-container" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; padding: 1.5rem; width: 320px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #64748b; font-size: 0.95rem;">
                        <span>Gross Total:</span>
                        <span id="grossTotalDisplay" style="font-weight: 600; color: #1e293b;">Rs. 0.00</span>
                    </div>
                    <div style="border-top: 1px dashed #cbd5e1; margin: 0.75rem 0; padding-top: 0.75rem; display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 700; color: var(--primary-color);">
                        <span>Net Payable:</span>
                        <span id="netPayableDisplay" style="color: #3b82f6;">Rs. 0.00</span>
                    </div>
                    <button type="submit" name="submit_request" class="btn btn--primary" style="width: 100%; padding: 0.75rem; font-weight: 600; border-radius: 6px; margin-top: 1rem; min-height: 44px;">
                        Send Shipment Request 🚀
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($is_admin_or_manager): ?>
        <div class="action-panel" style="margin-bottom: 2rem; background: #ffffff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <h3 class="action-panel__title" style="color: var(--primary-color); margin-bottom: 1rem;">🔑 Pending Operational Requests</h3>
            
            <!-- Desktop Table View -->
            <div class="data-table-wrapper desktop-table-view">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="data-table__th">Request ID</th>
                            <th class="data-table__th">Created By</th>
                            <th class="data-table__th">Requested Items (Qty)</th>
                            <th class="data-table__th">Date Logged</th>
                            <th class="data-table__th">Total Amount Due</th>
                            <th class="data-table__th">Status</th>
                            <th class="data-table__th" style="text-align: right;">Decisions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pending_found = false;
                        if (!empty($shipment_requests)): 
                            foreach ($shipment_requests as $req): 
                                if ($req['status'] !== 'Pending') continue; 
                                $pending_found = true;

                                $items_query = $pdo->prepare("SELECT product_name, quantity, price_per_unit FROM shipment_request_items WHERE request_id = ?");
                                $items_query->execute([$req['id']]);
                                $requested_items = $items_query->fetchAll();
                        ?>
                            <tr class="data-table__tr">
                                <td class="data-table__td" style="font-weight: 700;">#REQ-<?php echo $req['id']; ?></td>
                                <td class="data-table__td"><strong><?php echo htmlspecialchars($req['username']); ?></strong></td>
                                <td class="data-table__td" style="max-width: 300px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.85rem;">
                                        <?php foreach ($requested_items as $itm): ?>
                                            <div style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; color: #334155; border-left: 3px solid #3b82f6;">
                                                <strong><?php echo htmlspecialchars($itm['product_name']); ?></strong> 
                                                <span style="color: #64748b; margin-left: 5px;">(x<?php echo $itm['quantity']; ?>)</span>
                                                <span style="float: right; font-weight: 600; color: #0f172a;">Rs. <?php echo number_format($itm['price_per_unit'] * $itm['quantity'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="data-table__td"><?php echo date('M d, Y h:i A', strtotime($req['requested_at'])); ?></td>
                                <td class="data-table__td" style="font-weight: 700;">Rs. <?php echo number_format($req['total_amount'], 2); ?></td>
                                <td class="data-table__td"><span class="badge badge--warning">Pending</span></td>
                                <td class="data-table__td" style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn--sm btn--success" onclick="openApprovalModal(<?php echo $req['id']; ?>, 'accept')" style="margin-right: 5px; padding: 6px 12px; cursor: pointer; min-height: 38px;">Accept ✅</button>
                                    <button type="button" class="btn btn--sm" onclick="openApprovalModal(<?php echo $req['id']; ?>, 'decline')" style="background: #fee2e2; color: #dc2626; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; min-height: 38px;">Decline ❌</button>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        if (!$pending_found): 
                        ?>
                            <tr>
                                <td colspan="7" class="data-table__td" style="text-align: center; color: #94a3b8; padding: 2rem;">No pending shipment requests.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View for Pending -->
            <div class="mobile-card-view">
                <?php 
                $mobile_pending_found = false;
                if (!empty($shipment_requests)) {
                    foreach ($shipment_requests as $req) {
                        if ($req['status'] !== 'Pending') continue;
                        $mobile_pending_found = true;
                        
                        $items_query = $pdo->prepare("SELECT product_name, quantity, price_per_unit FROM shipment_request_items WHERE request_id = ?");
                        $items_query->execute([$req['id']]);
                        $requested_items = $items_query->fetchAll();
                ?>
                    <div class="m-card">
                        <div class="m-card-header">
                            <span>#REQ-<?php echo $req['id']; ?></span>
                            <span class="badge badge--warning">Pending</span>
                        </div>
                        <div class="m-card-body">
                            <div><span style="color:#64748b;">Created By:</span> <strong><?php echo htmlspecialchars($req['username']); ?></strong></div>
                            <div><span style="color:#64748b;">Date:</span> <span><?php echo date('M d, Y h:i A', strtotime($req['requested_at'])); ?></span></div>
                            <div><span style="color:#64748b;">Total Due:</span> <strong style="color:var(--primary-color);">Rs. <?php echo number_format($req['total_amount'], 2); ?></strong></div>
                        </div>
                        <div style="margin-top: 0.75rem; border-top: 1px dashed #f1f5f9; padding-top: 0.5rem;">
                            <span style="font-size: 0.8rem; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Items:</span>
                            <div style="display: flex; flex-direction: column; gap: 4px; font-size: 0.85rem;">
                                <?php foreach ($requested_items as $itm): ?>
                                    <div style="background: #f8fafc; padding: 4px 8px; border-radius: 4px; display: flex; justify-content: space-between;">
                                        <span><?php echo htmlspecialchars($itm['product_name']); ?> (x<?php echo $itm['quantity']; ?>)</span>
                                        <strong>Rs. <?php echo number_format($itm['price_per_unit'] * $itm['quantity'], 2); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 1rem;">
                            <button type="button" class="btn btn--success" onclick="openApprovalModal(<?php echo $req['id']; ?>, 'accept')" style="flex:1; min-height: 44px; justify-content: center;">Accept ✅</button>
                            <button type="button" class="btn" onclick="openApprovalModal(<?php echo $req['id']; ?>, 'decline')" style="flex:1; background: #fee2e2; color: #dc2626; border: none; min-height: 44px; justify-content: center; font-weight:600;">Decline ❌</button>
                        </div>
                    </div>
                <?php 
                    }
                }
                if (!$mobile_pending_found): 
                ?>
                    <div style="text-align: center; color: #94a3b8; padding: 1.5rem; background: #f8fafc; border-radius: 8px;">No pending shipment requests.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- APPROVAL MODAL -->
    <div id="approvalModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); align-items: center; justify-content: center; z-index: 9999; padding: 1rem;">
        <div style="background: #ffffff; padding: 1.5rem; border-radius: 12px; width: 100%; max-width: 480px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <h3 id="modalTitle" style="margin-top: 0; color: #1e293b; font-size: 1.2rem;">Process Shipment Request</h3>
            <form method="POST" action="">
                <input type="hidden" name="req_id" id="modalReqId" value="">
                <input type="hidden" name="action_type" id="modalActionType" value="">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">
                        Note/Message for User (Saved to Interactions Log):
                    </label>
                    <textarea name="admin_note" rows="4" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1; font-family: inherit; height: auto;" placeholder="Write a response note to the user..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                    <button type="button" onclick="closeApprovalModal()" class="btn" style="background: #e2e8f0; color: #475569; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer; font-weight: 600; min-height: 44px;">Cancel</button>
                    <button type="submit" name="process_approval" id="modalSubmitBtn" class="btn" style="color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer; font-weight: 600; min-height: 44px;">Submit Decision</button>
                </div>
            </form>
        </div>
    </div>

    <div class="action-panel" style="background: #ffffff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <h3 class="action-panel__title" style="margin-bottom: 1.25rem; color: var(--primary-color);">📋 Shipment Request History</h3>
        
        <!-- Desktop History Table View -->
        <div class="data-table-wrapper desktop-table-view">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="data-table__th">Request ID</th>
                        <th class="data-table__th">Requested By</th>
                        <th class="data-table__th">Items Summary</th>
                        <th class="data-table__th">Date Submitted</th>
                        <th class="data-table__th">Total Value</th>
                        <th class="data-table__th">Execution Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($shipment_requests)): foreach ($shipment_requests as $s_req): 
                        $hist_items_query = $pdo->prepare("SELECT product_name, quantity FROM shipment_request_items WHERE request_id = ?");
                        $hist_items_query->execute([$s_req['id']]);
                        $hist_items = $hist_items_query->fetchAll();
                    ?>
                        <tr class="data-table__tr">
                            <td class="data-table__td" style="font-weight: 600; color: var(--primary-color);">#REQ-<?php echo $s_req['id']; ?></td>
                            <td class="data-table__td"><strong><?php echo htmlspecialchars($s_req['username']); ?></strong></td>
                            <td class="data-table__td">
                                <div style="font-size: 0.8rem; max-width: 280px; display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php foreach ($hist_items as $h_itm): ?>
                                        <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo htmlspecialchars($h_itm['product_name']); ?> <strong>(x<?php echo $h_itm['quantity']; ?>)</strong>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="data-table__td"><?php echo date('M d, Y h:i A', strtotime($s_req['requested_at'])); ?></td>
                            <td class="data-table__td" style="font-weight:700;">Rs. <?php echo number_format($s_req['total_amount'], 2); ?></td>
                            <td class="data-table__td">
                                <?php if ($s_req['status'] === 'Pending'): ?>
                                    <span class="badge badge--warning">Pending</span>
                                <?php elseif ($s_req['status'] === 'Accepted'): ?>
                                    <span class="badge badge--success">Accepted</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#fee2e2; color:#ef4444;">Declined</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" class="data-table__td" style="text-align:center; padding: 2.5rem; color:#64748b;">
                                No historical records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card History View -->
        <div class="mobile-card-view">
            <?php if (!empty($shipment_requests)): foreach ($shipment_requests as $s_req): 
                $hist_items_query = $pdo->prepare("SELECT product_name, quantity FROM shipment_request_items WHERE request_id = ?");
                $hist_items_query->execute([$s_req['id']]);
                $hist_items = $hist_items_query->fetchAll();
            ?>
                <div class="m-card">
                    <div class="m-card-header">
                        <span style="color:var(--primary-color);">#REQ-<?php echo $s_req['id']; ?></span>
                        <?php if ($s_req['status'] === 'Pending'): ?>
                            <span class="badge badge--warning">Pending</span>
                        <?php elseif ($s_req['status'] === 'Accepted'): ?>
                            <span class="badge badge--success">Accepted</span>
                        <?php else: ?>
                            <span class="badge" style="background:#fee2e2; color:#ef4444;">Declined</span>
                        <?php endif; ?>
                    </div>
                    <div class="m-card-body">
                        <div><span style="color:#64748b;">User:</span> <strong><?php echo htmlspecialchars($s_req['username']); ?></strong></div>
                        <div><span style="color:#64748b;">Date:</span> <span><?php echo date('M d, Y h:i A', strtotime($s_req['requested_at'])); ?></span></div>
                        <div><span style="color:#64748b;">Value:</span> <strong style="color:var(--primary-color);">Rs. <?php echo number_format($s_req['total_amount'], 2); ?></strong></div>
                    </div>
                    <div style="margin-top: 0.5rem; border-top: 1px dashed #f1f5f9; padding-top: 0.5rem;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Items Summary:</span>
                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                            <?php foreach ($hist_items as $h_itm): ?>
                                <span style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">
                                    <?php echo htmlspecialchars($h_itm['product_name']); ?> (x<?php echo $h_itm['quantity']; ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div style="text-align: center; color: #64748b; padding: 1.5rem; background: #f8fafc; border-radius: 8px;">No historical records found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Define toggleMobileMenu globally matching other portal pages
window.toggleMobileMenu = function() {
    document.body.classList.toggle('mobile-menu-open');
};

const catalog = <?php echo json_encode(!empty($catalog) ? $catalog : (object)[], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function openApprovalModal(reqId, action) {
    document.getElementById('modalReqId').value = reqId;
    document.getElementById('modalActionType').value = action;
    const modal = document.getElementById('approvalModal');
    const title = document.getElementById('modalTitle');
    const btn = document.getElementById('modalSubmitBtn');
    
    if (action === 'accept') {
        title.innerText = "Accept Request #REQ-" + reqId;
        btn.innerText = "Accept Request";
        btn.style.background = "#10b981";
    } else {
        title.innerText = "Decline Request #REQ-" + reqId;
        btn.innerText = "Decline Request";
        btn.style.background = "#ef4444";
    }
    modal.style.display = 'flex';
}

function closeApprovalModal() {
    document.getElementById('approvalModal').style.display = 'none';
}

// Form calculations initialization on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('productRowsContainer');
    const addRowBtn = document.getElementById('addRowBtn');
    
    if (!container || !addRowBtn) return; 

    function calculateRow(row) {
        const select = row.querySelector('.prod-select');
        const qtyInput = row.querySelector('.prod-qty');
        const subtotalInput = row.querySelector('.prod-subtotal');
        
        if (!select || !qtyInput || !subtotalInput) return;

        const productId = select.value;
        const qty = parseInt(qtyInput.value, 10) || 0;
        
        let subtotal = 0;
        if (productId && catalog[productId]) {
            subtotal = catalog[productId].price * qty;
        }
        
        subtotalInput.value = "Rs. " + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        subtotalInput.setAttribute('data-value', subtotal);
        
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let grandTotal = 0;
        const subtotals = container.querySelectorAll('.prod-subtotal');
        
        subtotals.forEach(function(input) {
            const val = parseFloat(input.getAttribute('data-value')) || 0;
            grandTotal += val;
        });

        const formattedTotal = "Rs. " + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const grossDisplay = document.getElementById('grossTotalDisplay');
        const netDisplay = document.getElementById('netPayableDisplay');
        
        if (grossDisplay) grossDisplay.innerText = formattedTotal;
        if (netDisplay) netDisplay.innerText = formattedTotal;
    }

    container.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('prod-qty')) {
            calculateRow(e.target.closest('.product-row'));
        }
    });

    container.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('prod-select')) {
            calculateRow(e.target.closest('.product-row'));
        }
    });

    container.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-row-btn');
        if (removeBtn) {
            const rows = container.querySelectorAll('.product-row');
            if (rows.length > 1) {
                removeBtn.closest('.product-row').remove();
                calculateGrandTotal();
            } else {
                alert('At least one item row is required.');
            }
        }
    });

    addRowBtn.addEventListener('click', function() {
        const firstRow = container.querySelector('.product-row');
        if (!firstRow) return;

        const newRow = firstRow.cloneNode(true);
        const select = newRow.querySelector('.prod-select');
        const qty = newRow.querySelector('.prod-qty');
        const subtotal = newRow.querySelector('.prod-subtotal');

        if (select) select.value = '';
        if (qty) qty.value = '1';
        if (subtotal) {
            subtotal.value = 'Rs. 0.00';
            subtotal.removeAttribute('data-value');
        }

        container.appendChild(newRow);
    });
});
</script>