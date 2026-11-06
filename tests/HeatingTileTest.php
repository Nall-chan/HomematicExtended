<?php

declare(strict_types=1);

include_once __DIR__ . '/HeatingTestCase.php';
require_once __DIR__ . '/../HeatingGroupHmIP/module.php';
require_once __DIR__ . '/../HeatingGroup/module.php';

/**
 * Testklasse: Die Stubs können keine Ereignisse (Wochenplan) anlegen. Der Wochenplan ist beim Einrichten abgeschaltet
 * (kein Ereignis), danach ist er für die Kachel eingeschaltet.
 */
class TileHeatingGroupHmIP extends HomeMaticIPHeatingGroup
{
    public static bool $ScheduleEnabled = false;

    public static function Slots(array $Days, int $Plan): array|string
    {
        return static::SlotsToParams($Days, $Plan);
    }

    protected function ReadPropertyBoolean(string $Name): bool
    {
        return ($Name == \HMExtended\Device\Property::Schedule) ? self::$ScheduleEnabled : parent::ReadPropertyBoolean($Name);
    }
}

class TileHeatingGroup extends HomeMaticHeatingGroup
{
    protected function ReadPropertyBoolean(string $Name): bool
    {
        return ($Name == \HMExtended\Device\Property::Schedule) ? false : parent::ReadPropertyBoolean($Name);
    }
}

/**
 * Kachel der Heizungsmodule: Rollen und Werte, abgewählte Statusvariablen und das Schreiben des Wochenplans.
 */
class HeatingTileTest extends HeatingTestCase
{
    private const Address = '3014F711A0001F5A4993F31C';

    protected function setUp(): void
    {
        parent::setUp();
        TileHeatingGroupHmIP::$ScheduleEnabled = false;
    }

    protected function tearDown(): void
    {
        TileHeatingGroupHmIP::$ScheduleEnabled = false;
        parent::tearDown();
    }

    public function testTileRolesAndValuesOfHmIPGroup(): void
    {
        $this->hmipDevice();
        $Config = $this->tileConfig();
        $this->assertSame('SET_POINT_TEMPERATURE', $Config['roles']['set']);
        $this->assertSame('SET_POINT_MODE', $Config['roles']['mode']);
        $this->assertSame('BOOST_MODE', $Config['roles']['boost']);
        $this->assertEqualsWithDelta(21.0, $Config['values']['set'], 0.001);
        $this->assertEqualsWithDelta(19.1, $Config['values']['actual'], 0.001);
        // LEVEL ist ein Anteil, BOOST_TIME in Sekunden
        $this->assertEqualsWithDelta(25.0, $Config['values']['valve'], 0.001);
        $this->assertEqualsWithDelta(5.0, $Config['values']['boostRemain'], 0.001);
        $this->assertTrue($Config['values']['boost']);
        $this->assertNull($Config['boostMode']);
        $this->assertContains('set', $Config['actions']);
        $this->assertContains('mode', $Config['actions']);
        $this->assertNotContains('actual', $Config['actions']);
        $this->assertEqualsWithDelta(4.5, $Config['limits']['min'], 0.001);
    }

    public function testDisabledStatusVariablesAreMissingInTheTile(): void
    {
        $this->hmipDevice(['PARTY', 'LEVEL']);
        $Config = $this->tileConfig();
        foreach (['partyStart', 'partyEnd', 'partyTemp', 'valve'] as $Role) {
            $this->assertArrayNotHasKey($Role, $Config['roles'], $Role);
            $this->assertArrayNotHasKey($Role, $Config['values'], $Role);
        }
        $this->assertArrayHasKey('set', $Config['roles']);
    }

    public function testTileGetsTheActiveWeekSchedule(): void
    {
        $this->hmipDevice();
        $Schedule = $this->tileConfig()['schedule'];
        $this->assertSame(1, $Schedule['plan']);
        $this->assertSame(6, $Schedule['plans']);
        $this->assertSame(13, $Schedule['slots']);
        $this->assertCount(7, $Schedule['days']);
        $this->assertEqualsWithDelta([[420, 17.0], [1320, 21.0], [1440, 17.0]], $Schedule['days'][0], 0.001);
    }

    public function testNoScheduleInTheTileWhenDisabled(): void
    {
        $this->hmipDevice();
        TileHeatingGroupHmIP::$ScheduleEnabled = false;
        $this->assertNull($this->tileConfig()['schedule']);
    }

    public function testHeatingGroupWithoutTileRolesHasNoTile(): void
    {
        $this->assertSame([], (new ReflectionClassConstant(HMHeatingDevice::class, 'TileRoles'))->getValue());
    }

    public function testBoostIsAModeValueForTheHeatingGroup(): void
    {
        $this->IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription:VALUES' => $this->description(['CONTROL_MODE' => 'INTEGER', 'SET_TEMPERATURE' => 'FLOAT', 'ACTUAL_TEMPERATURE' => 'FLOAT', 'ACTUAL_HUMIDITY' => 'INTEGER']),
            'getParamset:VALUES'            => ['CONTROL_MODE' => 3, 'SET_TEMPERATURE' => 30.5, 'ACTUAL_TEMPERATURE' => 19.0, 'ACTUAL_HUMIDITY' => 55],
            'getParamsetDescription:MASTER' => $this->description(['WEEK_PROGRAM_POINTER' => 'INTEGER', 'DECALCIFICATION_TIME' => 'INTEGER', 'DECALCIFICATION_WEEKDAY' => 'INTEGER']),
            'getParamset:MASTER'            => array_merge(['WEEK_PROGRAM_POINTER' => 0, 'DECALCIFICATION_TIME' => 1320, 'DECALCIFICATION_WEEKDAY' => 6], $this->schedule('P%3$d_ENDTIME_%2$s_%1$d', 'P%3$d_TEMPERATURE_%2$s_%1$d', 3, 13))
        ];
        $this->DeviceId = $this->createDevice(TileHeatingGroup::class, \HMExtended\GUID::HeatingGroup, 'HMHG', $this->IoId);
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::Address, 'INT0000001');
        IPS_ApplyChanges($this->DeviceId);
        $Config = $this->tileConfig();
        $this->assertSame(3, $Config['boostMode']);
        $this->assertSame('CONTROL_MODE', $Config['roles']['mode']);
        $this->assertSame(3, $Config['values']['mode']);
        $this->assertSame(55, $Config['values']['humidity']);
        $this->assertArrayNotHasKey('boost', $Config['roles']);
    }

    //################# Wochenplan schreiben

    public function testSlotsAreConvertedToParameters(): void
    {
        $Days = array_fill(0, 7, [[423, 17.2], [1320, 21.0], [1440, 17.0]]);
        $Result = TileHeatingGroupHmIP::Slots($Days, 2);
        $this->assertIsArray($Result);
        [$Params, $Schedule] = $Result;
        // Endzeiten im Raster von 5 Minuten, Temperaturen auf 0,5 °C, der letzte Eintrag gilt bis 24:00
        $this->assertSame(425, $Params['P2_ENDTIME_MONDAY_1']);
        $this->assertEqualsWithDelta(17.0, $Params['P2_TEMPERATURE_MONDAY_1'], 0.001);
        $this->assertSame(1320, $Params['P2_ENDTIME_SUNDAY_2']);
        $this->assertSame(1440, $Params['P2_ENDTIME_SUNDAY_3']);
        $this->assertCount(7 * 3 * 2, $Params);
        // Zeitprofil je Wochentag-Bit (Montag = 1, Sonntag = 64)
        $this->assertSame([1, 2, 4, 8, 16, 32, 64], array_keys($Schedule));
        $this->assertEqualsWithDelta(21.0, $Schedule[64][2]['Temp'], 0.001);
    }

    public function testLastSlotOfADayAlwaysEndsAtMidnight(): void
    {
        $Days = array_fill(0, 7, [[600, 20.0]]);
        [$Params] = TileHeatingGroupHmIP::Slots($Days, 1);
        $this->assertSame(1440, $Params['P1_ENDTIME_FRIDAY_1']);
    }

    /**
     * Jeder Text, den die Kachel mit T('…') ausgibt, und jede Beschriftung der Schnellwahl muss in TileTexts stehen,
     * sonst bleibt er in der Kachel englisch.
     */
    public function testAllTextsOfTheTileScriptAreTranslatable(): void
    {
        $Texts = (new ReflectionClass(HMHeatingDevice::class))->getReflectionConstant('TileTexts')->getValue();
        $Script = (string) file_get_contents(__DIR__ . '/../libs/tile/heating.js');
        preg_match_all("/\\bT\\('([^']+)'\\)/", $Script, $Used);
        preg_match_all("/\\['\\w+', '([^']+)'\\]/", $Script, $Presets);
        $Missing = array_diff(array_unique(array_merge($Used[1], $Presets[1])), $Texts);
        $this->assertSame([], array_values($Missing));
    }

    /**
     * Die Texte der Kachel müssen in der locale.json jedes Heizungsmoduls übersetzt sein.
     */
    public function testTileTextsAreTranslatedInEveryHeatingModule(): void
    {
        $Texts = (new ReflectionClass(HMHeatingDevice::class))->getReflectionConstant('TileTexts')->getValue();
        foreach (['HeatingGroupHmIP', 'HeatingGroup', 'HeatingDevice', 'ClimacontrolRegulator'] as $Module) {
            $Locale = json_decode((string) file_get_contents(__DIR__ . '/../' . $Module . '/locale.json'), true)['translations']['de'];
            $this->assertSame([], array_values(array_diff($Texts, array_keys($Locale))), $Module);
        }
    }

    public static function invalidDaysProvider(): array
    {
        $Valid = [[600, 17.0], [1440, 21.0]];
        return [
            'six days'          => [array_fill(0, 6, $Valid), 'Invalid weekly program.'],
            'empty day'         => [array_merge(array_fill(0, 6, $Valid), [[]]), 'Invalid weekly program.'],
            'text instead time' => [array_merge(array_fill(0, 6, $Valid), [[['x', 17.0], [1440, 17.0]]]), 'Invalid weekly program.'],
            'too many slots'    => [array_merge(array_fill(0, 6, $Valid), [array_map(fn ($i) => [$i * 60, 17.0], range(1, 14))]), 'A day of the schedule has more than %d switching points.'],
            'not ascending'     => [array_merge(array_fill(0, 6, $Valid), [[[900, 17.0], [600, 17.0], [1440, 17.0]]]), 'The end times of a day must be in ascending order.'],
            'same rounded time' => [array_merge(array_fill(0, 6, $Valid), [[[601, 17.0], [602, 17.0], [1440, 17.0]]]), 'The end times of a day must be in ascending order.'],
            'too cold'          => [array_merge(array_fill(0, 6, $Valid), [[[600, 3.0], [1440, 17.0]]]), 'The temperature of a switching point is out of range.'],
            'too warm'          => [array_merge(array_fill(0, 6, $Valid), [[[600, 17.0], [1440, 31.0]]]), 'The temperature of a switching point is out of range.']
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('invalidDaysProvider')]
    public function testInvalidSlotsAreRejected(array $Days, string $Error): void
    {
        $this->assertSame($Error, TileHeatingGroupHmIP::Slots($Days, 1));
    }

    public function testTileSavesTheWeekScheduleInOneCall(): void
    {
        $this->hmipDevice();
        $Days = array_fill(0, 7, [[390, 17.0], [1380, 21.5], [1440, 17.0]]);
        $this->assertSame('', $this->action('TileSave', json_encode(['days' => $Days])));
        $Params = $this->lastPut('MASTER');
        $this->assertCount(1, $this->requests('putParamset', 'MASTER'));
        $this->assertSame(390, $Params['P1_ENDTIME_MONDAY_1']);
        $this->assertEqualsWithDelta(21.5, $Params['P1_TEMPERATURE_SATURDAY_2'], 0.001);
        // Das gemerkte Zeitprofil und die Kachel haben den neuen Stand
        $Schedule = $this->tileConfig()['schedule'];
        $this->assertEqualsWithDelta([[390, 17.0], [1380, 21.5], [1440, 17.0]], $Schedule['days'][6], 0.001);
    }

    public function testTileSaveWithInvalidDataSendsNothing(): void
    {
        $this->hmipDevice();
        $Days = array_fill(0, 7, [[900, 17.0], [600, 21.0], [1440, 17.0]]);
        // Fehler gehen an die Kachel, nicht an den Aufrufer
        $this->assertSame('', $this->action('TileSave', json_encode(['days' => $Days])));
        $this->assertCount(0, $this->requests('putParamset'));
        $Schedule = $this->tileConfig()['schedule'];
        $this->assertEqualsWithDelta([[420, 17.0], [1320, 21.0], [1440, 17.0]], $Schedule['days'][0], 0.001);
    }

    public function testTileSaveIsRejectedWithoutWeeklyProgram(): void
    {
        $this->hmipDevice();
        TileHeatingGroupHmIP::$ScheduleEnabled = false;
        $this->action('TileSave', json_encode(['days' => array_fill(0, 7, [[1440, 17.0]])]));
        $this->assertCount(0, $this->requests('putParamset'));
    }

    public function testTileReloadNeedsNoValueChange(): void
    {
        $this->hmipDevice();
        $this->assertSame('', $this->action('TileReload', ''));
        $this->assertCount(0, $this->requests('setValue'));
    }

    /**
     * HmIP-Heizgruppe mit den Werten der Kachel. Wochenplan: Profil 1, an jedem Tag 17 °C bis 07:00, 21 °C bis 22:00, danach 17 °C.
     */
    private function hmipDevice(array $DisabledProperties = []): void
    {
        $this->IoId = $this->createFakeIo();
        $Schedule = $this->schedule('P%3$d_ENDTIME_%2$s_%1$d', 'P%3$d_TEMPERATURE_%2$s_%1$d', 6, 13);
        foreach (self::WeekDays as $Day) {
            foreach ([1 => [420, 17.0], 2 => [1320, 21.0], 3 => [1440, 17.0]] as $Slot => [$Time, $Temp]) {
                $Schedule[sprintf('P1_ENDTIME_%s_%d', $Day, $Slot)] = $Time;
                $Schedule[sprintf('P1_TEMPERATURE_%s_%d', $Day, $Slot)] = $Temp;
            }
        }
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription:VALUES' => $this->description([
                'ACTIVE_PROFILE'              => 'INTEGER',
                'ACTUAL_TEMPERATURE'          => 'FLOAT',
                'BOOST_MODE'                  => 'BOOL',
                'BOOST_TIME'                  => 'INTEGER',
                'HUMIDITY'                    => 'INTEGER',
                'LEVEL'                       => 'FLOAT',
                'SET_POINT_MODE'              => 'INTEGER',
                'CONTROL_MODE'                => 'INTEGER',
                'SET_POINT_TEMPERATURE'       => 'FLOAT',
                'PARTY_SET_POINT_TEMPERATURE' => 'FLOAT',
                'PARTY_TIME_START'            => 'STRING',
                'PARTY_TIME_END'              => 'STRING'
            ]),
            'getParamset:VALUES' => [
                'ACTIVE_PROFILE'              => 1,
                'ACTUAL_TEMPERATURE'          => 19.1,
                'BOOST_MODE'                  => true,
                'BOOST_TIME'                  => 300,
                'HUMIDITY'                    => 60,
                'LEVEL'                       => 0.25,
                'SET_POINT_MODE'              => 0,
                'CONTROL_MODE'                => 0,
                'SET_POINT_TEMPERATURE'       => 21.0,
                'PARTY_SET_POINT_TEMPERATURE' => 17.0,
                'PARTY_TIME_START'            => '2026_03_05 09:30',
                'PARTY_TIME_END'              => '2026_03_05 17:30'
            ],
            'getParamsetDescription:MASTER' => $this->description(['DECALCIFICATION_TIME' => 'INTEGER']),
            'getParamset:MASTER'            => array_merge(['DECALCIFICATION_TIME' => 5], $Schedule)
        ];
        $this->DeviceId = $this->createDevice(TileHeatingGroupHmIP::class, \HMExtended\GUID::HeatingGroupHmIP, 'HMHI', $this->IoId);
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::Address, self::Address);
        IPS_SetProperty($this->DeviceId, 'enable_BOOST_TIME', true);
        foreach ($DisabledProperties as $Property) {
            IPS_SetProperty($this->DeviceId, 'enable_' . $Property, false);
        }
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
        TileHeatingGroupHmIP::$ScheduleEnabled = true;
    }

    private function tileConfig(): array
    {
        $Html = $this->device()->GetVisualizationTile();
        $this->assertStringNotContainsString('/*CONFIG*/', $Html);
        $this->assertStringNotContainsString('/*CORE*/', $Html);
        $this->assertStringNotContainsString('/*STYLE*/', $Html);
        $this->assertSame(1, preg_match('/window\.HT_CONFIG = (\{.*\});<\/script>/', $Html, $Matches));
        return json_decode($Matches[1], true);
    }
}
