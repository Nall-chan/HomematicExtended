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
        IPS_SetProperty($this->IoId, 'WROpen', true);
        IPS_ApplyChanges($this->IoId);
        $this->WRId = $this->createDevice(HomeMaticWRInterface::class, \HMExtended\GUID::WR_Interface, 'HMWR', $this->IoId);
        IPS_ApplyChanges($this->WRId);
        FakeHomematicIO::$Requests = [];
    }

    public function testValuesAreStored(): void
    {
        $this->assertTrue($this->wr()->ReadWRInterface());
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId)));
        $this->assertSame(5, GetValueInteger(IPS_GetObjectIDByIdent('DUTY_CYCLE', $this->WRId)));
        $this->assertSame('1.2.3', GetValueString(IPS_GetObjectIDByIdent('FIRMWARE_VERSION', $this->WRId)));
        $this->assertSame(VARIABLE_PRESENTATION_VALUE_PRESENTATION, IPS_GetVariable(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId))['VariablePresentation']['PRESENTATION']);
        $this->assertSame([], IPS_GetVariable(IPS_GetObjectIDByIdent('FIRMWARE_VERSION', $this->WRId))['VariablePresentation']);
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
        $this->expectOutputString('Use of the HomeMatic Wired interface must be activated in the IO.' . PHP_EOL);
        $this->assertFalse($this->wr()->ReadWRInterface());
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testFailedRequestReturnsFalseAndKeepsValues(): void
    {
        $this->wr()->ReadWRInterface();
        unset(FakeHomematicIO::$RpcResponses['getLGWStatus']);
        $this->expectOutputString('Error on read WR-Interface.' . PHP_EOL);
        $this->assertFalse($this->wr()->ReadWRInterface());
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId)));
    }

    public function testDefaultIntervalIsSixtySeconds(): void
    {
        $this->assertSame(60, json_decode(IPS_GetConfiguration($this->WRId), true)[\HMExtended\Device\Property::Interval]);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($this->WRId)['InstanceStatus']);
    }

    public function testWithoutParentNoTimerAndNoError(): void
    {
        IPS_DisconnectInstance($this->WRId);
        IPS_ApplyChanges($this->WRId);
        FakeHomematicIO::$Requests = [];
        // Ohne Parent läuft kein Timer, es entsteht keine Ausgabe (jede Ausgabe wäre ein Fehler im Log).
        $this->expectOutputString('');
        $this->assertSame(0, $this->timerInterval());
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testDisabledWiredInterfaceIsReportedByTimerFunction(): void
    {
        IPS_SetProperty($this->IoId, 'WROpen', false);
        IPS_ApplyChanges($this->IoId);
        FakeHomematicIO::$Requests = [];
        $this->expectOutputString('Use of the HomeMatic Wired interface must be activated in the IO.' . PHP_EOL);
        $this->assertFalse($this->wr()->ReadWRInterface());
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testInactiveSocketKeepsTimerAndIsReported(): void
    {
        // Socket wird inaktiv (Verbindung zur CCU defekt): der Timer läuft weiter und meldet den Fehler bei jedem Abruf.
        $SetStatus = new ReflectionMethod(FakeHomematicIO::class, 'SetStatus');
        $SetStatus->invoke(IPS\InstanceManager::getInstanceInterface($this->IoId), IS_INACTIVE);
        $this->assertSame(60000, $this->timerInterval());
        FakeHomematicIO::$Requests = [];
        $this->expectOutputString('Instance has no active parent instance!' . PHP_EOL);
        $this->assertFalse($this->wr()->ReadWRInterface());
        $this->assertCount(0, FakeHomematicIO::$Requests);
    }

    public function testFailedRequestIsReportedByTimerFunction(): void
    {
        unset(FakeHomematicIO::$RpcResponses['getLGWStatus']);
        $this->expectOutputString('Error on read WR-Interface.' . PHP_EOL);
        $this->assertFalse($this->wr()->ReadWRInterface());
        // Der letzte gelesene Wert bleibt erhalten.
        $this->assertTrue(GetValueBoolean(IPS_GetObjectIDByIdent('CONNECTED', $this->WRId)));
    }

    public function testIntervalSetsStatus(): void
    {
        IPS_SetProperty($this->WRId, \HMExtended\Device\Property::Interval, 0);
        IPS_ApplyChanges($this->WRId);
        $this->assertSame(IS_INACTIVE, IPS_GetInstance($this->WRId)['InstanceStatus']);
        IPS_SetProperty($this->WRId, \HMExtended\Device\Property::Interval, 3);
        IPS_ApplyChanges($this->WRId);
        $this->assertSame(IS_EBASE + 2, IPS_GetInstance($this->WRId)['InstanceStatus']);
        IPS_SetProperty($this->WRId, \HMExtended\Device\Property::Interval, 60);
        IPS_ApplyChanges($this->WRId);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($this->WRId)['InstanceStatus']);
    }

    private function timerInterval(): int
    {
        $Method = new ReflectionMethod(HomeMaticWRInterface::class, 'GetTimerInterval');
        return $Method->invoke($this->wr(), \HMExtended\Device\Timer::ReadWRInterface);
    }

    private function wr(): object
    {
        return IPS\InstanceManager::getInstanceInterface($this->WRId);
    }
}
