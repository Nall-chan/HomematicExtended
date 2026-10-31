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
     * Alle Darstellungen der Heizungsmodule, mit und ohne Aktion, sofern die Aktion vom Datenpunkt abhängt.
     */
    public function testHeatingPresentations(): void
    {
        $Translate = fn (string $Text): string => $Text;
        $Checked = 0;
        foreach ([\HMExtended\ValuesSet::$Variables, \HMExtended\ParamSet::$Variables] as $Tables) {
            foreach ($Tables as $DeviceType => $Variables) {
                foreach ($Variables as $Ident => $Data) {
                    if ($Data[0] == \HMExtended\Variables::VARIABLETYPE_NONE) {
                        continue;
                    }
                    foreach (($Data[2] === '') ? [true, false] : [(bool) $Data[2]] as $HasAction) {
                        $Presentation = \HMExtended\HeatingPresentation::Get($Data[1], $Data[0], $HasAction, [], $Translate);
                        $this->checkPresentation($DeviceType . '/' . $Ident, $Data[0], $Presentation, $HasAction);
                        $Checked++;
                    }
                }
            }
        }
        // Variablen zum Speichern des Wochenplans.
        foreach ([\HMExtended\HeatingPresentation::ProfileHmIP, \HMExtended\HeatingPresentation::ProfileHM, \HMExtended\HeatingPresentation::Execute] as $Key) {
            $this->checkPresentation($Key, VARIABLETYPE_INTEGER, \HMExtended\HeatingPresentation::Get($Key, VARIABLETYPE_INTEGER, true, [], $Translate), true);
        }
        $this->assertGreaterThan(50, $Checked);
    }

    /**
     * Alle Darstellungen von PowerMeter, Weather, RF- und WR-Interface.
     */
    public function testDatapointPresentations(): void
    {
        $Translate = fn (string $Text): string => $Text;
        $Checked = 0;
        foreach (\HMExtended\DatapointPresentation::Units as $Unit => $Format) {
            foreach ([VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT] as $VariableType) {
                $this->checkPresentation($Unit, $VariableType, \HMExtended\DatapointPresentation::Get('', $Unit, $VariableType, false, $Translate), false);
                $Checked++;
            }
        }
        foreach (array_keys(\HMExtended\DatapointPresentation::Datapoints) as $Ident) {
            foreach ([VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT] as $VariableType) {
                $this->checkPresentation($Ident, $VariableType, \HMExtended\DatapointPresentation::Get($Ident, '', $VariableType, false, $Translate), false);
                $Checked++;
            }
        }
        foreach (array_keys(\HMExtended\DatapointPresentation::Booleans) as $Ident) {
            $this->checkPresentation($Ident, VARIABLETYPE_BOOLEAN, \HMExtended\DatapointPresentation::Get($Ident, '', VARIABLETYPE_BOOLEAN, false, $Translate), false);
            $Checked++;
        }
        $this->assertGreaterThan(30, $Checked);
        // GAS_POWER: Einheit m3 laut CCU-Firmware (rf_es_tx_wm), die CCU-Oberfläche zeigt ebenfalls m³.
        $this->assertSame(' m³', \HMExtended\DatapointPresentation::Get('GAS_POWER', 'm3', VARIABLETYPE_FLOAT, false, $Translate)['SUFFIX']);
        // Bedienbare Variablen und unbekannte Einheiten erhalten keine Darstellung.
        $this->assertSame([], \HMExtended\DatapointPresentation::Get('POWER', 'W', VARIABLETYPE_FLOAT, true, $Translate));
        $this->assertSame([], \HMExtended\DatapointPresentation::Get('X', 'xyz', VARIABLETYPE_FLOAT, false, $Translate));
    }

    public function testDatapointCaptionsAreTranslated(): void
    {
        foreach (['RAINING' => 'Weather', 'CONNECTED' => ['RFInterface', 'WRInterface']] as $Ident => $Modules) {
            foreach ((array) $Modules as $Module) {
                $Translations = json_decode(file_get_contents(__DIR__ . '/../' . $Module . '/locale.json'), true)['translations']['de'];
                foreach (\HMExtended\DatapointPresentation::Booleans[$Ident][1] as $Entry) {
                    $this->assertArrayHasKey($Entry[1], $Translations, $Module);
                }
            }
        }
        $Translations = json_decode(file_get_contents(__DIR__ . '/../Programme/locale.json'), true)['translations']['de'];
        $this->assertArrayHasKey('Start', $Translations);
    }

    public function testSetPointLimitsComeFromDescription(): void
    {
        $Translate = fn (string $Text): string => $Text;
        $Get = fn (array $Description): array => \HMExtended\HeatingPresentation::Get(\HMExtended\HeatingPresentation::SetPointTemperature, VARIABLETYPE_FLOAT, true, $Description, $Translate);
        $Intervals = fn (array $Presentation): array => array_map(fn ($Interval) => [$Interval['IntervalMinValue'], $Interval['ConstantValue']], json_decode($Presentation['INTERVALS'] ?? '[]', true));

        // HM-CC-RT-DN und HmIP: 4,5 = Aus, 30,5 = An.
        $RtDn = $Get(['MIN' => 4.5, 'MAX' => 30.5]);
        $this->assertSame([VARIABLE_PRESENTATION_SLIDER, 4.5, 30.5, 0.5], [$RtDn['PRESENTATION'], $RtDn['MIN'], $RtDn['MAX'], $RtDn['STEP_SIZE']]);
        $this->assertSame([[4.5, 'Off'], [30.5, 'On']], $Intervals($RtDn));
        // Verwendung und Gradient Temperatur wie die Vorlage „Raumtemperatur“.
        $this->assertSame([0, 1], [$RtDn['USAGE_TYPE'], $RtDn['GRADIENT_TYPE']]);
        $ReadOnly = \HMExtended\HeatingPresentation::Get(\HMExtended\HeatingPresentation::Temperature, VARIABLETYPE_FLOAT, false, [], $Translate);
        $this->assertSame([VARIABLE_PRESENTATION_VALUE_PRESENTATION, 1], [$ReadOnly['PRESENTATION'], $ReadOnly['USAGE_TYPE']]);
        $this->checkPresentation('RT-DN', VARIABLETYPE_FLOAT, $RtDn, true);

        // HM-CC-VG-1: Grenzen als Text, ohne Aus/An.
        $Vg1 = $Get(['MIN' => '5.0', 'MAX' => '30.0']);
        $this->assertSame([5.0, 30.0], [$Vg1['MIN'], $Vg1['MAX']]);
        $this->assertArrayNotHasKey('INTERVALS', $Vg1);

        // HM-CC-TC: Sonderwerte außerhalb des Bereichs.
        $CcTc = $Get(['MIN' => 6.0, 'MAX' => 30.0, 'SPECIAL' => [['ID' => 'VENT_CLOSED', 'VALUE' => 0], ['ID' => 'VENT_OPEN', 'VALUE' => 100]]]);
        $this->assertSame([6.0, 30.0], [$CcTc['MIN'], $CcTc['MAX']]);
        // json_encode schreibt 0.0 als 0, daher assertEquals.
        $this->assertEquals([[0, 'Valve closed'], [100, 'Valve open']], $Intervals($CcTc));
        $this->checkPresentation('CC-TC', VARIABLETYPE_FLOAT, $CcTc, true);

        // Ohne Beschreibung gilt 4,5 bis 30,5.
        $this->assertSame([4.5, 30.5], [$Get([])['MIN'], $Get([])['MAX']]);
    }

    public function testActiveProfileCountComesFromDescription(): void
    {
        $Translate = fn (string $Text): string => $Text;
        $Count = fn (array $Description): int => count(json_decode(\HMExtended\HeatingPresentation::Get(\HMExtended\HeatingPresentation::ProfileHmIP, VARIABLETYPE_INTEGER, true, $Description, $Translate)['OPTIONS'], true));
        $this->assertSame(3, $Count(['MIN' => 1, 'MAX' => 3]));
        $this->assertSame(6, $Count([]));
    }

    public function testReadOnlyEnumerationIsValuePresentation(): void
    {
        $Presentation = \HMExtended\HeatingPresentation::Get(\HMExtended\HeatingPresentation::ValveStateHmIP, VARIABLETYPE_INTEGER, false, [], fn (string $Text): string => $Text);
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, $Presentation['PRESENTATION']);
        $Intervals = json_decode($Presentation['INTERVALS'], true);
        $this->assertSame([1, 1, 'Run to start position'], [$Intervals[1]['IntervalMinValue'], $Intervals[1]['IntervalMaxValue'], $Intervals[1]['ConstantValue']]);
    }

    /**
     * Die Beschriftungen der Darstellungen werden im Modul übersetzt und müssen in der locale.json jedes Heizungsmoduls stehen.
     */
    public function testHeatingCaptionsAreTranslated(): void
    {
        $Captions = array_merge(\HMExtended\HeatingPresentation::Captions, array_values(\HMExtended\HeatingPresentation::SpecialCaptions));
        foreach (\HMExtended\HeatingPresentation::Options as [, $Entries]) {
            foreach ($Entries as $Entry) {
                if (!is_numeric($Entry[1])) {
                    $Captions[] = $Entry[1];
                }
            }
        }
        foreach (['HeatingDevice', 'HeatingGroup', 'HeatingGroupHmIP', 'ClimacontrolRegulator'] as $Module) {
            $Translations = json_decode(file_get_contents(__DIR__ . '/../' . $Module . '/locale.json'), true)['translations']['de'];
            $this->assertSame([], array_values(array_diff(array_unique($Captions), array_keys($Translations))), $Module . ': captions without translation');
        }
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
        // requestAction: true = nur mit Aktion, false = nur ohne Aktion, null = beides.
        if ($HasAction) {
            $this->assertNotFalse($Definition['requestAction'], $Name . ': ' . $Definition['caption'] . ' cannot be switched');
        } else {
            $this->assertNotTrue($Definition['requestAction'], $Name . ': ' . $Definition['caption'] . ' requires an action');
        }
        $Group = array_merge($Definition['lists'], $Definition['groups'][(string) $VariableType] ?? []);
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
