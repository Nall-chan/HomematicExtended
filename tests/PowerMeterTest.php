<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../PowerMeter/module.php';

class PowerMeterTest extends HomematicModuleTestCase
{
    private const Address = 'XXX0000001:1';
    private int $PowerMeterId;
    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        $this->IoId = $IoId;
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription' => [
                'ENERGY_COUNTER'     => ['ID' => 'ENERGY_COUNTER', 'TYPE' => 'FLOAT', 'UNIT' => 'Wh', 'OPERATIONS' => 5],
                'GAS_ENERGY_COUNTER' => ['ID' => 'GAS_ENERGY_COUNTER', 'TYPE' => 'FLOAT', 'UNIT' => 'm3', 'OPERATIONS' => 5],
                'GAS_VOLUME'         => ['ID' => 'GAS_VOLUME', 'TYPE' => 'FLOAT', 'UNIT' => 'm3', 'OPERATIONS' => 5],
                'GAS_VOLUME_STATUS'  => ['ID' => 'GAS_VOLUME_STATUS', 'TYPE' => 'ENUM', 'UNIT' => '', 'OPERATIONS' => 5],
                'POWER'              => ['ID' => 'POWER', 'TYPE' => 'FLOAT', 'UNIT' => 'W', 'OPERATIONS' => 5],
                'CURRENT'            => ['ID' => 'CURRENT', 'TYPE' => 'FLOAT', 'UNIT' => 'mA', 'OPERATIONS' => 5],
                'FREQUENCY'          => ['ID' => 'FREQUENCY', 'TYPE' => 'FLOAT', 'UNIT' => 'Hz', 'OPERATIONS' => 5],
                'VOLTAGE'            => ['ID' => 'VOLTAGE', 'TYPE' => 'FLOAT', 'UNIT' => 'V', 'OPERATIONS' => 5],
                'BOOT'               => ['ID' => 'BOOT', 'TYPE' => 'BOOL', 'UNIT' => '', 'OPERATIONS' => 5],
                // unbekannter Typ und unbekannte Einheit dürfen keinen Fehler auslösen
                'WEIRD_TYPE' => ['ID' => 'WEIRD_TYPE', 'TYPE' => 'UNKNOWN', 'UNIT' => '', 'OPERATIONS' => 5],
                'WEIRD_UNIT' => ['ID' => 'WEIRD_UNIT', 'TYPE' => 'FLOAT', 'UNIT' => 'xyz', 'OPERATIONS' => 5],
            ],
            'getParamset' => [
                'ENERGY_COUNTER'     => 1500.0,
                'GAS_ENERGY_COUNTER' => 12.5,
                'GAS_VOLUME'         => 2034.2,
                'GAS_VOLUME_STATUS'  => 0,
                'POWER'              => 42.0,
                'BOOT'               => false,
                'WEIRD_UNIT'         => 1.0
            ]
        ];
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>5000000.0</Value></xml>';
        $this->PowerMeterId = $this->createDevice(HomeMaticPowermeter::class, \HMExtended\GUID::Powermeter, 'HMPM', $IoId);
        IPS_SetProperty($this->PowerMeterId, \HMExtended\Device\Property::Address, self::Address);
        IPS_ApplyChanges($this->PowerMeterId);
    }

    public function testValuesAreReadSinglyWhenParamsetFails(): void
    {
        // HM-ES-TX-WM im Strommodus (live): getParamset scheitert, weil die Gas- und IEC-Werte nie gesendet wurden.
        unset(FakeHomematicIO::$RpcResponses['getParamset']);
        FakeHomematicIO::$RpcResponses['getValue:ENERGY_COUNTER'] = 1000.0;
        FakeHomematicIO::$RpcResponses['getValue:POWER'] = 12.5;
        FakeHomematicIO::$Requests = [];
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>1000.0</Value></xml>');
        $this->assertEquals(1000.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER', $this->PowerMeterId)));
        $this->assertEquals(12.5, GetValueFloat(IPS_GetObjectIDByIdent('POWER', $this->PowerMeterId)));
        // Datenpunkte ohne Wert werden trotzdem angelegt, die Summe wird gelesen.
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('GAS_ENERGY_COUNTER', $this->PowerMeterId));
        $this->assertEquals(1.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($this->PowerMeterId)['InstanceStatus']);
        $this->assertCount(1, array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'getParamset'));
    }

    public function testNoRequestAfterMissingDescription(): void
    {
        // Die Meldung erzeugt SendRPC (der Fake-Socket kann keinen Fehler liefern, nur eine leere Antwort).
        unset(FakeHomematicIO::$RpcResponses['getParamsetDescription']);
        FakeHomematicIO::$Requests = [];
        IPS_ApplyChanges($this->PowerMeterId);
        $this->assertCount(1, FakeHomematicIO::$Requests);
    }

    public function testUnitsGetPresentations(): void
    {
        $Expected = ['CURRENT' => ' mA', 'FREQUENCY' => ' Hz', 'VOLTAGE' => ' V', 'POWER' => ' W', 'ENERGY_COUNTER' => ' Wh', 'GAS_VOLUME' => ' m³'];
        foreach ($Expected as $Ident => $Suffix) {
            $Variable = IPS_GetVariable(IPS_GetObjectIDByIdent($Ident, $this->PowerMeterId));
            $this->assertSame('', $Variable['VariableProfile'], $Ident);
            $this->assertSame([VARIABLE_PRESENTATION_VALUE_PRESENTATION, $Suffix], [$Variable['VariablePresentation']['PRESENTATION'], $Variable['VariablePresentation']['SUFFIX']], $Ident);
        }
        // Ohne bekannte Einheit keine Darstellung.
        $this->assertSame([], IPS_GetVariable(IPS_GetObjectIDByIdent('WEIRD_UNIT', $this->PowerMeterId))['VariablePresentation']);
    }

    public function testLegacyProfileIsReplacedByPresentation(): void
    {
        // Stand bis Version 3.81: Systemprofil ~Watt.
        $VariableId = IPS_GetObjectIDByIdent('POWER', $this->PowerMeterId);
        IPS\VariableManager::setVariableProfile($VariableId, '~Watt');
        IPS_ApplyChanges($this->PowerMeterId);
        $Variable = IPS_GetVariable($VariableId);
        $this->assertSame('', $Variable['VariableProfile']);
        $this->assertSame(' W', $Variable['VariablePresentation']['SUFFIX']);
        $this->assertSame($VariableId, IPS_GetObjectIDByIdent('POWER', $this->PowerMeterId), 'Variable (and archive) must be kept');
    }

    public function testVariablesAreCreated(): void
    {
        foreach (['ENERGY_COUNTER', 'GAS_ENERGY_COUNTER', 'POWER', 'BOOT'] as $Ident) {
            $this->assertNotFalse(@IPS_GetObjectIDByIdent($Ident, $this->PowerMeterId), $Ident);
        }
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('GAS_ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('GAS_VOLUME', $this->PowerMeterId));
        // ENERGY_COUNTER hat einen Wert: kein Gasmodus, die gemeinsame Systemvariable gehört zu ENERGY_COUNTER
        $this->assertFalse(@IPS_GetObjectIDByIdent('GAS_VOLUME_TOTAL', $this->PowerMeterId));
        // Status-ENUM ist nicht unterstützt und erzeugt keine Variable
        $this->assertFalse(@IPS_GetObjectIDByIdent('GAS_VOLUME_STATUS', $this->PowerMeterId));
        // IEC_ENERGY_COUNTER gehört nicht zu diesem Gerät, daher auch kein Summenzähler
        $this->assertFalse(@IPS_GetObjectIDByIdent('IEC_ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
    }

    public function testTotalIsNotCreatedWithoutSystemVariable(): void
    {
        // Systemvariable fehlt auf der CCU: das Skript liefert keinen Wert.
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>');
        foreach (['ENERGY_COUNTER_TOTAL', 'GAS_ENERGY_COUNTER_TOTAL', 'GAS_VOLUME_TOTAL'] as $Ident) {
            $this->assertFalse(@IPS_GetObjectIDByIdent($Ident, $this->PowerMeterId), $Ident);
        }
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER', $this->PowerMeterId));
    }

    public function testTotalIsNotCreatedWithZeroValueAndCreatedLater(): void
    {
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>0.000000</Value></xml>');
        $this->assertFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        // Der Zähler läuft an: der nächste Event legt die Variable an.
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>3000.0</Value></xml>';
        IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'ENERGY_COUNTER',
            'VariableValue' => 3000.0
        ]));
        $this->runTimer();
        $this->assertEquals(3.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
        // Zurücksetzen auf 0: die vorhandene Variable folgt.
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>0.0</Value></xml>';
        IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'ENERGY_COUNTER',
            'VariableValue' => 0.0
        ]));
        $this->runTimer();
        $this->assertEquals(0.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
    }

    public function testEventOfGasVolumeUpdatesTotal(): void
    {
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>2035.5</Value></xml>';
        IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'GAS_VOLUME',
            'VariableValue' => 2035.5
        ]));
        $this->runTimer();
        $this->assertEquals(2035.5, GetValueFloat(IPS_GetObjectIDByIdent('GAS_VOLUME', $this->PowerMeterId)));
        $this->assertEquals(2035.5, GetValueFloat(IPS_GetObjectIDByIdent('GAS_VOLUME_TOTAL', $this->PowerMeterId)));
    }

    public function testGasModeOfHmIpEsi(): void
    {
        // HmIP-ESI im Gasmodus (Debug eines Forennutzers): getParamset liefert nur GAS_VOLUME, die CCU führt svEnergyCounter_… in m³.
        FakeHomematicIO::$RpcResponses['getParamset'] = ['GAS_VOLUME' => 2034.4, 'GAS_VOLUME_STATUS' => 0];
        FakeHomematicIO::$Requests = [];
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>76558.900000</Value></xml>');
        $this->assertEquals(76558.9, GetValueFloat(IPS_GetObjectIDByIdent('GAS_VOLUME_TOTAL', $this->PowerMeterId)));
        $this->assertSame(' m³', IPS_GetVariable(IPS_GetObjectIDByIdent('GAS_VOLUME_TOTAL', $this->PowerMeterId))['VariablePresentation']['SUFFIX']);
        $this->assertEquals(2034.4, GetValueFloat(IPS_GetObjectIDByIdent('GAS_VOLUME', $this->PowerMeterId)));
        // Dieselbe Systemvariable wird nicht zusätzlich als kWh ausgewertet.
        $this->assertFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        $Scripts = array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['Content'])), 'Content');
        $GasScripts = array_filter($Scripts, fn ($Script) => str_contains($Script, '.GAS_VOLUME"'));
        $this->assertCount(1, $GasScripts);
        $this->assertStringContainsString('"svEnergyCounter_"', reset($GasScripts));
        // Liefert die CCU später keine Werte, bleibt der Gasmodus erhalten (kein ENERGY_COUNTER_TOTAL aus den m³).
        FakeHomematicIO::$RpcResponses['getParamset'] = [];
        IPS_ApplyChanges($this->PowerMeterId);
        $this->assertFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
    }

    public function testEventOfEnergyCounterLeavesGasMode(): void
    {
        FakeHomematicIO::$RpcResponses['getParamset'] = ['GAS_VOLUME' => 2034.4];
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>5000.0</Value></xml>');
        $this->assertFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'ENERGY_COUNTER',
            'VariableValue' => 5000.0
        ]));
        $this->runTimer();
        $this->assertEquals(5.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
    }

    public function testFeedInCounterOfHmIpPsmco(): void
    {
        // HmIP-PSMCO / ELV-SH-PSMCI: zusätzlicher Einspeisezähler, die CCU führt ihn in svEnergyCounterFeedIn_… (Wh).
        FakeHomematicIO::$RpcResponses['getParamsetDescription']['ENERGY_COUNTER_FEED_IN'] = ['ID' => 'ENERGY_COUNTER_FEED_IN', 'TYPE' => 'FLOAT', 'UNIT' => 'Wh', 'OPERATIONS' => 5];
        FakeHomematicIO::$RpcResponses['getParamset']['ENERGY_COUNTER_FEED_IN'] = 800.0;
        FakeHomematicIO::$Requests = [];
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>2500000.0</Value></xml>');
        $this->assertEquals(800.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_FEED_IN', $this->PowerMeterId)));
        $this->assertEquals(2500.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_FEED_IN_TOTAL', $this->PowerMeterId)));
        $this->assertSame(' kWh', IPS_GetVariable(IPS_GetObjectIDByIdent('ENERGY_COUNTER_FEED_IN_TOTAL', $this->PowerMeterId))['VariablePresentation']['SUFFIX']);
        $Scripts = array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['Content'])), 'Content');
        $FeedInScripts = array_filter($Scripts, fn ($Script) => str_contains($Script, '.ENERGY_COUNTER_FEED_IN"'));
        $this->assertCount(1, $FeedInScripts);
        $this->assertStringContainsString('"svEnergyCounterFeedIn_"', reset($FeedInScripts));
        // Ein Event des Einspeisezählers aktualisiert den Summenzähler.
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>2600000.0</Value></xml>';
        IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'ENERGY_COUNTER_FEED_IN',
            'VariableValue' => 900.0
        ]));
        $this->runTimer();
        $this->assertEquals(2600.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_FEED_IN_TOTAL', $this->PowerMeterId)));
        // Der normale Zähler bleibt davon unberührt ein eigener Summenzähler.
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
    }

    public function testUnknownTypeIsSkipped(): void
    {
        $this->assertFalse(@IPS_GetObjectIDByIdent('WEIRD_TYPE', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('WEIRD_UNIT', $this->PowerMeterId));
    }

    public function testDatapointWithoutUnitKeyIsCreated(): void
    {
        // Nicht jeder Datenpunkt der CCU liefert den Schlüssel UNIT (z.B. HmIP-BSM).
        FakeHomematicIO::$RpcResponses['getParamsetDescription']['NO_UNIT'] = ['ID' => 'NO_UNIT', 'TYPE' => 'FLOAT', 'OPERATIONS' => 5];
        $this->recreateDevice('<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>0</Value></xml>');
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('NO_UNIT', $this->PowerMeterId));
    }

    public function testEventBeforeBufferIsInitializedDoesNotFail(): void
    {
        // Nach einem Update trifft ein Ereignis ein, bevor ApplyChanges den Zwischenspeicher initialisiert hat.
        $Instance = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId);
        (fn () => $this->SetBuffer('PendingCounters', ''))->call($Instance);
        $Instance->ReceiveData(json_encode(['DataID' => \HMExtended\GUID::HmDevice, 'DeviceID' => self::Address, 'VariableName' => 'ENERGY_COUNTER', 'VariableValue' => 5.0]));
        $this->assertTrue(true);
    }

    public function testValuesAreRead(): void
    {
        $this->assertEquals(1500.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER', $this->PowerMeterId)));
        $this->assertEquals(42.0, GetValueFloat(IPS_GetObjectIDByIdent('POWER', $this->PowerMeterId)));
    }

    public function testTotalScaling(): void
    {
        // Systemvariable der CCU 5000000 Wh -> 5000 kWh, Gas ohne Faktor.
        $this->assertEquals(5000.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
        $this->assertEquals(5000000.0, GetValueFloat(IPS_GetObjectIDByIdent('GAS_ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
    }

    public function testEventOfEnergyCounterUpdatesTotal(): void
    {
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>7000000.0</Value></xml>';
        $Interface = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId);
        $Interface->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'ENERGY_COUNTER',
            'VariableValue' => 1600.0
        ]));
        $this->runTimer();
        $this->assertEquals(1600.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER', $this->PowerMeterId)));
        $this->assertEquals(7000.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
    }

    public function testEventOfCounterReadsSystemVariableOnlyAfterTimer(): void
    {
        // Das Zählerprogramm der CCU läuft zeitgleich zum Ereignis, gelesen wird erst nach Ablauf des Timers und nur einmal je Datenpunkt.
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>8000000.0</Value></xml>';
        $Interface = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId);
        foreach ([1700.0, 1800.0] as $Value) {
            $Interface->ReceiveData(json_encode([
                'DataID'        => \HMExtended\GUID::HmDevice,
                'DeviceID'      => self::Address,
                'VariableName'  => 'ENERGY_COUNTER',
                'VariableValue' => $Value
            ]));
        }
        $this->assertCount(0, FakeHomematicIO::$Requests);
        $this->assertSame(50, $this->timerInterval());
        $this->assertEquals(5000.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
        $this->runTimer();
        $this->assertCount(1, FakeHomematicIO::$Requests);
        $this->assertEquals(8000.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
        $this->assertSame(0, $this->timerInterval());
    }

    public function testEventOfOtherDatapointDoesNotReadScript(): void
    {
        FakeHomematicIO::$Requests = [];
        $Interface = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId);
        $Interface->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => self::Address,
            'VariableName'  => 'POWER',
            'VariableValue' => 55.0
        ]));
        $this->assertEquals(55.0, GetValueFloat(IPS_GetObjectIDByIdent('POWER', $this->PowerMeterId)));
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testInvalidEventIsIgnored(): void
    {
        $Interface = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId);
        $this->assertSame('', $Interface->ReceiveData('{"DeviceID":"x"}'));
        $this->assertSame('', $Interface->ReceiveData('not json'));
    }

    public function testMigrateTakesAddressAndProtocolFromEventVariable(): void
    {
        $Variable = $this->createHmDeviceVariable('OLD0000001:2', 'ENERGY_COUNTER', 2, VARIABLETYPE_FLOAT);
        $Config = $this->migrate(['EventID' => $Variable]);
        $this->assertSame('OLD0000001:2', $Config['Address']);
        $this->assertSame(2, $Config['Protocol']);
    }

    public function testMigrateKeepsConfigurationWithoutValidEvent(): void
    {
        $this->assertArrayNotHasKey('Address', $this->migrate(['EventID' => 0]));
        $this->assertArrayNotHasKey('Address', $this->migrate(['EventID' => 99999]));
        $this->assertSame('', $this->migrate(['Address' => ''])['Address']);
    }

    public function testMigrateIgnoresVariableOfOtherModule(): void
    {
        $Variable = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        IPS_SetParent($Variable, $this->PowerMeterId);
        $Config = $this->migrate(['EventID' => $Variable]);
        $this->assertArrayNotHasKey('Address', $Config);
    }

    public function testMigrateIgnoresInvalidData(): void
    {
        $Interface = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId);
        $this->assertSame('{}', $Interface->Migrate('{}'));
        $this->assertSame('invalid', $Interface->Migrate('invalid'));
    }

    /**
     * Löst den Timer aus, der nach einem Ereignis die Summenzähler liest.
     */
    private function runTimer(): void
    {
        IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->RequestAction(\HMExtended\Device\Timer::ReadSystemVariables, true);
    }

    private function timerInterval(): int
    {
        $Method = new ReflectionMethod(HomeMaticPowermeter::class, 'GetTimerInterval');
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($this->PowerMeterId), \HMExtended\Device\Timer::ReadSystemVariables);
    }

    /**
     * Legt eine neue Instanz an, nachdem die Antwort der CCU auf das HM-Script geändert wurde.
     */
    private function recreateDevice(string $ScriptResponse): void
    {
        FakeHomematicIO::$ScriptResponse = $ScriptResponse;
        $this->PowerMeterId = $this->createDevice(HomeMaticPowermeter::class, \HMExtended\GUID::Powermeter, 'HMPM2', $this->IoId);
        IPS_SetProperty($this->PowerMeterId, \HMExtended\Device\Property::Address, self::Address);
        IPS_ApplyChanges($this->PowerMeterId);
    }

    private function migrate(array $Configuration): array
    {
        $Result = IPS\InstanceManager::getInstanceInterface($this->PowerMeterId)->Migrate(json_encode(['configuration' => $Configuration]));
        return json_decode($Result, true)['configuration'];
    }
}
