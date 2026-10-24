<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../HomeMaticScript/module.php';

class HomeMaticScriptTest extends HomematicModuleTestCase
{
    private int $ScriptId;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        $this->ScriptId = $this->createDevice(HomeMaticRemoteScript::class, \HMExtended\GUID::RemoteScript, 'HMRS', $IoId);
        IPS_ApplyChanges($this->ScriptId);
    }

    public function testStaticAddressIsRegistered(): void
    {
        $this->assertSame(\HMExtended\Device\Address::REMOTESCRIPT, IPS_GetProperty($this->ScriptId, \HMExtended\Device\Property::Address));
        $this->assertSame(0, IPS_GetProperty($this->ScriptId, \HMExtended\Device\Property::Protocol));
    }

    public function testRunScriptReturnsOutputAndVariables(): void
    {
        FakeHomematicIO::$ScriptResponse = "Hello\r\n<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent><Value>42</Value></xml>";
        $Result = IPS\InstanceManager::getInstanceInterface($this->ScriptId)->RunScript('Value=42;');
        $this->assertIsArray($Result);
        $this->assertSame('42', $Result['Value']);
        $this->assertStringContainsString('Hello', $Result['Output']);
        $this->assertArrayNotHasKey('exec', $Result);
        $this->assertSame('Value=42;', FakeHomematicIO::$Requests[0]['Content']);
    }

    public function testRunScriptWithInvalidAnswerReturnsFalse(): void
    {
        FakeHomematicIO::$ScriptResponse = 'no xml';
        $this->assertFalse(@IPS\InstanceManager::getInstanceInterface($this->ScriptId)->RunScript('x'));
    }

    public function testMigrateReplacesPlaceholderAddress(): void
    {
        $Interface = IPS\InstanceManager::getInstanceInterface($this->ScriptId);
        $Config = json_decode($Interface->Migrate(json_encode(['configuration' => ['Address' => 'XXX9999993:2', 'Protocol' => 0]])), true)['configuration'];
        $this->assertSame(\HMExtended\Device\Address::REMOTESCRIPT, $Config['Address']);
    }

    public function testMigrateKeepsOtherConfiguration(): void
    {
        $Interface = IPS\InstanceManager::getInstanceInterface($this->ScriptId);
        $Json = json_encode(['configuration' => ['Address' => 'REMOTESCRIPT']]);
        $this->assertSame($Json, $Interface->Migrate($Json));
        $this->assertSame('invalid', $Interface->Migrate('invalid'));
    }
}
