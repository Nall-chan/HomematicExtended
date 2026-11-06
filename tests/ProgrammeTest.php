<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../Programme/module.php';

class ProgrammeTest extends HomematicModuleTestCase
{
    private int $ProgrammeId;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        // Die CCU liefert ISO-8859-1.
        FakeHomematicIO::$ScriptResponse = mb_convert_encoding("1234\tHaustürklingel\r\n5678\tDummy für Tasten\r\nkaputt\r\n9\r\n", 'ISO-8859-1', 'UTF-8') . $this->xml('');
        $this->ProgrammeId = $this->createDevice(HomeMaticProgramme::class, \HMExtended\GUID::Programme, 'HMPR', $IoId);
        IPS_ApplyChanges($this->ProgrammeId);
        // Eine neue Instanz legt nichts an, bis Programme in der Liste gewählt werden.
        $this->assertCount(0, IPS_GetChildrenIDs($this->ProgrammeId));
        $this->select(1234, true);
        $this->select(5678, true);
    }

    public function testProgramsBecomeVariablesWithAction(): void
    {
        $this->assertSame('Haustürklingel', IPS_GetName(IPS_GetObjectIDByIdent('1234', $this->ProgrammeId)));
        $this->assertSame('Dummy für Tasten', IPS_GetName(IPS_GetObjectIDByIdent('5678', $this->ProgrammeId)));
        $Variable = IPS_GetVariable(IPS_GetObjectIDByIdent('1234', $this->ProgrammeId));
        $this->assertSame('', $Variable['VariableProfile']);
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, $Variable['VariablePresentation']['PRESENTATION']);
        $this->assertSame('Start', json_decode($Variable['VariablePresentation']['OPTIONS'], true)[0]['Caption']);
        $this->assertTrue(HasAction(IPS_GetObjectIDByIdent('1234', $this->ProgrammeId)));
        $this->assertFalse(IPS_VariableProfileExists('Execute.HM'));
    }

    public function testLegacyProfileIsRemovedAfterMigration(): void
    {
        // Stand bis Version 3.81: Profil Execute.HM.
        IPS_CreateVariableProfile('Execute.HM', VARIABLETYPE_INTEGER);
        $VariableId = IPS_GetObjectIDByIdent('1234', $this->ProgrammeId);
        IPS\VariableManager::setVariableProfile($VariableId, 'Execute.HM');
        IPS_ApplyChanges($this->ProgrammeId);
        $this->assertSame('', IPS_GetVariable($VariableId)['VariableProfile']);
        $this->assertFalse(IPS_VariableProfileExists('Execute.HM'));
    }

    public function testLegacyProfileOfDeletedProgramIsKept(): void
    {
        // Die Variable eines auf der CCU gelöschten Programms wird nicht mehr gepflegt und behält ihr Profil.
        IPS_CreateVariableProfile('Execute.HM', VARIABLETYPE_INTEGER);
        IPS\VariableManager::setVariableProfile(IPS_GetObjectIDByIdent('5678', $this->ProgrammeId), 'Execute.HM');
        FakeHomematicIO::$ScriptResponse = "1234\tHaustürklingel\r\n" . $this->xml('');
        IPS_ApplyChanges($this->ProgrammeId);
        $this->assertTrue(IPS_VariableProfileExists('Execute.HM'));
    }

    public function testUnselectedProgramGetsNoVariable(): void
    {
        FakeHomematicIO::$ScriptResponse = "1234\tHaustürklingel\r\n9999\tNicht gewählt\r\n" . $this->xml('');
        $this->assertTrue($this->programme()->ReadPrograms());
        $this->assertFalse(@IPS_GetObjectIDByIdent('9999', $this->ProgrammeId));
    }

    public function testDeselectDeletesVariableAndChangesAttribute(): void
    {
        $this->select(1234, false);
        $this->assertFalse(@IPS_GetObjectIDByIdent('1234', $this->ProgrammeId));
        $this->assertSame([5678], $this->attribute('Selected'));
        // Auch das Auslesen legt die abgewählte Variable nicht wieder an.
        FakeHomematicIO::$ScriptResponse = "1234\tHaustürklingel\r\n5678\tDummy für Tasten\r\n" . $this->xml('');
        $this->programme()->ReadPrograms();
        $this->assertFalse(@IPS_GetObjectIDByIdent('1234', $this->ProgrammeId));
    }

    public function testSelectUnknownProgramIsReported(): void
    {
        ob_start();
        $this->select(4242, true);
        $this->assertStringContainsString('CCU program 4242 does not exist.', (string) ob_get_clean());
        $this->assertSame([1234, 5678], $this->attribute('Selected'));
    }
    public function testMigrateSelectsExistingProgramVariables(): void
    {
        // Instanz bis Version 3.90: Variablen ohne Auswahl, alle werden gewählt; fremde Objekte ohne Ident bleiben außen vor.
        $Foreign = IPS_CreateVariable(VARIABLETYPE_STRING);
        IPS_SetParent($Foreign, $this->ProgrammeId);
        $this->writeAttribute('Selected', []);
        $Result = json_decode($this->programme()->Migrate(json_encode(['configuration' => new stdClass(), 'attributes' => ['Selected' => '[]']])), true);
        $this->assertEqualsCanonicalizing([1234, 5678], json_decode($Result['attributes']['Selected'], true));
    }

    public function testMigrateKeepsExistingSelection(): void
    {
        $this->assertSame('', $this->programme()->Migrate(json_encode(['attributes' => ['Selected' => '[1234]']])));
    }

    public function testMigrateWithoutVariablesIsUnchanged(): void
    {
        $this->select(1234, false);
        $this->select(5678, false);
        $this->assertSame('', $this->programme()->Migrate(json_encode(['attributes' => ['Selected' => '[]']])));
    }

    public function testFormListShowsAllProgramsWithSelection(): void
    {
        $this->select(5678, false);
        FakeHomematicIO::$ScriptResponse = "1234\tHaustürklingel\r\n5678\tDummy für Tasten\r\n" . $this->xml('');
        $Form = json_decode($this->programme()->GetConfigurationForm(), true);
        $Rows = [];
        foreach ($Form['actions'] as $Action) {
            if (($Action['name'] ?? '') === 'Programs') {
                $Rows = array_column($Action['values'], 'Use', 'Id');
            }
        }
        ksort($Rows);
        $this->assertSame([1234 => true, 5678 => false], $Rows);
    }

    public function testInvalidLinesAreIgnored(): void
    {
        $this->assertCount(2, IPS_GetChildrenIDs($this->ProgrammeId));
    }

    public function testRequestActionStartsProgram(): void
    {
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$ScriptResponse = $this->xml('true');
        ob_start();
        $this->programme()->RequestAction('1234', 0);
        $this->assertSame('', ob_get_clean());
        $this->assertSame('State=dom.GetObject(1234).ProgramExecute();', FakeHomematicIO::$Requests[0]['Content']);
    }

    public function testStartProgramFunction(): void
    {
        FakeHomematicIO::$ScriptResponse = $this->xml('true');
        $this->assertTrue($this->programme()->StartProgram('5678'));
    }

    public function testRejectedStartIsReported(): void
    {
        FakeHomematicIO::$ScriptResponse = $this->xml('false');
        ob_start();
        $this->programme()->RequestAction('1234', 0);
        $this->assertStringContainsString('Error on start program.', (string) ob_get_clean());
        $this->assertFalse(@$this->programme()->StartProgram('1234'));
    }

    public function testInvalidIdentIsNotSentToCcu(): void
    {
        FakeHomematicIO::$Requests = [];
        ob_start();
        $this->programme()->RequestAction('1234); WriteLine("x"', 0);
        $this->assertStringContainsString('Invalid Ident.', (string) ob_get_clean());
        $this->assertFalse(@$this->programme()->StartProgram('abc'));
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testReadProgramsReturnsTrue(): void
    {
        FakeHomematicIO::$ScriptResponse = "4711\tNeues Programm\r\n" . $this->xml('');
        $this->programme()->RequestAction('ProgramEdited', json_encode([4711, true]));
        $this->assertTrue($this->programme()->ReadPrograms());
        $this->assertSame('Neues Programm', IPS_GetName(IPS_GetObjectIDByIdent('4711', $this->ProgrammeId)));
    }

    private function attribute(string $Name): array
    {
        return (new ReflectionMethod($this->programme(), 'ReadAttributeArray'))->invoke($this->programme(), $Name);
    }

    private function writeAttribute(string $Name, mixed $Value): void
    {
        $Method = is_array($Value) ? 'WriteAttributeArray' : 'WriteAttributeBoolean';
        (new ReflectionMethod($this->programme(), $Method))->invoke($this->programme(), $Name, $Value);
    }

    private function select(int $Id, bool $Use): void
    {
        FakeHomematicIO::$ScriptResponse = mb_convert_encoding('1234	Haustürklingel
5678	Dummy für Tasten
', 'ISO-8859-1', 'UTF-8') . $this->xml('');
        $this->programme()->RequestAction('ProgramEdited', json_encode([$Id, $Use]));
    }

    private function programme(): object
    {
        return IPS\InstanceManager::getInstanceInterface($this->ProgrammeId);
    }

    private function xml(string $State): string
    {
        return '<xml><exec>/x</exec><sessionId></sessionId><httpUserAgent>x</httpUserAgent>' . ($State == '' ? '' : '<State>' . $State . '</State>') . '</xml>';
    }
}
