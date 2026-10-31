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
        $this->assertTrue($this->programme()->ReadPrograms());
        $this->assertSame('Neues Programm', IPS_GetName(IPS_GetObjectIDByIdent('4711', $this->ProgrammeId)));
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
