<?php

declare(strict_types=1);

namespace App\Service\Xtream;

class SyncService
{
    private const ACTIONS = [
        'live_categories'   => 'get_live_categories',
        'live_streams'      => 'get_live_streams',
        'vod_categories'    => 'get_vod_categories',
        'vod_streams'       => 'get_vod_streams',
        'series_categories' => 'get_series_categories',
        'series'            => 'get_series',
    ];

    public function fetch(string $url, string $username, string $password): string
    {
        $base = rtrim($url, '/') . '/player_api.php?' . http_build_query([
            'username' => $username,
            'password' => $password,
        ]);

        $mh      = curl_multi_init();
        $handles = [];

        foreach (self::ACTIONS as $key => $action) {
            $ch = curl_init($base . '&action=' . $action);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh);
            }
        } while ($running && $status === CURLM_OK);

        $raw = [];
        foreach ($handles as $key => $ch) {
            $body      = curl_multi_getcontent($ch);
            // Validate without decoding — avoids the 10× memory overhead of json_decode
            $raw[$key] = ($body !== false && json_validate($body)) ? $body : '[]';
            curl_multi_remove_handle($mh, $ch);
        }

        return sprintf(
            '{"live":{"categories":%s,"streams":%s},"vod":{"categories":%s,"streams":%s},"series":{"categories":%s,"streams":%s}}',
            $raw['live_categories'],
            $raw['live_streams'],
            $raw['vod_categories'],
            $raw['vod_streams'],
            $raw['series_categories'],
            $raw['series'],
        );
    }
}
