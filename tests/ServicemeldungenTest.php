<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../Servicemeldungen/module.php';

/**
 * Modul Servicemeldungen: Lesen über ReGa (ID_SERVICES), Bestätigen, CCU-Programm auf der Systemvariable 41 und Kacheldaten.
 * Die Zeilen entsprechen einer echten Ausgabe der CCU (2026-11-19), Adressen anonymisiert.
 */
class ServicemeldungenTest extends HomematicModuleTestCase
{
    private const Xml = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>';
    private const Key = 'BidCoS-RF:50';
    private const Output = "@@2026-11-19 19:17:27\r\n" .
        "27148\tLEQ0000001:4\tFAULT_REPORTING\t2\tADJUSTING_RANGE_TOO_LARGE\tBidCos-RF\tHM-CC-RT-DN\tTest1\tHM-CC-RT-DN LEQ0000001:4\tDachboden\t\t2026-11-08 11:02:23\t2026-11-19 19:08:04\t0\tAL-LEQ0000001:4.FAULT_REPORTING-2\r\n" .
        "25807\t0000000000000A:0\tUNREACH\ttrue\t\tHmIP-RF\tHmIP-SRH\tTim Fenster:0\tTim Fenster:0:0\t\t\t2026-11-11 14:32:57\t2026-11-11 14:32:57\t0\tAL-0000000000000A:0.UNREACH\r\n" .
        "30111\tMEQ0000002:0\tSTICKY_UNREACH\ttrue\t\tBidCos-Wired\tHMW-IO-12-Sw7-DR\tKeller\tKeller:0\tKeller, Flur\tLicht\t2026-11-12 08:00:00\t2026-11-12 08:00:00\t1\tAL-MEQ0000002:0.STICKY_UNREACH\r\n" .
        "30200\t\t\ttrue\t\t\t\t\t\t\t\t2026-11-13 08:00:00\t2026-11-13 08:00:00\t0\tAL-BidCoS-RF.U_SOURCE_FAIL\r\n";

    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        date_default_timezone_set('Europe/Berlin');
    }

    public function testParse(): void
    {
        $Result = \HMExtended\ServiceMessages::Parse(self::Output, 'Europe/Berlin');
        $this->assertSame(1795112247, $Result['Now']);
        $Messages = array_column($Result['Messages'], null, 'id');
        $this->assertSame([27148, 30200, 30111, 25807], array_keys($Messages), 'neueste Meldung zuerst');
        $this->assertSame(2, $Messages[27148]['value']);
        $this->assertSame('ADJUSTING_RANGE_TOO_LARGE', $Messages[27148]['text']);
        $this->assertSame('other', $Messages[27148]['group']);
        $this->assertSame(4, $Messages[27148]['channel']);
        $this->assertSame('Dachboden', $Messages[27148]['rooms']);
        $this->assertSame('unreach', $Messages[25807]['group']);
        $this->assertTrue($Messages[25807]['value']);
        $this->assertFalse($Messages[25807]['confirmable']);
        $this->assertTrue($Messages[30111]['confirmable']);
        $this->assertSame('BidCos-Wired', $Messages[30111]['interface']);
        $this->assertSame('Keller, Flur', $Messages[30111]['rooms']);
        // Meldung des Systems ohne Kanal: Adresse und Datenpunkt aus dem Namen des Alarms
        $this->assertSame('BidCoS-RF', $Messages[30200]['address']);
        $this->assertSame('U_SOURCE_FAIL', $Messages[30200]['datapoint']);
        $this->assertSame('battery', $Messages[30200]['group']);
        $this->assertSame(-1, $Messages[30200]['channel']);
    }

    /**
     * Sabotage als eigene Art: Datenpunkte SABOTAGE/STICKY_SABOTAGE (HmIP) und Aufzählungen mit dem Wert SABOTAGE (BidCos, z.B. HM-Sec-SCo ERROR = 7).
     */
    public function testSabotageGroup(): void
    {
        $Output = "@@2026-11-19 20:44:00\r\n" .
            "31000\tLEQ0000003:1\tERROR\t7\tSABOTAGE\tBidCos-RF\tHM-Sec-SCo\tFenster\tFenster:1\tSchlafzimmer\t\t2026-11-19 20:44:00\t2026-11-19 20:44:00\t0\tAL-LEQ0000003:1.ERROR\r\n" .
            "31001\t0000000000000D:0\tSABOTAGE\ttrue\t\tHmIP-RF\tHmIP-SWDO\tTür\tTür:0\t\t\t2026-11-19 20:40:00\t2026-11-19 20:40:00\t0\tAL-0000000000000D:0.SABOTAGE\r\n" .
            "31002\tLEQ0000004:4\tFAULT_REPORTING\t2\tADJUSTING_RANGE_TOO_LARGE\tBidCos-RF\tHM-CC-RT-DN\tHeizung\tHeizung:4\t\t\t2026-11-19 20:30:00\t2026-11-19 20:30:00\t0\tAL-LEQ0000004:4.FAULT_REPORTING-2\r\n";
        $Groups = array_column(\HMExtended\ServiceMessages::Parse($Output, 'Europe/Berlin')['Messages'], 'group', 'id');
        $this->assertSame([31000 => 'sabotage', 31001 => 'sabotage', 31002 => 'other'], $Groups);
    }

    public function testScriptReadsAllInterfacesFromReGa(): void
    {
        $Script = \HMExtended\ServiceMessages::Script();
        $this->assertStringContainsString('dom.GetObject(ID_SERVICES).EnumIDs()', $Script);
        $this->assertStringContainsString('oA.IsTypeOf(OT_ALARMDP) && (oA.AlState() == asOncoming)', $Script);
        $this->assertStringContainsString('if (oDP.Operations() & 2)', $Script);
    }

    public function testMessagesAreReadOnApplyAndReturned(): void
    {
        $Id = $this->createInstance();
        $Scripts = $this->scripts();
        $this->assertCount(1, $Scripts);
        $this->assertStringContainsString('ID_SERVICES', $Scripts[0]);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($Id)['InstanceStatus']);
        $this->assertSame([], IPS_GetChildrenIDs($Id), 'keine Statusvariablen');

        FakeHomematicIO::$ScriptResponse = $this->ccu(self::Output) . self::Xml;
        $Messages = IPS\InstanceManager::getInstanceInterface($Id)->GetServiceMessages();
        $this->assertCount(4, $Messages);
        $this->assertSame(0, $Messages[0]['instanceID']);
    }

    public function testInstanceOfDeviceIsAssigned(): void
    {
        $Device = $this->createDevice(FakeHmDevice::class, \HMExtended\GUID::HmDevice, 'HMD', $this->IoId);
        IPS_SetProperty($Device, \HMExtended\Device\Property::Address, '0000000000000A:1');
        IPS_ApplyChanges($Device);
        IPS_SetName($Device, 'Fenster Tim');
        $Id = $this->createInstance();
        $Messages = array_column(IPS\InstanceManager::getInstanceInterface($Id)->GetServiceMessages(), null, 'id');
        $this->assertSame($Device, $Messages[25807]['instanceID']);
        $this->assertSame('Fenster Tim', $Messages[25807]['instanceName']);
    }

    /**
     * Ereignisse der Taste lesen höchstens alle 30 s (die CCU aktualisiert die Systemvariable 41 bei jeder erneuten Meldung).
     */
    public function testEventReadingIsThrottled(): void
    {
        $Id = $this->createInstance();
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        $Event = json_encode(['DataID' => \HMExtended\GUID::HmDevice, 'DeviceID' => self::Key, 'VariableName' => 'PRESS_SHORT', 'VariableValue' => true]);
        // Gerade gelesen (ApplyChanges): erst 30 s nach dem letzten Lesen
        $Interface->ReceiveData($Event);
        $this->assertGreaterThanOrEqual(29000, $this->timerInterval($Id));
        $this->assertLessThanOrEqual(30000, $this->timerInterval($Id));
        // Letztes Lesen länger als 30 s her: sofort (nach der kurzen Sammelverzögerung)
        $Interface->LastRead = time() - 31;
        $Interface->ReceiveData($Event);
        $this->assertSame(250, $this->timerInterval($Id));
        // Andere Datenpunkte der Taste lösen nichts aus
        $Interface->LastRead = time();
        $Interface->ReceiveData(json_encode(['DataID' => \HMExtended\GUID::HmDevice, 'DeviceID' => self::Key, 'VariableName' => 'PRESS_LONG', 'VariableValue' => true]));
        $this->assertSame(250, $this->timerInterval($Id));
    }

    /**
     * Manuelles Lesen ist nicht gedrosselt.
     */
    public function testManualReadIsNotThrottled(): void
    {
        $Id = $this->createInstance();
        FakeHomematicIO::$Requests = [];
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        $Interface->RequestAction('TileRead', true);
        $Interface->GetServiceMessages();
        $this->assertCount(2, $this->scripts());
    }

    public function testConfirm(): void
    {
        $Id = $this->createInstance();
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = ["30111\t1\r\n" . self::Xml, $this->ccu(self::Output) . self::Xml];
        $this->assertTrue($Interface->ConfirmServiceMessage(30111));
        $Scripts = $this->scripts();
        $this->assertStringContainsString('oA = dom.GetObject(30111);', $Scripts[0]);
        $this->assertStringContainsString('oA.AlReceipt();', $Scripts[0]);
        $this->assertStringContainsString('ID_SERVICES', $Scripts[1], 'nach dem Bestätigen neu lesen');

        FakeHomematicIO::$ScriptResponses = ["25807\t0\r\n" . self::Xml];
        $this->assertFalse($Interface->ConfirmServiceMessage(25807));
        $this->expectOutputString('This service message cannot be confirmed.' . PHP_EOL);
    }

    public function testConfirmAllOnlySendsConfirmableMessages(): void
    {
        $Id = $this->createInstance();
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = [$this->ccu(self::Output) . self::Xml, "30111\t1\r\n" . self::Xml, $this->ccu(self::Output) . self::Xml];
        $this->assertSame(1, IPS\InstanceManager::getInstanceInterface($Id)->ConfirmAllServiceMessages());
        $Confirm = $this->scripts()[1];
        $this->assertStringContainsString('dom.GetObject(30111)', $Confirm);
        $this->assertStringNotContainsString('dom.GetObject(25807)', $Confirm);
    }

    public function testProgramUsesServiceMessageCounter(): void
    {
        $Id = $this->createInstance();
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = ["K\tBidCoS-RF:50\tHM-RCV-50 BidCoS-RF:50\t0\r\n" . self::Xml, "@@29800\r\n" . self::Xml];
        $this->assertSame('CCU program "Symcon Servicemeldungen ' . $Id . '" created.', IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
        $Script = $this->scripts()[1];
        // Systemvariable 41 (Integer): „= 0“ ODER „≠ 0“, beide bei Aktualisierung
        $this->assertSame(2, substr_count($Script, 'oSC.LeftVal(41); oSC.RightVal1(0);'));
    }

    public function testTileData(): void
    {
        $Id = $this->createInstance();
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        $Html = $Interface->GetVisualizationTile();
        $this->assertStringContainsString('window.HMT_CONFIG = {', $Html);
        $this->assertStringNotContainsString('/*CORE*/', $Html);
        $this->assertStringNotContainsString('/*MODULE*/', $Html);
        $this->assertStringContainsString('HMT.render', $Html);
        preg_match('/window\.HMT_CONFIG = (\{.*?\});<\/script>/s', $Html, $Match);
        $Config = json_decode($Match[1], true);
        $this->assertSame($Id, $Config['instance']);
        $this->assertCount(4, $Config['data']['messages']);
        $this->assertSame(1795112247, $Config['data']['time']);
        $this->assertArrayHasKey('UNREACH', $Config['texts']);
    }

    /**
     * Jeder Text der Kachel (T('…') im Skript, Gruppen, Meldungen) steht in GetTileTextKeys und ist übersetzt.
     */
    public function testTileTextsAreTranslated(): void
    {
        $Locale = json_decode(file_get_contents(__DIR__ . '/../Servicemeldungen/locale.json'), true)['translations']['de'];
        $Method = new ReflectionMethod(HomeMaticServicemeldungen::class, 'GetTileTextKeys');
        $Id = $this->createInstance();
        $Keys = array_merge(['Entries', 'All', 'Cancel', 'OK', 'Search'], $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id)));
        foreach ($Keys as $Key) {
            $this->assertArrayHasKey($Key, $Locale, $Key);
        }
        $Script = file_get_contents(__DIR__ . '/../Servicemeldungen/tile.js') . file_get_contents(__DIR__ . '/../libs/tile/tile.js');
        preg_match_all("/\\bT\\('([^']+)'\\)/", $Script, $Matches);
        preg_match_all("/\\['[a-z]+', '([A-Z][A-Za-z ]+)'\\]/", $Script, $Groups);
        foreach (array_unique(array_merge($Matches[1], $Groups[1])) as $Text) {
            $this->assertContains($Text, $Keys, $Text);
        }
        foreach (array_keys(\HMExtended\ServiceMessages::Groups) as $Datapoint) {
            $this->assertContains($Datapoint, $Keys, $Datapoint);
        }
    }

    private function createInstance(): int
    {
        $Id = $this->createDevice(HomeMaticServicemeldungen::class, \HMExtended\GUID::Servicemeldungen, 'HMSM', $this->IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, self::Key);
        FakeHomematicIO::$ScriptResponse = $this->ccu(self::Output) . self::Xml;
        FakeHomematicIO::$Requests = [];
        IPS_ApplyChanges($Id);
        return $Id;
    }

    /**
     * Die CCU antwortet in ISO-8859-1.
     */
    private function ccu(string $Output): string
    {
        return mb_convert_encoding($Output, 'ISO-8859-1', 'UTF-8');
    }

    private function scripts(): array
    {
        return array_values(array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['Content'])), 'Content'));
    }

    private function timerInterval(int $Id): int
    {
        $Method = new ReflectionMethod(HomeMaticServicemeldungen::class, 'GetTimerInterval');
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id), \HMExtended\Device\Timer::ReadSystemVariables);
    }
}
