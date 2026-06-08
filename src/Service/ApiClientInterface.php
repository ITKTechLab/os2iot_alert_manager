<?php

namespace App\Service;

use App\Model\Application;
use App\Model\Device;
use App\Model\Gateway;

interface ApiClientInterface
{
    /**
     * Get all applications.
     *
     * @param bool $filterOnStatus
     *   Filter out applications based on statuses given in configuration
     *
     * @return array<Application>
     *   Parsed applications
     */
    public function getApplications(bool $filterOnStatus): array;

    /**
     * Get a single application.
     *
     * @param int $id
     *   ID for the application to fetch
     *
     * @return Application
     *   Parsed application
     */
    public function getApplication(int $id): Application;

    /**
     * Fetch a single IoT device.
     *
     * @param int $id
     *   Identifier for the IoT device to retrieve
     *
     * @return Device
     *   Parsed IoT device
     */
    public function getDevice(int $id): Device;

    /**
     * Retrieve a list of gateways.
     *
     * @param bool $filterOnStatus
     *   Indicates whether to filter gateways based on a specific status
     *
     * @return array<Gateway>
     *   An array of parsed gateways
     */
    public function getGateways(bool $filterOnStatus): array;

    /**
     * Retrieve a single gateway.
     *
     * @param string $id
     *   ID of the gateway to fetch
     *
     * @return Gateway
     *   Parsed gateway
     */
    public function getGateway(string $id): Gateway;
}
