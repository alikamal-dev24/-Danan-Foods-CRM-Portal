<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager')) {
    require_once 'includes/header.php';
    echo "<h2 style='color:#ef4444; padding:2rem;'>Access Denied.</h2>";
    require_once 'includes/footer.php';
    exit;
}

// Flash Message Handling
$msg = $_SESSION['flash_msg'] ?? '';
$err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_err']);

// --- 1. Handle Manual Cheque Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
    $payment_id = intval($_POST['payment_id']);
    $new_status = trim($_POST['new_status']);

    if ($payment_id > 0 && in_array($new_status, ['Cleared', 'Hold', 'Bounced', 'Cancelled'])) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM customer_payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();

            if ($payment && $payment['payment_method'] === 'Bank Cheque') {
                $old_status = $payment['status'];
                $amount = floatval($payment['amount_paid']);
                $cust_id = intval($payment['customer_id']);

                if ($old_status !== $new_status) {
                    $up_stmt = $pdo->prepare("UPDATE customer_payments SET status = ? WHERE id = ?");
                    $up_stmt->execute([$new_status, $payment_id]);

                    // Ledger Adjustments:
                    if ($old_status === 'Hold' && $new_status === 'Cleared') {
                        // Cheque Cleared -> Deduct customer balance
                        $bal_stmt = $pdo->prepare("UPDATE customers SET total_balance_due = total_balance_due - ? WHERE id = ?");
                        $bal_stmt->execute([$amount, $cust_id]);
                        $_SESSION['flash_msg'] = "Cheque marked as Cleared! Balance reduced by Rs. " . number_format($amount, 2);
                    } 
                    else if ($old_status === 'Cleared' && ($new_status === 'Bounced' || $new_status === 'Cancelled' || $new_status === 'Hold')) {
                        // Previously cleared cheque bounced or put on hold -> Revert balance
                        $bal_stmt = $pdo->prepare("UPDATE customers SET total_balance_due = total_balance_due + ? WHERE id = ?");
                        $bal_stmt->execute([$amount, $cust_id]);
                        $_SESSION['flash_msg'] = "Cheque status updated to {$new_status}. Rs. " . number_format($amount, 2) . " restored to customer balance.";
                    } 
                    else {
                        $_SESSION['flash_msg'] = "Cheque status updated to {$new_status}.";
                    }
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_err'] = "Error updating cheque status: " . $e->getMessage();
        }
    }
    header("Location: payments.php");
    exit;
}

// --- 2. Handle Adding New Bank Account ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bank') {
    $bank_name = trim($_POST['bank_name']);
    $account_title = trim($_POST['account_title']);
    $account_number = trim($_POST['account_number']);

    if (!empty($bank_name) && !empty($account_number)) {
        $stmt = $pdo->prepare("INSERT INTO bank_accounts (bank_name, account_title, account_number) VALUES (?, ?, ?)");
        $stmt->execute([$bank_name, $account_title, $account_number]);
        $_SESSION['flash_msg'] = "Bank account registered successfully.";
    } else {
        $_SESSION['flash_err'] = "Bank Name and Account Number are required.";
    }

    header("Location: payments.php");
    exit;
}

// --- 3. Handle Processing New Payment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $customer_id = intval($_POST['customer_id']);
    $splits = $_POST['splits'] ?? [];
    $notes = trim($_POST['notes'] ?? '');

    if ($customer_id > 0 && !empty($splits)) {
        $total_payment_collected = 0;
        $total_immediate_deduction = 0;
        $valid_splits = [];
        $today = date('Y-m-d');

        foreach ($splits as $split) {
            $method = trim($split['method'] ?? 'Cash');
            if (!in_array($method, ['Cash', 'Bank Transfer', 'Bank Cheque'])) {
                $method = 'Cash';
            }

            $bank_id = !empty($split['bank_account_id']) ? intval($split['bank_account_id']) : null;
            $cheque_num = !empty($split['cheque_number']) ? trim($split['cheque_number']) : null;
            $cheque_date = !empty($split['cheque_date']) ? $split['cheque_date'] : null;
            $amount = floatval($split['amount'] ?? 0);

            if ($amount > 0) {
                // Rule: Cash & Bank Transfer are ALWAYS Cleared. Cheques default to Hold.
                $status = ($method === 'Bank Cheque') ? 'Hold' : 'Cleared';

                $total_payment_collected += $amount;
                if ($status === 'Cleared') {
                    $total_immediate_deduction += $amount;
                }

                $valid_splits[] = [
                    'method' => $method,
                    'bank_account_id' => ($method === 'Bank Transfer' || $method === 'Bank Cheque') ? $bank_id : null,
                    'cheque_number' => ($method === 'Bank Cheque') ? $cheque_num : null,
                    'cheque_date' => ($method === 'Bank Cheque') ? $cheque_date : null,
                    'amount' => $amount,
                    'status' => $status
                ];
            }
        }

        if ($total_payment_collected > 0 && !empty($valid_splits)) {
            $pdo->beginTransaction();
            try {
                $max_group = $pdo->query("SELECT MAX(payment_group_id) FROM customer_payments")->fetchColumn();
                $group_id = ($max_group && $max_group > 0) ? ($max_group + 1) : 1001;

                $pay_stmt = $pdo->prepare("INSERT INTO customer_payments 
                    (payment_group_id, customer_id, payment_method, bank_account_id, cheque_number, cheque_date, amount_paid, status, notes, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($valid_splits as $p) {
                    $pay_stmt->execute([
                        $group_id,
                        $customer_id,
                        $p['method'],
                        $p['bank_account_id'],
                        $p['cheque_number'],
                        $p['cheque_date'],
                        $p['amount'],
                        $p['status'],
                        $notes,
                        $_SESSION['user_id'] ?? 1
                    ]);
                }

                if ($total_immediate_deduction > 0) {
                    $deduct_stmt = $pdo->prepare("UPDATE customers SET total_balance_due = total_balance_due - ? WHERE id = ?");
                    $deduct_stmt->execute([$total_immediate_deduction, $customer_id]);
                }

                $pdo->commit();
                $_SESSION['flash_msg'] = "Payment group #GRP-{$group_id} recorded successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_err'] = "Failed to process payment: " . $e->getMessage();
            }
        }
    }

    header("Location: payments.php");
    exit;
}

require_once 'includes/header.php';

// Fetch Dropdown Data
$customers = $pdo->query("SELECT id, full_name, total_balance_due FROM customers ORDER BY full_name ASC")->fetchAll();
$banks = $pdo->query("SELECT id, bank_name, account_title, account_number, status FROM bank_accounts ORDER BY bank_name ASC")->fetchAll();

// Fetch Payment History
$raw_payments = $pdo->query("
    SELECT 
        p.*, 
        c.full_name as customer_name,
        b.bank_name,
        b.account_number
    FROM customer_payments p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN bank_accounts b ON p.bank_account_id = b.id
    ORDER BY p.payment_date DESC, p.id DESC
")->fetchAll();

$grouped_history = [];
$today_str = date('Y-m-d');

foreach ($raw_payments as $row) {
    $ref = $row['payment_group_id'] ?: $row['id'];
    
    // Evaluate display status: If Cheque is on Hold & date <= today -> Time Up
    $display_status = $row['status'];
    if ($row['payment_method'] === 'Bank Cheque' && $row['status'] === 'Hold') {
        if (!empty($row['cheque_date']) && $row['cheque_date'] <= $today_str) {
            $display_status = 'Time Up';
        }
    }
    $row['display_status'] = $display_status;

    if (!isset($grouped_history[$ref])) {
        $grouped_history[$ref] = [
            'group_ref' => $ref,
            'customer_name' => $row['customer_name'],
            'payment_date' => $row['payment_date'],
            'notes' => $row['notes'],
            'total_amount' => 0,
            'items' => []
        ];
    }
    $grouped_history[$ref]['total_amount'] += floatval($row['amount_paid']);
    $grouped_history[$ref]['items'][] = $row;
}
?>

<style>
/* Modern UI Styles */
:root {
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-muted: #64748b;
    --primary-blue: #3b82f6;
    --accent-green: #10b981;
    --accent-yellow: #f59e0b;
    --accent-red: #ef4444;
}

.payments-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    font-family: inherit;
}

.pm-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.pm-card__title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

.split-row-grid {
    display: grid;
    grid-template-columns: 1.2fr 1.5fr 1fr 1fr 1fr auto;
    gap: 0.75rem;
    align-items: end;
    background: #f8fafc;
    padding: 0.85rem;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    margin-bottom: 0.75rem;
}

/* Badge Styles */
.badge-status {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    margin-right: 4px;
    margin-bottom: 4px;
}
.badge-status--green { background: #dcfce7; color: #15803d; }
.badge-status--yellow { background: #fef3c7; color: #b45309; }
.badge-status--red { background: #fee2e2; color: #b91c1c; }

/* Table Adjustments */
.pm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.pm-table th {
    background: #f8fafc;
    color: var(--text-muted);
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.85rem;
    border-bottom: 1px solid var(--border-color);
    text-align: left;
}
.pm-table td {
    padding: 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.9rem;
}

/* Modal Styling */
.pm-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}
.pm-modal__content {
    background: #fff;
    width: 100%;
    max-width: 680px;
    border-radius: 12px;
    padding: 1.5rem;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Responsive Mobile Layout for Tables */
@media (max-width: 868px) {
    .split-row-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .pm-table, .pm-table tbody, .pm-table tr, .pm-table td {
        display: block;
        width: 100%;
    }
    .pm-table thead { display: none; }
    .pm-table tr {
        margin-bottom: 1rem;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.5rem;
    }
    .pm-table td {
        text-align: right;
        padding: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pm-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.8rem;
    }
}

/* Bank Accounts Section Specific Styles */
.bank-accounts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}
.bank-account-item {
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.bank-account-details {
    display: none;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed var(--border-color);
    font-size: 0.85rem;
    color: var(--text-muted);
}
.bank-account-item.expanded .bank-account-details {
    display: block;
}
.toggle-arrow-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    color: var(--primary-blue);
    padding: 0;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

/* Mobile Card layout for Bank Accounts if screen is narrow, otherwise listed */
@media (max-width: 768px) {
    .bank-accounts-container {
        grid-template-columns: 1fr;
    }
}

@media print {
    body * { visibility: hidden; }
    #modalPrintArea, #modalPrintArea * { visibility: visible; }
    #modalPrintArea { position: absolute; left: 0; top: 0; width: 100%; border: none; }
    .no-print { display: none !important; }
}
</style>

<div class="payments-wrapper">
    <!-- Header -->
    <div style="margin-bottom: 1.5rem;">
        <h2 style="margin: 0; color: var(--text-primary);">Customer Payments & Cheque Manager 💳</h2>
        <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 0.9rem;">Log transactions, automate statuses, and manage uncleared bank cheques.</p>
    </div>

    <?php if(!empty($msg)): ?><div style="background:#dcfce7; color:#15803d; padding:0.85rem; border-radius:8px; margin-bottom:1rem; font-size:0.9rem;">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if(!empty($err)): ?><div style="background:#fee2e2; color:#b91c1c; padding:0.85rem; border-radius:8px; margin-bottom:1rem; font-size:0.9rem;">❌ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

    <!-- Registered Bank Accounts Section (Listed / Card form with Show Less / Show More Arrows) -->
    <div class="pm-card">
        <div class="pm-card__title">
            <span>🏦 Registered Bank Accounts</span>
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;"><?php echo count($banks); ?> Accounts Registered</span>
        </div>
        
        <?php if (!empty($banks)): ?>
            <div class="bank-accounts-container">
                <?php foreach ($banks as $b): ?>
                    <div class="bank-account-item" id="bank-card-<?php echo $b['id']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--primary-blue);"><?php echo htmlspecialchars($b['bank_name']); ?></span>
                                <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem; margin-top: 2px;"><?php echo htmlspecialchars($b['account_title']); ?></div>
                            </div>
                            <div>
                                <span class="badge-status <?php echo ($b['status'] ?? 'Active') === 'Active' ? 'badge-status--green' : 'badge-status--red'; ?>">
                                    <?php echo htmlspecialchars($b['status'] ?? 'Active'); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Collapsible Details Section with Arrows -->
                        <div class="bank-account-details">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span>Account / IBAN:</span>
                                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($b['account_number']); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Account ID Ref:</span>
                                <span>#ACC-<?php echo $b['id']; ?></span>
                            </div>
                        </div>

                        <div style="margin-top: 0.75rem; display: flex; justify-content: flex-end;">
                            <button type="button" class="toggle-arrow-btn" onclick="toggleBankDetails(<?php echo $b['id']; ?>)">
                                <span class="toggle-text">Show More</span> <span class="toggle-icon">▼</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0; text-align: center; padding: 1rem;">No bank accounts registered yet. Use the form below to add one.</p>
        <?php endif; ?>
    </div>

    <!-- Register Bank Account Form -->
    <div class="pm-card" style="background: #f8fafc;">
        <div class="pm-card__title">🏦 Register New Bank Account</div>
        <form action="payments.php" method="POST" class="form-grid-3">
            <input type="hidden" name="action" value="add_bank">
            <div>
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Bank Name</label>
                <input type="text" name="bank_name" class="form-control" placeholder="e.g. Meezan Bank" required>
            </div>
            <div>
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Account Title</label>
                <input type="text" name="account_title" class="form-control" placeholder="Account Title" required>
            </div>
            <div>
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Account / IBAN</label>
                <input type="text" name="account_number" class="form-control" placeholder="Account / IBAN No." required>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn" style="background: var(--text-primary); color: #fff; width: 100%; height: 38px;">Save Bank Account</button>
            </div>
        </form>
    </div>

    <!-- Payment Process Form -->
    <form action="payments.php" method="POST" id="paymentForm">
        <input type="hidden" name="action" value="process_payment">
        
        <div class="pm-card">
            <div class="pm-card__title">1. Select Customer</div>
            <div style="max-width: 500px;">
                <select name="customer_id" class="form-control" required style="cursor: pointer;">
                    <option value="">-- Choose Customer --</option>
                    <?php foreach($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo htmlspecialchars($c['full_name']); ?> (Due: Rs. <?php echo number_format($c['total_balance_due'], 2); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="pm-card">
            <div class="pm-card__title">
                <span>2. Payment Breakdown</span>
                <button type="button" class="btn btn--sm" id="add_split_row" style="background: var(--accent-green); color: white; border:none; padding:0.4rem 0.8rem; border-radius:6px; cursor:pointer;">+ Add Split Method</button>
            </div>

            <div id="split_rows_container">
                <div class="split-row-grid">
                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Method</label>
                        <select name="splits[0][method]" class="form-control method-select" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Bank Cheque">Bank Cheque</option>
                        </select>
                    </div>

                    <div class="bank-select-group" style="display: none;">
                        <label style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Receiving Bank</label>
                        <select name="splits[0][bank_account_id]" class="form-control bank-select">
                            <option value="">-- Select Bank --</option>
                            <?php foreach($banks as $b): ?>
                                <?php if (($b['status'] ?? 'Active') === 'Active'): ?>
                                    <option value="<?php echo $b['id']; ?>">
                                        <?php echo htmlspecialchars($b['bank_name']); ?> (<?php echo htmlspecialchars($b['account_number']); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cheque-num-group" style="display: none;">
                        <label style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Cheque No.</label>
                        <input type="text" name="splits[0][cheque_number]" class="form-control" placeholder="e.g. CHQ-9921">
                    </div>

                    <div class="cheque-date-group" style="display: none;">
                        <label style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Clearing Date</label>
                        <input type="date" name="splits[0][cheque_date]" class="form-control cheque-date-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div>
                        <label style="font-size:0.75rem; font-weight:600; color:var(--text-muted);">Amount (PKR)</label>
                        <input type="number" step="0.01" min="0.01" name="splits[0][amount]" class="form-control split-amount" placeholder="0.00" required>
                    </div>

                    <button type="button" class="btn remove-split-btn" style="background-color: var(--accent-red); color: white; height: 38px; width: 38px; display: none; padding:0; border:none; border-radius:6px; justify-content:center; align-items:center; cursor:pointer;">✕</button>
                </div>
            </div>

            <div style="margin-top: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Notes / Transaction Reference</label>
                <input type="text" name="notes" class="form-control" placeholder="e.g. Counter deposit details or cheque reference">
            </div>

            <div style="margin-top: 1rem; background: #f8fafc; padding: 0.85rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">Total Collection Amount:</span>
                <span id="lbl_total_payment" style="font-size: 1.2rem; font-weight: 700; color: var(--accent-green);">Rs. 0.00</span>
            </div>
        </div>

        <div style="text-align: right; margin-bottom: 2rem;">
            <button type="submit" class="btn" style="background: var(--primary-blue); color: white; height: 44px; padding: 0 2rem; font-size: 0.95rem; font-weight: 600; border-radius: 8px;">Record Payment Entry</button>
        </div>
    </form>

    <!-- Payment Logs & Filter Controls -->
    <div class="pm-card">
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Payment Receipts Log</h3>
            
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-right: 4px;">Method:</label>
                    <select id="filter_method" style="padding: 0.4rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.85rem;">
                        <option value="ALL">All Methods</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Bank Cheque">Bank Cheque</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-right: 4px;">Cheque Status:</label>
                    <select id="filter_status" style="padding: 0.4rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.85rem;">
                        <option value="ALL">All Statuses</option>
                        <option value="Cleared">Cleared (Success)</option>
                        <option value="Hold">On Hold</option>
                        <option value="Time Up">Time Up (Overdue Red)</option>
                        <option value="Bounced">Bounced / Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>Group Ref</th>
                        <th>Customer</th>
                        <th>Method Breakdown</th>
                        <th>Cheque Status Actions</th>
                        <th>Total Amount</th>
                        <th>Date</th>
                        <th style="text-align: center;">Details</th>
                    </tr>
                </thead>
                <tbody id="receipts_tbody">
                    <?php foreach($grouped_history as $group): 
                        $badges = [];
                        $methods_str = [];
                        $statuses_str = [];
                        $has_cheque = false;

                        foreach ($group['items'] as $it) {
                            $m = $it['payment_method'] ?? 'Cash';
                            $st = $it['display_status'] ?? 'Cleared';

                            $methods_str[] = strtolower($m);
                            $statuses_str[] = strtolower($st);

                            $bg_class = 'badge-status--green';
                            if ($st === 'Hold') { $bg_class = 'badge-status--yellow'; }
                            else if ($st === 'Time Up' || $st === 'Bounced' || $st === 'Cancelled') { $bg_class = 'badge-status--red'; }

                            if ($m === 'Bank Cheque') {
                                $has_cheque = true;
                                $badges[] = "<span class='badge-status {$bg_class}'>Cheque: {$st}</span>";
                            } else {
                                $badges[] = "<span class='badge-status badge-status--green'>{$m}</span>";
                            }
                        }

                        $json_data = htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8');
                        $method_attr = implode(',', array_unique($methods_str));
                        $status_attr = implode(',', array_unique($statuses_str));
                    ?>
                    <tr class="receipt-row" data-methods="<?php echo $method_attr; ?>" data-statuses="<?php echo $status_attr; ?>">
                        <td data-label="Group Ref"><strong>#GRP-<?php echo $group['group_ref']; ?></strong></td>
                        <td data-label="Customer"><strong><?php echo htmlspecialchars($group['customer_name']); ?></strong></td>
                        <td data-label="Methods"><?php echo implode(' ', $badges); ?></td>
                        
                        <td data-label="Cheque Actions">
                            <?php 
                            $cheque_action_rendered = false;
                            foreach($group['items'] as $item): 
                                if($item['payment_method'] === 'Bank Cheque'):
                                    $cheque_action_rendered = true;
                            ?>
                                <div style="margin-bottom: 4px;">
                                    <form action="payments.php" method="POST" style="display:inline-flex; align-items:center;">
                                        <input type="hidden" name="action" value="update_payment_status">
                                        <input type="hidden" name="payment_id" value="<?php echo $item['id']; ?>">
                                        
                                        <select name="new_status" onchange="if(confirm('Change cheque status to ' + this.value + '?')) this.form.submit()" style="font-size:0.75rem; padding:3px 6px; border-radius:4px; border:1px solid var(--border-color); cursor:pointer;">
                                            <option value="" disabled selected>Cheque Status...</option>
                                            <option value="Cleared">🟢 Mark Cleared (Paid)</option>
                                            <option value="Hold">🟡 Keep On Hold</option>
                                            <option value="Bounced">🔴 Mark Bounced</option>
                                            <option value="Cancelled">🚫 Mark Cancelled</option>
                                        </select>
                                    </form>
                                </div>
                            <?php 
                                endif;
                            endforeach; 

                            if (!$cheque_action_rendered) {
                                echo "<span style='color:var(--text-muted); font-size:0.8rem;'>Automated (Success)</span>";
                            }
                            ?>
                        </td>

                        <td data-label="Total Amount" style="font-weight:700; color:var(--accent-green);">Rs. <?php echo number_format($group['total_amount'], 2); ?></td>
                        <td data-label="Date" style="font-size:0.82rem; color:var(--text-muted);"><?php echo date('M d, Y h:i A', strtotime($group['payment_date'])); ?></td>
                        <td data-label="Details" style="text-align:center;">
                            <button type="button" class="btn btn--sm view-details-btn" data-json="<?php echo $json_data; ?>" style="background:var(--primary-blue); color:#fff; border:none; padding:0.35rem 0.75rem; border-radius:6px; cursor:pointer; font-size:0.8rem;">👁️ View Details</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details & PDF Print Modal -->
<div id="detailsModal" class="pm-modal">
    <div class="pm-modal__content" id="modalPrintArea">
        <button type="button" id="closeModalBtn" class="no-print" style="position:absolute; top:16px; right:16px; border:none; background:transparent; font-size:1.2rem; cursor:pointer; color:var(--text-muted);">✕</button>
        <div id="modalContent"></div>
        <div style="margin-top:1.5rem; text-align:right;" class="no-print">
            <button onclick="window.print()" class="btn" style="background:var(--accent-green); color:#fff; border:none; padding:0.5rem 1.25rem; border-radius:6px; cursor:pointer; font-weight:600;">🖨️ Print / Download PDF</button>
        </div>
    </div>
</div>

<script>
// Toggle function for bank account card/list show more / show less arrows
function toggleBankDetails(bankId) {
    const card = document.getElementById('bank-card-' + bankId);
    if (card) {
        card.classList.toggle('expanded');
        const textSpan = card.querySelector('.toggle-text');
        const iconSpan = card.querySelector('.toggle-icon');
        if (card.classList.contains('expanded')) {
            textSpan.textContent = 'Show Less';
            iconSpan.textContent = '▲';
        } else {
            textSpan.textContent = 'Show More';
            iconSpan.textContent = '▼';
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    let splitIndex = 1;
    const container = document.getElementById("split_rows_container");
    const addBtn = document.getElementById("add_split_row");

    function attachRowLogic(row) {
        const methodSelect = row.querySelector(".method-select");
        const bankGroup = row.querySelector(".bank-select-group");
        const chequeNumGroup = row.querySelector(".cheque-num-group");
        const chequeDateGroup = row.querySelector(".cheque-date-group");
        const amountInput = row.querySelector(".split-amount");

        function updateVisibility() {
            const val = methodSelect.value;
            if (val === "Bank Transfer") {
                bankGroup.style.display = "block";
                chequeNumGroup.style.display = "none";
                chequeDateGroup.style.display = "none";
                bankGroup.querySelector("select").required = true;
            } else if (val === "Bank Cheque") {
                bankGroup.style.display = "block";
                chequeNumGroup.style.display = "block";
                chequeDateGroup.style.display = "block";
                bankGroup.querySelector("select").required = true;
            } else {
                bankGroup.style.display = "none";
                chequeNumGroup.style.display = "none";
                chequeDateGroup.style.display = "none";
                bankGroup.querySelector("select").required = false;
            }
        }

        methodSelect.addEventListener("change", updateVisibility);
        amountInput.addEventListener("input", calculateTotalPayment);
        updateVisibility();
    }

    function calculateTotalPayment() {
        let total = 0;
        document.querySelectorAll(".split-amount").forEach(input => {
            let val = parseFloat(input.value) || 0;
            total += val;
        });
        document.getElementById("lbl_total_payment").textContent = "Rs. " + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    if (container.querySelector(".split-row-grid")) {
        attachRowLogic(container.querySelector(".split-row-grid"));
    }

    addBtn.addEventListener("click", function() {
        splitIndex++;
        const firstRow = container.querySelector(".split-row-grid");
        const newRow = firstRow.cloneNode(true);

        const methodSelect = newRow.querySelector(".method-select");
        methodSelect.name = `splits[${splitIndex}][method]`;
        methodSelect.value = "Cash";

        const bankSelect = newRow.querySelector(".bank-select");
        bankSelect.name = `splits[${splitIndex}][bank_account_id]`;
        bankSelect.value = "";

        const chequeNum = newRow.querySelector(".cheque-num-group input");
        chequeNum.name = `splits[${splitIndex}][cheque_number]`;
        chequeNum.value = "";

        const chequeDate = newRow.querySelector(".cheque-date-group input");
        chequeDate.name = `splits[${splitIndex}][cheque_date]`;

        const amtInput = newRow.querySelector(".split-amount");
        amtInput.name = `splits[${splitIndex}][amount]`;
        amtInput.value = "";

        const removeBtn = newRow.querySelector(".remove-split-btn");
        removeBtn.style.display = "inline-flex";
        removeBtn.addEventListener("click", function() {
            newRow.remove();
            calculateTotalPayment();
        });

        container.appendChild(newRow);
        attachRowLogic(newRow);
    });

    // Filtering logic for receipts
    const filterMethod = document.getElementById("filter_method");
    const filterStatus = document.getElementById("filter_status");

    function applyFilters() {
        const mVal = filterMethod.value.toLowerCase();
        const sVal = filterStatus.value.toLowerCase();

        document.querySelectorAll(".receipt-row").forEach(row => {
            const methods = row.getAttribute("data-methods").toLowerCase();
            const statuses = row.getAttribute("data-statuses").toLowerCase();

            let matchMethod = (mVal === "all" || methods.includes(mVal));
            let matchStatus = (sVal === "all" || statuses.includes(sVal));

            if (matchMethod && matchStatus) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    if (filterMethod) filterMethod.addEventListener("change", applyFilters);
    if (filterStatus) filterStatus.addEventListener("change", applyFilters);

    // Modal Details Handling
    const modal = document.getElementById("detailsModal");
    const modalContent = document.getElementById("modalContent");
    const closeModalBtn = document.getElementById("closeModalBtn");

    document.querySelectorAll(".view-details-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const data = JSON.parse(this.getAttribute("data-json"));
            
            let html = `
                <div style="margin-bottom:1rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem;">
                    <h3 style="margin:0; color:var(--text-primary);">Payment Receipt #GRP-${data.group_ref}</h3>
                    <p style="margin:4px 0 0; color:var(--text-muted); font-size:0.85rem;">Date: ${data.payment_date}</p>
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Customer:</strong> ${data.customer_name}
                </div>
                <div style="margin-bottom:1rem;">
                    <strong>Notes:</strong> ${data.notes || 'None'}
                </div>
                <h4 style="margin: 1rem 0 0.5rem; font-size:0.95rem; color:var(--text-primary);">Split Items Breakdown:</h4>
                <table class="pm-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Bank / Cheque Details</th>
                            <th>Status</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.items.forEach(item => {
                let detailsText = "-";
                if (item.payment_method === 'Bank Transfer') {
                    detailsText = `Bank: ${item.bank_name || 'N/A'} (${item.account_number || ''})`;
                } else if (item.payment_method === 'Bank Cheque') {
                    detailsText = `Cheque No: ${item.cheque_number || 'N/A'}<br>Clearing Date: ${item.cheque_date || 'N/A'}<br>Bank: ${item.bank_name || 'N/A'}`;
                }

                let badgeClass = 'badge-status--green';
                if (item.display_status === 'Hold') badgeClass = 'badge-status--yellow';
                if (['Time Up', 'Bounced', 'Cancelled'].includes(item.display_status)) badgeClass = 'badge-status--red';

                html += `
                    <tr>
                        <td data-label="Method"><strong>${item.payment_method}</strong></td>
                        <td data-label="Details">${detailsText}</td>
                        <td data-label="Status"><span class="badge-status ${badgeClass}">${item.display_status}</span></td>
                        <td data-label="Amount" style="text-align:right; font-weight:700;">Rs. ${parseFloat(item.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
                <div style="margin-top:1rem; text-align:right; font-size:1.1rem; font-weight:700; color:var(--accent-green);">
                    Total Group Amount: Rs. ${parseFloat(data.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}
                </div>
            `;

            modalContent.innerHTML = html;
            modal.style.display = "flex";
        });
    });

    if (closeModalBtn) {
        closeModalBtn.addEventListener("click", () => modal.style.display = "none");
    }
    window.addEventListener("click", (e) => {
        if (e.target === modal) modal.style.display = "none";
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>