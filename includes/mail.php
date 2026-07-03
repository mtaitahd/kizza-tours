<?php
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

function sendMail($to, $subject, $body, $replyTo = '', $replyToName = '') {
    try {
        $mail = new PHPMailer(true);

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
            $mail->SMTPSecure = $smtpEnc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $smtpPort;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mail->setFrom(getSetting('site_email', SITE_EMAIL), getSetting('site_name', SITE_NAME));
        $mail->addAddress($to);

        if (!empty($replyTo)) {
            $mail->addReplyTo($replyTo, $replyToName ?: $replyTo);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body));

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log("sendMail Error [" . get_class($e) . "]: " . $e->getMessage());
        return false;
    }
}
