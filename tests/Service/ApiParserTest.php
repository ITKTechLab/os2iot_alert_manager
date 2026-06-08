<?php

namespace App\Tests\Service;

use App\Exception\ParsingException;
use App\Model\Application;
use App\Model\DataTypes\Location;
use App\Model\DataTypes\Message;
use App\Model\DataTypes\ReceivedInfo;
use App\Model\DataTypes\Status;
use App\Model\Device;
use App\Model\Gateway;
use App\Service\ApiParser;
use ItkDev\MetricsBundle\Service\MetricsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiParser::class)]
#[UsesClass(Application::class)]
#[UsesClass(Device::class)]
#[UsesClass(Gateway::class)]
#[UsesClass(Location::class)]
#[UsesClass(Message::class)]
#[UsesClass(ReceivedInfo::class)]
#[UsesClass(Status::class)]
final class ApiParserTest extends TestCase
{
    private MetricsService&MockObject $metrics;

    protected function setUp(): void
    {
        $this->metrics = $this->createMock(MetricsService::class);
    }

    private function makeParser(array $statuses = ['IN-OPERATION', 'PROJECT']): ApiParser
    {
        return new ApiParser($this->metrics, $statuses, 'UTC', 'Europe/Copenhagen', 'Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Capture every counter() name emitted by the parser.
     *
     * @param list<string> $names
     */
    private function captureCounters(array &$names): void
    {
        $this->metrics->method('counter')->willReturnCallback(
            function (string $name) use (&$names): void {
                $names[] = $name;
            }
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function applicationData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'createdAt' => '2024-07-01T10:00:00.000000Z',
            'updatedAt' => '2024-07-02T10:00:00.000000Z',
            'startDate' => null,
            'endDate' => null,
            'name' => 'Test App',
            'status' => 'IN-OPERATION',
            'contactPerson' => 'John Doe',
            'contactEmail' => 'john@example.test',
            'contactPhone' => '+4512345678',
            'iotDevices' => [['id' => 101], ['id' => 102]],
        ], $overrides);
    }

    private function applicationJson(array $overrides = []): string
    {
        return json_encode($this->applicationData($overrides), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int, array<string, mixed>> $apps
     */
    private function applicationsJson(array $apps): string
    {
        return json_encode(['data' => $apps], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function deviceData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'application' => ['id' => 10],
            'createdAt' => '2024-07-01T10:00:00.000000Z',
            'updatedAt' => '2024-07-09T10:00:00.000000Z',
            'name' => 'Sensor 01',
            'deviceEUI' => '0123456789ABCDEF',
            'location' => ['latitude' => 56.15, 'longitude' => 10.2],
            'lorawanSettings' => ['deviceStatusBattery' => 85],
            'metadata' => '{}',
            'latestReceivedMessage' => null,
        ], $overrides);
    }

    private function deviceJson(array $overrides = []): string
    {
        return json_encode($this->deviceData($overrides), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function gatewayData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'gatewayId' => 'gw-eui-001',
            'createdAt' => '2024-07-01T10:00:00.000000Z',
            'updatedAt' => '2024-07-02T10:00:00.000000Z',
            'lastSeenAt' => '2024-07-03T10:00:00.000000Z',
            'name' => 'Gateway Main',
            'description' => 'Main gateway',
            'location' => ['latitude' => 56.15, 'longitude' => 10.2],
            'status' => 'IN-OPERATION',
            'gatewayResponsibleName' => 'Jane',
            'gatewayResponsibleEmail' => 'jane@example.test',
            'gatewayResponsiblePhoneNumber' => '+4587654321',
            'tags' => ['env' => 'prod'],
        ], $overrides);
    }

    private function gatewayJson(array $overrides = []): string
    {
        return json_encode(['gateway' => $this->gatewayData($overrides)], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int, array<string, mixed>> $gateways
     */
    private function gatewaysJson(array $gateways): string
    {
        return json_encode(['resultList' => $gateways], JSON_UNESCAPED_UNICODE);
    }

    #[Test]
    public function applicationParsesScalarFieldsAndDeviceIds(): void
    {
        $app = $this->makeParser()->application($this->applicationJson());

        self::assertSame(1, $app->id);
        self::assertSame('Test App', $app->name);
        self::assertSame('john@example.test', $app->contactEmail);
        self::assertSame('+4512345678', $app->contactPhone);
        self::assertSame(Status::IN_OPERATION, $app->status);
        self::assertSame([101, 102], $app->devices);
    }

    #[Test]
    public function applicationNullStartAndEndDatesStayNull(): void
    {
        $app = $this->makeParser()->application($this->applicationJson());

        self::assertNull($app->startDate);
        self::assertNull($app->endDate);
    }

    #[Test]
    public function applicationsFilterOnStatusKeepsConfiguredStatuses(): void
    {
        $gaugeValues = [];
        $this->metrics->method('gauge')->willReturnCallback(
            function (string $name, string $help, int $value) use (&$gaugeValues): void {
                $gaugeValues[$name] = $value;
            }
        );

        $json = $this->applicationsJson([
            $this->applicationData(['id' => 1, 'status' => 'IN-OPERATION']),
            $this->applicationData(['id' => 2, 'status' => 'PROJECT']),
            $this->applicationData(['id' => 3, 'status' => 'OTHER']),
        ]);

        $result = $this->makeParser()->applications($json, true);

        self::assertCount(2, $result);
        self::assertSame(2, $gaugeValues['api_parsed_applications']);
    }

    #[Test]
    public function applicationsWithoutFilterReturnsAll(): void
    {
        $json = $this->applicationsJson([
            $this->applicationData(['id' => 1, 'status' => 'IN-OPERATION']),
            $this->applicationData(['id' => 2, 'status' => 'OTHER']),
        ]);

        self::assertCount(2, $this->makeParser()->applications($json, false));
    }

    /**
     * @return array<string, array{0: string|null, 1: Status}>
     */
    public static function statusProvider(): array
    {
        return [
            'in-operation' => ['IN-OPERATION', Status::IN_OPERATION],
            'project' => ['PROJECT', Status::PROJECT],
            'prototype' => ['PROTOTYPE', Status::PROTOTYPE],
            'other' => ['OTHER', Status::OTHER],
            'null becomes none' => [null, Status::NONE],
        ];
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function statusIsMappedToEnum(?string $status, Status $expected): void
    {
        $app = $this->makeParser()->application($this->applicationJson(['status' => $status]));

        self::assertSame($expected, $app->status);
    }

    #[Test]
    public function invalidStatusThrowsAndCountsMetric(): void
    {
        $counters = [];
        $this->captureCounters($counters);

        try {
            $this->makeParser()->application($this->applicationJson(['status' => 'GARBAGE']));
            self::fail('Expected ParsingException was not thrown.');
        } catch (ParsingException) {
            self::assertContains('api_parse_status_invalid_total', $counters);
        }
    }

    #[Test]
    public function dateIsConvertedFromUtcToCopenhagen(): void
    {
        $summer = $this->makeParser()->application($this->applicationJson(['createdAt' => '2024-07-01T10:00:00.000000Z']));
        self::assertSame('2024-07-01 12:00:00', $summer->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Copenhagen', $summer->createdAt->getTimezone()->getName());

        $winter = $this->makeParser()->application($this->applicationJson(['createdAt' => '2024-01-01T10:00:00.000000Z']));
        self::assertSame('2024-01-01 11:00:00', $winter->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function nullDateBecomesEpochInTargetTimezone(): void
    {
        $app = $this->makeParser()->application($this->applicationJson(['createdAt' => null]));

        self::assertSame('1970-01-01 00:00:00', $app->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Copenhagen', $app->createdAt->getTimezone()->getName());
    }

    #[Test]
    public function malformedDateThrowsAndCountsMetric(): void
    {
        $counters = [];
        $this->captureCounters($counters);

        try {
            $this->makeParser()->application($this->applicationJson(['createdAt' => 'not-a-date']));
            self::fail('Expected ParsingException was not thrown.');
        } catch (ParsingException) {
            self::assertContains('api_parse_date_error_total', $counters);
        }
    }

    #[Test]
    public function deviceParsesCoreFields(): void
    {
        $device = $this->makeParser()->device($this->deviceJson(), []);

        self::assertInstanceOf(Device::class, $device);
        self::assertSame(1, $device->id);
        self::assertSame(10, $device->applicationId);
        self::assertSame('0123456789ABCDEF', $device->eui);
        self::assertSame(85.0, $device->statusBattery);
        self::assertNull($device->latestReceivedMessage);
    }

    #[Test]
    public function deviceLocationFromCoordinatesArrayMapsLatitudeFromEnd(): void
    {
        // GeoJSON-style [longitude, latitude]: latitude = end(), longitude = reset().
        $device = $this->makeParser()->device($this->deviceJson(['location' => ['coordinates' => [10, 56]]]), []);

        self::assertSame('56', $device->location->latitude);
        self::assertSame('10', $device->location->longitude);
    }

    #[Test]
    public function deviceWithoutLocationFallsBackToZero(): void
    {
        $device = $this->makeParser()->device($this->deviceJson(['location' => null]), []);

        self::assertSame('0', $device->location->latitude);
        self::assertSame('0', $device->location->longitude);
    }

    #[Test]
    public function deviceDefaultsAreApplied(): void
    {
        $device = $this->makeParser()->device($this->deviceJson(['deviceEUI' => null, 'lorawanSettings' => []]), []);

        self::assertSame('unknown', $device->eui);
        self::assertSame(-1.0, $device->statusBattery);
    }

    #[Test]
    public function deviceMetadataStringIsDecodedToArray(): void
    {
        $json = $this->deviceJson(['metadata' => json_encode(['email' => 'owner@example.test'])]);

        $device = $this->makeParser()->device($json, []);

        self::assertSame(['email' => 'owner@example.test'], $device->metadata);
    }

    #[Test]
    public function deviceInvalidMetadataThrowsAndCountsMetric(): void
    {
        $counters = [];
        $this->captureCounters($counters);

        try {
            $this->makeParser()->device($this->deviceJson(['metadata' => '{not valid json']), []);
            self::fail('Expected ParsingException was not thrown.');
        } catch (ParsingException) {
            self::assertContains('api_parse_metadata_error_total', $counters);
        }
    }

    #[Test]
    public function deviceUpdatedAtMirrorsCreatedAt(): void
    {
        // NOTE: pins a suspected bug at ApiParser.php:140 — device() sets updatedAt
        // from $data['createdAt'], so updatedAt always equals createdAt regardless of
        // the API's updatedAt value. Do not "fix" this test; fix the parser instead.
        $device = $this->makeParser()->device($this->deviceJson([
            'createdAt' => '2024-07-01T10:00:00.000000Z',
            'updatedAt' => '2024-07-09T10:00:00.000000Z',
        ]), []);

        self::assertEquals($device->createdAt, $device->updatedAt);
        self::assertSame('2024-07-01 12:00:00', $device->updatedAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function deviceParsesLatestMessageAndRxInfoWithGatewayName(): void
    {
        $gateway = $this->makeParser()->gateway($this->gatewayJson(['gatewayId' => 'gw-001', 'name' => 'Gateway One']));

        $device = $this->makeParser()->device($this->deviceJson([
            'latestReceivedMessage' => [
                'id' => 200,
                'createdAt' => '2024-07-01T09:59:00.000000Z',
                'sentTime' => '2024-07-01T09:58:00.000000Z',
                'rssi' => -95,
                'snr' => 8,
                'rawData' => ['rxInfo' => [[
                    'gatewayId' => 'gw-001',
                    'rssi' => -95,
                    'snr' => 8,
                    'crcStatus' => 'CRC_OK',
                    'location' => ['latitude' => 56.15, 'longitude' => 10.2],
                ]]],
            ],
        ]), [$gateway]);

        self::assertNotNull($device->latestReceivedMessage);
        self::assertSame(200, $device->latestReceivedMessage->id);
        self::assertCount(1, $device->latestReceivedMessage->rxInfo);
        self::assertSame('Gateway One', $device->latestReceivedMessage->rxInfo[0]->gatewayName);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function rxInfoAliasProvider(): array
    {
        return [
            'canonical keys' => [['gatewayId' => 'gw-1', 'snr' => 7]],
            'capitalised gatewayID and loRaSNR' => [['gatewayID' => 'gw-1', 'loRaSNR' => 7]],
        ];
    }

    #[Test]
    #[DataProvider('rxInfoAliasProvider')]
    public function rxInfoToleratesFieldAliases(array $rxInfo): void
    {
        $rxInfo += ['rssi' => -90, 'crcStatus' => 'CRC_OK', 'location' => ['latitude' => 1, 'longitude' => 2]];

        $device = $this->makeParser()->device($this->deviceJson([
            'latestReceivedMessage' => [
                'id' => 1,
                'createdAt' => '2024-07-01T09:59:00.000000Z',
                'sentTime' => '2024-07-01T09:58:00.000000Z',
                'rawData' => ['rxInfo' => [$rxInfo]],
            ],
        ]), []);

        self::assertNotNull($device->latestReceivedMessage);
        $received = $device->latestReceivedMessage->rxInfo[0];
        self::assertSame('gw-1', $received->gatewayId);
        self::assertSame(7, $received->snr);
        self::assertSame('Name not found', $received->gatewayName);
    }

    #[Test]
    public function gatewayIsParsedFromWrappedPayload(): void
    {
        $gateway = $this->makeParser()->gateway($this->gatewayJson());

        self::assertInstanceOf(Gateway::class, $gateway);
        self::assertSame('gw-eui-001', $gateway->gatewayId);
        self::assertSame('jane@example.test', $gateway->responsibleEmail);
        self::assertSame(Status::IN_OPERATION, $gateway->status);
        self::assertSame(['env' => 'prod'], $gateway->tags);
    }

    #[Test]
    public function gatewaysAreParsedFromResultListAndFiltered(): void
    {
        $gaugeValues = [];
        $this->metrics->method('gauge')->willReturnCallback(
            function (string $name, string $help, int $value) use (&$gaugeValues): void {
                $gaugeValues[$name] = $value;
            }
        );

        $json = $this->gatewaysJson([
            $this->gatewayData(['id' => 1, 'status' => 'IN-OPERATION']),
            $this->gatewayData(['id' => 2, 'status' => 'OTHER']),
        ]);

        $result = $this->makeParser()->gateways($json, true);

        self::assertCount(1, $result);
        self::assertContainsOnlyInstancesOf(Gateway::class, $result);
        self::assertSame(1, $gaugeValues['api_parsed_gateways']);
    }
}
