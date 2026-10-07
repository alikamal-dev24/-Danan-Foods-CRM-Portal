<?php
session_start();
ob_start(); // Buffer output to prevent headers issues

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager')) {
    require_once 'includes/header.php';
    echo "<h2 style='color:#ef4444; padding:2rem;'>Access Denied. Internal logistics entry restricted.</h2>";
    require_once 'includes/footer.php';
    exit;
}

// Ensure safe individual schema migrations so existing columns don't block new ones
try { $pdo->exec("ALTER TABLE product_shipments ADD COLUMN shipment_group_id VARCHAR(50) NULL AFTER id"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE product_shipments ADD COLUMN truck_number VARCHAR(50) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE product_shipments ADD COLUMN builty_no VARCHAR(50) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE product_shipments ADD COLUMN driver_no VARCHAR(50) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE product_shipments ADD COLUMN transport_goods_name VARCHAR(100) NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE product_shipments ADD COLUMN loading_price DECIMAL(10,2) DEFAULT 0.00"); } catch (Exception $e) {}

// Process Form BEFORE including header.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_shipment') {
    $customer_id = intval($_POST['customer_id']); 
    $products = $_POST['products'] ?? []; 
    $discount_type = $_POST['discount_type'] ?? 'percentage'; 
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $total_fair_price = floatval($_POST['total_fair_price'] ?? 0);
    $loading_price = floatval($_POST['loading_price'] ?? 0);

    // Transport & Logistics Fields
    $truck_number = trim($_POST['truck_number'] ?? '');
    $builty_no = trim($_POST['builty_no'] ?? '');
    $driver_no = trim($_POST['driver_no'] ?? '');
    $transport_goods_name = trim($_POST['transport_goods_name'] ?? '');

    if ($customer_id > 0 && !empty($products)) {
        $pdo->beginTransaction();
        try {
            $gross_total_bill = 0;
            $items_summary_text = "";
            $shipment_items_to_save = [];
            $group_id = 'INV-' . date('Ymd') . '-' . rand(100, 999);

            $p_stmt = $pdo->prepare("SELECT product_name, available_stock, unit_price FROM inventory WHERE id = ? FOR UPDATE");

            foreach ($products as $item) {
                $product_id = intval($item['product_id']);
                $qty = intval($item['quantity_sent']);

                if ($product_id <= 0 || $qty <= 0) continue;

                $p_stmt->execute([$product_id]);
                $product = $p_stmt->fetch();

                if (!$product || $product['available_stock'] < $qty) {
                    throw new Exception("Insufficient stock for item: " . ($product['product_name'] ?? "ID $product_id"));
                }

                $item_total = $qty * $product['unit_price'];
                $gross_total_bill += $item_total;

                $shipment_items_to_save[] = [
                    'product_id' => $product_id,
                    'qty' => $qty,
                    'unit_price' => (float)$product['unit_price'],
                    'total' => (float)$item_total,
                    'product_name' => $product['product_name']
                ];
            }

            if (empty($shipment_items_to_save)) {
                throw new Exception("No valid items selected for shipment.");
            }

            $total_additional_charges = $total_fair_price + $loading_price;
            $total_with_additions = $gross_total_bill + $total_additional_charges;

            $discount_amount = 0;
            if ($discount_value > 0) {
                $discount_amount = ($discount_type === 'percentage') ? ($gross_total_bill * ($discount_value / 100)) : $discount_value;
            }

            $final_net_bill = max(0, $total_with_additions - $discount_amount);
            $net_receivable_addition = $final_net_bill - $amount_paid;

            $deduct_stmt = $pdo->prepare("UPDATE inventory SET available_stock = available_stock - ? WHERE id = ?");
            $shipment_stmt = $pdo->prepare("INSERT INTO product_shipments (shipment_group_id, customer_id, product_id, quantity_sent, total_bill, amount_paid, truck_number, builty_no, driver_no, transport_goods_name, loading_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $items_count = count($shipment_items_to_save);

            foreach ($shipment_items_to_save as $save_item) {
                $deduct_stmt->execute([$save_item['qty'], $save_item['product_id']]);

                $proportional_discount_ratio = $gross_total_bill > 0 ? ($final_net_bill / $gross_total_bill) : 0;
                $proportional_item_bill = $save_item['total'] * $proportional_discount_ratio;
                $proportional_paid = $amount_paid / $items_count;

                $shipment_stmt->execute([
                    $group_id,
                    $customer_id, 
                    $save_item['product_id'], 
                    $save_item['qty'], 
                    $proportional_item_bill, 
                    $proportional_paid,
                    $truck_number,
                    $builty_no,
                    $driver_no,
                    $transport_goods_name,
                    $loading_price
                ]);

                $items_summary_text .= "▪️ " . $save_item['product_name'] . " x " . $save_item['qty'] . " = Rs. " . number_format($save_item['total'], 2) . "\n";
            }

            $balance = $pdo->prepare("UPDATE customers SET total_balance_due = total_balance_due + ? WHERE id = ?");
            $balance->execute([$net_receivable_addition, $customer_id]);

            $c_stmt = $pdo->prepare("SELECT full_name, phone, total_balance_due FROM customers WHERE id = ?");
            $c_stmt->execute([$customer_id]);
            $client_data = $c_stmt->fetch();

            $pdo->commit();

            $_SESSION['flash_msg'] = "Invoice $group_id successfully created.";
            
            $whatsapp_url = "";
            if ($client_data && !empty($client_data['phone'])) {
                $clean_phone = preg_replace('/[^0-9]/', '', $client_data['phone']);
                // Backward-compatible check instead of str_starts_with
                if (substr($clean_phone, 0, 1) === '0') {
                    $clean_phone = '92' . substr($clean_phone, 1);
                }

                $text_message = "📄 *DANAN FOODS PVT LTD*\n";
                $text_message .= "*Invoice ID:* " . $group_id . "\n\n";
                $text_message .= "Dear *" . $client_data['full_name'] . "*,\n";
                $text_message .= "Dispatch details:\n\n";
                $text_message .= $items_summary_text . "\n";
                
                if (!empty($transport_goods_name)) $text_message .= "📦 *Goods Name:* " . $transport_goods_name . "\n";
                if (!empty($truck_number)) $text_message .= "🚛 *Truck No:* " . $truck_number . "\n";
                if (!empty($builty_no)) $text_message .= "🧾 *Builty No:* " . $builty_no . "\n";
                if (!empty($driver_no)) $text_message .= "📞 *Driver No:* " . $driver_no . "\n\n";

                $text_message .= "📈 *Gross Base Total:* Rs. " . number_format($gross_total_bill, 2) . "\n";
                if ($total_fair_price > 0) $text_message .= "🚚 *Total Fair Price:* +Rs. " . number_format($total_fair_price, 2) . "\n";
                if ($loading_price > 0) $text_message .= "🏗️ *Loading Price:* +Rs. " . number_format($loading_price, 2) . "\n";
                if ($discount_amount > 0) $text_message .= "🏷️ *Discount:* -Rs. " . number_format($discount_amount, 2) . "\n";
                $text_message .= "💰 *Net Total:* Rs. " . number_format($final_net_bill, 2) . "\n";
                $text_message .= "💵 *Advance Paid:* Rs. " . number_format($amount_paid, 2) . "\n\n";
                $text_message .= "⚠️ *Outstanding Balance:* Rs. " . number_format($client_data['total_balance_due'], 2) . "\n";

                $whatsapp_url = "https://web.whatsapp.com/send?phone=" . $clean_phone . "&text=" . urlencode($text_message);
            }

            $_SESSION['active_invoice'] = [
                'invoice_id' => $group_id,
                'client_name' => $client_data['full_name'] ?? 'N/A',
                'date' => date('d M Y, h:i A'),
                'items' => $shipment_items_to_save,
                'gross_total' => $gross_total_bill,
                'fair_price' => $total_fair_price,
                'loading_price' => $loading_price,
                'discount' => $discount_amount,
                'net_total' => $final_net_bill,
                'advance' => $amount_paid,
                'balance' => $client_data['total_balance_due'] ?? 0,
                'truck_number' => $truck_number,
                'builty_no' => $builty_no,
                'driver_no' => $driver_no,
                'transport_goods_name' => $transport_goods_name,
                'whatsapp_url' => $whatsapp_url
            ];

            header("Location: shipments.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_err'] = "Invoice processing failed: " . $e->getMessage();
            header("Location: shipments.php");
            exit;
        }
    }
}

// NOW Include Header Output
require_once 'includes/header.php';

$msg = $_SESSION['flash_msg'] ?? '';
$err = $_SESSION['flash_err'] ?? '';
$active_invoice = $_SESSION['active_invoice'] ?? null;

unset($_SESSION['flash_msg'], $_SESSION['flash_err'], $_SESSION['active_invoice']);

$distributors = $pdo->query("SELECT id, full_name, total_balance_due FROM customers ORDER BY full_name ASC")->fetchAll();
$inventory_items = $pdo->query("SELECT id, product_name, available_stock, unit_price FROM inventory WHERE available_stock > 0 ORDER BY product_name ASC")->fetchAll();

$history_raw = $pdo->query("
    SELECT 
        COALESCE(s.shipment_group_id, CONCAT('INV-', s.id)) as group_ref,
        c.full_name as dist_name,
        c.total_balance_due as dist_balance,
        SUM(s.quantity_sent) as total_qty,
        SUM(s.total_bill) as aggregate_total,
        MAX(s.amount_paid) as advance_paid,
        MAX(s.shipment_date) as shipment_date,
        MAX(s.truck_number) as truck_number,
        MAX(s.builty_no) as builty_no,
        MAX(s.driver_no) as driver_no,
        MAX(s.transport_goods_name) as transport_goods_name,
        MAX(s.loading_price) as loading_price,
        GROUP_CONCAT(CONCAT(p.product_name, '::', s.quantity_sent, '::', p.unit_price) SEPARATOR '||') as items_data
    FROM product_shipments s
    JOIN customers c ON s.customer_id = c.id
    JOIN inventory p ON s.product_id = p.id
    GROUP BY COALESCE(s.shipment_group_id, s.id), c.full_name, c.total_balance_due
    ORDER BY shipment_date DESC LIMIT 15
")->fetchAll();

// Setup pre-rendered default print data
$print_data = $active_invoice;
if (!$print_data && !empty($history_raw)) {
    $row = $history_raw[0];
    $items = [];
    $raw_items = explode('||', $row['items_data']);
    $gross = 0;
    foreach($raw_items as $ri) {
        $p = explode('::', $ri);
        if (count($p) === 3) {
            $tot = intval($p[1]) * floatval($p[2]);
            $gross += $tot;
            $items[] = ['product_name' => $p[0], 'qty' => intval($p[1]), 'unit_price' => floatval($p[2]), 'total' => $tot];
        }
    }
    $net_rec = floatval($row['aggregate_total']);
    $load_pr = floatval($row['loading_price'] ?? 0);
    $base_plus_charges = $gross + $load_pr;
    $fair = ($net_rec > $base_plus_charges) ? ($net_rec - $base_plus_charges) : 0;
    $disc = ($net_rec < $base_plus_charges) ? ($base_plus_charges - $net_rec) : 0;

    $print_data = [
        'invoice_id' => $row['group_ref'],
        'client_name' => $row['dist_name'],
        'date' => date('d M Y, h:i A', strtotime($row['shipment_date'])),
        'items' => $items,
        'gross_total' => $gross,
        'fair_price' => $fair,
        'loading_price' => $load_pr,
        'discount' => $disc,
        'net_total' => $net_rec,
        'advance' => floatval($row['advance_paid']),
        'balance' => floatval($row['dist_balance']),
        'truck_number' => $row['truck_number'] ?? '',
        'builty_no' => $row['builty_no'] ?? '',
        'driver_no' => $row['driver_no'] ?? '',
        'transport_goods_name' => $row['transport_goods_name'] ?? ''
    ];
}
?>

<style>
.checkout-summary-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; }
.checkout-inputs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
.product-manifest-row { display: grid; grid-template-columns: 2.5fr 1fr 1.2fr auto; gap: 1rem; align-items: end; margin-bottom: 1.25rem; }

/* Modal Styles */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
.modal-overlay.active { display: flex; }
.modal-card { background: white; width: 100%; max-width: 650px; border-radius: 12px; padding: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }

.delivery-log-desktop { display: block; }
.delivery-log-mobile { display: none; }

@media (max-width: 768px) {
    .checkout-summary-grid { grid-template-columns: 1fr; }
    .checkout-inputs-grid { grid-template-columns: 1fr; }
    .product-manifest-row { grid-template-columns: 1fr; gap: 0.75rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; }
    .remove-row-btn { width: 100% !important; margin-top: 0.5rem; }
    
    .delivery-log-desktop { display: none !important; }
    .delivery-log-mobile { display: flex !important; flex-direction: column; gap: 1rem; }
}

#printable_invoice_area {
    display: none;
}

@media print {
    @page { size: auto; margin: 5mm; }
    body, html { width: 100% !important; height: auto !important; margin: 0 !important; padding: 0 !important; background: white !important; }
    body > *:not(#printable_invoice_area) { display: none !important; }
    #printable_invoice_area {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 10px !important;
        background: white !important;
        z-index: 999999;
    }
    #printable_invoice_area * { visibility: visible !important; }
    #printable_invoice_area table { display: table !important; width: 100% !important; }
    #printable_invoice_area thead { display: table-header-group !important; }
    #printable_invoice_area tbody { display: table-row-group !important; }
    #printable_invoice_area tr { display: table-row !important; }
    #printable_invoice_area th, #printable_invoice_area td { display: table-cell !important; padding: 6px 8px !important; }
}
</style>

<div class="dashboard-header" style="margin-bottom: 2rem;">
    <h2 class="section-title" style="color: var(--primary-color);">Multi-Product Shipments Engine 📦</h2>
    <p style="color: #64748b;">Process distributions with dynamic total fair price, loading fees, and transport logistics.</p>
</div>

<?php if(!empty($msg)): ?><div class="alert alert--success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if(!empty($err)): ?><div class="alert alert--danger">❌ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<?php if(!empty($active_invoice)): ?>
<div style="background:#e8f8f5; border: 1px solid #a3e4d7; padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h4 style="color:#117a65; margin-bottom:0.25rem;">📦 Invoice Processed (ID: <?php echo htmlspecialchars($active_invoice['invoice_id']); ?>)</h4>
        <p style="font-size:0.9rem; color:#16a085; margin: 0;">Recipient: <strong><?php echo htmlspecialchars($active_invoice['client_name']); ?></strong></p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <button onclick="triggerPrintFromObject(activeInvoiceSessionData)" class="btn" style="background-color: #0284c7; color: white; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: bold;">
            📥 Download / Save PDF
        </button>
        <?php if(!empty($active_invoice['whatsapp_url'])): ?>
        <a href="<?php echo $active_invoice['whatsapp_url']; ?>" target="_blank" class="btn" style="background-color: #25d366; color: white; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: bold; text-decoration: none;">
            💬 Send to WhatsApp
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<form action="shipments.php" method="POST" id="invoiceForm">
    <input type="hidden" name="action" value="process_shipment">
    
    <div class="action-panel">
        <div class="action-panel__title">1. Destination Account</div>
        <div class="form-group" style="max-width: 100%; margin: 0;">
            <label>Distributor Account</label>
            <select name="customer_id" class="form-control" required style="cursor: pointer;">
                <option value="">-- Choose Account Profile --</option>
                <?php foreach($distributors as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?> (Current Balance: Rs.<?php echo number_format($d['total_balance_due'],2); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="action-panel" style="background: #fdfefe; border: 1px solid #e2e8f0;">
        <div class="action-panel__title">2. Transport & Logistics Details (Optional)</div>
        <div class="checkout-inputs-grid">
            <div class="form-group" style="margin:0;">
                <label>Transport Goods Name</label>
                <input type="text" name="transport_goods_name" class="form-control" placeholder="e.g. Biscuits & Sweets Cargo">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Truck Number Plate</label>
                <input type="text" name="truck_number" class="form-control" placeholder="e.g. LES-7865">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Builty Number (Waybill)</label>
                <input type="text" name="builty_no" class="form-control" placeholder="e.g. BLY-99420">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Driver Contact Number</label>
                <input type="text" name="driver_no" class="form-control" placeholder="e.g. 0300-1234567">
            </div>
        </div>
    </div>

    <div class="action-panel">
        <div class="action-panel__title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span>3. Manifest Items</span>
            <button type="button" class="btn btn--sm" id="add_product_row" style="background-color: var(--success-color); color: white;">+ Add Product</button>
        </div>
        
        <div id="product_rows_container">
            <div class="product-row product-manifest-row">
                <div class="form-group" style="margin:0;">
                    <label>Product Asset</label>
                    <select name="products[0][product_id]" class="form-control product-select" required style="cursor: pointer;">
                        <option value="">-- Choose Stock Item --</option>
                        <?php foreach($inventory_items as $i): ?>
                            <option value="<?php echo $i['id']; ?>" data-price="<?php echo $i['unit_price']; ?>" data-stock="<?php echo $i['available_stock']; ?>">
                                <?php echo htmlspecialchars($i['product_name']); ?> (Unit: Rs.<?php echo number_format($i['unit_price'],2); ?> | Stock: <?php echo $i['available_stock']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label>Quantity</label>
                    <input type="number" name="products[0][quantity_sent]" class="form-control qty-input" min="1" placeholder="Units" required value="1">
                </div>

                <div class="form-group" style="margin:0;">
                    <label>Subtotal</label>
                    <input type="number" step="0.01" name="products[0][custom_subtotal]" class="form-control row-subtotal-input" placeholder="0.00" readonly style="background-color: #f8fafc; font-weight: 600;">
                </div>

                <button type="button" class="btn btn--primary remove-row-btn" style="background-color: var(--danger-color) !important; height: 42px; width: 42px; display:none; padding:0; justify-content:center; align-items:center;">✕</button>
            </div>
        </div>
    </div>

    <div class="action-panel" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.5rem;">
        <div class="checkout-summary-grid">
            <div class="checkout-inputs-grid">
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 600;">Total Fair Price (PKR)</label>
                    <input type="number" step="0.01" name="total_fair_price" id="total_fair_price" class="form-control" value="0" min="0">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 600;">Loading Price (PKR)</label>
                    <input type="number" step="0.01" name="loading_price" id="loading_price" class="form-control" value="0" min="0">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 600;">Advance Paid (PKR)</label>
                    <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" value="0" min="0">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 600;">Discount Rule</label>
                    <select name="discount_type" id="discount_type" class="form-control">
                        <option value="percentage">Percentage (%)</option>
                        <option value="pkr">Flat Cash Rate (PKR)</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-weight: 600;">Discount Value</label>
                    <input type="number" step="0.01" name="discount_value" id="discount_value" class="form-control" value="0" min="0">
                </div>
            </div>

            <div style="background: #ffffff; padding: 1.25rem; border-radius: 8px; border: 1px solid #cbd5e1;">
                <h4 style="margin: 0 0 1rem 0; color: #0f172a; font-size: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">Invoice Summary</h4>
                <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <div style="font-size: 0.875rem; display: flex; justify-content: space-between;"><span>Gross Total:</span> <span id="lbl_gross" style="font-weight: 600;">Rs. 0.00</span></div>
                    <div style="font-size: 0.875rem; display: flex; justify-content: space-between;"><span>(+) Fair Price:</span> <span id="lbl_fair_total" style="font-weight: 600; color: #16a085;">Rs. 0.00</span></div>
                    <div style="font-size: 0.875rem; display: flex; justify-content: space-between;"><span>(+) Loading Price:</span> <span id="lbl_loading_total" style="font-weight: 600; color: #d97706;">Rs. 0.00</span></div>
                    <div style="font-size: 0.875rem; color: var(--danger-color); display: flex; justify-content: space-between;"><span>(-) Discount:</span> <span id="lbl_discount_deduction" style="font-weight: 600;">- Rs. 0.00</span></div>
                    <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 0.25rem 0;">
                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Net Receivable Bill</span>
                        <span id="lbl_net_total" style="font-size: 1.35rem; font-weight: 800; color: #2563eb;">Rs. 0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align: right; margin-bottom: 3rem;">
        <button type="submit" class="btn btn--primary" style="height: 48px; width: 100%; max-width: 320px; font-size: 1rem;">Finalize Inventory Dispatch & Invoice</button>
    </div>
</form>

<div class="content-card">
    <div class="content-card__header">
        <h3 class="content-card__title">📦 Recent Delivery Log Entries</h3>
    </div>
    
    <div class="delivery-log-desktop">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.925rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569;">
                        <th style="padding: 0.85rem 1rem;">Invoice Ref</th>
                        <th style="padding: 0.85rem 1rem;">Distributor</th>
                        <th style="padding: 0.85rem 1rem;">Transport / Truck</th>
                        <th style="padding: 0.85rem 1rem;">Items Summary</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">Total Qty</th>
                        <th style="padding: 0.85rem 1rem; text-align: right;">Net Total</th>
                        <th style="padding: 0.85rem 1rem; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($history_raw as $row): 
                        $items = [];
                        $raw_items = explode('||', $row['items_data']);
                        $gross = 0;
                        foreach($raw_items as $ri) {
                            $p = explode('::', $ri);
                            if (count($p) === 3) {
                                $p_name = $p[0];
                                $p_qty = intval($p[1]);
                                $p_price = floatval($p[2]);
                                $tot = $p_qty * $p_price;
                                $gross += $tot;
                                $items[] = ['product_name' => $p_name, 'qty' => $p_qty, 'unit_price' => $p_price, 'total' => $tot];
                            }
                        }
                        $net_rec = floatval($row['aggregate_total']);
                        $load_pr = floatval($row['loading_price'] ?? 0);
                        $fair = 0; $disc = 0;
                        
                        $base_plus_charges = $gross + $load_pr;
                        if ($net_rec > $base_plus_charges) {
                            $fair = $net_rec - $base_plus_charges;
                        } else if ($net_rec < $base_plus_charges) {
                            $disc = $base_plus_charges - $net_rec;
                        }

                        $row_payload = [
                            'invoice_id' => $row['group_ref'],
                            'client_name' => $row['dist_name'],
                            'date' => date('d M Y, h:i A', strtotime($row['shipment_date'])),
                            'items' => $items,
                            'gross_total' => $gross,
                            'fair_price' => $fair,
                            'loading_price' => $load_pr,
                            'discount' => $disc,
                            'net_total' => $net_rec,
                            'advance' => floatval($row['advance_paid']),
                            'balance' => floatval($row['dist_balance']),
                            'truck_number' => $row['truck_number'] ?? '',
                            'builty_no' => $row['builty_no'] ?? '',
                            'driver_no' => $row['driver_no'] ?? '',
                            'transport_goods_name' => $row['transport_goods_name'] ?? ''
                        ];
                        // Safe JSON encoding for HTML attributes
                        $json_encoded_row = htmlspecialchars(json_encode($row_payload, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 700; color: var(--primary-color);"><?php echo htmlspecialchars($row['group_ref']); ?></td>
                        <td style="padding: 0.85rem 1rem; font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($row['dist_name']); ?></td>
                        <td style="padding: 0.85rem 1rem; color: #475569; font-size: 0.875rem;">
                            <?php if(!empty($row['truck_number'])): ?>
                                🚛 <?php echo htmlspecialchars($row['truck_number']); ?><br>
                            <?php endif; ?>
                            <?php if(!empty($row['builty_no'])): ?>
                                <span style="font-size: 0.8rem; color: #64748b;">Builty: <?php echo htmlspecialchars($row['builty_no']); ?></span>
                            <?php endif; ?>
                            <?php if(empty($row['truck_number']) && empty($row['builty_no'])): ?>
                                <span style="color: #94a3b8; font-style: italic;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                <?php foreach($items as $itm): ?>
                                    <span style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.8rem; color: #334155;">
                                        <?php echo htmlspecialchars($itm['product_name']); ?> (<?php echo $itm['qty']; ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: center;"><span style="background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600;"><?php echo $row['total_qty']; ?></span></td>
                        <td style="padding: 0.85rem 1rem; text-align: right; font-weight: 700; color: #2563eb;">Rs. <?php echo number_format($row['aggregate_total'], 2); ?></td>
                        <td style="padding: 0.85rem 1rem; text-align: center;">
                            <button type="button" class="btn btn--sm btn--primary" onclick="openDetailModal('<?php echo $json_encoded_row; ?>')">
                                👁️ View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="delivery-log-mobile">
        <?php foreach($history_raw as $row): 
            $items = [];
            $raw_items = explode('||', $row['items_data']);
            $gross = 0;
            foreach($raw_items as $ri) {
                $p = explode('::', $ri);
                if (count($p) === 3) {
                    $p_name = $p[0];
                    $p_qty = intval($p[1]);
                    $p_price = floatval($p[2]);
                    $tot = $p_qty * $p_price;
                    $gross += $tot;
                    $items[] = ['product_name' => $p_name, 'qty' => $p_qty, 'unit_price' => $p_price, 'total' => $tot];
                }
            }
            $net_rec = floatval($row['aggregate_total']);
            $load_pr = floatval($row['loading_price'] ?? 0);
            $fair = 0; $disc = 0;
            $base_plus_charges = $gross + $load_pr;
            if ($net_rec > $base_plus_charges) {
                $fair = $net_rec - $base_plus_charges;
            } else if ($net_rec < $base_plus_charges) {
                $disc = $base_plus_charges - $net_rec;
            }

            $row_payload = [
                'invoice_id' => $row['group_ref'],
                'client_name' => $row['dist_name'],
                'date' => date('d M Y, h:i A', strtotime($row['shipment_date'])),
                'items' => $items,
                'gross_total' => $gross,
                'fair_price' => $fair,
                'loading_price' => $load_pr,
                'discount' => $disc,
                'net_total' => $net_rec,
                'advance' => floatval($row['advance_paid']),
                'balance' => floatval($row['dist_balance']),
                'truck_number' => $row['truck_number'] ?? '',
                'builty_no' => $row['builty_no'] ?? '',
                'driver_no' => $row['driver_no'] ?? '',
                'transport_goods_name' => $row['transport_goods_name'] ?? ''
            ];
            $json_encoded_row = htmlspecialchars(json_encode($row_payload, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
        ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                <span style="font-weight: 700; font-size: 1.1rem; font-family: monospace; color: var(--primary-color);"><?php echo htmlspecialchars($row['group_ref']); ?></span>
                <span style="background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600;"><?php echo $row['total_qty']; ?> Units</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.95rem;">
                <div><span style="color: #64748b;">Distributor:</span> <strong style="color: #0f172a;"><?php echo htmlspecialchars($row['dist_name']); ?></strong></div>
                <div><span style="color: #64748b;">Truck / Builty:</span> <span style="color: #0f172a;"><?php echo htmlspecialchars($row['truck_number'] ?: 'N/A'); ?></span></div>
                <div><span style="color: #64748b;">Net Total:</span> <strong style="color: #2563eb;">Rs. <?php echo number_format($row['aggregate_total'], 2); ?></strong></div>
            </div>

            <div style="display: flex; justify-content: flex-end; border-top: 1px solid #f1f5f9; padding-top: 0.75rem; margin-top: 0.25rem;">
                <button type="button" class="btn btn--sm btn--primary" onclick="openDetailModal('<?php echo $json_encoded_row; ?>')">
                    👁️ View Details
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Dialog -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; margin-bottom:1rem;">
            <h3 style="margin:0;" id="m_inv_id">Invoice Breakdown</h3>
            <button onclick="closeDetailModal()" style="background:none; border:none; font-size:1.25rem; cursor:pointer;">✕</button>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.95rem; margin-bottom:1.5rem;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                <span><strong>Distributor:</strong> <span id="m_client"></span></span>
                <span><strong>Date:</strong> <span id="m_date"></span></span>
            </div>

            <div style="background:#f8fafc; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0; display:grid; grid-template-columns: repeat(2, 1fr); gap:0.5rem; font-size:0.875rem;">
                <div><strong>Goods Name:</strong> <span id="m_goods">N/A</span></div>
                <div><strong>Truck No:</strong> <span id="m_truck">N/A</span></div>
                <div><strong>Builty No:</strong> <span id="m_builty">N/A</span></div>
                <div><strong>Driver No:</strong> <span id="m_driver">N/A</span></div>
            </div>
            
            <div style="margin-top:0.2rem;"><strong>Line Items:</strong></div>
            <div id="m_items_list" style="background:#f8fafc; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0;"></div>

            <div style="background:#f1f5f9; padding:1rem; border-radius:6px; display:flex; flex-direction:column; gap:0.4rem; margin-top:0.2rem;">
                <div style="display:flex; justify-content:space-between;"><span>Gross Subtotal:</span> <strong id="m_gross"></strong></div>
                <div style="display:flex; justify-content:space-between; color:#16a085;"><span>(+) Total Fair Price:</span> <strong id="m_fair"></strong></div>
                <div style="display:flex; justify-content:space-between; color:#d97706;"><span>(+) Loading Price:</span> <strong id="m_loading"></strong></div>
                <div style="display:flex; justify-content:space-between; color:#ef4444;"><span>(-) Discount:</span> <strong id="m_disc"></strong></div>
                <hr style="margin:0.25rem 0; border:0; border-top:1px solid #cbd5e1;">
                <div style="display:flex; justify-content:space-between; font-size:1.1rem; color:#2563eb;"><span>Net Total Bill:</span> <strong id="m_net"></strong></div>
                <div style="display:flex; justify-content:space-between; color:#64748b; font-size:0.875rem;"><span>Advance Paid:</span> <span id="m_advance"></span></div>
                <div style="display:flex; justify-content:space-between; color:#b91c1c; font-size:0.875rem;"><span>Current Outstanding Balance:</span> <strong id="m_balance"></strong></div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
            <button type="button" class="btn btn--primary" onclick="printModalInvoice()">
                📥 Download PDF Invoice
            </button>
            <button type="button" class="btn" onclick="closeDetailModal()" style="background:#64748b; color:white;">Close</button>
        </div>
    </div>
</div>

<!-- Print Container Pre-rendered with PHP Data -->
<div id="printable_invoice_area">
    <div style="text-align:center; margin-bottom:1rem; border-bottom:2px solid #000; padding-bottom:0.75rem;">
        <h1 style="margin:0; font-size:22px;">DANAN FOODS PVT LTD</h1>
        <p style="margin:0.25rem 0 0 0; font-size:14px;">Official Dispatch & Cargo Invoice</p>
    </div>
    
    <div style="display:flex; justify-content:space-between; margin-bottom:1rem; font-size:13px;" id="print_meta">
        <div>
            <strong>Recipient:</strong> <span id="p_client"><?php echo htmlspecialchars($print_data['client_name'] ?? 'N/A'); ?></span><br>
            <strong>Invoice Ref:</strong> <span id="p_invid"><?php echo htmlspecialchars($print_data['invoice_id'] ?? 'N/A'); ?></span>
        </div>
        <div style="text-align:right;">
            <strong>Date:</strong> <span id="p_date"><?php echo htmlspecialchars($print_data['date'] ?? date('d M Y')); ?></span>
        </div>
    </div>

    <div style="margin-bottom:1rem; font-size:13px; border: 1px solid #000; padding: 6px 10px;" id="print_transport_meta">
        <strong>Transport Goods Name:</strong> <span id="p_goods"><?php echo htmlspecialchars($print_data['transport_goods_name'] ?? 'N/A'); ?></span> | 
        <strong>Truck No:</strong> <span id="p_truck"><?php echo htmlspecialchars($print_data['truck_number'] ?? 'N/A'); ?></span> | 
        <strong>Builty No:</strong> <span id="p_builty"><?php echo htmlspecialchars($print_data['builty_no'] ?? 'N/A'); ?></span> | 
        <strong>Driver No:</strong> <span id="p_driver"><?php echo htmlspecialchars($print_data['driver_no'] ?? 'N/A'); ?></span>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-bottom:1.5rem; font-size:13px;" border="1" cellpadding="6" id="print_table">
        <thead>
            <tr style="background:#f1f5f9;">
                <th>Product Item</th>
                <th style="text-align:center;">Quantity</th>
                <th style="text-align:right;">Unit Price</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody id="p_tbody">
            <?php if (!empty($print_data['items'])): ?>
                <?php foreach($print_data['items'] as $i): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($i['product_name']); ?></td>
                        <td style="text-align:center;"><?php echo $i['qty']; ?></td>
                        <td style="text-align:right;">Rs. <?php echo number_format($i['unit_price'], 2); ?></td>
                        <td style="text-align:right;">Rs. <?php echo number_format($i['total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;">No items found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="text-align:right; font-size:13px;" id="print_summary">
        <p><strong>Gross Total:</strong> Rs. <span id="p_gross"><?php echo number_format($print_data['gross_total'] ?? 0, 2); ?></span></p>
        <p><strong>Total Fair Price:</strong> Rs. <span id="p_fair"><?php echo number_format($print_data['fair_price'] ?? 0, 2); ?></span></p>
        <p><strong>Loading Price:</strong> Rs. <span id="p_loading"><?php echo number_format($print_data['loading_price'] ?? 0, 2); ?></span></p>
        <p><strong>Discount:</strong> - Rs. <span id="p_disc"><?php echo number_format($print_data['discount'] ?? 0, 2); ?></span></p>
        <h3 style="margin:10px 0; font-size:16px;">Net Total Bill: Rs. <span id="p_net"><?php echo number_format($print_data['net_total'] ?? 0, 2); ?></span></h3>
        <p><strong>Advance Paid:</strong> Rs. <span id="p_adv"><?php echo number_format($print_data['advance'] ?? 0, 2); ?></span></p>
        <p><strong>Outstanding Balance:</strong> Rs. <span id="p_bal"><?php echo number_format($print_data['balance'] ?? 0, 2); ?></span></p>
    </div>
</div>

<script>
const inventoryItems = <?php echo json_encode($inventory_items); ?>;
const activeInvoiceSessionData = <?php echo json_encode($active_invoice); ?>;
let rowIdx = 1;

document.getElementById('add_product_row').addEventListener('click', function() {
    const container = document.getElementById('product_rows_container');
    
    let optionsHtml = '<option value="">-- Choose Stock Item --</option>';
    inventoryItems.forEach(item => {
        optionsHtml += `<option value="${item.id}" data-price="${item.unit_price}" data-stock="${item.available_stock}">
            ${item.product_name} (Unit: Rs.${parseFloat(item.unit_price).toFixed(2)} | Stock: ${item.available_stock})
        </option>`;
    });

    const newRow = document.createElement('div');
    newRow.className = 'product-row product-manifest-row';
    newRow.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label>Product Asset</label>
            <select name="products[${rowIdx}][product_id]" class="form-control product-select" required style="cursor: pointer;">
                ${optionsHtml}
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Quantity</label>
            <input type="number" name="products[${rowIdx}][quantity_sent]" class="form-control qty-input" min="1" placeholder="Units" required value="1">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Subtotal</label>
            <input type="number" step="0.01" name="products[${rowIdx}][custom_subtotal]" class="form-control row-subtotal-input" placeholder="0.00" readonly style="background-color: #f8fafc; font-weight: 600;">
        </div>
        <button type="button" class="btn btn--primary remove-row-btn" style="background-color: var(--danger-color) !important; height: 42px; width: 42px; display:flex; padding:0; justify-content:center; align-items:center;">✕</button>
    `;
    
    container.appendChild(newRow);
    rowIdx++;
    updateCalculations();
});

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('remove-row-btn')) {
        e.target.closest('.product-row').remove();
        updateCalculations();
    }
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('qty-input') || e.target.classList.contains('product-select') || e.target.id === 'total_fair_price' || e.target.id === 'loading_price' || e.target.id === 'discount_type' || e.target.id === 'discount_value') {
        updateCalculations();
    }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('product-select') || e.target.classList.contains('qty-input') || e.target.id === 'discount_type') {
        updateCalculations();
    }
});

function updateCalculations() {
    let grossTotal = 0;
    const rows = document.querySelectorAll('.product-row');

    rows.forEach(row => {
        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.qty-input');
        const subtotalInput = row.querySelector('.row-subtotal-input');

        const selectedOption = select.options[select.selectedIndex];
        const unitPrice = parseFloat(selectedOption?.getAttribute('data-price') || 0);
        const maxStock = parseInt(selectedOption?.getAttribute('data-stock') || 0);
        let qty = parseInt(qtyInput.value) || 0;

        if (qty > maxStock) {
            qty = maxStock;
            qtyInput.value = qty;
        }

        const subtotal = qty * unitPrice;
        subtotalInput.value = subtotal.toFixed(2);
        grossTotal += subtotal;
    });

    const fairPrice = parseFloat(document.getElementById('total_fair_price').value) || 0;
    const loadingPrice = parseFloat(document.getElementById('loading_price').value) || 0;
    const discountType = document.getElementById('discount_type').value;
    const discountValue = parseFloat(document.getElementById('discount_value').value) || 0;

    let discountAmount = 0;
    if (discountValue > 0) {
        discountAmount = (discountType === 'percentage') ? (grossTotal * (discountValue / 100)) : discountValue;
    }

    const totalWithAdditions = grossTotal + fairPrice + loadingPrice;
    const netTotal = Math.max(0, totalWithAdditions - discountAmount);

    document.getElementById('lbl_gross').innerText = 'Rs. ' + grossTotal.toFixed(2);
    document.getElementById('lbl_fair_total').innerText = 'Rs. ' + fairPrice.toFixed(2);
    document.getElementById('lbl_loading_total').innerText = 'Rs. ' + loadingPrice.toFixed(2);
    document.getElementById('lbl_discount_deduction').innerText = '- Rs. ' + discountAmount.toFixed(2);
    document.getElementById('lbl_net_total').innerText = 'Rs. ' + netTotal.toFixed(2);
}

let currentModalData = null;

function openDetailModal(jsonString) {
    const data = JSON.parse(jsonString);
    currentModalData = data;

    document.getElementById('m_inv_id').innerText = 'Invoice Breakdown: ' + data.invoice_id;
    document.getElementById('m_client').innerText = data.client_name;
    document.getElementById('m_date').innerText = data.date;

    document.getElementById('m_goods').innerText = data.transport_goods_name || 'N/A';
    document.getElementById('m_truck').innerText = data.truck_number || 'N/A';
    document.getElementById('m_builty').innerText = data.builty_no || 'N/A';
    document.getElementById('m_driver').innerText = data.driver_no || 'N/A';

    let itemsHtml = '<ul style="margin:0; padding-left:1.25rem;">';
    data.items.forEach(i => {
        itemsHtml += `<li><strong>${i.product_name}</strong> — Qty: ${i.qty} | Unit: Rs.${parseFloat(i.unit_price).toFixed(2)} | Subtotal: <strong>Rs.${parseFloat(i.total).toFixed(2)}</strong></li>`;
    });
    itemsHtml += '</ul>';
    document.getElementById('m_items_list').innerHTML = itemsHtml;

    document.getElementById('m_gross').innerText = 'Rs. ' + parseFloat(data.gross_total).toFixed(2);
    document.getElementById('m_fair').innerText = 'Rs. ' + parseFloat(data.fair_price).toFixed(2);
    document.getElementById('m_loading').innerText = 'Rs. ' + parseFloat(data.loading_price || 0).toFixed(2);
    document.getElementById('m_disc').innerText = 'Rs. ' + parseFloat(data.discount).toFixed(2);
    document.getElementById('m_net').innerText = 'Rs. ' + parseFloat(data.net_total).toFixed(2);
    document.getElementById('m_advance').innerText = 'Rs. ' + parseFloat(data.advance).toFixed(2);
    document.getElementById('m_balance').innerText = 'Rs. ' + parseFloat(data.balance).toFixed(2);

    document.getElementById('detailsModal').classList.add('active');
}

function closeDetailModal() {
    document.getElementById('detailsModal').classList.remove('active');
}

function printModalInvoice() {
    if (currentModalData) {
        triggerPrintFromObject(currentModalData);
    }
}

function triggerPrintFromObject(inv) {
    document.getElementById('p_client').innerText = inv.client_name;
    document.getElementById('p_invid').innerText = inv.invoice_id;
    document.getElementById('p_date').innerText = inv.date;

    document.getElementById('p_goods').innerText = inv.transport_goods_name || 'N/A';
    document.getElementById('p_truck').innerText = inv.truck_number || 'N/A';
    document.getElementById('p_builty').innerText = inv.builty_no || 'N/A';
    document.getElementById('p_driver').innerText = inv.driver_no || 'N/A';

    let tableHtml = '';
    inv.items.forEach(i => {
        tableHtml += `
            <tr>
                <td>${i.product_name}</td>
                <td style="text-align:center;">${i.qty}</td>
                <td style="text-align:right;">Rs. ${parseFloat(i.unit_price).toFixed(2)}</td>
                <td style="text-align:right;">Rs. ${parseFloat(i.total).toFixed(2)}</td>
            </tr>
        `;
    });
    document.getElementById('p_tbody').innerHTML = tableHtml;

    document.getElementById('p_gross').innerText = parseFloat(inv.gross_total).toFixed(2);
    document.getElementById('p_fair').innerText = parseFloat(inv.fair_price).toFixed(2);
    document.getElementById('p_loading').innerText = parseFloat(inv.loading_price || 0).toFixed(2);
    document.getElementById('p_disc').innerText = parseFloat(inv.discount).toFixed(2);
    document.getElementById('p_net').innerText = parseFloat(inv.net_total).toFixed(2);
    document.getElementById('p_adv').innerText = parseFloat(inv.advance).toFixed(2);
    document.getElementById('p_bal').innerText = parseFloat(inv.balance).toFixed(2);

    window.print();
}
</script>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>