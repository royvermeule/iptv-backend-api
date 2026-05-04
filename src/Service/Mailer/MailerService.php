<?php

declare(strict_types=1);

namespace App\Service\Mailer;

use PHPMailer\PHPMailer\PHPMailer;

class MailerService
{
    public function sendVerificationEmail(string $toEmail, string $token): void
    {
        $frontendUrl = rtrim($_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:3000', '/');
        $verifyUrl   = "{$frontendUrl}/verify-email?token={$token}";

        $mail = $this->createMailer();
        $mail->addAddress($toEmail);
        $mail->Subject = 'Verify your email address';
        $mail->isHTML(true);
        $mail->Body    = $this->buildHtmlBody($verifyUrl);
        $mail->AltBody = "Please verify your email by visiting: {$verifyUrl}";
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

    private function buildHtmlBody(string $verifyUrl): string
    {
        $escaped = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <p>Thank you for registering. Please verify your email address by clicking the link below:</p>
        <p><a href="{$escaped}">{$escaped}</a></p>
        <p>If you did not create an account, you can safely ignore this email.</p>
        HTML;
    }
}
