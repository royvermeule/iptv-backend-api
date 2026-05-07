<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Service\Mailer\MailerService;
use Predis\Client;

class EmailWorkerJob implements JobInterface
{
    public function __construct(
        private readonly Client $redis,
        private readonly MailerService $mailer,
    ) {}

    public function getName(): string
    {
        return 'email-worker';
    }

    public function getDescription(): string
    {
        return 'Processes the email jobs queue and sends emails';
    }

    public function run(): void
    {
        echo "Email worker started. Waiting for jobs...\n";

        while (true) {
            $result = $this->redis->brpop('email_jobs', 5);

            if ($result === null) {
                continue;
            }

            $payload = json_decode($result[1], true);

            if (!is_array($payload) || !isset($payload['type'])) {
                fwrite(STDERR, "Invalid job payload: {$result[1]}\n");
                continue;
            }

            try {
                match ($payload['type']) {
                    'verify_email' => $this->mailer->sendVerificationEmail(
                        $payload['email'],
                        $payload['token'],
                    ),
                    'reset_password' => $this->mailer->sendPasswordResetEmail(
                        $payload['email'],
                        $payload['token'],
                        (int) ($payload['expiry_minutes'] ?? 60),
                    ),
                    default => fwrite(STDERR, "Unknown job type: {$payload['type']}\n"),
                };

                echo "Sent {$payload['type']} to {$payload['email']}\n";
            } catch (\Throwable $e) {
                fwrite(STDERR, "Failed to process job [{$payload['type']}]: {$e->getMessage()}\n");
            }
        }
    }
}
