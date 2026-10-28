<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../Alarmvariable/module.php';

class AlarmvariableTest extends HomematicModuleTestCase
{
    private const Xml = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>';
    private const Key = 'BidCoS-RF:50';
    private const AlarmOutput = "@@2026-10-28 08:20:13\r\ntrue\t2026-10-28 08:00:01\t2026-10-28 07:59:00\t2026-10-28 08:00:00\tTür Flur\tFlur\tHmIP-RF.0000000000000:1.STATE\r\n";

    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        date_default_timezone_set('Europe/Berlin');
    }

    public function testAlarmIsRead(): void
    {
        $Id = $this->createAlarm(1253);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($Id)['InstanceStatus']);
        $Scripts = $this->scripts();
        $this->assertCount(1, $Scripts);
        $this->assertStringContainsString('object o = dom.GetObject(1253);', $Scripts[0]);
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('STATE', $Id)));
        $this->assertSame(1793170740, GetValueInteger(IPS_GetObjectIDByIdent('FIRST_TIME', $Id)));
        $this->assertSame(1793170800, GetValueInteger(IPS_GetObjectIDByIdent('LAST_TIME', $Id)));
        $this->assertSame('Tür Flur', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_CHANNEL', $Id)));
        $this->assertSame('Flur', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_ROOM', $Id)));
        $this->assertSame('HmIP-RF.0000000000000:1.STATE', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_DATAPOINT', $Id)));
        $State = IPS_GetVariable(IPS_GetObjectIDByIdent('STATE', $Id));
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, $State['VariablePresentation']['PRESENTATION']);
        $this->assertGreaterThan(0, $State['VariableAction']);
        $this->assertSame(VARIABLE_PRESENTATION_DATE_TIME, IPS_GetVariable(IPS_GetObjectIDByIdent('LAST_TIME', $Id))['VariablePresentation']['PRESENTATION']);
    }

    public function testTriggerVariablesOnlyWithTrigger(): void
    {
        // Interne Alarmvariable (WatchDog), nie mit Auslöser: nur Zustand, erster und letzter Alarm.
        FakeHomematicIO::$ScriptResponse = "@@2026-10-28 08:20:13\r\n\t1970-01-01 01:00:00\t1970-01-01 01:00:00\t1970-01-01 01:00:00\t\t\t\r\n" . self::Xml;
        $Id = $this->createAlarm(27852, false, false);
        $this->assertSame(0, GetValueInteger(IPS_GetObjectIDByIdent('FIRST_TIME', $Id)));
        foreach (['TRIGGER_CHANNEL', 'TRIGGER_ROOM', 'TRIGGER_DATAPOINT'] as $Ident) {
            $this->assertFalse(@IPS_GetObjectIDByIdent($Ident, $Id), $Ident);
        }
        // Liefert die CCU später einen Auslöser, entstehen die Variablen.
        FakeHomematicIO::$ScriptResponse = $this->ccu(self::AlarmOutput) . self::Xml;
        IPS\InstanceManager::getInstanceInterface($Id)->ReadAlarm();
        $this->assertSame('Tür Flur', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_CHANNEL', $Id)));
    }

    public function testNoAlarmSelected(): void
    {
        $Id = $this->createAlarm(0);
        $this->assertSame(204, IPS_GetInstance($Id)['InstanceStatus']);
        $this->assertSame([], $this->scripts());
        $this->assertFalse(@IPS_GetObjectIDByIdent('STATE', $Id));
    }

    public function testEventReadsOnlyWhenTimestampChanged(): void
    {
        $Id = $this->createAlarm(1253);
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        $Interface->ReceiveData(json_encode(['DataID' => \HMExtended\GUID::HmDevice, 'DeviceID' => self::Key, 'VariableName' => 'PRESS_SHORT', 'VariableValue' => true]));
        $this->assertSame(250, $this->timerInterval($Id));

        // Gleicher Zeitstempel (eine andere Variable auf derselben Taste hat ausgelöst): keine Änderung.
        $StateId = IPS_GetObjectIDByIdent('STATE', $Id);
        $this->setModuleValue($Id, 'STATE', false);
        FakeHomematicIO::$ScriptResponse = $this->ccu(self::AlarmOutput) . self::Xml;
        $Interface->RequestAction(\HMExtended\Device\Timer::ReadSystemVariables, true);
        $this->assertFalse(GetValueBoolean($StateId));

        FakeHomematicIO::$ScriptResponse = "@@2026-10-28 08:30:00\r\nfalse\t2026-10-28 08:29:00\t2026-10-28 07:59:00\t2026-10-28 08:00:00\tFlur\tFlur\tx\r\n" . self::Xml;
        $this->setModuleValue($Id, 'STATE', true);
        $Interface->RequestAction(\HMExtended\Device\Timer::ReadSystemVariables, true);
        $this->assertFalse(GetValueBoolean($StateId));
        // Nach dem Ende des Alarms liefert die CCU weiter den letzten Auslöser (live geprüft): die Variablen des Auslösers werden geleert,
        // erster und letzter Alarm bleiben.
        $this->assertSame('', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_CHANNEL', $Id)));
        $this->assertSame('', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_ROOM', $Id)));
        $this->assertSame('', GetValueString(IPS_GetObjectIDByIdent('TRIGGER_DATAPOINT', $Id)));
        $this->assertSame(1793170800, GetValueInteger(IPS_GetObjectIDByIdent('LAST_TIME', $Id)));
    }

    public function testEndedAlarmCreatesNoTriggerVariables(): void
    {
        FakeHomematicIO::$ScriptResponse = "@@2026-10-28 11:05:00\r\nfalse\t2026-10-28 11:03:43\t2026-10-28 11:03:38\t2026-10-28 11:03:38\tBWM Keller hinten\tKeller hinten\tBidCos-Wired.IEQ0000001:2.SENSOR\r\n" . self::Xml;
        $Id = $this->createAlarm(9875, false, false);
        $this->assertFalse(@IPS_GetObjectIDByIdent('TRIGGER_CHANNEL', $Id));
        $this->assertGreaterThan(0, GetValueInteger(IPS_GetObjectIDByIdent('LAST_TIME', $Id)));
    }

    public function testAcknowledge(): void
    {
        $Id = $this->createAlarm(1253, true);
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponse = "true\r\n" . self::Xml;
        $Interface->RequestAction('STATE', false);
        $this->assertStringContainsString('if (o.AlState() == asOncoming)', $this->scripts()[0]);
        $this->assertFalse(GetValueBoolean(IPS_GetObjectIDByIdent('STATE', $Id)));

        FakeHomematicIO::$ScriptResponse = "false\r\n" . self::Xml;
        $this->assertFalse($Interface->AlarmReceipt());
        $Interface->RequestAction('STATE', true);
        $this->expectOutputString('An alarm can only be acknowledged.' . PHP_EOL);
    }

    public function testMissingAlarmIsReported(): void
    {
        $this->expectOutputString('Alarm variable 4711 does not exist on the CCU.' . PHP_EOL);
        FakeHomematicIO::$ScriptResponse = "@@2026-10-28 08:20:13\r\n-\r\n" . self::Xml;
        $this->createAlarm(4711, false, false);
    }

    public function testProgramUsesAlarmVariable(): void
    {
        $Id = $this->createAlarm(1253);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = ["K\tBidCoS-RF:50\tServicemeldungen an IPS pushen\t0\r\n" . self::Xml, "@@29800\r\n" . self::Xml];
        $this->assertSame('CCU program "Symcon Alarmvariable ' . $Id . '" created.', IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
        $Script = $this->scripts()[1];
        // Die Taste ist bereits umbenannt, das Script ändert den Namen nur bei Werksnamen.
        $this->assertStringContainsString('if (oKey.Name() == "HM-RCV-50 BidCoS-RF:50")', $Script);
        $this->assertStringContainsString('dom.GetObject("BidCos-RF.BidCoS-RF:50.PRESS_SHORT")', $Script);
        $this->assertStringContainsString('oSC.LeftVal(1253); oSC.RightVal1(false);', $Script);
        $this->assertStringContainsString('oSC.LeftVal(1253); oSC.RightVal1(true);', $Script);
    }

    public function testConfigurationFormListsAlarms(): void
    {
        $Id = $this->createAlarm(1253);
        FakeHomematicIO::$ScriptResponses = [
            "K\tBidCoS-RF:50\tServicemeldungen an IPS pushen\t0\r\n" . self::Xml,
            $this->ccu("950\tAnwesenheit\t1089\t2\t2\tfalse\t65535\t\t\t\tabwesend\tanwesend\t\r\n" .
            "1253\tExt. Alarm Haus\t2113\t2\t6\tfalse\t65535\t\t\t\tnicht ausgelöst\tausgelöst\t\r\n" .
            "27852\tWatchDog: chronyd-highoffset\t2113\t2\t6\ttrue\t65535\t\t\t\tnicht ausgelöst\tausgelöst\t\r\n") . self::Xml
        ];
        $Form = json_decode(IPS\InstanceManager::getInstanceInterface($Id)->GetConfigurationForm(), true);
        $Alarm = array_values(array_filter($Form['elements'], fn ($Element) => ($Element['name'] ?? '') === 'AlarmID'))[0];
        $this->assertSame([1253, 27852], array_column($Alarm['options'], 'value'));
    }

    private function createAlarm(int $AlarmId, bool $EmulateStatus = false, bool $SetResponse = true): int
    {
        $Id = $this->createDevice(HomeMaticAlarmvariable::class, \HMExtended\GUID::Alarmvariable, 'HMAV', $this->IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, self::Key);
        IPS_SetProperty($Id, \HMExtended\Device\Property::AlarmID, $AlarmId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::EmulateStatus, $EmulateStatus);
        if ($SetResponse) {
            FakeHomematicIO::$ScriptResponse = $this->ccu(self::AlarmOutput) . self::Xml;
        }
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

    private function setModuleValue(int $Id, string $Ident, mixed $Value): void
    {
        $Method = new ReflectionMethod(HomeMaticAlarmvariable::class, 'SetValue');
        $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id), $Ident, $Value);
    }

    private function scripts(): array
    {
        return array_values(array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['Content'])), 'Content'));
    }

    private function timerInterval(int $Id): int
    {
        $Method = new ReflectionMethod(HomeMaticAlarmvariable::class, 'GetTimerInterval');
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id), \HMExtended\Device\Timer::ReadSystemVariables);
    }
}
