<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../ePaperStatusAnzeige/module.php';

class ePaperStatusAnzeigeTest extends HomematicModuleTestCase
{
    private const Address = 'NEQ0000001:3';
    private int $DisplayId;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        $this->DisplayId = $this->createDevice(HomeMaticDisEPWM55::class, \HMExtended\GUID::Dis_EP_WM55, 'HMEP', $IoId);
    }

    public function testPlaceholderAddressIsInactive(): void
    {
        IPS_ApplyChanges($this->DisplayId);
        $this->assertStringStartsWith('XXX', IPS_GetProperty($this->DisplayId, \HMExtended\Device\Property::Address));
        $this->assertSame(IS_INACTIVE, $this->instanceStatus());
    }

    public function testAddressWithoutChannelSetsError(): void
    {
        $this->configure('NEQ0000001');
        $this->assertSame(IS_EBASE + 2, $this->instanceStatus());
    }

    public function testValidAddressIsActive(): void
    {
        $this->configure(self::Address);
        $this->assertSame(IS_ACTIVE, $this->instanceStatus());
    }

    public function testWriteLineSendsSubmitToConfiguredAddress(): void
    {
        $this->configure(self::Address);
        $this->assertTrue($this->display()->WriteValueDisplayLine(1, 'Hallo', 2));
        $Request = FakeHomematicIO::$Requests[0];
        $this->assertSame('setValue', $Request['MethodName']);
        $this->assertSame(0, $Request['Protocol']);
        $this->assertSame(self::Address, $Request['Data'][0]);
        $this->assertSame('SUBMIT', $Request['Data'][1]);
        $this->assertStringStartsWith('0x02,', $Request['Data'][2]);
        $this->assertStringEndsWith(',0x03', $Request['Data'][2]);
        // 'Hallo' = 48 61 6c 6c 6f
        $this->assertStringContainsString('0x48,0x61,0x6c,0x6c,0x6f', $Request['Data'][2]);
    }

    public function testNotifySendsData(): void
    {
        $this->configure(self::Address);
        $this->assertTrue($this->display()->WriteValueDisplayNotify(1, 2, 3, 1));
        $this->assertCount(1, FakeHomematicIO::$Requests);
    }

    public function testInvalidAddressDoesNotSend(): void
    {
        $this->configure('NEQ0000001');
        $this->assertFalse(@$this->display()->WriteValueDisplayLine(1, 'Hallo', 2));
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testTooLongTextIsCutToTwelveCharacters(): void
    {
        $this->configure(self::Address);
        $this->assertTrue($this->display()->WriteValueDisplayLine(1, 'ABCDEFGHIJKLMNOP', 0));
        // Zwölf Zeichen A-L, danach nichts mehr
        $this->assertStringContainsString('0x41,0x42,0x43,0x44,0x45,0x46,0x47,0x48,0x49,0x4a,0x4b,0x4c,0x0A', FakeHomematicIO::$Requests[0]['Data'][2]);
        $this->assertStringNotContainsString('0x4d', FakeHomematicIO::$Requests[0]['Data'][2]);
    }

    public function testInvalidLineIsRejected(): void
    {
        $this->configure(self::Address);
        $this->assertFalse(@$this->display()->WriteValueDisplayLine(0, 'Hallo', 0));
        $this->assertFalse(@$this->display()->WriteValueDisplayLine(4, 'Hallo', 0));
        $this->assertFalse(@$this->display()->WriteValueDisplayLineEx(4, 'Hallo', 0, 0, 0, 0, 0));
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testInvalidSignalParametersAreRejected(): void
    {
        $this->configure(self::Address);
        $this->assertFalse(@$this->display()->WriteValueDisplayNotify(7, 0, 0, 0));
        $this->assertFalse(@$this->display()->WriteValueDisplayNotify(0, 16, 0, 0));
        $this->assertFalse(@$this->display()->WriteValueDisplayNotify(0, 0, 16, 0));
        $this->assertFalse(@$this->display()->WriteValueDisplayNotify(0, 0, 0, 4));
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testUmlautsAreConverted(): void
    {
        $this->configure(self::Address);
        $this->assertTrue($this->display()->WriteValueDisplayLine(1, 'ÄÖÜäöüß:', 0));
        // [ # $ { | } _ :
        $this->assertStringContainsString('0x5b,0x23,0x24,0x7b,0x7c,0x7d,0x5f,0x3a', FakeHomematicIO::$Requests[0]['Data'][2]);
    }

    public function testInvalidIconIsRejected(): void
    {
        $this->configure(self::Address);
        $this->assertFalse(@$this->display()->WriteValueDisplayLine(1, 'Hallo', 10));
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    private function configure(string $Address): void
    {
        IPS_SetProperty($this->DisplayId, \HMExtended\Device\Property::Address, $Address);
        IPS_ApplyChanges($this->DisplayId);
    }

    private function display(): object
    {
        return IPS\InstanceManager::getInstanceInterface($this->DisplayId);
    }

    private function instanceStatus(): int
    {
        return IPS_GetInstance($this->DisplayId)['InstanceStatus'];
    }
}
