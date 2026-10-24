<?php

declare(strict_types=1);

include_once __DIR__ . '/HeatingTestCase.php';
require_once __DIR__ . '/../HeatingDevice/module.php';

/**
 * Testklasse: Die Stubs können keine Ereignisse (Wochenplan) anlegen, der Wochenplan wird daher abgeschaltet.
 */
class TestHeatingDevice extends HomeMaticHeatingDevice
{
    protected function ReadPropertyBoolean(string $Name): bool
    {
        return ($Name == \HMExtended\Device\Property::Schedule) ? false : parent::ReadPropertyBoolean($Name);
    }

}

class HeatingDeviceTest extends HeatingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses = [
            'getParamsetDescription:VALUES' => $this->description([
                'CONTROL_MODE'    => 'INTEGER',
                'SET_TEMPERATURE' => 'FLOAT'
            ]),
            'getParamset:VALUES' => [
                'CONTROL_MODE'    => 1,
                'SET_TEMPERATURE' => 21.0
            ],
            'getParamsetDescription:MASTER' => $this->description([
                'DECALCIFICATION_TIME'    => 'INTEGER',
                'DECALCIFICATION_WEEKDAY' => 'INTEGER',
                'BOOST_TIME_PERIOD'       => 'INTEGER'
            ]),
            'getParamset:MASTER' => array_merge([
                'DECALCIFICATION_TIME'    => 1320,
                'DECALCIFICATION_WEEKDAY' => 6,
                'BOOST_TIME_PERIOD'       => 7
            ], $this->schedule('ENDTIME_%2$s_%1$d', 'TEMPERATURE_%2$s_%1$d', 1, 13))
        ];
        $this->DeviceId = $this->createDevice(TestHeatingDevice::class, \HMExtended\GUID::HeatingDevice, 'HMHD', $this->IoId);
        IPS_SetProperty($this->DeviceId, \HMExtended\Device\Property::Address, 'KEQ0000001');
        IPS_SetProperty($this->DeviceId, 'enable_DECALCIFICATION_TIME', true);
        IPS_ApplyChanges($this->DeviceId);
        FakeHomematicIO::$Requests = [];
    }

    public function testDeviceUsesRadioProtocol(): void
    {
        $this->assertSame(0, IPS_GetProperty($this->DeviceId, \HMExtended\Device\Property::Protocol));
    }

    public function testDecalcificationTimeIsConvertedToTimestamp(): void
    {
        $this->assertSame('22:00', date('H:i', GetValueInteger($this->variable('DECALCIFICATION_TIME'))));
    }

    public function testDecalcificationTimeIsSentInMinutes(): void
    {
        $this->assertSame('', $this->action('DECALCIFICATION_TIME', mktime(22, 30, 0, 1, 1, 2026)));
        $this->assertSame(['DECALCIFICATION_TIME' => 1350], $this->lastPut('MASTER'));
    }

    public function testSetTemperatureIsSentToChannelFour(): void
    {
        $this->assertSame('', $this->action('SET_TEMPERATURE', 22.5));
        $Requests = $this->requests('setValue');
        $this->assertCount(1, $Requests);
        $this->assertSame('KEQ0000001:4', $Requests[0]['Data'][0]);
        $this->assertSame('SET_TEMPERATURE', $Requests[0]['Data'][1]);
        $this->assertEquals(22.5, $Requests[0]['Data'][2]);
    }

    public function testParamsetDescriptionWithIso88591IsDecoded(): void
    {
        $Description = $this->description(['CONTROL_MODE' => 'INTEGER', 'SET_TEMPERATURE' => 'FLOAT']);
        $Description['SET_TEMPERATURE']['UNIT'] = 'PLACEHOLDER';
        $Json = str_replace('PLACEHOLDER', chr(0xB0) . 'C', json_encode($Description));
        $this->assertNull(json_decode($Json, true));
        FakeHomematicIO::$RpcResponses['getParamsetDescription:VALUES'] = $Json;
        IPS_DeleteVariable($this->variable('SET_TEMPERATURE'));
        IPS_ApplyChanges($this->DeviceId);
        $this->assertGreaterThan(0, @IPS_GetObjectIDByIdent('SET_TEMPERATURE', $this->DeviceId));
    }

    public function testInvalidIdentIsReportedToUser(): void
    {
        $this->assertStringContainsString('Invalid Ident.', $this->action('UNKNOWN', 1));
        $this->assertCount(0, $this->requests('putParamset'));
    }
}
