<?php

namespace App\Service;

use App\Exception\ParsingException;
use App\Model\Application;
use App\Model\Device;
use App\Model\Gateway;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiClient implements ApiClientInterface
{
    public function __construct(
        private HttpClientInterface $iotApiClient,
        private ApiParser $apiParser,
        private int $gateWayOrgId,
        private int $apiRequestLimit,
        private CacheInterface $cache,
        private int $applicationCacheTTL,
        private int $gatewayCacheTTL,
    ) {
    }

    /**
     * Get all applications.
     *
     * @param bool $filterOnStatus
     *   Filter out applications based on statuses given in configuration
     *
     * @return array<Application>
     *   Parsed applications
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getApplications(bool $filterOnStatus): array
    {
        $cacheKey = 'iot_applications';
        $content = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter($this->applicationCacheTTL);

            // Currently the IoT API does not work correctly with offset and limits.
            // Therefore, we use offset 0 and set limit high, .e.g. 999,
            // to just get all applications.
            $response = $this->iotApiClient->request('GET', '/api/v1/application', [
                'query' => [
                    'offset' => 0,
                    'limit' => $this->apiRequestLimit,
                ],
            ]);

            return $response->getContent();
        });

        return $this->apiParser->applications($content, $filterOnStatus);
    }

    /**
     * Get a single application.
     *
     * @param int $id
     *   ID for the application to fetch
     *
     * @return Application
     *   Parsed application
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getApplication(int $id): Application
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/application/'.$id);
        $content = $response->getContent();

        return $this->apiParser->application($content);
    }

    /**
     * Fetch a single IoT device.
     *
     * @param int $id
     *   Identifier for the IoT device to retrieve
     *
     * @return Device
     *   Parsed IoT device
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getDevice(int $id): Device
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/iot-device/'.$id);
        $content = $response->getContent();

        // We need to enrich the information about last gateway that have
        // received a message from a devices.
        $gateways = $this->getGateways(false);

        return $this->apiParser->device($content, $gateways);
    }

    /**
     * Retrieve a list of gateways.
     *
     * @param bool $filterOnStatus
     *   Indicates whether to filter gateways based on a specific status
     *
     * @return array<Gateway>
     *   An array of parsed gateways
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws InvalidArgumentException
     */
    public function getGateways(bool $filterOnStatus): array
    {
        $cacheKey = 'iot_gateways';
        $content = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter($this->gatewayCacheTTL);

            // Currently the IoT API does not work correctly with offset and limits.
            // Therefore, we use offset 0 and set limit high, .e.g. 999,
            // to just get all gateways.
            $response = $this->iotApiClient->request('GET', '/api/v1/chirpstack/gateway', [
                'query' => [
                    'organizationId' => $this->gateWayOrgId,
                    'offset' => 0,
                    'limit' => $this->apiRequestLimit,
                ],
            ]);

            return $response->getContent();
        });

        return $this->apiParser->gateways($content, $filterOnStatus);
    }

    /**
     * Retrieve a single gateway.
     *
     * @param string $id
     *   ID of the gateway to fetch
     *
     * @return Gateway
     *   Parsed gateway
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getGateway(string $id): Gateway
    {
        $cacheKey = 'iot_gateway_'.$id;
        $content = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter($this->gatewayCacheTTL);

            $response = $this->iotApiClient->request('GET', '/api/v1/chirpstack/gateway/'.$id);

            return $response->getContent();
        });

        return $this->apiParser->gateway($content);
    }
}
