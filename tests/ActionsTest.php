<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        foreach ($Action['form'] as $Element) {
            $Texts[] = $Element['caption'];
            foreach ($Element['options'] ?? [] as $Option) {
                $Texts[] = $Option['caption'];
            }
        }
        foreach ($Texts as $Text) {
            $this->assertArrayHasKey($Text, $Translations, 'Nicht übersetzt: ' . $Text);
        }
        // Jeder Parameter der Aktion muss im Formular vorkommen
        $Names = array_column($Action['form'], 'name');
        preg_match_all('/\$_IPS\[\'(?!TARGET)([A-Z0-9_]+)\'\]/', $Action['action'], $Matches);
        foreach (array_unique($Matches[1]) as $Parameter) {
            $this->assertContains($Parameter, $Names, 'Parameter ohne Formularfeld: ' . $Parameter);
        }
        $Lines = array_filter(array_map('trim', explode("\n", $Action['action'])));
        foreach ($Lines as $Line) {
            $this->assertMatchesRegularExpression('/^HM_WriteValue(Float|Boolean|Integer|String)\(\$_IPS\[\'TARGET\'\], \'[A-Z_]+\',\s*.+\);$/', $Line);
        }
    }

    public function testIdsAreUnique(): void
    {
        $Ids = [];
        foreach (glob(__DIR__ . '/../actions/*.json') as $File) {
            $Ids[] = json_decode(file_get_contents($File), true)['id'];
        }
        $this->assertSame(count($Ids), count(array_unique($Ids)));
    }
}
