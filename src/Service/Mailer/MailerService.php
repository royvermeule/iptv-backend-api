<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use PHPMailer\PHPMailer\PHPMailer;

class MailerService
{
    private TemplateService $templates;

    public function __construct(?TemplateService $templates = null)
    {
        $this->templates = $templates ?? new TemplateService();
    }

    public function sendVerificationEmail(string $toEmail, string $token): void
    {
        $frontendUrl = rtrim($_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:3000', '/');
        $verifyUrl   = "{$frontendUrl}/verify-email?token={$token}";

        $body = $this->templates->render('verify-email.html', [
            'verify_url' => $verifyUrl,
        ]);

        $this->send($toEmail, 'Verify your email address', $body, strip_tags($body));
    }

    public function sendPasswordResetEmail(string $toEmail, string $token, int $expiryMinutes = 60): void
    {
        $frontendUrl = rtrim($_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:3000', '/');
        $resetUrl    = "{$frontendUrl}/reset-password?token={$token}";

        $body = $this->templates->render('reset-password.html', [
            'reset_url'      => $resetUrl,
            'expiry_minutes' => $expiryMinutes,
        ]);

        $this->send($toEmail, 'Reset your password', $body, strip_tags($body));
    }

    private function send(string $toEmail, string $subject, string $htmlBody, string $plainBody): void
    {
        $mail = $this->createMailer();
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
    }

    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(exceptions: true);
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'] ?? '127.0.0.1';
        $mail->Port = (int) ($_ENV['MAIL_PORT'] ?? 1025);

        $username = $_ENV['MAIL_USERNAME'] ?? '';
        $password = $_ENV['MAIL_PASSWORD'] ?? '';

        if ($username !== '' && $password !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }

        $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';
        if ($encryption !== '') {
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
            $_ENV['MAIL_FROM_NAME']    ?? 'IPTV App',
        );

        return $mail;
    }
}
