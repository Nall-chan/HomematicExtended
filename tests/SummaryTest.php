<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../ExtendedConfigurator/module.php';
require_once __DIR__ . '/../RFInterfaceSplitter/module.php';
require_once __DIR__ . '/../HomeMaticScript/module.php';
require_once __DIR__ . '/../Programme/module.php';
require_once __DIR__ . '/../WRInterface/module.php';
require_once __DIR__ . '/../ePaperStatusAnzeige/module.php';

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Konfigurator, Splitter und Singletons zeigen den Host des Homematic Sockets in der Zusammenfassung.
 */
class SummaryTest extends HomematicModuleTestCase
{
    private int $IoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
    }

    public static function moduleProvider(): array
    {
        return [
            'ExtendedConfigurator' => [HomeMaticExtendedConfigurator::class, \HMExtended\GUID::ExtendedConfigurator, 'HMEC', MODULETYPE_CONFIGURATOR],
            'RFInterfaceSplitter'  => [HomeMaticRFInterfaceSplitter::class, \HMExtended\GUID::RF_Interface_Splitter, 'HMRS', MODULETYPE_SPLITTER],
            'HomeMaticScript'      => [HomeMaticRemoteScript::class, \HMExtended\GUID::RemoteScript, 'HMRS', MODULETYPE_DEVICE],
            'Programme'            => [HomeMaticProgramme::class, \HMExtended\GUID::Programme, 'HMPR', MODULETYPE_DEVICE],
            'WRInterface'          => [HomeMaticWRInterface::class, \HMExtended\GUID::WR_Interface, 'HMWR', MODULETYPE_DEVICE]
        ];
    }

    #[DataProvider('moduleProvider')]
    public function testSummaryShowsHostOfSocket(string $Class, string $Guid, string $Prefix, int $Type): void
    {
        $Id = $this->createDevice($Class, $Guid, $Prefix, $this->IoId, $Type);
        IPS_ApplyChanges($Id);
        $this->assertSame('192.168.1.10', IPS_GetObject($Id)['ObjectSummary']);
    }

    #[DataProvider('moduleProvider')]
    public function testSummaryFollowsChangedHost(string $Class, string $Guid, string $Prefix, int $Type): void
    {
        $Id = $this->createDevice($Class, $Guid, $Prefix, $this->IoId, $Type);
        IPS_ApplyChanges($Id);
        IPS_SetProperty($this->IoId, 'Host', 'ccu.local');
        IPS_ApplyChanges($this->IoId);
        IPS\InstanceManager::getInstanceInterface($Id)->MessageSink(0, $this->IoId, IM_CHANGESETTINGS, []);
        $this->assertSame('ccu.local', IPS_GetObject($Id)['ObjectSummary']);
    }

    #[DataProvider('moduleProvider')]
    public function testSettingsOfOtherInstancesAreIgnored(string $Class, string $Guid, string $Prefix, int $Type): void
    {
        $Id = $this->createDevice($Class, $Guid, $Prefix, $this->IoId, $Type);
        IPS_ApplyChanges($Id);
        IPS_SetProperty($this->IoId, 'Host', 'ccu.local');
        IPS\InstanceManager::getInstanceInterface($Id)->MessageSink(0, $this->IoId + 1, IM_CHANGESETTINGS, []);
        $this->assertSame('192.168.1.10', IPS_GetObject($Id)['ObjectSummary']);
    }

    #[DataProvider('moduleProvider')]
    public function testSummaryIsEmptyWithoutParent(string $Class, string $Guid, string $Prefix, int $Type): void
    {
        $Id = $this->createDevice($Class, $Guid, $Prefix, $this->IoId, $Type);
        IPS_ApplyChanges($Id);
        IPS_DisconnectInstance($Id);
        IPS\InstanceManager::getInstanceInterface($Id)->MessageSink(0, $Id, FM_DISCONNECT, []);
        $this->assertSame('', IPS_GetObject($Id)['ObjectSummary']);
    }

    public function testDeviceKeepsAddressAsSummary(): void
    {
        $Id = $this->createDevice(HomeMaticDisEPWM55::class, \HMExtended\GUID::Dis_EP_WM55, 'HMEP', $this->IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, 'ABC0000001:3');
        IPS_ApplyChanges($Id);
        $this->assertSame('ABC0000001:3', IPS_GetObject($Id)['ObjectSummary']);
    }
}
