<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../WRInterface/module.php';

class WRInterfaceTest extends HomematicModuleTestCase
{
    private int $IoId;
    private int $WRId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses['getLGWStatus'] = [
            'CONNECTED'        => true,
            'DUTY_CYCLE'       => 5,
            'FIRMWARE_VERSION' => '1.2.3',
            'EMPTY'            => ''
        ];
        $this->WRId = $this->createDevice(HomeMaticWRInterface::class, \HMExtended\GUID::WR_Interface, 'HMWR', $this->IoId);
        IPS_SetProperty($this->IoId, 'WROpen', true);
        IPS_ApplyChanges($this->IoId);
        IPS_ApplyChanges($this->WRId);
    }

    public function testValuesAreStored(): void
    {
        $this->assertTrue($this->wr()->ReadWRInterface());
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId)));
        $this->assertSame(5, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $this->WRId)));
        $this->assertSame('1.2.3', GetValueString(IPS_GetObjectIDByIdent('FIRMWARE_VERSION', $this->WRId)));
        $this->assertSame('~Alert.Reversed', IPS_GetVariable(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId))['VariableProfile']);
    }

    public function testEmptyValuesAreIgnored(): void
    {
        $this->wr()->ReadWRInterface();
        $this->assertSame(0, @IPS_GetObjectIDByIdent('EMPTY', $this->WRId) ?: 0);
    }

    public function testRequestUsesWiredProtocol(): void
    {
        $this->wr()->ReadWRInterface();
        $Request = FakeHomematicIO::$Requests[0];
        $this->assertSame('getLGWStatus', $Request['MethodName']);
        $this->assertSame(1, $Request['Protocol']);
    }

    public function testDisabledWiredInterfaceIsReported(): void
    {
        IPS_SetProperty($this->IoId, 'WROpen', false);
        IPS_ApplyChanges($this->IoId);
        FakeHomematicIO::$Requests = [];
        $this->assertFalse(@$this->wr()->ReadWRInterface());
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testFailedRequestReturnsFalseAndKeepsValues(): void
    {
        $this->wr()->ReadWRInterface();
        unset(FakeHomematicIO::$RpcResponses['getLGWStatus']);
        $this->assertFalse(@$this->wr()->ReadWRInterface());
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId)));
    }

    public function testIntervalSetsStatus(): void
    {
        $this->assertSame(IS_INACTIVE, IPS_GetInstance($this->WRId)['InstanceStatus']);
        IPS_SetProperty($this->WRId, \HMExtended\Device\Property::Interval, 3);
        IPS_ApplyChanges($this->WRId);
        $this->assertSame(IS_EBASE + 2, IPS_GetInstance($this->WRId)['InstanceStatus']);
        IPS_SetProperty($this->WRId, \HMExtended\Device\Property::Interval, 60);
        IPS_ApplyChanges($this->WRId);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($this->WRId)['InstanceStatus']);
    }

    private function wr(): object
    {
        return IPS\InstanceManager::getInstanceInterface($this->WRId);
    }
}
