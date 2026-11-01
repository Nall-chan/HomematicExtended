<?php

declare(strict_types=1);

require_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../LED16/module.php';

/**
 * Prüft das Modul HomeMatic LED16 (HM-OU-LED16 mit allen Kanälen in einer Instanz).
 */
class LED16Test extends HomematicModuleTestCase
{
    private const Address = 'JEQ0087605';
    private int $Id;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        $Children = [];
        for ($Channel = 0; $Channel <= 16; $Channel++) {
            $Children[] = self::Address . ':' . $Channel;
        }
        // Die Beschreibung gilt für alle Kanäle (Nachbildung der Datenpunkte der Tastenkanäle der HM-OU-LED16 und des Wartungskanals).
        FakeHomematicIO::$RpcResponses = [
            'getDeviceDescription'   => ['ADDRESS' => self::Address, 'CHILDREN' => $Children],
            'getParamsetDescription' => [
                'LED_STATUS'   => ['ID' => 'LED_STATUS', 'TYPE' => 'ENUM', 'OPERATIONS' => 7, 'VALUE_LIST' => ['OFF', 'RED', 'GREEN', 'ORANGE']],
                'PRESS_SHORT'  => ['ID' => 'PRESS_SHORT', 'TYPE' => 'ACTION', 'OPERATIONS' => 6],
                'INSTALL_TEST' => ['ID' => 'INSTALL_TEST', 'TYPE' => 'ACTION', 'OPERATIONS' => 6],
                'ALL_LEDS'     => ['ID' => 'ALL_LEDS', 'TYPE' => 'STRING', 'OPERATIONS' => 2],
                'UNREACH'      => ['ID' => 'UNREACH', 'TYPE' => 'BOOL', 'OPERATIONS' => 5],
                'AES_KEY'      => ['ID' => 'AES_KEY', 'TYPE' => 'INTEGER', 'OPERATIONS' => 1],
                'RSSI_DEVICE'  => ['ID' => 'RSSI_DEVICE', 'TYPE' => 'INTEGER', 'OPERATIONS' => 5]
            ],
            'getParamset:VALUES' => ['LED_STATUS' => 2, 'UNREACH' => false, 'RSSI_DEVICE' => -60]
        ];
        $this->Id = $this->createDevice(HomeMaticLED16::class, \HMExtended\GUID::LED16, 'HMLED', $IoId);
        IPS_SetProperty($this->Id, \HMExtended\Device\Property::Address, self::Address);
        IPS_ApplyChanges($this->Id);
    }

    public function testVariablesOfAllChannelsAreCreated(): void
    {
        foreach ([1, 7, 16] as $Led) {
            foreach (['LED_STATUS', 'PRESS_SHORT'] as $Datapoint) {
                $this->assertGreaterThan(0, $this->variable($Datapoint . '_' . $Led), $Datapoint . '_' . $Led);
            }
        }
        // Wartungskanal ohne Kanalnummer, nicht lesbare Datenpunkte (ALL_LEDS) und AES_KEY werden nicht angelegt
        $this->assertGreaterThan(0, $this->variable('UNREACH'));
        $this->assertGreaterThan(0, $this->variable('RSSI_DEVICE'));
        $this->assertFalse(@IPS_GetObjectIDByIdent('ALL_LEDS_1', $this->Id));
        $this->assertFalse(@IPS_GetObjectIDByIdent('AES_KEY', $this->Id));
        $this->assertSame('LED status 3', IPS_GetName($this->variable('LED_STATUS_3')));
        $this->assertSame('Key press short 16', IPS_GetName($this->variable('PRESS_SHORT_16')));
        $this->assertTrue(HasAction($this->variable('LED_STATUS_3')));
        // Der Tastendruck ist ein Ereignis (Boolean), bedienbar, weil die Beschreibung des Gerätes ihn als schreibbar meldet; der Installationstest wird nicht angelegt
        $this->assertSame(VARIABLETYPE_BOOLEAN, IPS_GetVariable($this->variable('PRESS_SHORT_3'))['VariableType']);
        $this->assertTrue(HasAction($this->variable('PRESS_SHORT_3')));
        $this->assertFalse(@IPS_GetObjectIDByIdent('INSTALL_TEST_3', $this->Id));
        // Die Aufzählung einer Boolean-Variable enthält beide Werte
        $Options = json_decode(IPS_GetVariable($this->variable('PRESS_SHORT_3'))['VariablePresentation']['OPTIONS'], true);
        $this->assertSame([false, true], array_column($Options, 'Value'));
        $this->assertFalse(HasAction($this->variable('RSSI_DEVICE_1')));
    }

    public function testValuesAreRead(): void
    {
        $this->assertSame(2, GetValueInteger($this->variable('LED_STATUS_5')));
        $this->assertFalse(GetValueBoolean($this->variable('UNREACH')));
        $this->assertSame(-60, GetValueInteger($this->variable('RSSI_DEVICE_2')));
    }

    public function testLedStatusIsAnEnumerationWithColors(): void
    {
        $Variable = IPS_GetVariable($this->variable('LED_STATUS_1'));
        $this->assertSame(VARIABLE_PRESENTATION_ENUMERATION, $Variable['VariablePresentation']['PRESENTATION']);
        $Options = json_decode($Variable['VariablePresentation']['OPTIONS'], true);
        $this->assertSame(['Off', 'Red', 'Green', 'Orange'], array_column($Options, 'Caption'));
        $this->assertSame([-1, 0xFF0000, 0x00CC00, 0xFF9900], array_column($Options, 'Color'));
    }

    public function testEventsOfAllChannelsUpdateTheVariables(): void
    {
        $Instance = IPS\InstanceManager::getInstanceInterface($this->Id);
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':3', 'VariableName' => 'LED_STATUS', 'VariableValue' => 1]));
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':12', 'VariableName' => 'LED_STATUS', 'VariableValue' => 3]));
        $this->assertSame(1, GetValueInteger($this->variable('LED_STATUS_3')));
        $this->assertSame(3, GetValueInteger($this->variable('LED_STATUS_12')));
        $this->assertSame(2, GetValueInteger($this->variable('LED_STATUS_4')));
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':0', 'VariableName' => 'UNREACH', 'VariableValue' => true]));
        $this->assertTrue(GetValueBoolean($this->variable('UNREACH')));
        // Tastendruck: jedes Ereignis setzt die Variable erneut auf true (Auslöser „bei Aktualisierung“)
        $Before = IPS_GetVariable($this->variable('PRESS_SHORT_5'))['VariableUpdated'];
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address . ':5', 'VariableName' => 'PRESS_SHORT', 'VariableValue' => true]));
        $this->assertTrue(GetValueBoolean($this->variable('PRESS_SHORT_5')));
        $this->assertGreaterThanOrEqual($Before, IPS_GetVariable($this->variable('PRESS_SHORT_5'))['VariableUpdated']);
    }

    public function testOperatingALedWritesTheDatapointOfItsChannel(): void
    {
        FakeHomematicIO::$Requests = [];
        RequestAction($this->variable('LED_STATUS_4'), 3);
        $Set = $this->setValueRequests();
        $this->assertCount(1, $Set);
        $this->assertSame([self::Address . ':4', 'LED_STATUS'], array_slice($Set[0]['Data'], 0, 2));
        $this->assertSame('3', (string) $Set[0]['Data'][2]);
        $this->assertSame(3, GetValueInteger($this->variable('LED_STATUS_4')));
        // Bedienen des Tastendrucks löst den Datenpunkt aus (true), die Variable setzt erst das Ereignis des Gerätes
        FakeHomematicIO::$Requests = [];
        RequestAction($this->variable('PRESS_SHORT_9'), false);
        $Set = $this->setValueRequests();
        $this->assertSame([self::Address . ':9', 'PRESS_SHORT'], array_slice($Set[0]['Data'], 0, 2));
        $this->assertTrue($Set[0]['Data'][2]);
        $this->assertFalse(GetValueBoolean($this->variable('PRESS_SHORT_9')));
    }

    public function testSetAllLedsSendsOneTelegramAndKeepsUnchangedLeds(): void
    {
        FakeHomematicIO::$Requests = [];
        // LED 1 orange, LED 2 unverändert (Statusvariable = 2, grün), LED 3 rot, Rest aus
        $Colors = array_fill(0, 16, 0);
        $Colors[0] = 3;
        $Colors[1] = 4;
        $Colors[2] = 1;
        $this->assertTrue(HMLED_SetAllLEDs($this->Id, json_encode($Colors)));
        $Set = $this->setValueRequests();
        $this->assertCount(1, $Set);
        $this->assertSame([self::Address . ':1', 'ALL_LEDS'], array_slice($Set[0]['Data'], 0, 2));
        $this->assertSame((string) (3 | (2 << 2) | (1 << 4)), (string) $Set[0]['Data'][2]);
        $this->assertSame(3, GetValueInteger($this->variable('LED_STATUS_1')));
        $this->assertSame(1, GetValueInteger($this->variable('LED_STATUS_3')));
        $this->assertSame(0, GetValueInteger($this->variable('LED_STATUS_4')));
    }

    public function testSetAllLedsRejectsInvalidInput(): void
    {
        FakeHomematicIO::$Requests = [];
        ob_start();
        $this->assertFalse(HMLED_SetAllLEDs($this->Id, '[1,2,3]'));
        $this->assertFalse(HMLED_SetAllLEDs($this->Id, json_encode(array_fill(0, 16, 5))));
        $this->assertFalse(HMLED_SetAllLEDs($this->Id, 'x'));
        ob_end_clean();
        $this->assertSame([], $this->setValueRequests());
    }

    private function variable(string $Ident): int
    {
        return IPS_GetObjectIDByIdent($Ident, $this->Id);
    }

    private function setValueRequests(): array
    {
        return array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'setValue'));
    }
}
