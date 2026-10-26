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

    public function testUnitsGetProfiles(): void
    {
        $Expected = ['CURRENT' => '~Milliampere.HM', 'FREQUENCY' => '~Hertz', 'VOLTAGE' => '~Volt', 'POWER' => '~Watt', 'ENERGY_COUNTER' => '~Electricity.Wh'];
        foreach ($Expected as $Ident => $Profile) {
            $this->assertSame($Profile, IPS_GetVariable(IPS_GetObjectIDByIdent($Ident, $this->PowerMeterId))['VariableProfile'], $Ident);
        }
    }

    public function testVariablesAreCreated(): void
    {
        foreach (['ENERGY_COUNTER', 'GAS_ENERGY_COUNTER', 'POWER', 'BOOT'] as $Ident) {
            $this->assertNotFalse(@IPS_GetObjectIDByIdent($Ident, $this->PowerMeterId), $Ident);
        }
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('GAS_ENERGY_COUNTER_TOTAL', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('GAS_VOLUME', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('GAS_VOLUME_TOTAL', $this->PowerMeterId));
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
        $this->assertEquals(3.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
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
        $this->assertEquals(2035.5, GetValueFloat(IPS_GetObjectIDByIdent('GAS_VOLUME', $this->PowerMeterId)));
        $this->assertEquals(2035.5, GetValueFloat(IPS_GetObjectIDByIdent('GAS_VOLUME_TOTAL', $this->PowerMeterId)));
    }

    public function testUnknownTypeIsSkipped(): void
    {
        $this->assertFalse(@IPS_GetObjectIDByIdent('WEIRD_TYPE', $this->PowerMeterId));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('WEIRD_UNIT', $this->PowerMeterId));
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
        $this->assertEquals(1600.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER', $this->PowerMeterId)));
        $this->assertEquals(7000.0, GetValueFloat(IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->PowerMeterId)));
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
