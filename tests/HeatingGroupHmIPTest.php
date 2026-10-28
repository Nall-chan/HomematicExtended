<?php

declare(strict_types=1);

include_once __DIR__ . '/HeatingTestCase.php';
require_once __DIR__ . '/../HeatingGroupHmIP/module.php';

/**
 * Testklasse: Die Stubs können keine Ereignisse (Wochenplan) anlegen, der Wochenplan wird daher abgeschaltet.
 */
class TestHeatingGroupHmIP extends HomeMaticIPHeatingGroup
{
    protected function ReadPropertyBoolean(string $Name): bool
    {
        return ($Name == \HMExtended\Device\Property::Schedule) ? false : parent::ReadPropertyBoolean($Name);
    }

}

class HeatingGroupHmIPTest extends HeatingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription:VALUES' => $this->description([
                'ACTIVE_PROFILE'              => 'INTEGER',
                'SET_POINT_MODE'              => 'INTEGER',
                'CONTROL_MODE'                => 'INTEGER',
                'SET_POINT_TEMPERATURE'       => 'FLOAT',
                'PARTY_SET_POINT_TEMPERATURE' => 'FLOAT',
                'PARTY_TIME_START'            => 'STRING',
                'PARTY_TIME_END'              => 'STRING'
            ]),
            'getParamset:VALUES' => [
                'ACTIVE_PROFILE'              => 1,
                'SET_POINT_MODE'              => 0,
                'CONTROL_MODE'                => 0,
                'SET_POINT_TEMPERATURE'       => 21.0,
                'PARTY_SET_POINT_TEMPERATURE' => 17.0,
                'PARTY_TIME_START'            => '2026_03_05 09:30',
                'PARTY_TIME_END'              => '2000_01_01 00:00'
            ],
            'getParamsetDescription:MASTER' => $this->description([
                'DECALCIFICATION_TIME'    => 'INTEGER',
                'DECALCIFICATION_WEEKDAY' => 'INTEGER'
            ]),
            'getParamset:MASTER' => array_merge([
                // Halbe Stunden seit 00:00: 5 = 02:30
                'DECALCIFICATION_TIME'    => 5,
                'DECALCIFICATION_WEEKDAY' => 6
            ], $this->schedule('P%3$d_ENDTIME_%2$s_%1$d', 'P%3$d_TEMPERATURE_%2$s_%1$d', 6, 13))
        ];
        $this->DeviceId = $this->createDevice(TestHeatingGroupHmIP::class, \HMExtended\GUID::HeatingGroupHmIP, 'HMHI', $this->IoId);
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::Address, '3014F711A0001F5A4993F31C');
        IPS_SetProperty($this->DeviceId, 'enable_DECALCIFICATION_TIME', true);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
    }

    public function testPartyStartIsComposedFromDateString(): void
    {
        $this->assertSame(mktime(9, 30, 0, 3, 5, 2026), GetValueInteger($this->variable('PARTY_TIME_START')));
    }

    public function testNeverSetPartyTimeBecomesNow(): void
    {
        // Die CCU liefert den kleinsten Wert des Datenpunktes (2000_01_01 00:00), wenn nie eine Zeit gesetzt wurde.
        $this->assertEqualsWithDelta(time(), GetValueInteger($this->variable('PARTY_TIME_END')), 5);
    }

    public function testNeverSetPartyTimeOfOldFirmwareBecomesNow(): void
    {
        $this->device()->ReceiveData(json_encode(['VariableName' => 'PARTY_TIME_END', 'VariableValue' => '2026_03_05 09:30']));
        $this->assertSame(mktime(9, 30, 0, 3, 5, 2026), GetValueInteger($this->variable('PARTY_TIME_END')));
        $this->device()->ReceiveData(json_encode(['VariableName' => 'PARTY_TIME_END', 'VariableValue' => '1999_11_30 00:00']));
        $this->assertEqualsWithDelta(time(), GetValueInteger($this->variable('PARTY_TIME_END')), 5);
    }

    public function testDecalcificationTimeIsConvertedFromHalfHours(): void
    {
        $this->assertSame('02:30', date('H:i', GetValueInteger($this->variable('DECALCIFICATION_TIME'))));
    }

    /**
     * @return array<string, array{int, int}> Minute der Eingabe, erwarteter Wert in halben Stunden bei 22 Uhr.
     */
    public static function halfHourProvider(): array
    {
        return [
            '22:00' => [0, 44],
            '22:29' => [29, 44],
            '22:30' => [30, 45],
            '22:59' => [59, 45]
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('halfHourProvider')]
    public function testDecalcificationTimeIsSentInHalfHours(int $Minute, int $Expected): void
    {
        $this->assertSame('', $this->action('DECALCIFICATION_TIME', mktime(22, $Minute, 0, 1, 1, 2026)));
        $this->assertSame(['DECALCIFICATION_TIME' => $Expected], $this->lastPut('MASTER'));
    }

    public function testPartyTimeIsSentAsDateString(): void
    {
        $this->assertSame('', $this->action('PARTY_TIME_START', mktime(18, 45, 0, 12, 24, 2026)));
        $Requests = $this->requests('setValue');
        $this->assertCount(1, $Requests);
        $this->assertSame(['3014F711A0001F5A4993F31C:1', 'PARTY_TIME_START', '2026_12_24 18:45'], $Requests[0]['Data']);
    }

    public function testPartyTemperatureSendsTimesFirstWhenModeIsNotParty(): void
    {
        $this->event('PARTY_TIME_START', '2026_03_05 08:00');
        $this->event('PARTY_TIME_END', '2026_03_08 20:00');
        FakeHomematicIO::$Requests = [];
        $this->assertSame('', $this->action('PARTY_SET_POINT_TEMPERATURE', 16.5));
        $this->assertSame(
            ['PARTY_TIME_START' => '2026_03_05 08:00', 'PARTY_TIME_END' => '2026_03_08 20:00'],
            $this->lastPut('VALUES')
        );
        $Requests = $this->requests('setValue');
        $this->assertSame('SET_POINT_TEMPERATURE', $Requests[0]['Data'][1]);
        $this->assertEquals(16.5, $Requests[0]['Data'][2]);
    }

    public function testPartyTemperatureInPartyModeOnlySetsTemperature(): void
    {
        $this->event('SET_POINT_MODE', 2);
        $this->assertSame('', $this->action('PARTY_SET_POINT_TEMPERATURE', 16.5));
        $this->assertCount(0, $this->requests('putParamset'));
        $this->assertSame('SET_POINT_TEMPERATURE', $this->requests('setValue')[0]['Data'][1]);
    }

    public function testPartyModeSendsPartyTimes(): void
    {
        IPS_SetProperty($this->DeviceId, 'enable_PARTY', true);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
        $this->assertSame('', $this->action('CONTROL_MODE', 2));
        $Put = $this->lastPut('VALUES');
        $this->assertArrayHasKey('PARTY_TIME_START', $Put);
        $this->assertArrayHasKey('PARTY_TIME_END', $Put);
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2} \d{2}:\d{2}$/', $Put['PARTY_TIME_START']);
    }

    public function testPartyModeIsRejectedWhenPartyIsDisabled(): void
    {
        IPS_SetProperty($this->DeviceId, 'enable_PARTY', false);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
        $this->assertStringContainsString('Party is disabled in config.', $this->action('CONTROL_MODE', 2));
        $this->assertCount(0, $this->requests('putParamset'));
        $this->assertCount(0, $this->requests('setValue'));
    }

    public function testSetPointSwitchesToManualModeWhenAutomaticIsActive(): void
    {
        $this->assertSame('', $this->action('SET_POINT_TEMPERATURE', 22.5));
        $Requests = $this->requests('setValue');
        $this->assertSame(['CONTROL_MODE', 'SET_POINT_TEMPERATURE'], array_column(array_column($Requests, 'Data'), 1));
    }

    public function testSetPointKeepsModeWhenBehaviorIsDisabled(): void
    {
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::SetPointBehavior, false);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
        $this->action('SET_POINT_TEMPERATURE', 22.5);
        $this->assertSame(['SET_POINT_TEMPERATURE'], array_column(array_column($this->requests('setValue'), 'Data'), 1));
    }

    public function testInvalidIdentIsReportedToUser(): void
    {
        $this->assertStringContainsString('Invalid Ident.', $this->action('UNKNOWN', 1));
    }

    /**
     * Simuliert ein Event der CCU, welches über den Socket an die Instanz geliefert wird.
     */
    private function event(string $Ident, mixed $Value): void
    {
        $this->device()->ReceiveData(json_encode(['VariableName' => $Ident, 'VariableValue' => $Value]));
    }
}
