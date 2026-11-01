<?php

declare(strict_types=1);

require_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../RGBW/module.php';

/**
 * Prüft das Modul HomeMatic RGBW (HM-LC-RGBW-WM mit allen Kanälen in einer Instanz).
 */
class RGBWTest extends HomematicModuleTestCase
{
    private const Address = 'MEQ1097619';
    private int $Id;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        $Children = [];
        for ($Channel = 0; $Channel <= 3; $Channel++) {
            $Children[] = self::Address . ':' . $Channel;
        }
        // Datenpunkte laut getParamsetDescription des Gerätes (Auszug)
        FakeHomematicIO::$RpcResponses = [
            'getDeviceDescription'                                  => ['ADDRESS' => self::Address, 'CHILDREN' => $Children],
            'getParamsetDescription:' . self::Address . ':0:VALUES' => [
                'UNREACH'      => ['ID' => 'UNREACH', 'TYPE' => 'BOOL', 'OPERATIONS' => 5],
                'AES_KEY'      => ['ID' => 'AES_KEY', 'TYPE' => 'INTEGER', 'OPERATIONS' => 1],
                'LOWBAT'       => ['ID' => 'LOWBAT', 'TYPE' => 'BOOL', 'OPERATIONS' => 5],
                'RSSI_DEVICE'  => ['ID' => 'RSSI_DEVICE', 'TYPE' => 'INTEGER', 'OPERATIONS' => 5]
            ],
            'getParamsetDescription:' . self::Address . ':1:VALUES' => [
                'DIRECTION'    => ['ID' => 'DIRECTION', 'TYPE' => 'ENUM', 'OPERATIONS' => 5, 'VALUE_LIST' => ['NONE', 'UP', 'DOWN', 'UNDEFINED']],
                'INHIBIT'      => ['ID' => 'INHIBIT', 'TYPE' => 'BOOL', 'OPERATIONS' => 7],
                'INSTALL_TEST' => ['ID' => 'INSTALL_TEST', 'TYPE' => 'ACTION', 'OPERATIONS' => 2],
                'LEVEL'        => ['ID' => 'LEVEL', 'TYPE' => 'FLOAT', 'OPERATIONS' => 7, 'MIN' => 0.0, 'MAX' => 1.0],
                'OLD_LEVEL'    => ['ID' => 'OLD_LEVEL', 'TYPE' => 'ACTION', 'OPERATIONS' => 2],
                'ON_TIME'      => ['ID' => 'ON_TIME', 'TYPE' => 'FLOAT', 'OPERATIONS' => 2],
                'RAMP_STOP'    => ['ID' => 'RAMP_STOP', 'TYPE' => 'ACTION', 'OPERATIONS' => 2],
                'RAMP_TIME'    => ['ID' => 'RAMP_TIME', 'TYPE' => 'FLOAT', 'OPERATIONS' => 2],
                'WORKING'      => ['ID' => 'WORKING', 'TYPE' => 'BOOL', 'OPERATIONS' => 5]
            ],
            'getParamsetDescription:' . self::Address . ':2:VALUES' => [
                'COLOR'               => ['ID' => 'COLOR', 'TYPE' => 'INTEGER', 'OPERATIONS' => 7, 'MIN' => 0, 'MAX' => 255],
                'INHIBIT'             => ['ID' => 'INHIBIT', 'TYPE' => 'BOOL', 'OPERATIONS' => 7],
                'ACT_HSV_COLOR_VALUE' => ['ID' => 'ACT_HSV_COLOR_VALUE', 'TYPE' => 'INTEGER', 'OPERATIONS' => 2],
                'USER_COLOR'          => ['ID' => 'USER_COLOR', 'TYPE' => 'STRING', 'OPERATIONS' => 2]
            ],
            'getParamsetDescription:' . self::Address . ':3:VALUES' => [
                'PROGRAM'         => ['ID' => 'PROGRAM', 'TYPE' => 'INTEGER', 'OPERATIONS' => 7],
                'INHIBIT'         => ['ID' => 'INHIBIT', 'TYPE' => 'BOOL', 'OPERATIONS' => 7],
                'ACT_MIN_BOARDER' => ['ID' => 'ACT_MIN_BOARDER', 'TYPE' => 'INTEGER', 'OPERATIONS' => 3],
                'ACT_BRIGHTNESS'  => ['ID' => 'ACT_BRIGHTNESS', 'TYPE' => 'INTEGER', 'OPERATIONS' => 3],
                'ACT_MAX_BOARDER' => ['ID' => 'ACT_MAX_BOARDER', 'TYPE' => 'INTEGER', 'OPERATIONS' => 3],
                'ON_TIME'         => ['ID' => 'ON_TIME', 'TYPE' => 'FLOAT', 'OPERATIONS' => 3],
                'RAMP_TIME'       => ['ID' => 'RAMP_TIME', 'TYPE' => 'FLOAT', 'OPERATIONS' => 3]
            ],
            'getParamset:' . self::Address . ':0:VALUES'  => ['UNREACH' => false, 'RSSI_DEVICE' => -60],
            'getParamset:' . self::Address . ':1:VALUES'  => ['LEVEL' => 1.0, 'WORKING' => false, 'DIRECTION' => 0, 'INHIBIT' => false],
            'getParamset:' . self::Address . ':2:VALUES'  => ['COLOR' => 10, 'INHIBIT' => false],
            'getParamset:' . self::Address . ':3:VALUES'  => ['PROGRAM' => 4, 'ON_TIME' => 0.0, 'RAMP_TIME' => 0.5, 'ACT_MIN_BOARDER' => 0],
            'getParamset:MASTER'                          => ['WHITE_ADJUSTMENT_VALUE_RED' => 90, 'WHITE_ADJUSTMENT_VALUE_GREEN' => 80, 'WHITE_ADJUSTMENT_VALUE_BLUE' => 100, 'AES_ACTIVE' => false],
            'putParamset'                                 => []
        ];
        $this->Id = $this->createDevice(HomeMaticRGBW::class, \HMExtended\GUID::RGBW, 'HMRGB', $IoId);
        IPS_SetProperty($this->Id, \HMExtended\Device\Property::Address, self::Address);
        IPS_ApplyChanges($this->Id);
        FakeHomematicIO::$Requests = [];
    }

    public function testVariablesAreCreated(): void
    {
        foreach (['LEVEL_1', 'WORKING_1', 'DIRECTION_1', 'INHIBIT_1', 'COLOR_2', 'INHIBIT_2', 'PROGRAM_3', 'ON_TIME_3', 'RAMP_TIME_3', 'UNREACH', 'RSSI_DEVICE', 'STATE', 'COLOR_HSV', 'RAMP_TIME_1', 'ON_TIME_1'] as $Ident) {
            $this->assertGreaterThan(0, $this->variable($Ident), $Ident);
        }
        // Nur schreibbare Datenpunkte, Installationstest und Schlüssel sind keine Variablen
        foreach (['OLD_LEVEL_1', 'RAMP_STOP_1', 'INSTALL_TEST_1', 'USER_COLOR_2', 'ACT_HSV_COLOR_VALUE_2', 'AES_KEY', 'LOWBAT', 'ACT_MIN_BOARDER_3', 'ACT_MAX_BOARDER_3', 'ACT_BRIGHTNESS_3'] as $Ident) {
            $this->assertFalse(@IPS_GetObjectIDByIdent($Ident, $this->Id), $Ident);
        }
        $this->assertSame('Brightness', IPS_GetName($this->variable('LEVEL_1')));
        $this->assertSame('Hue', IPS_GetName($this->variable('COLOR_2')));
        $this->assertSame('Program', IPS_GetName($this->variable('PROGRAM_3')));
        $this->assertSame('Direction', IPS_GetName($this->variable('DIRECTION_1')));
        $this->assertSame('Inhibit brightness', IPS_GetName($this->variable('INHIBIT_1')));
        $this->assertSame('Inhibit color', IPS_GetName($this->variable('INHIBIT_2')));
        $this->assertSame('Inhibit program', IPS_GetName($this->variable('INHIBIT_3')));
        $this->assertSame('Ramp program', IPS_GetName($this->variable('RAMP_TIME_3')));
        $this->assertSame('On time program', IPS_GetName($this->variable('ON_TIME_3')));
        $this->assertSame('Ramp brightness', IPS_GetName($this->variable('RAMP_TIME_1')));
        $this->assertSame('On time brightness', IPS_GetName($this->variable('ON_TIME_1')));
        $this->assertSame(VARIABLETYPE_BOOLEAN, IPS_GetVariable($this->variable('STATE'))['VariableType']);
        $this->assertSame(VARIABLETYPE_STRING, IPS_GetVariable($this->variable('COLOR_HSV'))['VariableType']);
        foreach (['STATE', 'COLOR_HSV', 'RAMP_TIME_1', 'ON_TIME_1', 'LEVEL_1', 'COLOR_2', 'PROGRAM_3'] as $Ident) {
            $this->assertTrue(HasAction($this->variable($Ident)), $Ident);
        }
        $this->assertEquals(0.5, GetValueFloat($this->variable('RAMP_TIME_1')));
    }

    public function testPresentationsMatchTheLightTile(): void
    {
        $Level = IPS_GetVariable($this->variable('LEVEL_1'))['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_SLIDER, $Level['PRESENTATION']);
        $this->assertSame(2, $Level['USAGE_TYPE']);
        $State = IPS_GetVariable($this->variable('STATE'))['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_SWITCH, $State['PRESENTATION']);
        $Color = IPS_GetVariable($this->variable('COLOR_HSV'))['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_COLOR, $Color['PRESENTATION']);
        $this->assertSame(COLOR_ENCODING_HSV, $Color['ENCODING']);
        $Hue = IPS_GetVariable($this->variable('COLOR_2'))['VariablePresentation'];
        $this->assertSame(200, $Hue['MAX']);
        $Gradient = json_decode($Hue['CUSTOM_GRADIENT'], true);
        $this->assertSame(0xFFFFFF, end($Gradient)['Color']);
        $Program = json_decode(IPS_GetVariable($this->variable('PROGRAM_3'))['VariablePresentation']['OPTIONS'], true);
        $this->assertSame(['Off', 'Slow color change', 'Normal color change', 'Fast color change', 'Campfire', 'Waterfall', 'TV simulation'], array_column($Program, 'Caption'));
    }

    public function testReadOnlyEnumerationShowsTheTextsOfTheValues(): void
    {
        $Presentation = IPS_GetVariable($this->variable('DIRECTION_1'))['VariablePresentation'];
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $Presentation['PRESENTATION']);
        $Intervals = json_decode($Presentation['INTERVALS'], true);
        $this->assertSame(['None', 'Up', 'Down', 'Undefined'], array_column($Intervals, 'ConstantValue'));
        $this->assertSame([0, 1, 2, 3], array_column($Intervals, 'IntervalMinValue'));
    }

    public function testValuesAreReadAndDeriveStateAndColor(): void
    {
        $this->assertEquals(1.0, GetValueFloat($this->variable('LEVEL_1')));
        $this->assertSame(10, GetValueInteger($this->variable('COLOR_2')));
        $this->assertSame(4, GetValueInteger($this->variable('PROGRAM_3')));
        $this->assertTrue(GetValueBoolean($this->variable('STATE')));
        $Hsv = json_decode(GetValueString($this->variable('COLOR_HSV')), true);
        $this->assertEquals(18, $Hsv['h']);
        $this->assertEquals(100, $Hsv['s']);
        $this->assertEquals(100, $Hsv['v']);
    }

    public function testEventsUpdateStateAndColor(): void
    {
        $Instance = IPS\InstanceManager::getInstanceInterface($this->Id);
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':1', 'VariableName' => 'LEVEL', 'VariableValue' => 0.0]));
        $this->assertFalse(GetValueBoolean($this->variable('STATE')));
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':1', 'VariableName' => 'LEVEL', 'VariableValue' => 0.4]));
        $this->assertTrue(GetValueBoolean($this->variable('STATE')));
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':2', 'VariableName' => 'COLOR', 'VariableValue' => 200]));
        $this->assertEquals(0, json_decode(GetValueString($this->variable('COLOR_HSV')), true)['s']);
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':2', 'VariableName' => 'COLOR', 'VariableValue' => 100]));
        $Hsv = json_decode(GetValueString($this->variable('COLOR_HSV')), true);
        $this->assertEquals(181, $Hsv['h']);
        $this->assertEquals(100, $Hsv['s']);
    }

    public function testSwitchingOnRestoresTheOldLevelAndSendsTheTimesOnce(): void
    {
        RequestAction($this->variable('STATE'), false);
        $Set = $this->setValueRequests();
        $this->assertSame(['RAMP_TIME', 'ON_TIME', 'LEVEL'], array_column(array_column($Set, 'Data'), 1));
        $this->assertSame(self::Address . ':1', $Set[2]['Data'][0]);
        $this->assertEquals(0.0, GetValueFloat($this->variable('LEVEL_1')));
        $this->assertFalse(GetValueBoolean($this->variable('STATE')));
        FakeHomematicIO::$Requests = [];
        RequestAction($this->variable('STATE'), true);
        $Set = $this->setValueRequests();
        // Unveränderte Zeiten werden nicht erneut gesendet
        $this->assertSame(['OLD_LEVEL'], array_column(array_column($Set, 'Data'), 1));
        $this->assertTrue($Set[0]['Data'][2]);
        $this->assertTrue(GetValueBoolean($this->variable('STATE')));
    }

    public function testTheTimesOfTheNextCommandComeFromTheVariables(): void
    {
        RequestAction($this->variable('RAMP_TIME_1'), 2.5);
        RequestAction($this->variable('ON_TIME_1'), 60.0);
        $this->assertSame([], $this->setValueRequests());
        RequestAction($this->variable('LEVEL_1'), 0.5);
        $Set = $this->setValueRequests();
        $this->assertSame(['RAMP_TIME', 'ON_TIME', 'LEVEL'], array_column(array_column($Set, 'Data'), 1));
        $this->assertSame(2.5, $Set[0]['Data'][2]);
        $this->assertSame(60.0, $Set[1]['Data'][2]);
        $this->assertSame(0.5, $Set[2]['Data'][2]);
        $this->assertEquals(0.5, GetValueFloat($this->variable('LEVEL_1')));
        // Nach einer Einschaltdauer werden die Zeiten erneut gesendet
        FakeHomematicIO::$Requests = [];
        RequestAction($this->variable('LEVEL_1'), 0.7);
        $this->assertCount(3, $this->setValueRequests());
    }

    public function testHsvColorIsConvertedToTheHueOfTheDevice(): void
    {
        RequestAction($this->variable('COLOR_HSV'), json_encode(['h' => 120, 's' => 100, 'v' => 100]));
        $Set = $this->setValueRequests();
        $this->assertCount(1, $Set);
        $this->assertSame([self::Address . ':2', 'COLOR', 66], $Set[0]['Data']);
        $this->assertSame(66, GetValueInteger($this->variable('COLOR_2')));
        // Ein laufendes Programm ist damit beendet
        $this->assertSame(0, GetValueInteger($this->variable('PROGRAM_3')));
        // Blasse Farben werden Weiß (200)
        FakeHomematicIO::$Requests = [];
        RequestAction($this->variable('COLOR_HSV'), json_encode(['h' => 40, 's' => 20, 'v' => 100]));
        $this->assertSame(200, $this->setValueRequests()[0]['Data'][2]);
        $this->assertEquals(0, json_decode(GetValueString($this->variable('COLOR_HSV')), true)['s']);
        // Vollständiger Farbkreis
        FakeHomematicIO::$Requests = [];
        RequestAction($this->variable('COLOR_HSV'), json_encode(['h' => 360, 's' => 100, 'v' => 100]));
        $this->assertSame(0, $this->setValueRequests()[0]['Data'][2]);
    }

    public function testInvalidHsvColorIsRejected(): void
    {
        ob_start();
        RequestAction($this->variable('COLOR_HSV'), 'red');
        $this->assertStringContainsString('Invalid color', ob_get_clean());
        $this->assertSame([], $this->setValueRequests());
    }

    public function testChoosingAHueWritesTheDatapointAndStopsTheProgram(): void
    {
        RequestAction($this->variable('COLOR_2'), 150);
        $Set = $this->setValueRequests();
        $this->assertSame([self::Address . ':2', 'COLOR', 150], $Set[0]['Data']);
        $this->assertSame(0, GetValueInteger($this->variable('PROGRAM_3')));
        $this->assertEquals(271, json_decode(GetValueString($this->variable('COLOR_HSV')), true)['h']);
    }

    public function testFunctionsSetLevelAndColor(): void
    {
        $this->assertTrue(HMRGB_SetLevel($this->Id, 0.25, 3.0, 10.0));
        $Set = $this->setValueRequests();
        $this->assertSame(['RAMP_TIME', 'ON_TIME', 'LEVEL'], array_column(array_column($Set, 'Data'), 1));
        $this->assertSame([3.0, 10.0, 0.25], array_column(array_column($Set, 'Data'), 2));
        FakeHomematicIO::$Requests = [];
        $this->assertTrue(HMRGB_SetColor($this->Id, 240, 100));
        $this->assertSame([self::Address . ':2', 'COLOR', 133], $this->setValueRequests()[0]['Data']);
        FakeHomematicIO::$Requests = [];
        ob_start();
        $this->assertFalse(HMRGB_SetLevel($this->Id, 1.5, 0.5, 0.0));
        $this->assertFalse(HMRGB_SetLevel($this->Id, 0.5, -1.0, 0.0));
        $this->assertFalse(HMRGB_SetColor($this->Id, 361, 0));
        $this->assertFalse(HMRGB_SetColor($this->Id, 0, 101));
        $this->assertSame(4, substr_count(ob_get_clean(), PHP_EOL));
        $this->assertSame([], $this->setValueRequests());
    }

    public function testWhiteBalanceIsReadAndWrittenToChannel2(): void
    {
        $this->assertSame(['Red' => 90, 'Green' => 80, 'Blue' => 100], json_decode(HMRGB_ReadWhiteBalance($this->Id), true));
        $Get = array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'getParamset'));
        $this->assertSame([self::Address . ':2', 'MASTER'], array_slice($Get[0]['Data'], 0, 2));
        FakeHomematicIO::$Requests = [];
        $this->assertTrue(HMRGB_SetWhiteBalance($this->Id, 95, 85, 75));
        $Put = array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'putParamset'));
        $this->assertSame([self::Address . ':2', 'MASTER'], array_slice($Put[0]['Data'], 0, 2));
        $this->assertSame(['WHITE_ADJUSTMENT_VALUE_RED' => 95, 'WHITE_ADJUSTMENT_VALUE_GREEN' => 85, 'WHITE_ADJUSTMENT_VALUE_BLUE' => 75], json_decode($Put[0]['Data'][2], true));
        FakeHomematicIO::$Requests = [];
        ob_start();
        $this->assertFalse(HMRGB_SetWhiteBalance($this->Id, 101, 0, 0));
        $this->assertStringContainsString('between 0 and 100', ob_get_clean());
        $this->assertSame([], array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'putParamset'));
    }

    public function testWriteValueAndRequestStateWorkWithTheIdents(): void
    {
        $this->assertTrue(HMRGB_WriteValueInteger($this->Id, 'PROGRAM_3', 2));
        $this->assertSame([self::Address . ':3', 'PROGRAM', 2], $this->setValueRequests()[0]['Data']);
        FakeHomematicIO::$RpcResponses['getValue'] = 77;
        $this->assertTrue(HMRGB_RequestState($this->Id, 'COLOR_2'));
        $this->assertSame(77, GetValueInteger($this->variable('COLOR_2')));
    }

    private function variable(string $Ident): int
    {
        return IPS_GetObjectIDByIdent($Ident, $this->Id);
    }

    private function setValueRequests(): array
    {
        return array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'setValue'));
    }
}
