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
        $Run = function (array $_IPS) use ($Code): array
        {
            return eval(str_replace('HM_', 'FakeHm_', $Code));
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
        FakeHmFunctions::$Description = $Description;
        $Code = str_replace(['HM_', 'IPS_GetSystemLanguage'], ['FakeHm_', 'FakeIps_GetSystemLanguage'], $Action['action']);
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
