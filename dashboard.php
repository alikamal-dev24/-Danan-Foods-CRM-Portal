<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

$current_role = $_SESSION['role'] ?? 'Sales';
$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

// Filter selection (default: all)
$period_filter = $_GET['period'] ?? 'all';

// Generate SQL WHERE conditions based on period filter
$shipment_date_where = "";
$payment_date_where = "";

switch ($period_filter) {
    case 'today':
        $shipment_date_where = " WHERE DATE(shipment_date) = CURDATE()";
        $payment_date_where = " WHERE DATE(payment_date) = CURDATE()";
        break;
    case 'weekly':
        $shipment_date_where = " WHERE shipment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $payment_date_where = " WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'monthly':
        $shipment_date_where = " WHERE shipment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $payment_date_where = " WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'yearly':
        $shipment_date_where = " WHERE shipment_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $payment_date_where = " WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    default:
        $shipment_date_where = "";
        $payment_date_where = "";
        break;
}

// Fallback initializations
$kpi_sales = 0; 
$kpi_receivables = 0; 
$kpi_count = 0; 
$kpi_cash_received = 0;
$kpi_bank_received = 0;
$kpi_total_received = 0;
$shipments = [];
$payments = [];
$message = '';

// Setup tables if they do not exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        amount_paid DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    $message = "<div class='alert alert--danger'>Database setup failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// --- SUBMISSION LOGIC: DISTRIBUTOR REQUEST (SALES) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request']) && $current_role === 'Sales') {
    $quantities = $_POST['qty'] ?? [];
    $selected_products = array_filter($quantities, function($v) { return intval($v) > 0; });

    if (!empty($selected_products)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO shipment_requests (user_id, username, status) VALUES (?, ?, 'Pending')");
            $stmt->execute([$user_id, $username]);
            $request_id = $pdo->lastInsertId();

            $total_amount = 0;

            foreach ($selected_products as $prod_id => $qty) {
                $p_stmt = $pdo->prepare("SELECT product_name, price FROM inventory WHERE id = ?");
                $p_stmt->execute([$prod_id]);
                $product = $p_stmt->fetch();

                if ($product) {
                    $prod_name = $product['product_name'];
                    $price = $product['price'];
                    $item_total = $price * intval($qty);
                    $total_amount += $item_total;

                    $i_stmt = $pdo->prepare("INSERT INTO shipment_request_items (request_id, product_id, product_name, quantity, price_per_unit) VALUES (?, ?, ?, ?, ?)");
                    $i_stmt->execute([$request_id, $prod_id, $prod_name, intval($qty), $price]);
                }
            }

            $u_stmt = $pdo->prepare("UPDATE shipment_requests SET total_amount = ? WHERE id = ?");
            $u_stmt->execute([$total_amount, $request_id]);

            $pdo->commit();

            $download_url = "generate_invoice.php?req_id=" . $request_id . "&download=1";

            $message = "
            <div class='alert alert--success' style='display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;'>
                <span>🎉 <strong>Success!</strong> Request #$request_id submitted. Pending Admin approval.</span>
                <a href='$download_url' target='_blank' class='btn btn--sm' style='background: #0f172a; color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: 600;'>Download Created PDF 📥</a>
            </div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert--danger'>Error placing request: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='alert alert--danger'>Please select a quantity of at least 1 product.</div>";
    }
}

// --- DATA QUERIES RETRIEVAL WITH TIME FILTER ---
try {
    if ($current_role === 'Admin' || $current_role === 'Manager') {
        $kpi_sales = $pdo->query("SELECT SUM(total_bill) FROM product_shipments" . $shipment_date_where)->fetchColumn() ?: 0;
        $kpi_receivables = $pdo->query("SELECT SUM(total_balance_due) FROM customers")->fetchColumn() ?: 0;
        $kpi_count = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?: 0;
        
        $cash_where = empty($payment_date_where) ? " WHERE payment_method = 'Cash'" : $payment_date_where . " AND payment_method = 'Cash'";
        $kpi_cash_received = $pdo->query("SELECT SUM(amount_paid) FROM customer_payments" . $cash_where)->fetchColumn() ?: 0;

        $bank_where = empty($payment_date_where) ? " WHERE payment_method = 'Bank Transfer'" : $payment_date_where . " AND payment_method = 'Bank Transfer'";
        $kpi_bank_received = $pdo->query("SELECT SUM(amount_paid) FROM customer_payments" . $bank_where)->fetchColumn() ?: 0;

        $kpi_total_received = $pdo->query("SELECT SUM(amount_paid) FROM customer_payments" . $payment_date_where)->fetchColumn() ?: 0;

        // Recent Shipments Query
        $shipments = $pdo->query("SELECT s.*, c.full_name, p.product_name 
                                FROM product_shipments s 
                                LEFT JOIN customers c ON s.customer_id = c.id 
                                LEFT JOIN inventory p ON s.product_id = p.id
                                ORDER BY s.id DESC LIMIT 5")->fetchAll();

        // Payment History Query
        $pay_sql = "SELECT p.*, c.full_name 
                    FROM customer_payments p 
                    LEFT JOIN customers c ON p.customer_id = c.id";
        if (!empty($payment_date_where)) {
            $pay_sql .= $payment_date_where;
        }
        $pay_sql .= " ORDER BY p.id DESC LIMIT 10";
        $payments = $pdo->query($pay_sql)->fetchAll();

    } else {
        $c_stmt = $pdo->prepare("SELECT id, total_balance_due FROM customers WHERE username = ? OR id = ? LIMIT 1");
        $c_stmt->execute([$username, $user_id]);
        $client_profile = $c_stmt->fetch();
        
        $customer_id = $client_profile ? intval($client_profile['id']) : 0;
        
        if ($customer_id > 0) {
            $p_where = empty($shipment_date_where) ? " WHERE customer_id = $customer_id" : $shipment_date_where . " AND customer_id = $customer_id";
            $kpi_sales = $pdo->query("SELECT SUM(total_bill) FROM product_shipments" . $p_where)->fetchColumn() ?: 0;
            
            $kpi_receivables = $client_profile['total_balance_due'] ?: 0;
            
            $q_where = empty($shipment_date_where) ? " WHERE customer_id = $customer_id" : $shipment_date_where . " AND customer_id = $customer_id";
            $kpi_count = $pdo->query("SELECT SUM(quantity_sent) FROM product_shipments" . $q_where)->fetchColumn() ?: 0;

            $p_cash_where = empty($payment_date_where) ? " WHERE customer_id = $customer_id AND payment_method = 'Cash'" : $payment_date_where . " AND customer_id = $customer_id AND payment_method = 'Cash'";
            $kpi_cash_received = $pdo->query("SELECT SUM(amount_paid) FROM customer_payments" . $p_cash_where)->fetchColumn() ?: 0;

            $p_bank_where = empty($payment_date_where) ? " WHERE customer_id = $customer_id AND payment_method = 'Bank Transfer'" : $payment_date_where . " AND customer_id = $customer_id AND payment_method = 'Bank Transfer'";
            $kpi_bank_received = $pdo->query("SELECT SUM(amount_paid) FROM customer_payments" . $p_bank_where)->fetchColumn() ?: 0;

            $p_total_where = empty($payment_date_where) ? " WHERE customer_id = $customer_id" : $payment_date_where . " AND customer_id = $customer_id";
            $kpi_total_received = $pdo->query("SELECT SUM(amount_paid) FROM customer_payments" . $p_total_where)->fetchColumn() ?: 0;

            $ship_stmt = $pdo->prepare("SELECT s.*, c.full_name, p.product_name 
                                        FROM product_shipments s 
                                        LEFT JOIN customers c ON s.customer_id = c.id 
                                        LEFT JOIN inventory p ON s.product_id = p.id
                                        WHERE s.customer_id = ?
                                        ORDER BY s.id DESC LIMIT 5");
            $ship_stmt->execute([$customer_id]);
            $shipments = $ship_stmt->fetchAll();

            $pay_user_sql = "SELECT p.*, c.full_name 
                           FROM customer_payments p 
                           LEFT JOIN customers c ON p.customer_id = c.id 
                           WHERE p.customer_id = ?";
            if (!empty($payment_date_where)) {
                $pay_user_sql .= " AND " . ltrim($payment_date_where, ' WHERE');
            }
            $pay_user_sql .= " ORDER BY p.id DESC LIMIT 10";
            
            $p_stmt = $pdo->prepare($pay_user_sql);
            $p_stmt->execute([$customer_id]);
            $payments = $p_stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    $error_log_msg = $e->getMessage();
}
?>

<?php if (!empty($message)) echo $message; ?>

<!-- Responsive Custom CSS Layer for Layout Enhancements & Mobile Cards -->
<style>
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: stretch !important;
    }
    .dashboard-header > div {
        width: 100%;
    }
    .filter-container {
        width: 100%;
        justify-content: space-between;
    }
    .filter-container form {
        flex-grow: 1;
    }
    .filter-container select {
        width: 100%;
    }
    .kpi-grid {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
    .action-panel {
        padding: 1rem !important;
    }
    
    /* Transform tables into stacked cards on mobile */
    .data-table-responsive thead {
        display: none;
    }
    .data-table-responsive, .data-table-responsive tbody, .data-table-responsive tr, .data-table-responsive td {
        display: block;
        width: 100%;
    }
    .data-table-responsive tr {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 0.75rem;
    }
    .data-table-responsive td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: right !important;
        padding: 0.5rem 0.25rem !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .data-table-responsive td:last-child {
        border-bottom: none !important;
    }
    .data-table-responsive td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #64748b;
        text-align: left;
    }
}
</style>

<div class="dashboard-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 class="section-title" style="color: var(--primary-color); font-size: clamp(1.25rem, 2.5vw, 1.75rem);">Welcome Back, <?php echo htmlspecialchars($username); ?> 👋</h2>
        <p style="color: #64748b; font-size: 0.95rem;">
            Danan Foods Portal Engine &bull; <strong><?php echo htmlspecialchars($current_role); ?> Console Panel</strong>
        </p>
    </div>
    
    <!-- Time Filter Selector -->
    <div class="filter-container" style="display: flex; align-items: center; gap: 10px; background: #ffffff; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <label for="kpi_period_filter" style="font-weight: 600; font-size: 0.85rem; color: #475569; margin: 0; white-space: nowrap;">📅 Filter Period:</label>
        <form method="GET" action="dashboard.php" style="margin: 0; display: flex; flex-grow: 1;">
            <select name="period" id="kpi_period_filter" onchange="this.form.submit()" class="form-control" style="padding: 0.35rem 0.75rem; font-size: 0.85rem; cursor: pointer;">
                <option value="all" <?php echo $period_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="today" <?php echo $period_filter === 'today' ? 'selected' : ''; ?>>Today (1 Day)</option>
                <option value="weekly" <?php echo $period_filter === 'weekly' ? 'selected' : ''; ?>>This Week (7 Days)</option>
                <option value="monthly" <?php echo $period_filter === 'monthly' ? 'selected' : ''; ?>>This Month (30 Days)</option>
                <option value="yearly" <?php echo $period_filter === 'yearly' ? 'selected' : ''; ?>>This Year (365 Days)</option>
            </select>
        </form>
    </div>
</div>

<!-- Primary System KPIs -->
<div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
    <div class="kpi-card" style="border-left: 5px solid var(--success-color); background: #fff; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="kpi-card__title" style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
            <?php echo ($current_role === 'Admin' || $current_role === 'Manager') ? 'Global Channel Gross Sales' : 'Your Total Purchases'; ?>
        </div>
        <div class="kpi-card__value" style="font-size: 1.5rem; font-weight: 700; color: #0f172a;">
            Rs. <?php echo number_format($kpi_sales, 2); ?>
        </div>
    </div>
    
    <div class="kpi-card" style="border-left: 5px solid var(--danger-color); background: #fff; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="kpi-card__title" style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
            <?php echo ($current_role === 'Admin' || $current_role === 'Manager') ? 'Total Outstanding Receivables' : 'Your Remaining Balance Due'; ?>
        </div>
        <div class="kpi-card__value" style="font-size: 1.5rem; font-weight: 700; color: var(--danger-color);">
            Rs. <?php echo number_format($kpi_receivables, 2); ?>
        </div>
    </div>
    
    <div class="kpi-card" style="border-left: 5px solid var(--accent-color); background: #fff; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="kpi-card__title" style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
            <?php echo ($current_role === 'Admin' || $current_role === 'Manager') ? 'Active Distributors' : 'Total Units Shipped To You'; ?>
        </div>
        <div class="kpi-card__value" style="font-size: 1.5rem; font-weight: 700; color: var(--accent-color);">
            <?php echo number_format($kpi_count); ?> <?php echo ($current_role === 'Admin' || $current_role === 'Manager') ? 'Active Channels' : 'Units Logged'; ?>
        </div>
    </div>
</div>

<!-- Collections & Payment Received Widgets -->
<div class="kpi-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="kpi-card" style="border-left: 5px solid #16a085; background: #f0fdf4; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="kpi-card__title" style="font-size: 0.85rem; color: #166534; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
            💵 Cash Collected
        </div>
        <div class="kpi-card__value" style="font-size: 1.5rem; font-weight: 700; color: #15803d;">
            Rs. <?php echo number_format($kpi_cash_received, 2); ?>
        </div>
    </div>

    <div class="kpi-card" style="border-left: 5px solid #2563eb; background: #eff6ff; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="kpi-card__title" style="font-size: 0.85rem; color: #1e40af; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
            🏦 Bank Transfers Received
        </div>
        <div class="kpi-card__value" style="font-size: 1.5rem; font-weight: 700; color: #1d4ed8;">
            Rs. <?php echo number_format($kpi_bank_received, 2); ?>
        </div>
    </div>

    <div class="kpi-card" style="border-left: 5px solid #7c3aed; background: #f5f3ff; padding: 1.25rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="kpi-card__title" style="font-size: 0.85rem; color: #5b21b6; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">
            💳 Total Payments Received (Combined)
        </div>
        <div class="kpi-card__value" style="font-size: 1.5rem; font-weight: 700; color: #6d28d9;">
            Rs. <?php echo number_format($kpi_total_received, 2); ?>
        </div>
    </div>
</div>

<?php if ($current_role === 'Sales'): ?>
    <div class="action-panel" style="background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2rem;">
        <h3 class="action-panel__title" style="color: var(--primary-color); margin-top: 0; font-size: 1.2rem;">📦 Request a New Shipment</h3>
        <p style="margin-bottom: 1.5rem; color: #64748b; font-size: 0.9rem;">Select products and input your desired quantities. Your request will be instantly processed by administration.</p>
        
        <form method="POST" action="">
            <div class="data-table-wrapper" style="width: 100%; overflow-x: auto;">
                <table class="data-table" style="width: 100%; border-collapse: collapse; min-width: 500px;">
                    <thead>
                        <tr>
                            <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Product Item Description</th>
                            <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Price Rate</th>
                            <th class="data-table__th" style="width: 160px; text-align: center; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Desired Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $products = $pdo->query("SELECT id, product_name, price FROM inventory ORDER BY product_name ASC")->fetchAll();
                        if (!empty($products)): foreach ($products as $p):
                        ?>
                            <tr class="data-table__tr">
                                <td class="data-table__td" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-weight: 600;"><?php echo htmlspecialchars($p['product_name']); ?></td>
                                <td class="data-table__td" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9;">Rs. <?php echo number_format($p['price'], 2); ?></td>
                                <td class="data-table__td" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                                    <input type="number" name="qty[<?php echo $p['id']; ?>]" class="form-control" min="0" value="0" style="text-align: center; width: 100%; padding: 0.35rem; box-sizing: border-box;">
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="data-table__td" style="padding: 1rem; text-align: center;">No assets registered inside storage inventory systems.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                <button type="submit" name="submit_request" class="btn btn--primary" style="padding: 0.5rem 1.25rem; font-weight: 600; cursor: pointer;">Submit Order Request</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Payment History Panel -->
<div class="action-panel" style="background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 2rem;">
    <div class="action-panel__title" style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h3 style="color: var(--primary-color); margin: 0; font-size: 1.2rem;">💳 Recent Payment History</h3>
    </div>
    
    <div class="data-table-wrapper" style="width: 100%; overflow-x: auto;">
        <table class="data-table data-table-responsive" style="width: 100%; border-collapse: collapse; min-width: auto;">
            <thead>
                <tr>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Payment Ref</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Distributor / Account</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Amount Paid</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Payment Method</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Payment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): foreach ($payments as $pay): ?>
                    <tr class="data-table__tr">
                        <td class="data-table__td" data-label="Payment Ref" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: var(--primary-color);">#PAY-<?php echo $pay['id']; ?></td>
                        <td class="data-table__td" data-label="Distributor / Account" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9;"><strong><?php echo htmlspecialchars($pay['full_name'] ?? 'Unknown Account'); ?></strong></td>
                        <td class="data-table__td" data-label="Amount Paid" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: var(--success-color);">
                            Rs. <?php echo number_format($pay['amount_paid'], 2); ?>
                        </td>
                        <td class="data-table__td" data-label="Payment Method" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                            <?php if (($pay['payment_method'] ?? '') === 'Bank Transfer'): ?>
                                <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">🏦 Bank Transfer</span>
                            <?php else: ?>
                                <span class="badge" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">💵 Cash</span>
                            <?php endif; ?>
                        </td>
                        <td class="data-table__td" data-label="Payment Date" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: #64748b;">
                            <?php echo date('M d, Y h:i A', strtotime($pay['payment_date'])); ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="data-table__td" style="text-align: center; color: #94a3b8; padding: 2rem;">
                            No payment records found for the selected period.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Shipment Deliveries History Panel -->
<div class="action-panel" style="background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div class="action-panel__title" style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h3 style="color: var(--primary-color); margin: 0; font-size: 1.2rem;">📦 Recent Deliveries Dispatched History</h3>
    </div>
    
    <div class="data-table-wrapper" style="width: 100%; overflow-x: auto;">
        <table class="data-table data-table-responsive" style="width: 100%; border-collapse: collapse; min-width: auto;">
            <thead>
                <tr>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Ref Invoice</th>
                    <?php if ($current_role === 'Admin' || $current_role === 'Manager'): ?>
                        <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Distributor Link</th>
                    <?php endif; ?>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Product Item Allocation</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Quantity Sent</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Total Value Sum</th>
                    <th class="data-table__th" style="text-align: left; padding: 0.75rem; border-bottom: 2px solid #e2e8f0;">Timestamp Dispatch</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($shipments)): foreach ($shipments as $ship): ?>
                    <tr class="data-table__tr">
                        <td class="data-table__td" data-label="Ref Invoice" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-weight:600; color: var(--primary-color);">#INV-<?php echo $ship['id']; ?></td>
                        <?php if ($current_role === 'Admin' || $current_role === 'Manager'): ?>
                            <td class="data-table__td" data-label="Distributor Link" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9;"><strong><?php echo htmlspecialchars($ship['full_name'] ?? 'Unknown Account'); ?></strong></td>
                        <?php endif; ?>
                        <td class="data-table__td" data-label="Product Allocation" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9;"><?php echo htmlspecialchars($ship['product_name'] ?? 'Deleted/Unknown Asset'); ?></td>
                        <td class="data-table__td" data-label="Quantity Sent" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #475569;"><?php echo intval($ship['quantity_sent']); ?> Units</td>
                        <td class="data-table__td" data-label="Total Value" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-weight:700; color: #0f172a;">Rs. <?php echo number_format($ship['total_bill'], 2); ?></td>
                        <td class="data-table__td" data-label="Timestamp" style="padding: 0.75rem; border-bottom: 1px solid #f1f5f9; font-size:0.85rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($ship['shipment_date'])); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="<?php echo ($current_role === 'Admin' || $current_role === 'Manager') ? '6' : '5'; ?>" class="data-table__td" style="text-align:center; padding: 3rem; color:#64748b; font-size: 0.95rem;">
                            No authenticated delivery transactions found inside active registry datasets.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>