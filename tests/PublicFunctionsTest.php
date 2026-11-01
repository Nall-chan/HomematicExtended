<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/HMBase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Jede öffentliche Methode eines Moduls wird von Symcon als Instanz-Funktion (PREFIX_Funktion) angeboten, auch die der Basisklassen.
 * Der Test hält die gewollten Instanz-Funktionen je Modul in publicFunctions.json fest, damit keine interne Methode
 * (z.B. ein Fehlerbehandler) versehentlich öffentlich wird. Neue Funktionen müssen dort bewusst ergänzt werden
 * (neu erzeugen mit der Umgebungsvariable UPDATE_PUBLIC_FUNCTIONS=1 und das Ergebnis prüfen).
 */
class PublicFunctionsTest extends TestCase
{
    private const File = __DIR__ . '/publicFunctions.json';

    /** Methoden des SDK, die Symcon aufruft und nicht als Instanz-Funktion anbietet. */
    private const SdkMethods = [
        'Create', 'Destroy', 'ApplyChanges', 'ReceiveData', 'ForwardData', 'RequestAction', 'MessageSink', 'Translate',
        'GetConfigurationForm', 'GetConfigurationForParent', 'GetCompatibleParents', 'GetVisualizationTile', 'Migrate'
    ];

    public static function moduleProvider(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../*/module.json') as $File) {
            $Module = json_decode(file_get_contents($File), true);
            $Result[basename(dirname($File))] = [dirname($File), str_replace(' ', '', $Module['name'])];
        }
        return $Result;
    }

    #[DataProvider('moduleProvider')]
    public function testOnlyIntendedFunctionsArePublic(string $Folder, string $Class): void
    {
        $Names = self::functions($Folder, $Class);
        $Expected = json_decode(file_get_contents(self::File), true) ?: [];
        if (getenv('UPDATE_PUBLIC_FUNCTIONS')) {
            $Expected[basename($Folder)] = $Names;
            ksort($Expected);
            file_put_contents(self::File, json_encode($Expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $this->assertSame($Expected[basename($Folder)] ?? null, $Names, basename($Folder) . ': public functions changed');
    }

    public function testInternalMethodsAreNotPublic(): void
    {
        foreach (self::moduleProvider() as [$Folder, $Class]) {
            $Names = self::functions($Folder, $Class);
            $this->assertNotContains('ModulErrorHandler', $Names, $Class);
        }
    }

    /**
     * Symcon verlangt, dass alle Instanz-Funktionen gleichen Namens (PREFIX_Funktion) in der Library dieselbe Signatur haben,
     * auch dieselben Namen und Typen der Parameter und denselben Rückgabetyp. Sonst lässt sich die Funktion nicht registrieren
     * ("Cannot register function … Parameter 1 does not match").
     */
    public function testFunctionsWithTheSameNameHaveTheSameSignature(): void
    {
        $Signatures = [];
        foreach (self::moduleProvider() as [$Folder, $Class]) {
            require_once $Folder . '/module.php';
            foreach (self::functions($Folder, $Class) as $Name) {
                $Method = new ReflectionMethod($Class, $Name);
                $Parameters = array_map(fn (ReflectionParameter $Parameter): string => $Parameter->getType() . ' $' . $Parameter->getName(), $Method->getParameters());
                $Signatures[$Name][basename($Folder)] = $Name . '(' . implode(', ', $Parameters) . '): ' . $Method->getReturnType();
            }
        }
        foreach ($Signatures as $Name => $Modules) {
            $this->assertCount(1, array_unique($Modules), $Name . ' has different signatures: ' . json_encode($Modules));
            // Die Funktionen WriteValue… gibt es auch im Standardmodul von Symcon (Parameter: Instanz, Parameter, Value), die Namen müssen übereinstimmen
            if (preg_match('/^WriteValue(Boolean|Integer|Float|String)2?$/', $Name) === 1) {
                $this->assertStringContainsString('$Parameter, ', (string) reset($Modules), $Name);
            }
        }
    }

    private static function functions(string $Folder, string $Class): array
    {
        require_once $Folder . '/module.php';
        $Names = [];
        foreach ((new ReflectionClass($Class))->getMethods(ReflectionMethod::IS_PUBLIC) as $Method) {
            if (in_array($Method->getDeclaringClass()->getName(), ['IPSModule', 'IPSModuleStrict'], true) || $Method->isStatic()) {
                continue;
            }
            if (str_starts_with($Method->getName(), '__') || in_array($Method->getName(), self::SdkMethods, true)) {
                continue;
            }
            $Names[] = $Method->getName();
        }
        sort($Names);
        return $Names;
    }
}
