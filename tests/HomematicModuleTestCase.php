<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';
require_once __DIR__ . '/../libs/HMBase.php';

use PHPUnit\Framework\TestCase;

/**
 * Simuliert den Homematic Socket (IO) mit vorgegebenen CCU-Antworten.
 */
class FakeHomematicIO extends IPSModuleStrict
{
    /** @var array<string, array|string> Antworten je RPC-Methode. */
    public static array $RpcResponses = [];
    /** @var string Antwort auf HM-Script. */
    public static string $ScriptResponse = '';
    /** @var string[] Antworten auf HM-Script, werden nacheinander verbraucht, danach gilt $ScriptResponse. */
    public static array $ScriptResponses = [];
    /** @var array Alle empfangenen Anfragen. */
    public static array $Requests = [];

    public function Create(): void
    {
        parent::Create();
        // Interfaces des Homematic Sockets, werden z.B. vom Konfigurator gelesen
        $this->RegisterPropertyBoolean('RFOpen', true);
        $this->RegisterPropertyBoolean('IPOpen', false);
        $this->RegisterPropertyBoolean('WROpen', false);
        $this->RegisterPropertyBoolean('GROpen', false);
    }

    public function ForwardData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        self::$Requests[] = $Data;
        if ($Data['DataID'] == \HMExtended\GUID::SendScriptToIO) {
            return self::$ScriptResponses ? array_shift(self::$ScriptResponses) : self::$ScriptResponse;
        }
        // Antworten können je Methode und Protokoll ('listDevices@2'), je Methode und Paramset ('getParamset:MASTER') oder je Methode hinterlegt werden.
        $Response = self::$RpcResponses[$Data['MethodName'] . '@' . ($Data['Protocol'] ?? '')]
            ?? self::$RpcResponses[$Data['MethodName'] . ':' . ($Data['Data'][1] ?? '')]
            ?? self::$RpcResponses[$Data['MethodName']]
            ?? false;
        if ($Response === false) {
            return '';
        }
        return is_string($Response) ? $Response : json_encode($Response);
    }

    protected function getTime(): int
    {
        return 0;
    }
}

/**
 * Simuliert eine Homematic Geräteinstanz (Parent der Auslöser-Variablen).
 */
class FakeHmDevice extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, 0);
    }

    protected function getTime(): int
    {
        return 0;
    }
}

/**
 * Basisklasse für Modultests mit simuliertem Homematic Socket.
 */
abstract class HomematicModuleTestCase extends TestCase
{
    protected const DeviceDataFlow = '{98FEC99D-6AD9-4598-8F50-2976DA0A32C8}';

    /**
     * Setzt den Kernel zurück und legt den Fake-Socket an.
     *
     * @return int Instanz-ID des Fake-Socket.
     */
    protected function createFakeIo(): int
    {
        IPS\Kernel::reset();
        FakeHomematicIO::$Requests = [];
        FakeHomematicIO::$RpcResponses = [];
        FakeHomematicIO::$ScriptResponse = '';
        FakeHomematicIO::$ScriptResponses = [];
        $this->registerTestModule('FakeHomematicIO', \HMExtended\GUID::HmIO, MODULETYPE_IO, 'FHIO', [
            'childRequirements' => [self::DeviceDataFlow],
            'implemented'       => [\HMExtended\GUID::SendRpcToIO, \HMExtended\GUID::SendScriptToIO]
        ]);
        return IPS_CreateInstance(\HMExtended\GUID::HmIO);
    }

    /**
     * Registriert eine Testklasse als Modul (die Klasse muss bereits geladen sein).
     */
    protected function registerTestModule(string $Class, string $Guid, int $Type, string $Prefix, array $Extra = []): void
    {
        $Folder = sys_get_temp_dir() . '/' . $Class;
        if (!is_dir($Folder)) {
            mkdir($Folder);
        }
        file_put_contents($Folder . '/module.json', json_encode(array_merge([
            'id'     => $Guid,
            'name'   => $Class,
            'type'   => $Type,
            'vendor' => 'Test',
            'prefix' => $Prefix
        ], $Extra)));
        file_put_contents($Folder . '/module.php', '<?php');
        IPS\ModuleLoader::loadSingleModule($Folder, 'test');
    }

    /**
     * Legt ein Gerätemodul an, verbindet es mit dem Socket und liefert die Instanz-ID.
     */
    protected function createDevice(string $Class, string $Guid, string $Prefix, int $IoId, int $Type = MODULETYPE_DEVICE): int
    {
        $this->registerTestModule($Class, $Guid, $Type, $Prefix, [
            'parentRequirements' => [\HMExtended\GUID::SendRpcToIO, \HMExtended\GUID::SendScriptToIO],
            'implemented'        => [self::DeviceDataFlow]
        ]);
        $Id = IPS_CreateInstance($Guid);
        IPS_ConnectInstance($Id, $IoId);
        return $Id;
    }

    /**
     * Legt eine simulierte Homematic Geräteinstanz mit einer Variable an und liefert die Variablen-ID.
     */
    protected function createHmDeviceVariable(string $DeviceAddress, string $Ident, int $Protocol = 0, int $VariableType = VARIABLETYPE_BOOLEAN): int
    {
        if (!IPS\ModuleLoader::moduleExists(\HMExtended\GUID::HmDevice)) {
            $this->registerTestModule('FakeHmDevice', \HMExtended\GUID::HmDevice, MODULETYPE_DEVICE, 'FHMD', [
                'implemented' => [self::DeviceDataFlow]
            ]);
        }
        $Device = IPS_CreateInstance(\HMExtended\GUID::HmDevice);
        IPS_SetProperty($Device, \HMExtended\Device\Property::Address, $DeviceAddress);
        IPS_SetProperty($Device, \HMExtended\Device\Property::Protocol, $Protocol);
        IPS_ApplyChanges($Device);
        $Variable = IPS_CreateVariable($VariableType);
        IPS_SetParent($Variable, $Device);
        IPS_SetIdent($Variable, $Ident);
        return $Variable;
    }
}
