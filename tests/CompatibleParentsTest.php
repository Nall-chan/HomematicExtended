<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/HMBase.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * IPSModuleStrict kennt kein ConnectParent(), RequireParent() und ForceParent() mehr.
 * Jedes Modul mit Datenfluss zu einem Parent muss daher GetCompatibleParents() bereitstellen.
 */
class CompatibleParentsTest extends TestCase
{
    public static function moduleProvider(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../*/module.json') as $File) {
            $Module = json_decode(file_get_contents($File), true);
            if (empty($Module['parentRequirements'])) {
                continue;
            }
            $Result[basename(dirname($File))] = [dirname($File), $Module];
        }
        return $Result;
    }

    #[DataProvider('moduleProvider')]
    public function testModuleDescribesItsParent(string $Folder, array $Module): void
    {
        require_once $Folder . '/module.php';
        $Class = str_replace(' ', '', $Module['name']);
        $this->assertTrue(class_exists($Class), $Class);
        $Method = new ReflectionMethod($Class, 'GetCompatibleParents');
        $this->assertNotSame('IPSModuleStrict', $Method->getDeclaringClass()->getName(), $Class . ' must override GetCompatibleParents');

        $Instance = (new ReflectionClass($Class))->newInstanceWithoutConstructor();
        $Parents = json_decode($Instance->GetCompatibleParents(), true);
        $this->assertIsArray($Parents);
        // Geräte, Konfiguratoren und der Splitter teilen sich den Parent mit anderen Instanzen.
        $this->assertSame('connect', $Parents['type']);
        $Expected = in_array(\HMExtended\GUID::SendToRFSplitter, $Module['parentRequirements'], true)
            ? \HMExtended\GUID::RF_Interface_Splitter
            : \HMExtended\GUID::HmIO;
        $this->assertSame([$Expected], $Parents['moduleIDs']);
    }

    #[DataProvider('moduleProvider')]
    public function testNoRemovedParentFunctionsAreCalled(string $Folder): void
    {
        $this->assertDoesNotMatchRegularExpression('/->(ConnectParent|RequireParent|ForceParent)\(/', file_get_contents($Folder . '/module.php'));
    }

    public function testBaseClassesDoNotCallRemovedFunctions(): void
    {
        foreach (['HMBase.php', 'HMHeatingDevice.php'] as $File) {
            $this->assertDoesNotMatchRegularExpression('/->(ConnectParent|RequireParent|ForceParent)\(/', file_get_contents(__DIR__ . '/../libs/' . $File));
        }
    }
}
