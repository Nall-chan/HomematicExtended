<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../Weather/module.php';

class WeatherTest extends HomematicModuleTestCase
{
    private const Address = '0001D3C99C1234:1';
    private const Xml = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent>%s</xml>';
    private int $WeatherId;
    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        // Datenpunkte einer HmIP-SWO-PR laut CCU-Firmware (WEATHER_TRANSMIT).
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription' => [
                'ACTUAL_TEMPERATURE'        => ['ID' => 'ACTUAL_TEMPERATURE', 'TYPE' => 'FLOAT', 'UNIT' => '°C', 'OPERATIONS' => 5],
                'ACTUAL_TEMPERATURE_STATUS' => ['ID' => 'ACTUAL_TEMPERATURE_STATUS', 'TYPE' => 'ENUM', 'UNIT' => '', 'OPERATIONS' => 5],
                'HUMIDITY'                  => ['ID' => 'HUMIDITY', 'TYPE' => 'INTEGER', 'UNIT' => '%', 'OPERATIONS' => 5],
                'ILLUMINATION'              => ['ID' => 'ILLUMINATION', 'TYPE' => 'FLOAT', 'UNIT' => 'Lux', 'OPERATIONS' => 5],
                'SUNSHINEDURATION'          => ['ID' => 'SUNSHINEDURATION', 'TYPE' => 'INTEGER', 'UNIT' => 'min', 'OPERATIONS' => 5],
                'SUNSHINE_THRESHOLD_OVERRUN'=> ['ID' => 'SUNSHINE_THRESHOLD_OVERRUN', 'TYPE' => 'BOOL', 'UNIT' => '', 'OPERATIONS' => 5],
                'WIND_SPEED'                => ['ID' => 'WIND_SPEED', 'TYPE' => 'FLOAT', 'UNIT' => 'km/h', 'OPERATIONS' => 5],
                'WIND_DIR'                  => ['ID' => 'WIND_DIR', 'TYPE' => 'INTEGER', 'UNIT' => '°', 'OPERATIONS' => 5],
                'WIND_DIR_RANGE'            => ['ID' => 'WIND_DIR_RANGE', 'TYPE' => 'INTEGER', 'UNIT' => '°', 'OPERATIONS' => 5],
                'RAINING'                   => ['ID' => 'RAINING', 'TYPE' => 'BOOL', 'UNIT' => '', 'OPERATIONS' => 5],
                'RAIN_COUNTER'              => ['ID' => 'RAIN_COUNTER', 'TYPE' => 'FLOAT', 'UNIT' => 'mm', 'OPERATIONS' => 5]
            ],
            'getParamset' => [
                'ACTUAL_TEMPERATURE' => 12.5,
                'HUMIDITY'           => 80,
                'ILLUMINATION'       => 1500.0,
                'SUNSHINEDURATION'   => 42,
                'WIND_SPEED'         => 10.5,
                'WIND_DIR'           => 270,
                'RAINING'            => true,
                'RAIN_COUNTER'       => 3.4
            ]
        ];
        FakeHomematicIO::$ScriptResponse = $this->scriptResponse([
            'SUNSHINEDURATION_TOTAL'     => '1234.000000',
            'SUNSHINEDURATION_TODAY'     => '42.000000',
            'SUNSHINEDURATION_YESTERDAY' => '300.000000',
            'RAIN_COUNTER_TOTAL'         => '512.300000',
            'RAIN_COUNTER_TODAY'         => '0.000000',
            'RAIN_COUNTER_YESTERDAY'     => '4.500000'
        ]);
        $this->WeatherId = $this->createWeather();
    }

    public function testDefaultProtocolIsHmIp(): void
    {
        $this->assertSame(2, json_decode(IPS_GetConfiguration($this->WeatherId), true)['Protocol']);
    }

    public function testValuesAreReadWithProfiles(): void
    {
        $Expected = [
            'ACTUAL_TEMPERATURE' => [12.5, '~Temperature'],
            'HUMIDITY'           => [80, '~Humidity'],
            'ILLUMINATION'       => [1500.0, '~Illumination.F'],
            'WIND_SPEED'         => [10.5, '~WindSpeed.kmh'],
            'WIND_DIR'           => [270, '~WindDirection'],
            'RAINING'            => [true, '~Raining'],
            'RAIN_COUNTER'       => [3.4, '~Rainfall']
        ];
        foreach ($Expected as $Ident => [$Value, $Profile]) {
            $VariableId = IPS_GetObjectIDByIdent($Ident, $this->WeatherId);
            $this->assertEquals($Value, GetValue($VariableId), $Ident);
            $this->assertSame($Profile, IPS_GetVariable($VariableId)['VariableProfile'], $Ident);
        }
        $this->assertEquals(42, GetValue(IPS_GetObjectIDByIdent('SUNSHINEDURATION', $this->WeatherId)));
    }

    public function testStatusEnumIsSkipped(): void
    {
        $this->assertFalse(@IPS_GetObjectIDByIdent('ACTUAL_TEMPERATURE_STATUS', $this->WeatherId));
    }

    public function testSystemVariablesAreRead(): void
    {
        $Expected = [
            'SUNSHINEDURATION_TOTAL'     => 1234.0,
            'SUNSHINEDURATION_TODAY'     => 42.0,
            'SUNSHINEDURATION_YESTERDAY' => 300.0,
            'RAIN_COUNTER_TOTAL'         => 512.3,
            'RAIN_COUNTER_TODAY'         => 0.0,
            'RAIN_COUNTER_YESTERDAY'     => 4.5
        ];
        foreach ($Expected as $Ident => $Value) {
            $this->assertEquals($Value, GetValueFloat(IPS_GetObjectIDByIdent($Ident, $this->WeatherId)), $Ident);
        }
        $this->assertSame('~Rainfall', IPS_GetVariable(IPS_GetObjectIDByIdent('RAIN_COUNTER_TOTAL', $this->WeatherId))['VariableProfile']);
        $this->assertSame('Sunshine duration today', IPS_GetName(IPS_GetObjectIDByIdent('SUNSHINEDURATION_TODAY', $this->WeatherId)));
    }

    public function testScriptUsesSystemVariableNamesOfTheCcu(): void
    {
        FakeHomematicIO::$Requests = [];
        IPS_ApplyChanges($this->WeatherId);
        $Scripts = array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['Content'])), 'Content');
        $this->assertCount(1, $Scripts);
        $Script = reset($Scripts);
        $this->assertStringContainsString('dom.GetObject("HmIP-RF.' . self::Address . '.ACTUAL_TEMPERATURE")', $Script);
        $this->assertStringContainsString('"svHmIPSunshineCounter_" # iChannel # "_' . self::Address . '"', $Script);
        $this->assertStringContainsString('"svHmIPRainCounter_" # iChannel # "_' . self::Address . '"', $Script);
        // Tages- und Vortageswerte tragen nur die Kanal-ID im Namen.
        foreach (['svHmIPSunshineCounterToday_', 'svHmIPSunshineCounterYesterday_', 'svHmIPRainCounterToday_', 'svHmIPRainCounterYesterday_'] as $Name) {
            $this->assertStringContainsString('"' . $Name . '" # iChannel)', $Script, $Name);
        }
    }

    public function testMissingRainSensorCreatesNoRainVariables(): void
    {
        // HmIP-SWO-B: die CCU führt nur die Systemvariablen der Sonnenscheindauer.
        FakeHomematicIO::$ScriptResponse = $this->scriptResponse([
            'SUNSHINEDURATION_TOTAL'     => '10.000000',
            'SUNSHINEDURATION_TODAY'     => '0.000000',
            'SUNSHINEDURATION_YESTERDAY' => '5.000000'
        ]);
        $Weather = $this->createWeather();
        $this->assertEquals(10.0, GetValueFloat(IPS_GetObjectIDByIdent('SUNSHINEDURATION_TOTAL', $Weather)));
        foreach (['RAIN_COUNTER_TOTAL', 'RAIN_COUNTER_TODAY', 'RAIN_COUNTER_YESTERDAY'] as $Ident) {
            $this->assertFalse(@IPS_GetObjectIDByIdent($Ident, $Weather), $Ident);
        }
    }

    public function testUnknownChannelCreatesNoSystemVariables(): void
    {
        FakeHomematicIO::$ScriptResponse = sprintf(self::Xml, '<oDP>null</oDP><oSV>null</oSV><iChannel>0</iChannel>');
        $Weather = $this->createWeather();
        $this->assertFalse(@IPS_GetObjectIDByIdent('SUNSHINEDURATION_TOTAL', $Weather));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('ACTUAL_TEMPERATURE', $Weather));
    }

    public function testTriggerEventStartsTimerAndTimerReadsSystemVariables(): void
    {
        $Interface = IPS\InstanceManager::getInstanceInterface($this->WeatherId);
        $this->sendEvent('ACTUAL_TEMPERATURE', 13.0);
        $this->assertSame(0, $this->timerInterval());
        $this->sendEvent('RAIN_COUNTER', 3.6);
        $this->assertEquals(3.6, GetValueFloat(IPS_GetObjectIDByIdent('RAIN_COUNTER', $this->WeatherId)));
        $this->assertSame(5000, $this->timerInterval());
        FakeHomematicIO::$ScriptResponse = $this->scriptResponse(['RAIN_COUNTER_TOTAL' => '512.500000', 'RAIN_COUNTER_TODAY' => '0.200000']);
        $Interface->RequestAction(\HMExtended\Device\Timer::ReadSystemVariables, true);
        $this->assertEquals(512.5, GetValueFloat(IPS_GetObjectIDByIdent('RAIN_COUNTER_TOTAL', $this->WeatherId)));
        $this->assertEquals(0.2, GetValueFloat(IPS_GetObjectIDByIdent('RAIN_COUNTER_TODAY', $this->WeatherId)));
        $this->assertSame(0, $this->timerInterval());
    }

    private function createWeather(): int
    {
        $Id = $this->createDevice(HomeMaticWeather::class, \HMExtended\GUID::Weather, 'HMWE', $this->IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, self::Address);
        IPS_ApplyChanges($Id);
        return $Id;
    }

    private function scriptResponse(array $Values): string
    {
        $Content = '<oDP>HmIP-RF.' . self::Address . '.ACTUAL_TEMPERATURE</oDP><oSV>x</oSV><iChannel>4711</iChannel>';
        foreach ($Values as $Ident => $Value) {
            $Content .= '<' . $Ident . '>' . $Value . '</' . $Ident . '>';
        }
        return sprintf(self::Xml, $Content);
    }

    private function sendEvent(string $Datapoint, mixed $Value): void
    {
        IPS\InstanceManager::getInstanceInterface($this->WeatherId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => $Datapoint,
            'VariableValue' => $Value
        ]));
    }

    private function timerInterval(): int
    {
        $Method = new ReflectionMethod(HomeMaticWeather::class, 'GetTimerInterval');
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($this->WeatherId), \HMExtended\Device\Timer::ReadSystemVariables);
    }
}
