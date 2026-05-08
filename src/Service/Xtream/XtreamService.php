<?php

declare(strict_types=1);

namespace App\Service\Xtream;

class XtreamService
{
    public function testCredentials(string $url, string $username, string $password): bool
    {
        $apiUrl = rtrim($url, '/') . '/player_api.php?' . http_build_query([
            'username' => $username,
            'password' => $password,
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);

        return isset($data['user_info']['auth']) && (int) $data['user_info']['auth'] === 1;
    }
}
