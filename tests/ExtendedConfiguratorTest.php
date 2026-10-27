<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../ExtendedConfigurator/module.php';
require_once __DIR__ . '/../DisplayStatusAnzeige/module.php';
require_once __DIR__ . '/../RFInterfaceSplitter/module.php';
require_once __DIR__ . '/../RFInterface/module.php';

/**
 * Platzhalter für Singleton-Module, die der Konfigurator nur über den Modulnamen anbietet.
 */
class ConfiguratorTestProgramme extends IPSModuleStrict
{
    protected function getTime(): int
    {
        return 0;
    }
}

class ConfiguratorTestScript extends IPSModuleStrict
{
    protected function getTime(): int
    {
        return 0;
    }
}

class ExtendedConfiguratorTest extends HomematicModuleTestCase
{
    private int $IoId;
    private int $ConfiguratorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        foreach ([[\HMExtended\GUID::Programme, 'ConfiguratorTestProgramme', 'CTPR'], [\HMExtended\GUID::RemoteScript, 'ConfiguratorTestScript', 'CTRS'], [\HMExtended\GUID::WR_Interface, 'ConfiguratorTestScript', 'CTWR'], [\HMExtended\GUID::HeatingGroup, 'ConfiguratorTestScript', 'CTHG'], [\HMExtended\GUID::HeatingGroupHmIP, 'ConfiguratorTestScript', 'CTHI']] as [$Guid, $Name, $Prefix]) {
            $this->registerTestModule($Name, $Guid, MODULETYPE_DEVICE, $Prefix, [
                'parentRequirements' => [\HMExtended\GUID::SendRpcToIO, \HMExtended\GUID::SendScriptToIO],
                'implemented'        => [self::DeviceDataFlow]
            ]);
        }
        $this->registerTestModule('HomeMaticRFInterfaceSplitter', \HMExtended\GUID::RF_Interface_Splitter, MODULETYPE_SPLITTER, 'HMRS', [
            'parentRequirements' => [\HMExtended\GUID::SendRpcToIO],
            'childRequirements'  => [\HMExtended\GUID::SendToRFInterfaceDevice],
            'implemented'        => [self::DeviceDataFlow, \HMExtended\GUID::SendToRFSplitter]
        ]);
        $this->registerTestModule('HomeMaticRFInterface', \HMExtended\GUID::RF_Interface, MODULETYPE_DEVICE, 'HMRF', [
            'parentRequirements' => [\HMExtended\GUID::SendToRFSplitter],
            'implemented'        => [\HMExtended\GUID::SendToRFInterfaceDevice]
        ]);
        FakeHomematicIO::$RpcResponses['listBidcosInterfaces'] = [
            ['ADDRESS' => 'GW1', 'TYPE' => 'HMLGW2', 'CONNECTED' => true],
            ['ADDRESS' => 'GW2', 'TYPE' => 'CCU2', 'CONNECTED' => true]
        ];
        $this->setDevices([
            ['ADDRESS' => 'LEQ1323301', 'TYPE' => 'HM-Dis-WM55'],
            ['ADDRESS' => 'LEQ1323301:1', 'TYPE' => 'KEY'],
            ['ADDRESS' => 'NEQ0711565', 'TYPE' => 'HM-Dis-EP-WM55'],
            ['ADDRESS' => 'KEQ0971739:2', 'TYPE' => 'POWERMETER'],
            ['ADDRESS' => 'IEQ0242934', 'TYPE' => 'HM-CC-TC'],
        ]);
        FakeHomematicIO::$ScriptResponse = "BidCos-RF\tLEQ1323301\tMein Display\r\nBidCos-RF\tNEQ0711565\tePaper Flur\tFlur\r\n<xml><exec>/x</exec><sessionId></sessionId><httpUserAgent>x</httpUserAgent></xml>";
        $this->ConfiguratorId = $this->createDevice(HomeMaticExtendedConfigurator::class, \HMExtended\GUID::ExtendedConfigurator, 'HMEC', $this->IoId, MODULETYPE_CONFIGURATOR);
        IPS_ApplyChanges($this->ConfiguratorId);
    }

    public function testRowsOfAllSupportedDevicesAreListed(): void
    {
        $Rows = $this->rowsByAddress();
        foreach (['LEQ1323301', 'NEQ0711565', 'KEQ0971739:2', 'IEQ0242934'] as $Address) {
            $this->assertArrayHasKey($Address, $Rows, $Address);
        }
        $this->assertSame('HM-Dis-WM55', $Rows['LEQ1323301']['type']);
        $this->assertSame('HM-Dis-EP-WM55', $Rows['NEQ0711565']['type']);
    }

    public function testCreateConfigurationContainsChannelOfOutput(): void
    {
        $Rows = $this->rowsByAddress();
        $this->assertSame('LEQ1323301:1', $Rows['LEQ1323301']['create']['configuration']['Address']);
        $this->assertSame(\HMExtended\GUID::Dis_WM55, $Rows['LEQ1323301']['create']['moduleID']);
        $this->assertSame('NEQ0711565:3', $Rows['NEQ0711565']['create']['configuration']['Address']);
        $this->assertSame('KEQ0971739:2', $Rows['KEQ0971739:2']['create']['configuration']['Address']);
        $this->assertSame('IEQ0242934', $Rows['IEQ0242934']['create']['configuration']['Address']);
    }

    public function testNameAndRoomComeFromCcu(): void
    {
        $Rows = $this->rowsByAddress();
        $this->assertSame('Mein Display', $Rows['LEQ1323301']['name']);
        $this->assertSame([], $Rows['LEQ1323301']['create']['location']);
        $this->assertSame('ePaper Flur(Flur)', $Rows['NEQ0711565']['longname']);
        $this->assertSame(['Flur'], $Rows['NEQ0711565']['create']['location']);
    }

    public function testSingletonRowsAreListed(): void
    {
        $Rows = $this->getRows();
        $Created = array_map(function ($Row)
        {
            return $Row['create']['moduleID'] ?? '';
        }, $Rows);
        $this->assertContains(\HMExtended\GUID::Programme, $Created);
        $this->assertContains(\HMExtended\GUID::RemoteScript, $Created);
        $Script = $Rows[array_search(\HMExtended\GUID::RemoteScript, $Created)];
        $this->assertSame(\HMExtended\Device\Address::REMOTESCRIPT, $Script['create']['configuration']['Address']);
    }

    public function testExistingInstanceIsMatchedByDeviceAddress(): void
    {
        // Das Display ist mit Kanal 2 konfiguriert, die Zeile des Gerätes (ohne Kanal) muss die Instanz zeigen.
        $Display = $this->createDevice(HomeMaticDisWM55::class, \HMExtended\GUID::Dis_WM55, 'HMDIS', $this->IoId);
        IPS_SetProperty($Display, \HMExtended\Device\Property::Address, 'LEQ1323301:2');
        IPS_ApplyChanges($Display);
        IPS_SetName($Display, 'Mein Display Instanz');
        $Rows = $this->rowsByAddress();
        $this->assertSame($Display, $Rows['LEQ1323301']['instanceID']);
        $this->assertSame('Mein Display Instanz', $Rows['LEQ1323301']['name']);
        $this->assertSame(0, $Rows['NEQ0711565']['instanceID']);
    }

    public function testInstanceWithoutDeviceOnCcuShowsHint(): void
    {
        $Display = $this->createDevice(HomeMaticDisWM55::class, \HMExtended\GUID::Dis_WM55, 'HMDIS', $this->IoId);
        IPS_SetProperty($Display, \HMExtended\Device\Property::Address, 'ZZZ9999999:1');
        IPS_ApplyChanges($Display);
        $Row = $this->findRowByInstance($Display);
        $this->assertSame('Device not found on the CCU', $Row['type']);
    }

    public function testInstanceWithPlaceholderShowsMigrationHint(): void
    {
        $Display = $this->createDevice(HomeMaticDisWM55::class, \HMExtended\GUID::Dis_WM55, 'HMDIS', $this->IoId);
        $Row = $this->findRowByInstance($Display);
        $this->assertSame('No device address configured (migration required)', $Row['type']);
    }

    public function testNewlyPairedDeviceAppearsWithoutApplyChanges(): void
    {
        $Before = $this->rowsByAddress();
        $this->assertArrayNotHasKey('HEQ0000001', $Before);
        $this->setDevices([
            ['ADDRESS' => 'LEQ1323301', 'TYPE' => 'HM-Dis-WM55'],
            ['ADDRESS' => 'HEQ0000001', 'TYPE' => 'HM-CC-RT-DN'],
        ]);
        $After = $this->rowsByAddress();
        $this->assertArrayHasKey('HEQ0000001', $After);
        $this->assertArrayNotHasKey('NEQ0711565', $After);
    }

    public function testClosedInterfaceHidesRadioDevices(): void
    {
        IPS_SetProperty($this->IoId, 'RFOpen', false);
        IPS_ApplyChanges($this->IoId);
        $Rows = $this->rowsByAddress();
        $this->assertArrayNotHasKey('LEQ1323301', $Rows);
        $this->assertArrayNotHasKey('NEQ0711565', $Rows);
    }

    public function testEveryRfInterfaceGetsARowWithTheSameChain(): void
    {
        $Rows = $this->rowsByAddress();
        foreach (['GW1', 'GW2'] as $Address) {
            $this->assertArrayHasKey($Address, $Rows, $Address);
            $Chain = $Rows[$Address]['create'];
            $this->assertSame(
                [\HMExtended\GUID::RF_Interface, \HMExtended\GUID::RF_Interface_Splitter, \HMExtended\GUID::HmIO],
                array_column($Chain, 'moduleID')
            );
            $this->assertSame($Address, $Chain[0]['configuration']['Address']);
        }
        $this->assertSame('HMLGW2', $Rows['GW1']['name']);
        $this->assertSame(0, $Rows['GW1']['instanceID']);
    }

    /**
     * Symcon lehnt die Kette ab ("Parameter type of Configuration does not match"), wenn `configuration` kein Objekt ist.
     */
    public function testEveryChainElementHasConfigurationObject(): void
    {
        $Json = IPS\InstanceManager::getInstanceInterface($this->ConfiguratorId)->GetConfigurationForm();
        $this->assertStringNotContainsString('"configuration":[]', $Json);
        $Chains = array_filter($this->getRows(), fn ($Row) => isset($Row['create'][0]));
        $this->assertNotEmpty($Chains);
        $Decoded = json_decode($Json);
        foreach ($Decoded->actions[0]->values as $Row) {
            if (!is_array($Row->create ?? null)) {
                continue;
            }
            foreach ($Row->create as $Element) {
                $this->assertIsObject($Element->configuration, $Row->address ?? '');
            }
        }
    }

    public function testChainContainsConfigurationOfExistingSocketAndSplitter(): void
    {
        IPS_SetProperty($this->IoId, 'IPOpen', true);
        IPS_ApplyChanges($this->IoId);
        $Splitter = IPS_CreateInstance(\HMExtended\GUID::RF_Interface_Splitter);
        IPS_ConnectInstance($Splitter, $this->IoId);
        IPS_SetProperty($Splitter, \HMExtended\Device\Property::Interval, 30);
        IPS_ApplyChanges($Splitter);
        $Decoded = json_decode(IPS\InstanceManager::getInstanceInterface($this->ConfiguratorId)->GetConfigurationForm());
        foreach ($Decoded->actions[0]->values as $Row) {
            if (($Row->address ?? '') != 'GW1') {
                continue;
            }
            $this->assertEquals(json_decode(IPS_GetConfiguration($this->IoId)), $Row->create[2]->configuration);
            $this->assertTrue($Row->create[2]->configuration->IPOpen);
            $this->assertEquals(json_decode(IPS_GetConfiguration($Splitter)), $Row->create[1]->configuration);
            $this->assertSame(30, $Row->create[1]->configuration->Interval);
            return;
        }
        $this->fail('Row GW1 not found');
    }

    public function testExistingRfInterfaceIsMatchedByAddress(): void
    {
        $Splitter = IPS_CreateInstance(\HMExtended\GUID::RF_Interface_Splitter);
        IPS_ConnectInstance($Splitter, $this->IoId);
        IPS_ApplyChanges($Splitter);
        $Interface = IPS_CreateInstance(\HMExtended\GUID::RF_Interface);
        IPS_ConnectInstance($Interface, $Splitter);
        IPS_SetProperty($Interface, \HMExtended\Device\Property::Address, 'GW2');
        IPS_ApplyChanges($Interface);
        IPS_SetName($Interface, 'Mein Gateway');
        $Rows = $this->rowsByAddress();
        $this->assertSame($Interface, $Rows['GW2']['instanceID']);
        $this->assertSame('Mein Gateway', $Rows['GW2']['name']);
        $this->assertSame(0, $Rows['GW1']['instanceID']);
    }

    public function testRfInterfaceWithoutGatewayOnCcuShowsHint(): void
    {
        $Splitter = IPS_CreateInstance(\HMExtended\GUID::RF_Interface_Splitter);
        IPS_ConnectInstance($Splitter, $this->IoId);
        IPS_ApplyChanges($Splitter);
        $Interface = IPS_CreateInstance(\HMExtended\GUID::RF_Interface);
        IPS_ConnectInstance($Interface, $Splitter);
        IPS_SetProperty($Interface, \HMExtended\Device\Property::Address, 'GONE');
        IPS_ApplyChanges($Interface);
        $this->assertSame('Device not found on the CCU', $this->findRowByInstance($Interface)['type']);
    }

    public function testDisabledProtocolsArePopupListWithButtonToIo(): void
    {
        $Popup = $this->findPopup();
        $this->assertNotNull($Popup);
        $Items = $Popup['popup']['items'];
        $Text = implode(' ', array_column($Items, 'caption'));
        foreach (['HomeMatic IP', 'HomeMatic groups'] as $Protocol) {
            $this->assertStringContainsString($Protocol, $Text);
        }
        $this->assertStringNotContainsString('HomeMatic BidCos-RF', $Text);
        // Die Library unterstützt keine Wired-Geräte, ein geschlossenes Wired-Interface ist kein Hinweis wert.
        $this->assertStringNotContainsString('HomeMatic BidCos-Wired', $Text);
        $Button = array_values(array_filter($Items, fn ($Item) => $Item['type'] == 'OpenObjectButton'));
        $this->assertCount(1, $Button);
        $this->assertSame($this->IoId, $Button[0]['objectID']);
    }

    public function testHmIpDevicesAreListedWhenIpInterfaceIsOpen(): void
    {
        FakeHomematicIO::$RpcResponses['listDevices@2'] = [
            ['ADDRESS' => '0001D3C99C1234:1', 'TYPE' => 'WEATHER_TRANSMIT'],
            ['ADDRESS' => '0001D3C99C1234:2', 'TYPE' => 'COND_SWITCH_TRANSMITTER_WIND_SPEED'],
            ['ADDRESS' => '0001DA49A00001:6', 'TYPE' => 'ENERGIE_METER_TRANSMITTER']
        ];
        $this->assertArrayNotHasKey('0001D3C99C1234:1', $this->rowsByAddress());
        IPS_SetProperty($this->IoId, 'IPOpen', true);
        IPS_ApplyChanges($this->IoId);
        $Rows = $this->rowsByAddress();
        $this->assertSame(\HMExtended\GUID::Weather, $Rows['0001D3C99C1234:1']['create']['moduleID']);
        $this->assertSame(2, $Rows['0001D3C99C1234:1']['create']['configuration']['Protocol']);
        $this->assertArrayNotHasKey('0001D3C99C1234:2', $Rows);
        $this->assertSame(\HMExtended\GUID::Powermeter, $Rows['0001DA49A00001:6']['create']['moduleID']);
        $this->assertSame(2, $Rows['0001DA49A00001:6']['create']['configuration']['Protocol']);
        // Der BidCos-Powermeter bleibt auf Funk.
        $this->assertSame(0, $Rows['KEQ0971739:2']['create']['configuration']['Protocol']);
    }

    public function testPowermeterInstanceOfOtherProtocolIsNotListedAsMissing(): void
    {
        require_once __DIR__ . '/../PowerMeter/module.php';
        FakeHomematicIO::$RpcResponses['listDevices@2'] = [['ADDRESS' => '0001DA49A00001:6', 'TYPE' => 'ENERGIE_METER_TRANSMITTER']];
        IPS_SetProperty($this->IoId, 'IPOpen', true);
        IPS_ApplyChanges($this->IoId);
        $PowerMeter = $this->createDevice(HomeMaticPowermeter::class, \HMExtended\GUID::Powermeter, 'HMPM', $this->IoId);
        IPS_SetProperty($PowerMeter, \HMExtended\Device\Property::Address, '0001DA49A00001:6');
        IPS_SetProperty($PowerMeter, \HMExtended\Device\Property::Protocol, 2);
        IPS_ApplyChanges($PowerMeter);
        $Rows = array_values(array_filter($this->getRows(), fn ($Row) => ($Row['instanceID'] ?? 0) == $PowerMeter));
        $this->assertCount(1, $Rows);
        $this->assertSame('0001DA49A00001:6', $Rows[0]['address']);
    }

    public function testNoPopupWhenAllProtocolsAreEnabled(): void
    {
        foreach (['RFOpen', 'IPOpen', 'GROpen'] as $Property) {
            IPS_SetProperty($this->IoId, $Property, true);
        }
        IPS_ApplyChanges($this->IoId);
        $this->assertNull($this->findPopup());
    }

    public function testConfiguratorHasNoIntervalProperty(): void
    {
        $this->assertArrayNotHasKey('Interval', json_decode(IPS_GetConfiguration($this->ConfiguratorId), true));
    }

    public function testOfflineGatewayFromConfigFileGetsRowWithChain(): void
    {
        $Rows = $this->rowsWithGatewayConfig();
        $this->assertArrayHasKey('MEQ0460859', $Rows);
        $this->assertSame('Schlafzimmer', $Rows['MEQ0460859']['name']);
        $this->assertStringContainsString('not connected', $Rows['MEQ0460859']['type']);
        $this->assertStringContainsString('192.168.201.203', $Rows['MEQ0460859']['type']);
        $this->assertSame(
            [\HMExtended\GUID::RF_Interface, \HMExtended\GUID::RF_Interface_Splitter, \HMExtended\GUID::HmIO],
            array_column($Rows['MEQ0460859']['create'], 'moduleID')
        );
        $this->assertSame('MEQ0460859', $Rows['MEQ0460859']['create'][0]['configuration']['Address']);
    }

    public function testConnectedGatewayIsNotListedTwice(): void
    {
        FakeHomematicIO::$RpcResponses['listBidcosInterfaces'] = [['ADDRESS' => 'MEQ0460859', 'TYPE' => 'HMLGW2', 'CONNECTED' => true]];
        $Count = 0;
        foreach ($this->getRowsWithGatewayConfig() as $Row) {
            $Count += (($Row['address'] ?? '') == 'MEQ0460859') ? 1 : 0;
        }
        $this->assertSame(1, $Count);
        $this->assertStringNotContainsString('not connected', $this->rowsWithGatewayConfig()['MEQ0460859']['type']);
        $this->assertSame('Schlafzimmer', $this->rowsWithGatewayConfig()['MEQ0460859']['name']);
    }

    public function testExistingOfflineGatewayInstanceIsMatched(): void
    {
        $Splitter = IPS_CreateInstance(\HMExtended\GUID::RF_Interface_Splitter);
        IPS_ConnectInstance($Splitter, $this->IoId);
        IPS_ApplyChanges($Splitter);
        $Interface = IPS_CreateInstance(\HMExtended\GUID::RF_Interface);
        IPS_ConnectInstance($Interface, $Splitter);
        IPS_SetProperty($Interface, \HMExtended\Device\Property::Address, 'MEQ0460859');
        IPS_ApplyChanges($Interface);
        $this->assertSame($Interface, $this->rowsWithGatewayConfig()['MEQ0460859']['instanceID']);
    }

    public function testWiredGatewayShowsDataInWrInterfaceRow(): void
    {
        IPS_SetProperty($this->IoId, 'WROpen', true);
        IPS_ApplyChanges($this->IoId);
        $Rows = $this->rowsWithGatewayConfig();
        $this->assertArrayHasKey('KEQ0112340', $Rows);
        $this->assertSame(\HMExtended\Device\Address::WRINTERFACE, $Rows['KEQ0112340']['create']['configuration']['Address']);
        $this->assertStringContainsString('192.168.201.201', $Rows['KEQ0112340']['type']);
        $this->assertSame('HMWLGW', $Rows['KEQ0112340']['name'], 'Ohne Namen in der Konfiguration wird der Typ angezeigt');
    }

    public function testMissingGatewayConfigKeepsRpcList(): void
    {
        $Rows = $this->rowsByAddress();
        $this->assertArrayHasKey('GW1', $Rows);
        $this->assertArrayNotHasKey('MEQ0460859', $Rows);
    }

    public function testRowCountIsSet(): void
    {
        $Form = $this->getForm();
        $this->assertSame(count($Form['actions'][0]['values']) + 1, $Form['actions'][0]['rowCount']);
    }

    public function testFormWithoutActiveParentShowsAlert(): void
    {
        IPS_DisconnectInstance($this->ConfiguratorId);
        $Form = $this->getForm();
        $Types = array_column($Form['actions'], 'type');
        $this->assertContains('PopupAlert', $Types);
        $this->assertSame([], $Form['actions'][0]['values']);
    }

    private function getRowsWithGatewayConfig(): array
    {
        $Gateway = "@@rfd\r\n[Interface 1]\r\nType = HMLGW2\r\nName = Schlafzimmer\r\nSerial Number = MEQ0460859\r\nIP Address = 192.168.201.203\r\n@@hs485d\r\n[Interface 0]\r\nType = HMWLGW\r\nName = \r\nSerial Number = KEQ0112340\r\nIP Address = 192.168.201.201\r\n";
        $Xml = '<xml><exec>/x</exec><sessionId></sessionId><httpUserAgent>x</httpUserAgent></xml>';
        FakeHomematicIO::$ScriptResponses = [FakeHomematicIO::$ScriptResponse, $Gateway . $Xml];
        return $this->getRows();
    }

    private function rowsWithGatewayConfig(): array
    {
        $Result = [];
        foreach ($this->getRowsWithGatewayConfig() as $Row) {
            if (($Row['address'] ?? '') != '') {
                $Result[$Row['address']] = $Row;
            }
        }
        return $Result;
    }

    private function findPopup(): ?array
    {
        foreach ($this->getForm()['actions'] as $Action) {
            if (($Action['type'] ?? '') == 'PopupAlert') {
                return $Action;
            }
        }
        return null;
    }

    private function setDevices(array $Devices): void
    {
        FakeHomematicIO::$RpcResponses['listDevices'] = $Devices;
    }

    private function getForm(): array
    {
        return json_decode(IPS\InstanceManager::getInstanceInterface($this->ConfiguratorId)->GetConfigurationForm(), true);
    }

    private function getRows(): array
    {
        return $this->getForm()['actions'][0]['values'];
    }

    private function rowsByAddress(): array
    {
        $Result = [];
        foreach ($this->getRows() as $Row) {
            if (($Row['address'] ?? '') != '') {
                $Result[$Row['address']] = $Row;
            }
        }
        return $Result;
    }

    private function findRowByInstance(int $InstanceId): array
    {
        foreach ($this->getRows() as $Row) {
            if (($Row['instanceID'] ?? 0) == $InstanceId) {
                return $Row;
            }
        }
        $this->fail('Row of instance ' . $InstanceId . ' not found');
    }
}
