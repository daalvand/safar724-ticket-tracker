<?php

namespace Daalvand\Safar724AutoTrack;

use Carbon\Carbon;
use Daalvand\Safar724AutoTrack\Exceptions\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
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

    public function __construct()
    {
        $this->cache = new FilesystemAdapter();
    }

    /**
     * @param int $origin
     * @param $destination
     * @param string $date
     * @return mixed
     * @throws JsonException
     * @throws RequestException
     */
    public function setServices(int $origin, $destination, string $date)
    {
        $res     = $this->request('bus/getservices', 'GET', [
            'query' => [
                'origin'      => $origin,
                'destination' => $destination,
                'date'        => $date,
            ]
        ]);
        return json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param int $id
     * @param int $destination
     * @return array
     * @throws JsonException
     * @throws RequestException
     */
    public function setServiceDetail(int $id, int $destination): array
    {
        $res = $this->request('checkout/servicedetails', 'GET', ['query' => ['destinationCode' => $destination, 'id' => $id]]);
        return json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }


    /**
     * @param Carbon|Jalalian $date
     * @param string|int $source
     * @param string|int $destination
     * @return array
     * @throws RequestException
     * @throws JsonException
     */
    public function checkTicket(Carbon|Jalalian $date, string|int $source, string|int $destination): array
    {
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

    /**
     * @param string $path
     * @param string $httpMethod
     * @param array $options
     * @return ResponseInterface
     * @throws RequestException
     */
    public function request(string $path, string $httpMethod = 'GET', array $options = []): ResponseInterface
    {
        $url = self::BASE_URL . '/' . trim($path, '/ ');

        $options += ['headers' => $this->getHeaders(), 'http_errors' => false, 'verify' => self::VERIFY];
        try {
            $client   = new Client();
            $response = $client->request($httpMethod, $url, $options);
            if ($response->getStatusCode() !== 200) {
                throw new RequestException('safar724 error code:: ' . $response->getStatusCode() . 'body:: '. $response->getBody());
            }
            return $response;
        } catch (GuzzleException $e) {
            throw new RequestException('safar724 error',  $e->getCode(), $e);
        }
    }

    public function getId(string $location): int|null
    {
        $cities = $this->getCities();

        foreach ($cities as $city) {
            if (stripos($city['Name'], $location) !== false || stripos($city['PersianName'], $location) !== false) {
                $cityEntry = $city;
                break; // Break the loop once a match is found
            }
        }
        return isset($cityEntry['Code']) ? (int)$cityEntry['Code'] : null;
    }

    public function getCities(): array
    {
        return $this->cache->get("safar724_cities", function (ItemInterface $item): array {
            $response = $this->request('route/getcities');
            $json     = $response->getBody()->getContents();
            $cities   = json_decode($json, true);
            file_put_contents(__DIR__ . '/../resources/all-cities.json', json_encode($cities, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $cities;
        });
    }

    private function getHeaders(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/safar724-headers.json'), true);
    }

}