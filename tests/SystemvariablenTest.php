<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../Systemvariablen/module.php';

class SystemvariablenTest extends HomematicModuleTestCase
{
    private const Xml = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>';
    private const Key = 'BidCoS-RF:49';
    private const ListOutput = "950\tAnwesenheit\t1089\t2\t2\tfalse\t65535\t\t\t\tabwesend\tanwesend\t\r\n" .
        "27598\tDutyCycle\t1089\t4\t0\tfalse\t65535\t%\t-1\t100\t0\t1\t\r\n" .
        "1253\tExt. Alarm Haus\t2113\t2\t6\tfalse\t65535\t\t\t\tnicht ausgelöst\tausgelöst\t\r\n" .
        "14901\tServicemeldung automatisch quitiert\t1089\t20\t11\tfalse\t65535\t\t\t\t1\t1\t\r\n" .
        "41\tServicemeldungen\t1089\t16\t0\ttrue\t65535\t\t0\t65000\t0\t1\t\r\n" .
        "3000\tModus\t1089\t16\t29\tfalse\t65535\t\t0\t2\t0\t1\tAus;Tag;Nacht\r\n" .
        "22678\tsvEnergyCounter_22672_MEQ0000001:1\t1089\t4\t0\ttrue\t65535\t\t0\t1.7e+308\t0\t1\t\r\n";
    private const ReadOutput = "@@2026-10-28 08:19:58\r\n" .
        "950\t2\t2026-08-16 12:01:41\ttrue\r\n" .
        "27598\t4\t2026-10-28 08:19:01\t9.000000\r\n" .
        "14901\t20\t2026-10-28 08:02:35\tText\r\n" .
        "3000\t16\t2026-10-28 08:02:35\t2\r\n";

    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
    }

    public function testWithoutSelectionNothingIsRead(): void
    {
        $Id = $this->createSystemVariables([], '');
        $this->assertSame([], $this->scripts());
        $this->assertSame(IS_INACTIVE, IPS_GetInstance($Id)['InstanceStatus']);
    }

    public function testSelectedVariablesAreCreatedAndReadWithTwoScripts(): void
    {
        $Id = $this->createSystemVariables([950, 27598, 14901, 3000]);
        $Scripts = $this->scripts();
        $this->assertCount(2, $Scripts, 'one script for the properties, one for all values');
        $this->assertStringContainsString('foreach (sId, "950\t27598\t14901\t3000")', $Scripts[1]);
        $this->assertStringContainsString('string sSince = "";', $Scripts[1]);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($Id)['InstanceStatus']);

        $Expected = [
            '950'   => ['Anwesenheit', VARIABLETYPE_BOOLEAN, true, VARIABLE_PRESENTATION_ENUMERATION],
            '27598' => ['DutyCycle', VARIABLETYPE_FLOAT, 9.0, VARIABLE_PRESENTATION_VALUE_INPUT],
            '14901' => ['Servicemeldung automatisch quitiert', VARIABLETYPE_STRING, 'Text', VARIABLE_PRESENTATION_VALUE_INPUT],
            '3000'  => ['Modus', VARIABLETYPE_INTEGER, 2, VARIABLE_PRESENTATION_ENUMERATION]
        ];
        foreach ($Expected as $Ident => [$Name, $Type, $Value, $Presentation]) {
            $Ident = (string) $Ident;
            $VariableId = IPS_GetObjectIDByIdent($Ident, $Id);
            $Variable = IPS_GetVariable($VariableId);
            $this->assertSame($Name, IPS_GetName($VariableId), $Ident);
            $this->assertSame($Type, $Variable['VariableType'], $Ident);
            $this->assertSame($Value, GetValue($VariableId), $Ident);
            $this->assertSame($Presentation, $Variable['VariablePresentation']['PRESENTATION'], $Ident);
            $this->assertGreaterThan(0, $Variable['VariableAction'], $Ident);
        }
        $Presentation = IPS_GetVariable(IPS_GetObjectIDByIdent('27598', $Id))['VariablePresentation'];
        $this->assertSame(' %', $Presentation['SUFFIX']);
        $Options = json_decode(IPS_GetVariable(IPS_GetObjectIDByIdent('950', $Id))['VariablePresentation']['OPTIONS'], true);
        $this->assertSame(['abwesend', 'anwesend'], array_column($Options, 'Caption'));
    }

    public function testEventStartsTimerAndReadsOnlyChangedValues(): void
    {
        $Id = $this->createSystemVariables([950, 27598]);
        $this->sendEvent($Id, self::Key, 'PRESS_LONG');
        $this->assertSame(0, $this->timerInterval($Id));
        $this->sendEvent($Id, self::Key, 'PRESS_SHORT');
        $this->assertSame(250, $this->timerInterval($Id));

        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponse = "@@2026-10-28 08:21:00\r\n27598\t4\t2026-10-28 08:20:59\t12.500000\r\n" . self::Xml;
        IPS\InstanceManager::getInstanceInterface($Id)->RequestAction(\HMExtended\Device\Timer::ReadSystemVariables, true);
        $Scripts = $this->scripts();
        $this->assertCount(1, $Scripts);
        $this->assertStringContainsString('string sSince = "2026-10-28 08:19:58";', $Scripts[0]);
        $this->assertSame(12.5, GetValueFloat(IPS_GetObjectIDByIdent('27598', $Id)));
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('950', $Id)));
        $this->assertSame(0, $this->timerInterval($Id));
    }

    public function testFilterUsesKey(): void
    {
        $Id = $this->createSystemVariables([950]);
        $this->assertSame('.*"DeviceID":"' . self::Key . '".*', IPS\InstanceManager::getInstanceInterface($Id)->GetReceiveDataFilter());
    }

    public function testIntervalTimerAndTooSmallInterval(): void
    {
        $Id = $this->createSystemVariables([950], self::Key, 60);
        $this->assertSame(60000, $this->timerInterval($Id));
        FakeHomematicIO::$Requests = [];
        IPS_SetProperty($Id, \HMExtended\Device\Property::Interval, 3);
        IPS_ApplyChanges($Id);
        $this->assertSame(203, IPS_GetInstance($Id)['InstanceStatus']);
        $this->assertSame([], $this->scripts());
        $this->assertSame(0, $this->timerInterval($Id));
    }

    public function testWriteValue(): void
    {
        $Id = $this->createSystemVariables([950]);
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponse = "false\r\n" . self::Xml;
        $this->assertTrue($Interface->WriteValueBoolean('950', false) === false);
        FakeHomematicIO::$ScriptResponse = "true\r\n" . self::Xml;
        $this->assertTrue($Interface->WriteValueBoolean('950', false));
        $this->assertStringContainsString('object o = dom.GetObject(950);', $this->scripts()[1]);
        $this->assertStringContainsString('o.State(false)', $this->scripts()[1]);
        // Ohne Status emulieren folgt der Wert erst über das Event der CCU.
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('950', $Id)));
        $this->expectOutputString('Error on write CCU system variable 950.' . PHP_EOL);
    }

    public function testWriteValueWithEmulateStatus(): void
    {
        $Id = $this->createSystemVariables([950]);
        IPS_SetProperty($Id, \HMExtended\Device\Property::EmulateStatus, true);
        $this->select($Id, [950]);
        FakeHomematicIO::$ScriptResponse = "true\r\n" . self::Xml;
        IPS\InstanceManager::getInstanceInterface($Id)->RequestAction('950', false);
        $this->assertFalse(GetValueBoolean(IPS_GetObjectIDByIdent('950', $Id)));
    }

    public function testWriteValueChecksIdentAndType(): void
    {
        $Id = $this->createSystemVariables([950]);
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        $this->assertFalse($Interface->WriteValueInteger('950', 1));
        $this->assertFalse($Interface->WriteValueInteger('4711', 1));
        $this->expectOutputString('Wrong data type for 950.' . PHP_EOL . 'Ident 4711 does not exist.' . PHP_EOL);
    }

    public function testChangedTypeKeepsVariable(): void
    {
        $Id = $this->createSystemVariables([]);
        $Old = IPS_CreateVariable(VARIABLETYPE_STRING);
        IPS_SetParent($Old, $Id);
        IPS_SetIdent($Old, '950');
        $this->select($Id, [950]);
        $this->assertSame($Old, IPS_GetObjectIDByIdent('950', $Id));
        $this->assertSame(VARIABLETYPE_STRING, IPS_GetVariable($Old)['VariableType']);
        $this->assertSame([['Message' => 'Type of CCU system variable Anwesenheit has changed.', 'Type' => KL_WARNING]], IPS\LogServer::getLogMessages((string) $Id));
    }

    public function testMissingVariableIsLoggedAndOthersAreRead(): void
    {
        // Ein Hinweis im Log statt einer Fehlermeldung: die übrigen Variablen werden gelesen.
        $this->expectOutputString('');
        $Id = $this->createSystemVariables([4711, 1253, 950]);
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('950', $Id)));
        $this->assertFalse(@IPS_GetObjectIDByIdent('1253', $Id));
        $this->assertSame(['System variable 4711 does not exist on the CCU.', 'System variable 1253 does not exist on the CCU.'], array_column(IPS\LogServer::getLogMessages((string) $Id), 'Message'));
    }

    public function testNoParentNoMessages(): void
    {
        $this->registerSystemVariablesModule();
        $Id = IPS_CreateInstance(\HMExtended\GUID::Systemvariablen);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, self::Key);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Variables, json_encode([['Id' => 950, 'Use' => true]]));
        IPS_ApplyChanges($Id);
        $this->assertSame([], $this->scripts());
        $this->expectOutputString('');
    }

    public function testCreateProgram(): void
    {
        $Id = $this->createSystemVariables([950, 27598]);
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = [$this->keyList(), "@@29701\r\n" . self::Xml];
        $this->assertSame('CCU program "Symcon Systemvariablen ' . $Id . '" created.', $Interface->CreateCCUProgram());
        $Script = $this->scripts()[1];
        $this->assertStringContainsString('dom.GetObject("BidCos-RF.BidCoS-RF:49.PRESS_SHORT")', $Script);
        $this->assertStringContainsString('oSC.LeftVal(950); oSC.RightVal1(true);', $Script);
        $this->assertStringContainsString('oSC.LeftVal(27598); oSC.RightVal1(0.0);', $Script);
        $this->assertStringContainsString('oKey.Name("Symcon Systemvariablen ' . $Id . '")', $Script);
        $this->assertStringNotContainsString('DeleteObject', $Script);
        $this->assertSame(29701, $this->attribute($Id, \HMExtended\Device\Attribute::ProgramID));
        $this->assertSame(self::Key, $this->attribute($Id, \HMExtended\Device\Attribute::ProgramKey, 'ReadAttributeString'));

        // Ein zweiter Aufruf mit anderer Taste ersetzt das eigene Programm, die bisherige Taste erhält ihren Werksnamen zurück.
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, 'BidCoS-RF:47');
        $this->select($Id, [950, 27598]);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = [$this->keyList(), "@@29720\r\n" . self::Xml];
        $Interface->CreateCCUProgram();
        $this->assertStringContainsString('object oOld = dom.GetObject(29701);', $this->scripts()[1]);
        $this->assertStringContainsString('oOldKey.Name("HM-RCV-50 BidCoS-RF:49")', $this->scripts()[1]);
        $this->assertSame(29720, $this->attribute($Id, \HMExtended\Device\Attribute::ProgramID));
        $this->assertSame('BidCoS-RF:47', $this->attribute($Id, \HMExtended\Device\Attribute::ProgramKey, 'ReadAttributeString'));

        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = ["@@1\r\n" . self::Xml];
        $this->assertSame('CCU program "Symcon Systemvariablen ' . $Id . '" deleted.', $Interface->DeleteCCUProgram());
        $this->assertStringContainsString('oOldKey.Name("HM-RCV-50 BidCoS-RF:47")', $this->scripts()[0]);
        $this->assertSame(0, $this->attribute($Id, \HMExtended\Device\Attribute::ProgramID));
        $this->assertSame('', $this->attribute($Id, \HMExtended\Device\Attribute::ProgramKey, 'ReadAttributeString'));
    }

    public function testKeyUsedAsConditionIsRefused(): void
    {
        $Id = $this->createSystemVariables([950]);
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponses = [$this->keyList("U\tBidCoS-RF:49\tC\t8643\tLicht Flur\r\n")];
        $this->assertFalse(IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
        $this->assertCount(1, $this->scripts(), 'no program was created');
        $this->expectOutputString('The virtual key BidCoS-RF:49 is in use (condition in: Licht Flur). Every key press would trigger it as well. Please choose another key.' . PHP_EOL);
    }

    public function testKeyWithDirectLinksIsRefused(): void
    {
        $Id = $this->createSystemVariables([950]);
        FakeHomematicIO::$RpcResponses['getLinks@0'] = [['SENDER' => self::Key, 'RECEIVER' => 'LEQ0000001:1']];
        FakeHomematicIO::$ScriptResponses = [$this->keyList()];
        $this->assertFalse(IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
        $this->expectOutputString('The virtual key BidCoS-RF:49 is in use (direct links: 1). Every key press would trigger it as well. Please choose another key.' . PHP_EOL);
    }

    public function testKeyPressedByOtherProgramIsAllowed(): void
    {
        $Id = $this->createSystemVariables([950]);
        FakeHomematicIO::$ScriptResponses = [$this->keyList("U\tBidCoS-RF:49\tA\t10153\tSystemvars zu IPS pushen\r\n"), "@@29701\r\n" . self::Xml];
        $this->assertNotFalse(IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
    }

    public function testCreateProgramRequiresKeyAndVariables(): void
    {
        $Id = $this->createSystemVariables([950], '');
        $this->assertFalse(IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
        $Other = $this->createSystemVariables([]);
        $this->assertFalse(IPS\InstanceManager::getInstanceInterface($Other)->CreateCCUProgram());
        $this->expectOutputString('No virtual key selected.' . PHP_EOL . 'No variables selected.' . PHP_EOL);
    }

    public function testMissingKeyOnCcu(): void
    {
        $Id = $this->createSystemVariables([950]);
        FakeHomematicIO::$ScriptResponse = "@@0\r\n" . self::Xml;
        $this->assertFalse(IPS\InstanceManager::getInstanceInterface($Id)->CreateCCUProgram());
        $this->expectOutputString('The virtual key was not found on the CCU.' . PHP_EOL);
    }

    public function testUnusedVariablesAreOnlyDeletedOnRequest(): void
    {
        $Id = $this->createSystemVariables([950, 27598]);
        $this->select($Id, [950]);
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('27598', $Id), 'deselected variable is kept');
        IPS\InstanceManager::getInstanceInterface($Id)->RequestAction('DeleteUnusedVariables', true);
        $this->assertFalse(@IPS_GetObjectIDByIdent('27598', $Id));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('950', $Id));
    }

    public function testDeselectHintOncePerFormOpening(): void
    {
        $Id = $this->createSystemVariables([950]);
        $Interface = IPS\InstanceManager::getInstanceInterface($Id);
        FakeHomematicIO::$ScriptResponse = '';
        $Interface->GetConfigurationForm();
        $this->assertTrue($Interface->ShowDeselectHint);
        // Abwählen einer Systemvariable ohne Statusvariable und Anhaken: kein Hinweis.
        $Interface->RequestAction('VariableEdited', json_encode([27598, false]));
        $Interface->RequestAction('VariableEdited', json_encode([950, true]));
        $this->assertTrue($Interface->ShowDeselectHint);
        // Erstes Abwählen mit Statusvariable: Hinweis, danach nicht mehr.
        $Interface->RequestAction('VariableEdited', json_encode([950, false]));
        $this->assertFalse($Interface->ShowDeselectHint);
        // Erneutes Öffnen des Formulars zeigt den Hinweis wieder.
        $Interface->GetConfigurationForm();
        $this->assertTrue($Interface->ShowDeselectHint);
        $this->expectOutputString('');
    }

    public function testDeleteButtonIsAlwaysVisible(): void
    {
        $Id = $this->createSystemVariables([950, 27598]);
        FakeHomematicIO::$ScriptResponse = '';
        $Button = $this->findElement(json_decode(IPS\InstanceManager::getInstanceInterface($Id)->GetConfigurationForm(), true)['actions'], 'DeleteUnusedVariables');
        $this->assertArrayNotHasKey('visible', $Button);
        $this->assertFalse($Button['enabled']);
        $this->select($Id, [950]);
        FakeHomematicIO::$ScriptResponse = '';
        $Button = $this->findElement(json_decode(IPS\InstanceManager::getInstanceInterface($Id)->GetConfigurationForm(), true)['actions'], 'DeleteUnusedVariables');
        $this->assertTrue($Button['enabled']);
        $this->assertSame('Delete 1 status variables without selection', $Button['caption']);
    }

    public function testMigrationOfLegacyInstance(): void
    {
        $EventId = $this->createHmDeviceVariable('BidCoS-RF:48', 'PRESS_SHORT');
        $Id = $this->createSystemVariables([]);
        foreach (['950', 'AlDP1253', '27598'] as $Ident) {
            $Variable = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
            IPS_SetParent($Variable, $Id);
            IPS_SetIdent($Variable, $Ident);
        }
        $Old = json_encode(['configuration' => [
            'Address'       => 'XXX9999999:1',
            'Protocol'      => 0,
            'EventID'       => $EventId,
            'Interval'      => 0,
            'EmulateStatus' => false,
            'EnableAlarmDP' => true,
            'AlarmScriptID' => 0
        ]]);
        $New = json_decode(IPS\InstanceManager::getInstanceInterface($Id)->Migrate($Old), true)['configuration'];
        $this->assertSame('BidCoS-RF:48', $New['Address']);
        $this->assertSame(0, $New['Protocol']);
        $this->assertSame([['Id' => 950, 'Use' => true], ['Id' => 27598, 'Use' => true]], json_decode($New['Variables'], true));
        // Bereits migrierte Konfigurationen bleiben unverändert.
        $Migrated = json_encode(['configuration' => $New]);
        $this->assertSame($Migrated, IPS\InstanceManager::getInstanceInterface($Id)->Migrate($Migrated));
    }

    public function testLegacyProfileIsReplacedByPresentation(): void
    {
        $Id = $this->createSystemVariables([]);
        $Profile = 'HM.SysVar' . $Id . '.950';
        IPS_CreateVariableProfile($Profile, VARIABLETYPE_BOOLEAN);
        $Variable = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetParent($Variable, $Id);
        IPS_SetIdent($Variable, '950');
        IPS\VariableManager::setVariableProfile($Variable, $Profile);
        $this->select($Id, [950]);
        $this->assertSame('', IPS_GetVariable($Variable)['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, IPS_GetVariable($Variable)['VariablePresentation']['PRESENTATION']);
        $this->assertFalse(IPS_VariableProfileExists($Profile));
    }

    public function testLegacyAlarmVariablesAreDeletedOnRequest(): void
    {
        $Id = $this->createSystemVariables([950]);
        $Alarm = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetParent($Alarm, $Id);
        IPS_SetIdent($Alarm, 'AlDP1253');
        $Sub = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetParent($Sub, $Alarm);
        IPS_SetIdent($Sub, 'LastTime');
        IPS\InstanceManager::getInstanceInterface($Id)->RequestAction('DeleteLegacyAlarmVariables', true);
        $this->assertFalse(IPS_VariableExists($Alarm));
        $this->assertFalse(IPS_VariableExists($Sub));
        $this->assertNotFalse(@IPS_GetObjectIDByIdent('950', $Id));
    }

    public function testConfigurationFormListsSupportedVariables(): void
    {
        $Id = $this->createSystemVariables([950, 4711]);
        // Der Nutzer hat die Statusvariable umbenannt: die Liste zeigt den Objektnamen.
        IPS_SetName(IPS_GetObjectIDByIdent('950', $Id), 'Jemand zu Hause');
        // Eine zweite Instanz nutzt die Taste 47.
        $Other = $this->createSystemVariables([], 'BidCoS-RF:47');
        FakeHomematicIO::$RpcResponses['getLinks@0'] = [['SENDER' => 'BidCoS-RF:47', 'RECEIVER' => 'LEQ0000001:1']];
        FakeHomematicIO::$ScriptResponses = [
            $this->keyList("U\tBidCoS-RF:48\tA\t10153\tSystemvars zu IPS pushen\r\nU\tBidCoS-RF:47\tC\t8643\tLicht Flur\r\n"),
            self::ListOutput . self::Xml
        ];
        $Form = json_decode(IPS\InstanceManager::getInstanceInterface($Id)->GetConfigurationForm(), true);
        $this->assertIsArray($Form);
        $Address = $this->findElement($Form['elements'], 'Address');
        $this->assertSame([
            'BidCoS-RF:47 (condition in: Licht Flur; direct links: 1; Symcon instances: ' . $Other . ')',
            'BidCoS-RF:48 - Systemvariablen an IPS pushen (pressed by: Systemvars zu IPS pushen)',
            // Die eigene Instanz wird nicht als Verwendung angezeigt.
            'BidCoS-RF:49 (free)'
        ], array_column($Address['options'], 'caption'));
        $this->assertSame(['BidCoS-RF:47', 'BidCoS-RF:48', 'BidCoS-RF:49'], array_column($Address['options'], 'value'));
        $List = $this->findElement($Form['elements'], 'Variables');
        $Rows = array_column($List['values'], null, 'Id');
        // Die Zähler der Energiezähler und Alarmvariablen fehlen, die Konsole sortiert nach Namen.
        $this->assertEqualsCanonicalizing([950, 27598, 4711, 3000, 14901, 41], array_column($List['values'], 'Id'));
        $this->assertSame(['column' => 'Name', 'direction' => 'ascending'], $List['sort']);
        $this->assertTrue($Rows[950]['Use']);
        $this->assertFalse($Rows[27598]['Use']);
        $this->assertSame('Jemand zu Hause', $Rows[950]['Name']);
        $this->assertSame('DutyCycle', $Rows[27598]['Name'], 'without status variable the name of the CCU is shown');
        $this->assertSame('Yes', $Rows[41]['Internal']);
        $this->assertSame('', $Rows[950]['Internal']);
        $this->assertSame('', $Rows[4711]['Name']);
        $this->assertSame('missing', $Rows[4711]['Type']);
        $this->assertSame('#FFC0C0', $Rows[4711]['rowColor']);
        $this->assertSame('Value list', $Rows[3000]['Type']);
        $this->assertSame('CCU program: not created', $this->findElement($Form['actions'], 'ProgramState')['caption']);
    }

    public function testConfigurationFormWithoutCcuDoesNotMarkMissing(): void
    {
        $Id = $this->createSystemVariables([950]);
        // CCU antwortet nicht (Antwort ohne XML).
        FakeHomematicIO::$ScriptResponse = '';
        $Form = json_decode(IPS\InstanceManager::getInstanceInterface($Id)->GetConfigurationForm(), true);
        $Rows = $this->findElement($Form['elements'], 'Variables')['values'];
        $this->assertCount(1, $Rows);
        $this->assertSame('Anwesenheit', $Rows[0]['Name']);
        $this->assertSame('Boolean', $Rows[0]['Type'], 'last known type');
        $this->assertArrayNotHasKey('rowColor', $Rows[0]);
        $this->expectOutputString('');
    }

    private function registerSystemVariablesModule(): void
    {
        $this->registerTestModule(HomeMaticSystemvariablen::class, \HMExtended\GUID::Systemvariablen, MODULETYPE_DEVICE, 'HMSV', [
            'parentRequirements' => [\HMExtended\GUID::SendRpcToIO, \HMExtended\GUID::SendScriptToIO],
            'implemented'        => [self::DeviceDataFlow]
        ]);
    }

    private function createSystemVariables(array $Selected, string $Key = self::Key, int $Interval = 0): int
    {
        $Id = $this->createDevice(HomeMaticSystemvariablen::class, \HMExtended\GUID::Systemvariablen, 'HMSV', $this->IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Interval, $Interval);
        if ($Key !== '') {
            IPS_SetProperty($Id, \HMExtended\Device\Property::Address, $Key);
        }
        FakeHomematicIO::$Requests = [];
        $this->select($Id, $Selected);
        return $Id;
    }

    private function select(int $Id, array $Selected): void
    {
        FakeHomematicIO::$ScriptResponses = [self::ListOutput . self::Xml, self::ReadOutput . self::Xml];
        $Rows = [];
        foreach ($Selected as $VariableId) {
            $Rows[] = ['Id' => $VariableId, 'Use' => true];
        }
        IPS_SetProperty($Id, \HMExtended\Device\Property::Variables, json_encode($Rows));
        IPS_ApplyChanges($Id);
        FakeHomematicIO::$ScriptResponses = [];
    }

    private function scripts(): array
    {
        return array_values(array_column(array_filter(FakeHomematicIO::$Requests, fn ($Request) => isset($Request['Content'])), 'Content'));
    }

    private function sendEvent(int $Id, string $Address, string $Datapoint): void
    {
        IPS\InstanceManager::getInstanceInterface($Id)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => $Address,
            'VariableName'  => $Datapoint,
            'VariableValue' => true
        ]));
    }

    private function timerInterval(int $Id): int
    {
        $Method = new ReflectionMethod(HomeMaticSystemvariablen::class, 'GetTimerInterval');
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id), \HMExtended\Device\Timer::ReadSystemVariables);
    }

    private function attribute(int $Id, string $Name, string $Reader = 'ReadAttributeInteger'): mixed
    {
        $Method = new ReflectionMethod(HomeMaticSystemvariablen::class, $Reader);
        return $Method->invoke(IPS\InstanceManager::getInstanceInterface($Id), $Name);
    }

    /**
     * Antwort des Scripts für die Tasten: 47 und 49 mit Werksnamen, 48 umbenannt.
     *
     * @param string $Usage Zusätzliche Zeilen (Verwendung in Programmen).
     */
    private function keyList(string $Usage = ''): string
    {
        return "K\tBidCoS-RF:47\tHM-RCV-50 BidCoS-RF:47\t0\r\nK\tBidCoS-RF:48\tSystemvariablen an IPS pushen\t0\r\nK\tBidCoS-RF:49\tHM-RCV-50 BidCoS-RF:49\t0\r\n" . $Usage . self::Xml;
    }

    private function findElement(array $Items, string $Name): ?array
    {
        foreach ($Items as $Item) {
            if (($Item['name'] ?? '') === $Name) {
                return $Item;
            }
            if (isset($Item['items'])) {
                $Found = $this->findElement($Item['items'], $Name);
                if ($Found !== null) {
                    return $Found;
                }
            }
        }
        return null;
    }
}
