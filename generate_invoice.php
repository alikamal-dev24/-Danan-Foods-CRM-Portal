<?php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['role'])) {
    die("Unauthorized access.");
}

$request_id = isset($_GET['req_id']) ? intval($_GET['req_id']) : 0;

if ($request_id <= 0) {
    die("Invalid request ID.");
}

// Fetch Request Parent Details
$stmt = $pdo->prepare("SELECT * FROM shipment_requests WHERE id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    die("Invoice not found.");
}

// Fetch Customer Account info to track balance
$cust_stmt = $pdo->prepare("SELECT total_balance_due FROM customers WHERE id = ?");
$cust_stmt->execute([$request['user_id']]);
$customer_due = $cust_stmt->fetchColumn() ?: 0.00;

// Fetch Linked Request Items
$item_stmt = $pdo->prepare("SELECT * FROM shipment_request_items WHERE request_id = ?");
$item_stmt->execute([$request_id]);
$items = $item_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $request['id']; ?> - Danan Foods</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 40px;
            background-color: #f8fafc;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
        }
        .invoice-title {
            text-align: right;
            font-size: 28px;
            color: #1e293b;
            margin: 0;
            text-transform: uppercase;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table td {
            padding: 6px 0;
            font-size: 14px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 12px;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #cbd5e1;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .total-row td {
            font-size: 15px;
            font-weight: 500;
            color: #475569;
            padding-top: 10px;
        }
        .grand-total td {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            border-top: 2px solid #cbd5e1;
            padding-top: 15px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
        .btn-print-box {
            max-width: 800px;
            margin: 0 auto 20px auto;
            text-align: right;
        }
        .btn-print {
            background-color: #1e3a8a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        @media print {
            body { background-color: #fff; padding: 0; }
            .invoice-box { border: none; box-shadow: none; padding: 0; }
            .btn-print-box { display: none; }
        }
    </style>
</head>
<body>

    <div class="btn-print-box">
        <button onclick="window.print();" class="btn-print">Print / Save as PDF 📄</button>
    </div>

    <div class="invoice-box">
        <table class="header-table">
            <tr>
                <td>
                    <p class="logo">🍇 DANAN FOODS</p>
                    <p style="font-size: 13px; color: #64748b; margin: 5px 0 0 0;">
                        Official Portal Dispatched Ledger<br>
                        Pakistan Division
                    </p>
                </td>
                <td>
                    <p class="invoice-title">Invoice</p>
                    <p style="text-align: right; font-size: 14px; margin: 5px 0 0 0; color: #64748b;">
                        <strong>Invoice #:</strong> INV-<?php echo $request['id']; ?><br>
                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($request['requested_at'])); ?>
                    </p>
                </td>
            </tr>
        </table>

        <table class="details-table">
            <tr>
                <td style="width: 50%;">
                    <strong style="color: #475569;">Billed To:</strong><br>
                    <span style="font-size: 16px; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($request['username']); ?></span><br>
                    <span>ID: #USER-<?php echo $request['user_id']; ?></span>
                </td>
                <td style="width: 50%; text-align: right;">
                    <strong style="color: #475569;">Payment Status:</strong><br>
                    <span style="font-size: 16px; font-weight: bold; color: #16a34a;">Account Charge (Unpaid)</span>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Product Item Description</th>
                    <th style="text-align: right;">Price Rate</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td style="text-align: right;">Rs. <?php echo number_format($item['price_per_unit'], 2); ?></td>
                    <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                    <td style="text-align: right; font-weight: 600;">Rs. <?php echo number_format($item['price_per_unit'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="2"></td>
                    <td style="text-align: center; padding-top: 15px;">Invoice Amount:</td>
                    <td style="text-align: right; padding-top: 15px; font-weight: 600;">Rs. <?php echo number_format($request['total_amount'], 2); ?></td>
                </tr>

                <tr class="total-row">
                    <td colspan="2"></td>
                    <td style="text-align: center; color: var(--danger-color);">Remaining Dues (Accrued):</td>
                    <td style="text-align: right; color: var(--danger-color); font-weight: 600;">Rs. <?php echo number_format($customer_due, 2); ?></td>
                </tr>
                
                <tr class="grand-total">
                    <td colspan="2"></td>
                    <td style="text-align: center; padding-top: 15px;">Amount Charged:</td>
                    <td style="text-align: right; padding-top: 15px;">Rs. <?php echo number_format($request['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Thank you for doing business with Danan Foods!</p>
            <p style="font-size: 10px;">This is a system-generated document authorized via the Danan Foods Merchant Core Engine.</p>
        </div>
    </div>

    <?php if (isset($_GET['download']) && $_GET['download'] == '1'): ?>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    <?php endif; ?>

</body>
</html>