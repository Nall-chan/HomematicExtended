<?php

declare(strict_types=1);

include_once __DIR__ . '/HeatingTestCase.php';
require_once __DIR__ . '/../ClimacontrolRegulator/module.php';

/**
 * Testklasse: Die Stubs können keine Ereignisse (Wochenplan) anlegen, der Wochenplan wird daher abgeschaltet.
 */
class TestClimateControlRegulator extends HomeMaticClimateControlRegulator
{
    /**
     * Zugang zur Umrechnung des Wochenplans (die Stubs können kein Wochenplan-Ereignis anlegen).
     */
    public static function ScheduleParams(array $ScheduleGroups, array $ActionIdToTemp, int $Plan): array|false
    {
        return static::ScheduleToParams($ScheduleGroups, $ActionIdToTemp, $Plan);
    }

    protected function ReadPropertyBoolean(string $Name): bool
    {
        return ($Name == \HMExtended\Device\Property::Schedule) ? false : parent::ReadPropertyBoolean($Name);
    }

}

class ClimacontrolRegulatorTest extends HeatingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses = [
            // Die Fake-CCU antwortet je Paramset, Kanal :1 (TEMPERATURE, HUMIDITY) und :2 (SETPOINT) erhalten dieselbe Antwort.
            'getParamsetDescription:VALUES' => $this->description(['SETPOINT' => 'FLOAT', 'TEMPERATURE' => 'FLOAT', 'HUMIDITY' => 'INTEGER']),
            'getParamset:VALUES'            => ['SETPOINT' => 21.0, 'TEMPERATURE' => 22.4, 'HUMIDITY' => 55],
            'getParamsetDescription:MASTER' => $this->description([
                'MODE_TEMPERATUR_REGULATOR' => 'INTEGER',
                'DECALCIFICATION_DAY'       => 'INTEGER',
                'DECALCIFICATION_HOUR'      => 'INTEGER',
                'PARTY_END_DAY'             => 'FLOAT',
                'TEMPERATUR_PARTY_VALUE'    => 'FLOAT'
            ]),
            'getParamset:MASTER' => array_merge([
                'MODE_TEMPERATUR_REGULATOR' => 1,
                'DECALCIFICATION_DAY'       => 6,
                'DECALCIFICATION_HOUR'      => 22,
                'DECALCIFICATION_MINUTE'    => 30,
                'PARTY_END_DAY'             => 3.0,
                'PARTY_END_HOUR'            => 18,
                'PARTY_END_MINUTE'          => 1,
                'TEMPERATUR_PARTY_VALUE'    => 17.0
            ], $this->schedule('TIMEOUT_%2$s_%1$d', 'TEMPERATUR_%2$s_%1$d', 1, 24))
        ];
        $this->DeviceId = $this->createDevice(TestClimateControlRegulator::class, \HMExtended\GUID::ClimacontrolRegulator, 'HMCC', $this->IoId);
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::Address, 'IEQ0000001');
        IPS_SetProperty($this->DeviceId, 'enable_DECALCIFICATION_TIME', true);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
    }

    /**
     * Wochenplan laut CCU-Firmware (rf_cc_tc.xml): TIMEOUT_<TAG>_1..24 im 10-Minuten-Raster (10..1440), TEMPERATUR_<TAG>_1..24.
     */
    public function testScheduleIsConvertedToTimeoutsInTenMinuteSteps(): void
    {
        $Result = TestClimateControlRegulator::ScheduleParams([
            ['Days' => 32, 'Points' => [
                ['Start' => ['Hour' => 0, 'Minute' => 0], 'ActionID' => 1],
                ['Start' => ['Hour' => 6, 'Minute' => 7], 'ActionID' => 2],
                ['Start' => ['Hour' => 22, 'Minute' => 25], 'ActionID' => 1]
            ]]
        ], [1 => 17.0, 2 => 21.5], 1);
        $this->assertIsArray($Result);
        [$Params, $Schedule] = $Result;
        $this->assertSame([
            'TIMEOUT_SATURDAY_1'    => 370,
            'TEMPERATUR_SATURDAY_1' => 17.0,
            'TIMEOUT_SATURDAY_2'    => 1350,
            'TEMPERATUR_SATURDAY_2' => 21.5,
            'TIMEOUT_SATURDAY_3'    => 1440,
            'TEMPERATUR_SATURDAY_3' => 17.0
        ], $Params);
        // Das gespeicherte Zeitprofil enthält die gerundeten Zeiten, der Wochenplan zeigt sie nach dem Speichern an.
        $this->assertSame(['Time' => 370, 'Temp' => 17.0], $Schedule[32][1]);
    }

    /**
     * Kanal :1 (WEATHER, rf_cc_tc.xml) liefert Ist-Temperatur und Luftfeuchte.
     */
    public function testWeatherChannelIsCreatedAndRead(): void
    {
        IPS_ApplyChanges($this->DeviceId);
        $Addresses = array_map(fn ($Request) => $Request['Data'][0], $this->requests('getParamset', 'VALUES'));
        $this->assertSame(['IEQ0000001:2', 'IEQ0000001:1'], $Addresses);
        $this->assertSame(22.4, GetValueFloat($this->variable('TEMPERATURE')));
        $this->assertSame(55, GetValueInteger($this->variable('HUMIDITY')));
        $this->assertFalse(HasAction($this->variable('TEMPERATURE')));
    }

    public function testReceiveFilterMatchesBothChannels(): void
    {
        $Filter = '/' . $this->device()->GetReceiveDataFilter() . '/';
        $this->assertSame(1, preg_match($Filter, '{"DeviceID":"IEQ0000001:1","VariableName":"TEMPERATURE"}'));
        $this->assertSame(1, preg_match($Filter, '{"DeviceID":"IEQ0000001:2","VariableName":"SETPOINT"}'));
        $this->assertSame(0, preg_match($Filter, '{"DeviceID":"IEQ0000001:3","VariableName":"STATE"}'));
        $this->assertSame(0, preg_match($Filter, '{"DeviceID":"IEQ0000001:12","VariableName":"STATE"}'));
    }

    public function testWeatherEventSetsVariable(): void
    {
        $this->device()->ReceiveData(json_encode(['DeviceID' => 'IEQ0000001:1', 'VariableName' => 'TEMPERATURE', 'VariableValue' => 19.8]));
        $this->assertSame(19.8, GetValueFloat($this->variable('TEMPERATURE')));
    }

    public function testRequestStateReadsWeatherDatapointFromChannelOne(): void
    {
        FakeHomematicIO::$RpcResponses['getValue'] = 23.1;
        $this->assertTrue($this->device()->RequestState('TEMPERATURE'));
        $this->assertSame(['IEQ0000001:1', 'TEMPERATURE'], $this->requests('getValue')[0]['Data']);
        $this->assertTrue($this->device()->RequestState('SETPOINT'));
        $this->assertSame(['IEQ0000001:2', 'SETPOINT'], $this->requests('getValue')[1]['Data']);
    }

    public function testScheduleAcceptsTwentyFourPointsPerDay(): void
    {
        $Points = [];
        for ($i = 0; $i < 24; $i++) {
            $Points[] = ['Start' => ['Hour' => $i, 'Minute' => 0], 'ActionID' => 1];
        }
        $this->assertIsArray(TestClimateControlRegulator::ScheduleParams([['Days' => 1, 'Points' => $Points]], [1 => 20.0], 1));
        $Points[] = ['Start' => ['Hour' => 23, 'Minute' => 30], 'ActionID' => 1];
        $this->assertFalse(TestClimateControlRegulator::ScheduleParams([['Days' => 1, 'Points' => $Points]], [1 => 20.0], 1));
    }

    public function testMissingScheduleParamsCauseNoWarning(): void
    {
        // Antwort der CCU ohne die Schaltpunkte 14..24: kein PHP-Fehler beim Einlesen.
        $Master = FakeHomematicIO::$RpcResponses['getParamset:MASTER'];
        foreach (array_keys($Master) as $Key) {
            if (preg_match('/^(TIMEOUT|TEMPERATUR)_[A-Z]+_(1[4-9]|2[0-4])$/', $Key)) {
                unset($Master[$Key]);
            }
        }
        FakeHomematicIO::$RpcResponses['getParamset:MASTER'] = $Master;
        IPS_ApplyChanges($this->DeviceId);
        $this->assertSame('22:30', date('H:i', GetValueInteger($this->variable('DECALCIFICATION_TIME'))));
    }

    public function testDecalcificationTimeIsComposedFromHourAndMinute(): void
    {
        $this->assertSame('22:30', date('H:i', GetValueInteger($this->variable('DECALCIFICATION_TIME'))));
    }

    public function testPartyEndIsComposedFromDaysHourAndMinute(): void
    {
        $Expected = (new DateTime())->setTime(18, 30, 0, 0)->add(new DateInterval('P3D'));
        $this->assertSame($Expected->getTimestamp(), GetValueInteger($this->variable('PARTY_END_TIME')));
    }

    /**
     * @return array<string, array{int, int}> Minute der Eingabe, erwartete Minute (Raster 10 Minuten bis maximal 50).
     */
    public static function decalcificationMinuteProvider(): array
    {
        return [
            '03:20' => [20, 20],
            '03:50' => [50, 50],
            '03:55' => [55, 50],
            '03:00' => [0, 0]
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('decalcificationMinuteProvider')]
    public function testDecalcificationTimeIsSentAsHourAndMinute(int $Minute, int $ExpectedMinute): void
    {
        $this->assertSame('', $this->action('DECALCIFICATION_TIME', mktime(3, $Minute, 0, 1, 1, 2026)));
        $this->assertSame(['DECALCIFICATION_MINUTE' => $ExpectedMinute, 'DECALCIFICATION_HOUR' => 3], $this->lastPut('MASTER'));
    }

    /**
     * @return array<string, array{int, int, int}> Minute der Eingabe, erwartete Minute, erwarteter Wert PARTY_END_MINUTE.
     */
    public static function partyMinuteProvider(): array
    {
        return [
            '18:00' => [0, 0, 0],
            '18:29' => [29, 0, 0],
            '18:30' => [30, 30, 1],
            '18:59' => [59, 30, 1]
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('partyMinuteProvider')]
    public function testPartyEndIsSentInHalfHourSteps(int $Minute, int $ExpectedMinute, int $ExpectedFlag): void
    {
        $Target = (new DateTime())->setTime(18, $Minute, 0, 0)->add(new DateInterval('P2D'));
        $this->assertSame('', $this->action('PARTY_END_TIME', $Target->getTimestamp()));
        $Put = $this->lastPut('MASTER');
        $this->assertSame(3, $Put['MODE_TEMPERATUR_REGULATOR']);
        // PARTY_END_DAY ist auf der CCU ein FLOAT.
        $this->assertSame(2.0, $Put['PARTY_END_DAY']);
        $this->assertSame($ExpectedFlag, $Put['PARTY_END_MINUTE']);
        $this->assertSame(18, $Put['PARTY_END_HOUR']);
        $this->assertSame(3, GetValueInteger($this->variable('MODE_TEMPERATUR_REGULATOR')));
        $Expected = (new DateTime())->setTime(18, $ExpectedMinute, 0, 0)->add(new DateInterval('P2D'));
        $this->assertSame($Expected->getTimestamp(), GetValueInteger($this->variable('PARTY_END_TIME')));
    }

    public function testDisabledPartyRemovesVariablesAndBlocksActions(): void
    {
        IPS_SetProperty($this->DeviceId, 'enable_PARTY_END_TIME', false);
        IPS_SetProperty($this->DeviceId, 'enable_TEMPERATUR_PARTY_VALUE', false);
        IPS_ApplyChanges($this->DeviceId);
        $this->assertFalse(@IPS_GetObjectIDByIdent('PARTY_END_TIME', $this->DeviceId));
        $this->assertFalse(@IPS_GetObjectIDByIdent('TEMPERATUR_PARTY_VALUE', $this->DeviceId));
        FakeHomematicIO::$Requests = [];
        $this->assertStringContainsString('Variable is disabled in config.', $this->action('TEMPERATUR_PARTY_VALUE', 18.0));
        $this->assertCount(0, $this->requests('putParamset'));
    }

    public function testPartyEndInThePastIsRejected(): void
    {
        $this->assertStringContainsString('Time cannot be in the past', $this->action('PARTY_END_TIME', time() - 3600));
        $this->assertCount(0, $this->requests('putParamset'));
    }

    public function testPartyEndTooFarInTheFutureIsRejected(): void
    {
        $this->assertStringContainsString('Time too far in the future', $this->action('PARTY_END_TIME', time() + 201 * 86400));
        $this->assertCount(0, $this->requests('putParamset'));
    }

    public function testSetpointSwitchesToManualModeFirst(): void
    {
        $this->action('SETPOINT', 22.5);
        $this->assertSame(['MODE_TEMPERATUR_REGULATOR' => 0], $this->lastPut('MASTER'));
        $Requests = $this->requests('setValue');
        $this->assertSame('SETPOINT', end($Requests)['Data'][1]);
        $this->assertEquals(22.5, end($Requests)['Data'][2]);
        $this->assertSame(22.5, GetValueFloat($this->variable('SETPOINT')));
    }

    public function testSetpointKeepsModeWhenBehaviorIsDisabled(): void
    {
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::SetPointBehavior, false);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
        $this->action('SETPOINT', 22.5);
        $this->assertCount(0, $this->requests('putParamset'));
        $this->assertCount(1, $this->requests('setValue'));
    }

    public function testInvalidIdentIsReportedToUser(): void
    {
        $this->assertStringContainsString('Invalid Ident.', $this->action('UNKNOWN', 1));
        $this->assertCount(0, $this->requests('putParamset'));
    }
}
