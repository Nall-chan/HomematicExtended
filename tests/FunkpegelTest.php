<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../Funkpegel/module.php';

/**
 * Modul Funkpegel: Korrektur der Rohwerte, Zusammenführen von ReGa (HmIP), rssiInfo/listDevices/listBidcosInterfaces (BidCos-RF),
 * Lesen über den Socket und Kacheldaten. Werte nach einer echten Ausgabe der CCU (2026-11-19), Adressen anonymisiert.
 */
class FunkpegelTest extends HomematicModuleTestCase
{
    private const Xml = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>';
    private const Script = "@@2026-11-19 19:50:00\r\n" .
        "IEQ0000001\tBidCos-RF\tHM-TC-IT-WM-W-EU\tWandthermostat\t\t\t\t\t\r\n" .
        "JEQ0000002\tBidCos-RF\tHM-LC-Bl1-FM\tRollladen\t\t\t\t\t\r\n" .
        "BidCoS-RF\tBidCos-RF\tHM-RCV-50\tHM-RCV-50 BidCoS-RF\t\t\t\t\t\r\n" .
        "0000000000000A\tHmIP-RF\tHmIP-SRH\tTim Fenster\t-81\t0\t2026-11-19 18:39:18\t\t\r\n" .
        "0000000000000B\tHmIP-RF\tHmIP-eTRV-C\tHeizung Bad\t-39\t-46\t2026-11-19 19:09:26\t\t\r\n" .
        "0000000000000C\tHmIP-RF\tHmIP-RCV-50\tHmIP-RCV-50\t\t\t\t\t\r\n" .
        "3014F711A0000000000000\tHmIP-RF\tRPI-RF-MOD\tRPI-RF-MOD\t\t\t\t8.0\t1.5\r\n";
    private const RssiInfo = [
        'IEQ0000001' => ['QEQ0000009' => [-73, -83], 'MEQ0000008' => [65536, -60], 'JEQ0000002' => [-50, -52]],
        'JEQ0000002' => ['IEQ0000001' => [-52, -50]],
        'QEQ0000009' => ['IEQ0000001' => [-83, -73]]
    ];
    private const RfDevices = [
        ['ADDRESS' => 'IEQ0000001', 'PARENT' => '', 'INTERFACE' => 'QEQ0000009', 'TYPE' => 'HM-TC-IT-WM-W-EU', 'ROAMING' => 1],
        ['ADDRESS' => 'IEQ0000001:1', 'PARENT' => 'IEQ0000001', 'INTERFACE' => 'QEQ0000009', 'TYPE' => 'WEATHER_TRANSMIT'],
        ['ADDRESS' => 'JEQ0000002', 'PARENT' => '', 'INTERFACE' => 'KEQ0000007', 'TYPE' => 'HM-LC-Bl1-FM']
    ];
    private const RfInterfaces = [
        ['ADDRESS' => 'MEQ0000008', 'CONNECTED' => true, 'DEFAULT' => false, 'DESCRIPTION' => '', 'DUTY_CYCLE' => 1, 'TYPE' => 'HMLGW2'],
        ['ADDRESS' => 'QEQ0000009', 'CONNECTED' => true, 'DEFAULT' => true, 'DESCRIPTION' => 'CCU2-Coprocessor', 'DUTY_CYCLE' => 6, 'TYPE' => 'CCU2']
    ];
    private const IpInterfaces = [
        ['ADDRESS' => '3014F711A0000000000000AB', 'CONNECTED' => true, 'DEFAULT' => true, 'DESCRIPTION' => 'HMIP_CCU2', 'DUTY_CYCLE' => 7, 'TYPE' => 'HMIP_CCU2']
    ];

    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        date_default_timezone_set('Europe/Berlin');
    }

    public function testFixLevel(): void
    {
        $this->assertNull(\HMExtended\RadioLevels::FixLevel(65536));
        $this->assertNull(\HMExtended\RadioLevels::FixLevel(0));
        $this->assertNull(\HMExtended\RadioLevels::FixLevel(-128));
        $this->assertNull(\HMExtended\RadioLevels::FixLevel(256));
        $this->assertNull(\HMExtended\RadioLevels::FixLevel(''));
        $this->assertSame(-73, \HMExtended\RadioLevels::FixLevel(-73));
        $this->assertSame(-73, \HMExtended\RadioLevels::FixLevel('-73'));
        $this->assertSame(-60, \HMExtended\RadioLevels::FixLevel(60), 'fehlendes Vorzeichen');
        $this->assertSame(-56, \HMExtended\RadioLevels::FixLevel(200), 'vorzeichenlos gespeichert');
    }

    public function testQuality(): void
    {
        $this->assertSame('good', \HMExtended\RadioLevels::Quality(-75, -75, -85));
        $this->assertSame('medium', \HMExtended\RadioLevels::Quality(-85, -75, -85));
        $this->assertSame('weak', \HMExtended\RadioLevels::Quality(-86, -75, -85));
        $this->assertSame('unknown', \HMExtended\RadioLevels::Quality(null, -75, -85));
    }

    public function testBuild(): void
    {
        $Script = \HMExtended\RadioLevels::ParseScript(self::Script, 'Europe/Berlin');
        $Result = \HMExtended\RadioLevels::Build(
            $Script['Devices'],
            self::RssiInfo,
            self::RfDevices,
            ['BidCos-RF' => self::RfInterfaces, 'HmIP-RF' => self::IpInterfaces],
            -75,
            -85
        );
        $Devices = array_column($Result['devices'], null, 'address');
        // Virtuelle Fernbedienungen und das Funkmodul der CCU sind keine Geräte der Liste
        $this->assertSame(['IEQ0000001', '0000000000000A', '0000000000000B', 'JEQ0000002'], array_keys($Devices), 'schwächster zuerst, unbekannt zuletzt');
        // BidCos-RF: Pegel zum zugeordneten Gateway, Paare zwischen Geräten zählen nicht
        $this->assertSame('QEQ0000009', $Devices['IEQ0000001']['gateway']);
        $this->assertSame(-73, $Devices['IEQ0000001']['device']);
        $this->assertSame(-83, $Devices['IEQ0000001']['peer']);
        $this->assertSame(-83, $Devices['IEQ0000001']['level']);
        $this->assertSame('medium', $Devices['IEQ0000001']['quality']);
        $this->assertSame(['QEQ0000009', 'MEQ0000008'], array_column($Devices['IEQ0000001']['links'], 'gateway'));
        // Gerät an einem Gateway, das nicht verbunden ist, ohne Pegel
        $this->assertSame('KEQ0000007', $Devices['JEQ0000002']['gateway']);
        $this->assertSame('unknown', $Devices['JEQ0000002']['quality']);
        $this->assertTrue($Devices['IEQ0000001']['roaming']);
        $this->assertFalse($Devices['JEQ0000002']['roaming']);
        $this->assertFalse($Devices['0000000000000A']['roaming'], 'HmIP ohne Roaming');
        // HmIP: RSSI_PEER 0 = unbekannt
        $this->assertSame(-81, $Devices['0000000000000A']['device']);
        $this->assertNull($Devices['0000000000000A']['peer']);
        $this->assertSame('medium', $Devices['0000000000000A']['quality']);
        $this->assertSame(mktime(18, 39, 18, 11, 19, 2026), $Devices['0000000000000A']['time']);
        $this->assertSame('good', $Devices['0000000000000B']['quality']);
        $Gateways = array_column($Result['gateways'], null, 'address');
        $this->assertSame(6.0, $Gateways['QEQ0000009']['dutyCycle']);
        $this->assertSame(1, $Gateways['QEQ0000009']['devices']);
        $this->assertFalse($Gateways['KEQ0000007']['connected'], 'aus listDevices, nicht in listBidcosInterfaces');
        $this->assertSame(1.5, $Gateways['3014F711A0000000000000AB']['carrierSense'], 'Carrier Sense vom Funkmodul der CCU');
    }

    /**
     * Namen der Gateways aus rfd.conf (WebUI); Gateways weder in rfd.conf noch in listBidcosInterfaces sind nicht mehr eingerichtet.
     */
    public function testGatewayNamesFromRfdConf(): void
    {
        $Script = \HMExtended\RadioLevels::ParseScript(self::Script, 'Europe/Berlin');
        $Configured = \HMExtended\ScriptParser::ParseGatewayConfig("@@rfd\n[Interface 0]\nType = CCU2\nDescription = CCU2-Coprocessor\n[Interface 1]\nType = HMLGW2\nName = Schlafzimmer\nSerial Number = MEQ0000008\n[Interface 2]\nType = Lan Interface\nName = Keller\nSerial Number = NEQ0000006\n")['rfd'];
        $Result = \HMExtended\RadioLevels::Build(
            $Script['Devices'],
            self::RssiInfo,
            self::RfDevices,
            ['BidCos-RF' => self::RfInterfaces, 'HmIP-RF' => self::IpInterfaces],
            -75,
            -85,
            $Configured
        );
        $Gateways = array_column($Result['gateways'], null, 'address');
        $this->assertSame('Schlafzimmer', $Gateways['MEQ0000008']['name']);
        $this->assertTrue($Gateways['MEQ0000008']['connected']);
        $this->assertSame('CCU2-Coprocessor', $Gateways['QEQ0000009']['name'], 'ohne Seriennummer bleibt die Beschreibung');
        $this->assertSame('Keller', $Gateways['NEQ0000006']['name']);
        $this->assertFalse($Gateways['NEQ0000006']['connected'], 'eingerichtet, aber nicht verbunden');
        $this->assertTrue($Gateways['NEQ0000006']['configured']);
        $this->assertFalse($Gateways['KEQ0000007']['configured'], 'nur noch in listDevices: nicht mehr eingerichtet');
        $this->assertSame('', $Gateways['3014F711A0000000000000AB']['name'], 'HMIP_CCU2 <Seriennummer> ist kein Name');
    }

    public function testReadOnApplyAndFunction(): void
    {
        $Id = $this->createInstance();
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($Id)['InstanceStatus']);
        $this->assertSame([], IPS_GetChildrenIDs($Id), 'keine Statusvariablen');
        $Methods = array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['MethodName'])), 'MethodName');
        $this->assertSame(['rssiInfo', 'listDevices', 'listBidcosInterfaces', 'listBidcosInterfaces'], $Methods);
        $Result = IPS\InstanceManager::getInstanceInterface($Id)->GetRSSIValues();
        $this->assertCount(4, $Result['devices']);
        $this->assertSame(1795114200, $Result['time']);
        $this->assertSame(0, $Result['devices'][0]['instanceID']);
        $this->assertSame(300000, $this->timerInterval($Id));
    }

    /**
     * Die Symcon-Instanz wird über die Adresse gefunden (das Feld `device` ist hier der Pegel, keine Adresse).
     */
    public function testInstanceOfDeviceIsAssigned(): void
    {
        $Device = $this->createDevice(FakeHmDevice::class, \HMExtended\GUID::HmDevice, 'HMD', $this->IoId);
        IPS_SetProperty($Device, \HMExtended\Device\Property::Address, 'IEQ0000001:1');
        IPS_ApplyChanges($Device);
        IPS_SetName($Device, 'Wandthermostat Flur');
        $Id = $this->createInstance();
        $Devices = array_column(IPS\InstanceManager::getInstanceInterface($Id)->GetRSSIValues()['devices'], null, 'address');
        $this->assertSame($Device, $Devices['IEQ0000001']['instanceID']);
        $this->assertSame('Wandthermostat Flur', $Devices['IEQ0000001']['instanceName']);
        $this->assertSame(0, $Devices['JEQ0000002']['instanceID']);
    }

    public function testWithoutBidCosOnlyHmIP(): void
    {
        IPS_SetProperty($this->IoId, 'RFOpen', false);
        IPS_ApplyChanges($this->IoId);
        $Id = $this->createInstance();
        $Methods = array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['MethodName'])), 'MethodName');
        $this->assertSame(['listBidcosInterfaces'], $Methods);
        $Devices = IPS\InstanceManager::getInstanceInterface($Id)->GetRSSIValues()['devices'];
        // BidCos-Geräte aus der ReGa bleiben, aber ohne Pegel
        $this->assertSame('unknown', array_column($Devices, null, 'address')['IEQ0000001']['quality']);
    }

    public function testInvalidConfiguration(): void
    {
        $Id = $this->createInstance(-90, -85);
        $this->assertSame(IS_EBASE + 5, IPS_GetInstance($Id)['InstanceStatus']);
        $Id = $this->createInstance(-75, -85, 30);
        $this->assertSame(IS_EBASE + 3, IPS_GetInstance($Id)['InstanceStatus']);
    }

    public function testTileReloadReadsOnlyOldData(): void
    {
        $Id = $this->createInstance();
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        FakeHomematicIO::$Requests = [];
        $Interface->RequestAction('TileReload', true);
        $this->assertCount(0, FakeHomematicIO::$Requests, 'frische Daten');
        $Interface->LastRead = time() - 61;
        $Interface->RequestAction('TileReload', true);
        $this->assertNotCount(0, FakeHomematicIO::$Requests);
    }

    public function testTileData(): void
    {
        $Id = $this->createInstance();
        $Html = IPS\InstanceManager::getInstanceInterface($Id)->GetVisualizationTile();
        preg_match('/window\.HMT_CONFIG = (\{.*?\});<\/script>/s', $Html, $Match);
        $Config = json_decode($Match[1], true);
        $this->assertCount(4, $Config['data']['devices']);
        $this->assertCount(4, $Config['data']['gateways']);
        $this->assertSame(-75, $Config['data']['good']);
        $this->assertStringContainsString('HMT.render = render;', $Html);
    }

    /**
     * Jeder Text der Kachel (T('…') im Skript, Qualitäten) steht in GetTileTextKeys und ist übersetzt.
     */
    public function testTileTextsAreTranslated(): void
    {
        $Locale = json_decode(file_get_contents(__DIR__ . '/../Funkpegel/locale.json'), true)['translations']['de'];
        $Method = new ReflectionMethod(HomeMaticFunkpegel::class, 'GetTileTextKeys');
        $Id = $this->createInstance();
        $Keys = array_merge(['Entries', 'All', 'Cancel', 'OK', 'Search'], $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id)));
        foreach ($Keys as $Key) {
            $this->assertArrayHasKey($Key, $Locale, $Key);
        }
        $Script = file_get_contents(__DIR__ . '/../Funkpegel/tile.js') . file_get_contents(__DIR__ . '/../libs/tile/tile.js');
        preg_match_all("/\\bT\\('([^']+)'\\)/", $Script, $Matches);
        preg_match_all("/\\['[a-z]+', '([A-Z][A-Za-z ]+)'\\]/", $Script, $Groups);
        foreach (array_unique(array_merge($Matches[1], $Groups[1])) as $Text) {
            $this->assertContains($Text, $Keys, $Text);
        }
        foreach (array_keys($Locale) as $Key) {
            $this->assertNotSame('', $Locale[$Key], $Key);
        }
    }

    private function createInstance(int $Good = -75, int $Weak = -85, int $Interval = 300): int
    {
        IPS_SetProperty($this->IoId, 'IPOpen', true);
        IPS_ApplyChanges($this->IoId);
        FakeHomematicIO::$RpcResponses = [
            'rssiInfo'               => self::RssiInfo,
            'listDevices'            => self::RfDevices,
            'listBidcosInterfaces@0' => self::RfInterfaces,
            'listBidcosInterfaces@2' => self::IpInterfaces
        ];
        FakeHomematicIO::$ScriptResponse = self::Script . self::Xml;
        $Id = $this->createDevice(HomeMaticFunkpegel::class, \HMExtended\GUID::Funkpegel, 'HMFP', $this->IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::LevelGood, $Good);
        IPS_SetProperty($Id, \HMExtended\Device\Property::LevelWeak, $Weak);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Interval, $Interval);
        FakeHomematicIO::$Requests = [];
        IPS_ApplyChanges($Id);
        return $Id;
    }

    private function timerInterval(int $Id): int
    {
        $Method = new ReflectionMethod(HomeMaticFunkpegel::class, 'GetTimerInterval');
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id), \HMExtended\Device\Timer::ReadRadioLevels);
    }
}
