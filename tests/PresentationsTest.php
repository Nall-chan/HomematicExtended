<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

require_once __DIR__ . '/../Systemvariablen/module.php';
require_once __DIR__ . '/../Alarmvariable/module.php';

/**
 * Prüft alle Darstellungen der Module Systemvariablen und Alarmvariable gegen die Parameterlisten aus IPS_GetPresentation (presentations.json):
 * erlaubter Variablentyp, nur bekannte Parameter, nur bekannte Felder in OPTIONS, bedienbare Darstellung bei Variablen mit Aktion.
 */
class PresentationsTest extends HomematicModuleTestCase
{
    private const Xml = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>';

    private array $Presentations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Presentations = json_decode(file_get_contents(__DIR__ . '/presentations.json'), true);
    }

    public function testSystemVariablePresentations(): void
    {
        $List = \HMExtended\SystemVariables::ParseList(
            "950\tAnwesenheit\t1089\t2\t2\tfalse\t65535\t\t\t\tabwesend\tanwesend\t\r\n" .
            "951\tOhne Namen\t1089\t2\t2\tfalse\t65535\t\t\t\t\t\t\r\n" .
            "27598\tDutyCycle\t1089\t4\t0\tfalse\t65535\t%\t-1\t100\t0\t1\t\r\n" .
            "14901\tText\t1089\t20\t11\tfalse\t65535\t\t\t\t1\t1\t\r\n" .
            "41\tServicemeldungen\t1089\t16\t0\ttrue\t65535\t\t0\t65000\t0\t1\t\r\n" .
            "3000\tModus\t1089\t16\t29\tfalse\t65535\t\t0\t2\t0\t1\tAus;Tag;Nacht\r\n"
        );
        $this->assertCount(6, $List);
        foreach ($List as $Id => $Variable) {
            $Presentation = \HMExtended\SystemVariables::Presentation($Variable, VARIABLE_PRESENTATION_ENUMERATION, VARIABLE_PRESENTATION_SWITCH, VARIABLE_PRESENTATION_VALUE_INPUT);
            // Systemvariablen sind schreibbar, die Darstellung muss bedienbar sein.
            $this->checkPresentation((string) $Id, \HMExtended\SystemVariables::VariableTypes[$Variable['ValueType']], $Presentation, true);
        }
    }

    public function testAlarmPresentations(): void
    {
        $IoId = $this->createFakeIo();
        $Id = $this->createDevice(HomeMaticAlarmvariable::class, \HMExtended\GUID::Alarmvariable, 'HMAV', $IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::AlarmID, 1253);
        // Ausgelöster Alarm, damit auch die Variablen des Auslösers angelegt werden.
        FakeHomematicIO::$ScriptResponse = "@@2026-10-28 08:20:13\r\ntrue\t2026-10-28 08:00:01\t2026-10-28 07:59:00\t2026-10-28 08:00:00\tFlur\tFlur\tx\r\n" . self::Xml;
        IPS_ApplyChanges($Id);
        $Checked = 0;
        foreach (IPS_GetChildrenIDs($Id) as $VariableId) {
            $Variable = IPS_GetVariable($VariableId);
            $this->checkPresentation(IPS_GetObject($VariableId)['ObjectIdent'], $Variable['VariableType'], $Variable['VariablePresentation'], $Variable['VariableAction'] > 0);
            $Checked++;
        }
        $this->assertSame(6, $Checked);
    }

    /**
     * Prüft eine Darstellung gegen die Parameterliste.
     */
    private function checkPresentation(string $Name, int $VariableType, array $Presentation, bool $HasAction): void
    {
        $this->assertArrayHasKey('PRESENTATION', $Presentation, $Name);
        $Definition = $this->Presentations[$Presentation['PRESENTATION']] ?? null;
        $this->assertNotNull($Definition, $Name . ': unknown presentation ' . $Presentation['PRESENTATION']);
        $this->assertContains($VariableType, $Definition['variableTypes'], $Name . ': variable type not allowed for ' . $Definition['caption']);
        if ($HasAction) {
            $this->assertTrue($Definition['requestAction'], $Name . ': ' . $Definition['caption'] . ' cannot be switched');
        }
        $Group = $Definition['groups'][(string) $VariableType] ?? [];
        foreach ($Presentation as $Parameter => $Value) {
            if ($Parameter === 'PRESENTATION') {
                continue;
            }
            $this->assertTrue(in_array($Parameter, $Definition['parameters'], true) || array_key_exists($Parameter, $Group), $Name . ': unknown parameter ' . $Parameter . ' for ' . $Definition['caption']);
            if (($Parameter === 'MULTILINE') && ($VariableType !== VARIABLETYPE_STRING)) {
                $this->fail($Name . ': MULTILINE only for strings');
            }
            if (isset($Group[$Parameter]) && count($Group[$Parameter])) {
                $this->assertIsString($Value, $Name . ': ' . $Parameter . ' must be a JSON string');
                foreach (json_decode($Value, true) as $Entry) {
                    $this->assertSame([], array_values(array_diff(array_keys($Entry), $Group[$Parameter])), $Name . ': unknown fields in ' . $Parameter);
                }
            }
        }
    }
}
