<?php

declare(strict_types=1);

include_once __DIR__ . '/HeatingTestCase.php';
require_once __DIR__ . '/../ClimacontrolRegulator/module.php';

/**
 * Testklasse: Die Stubs können keine Ereignisse (Wochenplan) anlegen, der Wochenplan wird daher abgeschaltet.
 */
class TestClimateControlRegulator extends HomeMaticClimateControlRegulator
{
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
            'getParamsetDescription:VALUES' => $this->description(['SETPOINT' => 'FLOAT']),
            'getParamset:VALUES'            => ['SETPOINT' => 21.0],
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
