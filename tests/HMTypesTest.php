<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/HMTypes.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Prüft die Konsistenz der Gerätetabellen in libs/HMTypes.php.
 */
class HMTypesTest extends TestCase
{
    public function testGuidConstantsExistAsModules(): void
    {
        $Folders = $this->getModuleFolders();
        $Reflection = new ReflectionClass(\HMExtended\GUID::class);
        $DataFlowGuids = [
            \HMExtended\GUID::SendRpcToIO,
            \HMExtended\GUID::SendScriptToIO,
            \HMExtended\GUID::SendToRFInterfaceDevice,
            \HMExtended\GUID::SendToRFSplitter,
            \HMExtended\GUID::HmIO,
            \HMExtended\GUID::HmDevice
        ];
        foreach ($Reflection->getConstants() as $Name => $Guid) {
            if (in_array($Guid, $DataFlowGuids, true)) {
                continue;
            }
            $this->assertArrayHasKey(strtoupper($Guid), $Folders, 'GUID::' . $Name . ' is not a module of this library');
        }
    }

    public function testConfiguratorGuidsAreModules(): void
    {
        $Folders = $this->getModuleFolders();
        foreach (array_keys(\HMExtended\DeviceType::$GuidToType) as $Guid) {
            $this->assertArrayHasKey(strtoupper($Guid), $Folders);
        }
        foreach (array_keys(\HMExtended\Device\Address::$GuidToStaticAddress) as $Guid) {
            $this->assertArrayHasKey(strtoupper($Guid), $Folders);
        }
    }

    public function testInterfacesMatchProtocolConstants(): void
    {
        $this->assertSame(\HMExtended\CCU::BidCos_RF, \HMExtended\CCU::$Interfaces[\HMExtended\CCU::ProtocolBidCosRF]);
        $this->assertSame(\HMExtended\CCU::BidCos_WR, \HMExtended\CCU::$Interfaces[\HMExtended\CCU::ProtocolBidCosWR]);
        $this->assertSame(\HMExtended\CCU::HmIP, \HMExtended\CCU::$Interfaces[\HMExtended\CCU::ProtocolHmIP]);
        $this->assertSame(\HMExtended\CCU::Groups, \HMExtended\CCU::$Interfaces[\HMExtended\CCU::ProtocolGroups]);
    }

    /**
     * @see variableTableProvider
     */
    #[DataProvider('variableTableProvider')]
    public function testVariableDefinitions(string $Table, string $DeviceType, string $Ident, array $Definition): void
    {
        $this->assertContains(
            $Definition[0],
            [VARIABLETYPE_BOOLEAN, VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT, VARIABLETYPE_STRING, \HMExtended\Variables::VARIABLETYPE_NONE],
            $Table . '/' . $DeviceType . '/' . $Ident . ': invalid variable type'
        );
        if ($Definition[0] == \HMExtended\Variables::VARIABLETYPE_NONE) {
            return;
        }
        $this->assertArrayHasKey(1, $Definition, $Table . '/' . $DeviceType . '/' . $Ident . ': profile missing');
        $this->assertIsString($Definition[1]);
    }

    /**
     * @see variableTableProvider
     */
    #[DataProvider('variableTableProvider')]
    public function testVariableNamesAreTranslated(string $Table, string $DeviceType, string $Ident, array $Definition): void
    {
        if ($Definition[0] == \HMExtended\Variables::VARIABLETYPE_NONE) {
            $this->assertTrue(true);
            return;
        }
        $Name = $Definition[3] ?? null;
        if (!is_string($Name)) {
            $this->markTestSkipped($Ident . ' has no name');
        }
        $Folders = $this->getModuleFolders();
        foreach (\HMExtended\DeviceType::$GuidToType as $Guid => $Types) {
            if (!in_array($DeviceType, $Types, true)) {
                continue;
            }
            $Locale = json_decode(file_get_contents($Folders[strtoupper($Guid)] . '/locale.json'), true);
            $this->assertArrayHasKey($Name, $Locale['translations']['de'], $Table . '/' . $DeviceType . '/' . $Ident . ': "' . $Name . '" is not translated');
        }
    }

    public static function variableTableProvider(): array
    {
        $Result = [];
        foreach (['ValuesSet' => \HMExtended\ValuesSet::$Variables, 'ParamSet' => \HMExtended\ParamSet::$Variables] as $Table => $Devices) {
            foreach ($Devices as $DeviceType => $Variables) {
                foreach ($Variables as $Ident => $Definition) {
                    $Result[$Table . ' ' . $DeviceType . ' ' . $Ident] = [$Table, $DeviceType, (string) $Ident, $Definition];
                }
            }
        }
        return $Result;
    }

    public function testHmTypesAreMapped(): void
    {
        foreach (['FLOAT', 'BOOL', 'UINT'] as $Type) {
            $this->assertArrayHasKey($Type, \HMExtended\Variables::$HmTypeToIpsType);
        }
    }
    /**
     * @return array<string, string> Modul-GUID => Ordnername.
     */
    private function getModuleFolders(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../*/module.json') as $File) {
            $Module = json_decode(file_get_contents($File), true);
            $Result[strtoupper($Module['id'])] = dirname($File);
        }
        return $Result;
    }
}
