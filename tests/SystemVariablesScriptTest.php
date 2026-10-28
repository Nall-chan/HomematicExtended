<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/HMTypes.php';
require_once __DIR__ . '/../libs/HMSystemVariables.php';

use HMExtended\SystemVariables;
use PHPUnit\Framework\TestCase;

/**
 * Prüft die HM-Scripte und Parser für Systemvariablen, Alarmvariablen und das CCU-Programm.
 * Die Ausgaben stammen von einer CCU (RaspberryMatic), Seriennummern sind verfremdet.
 */
class SystemVariablesScriptTest extends TestCase
{
    private const ListOutput = "40\tAlarmmeldungen\t1089\t16\t0\ttrue\t65535\t\t0\t65000\t0\t1\t\r\n" .
        "950\tAnwesenheit\t1089\t2\t2\tfalse\t65535\t\t\t\tabwesend\tanwesend\t\r\n" .
        "27598\tDutyCycle\t1089\t4\t0\tfalse\t65535\t%\t-1\t100\t0\t1\t\r\n" .
        "1253\tExt. Alarm Haus\t2113\t2\t6\tfalse\t65535\t\t\t\tnicht ausgelöst\tausgelöst\t\r\n" .
        "12829\tKellertreppe Licht manuell\t1089\t2\t2\tfalse\t5555\t\t\t\tautomatik\tmanuell\t\r\n" .
        "2514\tRegen gestern\t1089\t4\t0\tfalse\t65535\tmm\t0.000000\t1000.000000\t1\t1\t\r\n" .
        "14901\tServicemeldung automatisch quitiert\t1089\t20\t11\tfalse\t65535\t\t\t\t1\t1\t\r\n" .
        "22678\tsvEnergyCounter_22672_MEQ0000001:1\t1089\t4\t0\ttrue\t65535\t\t0\t1.700000000000001e+308\t0\t1\t\r\n" .
        "22682\tsvEnergyCounter_22672_MEQ0000001:1_DEVICE_RESET\t1089\t2\t2\ttrue\t65535\t\t\t\tfalse\ttrue\t\r\n" .
        "27852\tWatchDog: chronyd-highoffset\t2113\t2\t6\ttrue\t65535\t\t\t\tnicht ausgelöst\tausgelöst\t\r\n" .
        "1987\tZeitPRG\t1089\t2\t2\tfalse\t65535\t\t\t\tTag\tNacht\t\r\n" .
        "3000\tModus\t1089\t16\t29\tfalse\t65535\t\t0\t2\t0\t1\tAus;Tag; Nacht\r\n" .
        "3001\tMehrzeilig\\tmit Tab\t1089\t20\t11\tfalse\t65535\t\t\t\t1\t1\t\r\n";

    private const ReadOutput = "@@2026-10-28 08:19:58\r\n" .
        "27598\t4\t2026-10-28 08:19:01\t9.000000\r\n" .
        "41\t16\t2026-10-28 08:19:52\t10\r\n" .
        "14901\t20\t2026-10-28 08:02:35\tGerät:BWM Flur:0 - Objekt: AL-HEQ0000002:0.STICKY_UNREACH\\nZeile 2\\t\\\\ Ende\r\n" .
        "29612\t2\t2026-10-28 08:10:24\ttrue\r\n" .
        "99999\t-\r\n";

    public function testParseListReadsAllColumns(): void
    {
        $List = SystemVariables::ParseList(self::ListOutput);
        $this->assertCount(13, $List);
        $this->assertSame([
            'Name'      => 'Anwesenheit',
            'Type'      => 1089,
            'ValueType' => 2,
            'SubType'   => 2,
            'Internal'  => false,
            'Channel'   => 65535,
            'Unit'      => '',
            'Min'       => 0.0,
            'Max'       => 0.0,
            'Name0'     => 'abwesend',
            'Name1'     => 'anwesend',
            'List'      => ''
        ], $List[950]);
        $this->assertSame('%', $List[27598]['Unit']);
        $this->assertSame(-1.0, $List[27598]['Min']);
        $this->assertTrue($List[40]['Internal']);
        $this->assertSame(5555, $List[12829]['Channel']);
        $this->assertSame('Aus;Tag; Nacht', $List[3000]['List']);
        $this->assertSame("Mehrzeilig\tmit Tab", $List[3001]['Name']);
    }

    public function testSupportedVariablesExcludeAlarmsAndCounters(): void
    {
        $List = SystemVariables::ParseList(self::ListOutput);
        $Supported = array_keys(array_filter($List, [SystemVariables::class, 'IsSupported']));
        sort($Supported);
        $this->assertSame([40, 950, 1987, 2514, 3000, 3001, 12829, 14901, 27598], $Supported);
        $this->assertTrue(SystemVariables::IsAlarm($List[1253]));
        $this->assertTrue(SystemVariables::IsAlarm($List[27852]));
        $this->assertFalse(SystemVariables::IsAlarm($List[950]));
    }

    public function testParseReadConvertsValuesAndReportsMissing(): void
    {
        $Result = SystemVariables::ParseRead(self::ReadOutput);
        $this->assertSame('2026-10-28 08:19:58', $Result['Now']);
        $this->assertSame([99999], $Result['Missing']);
        $this->assertSame(9.0, $Result['Values'][27598]['Value']);
        $this->assertSame(10, $Result['Values'][41]['Value']);
        $this->assertTrue($Result['Values'][29612]['Value']);
        $this->assertSame('2026-10-28 08:10:24', $Result['Values'][29612]['Timestamp']);
        $this->assertSame("Gerät:BWM Flur:0 - Objekt: AL-HEQ0000002:0.STICKY_UNREACH\nZeile 2\t\\ Ende", $Result['Values'][14901]['Value']);
    }

    public function testReadScriptContainsIdsAndSince(): void
    {
        $Script = SystemVariables::ReadScript([950, 27598], '2026-10-28 08:00:00');
        $this->assertStringContainsString('foreach (sId, "950\t27598")', $Script);
        $this->assertStringContainsString('string sSince = "2026-10-28 08:00:00";', $Script);
        $this->assertStringContainsString('if (sTs >= sSince)', $Script);
        $this->assertStringContainsString('o.Value().ToString().Replace("\\\\", "\\\\\\\\").Replace("\t", "\\\\t")', $Script);
        $this->assertStringContainsString('string sSince = "";', SystemVariables::ReadScript([1]));
    }

    public function testListScriptUsesAllIds(): void
    {
        // EnumUsedIDs() liefert keine internen Systemvariablen.
        $this->assertStringContainsString('dom.GetObject(ID_SYSTEM_VARIABLES).EnumIDs()', SystemVariables::ListScript());
    }

    public function testWriteScriptLiterals(): void
    {
        $this->assertStringContainsString('o.State(true)', SystemVariables::WriteScript(950, true));
        $this->assertStringContainsString('o.State(2)', SystemVariables::WriteScript(3000, 2));
        $this->assertStringContainsString('o.State(21.500000)', SystemVariables::WriteScript(1, 21.5));
        $this->assertStringContainsString('o.State("a \\"b\\"\\n\\\\")', SystemVariables::WriteScript(1, "a \"b\"\n\\"));
        $this->assertStringContainsString('dom.GetObject(14901)', SystemVariables::WriteScript(14901, 'x'));
    }

    public function testProtocolOfKey(): void
    {
        $this->assertSame(0, SystemVariables::ProtocolOfKey('BidCoS-RF:49'));
        $this->assertSame(2, SystemVariables::ProtocolOfKey('HmIP-RCV-1:1'));
        $this->assertFalse(SystemVariables::ProtocolOfKey('BidCoS-RF:51'));
        $this->assertFalse(SystemVariables::ProtocolOfKey('BidCoS-RF:0'));
        $this->assertFalse(SystemVariables::ProtocolOfKey('XXX9999999:1'));
        $this->assertFalse(SystemVariables::ProtocolOfKey('MEQ0000001:1'));
    }

    public function testKeyList(): void
    {
        $Script = SystemVariables::KeyListScript([0, 2]);
        $this->assertStringContainsString('dom.GetObject("BidCos-RF.BidCoS-RF:" # iKey.ToString() # ".PRESS_SHORT")', $Script);
        $this->assertStringContainsString('dom.GetObject("HmIP-RF.HmIP-RCV-1:" # iKey.ToString() # ".PRESS_SHORT")', $Script);
        // Verwendung in Programmen: Bedingungen, Aktivitäten und die Zweige „Sonst, wenn“ (live geprüft).
        $this->assertStringContainsString('foreach (sP, dom.GetObject(ID_PROGRAMS).EnumIDs())', $Script);
        $this->assertStringContainsString('oC.CndSingleConditions().EnumIDs()', $Script);
        $this->assertStringContainsString('oT.DestSingleDestinations().EnumIDs()', $Script);
        $this->assertStringContainsString('if (oR.RuleSubRule()) { iR = oR.RuleSubRule().ID(); }', $Script);
        $this->assertStringContainsString('oCh.Address().StartsWith("BidCoS-RF:") || oCh.Address().StartsWith("HmIP-RCV-1:")', $Script);
        $this->assertStringNotContainsString('HmIP-RCV-1', SystemVariables::KeyListScript([0]));
        $this->assertStringNotContainsString('ID_PROGRAMS', SystemVariables::KeyListScript([]));
        // Ausgabe einer CCU (RaspberryMatic)
        $List = SystemVariables::ParseKeyList(
            "K\tBidCoS-RF:48\tSystemvariablen an IPS pushen\t0\r\n" .
            "K\tBidCoS-RF:49\tHM-RCV-50 BidCoS-RF:49\t0\r\n" .
            "K\tHmIP-RCV-1:1\tHmIP-RCV-50 HmIP-RCV-1:1\t2\r\n" .
            "U\tBidCoS-RF:50\tA\t13106\tServicemeldungen pushen an IPS\r\n" .
            "U\tBidCoS-RF:48\tA\t10153\tSystemvars zu IPS pushen\r\n" .
            "U\tBidCoS-RF:49\tC\t8643\tDummy f\u{FC}r Tasten\r\n"
        );
        $this->assertSame([
            'Name'        => 'Systemvariablen an IPS pushen',
            'Protocol'    => 0,
            'DefaultName' => false,
            'Conditions'  => [],
            'Activities'  => [10153 => 'Systemvars zu IPS pushen'],
            'Links'       => 0
        ], $List['BidCoS-RF:48']);
        $this->assertTrue($List['BidCoS-RF:49']['DefaultName']);
        $this->assertSame([8643 => 'Dummy für Tasten'], $List['BidCoS-RF:49']['Conditions']);
        $this->assertTrue($List['HmIP-RCV-1:1']['DefaultName']);
        $this->assertSame(2, $List['HmIP-RCV-1:1']['Protocol']);
        $this->assertArrayNotHasKey('BidCoS-RF:50', $List, 'only keys of the listed protocols');
    }

    public function testDefaultKeyName(): void
    {
        $this->assertSame('HM-RCV-50 BidCoS-RF:49', SystemVariables::DefaultKeyName('BidCoS-RF:49'));
        $this->assertSame('HmIP-RCV-50 HmIP-RCV-1:1', SystemVariables::DefaultKeyName('HmIP-RCV-1:1'));
        $this->assertSame('', SystemVariables::DefaultKeyName('XXX9999999:1'));
    }

    public function testCountKeyLinks(): void
    {
        $this->assertSame(['BidCoS-RF:3' => 2, 'BidCoS-RF:4' => 1], SystemVariables::CountKeyLinks([
            ['SENDER' => 'BidCoS-RF:3', 'RECEIVER' => 'LEQ0000001:1'],
            ['SENDER' => 'BidCoS-RF:3', 'RECEIVER' => 'LEQ0000001:2'],
            ['SENDER' => 'LEQ0000002:1', 'RECEIVER' => 'BidCoS-RF:4'],
            ['SENDER' => 'LEQ0000002:1', 'RECEIVER' => 'LEQ0000003:1'],
            'invalid'
        ]));
    }

    public function testProgramScriptRenamesKeyWithFactoryName(): void
    {
        $Script = SystemVariables::ProgramScript('Symcon Systemvariablen 12345', 'Info', 0, '', 'BidCoS-RF:49', [950 => 2]);
        $this->assertStringContainsString('if (oKey) { if (oKey.Name() == "HM-RCV-50 BidCoS-RF:49") { oKey.Name("Symcon Systemvariablen 12345"); } }', $Script);
        $this->assertStringNotContainsString('oOldKey', $Script);
        // Taste gewechselt: die bisherige Taste erhält ihren Werksnamen zurück, wenn sie noch den Namen des Programms trägt.
        $Changed = SystemVariables::ProgramScript('Symcon Systemvariablen 12345', 'Info', 29625, 'BidCoS-RF:48', 'BidCoS-RF:49', [950 => 2]);
        $this->assertStringContainsString('dom.GetObject("BidCos-RF.BidCoS-RF:48.PRESS_SHORT")', $Changed);
        $this->assertStringContainsString('if (oOldKey.Name() == "Symcon Systemvariablen 12345") { oOldKey.Name("HM-RCV-50 BidCoS-RF:48"); }', $Changed);
        $this->assertStringNotContainsString('oOldKey', SystemVariables::ProgramScript('P', 'I', 29625, 'BidCoS-RF:49', 'BidCoS-RF:49', [950 => 2]));
    }

    public function testDeleteProgramRestoresKeyName(): void
    {
        $Script = SystemVariables::DeleteProgramScript(29625, 'Symcon Systemvariablen 12345', true, 'HmIP-RCV-1:2');
        $this->assertStringContainsString('dom.GetObject("HmIP-RF.HmIP-RCV-1:2.PRESS_SHORT")', $Script);
        $this->assertStringContainsString('oOldKey.Name("HmIP-RCV-50 HmIP-RCV-1:2")', $Script);
        $this->assertStringNotContainsString('oOldKey', SystemVariables::DeleteProgramScript(29625, 'x'));
    }

    public function testProgramScriptCoversEveryValue(): void
    {
        $Script = SystemVariables::ProgramScript('Symcon Systemvariablen 12345', 'Info', 0, '', 'BidCoS-RF:49', [950 => 2, 27598 => 4, 14901 => 20, 3000 => 16]);
        $this->assertStringNotContainsString('DeleteObject', $Script);
        $this->assertStringContainsString('dom.GetObject("BidCos-RF.BidCoS-RF:49.PRESS_SHORT")', $Script);
        $this->assertStringContainsString('oPrg.Name("Symcon Systemvariablen 12345");', $Script);
        $this->assertStringContainsString('dom.GetObject(ID_PROGRAMS).Add(oPrg.ID());', $Script);
        $this->assertStringContainsString('oCnd.CndOperatorType(2);', $Script);
        // Bool: = false ODER = true, alle übrigen Typen: = x ODER ≠ x.
        $this->assertStringContainsString('oSC.ConditionType(1); oSC.ConditionType2(13); oSC.LeftValType(19); oSC.ConditionChannel(65535); oSC.LeftVal(950); oSC.RightVal1(false);', $Script);
        $this->assertStringContainsString('oSC.ConditionType(1); oSC.ConditionType2(13); oSC.LeftValType(19); oSC.ConditionChannel(65535); oSC.LeftVal(950); oSC.RightVal1(true);', $Script);
        $this->assertStringContainsString('oSC.ConditionType(12); oSC.ConditionType2(13); oSC.LeftValType(19); oSC.ConditionChannel(65535); oSC.LeftVal(27598); oSC.RightVal1(0.0);', $Script);
        $this->assertStringContainsString('oSC.ConditionType(12); oSC.ConditionType2(13); oSC.LeftValType(19); oSC.ConditionChannel(65535); oSC.LeftVal(14901); oSC.RightVal1("");', $Script);
        $this->assertStringContainsString('oSC.ConditionType(12); oSC.ConditionType2(13); oSC.LeftValType(19); oSC.ConditionChannel(65535); oSC.LeftVal(3000); oSC.RightVal1(0);', $Script);
        $this->assertSame(8, substr_count($Script, 'oCnd.CndAddSingle()'));
        $this->assertStringContainsString('oSD.DestinationParam(18); oSD.DestinationChannel(oDP.Channel()); oSD.DestinationDP(oDP.ID()); oSD.DestinationValueType(ivtBinary); oSD.DestinationValue(1);', $Script);
        $this->assertStringContainsString('oPrg.Internal(true);', $Script);
        $this->assertStringContainsString('dom.RTUpdate(0);', $Script);

        $HmIP = SystemVariables::ProgramScript('P', 'I', 0, '', 'HmIP-RCV-1:3', [950 => 2]);
        $this->assertStringContainsString('dom.GetObject("HmIP-RF.HmIP-RCV-1:3.PRESS_SHORT")', $HmIP);
    }

    public function testProgramScriptDeletesOnlyOwnProgram(): void
    {
        $Script = SystemVariables::ProgramScript('Symcon Systemvariablen 12345', 'Info', 29613, 'BidCoS-RF:49', 'BidCoS-RF:49', [950 => 2]);
        $this->assertStringContainsString('object oOld = dom.GetObject(29613);', $Script);
        $this->assertStringContainsString('if ((oOld.TypeName() == "PROGRAM") && (oOld.Name() == "Symcon Systemvariablen 12345")) { dom.DeleteObject(oOld.ID()); bDeleted = true; }', $Script);
        $this->assertSame('', SystemVariables::DeleteProgramScript(0, 'x', false));
        $this->assertStringContainsString('WriteLine("@@0")', SystemVariables::DeleteProgramScript(0, 'x'));
        $this->assertStringContainsString('if (bDeleted) { WriteLine("@@1"); }', SystemVariables::DeleteProgramScript(29613, 'x'));
    }

    public function testParseHeader(): void
    {
        $this->assertSame('29701', SystemVariables::ParseHeader("@@29701\r\n"));
        $this->assertSame('0', SystemVariables::ParseHeader("@@0\r\n"));
        $this->assertFalse(SystemVariables::ParseHeader(''));
    }

    public function testParseProgramState(): void
    {
        $this->assertSame(
            ['Name' => 'Symcon Test', 'Active' => true, 'Variables' => [950, 29612]],
            SystemVariables::ParseProgramState("@@Symcon Test\ttrue\r\n29612\r\n29612\r\n950\r\n950\r\n")
        );
        $this->assertFalse(SystemVariables::ParseProgramState(''));
        $this->assertSame('', SystemVariables::ProgramStateScript(0));
    }

    public function testParseAlarm(): void
    {
        $Alarm = SystemVariables::ParseAlarm("@@2026-10-28 08:20:13\r\ntrue\t2026-10-28 08:00:01\t2026-10-28 07:59:00\t2026-10-28 08:00:00\tTür Flur\tFlur\tHmIP-RF.0000000000000:1.STATE\r\n");
        $this->assertSame([
            'Now'       => '2026-10-28 08:20:13',
            'Value'     => true,
            'Timestamp' => '2026-10-28 08:00:01',
            'FirstTime' => '2026-10-28 07:59:00',
            'LastTime'  => '2026-10-28 08:00:00',
            'Channel'   => 'Tür Flur',
            'Room'      => 'Flur',
            'Datapoint' => 'HmIP-RF.0000000000000:1.STATE'
        ], $Alarm);
        // Nie ausgelöste Alarmvariable (Ausgabe einer CCU).
        $Never = SystemVariables::ParseAlarm("@@2026-10-28 08:20:13\r\n\t1970-01-01 01:00:00\t1970-01-01 01:00:00\t1970-01-01 01:00:00\t\t\t\r\n");
        $this->assertFalse($Never['Value']);
        $this->assertSame(0, SystemVariables::ToUnixTime($Never['FirstTime'], 'Europe/Berlin'));
        $this->assertFalse(SystemVariables::ParseAlarm("@@2026-10-28 08:20:13\r\n-\r\n"));
    }

    public function testToUnixTime(): void
    {
        $this->assertSame(1793172000, SystemVariables::ToUnixTime('2026-10-28 08:20:00', 'Europe/Berlin'));
        $this->assertSame(0, SystemVariables::ToUnixTime('', 'Europe/Berlin'));
    }

    public function testPresentations(): void
    {
        $List = SystemVariables::ParseList(self::ListOutput);
        $Ids = ['{E}', '{S}', '{I}'];
        $Bool = SystemVariables::Presentation($List[950], ...$Ids);
        $this->assertSame('{E}', $Bool['PRESENTATION']);
        $this->assertSame([
            ['Value' => false, 'Caption' => 'abwesend', 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
            ['Value' => true, 'Caption' => 'anwesend', 'IconValue' => '', 'IconActive' => false, 'Color' => -1]
        ], json_decode($Bool['OPTIONS'], true));
        $Enum = SystemVariables::Presentation($List[3000], ...$Ids);
        $this->assertSame(['Aus', 'Tag', 'Nacht'], array_column(json_decode($Enum['OPTIONS'], true), 'Caption'));
        $this->assertSame([0, 1, 2], array_column(json_decode($Enum['OPTIONS'], true), 'Value'));
        $this->assertSame(['PRESENTATION' => '{I}', 'SUFFIX' => ' %'], SystemVariables::Presentation($List[27598], ...$Ids));
        $this->assertSame(['PRESENTATION' => '{I}'], SystemVariables::Presentation($List[14901], ...$Ids));
        $this->assertSame(['PRESENTATION' => '{I}'], SystemVariables::Presentation($List[40], ...$Ids));
        $NoNames = $List[950];
        $NoNames['Name0'] = $NoNames['Name1'] = '';
        $this->assertSame(['PRESENTATION' => '{S}'], SystemVariables::Presentation($NoNames, ...$Ids));
    }

    public function testAlarmReceiptScript(): void
    {
        $Script = SystemVariables::AlarmReceiptScript(1253);
        $this->assertStringContainsString('dom.GetObject(1253)', $Script);
        $this->assertStringContainsString('if (o.AlState() == asOncoming) { WriteLine(o.AlReceipt()); }', $Script);
    }
}
