<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Zeichnet die Aufrufe der Funktionen des Homematic-Moduls auf, die der Code der Aktionen verwendet.
 */
class FakeHmFunctions
{
    public static array $Calls = [];
    public static string $Description = '';
    /** @var array<int, array{address: string, led: ?int}> Instanzen des Homematic-Moduls (ID => Adresse und Zustand der LED, null = keine Variable LED_STATUS) */
    public static array $Instances = [];
    public static array $Requests = [];
}

function FakeHm_GetParamsetDescription(int $InstanceID): string
{
    return FakeHmFunctions::$Description;
}

function FakeHm_WriteValueString(int $InstanceID, string $Ident, string $Value): bool
{
    FakeHmFunctions::$Calls[] = ['String', $Ident, $Value];
    return true;
}

function FakeHm_WriteValueInteger(int $InstanceID, string $Ident, int $Value): bool
{
    FakeHmFunctions::$Calls[] = ['Integer', $Ident, $Value];
    return true;
}

function FakeHm_WriteValueBoolean(int $InstanceID, string $Ident, bool $Value): bool
{
    FakeHmFunctions::$Calls[] = ['Boolean', $Ident, $Value];
    return true;
}

function FakeHm_RequestStatus(int $InstanceID, string $Ident): bool
{
    FakeHmFunctions::$Requests[] = [$InstanceID, $Ident];
    return true;
}

function FakeIps_GetProperty(int $InstanceID, string $Name): string
{
    return $InstanceID == 12345 ? 'JEQ0087605:1' : (FakeHmFunctions::$Instances[$InstanceID]['address'] ?? '');
}

function FakeIps_GetInstanceListByModuleID(string $Guid): array
{
    return array_keys(FakeHmFunctions::$Instances);
}

function FakeIps_GetObjectIDByIdent(string $Ident, int $InstanceID): int|false
{
    return isset(FakeHmFunctions::$Instances[$InstanceID]['led']) ? 100000 + $InstanceID : false;
}

function FakeGetValueInteger(int $VariableID): int
{
    return FakeHmFunctions::$Instances[$VariableID - 100000]['led'];
}

function FakeIps_GetSystemLanguage(): string
{
    return 'en';
}

/**
 * Prüft die Aktionsdateien im Ordner actions: Pflichtfelder, eindeutige GUIDs, Übersetzungen und gültige PHP-Aktionen.
 */
class ActionsTest extends TestCase
{
    public static function actionProvider(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../actions/*.json') as $File) {
            $Result[basename($File)] = [$File];
        }
        return $Result;
    }

    #[DataProvider('actionProvider')]
    public function testActionIsValid(string $File): void
    {
        $Action = json_decode(file_get_contents($File), true);
        $this->assertIsArray($Action, 'Ungültiges JSON');
        foreach (['id', 'caption', 'form', 'restrictions', 'action', 'locale'] as $Key) {
            $this->assertArrayHasKey($Key, $Action, $Key);
        }
        $this->assertMatchesRegularExpression('/^\{[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}\}$/', $Action['id']);
        $this->assertNotEmpty($Action['restrictions']['moduleID']);
        $this->assertSame([1], $Action['restrictions']['objectType']);
        $Translations = $Action['locale']['de'] ?? [];
        $Texts = [$Action['caption']];
        if (isset($Action['description'])) {
            $Texts[] = $Action['description'];
        }
        // Formulare, die per PHP erzeugt werden, werden für ein gültiges und ein ungültiges Ziel ausgeführt
        $Elements = array_merge(
            $this->formElements($Action, '{"TEXT":{},"SUBMIT":{}}'),
            $this->formElements($Action, '{"STATE":{}}')
        );
        foreach ($Elements as $Element) {
            $Texts[] = $Element['caption'];
            foreach ($Element['options'] ?? [] as $Option) {
                $Texts[] = $Option['caption'];
            }
        }
        foreach ($Texts as $Text) {
            $this->assertArrayHasKey($Text, $Translations, 'Nicht übersetzt: ' . $Text);
        }
        // Jeder Parameter der Aktion muss im Formular vorkommen
        $Names = array_column($this->formElements($Action, '{"TEXT":{},"SUBMIT":{}}'), 'name');
        preg_match_all('/\$_IPS\[\'(?!TARGET)([A-Z0-9_]+)\'\]/', $Action['action'], $Matches);
        foreach (array_unique($Matches[1]) as $Parameter) {
            $this->assertContains($Parameter, $Names, 'Parameter ohne Formularfeld: ' . $Parameter);
        }
        // Der Code der Aktion muss gültiges PHP sein und Funktionen des Homematic-Moduls aufrufen
        token_get_all('<?php ' . $Action['action'], TOKEN_PARSE);
        $this->assertStringContainsString('HM_WriteValue', $Action['action']);
    }

    public function testDisplayActionWritesValuesAndSubmitsLast(): void
    {
        [$Calls, $Output] = $this->runAction('displayShow.json', $this->displayParams(['WINDOW' => true, 'BELL' => true, 'UNIT' => 3]), '{"TEXT":{},"SUBMIT":{}}');
        $this->assertSame('', $Output);
        $this->assertSame(['TEXT', 'Regen'], array_slice($Calls[0], 1));
        $this->assertContains(['Integer', 'UNIT', 3], $Calls);
        $this->assertContains(['Integer', 'BEEP', 1], $Calls);
        $this->assertContains(['Integer', 'BACKLIGHT', 2], $Calls);
        $this->assertContains(['Boolean', 'WINDOW', true], $Calls);
        $this->assertContains(['Boolean', 'BELL', true], $Calls);
        $this->assertNotContains(['Boolean', 'DOOR', true], $Calls);
        $this->assertSame(['Boolean', 'SUBMIT', true], end($Calls));
    }

    public function testDisplayActionFormatsTheText(): void
    {
        [$Calls] = $this->runAction('displayShow.json', $this->displayParams(['TEXT' => ' 21.55 °C']), '{"TEXT":{},"SUBMIT":{}}');
        $this->assertSame('21.55 ', $Calls[0][2]);
        [$Calls] = $this->runAction('displayShow.json', $this->displayParams(['TEXT' => '7']), '{"TEXT":{},"SUBMIT":{}}');
        $this->assertSame('    7', $Calls[0][2]);
        [$Calls] = $this->runAction('displayShow.json', $this->displayParams(['TEXT' => 'Warnung!']), '{"TEXT":{},"SUBMIT":{}}');
        $this->assertSame('Warnu', $Calls[0][2]);
    }

    public function testDisplayFormShowsOnlyAHintForOtherTargets(): void
    {
        $Action = json_decode(file_get_contents(__DIR__ . '/../actions/displayShow.json'), true);
        $Other = $this->formElements($Action, '{"STATE":{}}');
        $this->assertSame(['Label', 'Label'], array_column($Other, 'type'));
        $this->assertSame([], array_filter(array_column($Other, 'name')));
        $Display = $this->formElements($Action, '{"TEXT":{},"SUBMIT":{}}');
        $this->assertContains('TEXT', array_column($Display, 'name'));
        $this->assertContains('ARROW_DOWN', array_column($Display, 'name'));
        // Ohne Antwort der CCU gilt das Ziel als ungültig
        $this->assertSame(['Label', 'Label'], array_column($this->formElements($Action, 'invalid'), 'type'));
    }

    public function testLedActionCalculatesTheValueOfAllLeds(): void
    {
        // Belegt am HM-OU-LED16: LED n liegt auf den Bits 2(n-1) und 2(n-1)+1, 0 aus, 1 rot, 2 grün, 3 orange (Wert 35 = LED 1 orange, LED 3 grün)
        [$Calls, $Output] = $this->runAction('ledSetAll.json', $this->ledParams([1 => 3, 3 => 2]), '{"ALL_LEDS":{}}');
        $this->assertSame('', $Output);
        $this->assertSame([['String', 'ALL_LEDS', '35']], $Calls);
        [$Calls] = $this->runAction('ledSetAll.json', $this->ledParams([2 => 1]), '{"ALL_LEDS":{}}');
        $this->assertSame('4', $Calls[0][2]);
        [$Calls] = $this->runAction('ledSetAll.json', $this->ledParams(array_fill(1, 16, 3)), '{"ALL_LEDS":{}}');
        $this->assertSame('4294967295', $Calls[0][2]);
    }

    public function testLedActionKeepsUnchangedLeds(): void
    {
        // Kanäle 2 (rot) und 3 (grün) sind als Instanzen vorhanden, Kanal 1 ist die Instanz des Ziels ohne Variable
        FakeHmFunctions::$Instances = [
            21 => ['address' => 'JEQ0087605:2', 'led' => 1],
            22 => ['address' => 'JEQ0087605:3', 'led' => 2],
            23 => ['address' => 'XXX0000001:2', 'led' => 3]
        ];
        [$Calls] = $this->runAction('ledSetAll.json', $this->ledParams([1 => 3, 2 => 4, 3 => 4, 4 => 1]), '{"ALL_LEDS":{}}');
        // LED 1 orange (3), LED 2 unverändert rot (1 << 2), LED 3 unverändert grün (2 << 4), LED 4 rot (1 << 6)
        $this->assertSame([['String', 'ALL_LEDS', (string) (3 | (1 << 2) | (2 << 4) | (1 << 6))]], $Calls);
        $this->assertSame([[21, 'LED_STATUS'], [22, 'LED_STATUS']], FakeHmFunctions::$Requests);
        FakeHmFunctions::$Instances = [];
    }

    public function testLedActionDoesNotWriteWhenAnUnchangedLedHasNoInstance(): void
    {
        FakeHmFunctions::$Instances = [];
        [$Calls, $Output] = $this->runAction('ledSetAll.json', $this->ledParams([5 => 4]), '{"ALL_LEDS":{}}');
        $this->assertSame([], $Calls);
        $this->assertStringContainsString('LED 5', $Output);
    }

    public function testLedFormOffersUnchangedOnlyForExistingChannels(): void
    {
        FakeHmFunctions::$Instances = [
            21 => ['address' => 'JEQ0087605:2', 'led' => 0],
            22 => ['address' => 'JEQ0087605:7', 'led' => 0],
            23 => ['address' => 'JEQ0087605:9', 'led' => null]
        ];
        $Action = json_decode(file_get_contents(__DIR__ . '/../actions/ledSetAll.json'), true);
        $Elements = $this->formElements($Action, '{"ALL_LEDS":{}}');
        FakeHmFunctions::$Instances = [];
        $this->assertCount(16, $Elements);
        $WithUnchanged = [];
        foreach ($Elements as $Element) {
            if (in_array(4, array_column($Element['options'], 'value'), true)) {
                $WithUnchanged[] = $Element['name'];
                $this->assertSame(4, $Element['value']);
            } else {
                $this->assertSame(0, $Element['value']);
            }
        }
        $this->assertSame(['LED2', 'LED7'], $WithUnchanged);
    }

    public function testLedActionRejectsTargetsWithoutAllLeds(): void
    {
        [$Calls, $Output] = $this->runAction('ledSetAll.json', $this->ledParams([1 => 1]), '{"STATE":{}}');
        $this->assertSame([], $Calls);
        $this->assertStringContainsString('all LEDs', $Output);
        $Action = json_decode(file_get_contents(__DIR__ . '/../actions/ledSetAll.json'), true);
        $this->assertSame(['Label', 'Label'], array_column($this->formElements($Action, '{"STATE":{}}'), 'type'));
        $this->assertCount(16, $this->formElements($Action, '{"ALL_LEDS":{}}'));
    }

    public function testDisplayActionRejectsOtherChannels(): void
    {
        [$Calls, $Output] = $this->runAction('displayShow.json', $this->displayParams(), '{"STATE":{}}');
        $this->assertSame([], $Calls);
        $this->assertStringContainsString('DISPLAY', $Output);
    }

    public function testIdsAreUnique(): void
    {
        $Ids = [];
        foreach (glob(__DIR__ . '/../actions/*.json') as $File) {
            $Ids[] = json_decode(file_get_contents($File), true)['id'];
        }
        $this->assertSame(count($Ids), count(array_unique($Ids)));
    }

    private function ledParams(array $Colors): array
    {
        $Params = [];
        for ($Led = 1; $Led <= 16; $Led++) {
            $Params['LED' . $Led] = $Colors[$Led] ?? 0;
        }
        return $Params;
    }

    /**
     * Ersetzt die Funktionen von Symcon und des Homematic-Moduls im Code einer Aktion durch die simulierten Funktionen.
     */
    private function fakeFunctions(string $Code): string
    {
        return str_replace(['HM_', 'IPS_', 'GetValueInteger'], ['FakeHm_', 'FakeIps_', 'FakeGetValueInteger'], $Code);
    }

    /**
     * Elemente des Formulars einer Aktion. Besteht das Formular aus PHP-Code, wird er mit simulierten Funktionen ausgeführt.
     *
     * @param array $Action Die Aktion.
     * @param string $Description Beschreibung der Datenpunkte des Ziels (Antwort von HM_GetParamsetDescription).
     */
    private function formElements(array $Action, string $Description): array
    {
        if (!is_string($Action['form'][0] ?? null)) {
            return $Action['form'];
        }
        $Code = implode("\n", $Action['form']);
        token_get_all('<?php ' . $Code, TOKEN_PARSE);
        FakeHmFunctions::$Description = $Description;
        $Code = $this->fakeFunctions($Code);
        $Run = function (array $_IPS) use ($Code): array
        {
            return eval($Code);
        };
        return $Run(['TARGET' => 12345]);
    }

    /**
     * Führt den Code einer Aktion mit simulierten Funktionen des Homematic-Moduls aus.
     *
     * @return array Aufrufe (Funktion, Datenpunkt, Wert) und Ausgabe.
     */
    private function runAction(string $File, array $Params, string $Description): array
    {
        $Action = json_decode(file_get_contents(__DIR__ . '/../actions/' . $File), true);
        FakeHmFunctions::$Calls = [];
        FakeHmFunctions::$Requests = [];
        FakeHmFunctions::$Description = $Description;
        $Code = $this->fakeFunctions($Action['action']);
        $Run = function (array $_IPS) use ($Code): void
        {
            eval($Code);
        };
        ob_start();
        $Run(['TARGET' => 12345] + $Params);
        return [FakeHmFunctions::$Calls, ob_get_clean()];
    }

    private function displayParams(array $Changes = []): array
    {
        $Params = ['TEXT' => 'Regen', 'UNIT' => 0, 'BEEP' => 1, 'BACKLIGHT' => 2, 'ALARM_COUNT' => 0, 'SERVICE_COUNT' => 0];
        foreach (['BULB', 'SWITCH', 'WINDOW', 'DOOR', 'BLIND', 'SCENE', 'PHONE', 'BELL', 'CLOCK', 'ARROW_UP', 'ARROW_DOWN'] as $Symbol) {
            $Params[$Symbol] = false;
        }
        return $Changes + $Params;
    }
}
