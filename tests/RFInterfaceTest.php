<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../RFInterfaceSplitter/module.php';
require_once __DIR__ . '/../RFInterface/module.php';

class RFInterfaceTest extends HomematicModuleTestCase
{
    private int $IoId;
    private int $SplitterId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        $this->registerTestModule('HomeMaticRFInterfaceSplitter', \HMExtended\GUID::RF_Interface_Splitter, MODULETYPE_SPLITTER, 'HMRS', [
            'parentRequirements' => [\HMExtended\GUID::SendRpcToIO],
            'childRequirements'  => [\HMExtended\GUID::SendToRFInterfaceDevice],
            'implemented'        => [self::DeviceDataFlow, \HMExtended\GUID::SendToRFSplitter]
        ]);
        $this->SplitterId = IPS_CreateInstance(\HMExtended\GUID::RF_Interface_Splitter);
        IPS_ConnectInstance($this->SplitterId, $this->IoId);
        IPS_SetProperty($this->SplitterId, \HMExtended\Device\Property::Interval, 10);
        IPS_ApplyChanges($this->SplitterId);
        $this->setInterfaces([$this->gateway('GW1', true, 3)]);
    }

    public function testInterfaceValuesAreStored(): void
    {
        $Interface = $this->createInterface('GW1');
        $this->read();
        $this->assertTrue($this->connected($Interface));
        $this->assertSame(3, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $Interface)));
        $this->assertSame('~Alert.Reversed', IPS_GetVariable(IPS_GetObjectIDByIdent('CONNECTED', $Interface))['VariableProfile']);
    }

    public function testOtherInterfacesAreNotChanged(): void
    {
        $First = $this->createInterface('GW1');
        $Second = $this->createInterface('GW2');
        $this->setInterfaces([$this->gateway('GW1', true, 3), $this->gateway('GW2', true, 7)]);
        $this->read();
        $this->assertSame(3, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $First)));
        $this->assertSame(7, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $Second)));
    }

    /**
     * Issue 13: Ein Interface, das offline ist, fehlt in der Antwort der CCU. CONNECTED darf nicht auf dem alten Wert bleiben.
     */
    public function testOfflineInterfaceGetsConnectedFalse(): void
    {
        $First = $this->createInterface('GW1');
        $Second = $this->createInterface('GW2');
        $this->setInterfaces([$this->gateway('GW1', true, 3), $this->gateway('GW2', true, 7)]);
        $this->read();
        $this->assertTrue($this->connected($Second));

        $this->setInterfaces([$this->gateway('GW1', true, 4)]);
        $this->read();
        $this->assertTrue($this->connected($First));
        $this->assertSame(4, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $First)));
        $this->assertFalse($this->connected($Second));
        // Der letzte bekannte Wert der Auslastung bleibt erhalten
        $this->assertSame(7, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $Second)));
    }

    public function testInterfaceComesBackOnline(): void
    {
        $Second = $this->createInterface('GW2');
        $this->setInterfaces([$this->gateway('GW2', true, 7)]);
        $this->read();
        $this->setInterfaces([$this->gateway('GW1', true, 3)]);
        $this->read();
        $this->assertFalse($this->connected($Second));
        $this->setInterfaces([$this->gateway('GW2', true, 8)]);
        $this->read();
        $this->assertTrue($this->connected($Second));
    }

    public function testAllInterfacesOfflineSetsConnectedFalse(): void
    {
        $First = $this->createInterface('GW1');
        $this->read();
        $this->assertTrue($this->connected($First));
        $this->setInterfaces([]);
        $this->read();
        $this->assertFalse($this->connected($First));
    }

    public function testFailedRequestKeepsValues(): void
    {
        $First = $this->createInterface('GW1');
        $this->read();
        unset(FakeHomematicIO::$RpcResponses['listBidcosInterfaces']);
        @$this->splitter()->ReadRFInterfaces();
        $this->assertTrue($this->connected($First));
    }

    public function testMissingInterfaceWithoutVariableIsIgnored(): void
    {
        $Second = $this->createInterface('GW2');
        $this->read();
        $this->assertSame(0, @IPS_GetObjectIDByIdent('CONNECTED', $Second) ?: 0);
    }

    public function testFilterContainsOwnAddressAndAllMessage(): void
    {
        $Interface = $this->createInterface('GW.1');
        $Filter = IPS\InstanceManager::getInstanceInterface($Interface)->GetReceiveDataFilter();
        $this->assertStringContainsString('GW\.1', $Filter);
        $this->assertStringContainsString('\*ALL\*', $Filter);
    }

    private function gateway(string $Address, bool $Connected, int $DutyCycle): array
    {
        return ['ADDRESS' => $Address, 'TYPE' => 'HM-LAN', 'CONNECTED' => $Connected, 'DEFAULT' => false, 'DESCRIPTION' => '', 'DUTY_CYCLE' => $DutyCycle, 'FIRMWARE_VERSION' => ''];
    }

    private function setInterfaces(array $Interfaces): void
    {
        FakeHomematicIO::$RpcResponses['listBidcosInterfaces'] = $Interfaces;
    }

    private function splitter(): object
    {
        return IPS\InstanceManager::getInstanceInterface($this->SplitterId);
    }

    private function read(): void
    {
        $this->splitter()->ReadRFInterfaces();
    }

    private function createInterface(string $Address): int
    {
        $this->registerTestModule('HomeMaticRFInterface', \HMExtended\GUID::RF_Interface, MODULETYPE_DEVICE, 'HMRI', [
            'parentRequirements' => [\HMExtended\GUID::SendToRFSplitter],
            'implemented'        => [\HMExtended\GUID::SendToRFInterfaceDevice]
        ]);
        $Id = IPS_CreateInstance(\HMExtended\GUID::RF_Interface);
        IPS_ConnectInstance($Id, $this->SplitterId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, $Address);
        IPS_ApplyChanges($Id);
        return $Id;
    }

    private function connected(int $InterfaceId): bool
    {
        return GetValueBoolean(IPS_GetObjectIDByIdent('CONNECTED', $InterfaceId));
    }
}
