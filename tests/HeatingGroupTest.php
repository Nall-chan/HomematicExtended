<?php

declare(strict_types=1);

include_once __DIR__ . '/HeatingTestCase.php';
require_once __DIR__ . '/../HeatingGroup/module.php';

/**
 * Testklasse: Die Stubs können keine Ereignisse (Wochenplan) anlegen, der Wochenplan wird daher abgeschaltet.
 */
class TestHeatingGroup extends HomeMaticHeatingGroup
{
    protected function ReadPropertyBoolean(string $Name): bool
    {
        return ($Name == \HMExtended\Device\Property::Schedule) ? false : parent::ReadPropertyBoolean($Name);
    }

}

class HeatingGroupTest extends HeatingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription:VALUES' => $this->description([
                'CONTROL_MODE'      => 'INTEGER',
                'SET_TEMPERATURE'   => 'FLOAT',
                'PARTY_TEMPERATURE' => 'FLOAT',
                'PARTY_START_TIME'  => 'INTEGER',
                'PARTY_STOP_TIME'   => 'INTEGER'
            ]),
            'getParamset:VALUES' => [
                'CONTROL_MODE'      => 1,
                'SET_TEMPERATURE'   => 21.0,
                'PARTY_TEMPERATURE' => 17.0,
                // Zeit in Minuten seit 00:00, danach Tag, Monat und Jahr (zweistellig)
                'PARTY_START_TIME'  => 570,
                'PARTY_START_DAY'   => 5,
                'PARTY_START_MONTH' => 3,
                'PARTY_START_YEAR'  => 26,
                'PARTY_STOP_TIME'   => 1140,
                'PARTY_STOP_DAY'    => 8,
                'PARTY_STOP_MONTH'  => 3,
                'PARTY_STOP_YEAR'   => 26
            ],
            'getParamsetDescription:MASTER' => $this->description([
                'WEEK_PROGRAM_POINTER'    => 'INTEGER',
                'DECALCIFICATION_TIME'    => 'INTEGER',
                'DECALCIFICATION_WEEKDAY' => 'INTEGER',
                'BOOST_TIME_PERIOD'       => 'INTEGER'
            ]),
            'getParamset:MASTER' => array_merge([
                'WEEK_PROGRAM_POINTER'    => 0,
                'DECALCIFICATION_TIME'    => 1320,
                'DECALCIFICATION_WEEKDAY' => 6,
                'BOOST_TIME_PERIOD'       => 7
            ], $this->schedule('P%3$d_ENDTIME_%2$s_%1$d', 'P%3$d_TEMPERATURE_%2$s_%1$d', 3, 13))
        ];
        $this->DeviceId = $this->createDevice(TestHeatingGroup::class, \HMExtended\GUID::HeatingGroup, 'HMHG', $this->IoId);
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::Address, 'INT0000001');
        IPS_SetProperty($this->DeviceId, 'enable_DECALCIFICATION_TIME', true);
        IPS_SetProperty($this->DeviceId, 'enable_BOOST_TIME_PERIOD', true);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
    }

    public function testPartyTimestampIsComposedFromTimeDayMonthAndYear(): void
    {
        $this->assertSame(mktime(9, 30, 0, 3, 5, 2026), GetValueInteger($this->variable('PARTY_START_TIME')));
        $this->assertSame(mktime(19, 0, 0, 3, 8, 2026), GetValueInteger($this->variable('PARTY_STOP_TIME')));
    }

    public function testDecalcificationTimeIsConvertedToTimestamp(): void
    {
        $this->assertSame('22:00', date('H:i', GetValueInteger($this->variable('DECALCIFICATION_TIME'))));
    }

    public function testBoostPeriodIsMultipliedByFive(): void
    {
        $this->assertSame(35, GetValueInteger($this->variable('BOOST_TIME_PERIOD')));
    }

    public function testActiveProfileStartsAtOne(): void
    {
        $this->assertSame(1, GetValueInteger($this->variable('WEEK_PROGRAM_POINTER')));
    }

    /**
     * @return array<string, array{int, int, int}> Minute der Eingabe, erwartete Minute, erwartete Minuten seit 00:00 bei 09 Uhr.
     */
    public static function halfHourProvider(): array
    {
        return [
            '09:00' => [0, 0, 540],
            '09:29' => [29, 0, 540],
            '09:30' => [30, 30, 570],
            '09:31' => [31, 30, 570],
            '09:59' => [59, 30, 570]
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('halfHourProvider')]
    public function testPartyStartIsRoundedDownToHalfHour(int $Minute, int $ExpectedMinute, int $ExpectedTime): void
    {
        $Timestamp = mktime(9, $Minute, 17, 3, 5, 2026);
        $this->assertSame('', $this->action('PARTY_START_TIME', $Timestamp));
        $Put = $this->lastPut('VALUES');
        $this->assertSame(
            ['PARTY_START_TIME' => $ExpectedTime, 'PARTY_START_DAY' => 5, 'PARTY_START_MONTH' => 3, 'PARTY_START_YEAR' => 26],
            $Put
        );
        $this->assertSame(mktime(9, $ExpectedMinute, 0, 3, 5, 2026), GetValueInteger($this->variable('PARTY_START_TIME')));
    }

    public function testPartyStopIsSentWithDateParts(): void
    {
        $this->action('PARTY_STOP_TIME', mktime(23, 45, 0, 12, 31, 2026));
        $this->assertSame(
            ['PARTY_STOP_TIME' => 1410, 'PARTY_STOP_DAY' => 31, 'PARTY_STOP_MONTH' => 12, 'PARTY_STOP_YEAR' => 26],
            $this->lastPut('VALUES')
        );
    }

    public function testDecalcificationTimeIsSentInMinutes(): void
    {
        $this->action('DECALCIFICATION_TIME', mktime(22, 10, 0, 1, 1, 2026));
        $this->assertSame(['DECALCIFICATION_TIME' => 1320], $this->lastPut('MASTER'));
        $this->action('DECALCIFICATION_TIME', mktime(22, 30, 0, 1, 1, 2026));
        $this->assertSame(['DECALCIFICATION_TIME' => 1350], $this->lastPut('MASTER'));
        $this->assertSame('22:30', date('H:i', GetValueInteger($this->variable('DECALCIFICATION_TIME'))));
    }

    public function testBoostPeriodIsSentInFiveMinuteSteps(): void
    {
        $this->action('BOOST_TIME_PERIOD', 35);
        $this->assertSame(['BOOST_TIME_PERIOD' => 7], $this->lastPut('MASTER'));
    }

    public function testActiveProfileIsSentZeroBased(): void
    {
        $this->action('WEEK_PROGRAM_POINTER', 2);
        $this->assertSame(['WEEK_PROGRAM_POINTER' => 1], $this->lastPut('MASTER'));
        $this->assertSame(2, GetValueInteger($this->variable('WEEK_PROGRAM_POINTER')));
    }

    public function testInvalidIdentIsReportedToUser(): void
    {
        $this->assertStringContainsString('Invalid Ident.', $this->action('UNKNOWN', 1));
        $this->assertCount(0, $this->requests('putParamset'));
    }
}
