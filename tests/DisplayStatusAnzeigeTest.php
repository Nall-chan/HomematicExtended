<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../DisplayStatusAnzeige/module.php';

/**
 * Testklasse: Macht die Konvertierung der Display-Daten für den Test aufrufbar.
 */
class TestDisWM55 extends HomeMaticDisWM55
{
    public function TestConvert(array $Data): string
    {
        return $this->ConvertDisplayData($Data);
    }

}

class DisplayStatusAnzeigeTest extends HomematicModuleTestCase
{
    private const Address = 'DIS0000001:1';
    private int $DisplayId;
    private int $ScriptId;

    protected function setUp(): void
    {
        parent::setUp();
        $IoId = $this->createFakeIo();
        FakeHomematicIO::$ScriptResponse = '<xml><exec>/x</exec><sessionId>0</sessionId><httpUserAgent>x</httpUserAgent></xml>';
        $this->DisplayId = $this->createDevice(TestDisWM55::class, \HMExtended\GUID::Dis_WM55, 'HMDIS', $IoId);
        // Display-Script: eine Zeile mit der aktuellen Seite
        $this->ScriptId = IPS_CreateScript(0);
        IPS_SetScriptContent($this->ScriptId, '<?php echo json_encode([1 => ["Text" => "P" . $_IPS["PAGE"], "Icon" => 0, "Color" => 1]]);');
    }

    public function testWithoutAddressIsInactive(): void
    {
        IPS_ApplyChanges($this->DisplayId);
        $this->assertSame(IS_INACTIVE, $this->instanceStatus($this->DisplayId));
    }

    public function testStandardTriggersAreActive(): void
    {
        $this->configure();
        $this->assertSame(IS_ACTIVE, $this->instanceStatus($this->DisplayId));
        $Filter = IPS\InstanceManager::getInstanceInterface($this->DisplayId)->GetReceiveDataFilter();
        foreach (['DIS0000001:1', 'DIS0000001:2', 'PRESS_SHORT', 'PRESS_LONG'] as $Part) {
            $this->assertStringContainsString($Part, $Filter);
        }
    }

    public function testPressAlwaysSendsToConfiguredAddress(): void
    {
        $this->configure();
        $this->press('DIS0000001:2', 'PRESS_LONG');
        $this->assertStringContainsString('BidCos-RF.DIS0000001:1.SUBMIT', $this->lastScript());
        $this->assertStringNotContainsString('DIS0000001:2.SUBMIT', $this->lastScript());
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->assertStringContainsString('BidCos-RF.DIS0000001:1.SUBMIT', $this->lastScript());
    }

    public function testAddressWithoutChannelSetsError(): void
    {
        $this->configure([\HMExtended\Device\Property::Address => 'DIS0000001']);
        $this->assertSame(IS_EBASE + 6, $this->instanceStatus($this->DisplayId));
    }

    public function testExpertTriggerWithoutAddressIsInactive(): void
    {
        $Variable = $this->createHmDeviceVariable('OTH0000001:1', 'PRESS_SHORT');
        $this->configure([\HMExtended\Device\Property::Address => '', 'ExpertTriggers' => json_encode([['VariableID' => $Variable, 'Action' => 0]])]);
        $this->assertSame(IS_INACTIVE, $this->instanceStatus($this->DisplayId));
    }

    public function testPagesCycleUpAndDown(): void
    {
        $this->configure();
        // Text 'P1' = 0x50,0x31
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->assertStringContainsString('0x50,0x31', $this->lastScript());
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->assertStringContainsString('0x50,0x33', $this->lastScript());
        $this->press('DIS0000001:1', 'PRESS_SHORT'); // nach Seite 3 wieder Seite 1 (runter = weiter)
        $this->assertStringContainsString('0x50,0x31', $this->lastScript());
        $this->press('DIS0000001:2', 'PRESS_SHORT'); // hoch = zurück: von Seite 1 auf die letzte Seite
        $this->assertStringContainsString('0x50,0x33', $this->lastScript());
    }

    public function testActionButtonsDoNotChangePage(): void
    {
        $this->configure();
        $this->press('DIS0000001:2', 'PRESS_LONG');
        // Ohne vorherige Seitentaste wird dem Script Seite 1 übergeben, nie 0.
        $this->assertStringContainsString('0x50,0x31', $this->lastScript());
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->press('DIS0000001:1', 'PRESS_LONG');
        $this->assertStringContainsString('0x50,0x32', $this->lastScript());
    }

    public function testPageIsKeptOnApplyChanges(): void
    {
        $this->configure();
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        $this->press('DIS0000001:1', 'PRESS_SHORT');
        IPS_ApplyChanges($this->DisplayId);
        $this->press('DIS0000001:2', 'PRESS_LONG');
        $this->assertStringContainsString('0x50,0x32', $this->lastScript());
    }

    public function testRejectedSubmitIsRepeatedImmediately(): void
    {
        $this->configure();
        FakeHomematicIO::$ScriptResponses = [$this->submitResponse(false), $this->submitResponse(true)];
        $this->press('DIS0000001:2', 'PRESS_SHORT');
        $Scripts = $this->scripts();
        $this->assertCount(2, $Scripts);
        $this->assertSame($Scripts[0], $Scripts[1]);
    }

    public function testRejectedSubmitIsRepeatedOnlyOnce(): void
    {
        $this->configure();
        FakeHomematicIO::$ScriptResponse = $this->submitResponse(false);
        $this->press('DIS0000001:2', 'PRESS_SHORT');
        $this->assertCount(2, $this->scripts());
    }

    public function testAcceptedSubmitIsSentOnce(): void
    {
        $this->configure();
        FakeHomematicIO::$ScriptResponse = $this->submitResponse(true);
        $this->press('DIS0000001:2', 'PRESS_SHORT');
        $this->assertCount(1, $this->scripts());
    }

    public function testForeignEventIsIgnored(): void
    {
        $this->configure();
        FakeHomematicIO::$Requests = [];
        $this->press('OTHER0000001:1', 'PRESS_SHORT');
        $this->press('DIS0000001:1', 'PRESS_CONT');
        $this->assertSame('', $this->lastScript());
        $this->assertSame('', IPS\InstanceManager::getInstanceInterface($this->DisplayId)->ReceiveData('invalid'));
    }

    public function testExpertTriggerSendsToConfiguredAddress(): void
    {
        $Variable = $this->createHmDeviceVariable('OTH0000001:1', 'PRESS_SHORT');
        $this->configure(['ExpertTriggers' => json_encode([['VariableID' => $Variable, 'Action' => \HomeMaticDisWM55::ActionUp]])]);
        $this->assertSame(IS_ACTIVE, $this->instanceStatus($this->DisplayId));
        $this->press('OTH0000001:1', 'PRESS_SHORT');
        $this->assertStringContainsString('BidCos-RF.DIS0000001:1.SUBMIT', $this->lastScript());
        $this->assertStringNotContainsString('OTH0000001', $this->lastScript());
    }

    public function testInvalidExpertTriggerSetsError(): void
    {
        $this->configure(['ExpertTriggers' => json_encode([['VariableID' => 99999, 'Action' => 0]])]);
        $this->assertSame(IS_EBASE + 2, $this->instanceStatus($this->DisplayId));
    }

    public function testMissingScriptSetsError(): void
    {
        $this->configure(['ScriptID' => 0]);
        $this->assertSame(IS_EBASE + 3, $this->instanceStatus($this->DisplayId));
    }

    public function testMigrateOwnButtonsOnly(): void
    {
        $Config = $this->migrate([
            'PageUpID'     => $this->createHmDeviceVariable('DIS0000009:2', 'PRESS_SHORT'),
            'PageDownID'   => $this->createHmDeviceVariable('DIS0000009:1', 'PRESS_SHORT'),
            'ActionUpID'   => $this->createHmDeviceVariable('DIS0000009:2', 'PRESS_LONG'),
            'ActionDownID' => 0
        ]);
        $this->assertSame('DIS0000009:2', $Config['Address']);
        $this->assertSame('[]', $Config['ExpertTriggers']);
    }

    public function testMigrateForeignAndDifferentDatapointsGoToExpertList(): void
    {
        $Other = $this->createHmDeviceVariable('OTH0000001:1', 'PRESS_SHORT');
        $Cont = $this->createHmDeviceVariable('DIS0000009:1', 'PRESS_CONT');
        $Config = $this->migrate([
            'PageUpID'     => $this->createHmDeviceVariable('DIS0000009:2', 'PRESS_SHORT'),
            'PageDownID'   => $Cont,
            'ActionUpID'   => $Other,
            'ActionDownID' => 0
        ]);
        $this->assertSame('DIS0000009:2', $Config['Address']);
        $Expert = json_decode($Config['ExpertTriggers'], true);
        $this->assertSame([
            ['VariableID' => $Cont, 'Action' => \HomeMaticDisWM55::ActionPageDown],
            ['VariableID' => $Other, 'Action' => \HomeMaticDisWM55::ActionUp]
        ], $Expert);
    }

    public function testMigrateTakesAddressOfFirstTriggerIfAllForeign(): void
    {
        $Config = $this->migrate([
            'PageUpID'     => 0,
            'PageDownID'   => $this->createHmDeviceVariable('FOR0000001:1', 'PRESS_SHORT'),
            'ActionUpID'   => $this->createHmDeviceVariable('FOR0000002:2', 'PRESS_LONG'),
            'ActionDownID' => 0
        ]);
        $this->assertSame('FOR0000001:1', $Config['Address']);
        $Expert = json_decode($Config['ExpertTriggers'], true);
        $this->assertCount(1, $Expert);
        $this->assertSame(\HomeMaticDisWM55::ActionUp, $Expert[0]['Action']);
    }

    public function testMigrateWithoutOldTriggersKeepsConfiguration(): void
    {
        $Config = $this->migrate(['PageUpID' => 0, 'PageDownID' => 0, 'ActionUpID' => 0, 'ActionDownID' => 0, 'MaxPage' => 2]);
        $this->assertArrayNotHasKey('Address', $Config);
        $this->assertSame('[]', $Config['ExpertTriggers']);
        $this->assertSame(2, $Config['MaxPage']);
    }

    public function testMigrateWithoutOldPropertiesReturnsDataUnchanged(): void
    {
        $Json = json_encode(['configuration' => ['Address' => 'DIS0000001:1', 'MaxPage' => 3]]);
        $this->assertSame($Json, IPS\InstanceManager::getInstanceInterface($this->DisplayId)->Migrate($Json));
    }

    public function testRestoreButtonIsOnlyActiveWithoutScript(): void
    {
        $Button = fn () => array_values(array_filter(
            json_decode(IPS\InstanceManager::getInstanceInterface($this->DisplayId)->GetConfigurationForm(), true)['actions'],
            fn ($Action) => ($Action['name'] ?? '') == 'RestoreScript'
        ))[0];
        $this->assertFalse($Button()['enabled']);
        IPS_DeleteScript(IPS_GetObjectIDByIdent('DisplayScript', $this->DisplayId), true);
        $this->assertTrue($Button()['enabled']);
    }

    public function testRestoreDisplayScriptCreatesHiddenScriptAndSetsProperty(): void
    {
        $Instance = IPS\InstanceManager::getInstanceInterface($this->DisplayId);
        IPS_DeleteScript(IPS_GetObjectIDByIdent('DisplayScript', $this->DisplayId), true);
        // Die Stubs melden bei FindIDForIdent ohne Treffer eine Warnung, die der Handler ausgibt.
        $this->assertStringNotContainsString('already exists', $this->restore($Instance));
        $NewId = IPS_GetObjectIDByIdent('DisplayScript', $this->DisplayId);
        $this->assertTrue(IPS_GetObject($NewId)['ObjectIsHidden']);
        $this->assertSame($NewId, IPS_GetProperty($this->DisplayId, 'ScriptID'));
        $this->assertStringContainsString('HMDisWM55', IPS_GetScriptContent($NewId));
    }

    public function testDisplayScriptIsRegisteredAsReference(): void
    {
        $ScriptId = IPS_GetObjectIDByIdent('DisplayScript', $this->DisplayId);
        IPS_ApplyChanges($this->DisplayId);
        $this->assertContains($ScriptId, IPS\InstanceManager::getInstanceInterface($this->DisplayId)->GetReferenceList());
    }

    public function testRestoreDisplayScriptRejectsExistingScript(): void
    {
        $Instance = IPS\InstanceManager::getInstanceInterface($this->DisplayId);
        $this->assertStringContainsString('Display script already exists.', $this->restore($Instance));
        $this->assertSame(1, count(array_filter(IPS_GetChildrenIDs($this->DisplayId), fn ($Id) => IPS_GetObject($Id)['ObjectIdent'] == 'DisplayScript')));
    }

    public function testConvertDisplayData(): void
    {
        $Module = new TestDisWM55($this->DisplayId);
        $this->assertSame('0x02,0x12,0x41,0x11,0x81,0x13,0x82,0x0A,0x03', $Module->TestConvert([['Text' => 'A', 'Icon' => 130, 'Color' => 129]]));
    }

    private function restore(object $Instance): string
    {
        ob_start();
        $Instance->RequestAction('RestoreDisplayScript', true);
        return (string) ob_get_clean();
    }

    private function configure(array $Properties = []): void
    {
        $Properties = array_merge([
            \HMExtended\Device\Property::Address => self::Address,
            'ScriptID'                           => $this->ScriptId,
            'MaxPage'                            => 3
        ], $Properties);
        foreach ($Properties as $Name => $Value) {
            IPS_SetProperty($this->DisplayId, $Name, $Value);
        }
        IPS_ApplyChanges($this->DisplayId);
    }

    private function press(string $DeviceId, string $Datapoint): void
    {
        IPS\InstanceManager::getInstanceInterface($this->DisplayId)->ReceiveData(json_encode([
            'DataID'        => \HMExtended\GUID::HmDevice,
            'DeviceID'      => $DeviceId,
            'VariableName'  => $Datapoint,
            'VariableValue' => true
        ]));
    }

    private function lastScript(): string
    {
        $Scripts = array_values(array_filter(FakeHomematicIO::$Requests, function ($Request)
        {
            return $Request['DataID'] == \HMExtended\GUID::SendScriptToIO;
        }));
        return $Scripts ? end($Scripts)['Content'] : '';
    }

    private function submitResponse(bool $State): string
    {
        return '<xml><exec>/x</exec><sessionId></sessionId><httpUserAgent>x</httpUserAgent><DisplayKeySubmit>1</DisplayKeySubmit><State>' . ($State ? 'true' : 'false') . '</State></xml>';
    }

    /**
     * @return string[] Inhalte aller an die CCU gesendeten HM-Scripte.
     */
    private function scripts(): array
    {
        return array_values(array_map(function ($Request)
        {
            return $Request['Content'];
        }, array_filter(FakeHomematicIO::$Requests, function ($Request)
        {
            return $Request['DataID'] == \HMExtended\GUID::SendScriptToIO;
        })));
    }

    private function instanceStatus(int $InstanceId): int
    {
        return IPS_GetInstance($InstanceId)['InstanceStatus'];
    }

    private function migrate(array $Configuration): array
    {
        $Result = IPS\InstanceManager::getInstanceInterface($this->DisplayId)->Migrate(json_encode(['configuration' => $Configuration]));
        return json_decode($Result, true)['configuration'];
    }
}
