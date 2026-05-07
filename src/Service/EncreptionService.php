<?php

declare(strict_types=1);

namespace App\Service;

class EncryptionService
{
    private string $key;

    public function __construct(?string $key = null)
    {
        $this->key =  $key ?? $_ENV['APP_KEY'];
    }

    public function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $decoded    = base64_decode($encoded, strict: true);
        $iv         = substr($decoded, 0, 12);
        $tag        = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed, ciphertext may be corrupt or key mismatch');
        }

        return $plaintext;
    }
}
