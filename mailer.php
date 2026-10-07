<?php
/**
 * Custom Enterprise SMTP Transaction Wrapper for Danan Foods
 */
function sendInvoiceEmail($to_email, $client_name, $product_name, $qty, $total_bill, $amount_paid, $running_balance) {
    $subject = "Danan Foods - Official Cargo Shipment Invoice & Bill Statement";
    
    // Professional HTML Invoice Presentation Template Layout
    $message = "
    <html>
    <head>
        <title>Danan Foods Ledger Invoice</title>
    </head>
    <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px; max-width: 600px; margin: 0 auto; border: 1px solid #e1e8ed; border-radius: 8px;'>
        <div style='text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #2c3e50;'>
            <h2 style='color: #2c3e50; margin: 0;'>DANAN FOODS PVT LTD</h2>
            <p style='color: #7f8c8d; font-size: 0.9rem; margin: 5px 0 0 0;'>Automated Billing & Logistics Network Portal</p>
        </div>
        
        <p>Dear <strong>" . htmlspecialchars($client_name) . "</strong>,</p>
        <p>This automated dispatch receipt notification confirms that a new inventory cargo batch has been allocated and shipped out to your distribution hub network.</p>
        
        <div style='background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #3498db;'>
            <h3 style='margin-top: 0; color: #2c3e50;'>Cargo Breakdown Summary</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 5px 0; color: #7f8c8d;'>Material Item:</td>
                    <td style='padding: 5px 0; font-weight: bold; text-align: right;'>" . htmlspecialchars($product_name) . "</td>
                </tr>
                <tr>
                    <td style='padding: 5px 0; color: #7f8c8d;'>Quantity Sent:</td>
                    <td style='padding: 5px 0; font-weight: bold; text-align: right;'>" . $qty . " Units</td>
                </tr>
                <tr style='border-top: 1px dashed #ddd;'>
                    <td style='padding: 10px 0; color: #7f8c8d; font-weight: bold;'>Gross Total Bill:</td>
                    <td style='padding: 10px 0; font-weight: bold; text-align: right; color: #2c3e50; font-size: 1.1rem;'>Rs. " . number_format($total_bill, 2) . "</td>
                </tr>
                <tr>
                    <td style='padding: 5px 0; color: #2ecc71;'>Advance Deposited:</td>
                    <td style='padding: 5px 0; font-weight: bold; text-align: right; color: #2ecc71;'>Rs. " . number_format($amount_paid, 2) . "</td>
                </tr>
            </table>
        </div>
        
        <div style='background: #fef9e7; padding: 12px; border-radius: 6px; text-align: center; border: 1px solid #f5b041; margin-bottom: 20px;'>
            <span style='color: #b7950b; font-size: 0.95rem;'>Your current outstanding ledger account statement balance is:</span>
            <div style='font-size: 1.3rem; font-weight: bold; color: #e67e22; margin-top: 5px;'>Rs. " . number_format($running_balance, 2) . "</div>
        </div>
        
        <p style='font-size: 0.85rem; color: #95a5a6; text-align: center; margin-top: 30px; border-top: 1px solid #e1e8ed; padding-top: 15px;'>
            This is a system-generated operational ledger receipt. Please log into your salesman portal area to view your complete transactional history trails.
        </p>
    </body>
    </html>
    ";

    // Setup properly formatted corporate identity headers to stay clear of junk/spam boxes
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Danan Foods Billing <billing@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
    $headers .= "Reply-To: support@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Fire the email transit action line natively across the server engine
    return @mail($to_email, $subject, $message, $headers);
}
?>