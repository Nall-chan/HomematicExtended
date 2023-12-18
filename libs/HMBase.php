<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMBase.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.71
 */
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');
require_once __DIR__ . '/HMTypes.php';  // HMTypes Data

/**
 * HMBase ist die Basis-Klasse für alle Module welche HMScript verwenden.
 * Erweitert IPSModule.
 *
 * @property int $ParentID Aktueller IO-Parent.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void RegisterProfileEx(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0)
 *
 * @method void UnregisterProfile(string $Profile)
 *
 */
abstract class HMBase extends IPSModule
{
    use \HMExtended\DebugHelper,
        \HMExtended\VariableHelper,
        \HMExtended\VariableProfileHelper,
        \HMExtended\BufferHelper,
        \HMExtended\InstanceStatus {
            \HMExtended\InstanceStatus::RegisterParent as IORegisterParent;
            \HMExtended\InstanceStatus::MessageSink as IOMessageSink;
            \HMExtended\InstanceStatus::RequestAction as IORequestAction;
        }

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ParentID = 0;
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->ConnectParent(\HMExtended\GUID::HmIO);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->RegisterParent();
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @param int       $TimeStamp
     * @param int       $SenderID
     * @param int       $Message
     * @param array $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    //################# ActionHandler
    public function RequestAction($Ident, $Value)
    {
        return $this->IORequestAction($Ident, $Value);
    }

    //################# protected

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterParent();
    }

    /**
     * Setzte alle Eigenschaften, welche Instanzen die mit einem Homematic-Socket verbunden sind, haben müssen.
     *
     * @param string $Address Die zu nutzende HM-Device Adresse.
     */
    protected function RegisterHMPropertys(string $Address)
    {
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, 0);
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $count = IPS_GetInstanceListByModuleID(IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID']);
            if (is_array($count)) {
                $this->RegisterPropertyString(\HMExtended\Device\Property::Address, $Address . ':' . count($count));
                return;
            }
        }
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, $Address . ':0');
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @return int ID des Parent.
     */
    protected function RegisterParent()
    {
        return $this->IORegisterParent();
    }

    /**
     * Überträgt das übergeben HM-Script an die CCU und liefert das Ergebnis.
     *
     * @param string $HMScript Das zu übertragende HM-Script.
     *
     * @throws Exception Wenn die CCU nicht erreicht wurde.
     *
     * @return false|string Das Ergebnis von der CCU.
     */
    protected function LoadHMScript(string $HMScript)
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active Parent Instance!'), E_USER_NOTICE);
            return false;
        }
        $ParentData = [
            'DataID'        => \HMExtended\GUID::SendScriptToIO,
            'Content'       => $HMScript
        ];
        $this->SendDebug('Send', $ParentData, 0);
        $Result = @$this->SendDataToParent(json_encode($ParentData));
        $this->SendDebug('Receive', utf8_encode($Result), 0);
        return utf8_encode($Result);
    }

    protected function GetScriptXML($Content)
    {
        try {
            $xml = @new SimpleXMLElement(utf8_encode($Content), LIBXML_NOBLANKS + LIBXML_NONET);
        } catch (Throwable $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }
        unset($xml->exec);
        unset($xml->sessionId);
        unset($xml->httpUserAgent);
        return $xml;
    }
}

/* @} */
