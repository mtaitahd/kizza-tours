<?php
$dompdfAvailable = false;
$dompdfError = null;
try {
    $dompdfAutoloadPaths = [
        __DIR__ . '/../vendor/dompdf/autoload.inc.php',
        __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
    ];
    $dompdfLoaded = false;
    foreach ($dompdfAutoloadPaths as $p) {
        if (file_exists($p)) {
            require_once $p;
            $dompdfLoaded = true;
            break;
        }
    }
    if (!$dompdfLoaded) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        } else {
            throw new \RuntimeException('Dompdf not found. Run: composer require dompdf/dompdf');
        }
    }
    if (class_exists('Dompdf\Dompdf')) {
        $dompdfAvailable = true;
    }
} catch (\Throwable $e) {
    $dompdfError = $e->getMessage();
    $dompdfAvailable = false;
}

use Dompdf\Dompdf;
use Dompdf\Options;

function generateQuotePdf($quoteId) {
    global $dompdfAvailable, $dompdfError;
    if (!$dompdfAvailable) {
        throw new \RuntimeException('Cannot generate PDF: Dompdf library not available' . ($dompdfError ? ': ' . $dompdfError : '') . '. Run: composer install --no-dev');
    }
    $db = db();

    $quote = $db->fetchOne("
        SELECT q.*,
            COALESCE(i.full_name, b.full_name) AS full_name,
            COALESCE(i.email, b.email) AS email,
            COALESCE(i.phone, b.phone) AS phone
        FROM quotes q
        LEFT JOIN inquiries i ON q.inquiry_id = i.id
        LEFT JOIN bookings b ON q.booking_id = b.id
        WHERE q.id = ?
    ", [$quoteId]);

    if (!$quote) {
        throw new Exception('Quote not found');
    }

    $items = $db->fetchAll(
        "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC",
        [$quoteId]
    );

    $siteName = getSetting('site_name', 'Kizza Tours & Safaris');
    $siteEmail = getSetting('site_email', 'info@kizzatours.com');
    $sitePhone = getSetting('site_phone', '+255 734 335 668');
    $siteAddress = getSetting('site_address', 'Arusha, Tanzania');

    $currencySymbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'TZS' => 'TSh'];
    $currency = $currencySymbols[$quote['currency']] ?? '$';

    $logoPath = __DIR__ . '/../assets/images/ogimage.png';
    $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
    $logoSrc = $logoData ? 'data:image/png;base64,' . $logoData : SITE_URL . '/assets/images/ogimage.png';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Quote ' . htmlspecialchars($quote['quote_number']) . '</title>
        <style>
            @page { margin: 20px 30px; }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
            .header { border-bottom: 3px solid #D4AF37; padding-bottom: 20px; margin-bottom: 25px; overflow: hidden; }
            .header-left { float: left; }
            .header-left img { height: 50px; }
            .header-left h1 { font-size: 22px; color: #0A2540; margin: 5px 0 0; }
            .header-right { float: right; text-align: right; }
            .header-right h2 { font-size: 26px; color: #D4AF37; margin: 0 0 5px; }
            .header-right .badge { 
                display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;
                background-color: #28a745; color: #fff;
            }
            .info-section { margin-bottom: 25px; overflow: hidden; }
            .bill-to { float: left; width: 50%; }
            .bill-to h4 { color: #0A2540; font-size: 14px; margin: 0 0 8px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
            .bill-to p { margin: 2px 0; font-size: 12px; }
            .quote-meta { float: right; width: 40%; text-align: right; }
            .quote-meta table { width: 100%; }
            .quote-meta td { padding: 3px 0; font-size: 12px; }
            .quote-meta td:first-child { color: #888; padding-right: 10px; }
            .quote-meta td:last-child { font-weight: bold; }
            table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            table.items thead th { 
                background-color: #0A2540; color: #fff; padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;
            }
            table.items thead th:last-child { text-align: right; }
            table.items thead th:nth-child(3) { text-align: center; }
            table.items tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 12px; }
            table.items tbody td:last-child { text-align: right; }
            table.items tbody td:nth-child(3) { text-align: center; }
            table.items tbody tr:nth-child(even) { background-color: #f9f9f9; }
            .totals { float: right; width: 300px; margin-bottom: 25px; }
            .totals table { width: 100%; }
            .totals td { padding: 5px 10px; font-size: 12px; }
            .totals td:last-child { text-align: right; }
            .totals .grand-total td { font-size: 16px; font-weight: bold; color: #0A2540; border-top: 2px solid #D4AF37; padding-top: 8px; }
            .notes, .terms { margin-bottom: 15px; }
            .notes h4, .terms h4 { color: #0A2540; font-size: 13px; margin: 0 0 5px; }
            .notes p, .terms p { font-size: 11px; color: #666; margin: 0; }
            .footer { 
                position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #aaa;
                border-top: 1px solid #eee; padding-top: 8px;
            }
            .valid-until { font-size: 11px; color: #888; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="header-left">
                <img src="' . $logoSrc . '" alt="Logo">
                <h1>' . htmlspecialchars($siteName) . '</h1>
            </div>
            <div class="header-right">
                <h2>QUOTATION</h2>
                <div class="badge">' . htmlspecialchars($quote['status']) . '</div>
            </div>
        </div>

        <div class="info-section">
            <div class="bill-to">
                <h4>Bill To:</h4>
                <p><strong>' . htmlspecialchars($quote['full_name']) . '</strong></p>
                <p>Email: ' . htmlspecialchars($quote['email']) . '</p>
                <p>Phone: ' . htmlspecialchars($quote['phone'] ?: 'N/A') . '</p>
            </div>
            <div class="quote-meta">
                <table>
                    <tr><td>Quote #:</td><td>' . htmlspecialchars($quote['quote_number']) . '</td></tr>
                    <tr><td>Date:</td><td>' . date('M d, Y', strtotime($quote['created_at'])) . '</td></tr>
                    <tr><td>Valid Until:</td><td>' . ($quote['valid_until'] ? date('M d, Y', strtotime($quote['valid_until'])) : 'N/A') . '</td></tr>
                </table>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:8%;">#</th>
                    <th>Description</th>
                    <th style="width:10%;">Qty</th>
                    <th style="width:18%;">Unit Price</th>
                    <th style="width:18%;">Total</th>
                </tr>
            </thead>
            <tbody>';
    
    $i = 1;
    foreach ($items as $item) {
        $html .= '
                <tr>
                    <td>' . $i++ . '</td>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td>' . (int)$item['quantity'] . '</td>
                    <td>' . $currency . number_format($item['unit_price'], 2) . '</td>
                    <td>' . $currency . number_format($item['total'], 2) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr><td>Subtotal:</td><td>' . $currency . number_format($quote['subtotal'], 2) . '</td></tr>';
    
    if ($quote['tax_percent'] > 0) {
        $html .= '    <tr><td>Tax (' . number_format($quote['tax_percent'], 1) . '%):</td><td>' . $currency . number_format($quote['tax_amount'], 2) . '</td></tr>';
    }
    if ($quote['discount'] > 0) {
        $html .= '    <tr><td>Discount:</td><td>-' . $currency . number_format($quote['discount'], 2) . '</td></tr>';
    }
    
    $html .= '    <tr class="grand-total"><td>Total:</td><td>' . $currency . number_format($quote['total'], 2) . '</td></tr>
            </table>
        </div>

        <div style="clear:both;"></div>';

    if (!empty($quote['notes'])) {
        $html .= '
        <div class="notes">
            <h4>Notes:</h4>
            <p>' . nl2br(htmlspecialchars($quote['notes'])) . '</p>
        </div>';
    }

    if (!empty($quote['terms'])) {
        $html .= '
        <div class="terms">
            <h4>Terms &amp; Conditions:</h4>
            <p>' . nl2br(htmlspecialchars($quote['terms'])) . '</p>
        </div>';
    }

    $html .= '
        <p class="valid-until">This quotation is valid until ' . ($quote['valid_until'] ? date('F d, Y', strtotime($quote['valid_until'])) : 'N/A') . '.</p>

        <div class="footer">
            ' . htmlspecialchars($siteName) . ' | ' . htmlspecialchars($siteAddress) . ' | ' . htmlspecialchars($siteEmail) . ' | ' . htmlspecialchars($sitePhone) . '
        </div>
    </body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'quote_' . $quote['quote_number'] . '_' . date('Ymd') . '.pdf';
    $relativePath = 'uploads/quotes/' . $filename;
    $fullPath = __DIR__ . '/../' . $relativePath;

    file_put_contents($fullPath, $dompdf->output());

    $db->query("UPDATE quotes SET pdf_path = ? WHERE id = ?", [$relativePath, $quoteId]);

    return $relativePath;
}

function sendQuoteEmail($quoteId) {
    $db = db();

    $quote = $db->fetchOne("
        SELECT q.*,
            COALESCE(i.full_name, b.full_name) AS full_name,
            COALESCE(i.email, b.email) AS email,
            COALESCE(i.phone, b.phone) AS phone
        FROM quotes q
        LEFT JOIN inquiries i ON q.inquiry_id = i.id
        LEFT JOIN bookings b ON q.booking_id = b.id
        WHERE q.id = ?
    ", [$quoteId]);

    if (!$quote) {
        throw new Exception('Quote not found');
    }

    if (empty($quote['pdf_path']) || !file_exists(__DIR__ . '/../' . $quote['pdf_path'])) {
        $quote['pdf_path'] = generateQuotePdf($quoteId);
    }

    $fullPdfPath = __DIR__ . '/../' . $quote['pdf_path'];
    $siteName = getSetting('site_name', 'Kizza Tours & Safaris');

    $subject = !empty($quote['email_subject']) ? $quote['email_subject'] : ('Your Quotation from ' . $siteName . ' - ' . $quote['quote_number']);

    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <div style='background: #0A2540; padding: 25px; text-align: center;'>
                <img src='" . SITE_URL . "/assets/images/ogimage.png' alt='Logo' height='50'>
                <h2 style='color: #D4AF37; margin: 10px 0 0;'>Your Personalized Quotation</h2>
            </div>
            <div style='padding: 30px;'>
                <p>Dear <strong>" . htmlspecialchars($quote['full_name']) . "</strong>,</p>
                <p>Thank you for choosing " . htmlspecialchars($siteName) . "!</p>
                <p>We are pleased to attach your personalized quotation (Ref: <strong>" . htmlspecialchars($quote['quote_number']) . "</strong>). This package has been carefully designed to give you the best experience.</p>
                <p>Please review the details and let us know if you would like to make any adjustments. To confirm, simply reply to this email or contact us via phone/WhatsApp.</p>
                <p style='text-align: center; margin: 25px 0;'>
                    <a href='" . SITE_URL . "/" . $quote['pdf_path'] . "' style='background: #D4AF37; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Download Your Quote (PDF)</a>
                </p>
                <p>We look forward to welcoming you to East Africa!</p>
                <p>Warm regards,<br><strong>" . htmlspecialchars($siteName) . " Team</strong></p>
            </div>
            <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
                " . htmlspecialchars($siteName) . " | " . htmlspecialchars(getSetting('site_address', 'Arusha, Tanzania')) . " | " . htmlspecialchars(getSetting('site_email', 'info@kizzatours.com')) . "
            </div>
        </div>
    </body>
    </html>";

    require_once __DIR__ . '/mail.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $smtpHost = getSetting('smtp_host', '');
        $smtpPort = getSetting('smtp_port', '587');
        $smtpUser = getSetting('smtp_user', '');
        $smtpPass = getSetting('smtp_pass', '');
        $smtpEnc  = getSetting('smtp_encryption', 'tls');

        if (!empty($smtpHost)) {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $smtpEnc === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $smtpPort;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mail->setFrom(getSetting('site_email', SITE_EMAIL), $siteName);
        $mail->addAddress($quote['email'], $quote['full_name']);
        $mail->addAttachment($fullPdfPath, basename($quote['pdf_path']));
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body));

        $mail->send();

        $db->query("UPDATE quotes SET status = 'sent' WHERE id = ?", [$quoteId]);

        return true;
    } catch (\Throwable $e) {
        error_log("sendQuoteEmail Error: " . $e->getMessage());
        return false;
    }
}
