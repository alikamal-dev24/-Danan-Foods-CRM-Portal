<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager') {
    echo "<h2 style='color:#ef4444; padding:2rem;'>Access Denied.</h2>";
    require_once 'includes/footer.php';
    exit;
}

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    echo "<div class='alert alert--danger' style='margin:2rem;'>Invalid Customer ID specified.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Fetch Customer Profile
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    echo "<div class='alert alert--danger' style='margin:2rem;'>Customer record not found.</div>";
    require_once 'includes/footer.php';
    exit;
}

// -------------------------------------------------------------
// METRICS CALCULATIONS
// -------------------------------------------------------------

// 1. Total Remaining Balance
$total_remaining_balance = $customer['total_balance_due'];

// 2. Total Monthly Sales (Current Calendar Month)
$sales_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_bill), 0) AS monthly_sales 
    FROM product_shipments 
    WHERE customer_id = ? 
      AND MONTH(shipment_date) = MONTH(CURRENT_DATE()) 
      AND YEAR(shipment_date) = YEAR(CURRENT_DATE())
");
$sales_stmt->execute([$customer_id]);
$monthly_sales = $sales_stmt->fetchColumn();


// -------------------------------------------------------------
// HISTORICAL DATA SETS
// -------------------------------------------------------------

// Complete Shipment History
$shipment_stmt = $pdo->prepare("
    SELECT s.*, p.product_name, p.unit_price 
    FROM product_shipments s
    JOIN inventory p ON s.product_id = p.id
    WHERE s.customer_id = ?
    ORDER BY s.shipment_date DESC
");
$shipment_stmt->execute([$customer_id]);
$shipment_history = $shipment_stmt->fetchAll();

// Complete Payment History
$payment_stmt = $pdo->prepare("
    SELECT p.*, b.bank_name, b.account_number 
    FROM customer_payments p
    LEFT JOIN bank_accounts b ON p.bank_account_id = b.id
    WHERE p.customer_id = ?
    ORDER BY p.payment_date DESC
");
$payment_stmt->execute([$customer_id]);
$payment_history = $payment_stmt->fetchAll();
?>

<style>
/* Modern Responsive Layout Styles */
:root {
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-muted: #64748b;
    --primary-blue: #3b82f6;
    --accent-green: #10b981;
    --accent-red: #ef4444;
}

.customer-detail-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    font-family: inherit;
}

.cd-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.cd-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.cd-table th {
    background: #f8fafc;
    color: var(--text-muted);
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.85rem;
    border-bottom: 1px solid var(--border-color);
    text-align: left;
}
.cd-table td {
    padding: 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.9rem;
}

/* Mobile Responsive Card Layout */
@media (max-width: 868px) {
    .cd-table, .cd-table tbody, .cd-table tr, .cd-table td {
        display: block;
        width: 100%;
    }
    .cd-table thead { display: none; }
    .cd-table tr {
        margin-bottom: 1rem;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.5rem;
    }
    .cd-table td {
        text-align: right;
        padding: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .cd-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.8rem;
    }
}
</style>

<div class="customer-detail-wrapper">
    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="customers.php" style="text-decoration: none; color: #64748b; font-size: 0.9rem;">← Back to Customers List</a>
            <h2 class="section-title" style="color: var(--primary-color); margin-top: 0.4rem; font-size: 1.4rem;"><?php echo htmlspecialchars($customer['full_name']); ?> — Ledger & History</h2>
            <p style="color: #64748b; margin: 0; font-size: 0.85rem;">Phone: <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?> | Address: <?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <!-- Top Metrics Widgets -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        
        <!-- Widget 1: Remaining Balance -->
        <div class="cd-card" style="margin-bottom: 0; border-left: 5px solid <?php echo $total_remaining_balance > 0 ? '#ef4444' : '#10b981'; ?>;">
            <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Total Remaining Payment (Due)</div>
            <div style="font-size: 1.75rem; font-weight: 700; color: <?php echo $total_remaining_balance > 0 ? '#dc2626' : '#059669'; ?>; margin-top: 0.35rem;">
                Rs. <?php echo number_format($total_remaining_balance, 2); ?>
            </div>
            <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem;">
                <?php echo $total_remaining_balance > 0 ? 'Outstanding balance to collect' : 'Account balance cleared'; ?>
            </div>
        </div>

        <!-- Widget 2: Monthly Sales -->
        <div class="cd-card" style="margin-bottom: 0; border-left: 5px solid var(--primary-color);">
            <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Total Monthly Sales (<?php echo date('F Y'); ?>)</div>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--primary-color); margin-top: 0.35rem;">
                Rs. <?php echo number_format($monthly_sales, 2); ?>
            </div>
            <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem;">
                Calculated from cargo dispatches this month
            </div>
        </div>

    </div>

    <!-- Dispatch / Shipment History Section -->
    <div class="cd-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="font-weight: 600; color: var(--text-primary);">📦 Product Shipment & Cargo History</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo count($shipment_history); ?> Record(s)</span>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="cd-table">
                <thead>
                    <tr>
                        <th>Ref ID</th>
                        <th>Product Item</th>
                        <th>Qty Sent</th>
                        <th>Calculated Bill</th>
                        <th>Immediate Paid</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($shipment_history)): ?>
                        <?php foreach($shipment_history as $s): ?>
                        <tr>
                            <td data-label="Ref ID" style="font-weight:600;">#INV-<?php echo $s['id']; ?></td>
                            <td data-label="Product"><strong><?php echo htmlspecialchars($s['product_name']); ?></strong></td>
                            <td data-label="Qty Sent"><?php echo $s['quantity_sent']; ?> units</td>
                            <td data-label="Calculated Bill" style="font-weight:600; color:#0f172a;">Rs. <?php echo number_format($s['total_bill'], 2); ?></td>
                            <td data-label="Immediate Paid" style="color: #059669;">Rs. <?php echo number_format($s['amount_paid'], 2); ?></td>
                            <td data-label="Date & Time" style="font-size:0.82rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($s['shipment_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 1.5rem; color:#94a3b8;">No cargo shipments recorded for this customer yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ledger Payments History Section -->
    <div class="cd-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="font-weight: 600; color: var(--text-primary);">💳 Collected Payments & Ledger Entries</span>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo count($payment_history); ?> Record(s)</span>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="cd-table">
                <thead>
                    <tr>
                        <th>Pay Ref</th>
                        <th>Method</th>
                        <th>Bank / Account Details</th>
                        <th>Amount Paid</th>
                        <th>Notes / Ref ID</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payment_history)): ?>
                        <?php foreach($payment_history as $p): ?>
                        <tr>
                            <td data-label="Pay Ref" style="font-weight:600;">#PAY-<?php echo $p['id']; ?></td>
                            <td data-label="Method">
                                <span style="padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.78rem; font-weight: 600; background: <?php echo $p['payment_method'] === 'Cash' ? '#e0f2fe; color:#0369a1;' : '#fef3c7; color:#b45309;'; ?>">
                                    <?php echo $p['payment_method']; ?>
                                </span>
                            </td>
                            <td data-label="Bank Details">
                                <?php if (($p['payment_method'] === 'Bank Transfer' || $p['payment_method'] === 'Bank Cheque') && !empty($p['bank_name'])): ?>
                                    <?php echo htmlspecialchars($p['bank_name']); ?> (<?php echo htmlspecialchars($p['account_number']); ?>)
                                <?php else: ?>
                                    <span style="color: #64748b;">Direct Cash</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Amount Paid" style="font-weight:700; color:#10b981;">Rs. <?php echo number_format($p['amount_paid'], 2); ?></td>
                            <td data-label="Notes" style="font-size:0.82rem; color:#64748b;"><?php echo !empty($p['notes']) ? htmlspecialchars($p['notes']) : '—'; ?></td>
                            <td data-label="Timestamp" style="font-size:0.82rem; color:#64748b;"><?php echo date('M d, Y h:i A', strtotime($p['payment_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 1.5rem; color:#94a3b8;">No payment entries recorded for this customer yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>