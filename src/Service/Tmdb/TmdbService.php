<?php

declare(strict_types=1);

namespace App\Service\Tmdb;

class TmdbService
{
    private const BASE_URL = 'https://api.themoviedb.org/3';

    public function fetchTrending(string $region): array
    {
        $apiKey = $_ENV['TMDB_API_KEY'];

        $urls = [
            'movies' => self::BASE_URL . '/trending/movie/week?' . http_build_query(['region' => $region, 'api_key' => $apiKey]),
            'tv'     => self::BASE_URL . '/trending/tv/week?'    . http_build_query(['region' => $region, 'api_key' => $apiKey]),
        ];

        $mh      = curl_multi_init();
        $handles = [];

        foreach ($urls as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
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
            $body = curl_multi_getcontent($ch);
            $data = $body !== false ? json_decode($body, true) : null;

            if (!isset($data['results'])) {
                throw new \DomainException('Failed to fetch trending data from TMDB', 502);
            }

            $raw[$key] = $data['results'];
            curl_multi_remove_handle($mh, $ch);
        }

        return [
            'region' => $region,
            'movies' => array_map(fn(array $item) => [
                'tmdb_id'      => $item['id'],
                'media_type'   => 'movie',
                'title'        => $item['title'] ?? '',
                'year'         => substr($item['release_date'] ?? '', 0, 4),
                'overview'     => $item['overview'] ?? '',
                'poster_path'  => $item['poster_path'] ?? null,
                'vote_average' => $item['vote_average'] ?? 0,
            ], $raw['movies']),
            'tv' => array_map(fn(array $item) => [
                'tmdb_id'      => $item['id'],
                'media_type'   => 'tv',
                'title'        => $item['name'] ?? '',
                'year'         => substr($item['first_air_date'] ?? '', 0, 4),
                'overview'     => $item['overview'] ?? '',
                'poster_path'  => $item['poster_path'] ?? null,
                'vote_average' => $item['vote_average'] ?? 0,
            ], $raw['tv']),
        ];
    }
}
