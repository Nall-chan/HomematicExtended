<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Prüft, dass jeder Text, der im Code mit Translate() ausgegeben wird, in der locale.json des Moduls übersetzt ist.
 */
class TranslationsTest extends TestCase
{
    public static function moduleProvider(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../*/module.json') as $File) {
            $Folder = dirname($File);
            $Result[basename($Folder)] = [$Folder];
        }
        return $Result;
    }

    #[DataProvider('moduleProvider')]
    public function testAllTranslatedTextsAreInLocale(string $Folder): void
    {
        $this->assertFileExists($Folder . '/locale.json');
        $Locale = json_decode(file_get_contents($Folder . '/locale.json'), true);
        $this->assertIsArray($Locale);
        $Translations = $Locale['translations']['de'] ?? [];
        $Missing = [];
        foreach ($this->collectFiles($Folder . '/module.php') as $File) {
            foreach ($this->translatedTexts($File) as $Text) {
                if (!array_key_exists($Text, $Translations)) {
                    $Missing[] = basename($File) . ': ' . $Text;
                }
            }
        }
        $this->assertSame([], array_values(array_unique($Missing)), basename($Folder) . ': texts without translation');
    }

    #[DataProvider('moduleProvider')]
    public function testLocaleHasNoEmptyTranslations(string $Folder): void
    {
        $Locale = json_decode(file_get_contents($Folder . '/locale.json'), true);
        foreach ($Locale['translations']['de'] ?? [] as $Key => $Value) {
            $this->assertNotSame('', trim((string) $Value), basename($Folder) . ': empty translation for "' . $Key . '"');
        }
    }

    /**
     * Liefert die Datei und alle per require_once eingebundenen Dateien der Library (Basisklassen).
     *
     * @return string[]
     */
    private function collectFiles(string $File, array $Seen = []): array
    {
        $Real = realpath($File);
        if (($Real === false) || in_array($Real, $Seen, true)) {
            return $Seen;
        }
        $Seen[] = $Real;
        $Content = file_get_contents($Real);
        // Module ohne Basisklasse der Library (z.B. RFInterface) laden HMBase nur für die Helper.
        if (preg_match('/class \w+ extends IPSModuleStrict/', $Content)) {
            return $Seen;
        }
        if (preg_match_all("/require_once __DIR__ \\. '([^']+\\.php)'/", $Content, $Matches)) {
            foreach ($Matches[1] as $Required) {
                if (str_contains($Required, 'HMTypes') || str_contains($Required, 'HMScriptParser')) {
                    continue;
                }
                $Seen = $this->collectFiles(dirname($Real) . $Required, $Seen);
            }
        }
        return $Seen;
    }

    /**
     * Liefert alle Texte, die im Code als Zeichenkette an Translate() übergeben werden.
     *
     * @return string[]
     */
    private function translatedTexts(string $File): array
    {
        $Content = file_get_contents($File);
        $Texts = [];
        if (preg_match_all("/->Translate\\(\\s*'((?:[^'\\\\\\\\]|\\\\\\\\.)*)'\\s*\\)/", $Content, $Matches)) {
            foreach ($Matches[1] as $Text) {
                $Texts[] = str_replace("\\'", "'", $Text);
            }
        }
        return $Texts;
    }
}
