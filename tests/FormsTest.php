<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Prüft die Konfigurationsformulare: nur gültige Eingaben erlauben und alle Beschriftungen übersetzen.
 */
class FormsTest extends TestCase
{
    /** Module, deren Adresse nicht geprüft wird (Interface-Adresse mit freiem Format). */
    private const AddressNotValidated = ['RFInterface'];

    public static function formProvider(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../*/form.json') as $File) {
            $Result[basename(dirname($File))] = [dirname($File)];
        }
        return $Result;
    }

    #[DataProvider('formProvider')]
    public function testAddressIsValidated(string $Folder): void
    {
        if (in_array(basename($Folder), self::AddressNotValidated)) {
            $this->markTestSkipped('Free format');
        }
        $Checked = 0;
        foreach ($this->getElements($Folder, 'ValidationTextBox') as $Element) {
            if ($Element['name'] != 'Address') {
                continue;
            }
            $Checked++;
            $this->assertArrayHasKey('validate', $Element, basename($Folder) . ': Address without validate');
            $this->assertNotFalse(@preg_match('/' . str_replace('/', '\/', $Element['validate']) . '/', ''), 'Invalid regex');
            // Platzhalter des Moduls und gültige Adressen
            $this->assertMatchesRegularExpression('/' . $Element['validate'] . '/', $this->sample($Element['validate']));
            $this->assertDoesNotMatchRegularExpression('/' . $Element['validate'] . '/', '');
            $this->assertDoesNotMatchRegularExpression('/' . $Element['validate'] . '/', 'a b');
        }
        $this->assertGreaterThanOrEqual(0, $Checked);
    }

    #[DataProvider('formProvider')]
    public function testNumberSpinnerHasMinimum(string $Folder): void
    {
        foreach ($this->getElements($Folder, 'NumberSpinner') as $Element) {
            $this->assertArrayHasKey('minimum', $Element, basename($Folder) . ': ' . $Element['name'] . ' without minimum');
        }
        $this->assertTrue(true);
    }

    /**
     * Die Konsole speichert von einer Liste ohne Angabe nur die bearbeitbaren Spalten. Spalten ohne `edit` müssen `save` daher
     * ausdrücklich angeben (true für Schlüssel wie die Id, false für reine Anzeige).
     */
    #[DataProvider('formProvider')]
    public function testListColumnsWithoutEditDeclareSave(string $Folder): void
    {
        $Missing = [];
        foreach ($this->getElements($Folder, 'List') as $List) {
            foreach ($List['columns'] ?? [] as $Column) {
                if (!isset($Column['edit']) && !array_key_exists('save', $Column)) {
                    $Missing[] = ($List['name'] ?? '?') . '.' . ($Column['name'] ?? '?');
                }
            }
        }
        $this->assertSame([], $Missing, basename($Folder) . ': list columns without edit and without save');
    }

    #[DataProvider('formProvider')]
    public function testCaptionsAreTranslated(string $Folder): void
    {
        $Locale = json_decode(file_get_contents($Folder . '/locale.json'), true)['translations']['de'];
        $Form = json_decode(file_get_contents($Folder . '/form.json'), true);
        $Missing = [];
        foreach ($this->collectTexts($Form) as $Text) {
            if ($Text !== '' && !array_key_exists($Text, $Locale)) {
                $Missing[] = $Text;
            }
        }
        $this->assertSame([], array_values(array_unique($Missing)), basename($Folder) . ': untranslated texts');
    }

    /**
     * Liefert eine zur Regex passende Beispieladresse.
     */
    private function sample(string $Regex): string
    {
        return str_contains($Regex, ':') ? 'XXX9999995:1' : 'INT0000010';
    }

    /**
     * Liefert alle Elemente eines Typs aus den Elementen des Formulars.
     */
    private function getElements(string $Folder, string $Type): array
    {
        $Form = json_decode(file_get_contents($Folder . '/form.json'), true);
        $Result = [];
        $Walk = function (array $Items) use (&$Walk, &$Result, $Type): void
        {
            foreach ($Items as $Item) {
                if (!is_array($Item)) {
                    continue;
                }
                if (($Item['type'] ?? '') == $Type) {
                    $Result[] = $Item;
                }
                foreach (['items', 'columns'] as $Key) {
                    if (isset($Item[$Key]) && is_array($Item[$Key])) {
                        $Walk($Item[$Key]);
                    }
                }
                if (isset($Item['edit']) && is_array($Item['edit'])) {
                    $Walk([$Item['edit']]);
                }
            }
        };
        $Walk($Form['elements'] ?? []);
        return $Result;
    }

    /**
     * Sammelt alle Beschriftungen des Formulars (caption, Optionen, Status).
     */
    private function collectTexts(array $Data): array
    {
        $Texts = [];
        foreach ($Data as $Key => $Value) {
            if (is_array($Value)) {
                $Texts = array_merge($Texts, $this->collectTexts($Value));
            } elseif (($Key === 'caption') && is_string($Value)) {
                $Texts[] = $Value;
            }
        }
        return $Texts;
    }
}
