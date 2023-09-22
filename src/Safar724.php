<?php

namespace Daalvand\Safar724AutoTrack;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class Safar724
{
    protected FilesystemAdapter $cache;
    public const BASE_URL = 'https://safar724.com';
    public const VERIFY   = false;
    public const HEADERS  = [
        'authority'          => 'safar724.com',
        'accept'             => '*/*',
        'accept-language'    => 'en-US,en;q=0.9,fa;q=0.8,de;q=0.7',
        'cookie'             => '__RequestVerificationToken=OZuZkqb0HBAYu0JW7rA583BKb7GD3sxjnpXa0M9gfX14N6oaSR0QXjhht4JOnULTUfuXqA-ed9VGYxDAaLwlt9N7lmGFaaM2c5CBbIGmz3Y1; .AspNet.ApplicationCookie=yCEcwi0fvM04R3cj62A3tSFU7V_UnGF3HFruQkyyRZppvmF2e9XIUmK4RmxNFpJK25HegHme4n1AqSCqT68kgs1F9AkqVZ5nW3n819M871-7exlSPtoVsp-xh8VfDfLYjAM7hUy3WV7pQPaIWo-uUUK7rY5aAPqqOhA5MqMXdvHak-OWc-rAbkBrlXICMWrEKqoWVlIrx8FZAPvOLk1e8uKXaYEigNfF0GlaC-_tZiP3XinxbJ9DFZl4FqFfAPzBIpRG9QzBNzppQTWohN3MyfXmSVLyN3_rBodcZO0Cm_P5juCP-lPk_QRmNLycCabsKjEVf2yNpiw9EWSYppnXgTAGFO4Fe1gSWDVomDWwPXpA9BLYYLeLKaQJGTP0YzsdNuUks4lUdVKD_FJ_zCU09izHaJ7xFQUEkNhyME-gT7hlF_IxP5c3jxTYIR2kEGHVdKFThaFqbSTZFR2YUz9qLsKCp3o1IvNX6AfkHtZ8JBsEsUBlSRDZOdZHHxTOczsI6t2C7XEjOzTGVCsKqMz4L5vUvqbZbisIFNWMgCaxQNJxcfBAGkowT2pzrAsrX6lA0tr7VRPcK1Yqtzj8Yl3aCogabLjoVQGzsQgcQUV_nWgCKmdBzQ3x7H1VOQ3NpsiISdwj7Mc4V-dVYC6KFe64EXn3qkX9t6_g13dmCk9EiXU',
        'referer'            => 'https://safar724.com/bus/borujerd-tehran?date=1402-06-28',
        'sec-ch-ua'          => '"Microsoft Edge";v="117", "Not;A=Brand";v="8", "Chromium";v="117"',
        'sec-ch-ua-mobile'   => '?0',
        'sec-ch-ua-platform' => '"Windows"',
        'sec-fetch-dest'     => 'empty',
        'sec-fetch-mode'     => 'cors',
        'sec-fetch-site'     => 'same-origin',
        'user-agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36 Edg/117.0.2045.31',
        'x-requested-with'   => 'XMLHttpRequest'
    ];

    public function __construct() {

        $this->cache = new FilesystemAdapter();
    }

    public function checkTicket(Carbon|Jalalian $date, string|int $source, string|int $destination): array {
        $sourceId      = (int)$source === $source ? $source : $this->getId($source);
        $destinationId = (int)$destination === $destination ? $destination : $this->getId($destination);

        if (!$sourceId || !$destinationId) {
            throw new InvalidArgumentException('Invalid source or destination');
        }

        if ($date instanceof Jalalian) {
            $dateString = $date->format('Y-m-d');
        } else {
            $dateString = Jalalian::fromCarbon($date)->format('Y-m-d');
        }
        $query = [
            'origin'      => $sourceId,
            'destination' => $destinationId,
            'date'        => $dateString
        ];

        $res = $this->request('bus/getservices', 'GET', [
            'query' => $query
        ]);

        return json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function request(string $path, string $httpMethod = 'GET', array $options = []): ResponseInterface {
        $url = self::BASE_URL . '/' . trim($path, '/ ');

        $options += ['headers' => self::HEADERS, 'http_errors' => false, 'verify' => self::VERIFY];
        try {
            $client   = new Client();
            $response = $client->request($httpMethod, $url, $options);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('safar724 error error CODE: ' . $response->getStatusCode() . ' BODY: ' . $response->getBody()->getContents());
            }
            return $response;
        } catch (GuzzleException $e) {
            throw new RuntimeException('safar724 error happened: ' . $e->getMessage());
        }
    }

    private function getId(string $location): int|null {
        $cities = $this->getCities();

        foreach ($cities as $city) {
            if (stripos($city['Name'], $location) !== false || stripos($city['PersianName'], $location) !== false) {
                $cityEntry = $city;
                break; // Break the loop once a match is found
            }
        }
        return isset($cityEntry['Code']) ? (int)$cityEntry['Code'] : null;
    }

    public function getCities(): array {
        return $this->cache->get("safar724_cities", function (ItemInterface $item): array {
            $response = $this->request('route/getcities');
            $json     = $response->getBody()->getContents();
            $cities   = json_decode($json, true);
            file_put_contents(__DIR__ . '/all-cities.json', json_encode($cities, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $cities;
        });
    }

}