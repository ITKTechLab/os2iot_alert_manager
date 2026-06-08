<?php

namespace App\Tests\Service;

use App\Model\Application;
use App\Model\DataTypes\Location;
use App\Model\DataTypes\Message;
use App\Model\DataTypes\Status;
use App\Model\Device;
use App\Model\Gateway;
use App\Service\AlertManager;
use App\Service\ApiClientInterface;
use App\Service\MailServiceInterface;
use App\Service\SmsClientInterface;
use App\Service\TemplateService;
use ItkDev\MetricsBundle\Service\MetricsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Twig\Environment;

#[CoversClass(AlertManager::class)]
#[UsesClass(Application::class)]
#[UsesClass(Device::class)]
#[UsesClass(Gateway::class)]
#[UsesClass(Location::class)]
#[UsesClass(Message::class)]
#[UsesClass(Status::class)]
#[UsesClass(TemplateService::class)]
final class AlertManagerTest extends TestCase
{
    private ApiClientInterface&MockObject $apiClient;
    private MailServiceInterface&MockObject $mail;
    private SmsClientInterface&MockObject $sms;
    private MetricsService&MockObject $metrics;
    private LoggerInterface&MockObject $logger;
    private TemplateService $templateService;

    /** @var list<string> */
    private array $mailRecipients = [];

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClientInterface::class);
        $this->mail = $this->createMock(MailServiceInterface::class);
        $this->sms = $this->createMock(SmsClientInterface::class);
        $this->metrics = $this->createMock(MetricsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('rendered message');
        $this->templateService = new TemplateService($twig);

        $this->mailRecipients = [];
    }

    /**
     * Record the recipient passed to MailService::sendEmail (ignores the other args).
     */
    private function captureMailRecipients(): void
    {
        $this->mail->method('sendEmail')->willReturnCallback(
            function (string $to): void {
                $this->mailRecipients[] = $to;
            }
        );
    }

    /**
     * @param array<string, mixed> $overrides config keyed by constructor parameter name
     */
    private function makeManager(array $overrides = []): AlertManager
    {
        $cfg = array_merge([
            'applicationCheckStartDate' => true,
            'applicationCheckEndDate' => true,
            'applicationBaseUrl' => 'https://example.test/applications/%d',
            'gatewayLimit' => 3600,
            'gatewayFallbackMail' => 'fallback-gw@example.test',
            'gatewayFallbackPhone' => '+4511111111',
            'gatewayBaseUrl' => 'https://example.test/gateways/',
            'deviceFallbackLimit' => 86400,
            'deviceFallbackMail' => 'fallback-dev@example.test',
            'deviceFallbackPhone' => '+4522222222',
            'deviceMetadataFieldLimit' => 'notification_threshold',
            'deviceMetadataFieldMail' => 'email',
            'deviceMetadataFieldPhone' => 'phone',
            'deviceBaseUrl' => 'https://example.test/applications/%d/iot-device/%d/details',
            'gatewaySilencedTag' => 'silenced_until',
            'deviceMetadataFieldSilenced' => 'silenced_until',
            'silencedTimezone' => 'Europe/Copenhagen',
            'silencedTimeFormat' => 'd-m-y\TH:i:s',
        ], $overrides);

        return new AlertManager(
            $this->apiClient,
            $this->sms,
            $this->mail,
            $this->metrics,
            $this->templateService,
            $this->logger,
            applicationCheckStartDate: $cfg['applicationCheckStartDate'],
            applicationCheckEndDate: $cfg['applicationCheckEndDate'],
            applicationBaseUrl: $cfg['applicationBaseUrl'],
            gatewayLimit: $cfg['gatewayLimit'],
            gatewayFallbackMail: $cfg['gatewayFallbackMail'],
            gatewayFallbackPhone: $cfg['gatewayFallbackPhone'],
            gatewayBaseUrl: $cfg['gatewayBaseUrl'],
            deviceFallbackLimit: $cfg['deviceFallbackLimit'],
            deviceFallbackMail: $cfg['deviceFallbackMail'],
            deviceFallbackPhone: $cfg['deviceFallbackPhone'],
            deviceMetadataFieldLimit: $cfg['deviceMetadataFieldLimit'],
            deviceMetadataFieldMail: $cfg['deviceMetadataFieldMail'],
            deviceMetadataFieldPhone: $cfg['deviceMetadataFieldPhone'],
            deviceBaseUrl: $cfg['deviceBaseUrl'],
            gatewaySilencedTag: $cfg['gatewaySilencedTag'],
            deviceMetadataFieldSilenced: $cfg['deviceMetadataFieldSilenced'],
            silencedTimezone: $cfg['silencedTimezone'],
            silencedTimeFormat: $cfg['silencedTimeFormat'],
        );
    }

    /**
     * @param array<string, mixed> $o
     */
    private function gateway(array $o = []): Gateway
    {
        return new Gateway(
            id: $o['id'] ?? 1,
            gatewayId: $o['gatewayId'] ?? 'gw-1',
            createdAt: $o['createdAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            updatedAt: $o['updatedAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            lastSeenAt: $o['lastSeenAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            name: $o['name'] ?? 'GW',
            description: $o['description'] ?? null,
            location: $o['location'] ?? new Location('0', '0'),
            status: $o['status'] ?? Status::IN_OPERATION,
            responsibleName: $o['responsibleName'] ?? null,
            responsibleEmail: $o['responsibleEmail'] ?? null,
            responsiblePhone: $o['responsiblePhone'] ?? null,
            tags: $o['tags'] ?? [],
        );
    }

    /**
     * @param array<string, mixed> $o
     */
    private function device(array $o = []): Device
    {
        return new Device(
            id: $o['id'] ?? 1,
            applicationId: $o['applicationId'] ?? 10,
            createdAt: $o['createdAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            updatedAt: $o['updatedAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            name: $o['name'] ?? 'Device',
            eui: $o['eui'] ?? 'EUI-1',
            location: $o['location'] ?? new Location('0', '0'),
            latestReceivedMessage: array_key_exists('latestReceivedMessage', $o) ? $o['latestReceivedMessage'] : $this->message(),
            statusBattery: $o['statusBattery'] ?? 100.0,
            metadata: $o['metadata'] ?? [],
        );
    }

    private function message(?\DateTimeImmutable $sentTime = null): Message
    {
        $time = $sentTime ?? new \DateTimeImmutable('2024-01-01 00:00:00');

        return new Message(id: 1, createdAt: $time, sentTime: $time, rssi: 0, snr: 0, rxInfo: []);
    }

    /**
     * @param array<string, mixed> $o
     */
    private function application(array $o = []): Application
    {
        return new Application(
            id: $o['id'] ?? 1,
            createdAt: $o['createdAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            updatedAt: $o['updatedAt'] ?? new \DateTimeImmutable('2024-01-01 00:00:00'),
            startDate: array_key_exists('startDate', $o) ? $o['startDate'] : null,
            endDate: array_key_exists('endDate', $o) ? $o['endDate'] : null,
            name: $o['name'] ?? 'App',
            status: $o['status'] ?? Status::IN_OPERATION,
            contactPerson: $o['contactPerson'] ?? null,
            contactEmail: $o['contactEmail'] ?? null,
            contactPhone: $o['contactPhone'] ?? null,
            devices: $o['devices'] ?? [],
        );
    }

    // --- Phone fallback order (public helpers, no collaborators touched) ---

    /**
     * @return array<string, array{0: string, 1: string|null, 2: string}>
     */
    public static function gatewayPhoneProvider(): array
    {
        return [
            'override wins' => ['+4599999999', '+4512345678', '+4599999999'],
            'responsible phone when no override' => ['', '+4512345678', '+4512345678'],
            'fallback when responsible empty' => ['', '', '+4511111111'],
            'fallback when responsible null' => ['', null, '+4511111111'],
        ];
    }

    #[Test]
    #[DataProvider('gatewayPhoneProvider')]
    public function findGatewayPhoneResolvesInOrder(string $override, ?string $responsible, string $expected): void
    {
        $manager = $this->makeManager();
        $gateway = $this->gateway(['responsiblePhone' => $responsible]);

        self::assertSame($expected, $manager->findGatewayPhone($gateway, $override));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: string}>
     */
    public static function devicePhoneProvider(): array
    {
        return [
            'override wins' => ['+4599999999', '+4512345678', '+4500000000', '+4599999999'],
            'metadata phone when no override' => ['', '+4512345678', '+4500000000', '+4512345678'],
            'application phone when metadata empty' => ['', '', '+4500000000', '+4500000000'],
            'device fallback when metadata empty and app phone null' => ['', '', null, '+4522222222'],
        ];
    }

    #[Test]
    #[DataProvider('devicePhoneProvider')]
    public function findDevicePhoneResolvesInOrder(string $override, string $metaPhone, ?string $appPhone, string $expected): void
    {
        $manager = $this->makeManager();
        $device = $this->device(['metadata' => ['phone' => $metaPhone]]);
        $application = $this->application(['contactPhone' => $appPhone]);

        self::assertSame($expected, $manager->findDevicePhone($device, $application, $override));
    }

    // --- Mail recipient resolution (behavioral, mail channel only) ---

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function gatewayMailProvider(): array
    {
        return [
            'override wins' => ['admin@override.test', 'resp@gw.test', 'admin@override.test'],
            'responsible email' => ['', 'resp@gw.test', 'resp@gw.test'],
            'first of comma separated list, trimmed' => ['', 'first@gw.test, second@gw.test', 'first@gw.test'],
            'fallback when empty' => ['', '', 'fallback-gw@example.test'],
        ];
    }

    #[Test]
    #[DataProvider('gatewayMailProvider')]
    public function gatewayMailRecipientResolution(string $override, string $responsibleEmail, string $expected): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway([
            'responsibleEmail' => $responsibleEmail,
            'lastSeenAt' => $now->modify('-2 hours'),
        ]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->captureMailRecipients();

        $this->makeManager()->checkGateways($now, filterOnStatus: false, overrideMail: $override, noSms: true);

        self::assertSame([$expected], $this->mailRecipients);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: string}>
     */
    public static function deviceMailProvider(): array
    {
        return [
            'override wins' => ['admin@override.test', 'meta@dev.test', 'app@dev.test', 'admin@override.test'],
            'metadata email' => ['', 'meta@dev.test', 'app@dev.test', 'meta@dev.test'],
            'first of comma separated list, trimmed' => ['', 'one@dev.test, two@dev.test', 'app@dev.test', 'one@dev.test'],
            'application email when metadata empty' => ['', '', 'app@dev.test', 'app@dev.test'],
            'device fallback when metadata and app empty' => ['', '', null, 'fallback-dev@example.test'],
        ];
    }

    #[Test]
    #[DataProvider('deviceMailProvider')]
    public function deviceMailRecipientResolution(string $override, string $metaEmail, ?string $appEmail, string $expected): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $device = $this->device([
            'metadata' => ['email' => $metaEmail, 'notification_threshold' => 60],
            'latestReceivedMessage' => $this->message($now->modify('-5 minutes')),
        ]);
        $application = $this->application(['contactEmail' => $appEmail]);
        $this->apiClient->method('getDevice')->willReturn($device);
        $this->captureMailRecipients();

        $this->makeManager()->checkDevice($now, 1, $application, overrideMail: $override, noSms: true);

        self::assertSame([$expected], $this->mailRecipients);
    }

    // --- Offline threshold ---

    #[Test]
    public function gatewayOverThresholdTriggersAlert(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway(['responsibleEmail' => 'r@gw.test', 'lastSeenAt' => $now->modify('-2 hours')]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->mail->expects(self::once())->method('sendEmail');

        $this->makeManager()->checkGateways($now, filterOnStatus: false, noSms: true);
    }

    #[Test]
    public function gatewayWithinThresholdDoesNothing(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway(['lastSeenAt' => $now->modify('-10 minutes')]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->mail->expects(self::never())->method('sendEmail');
        $this->sms->expects(self::never())->method('send');

        $this->makeManager()->checkGateways($now, filterOnStatus: false);
    }

    #[Test]
    public function deviceMetadataThresholdOverridesFallbackLimit(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        // Fallback limit is a full day; metadata sets a 60s threshold, so 5 min triggers an alert.
        $device = $this->device([
            'metadata' => ['notification_threshold' => 60, 'email' => 'd@dev.test'],
            'latestReceivedMessage' => $this->message($now->modify('-5 minutes')),
        ]);
        $this->apiClient->method('getDevice')->with(5)->willReturn($device);
        $this->mail->expects(self::once())->method('sendEmail');

        $this->makeManager()->checkDevice($now, 5, noSms: true);
    }

    #[Test]
    public function deviceWithinFallbackLimitDoesNothing(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        // No metadata threshold -> falls back to 86400s; one hour stale stays silent.
        $device = $this->device([
            'metadata' => ['email' => 'd@dev.test'],
            'latestReceivedMessage' => $this->message($now->modify('-1 hour')),
        ]);
        $this->apiClient->method('getDevice')->willReturn($device);
        $this->mail->expects(self::never())->method('sendEmail');

        $this->makeManager()->checkDevice($now, 1);
    }

    #[Test]
    public function deviceWithoutMessageDoesNothingAndCountsMetric(): void
    {
        $counters = [];
        $this->metrics->method('counter')->willReturnCallback(
            function (string $name) use (&$counters): void {
                $counters[] = $name;
            }
        );
        $this->apiClient->method('getDevice')->willReturn($this->device(['latestReceivedMessage' => null]));
        $this->mail->expects(self::never())->method('sendEmail');
        $this->sms->expects(self::never())->method('send');

        $this->makeManager()->checkDevice(new \DateTimeImmutable('2024-06-01 12:00:00'), 1);

        self::assertContains('alert_message_missing_total', $counters);
    }

    // --- Silencing ---

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function gatewaySilenceProvider(): array
    {
        return [
            // Format is d-m-y (2-digit year): 99 -> 1999 (past), 49 -> 2049 (future).
            'past silenced_until is not silenced' => ['01-01-99T00:00:00', true],
            'future silenced_until is silenced' => ['01-01-49T00:00:00', false],
        ];
    }

    #[Test]
    #[DataProvider('gatewaySilenceProvider')]
    public function gatewaySilencingControlsAlert(string $silencedUntil, bool $expectAlert): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway([
            'responsibleEmail' => 'r@gw.test',
            'lastSeenAt' => $now->modify('-2 hours'),
            'tags' => ['silenced_until' => $silencedUntil],
        ]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->mail->expects($expectAlert ? self::once() : self::never())->method('sendEmail');

        $this->makeManager()->checkGateways($now, filterOnStatus: false, noSms: true);
    }

    #[Test]
    public function gatewayUnparseableSilenceDateStillAlertsAndLogs(): void
    {
        // An unparseable silenced_until fails open: the parse error is logged and
        // counted, but the gateway is treated as not silenced so the alert still fires.
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway([
            'responsibleEmail' => 'r@gw.test',
            'lastSeenAt' => $now->modify('-2 hours'),
            'tags' => ['silenced_until' => 'not-a-valid-date'],
        ]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $counters = [];
        $this->metrics->method('counter')->willReturnCallback(
            function (string $name) use (&$counters): void {
                $counters[] = $name;
            }
        );
        $this->logger->expects(self::once())->method('error');
        $this->mail->expects(self::once())->method('sendEmail');

        $this->makeManager()->checkGateways($now, filterOnStatus: false, noSms: true);

        self::assertContains('alert_silenced_parse_date_error_total', $counters);
    }

    #[Test]
    public function deviceUnparseableSilenceDateStillAlertsAndLogs(): void
    {
        // Same fail-open behaviour on the device silencing path (isDeviceSilenced).
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $device = $this->device([
            'metadata' => [
                'email' => 'd@dev.test',
                'notification_threshold' => 60,
                'silenced_until' => 'not-a-valid-date',
            ],
            'latestReceivedMessage' => $this->message($now->modify('-5 minutes')),
        ]);
        $this->apiClient->method('getDevice')->willReturn($device);
        $counters = [];
        $this->metrics->method('counter')->willReturnCallback(
            function (string $name) use (&$counters): void {
                $counters[] = $name;
            }
        );
        $this->logger->expects(self::once())->method('error');
        $this->mail->expects(self::once())->method('sendEmail');

        $this->makeManager()->checkDevice($now, 1, noSms: true);

        self::assertContains('alert_silenced_parse_date_error_total', $counters);
    }

    // --- Dispatch flags ---

    #[Test]
    public function alertSendsBothMailAndSms(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway([
            'responsibleEmail' => 'r@gw.test',
            'responsiblePhone' => '+4512345678',
            'lastSeenAt' => $now->modify('-2 hours'),
        ]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->mail->expects(self::once())->method('sendEmail');
        $this->sms->expects(self::once())->method('send')->willReturn(1);

        $this->makeManager()->checkGateways($now, filterOnStatus: false);
    }

    #[Test]
    public function noMailFlagSuppressesEmail(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway(['responsiblePhone' => '+4512345678', 'lastSeenAt' => $now->modify('-2 hours')]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->mail->expects(self::never())->method('sendEmail');
        $this->sms->expects(self::once())->method('send')->willReturn(1);

        $this->makeManager()->checkGateways($now, filterOnStatus: false, noMail: true);
    }

    #[Test]
    public function noSmsFlagSuppressesSms(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $gateway = $this->gateway(['responsibleEmail' => 'r@gw.test', 'lastSeenAt' => $now->modify('-2 hours')]);
        $this->apiClient->method('getGateways')->willReturn([$gateway]);
        $this->mail->expects(self::once())->method('sendEmail');
        $this->sms->expects(self::never())->method('send');

        $this->makeManager()->checkGateways($now, filterOnStatus: false, noSms: true);
    }

    // --- Application start/end date skipping ---

    #[Test]
    public function applicationWithFutureStartDateIsSkipped(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $app = $this->application(['startDate' => new \DateTimeImmutable('2099-01-01'), 'devices' => [1]]);
        $this->apiClient->method('getApplications')->willReturn([$app]);
        $this->apiClient->expects(self::never())->method('getDevice');

        $this->makeManager()->checkApplications($now, filterOnStatus: false);
    }

    #[Test]
    public function applicationWithPastEndDateIsSkipped(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $app = $this->application(['endDate' => new \DateTimeImmutable('1999-01-01'), 'devices' => [1]]);
        $this->apiClient->method('getApplications')->willReturn([$app]);
        $this->apiClient->expects(self::never())->method('getDevice');

        $this->makeManager()->checkApplications($now, filterOnStatus: false);
    }

    #[Test]
    public function applicationWithinDatesIsChecked(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $app = $this->application([
            'startDate' => new \DateTimeImmutable('2020-01-01'),
            'endDate' => new \DateTimeImmutable('2099-01-01'),
            'devices' => [7],
        ]);
        $this->apiClient->method('getApplications')->willReturn([$app]);
        // The device has no message, so checkDevice returns early — but getDevice being
        // called at all proves the application was not skipped.
        $this->apiClient->expects(self::once())->method('getDevice')->with(7)
            ->willReturn($this->device(['latestReceivedMessage' => null]));

        $this->makeManager()->checkApplications($now, filterOnStatus: false);
    }

    #[Test]
    public function startEndDateSkippingDisabledByConfigFlags(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $app = $this->application(['startDate' => new \DateTimeImmutable('2099-01-01'), 'devices' => [7]]);
        $this->apiClient->method('getApplications')->willReturn([$app]);
        $this->apiClient->expects(self::once())->method('getDevice')
            ->willReturn($this->device(['latestReceivedMessage' => null]));

        $manager = $this->makeManager(['applicationCheckStartDate' => false, 'applicationCheckEndDate' => false]);
        $manager->checkApplications($now, filterOnStatus: false);
    }

    // --- Pure helper ---

    #[Test]
    public function timeDiffInSecondsReturnsSignedDifference(): void
    {
        $manager = $this->makeManager();
        $method = new \ReflectionMethod($manager, 'timeDiffInSeconds');
        $earlier = new \DateTimeImmutable('2024-06-01 12:00:00');
        $later = new \DateTimeImmutable('2024-06-01 13:00:00');

        self::assertSame(3600, $method->invoke($manager, $earlier, $later));
        self::assertSame(-3600, $method->invoke($manager, $later, $earlier));
    }
}
