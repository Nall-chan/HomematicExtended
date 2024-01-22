<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.74
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticPowermeter ist die Klasse für das IPS-Modul 'HomeMatic PowerMeter'.
 * Erweitert HMBase.
 *
 * @property int $Event Die IPS-ID der Variable welche als Trigger dient.
 * @property string $HMDeviceAddress Die Geräte-Adresse des Zählers.
 * @property string $HMDeviceDatapoint Der zu überwachende Datenpunkt.
 * @property string $HMProtocol HmIP-RF oder BidCos-RF BidCos-WR
 */
class HomeMaticPowermeter extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999997');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger('EventID', 0);
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
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                $this->UnregisterReference($SenderID);
                if ($SenderID == $this->ReadPropertyInteger('EventID')) {
                    $this->SetNewConfig();
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetNewConfig();
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData($JSONString)
    {
        $this->ReadPowerSysVar();
        return '';
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        $this->ApplyChanges();
    }

    //################# PRIVATE

    /**
     * Übernimmt die neue Konfiguration.
     */
    private function SetNewConfig()
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            $this->HMProtocol = \HMExtended\CCU::BidCos_RF;
            $this->Event = 0;
            $this->SetReceiveDataFilter('.*9999999999.*');
            $this->SetSummary('');
            return;
        }
        if ($this->CheckConfig()) {
            $HMDeviceDatapoint = $this->HMDeviceDatapoint;
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $this->HMDeviceAddress . '","VariableName":"' . $HMDeviceDatapoint . '".*');

            switch ($HMDeviceDatapoint) {
                case 'GAS_ENERGY_COUNTER':
                    $Profil = '~Gas';

                    break;
                case 'IEC_ENERGY_COUNTER':

                    $Profil = '~Electricity';

                    break;
                case 'ENERGY_COUNTER':
                    $Profil = '~Electricity';

                    break;
            }

            $this->RegisterVariableFloat($HMDeviceDatapoint . '_TOTAL', $HMDeviceDatapoint . '_TOTAL', $Profil);
            $this->SetSummary($this->HMDeviceAddress);
            if (!$this->HasActiveParent()) {
                return;
            }

            try {
                $this->ReadPowerSysVar();
            } catch (Exception $exc) {
                echo $this->Translate($exc->getMessage());
            }
            return;
        }
        $this->SetReceiveDataFilter('.*9999999999.*');
    }

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     *
     * @return bool True wenn Konfig ok, sonst false.
     */
    private function CheckConfig()
    {
        $OldEvent = $this->Event;
        if ($OldEvent > 0) {
            $this->UnregisterMessage($OldEvent, VM_DELETE);
            $this->UnregisterReference($OldEvent);
            $this->Event = 0;
        }
        $Event = $this->ReadPropertyInteger('EventID');
        if ($Event == 0) {
            $this->SetStatus(IS_INACTIVE);
            return false;
        }
        if ($this->GetPowerAddress($Event)) {
            $this->RegisterMessage($Event, VM_DELETE);
            $this->RegisterReference($Event);
            $this->Event = $Event;
            $this->SetStatus(IS_ACTIVE);
            return true;
        }
        $this->SetStatus(IS_EBASE + 2);
        return false;
    }

    /**
     * Prüft und holt alle Daten zu der Quell-Variable und Instanz.
     *
     * @param int $EventID IPD-VarID des Datenpunktes, welcher als Event dient.
     *
     * @return bool True wenn Quelle gültig ist, sonst false.
     */
    private function GetPowerAddress(int $EventID)
    {
        if (($EventID == 0) || (!IPS_VariableExists($EventID))) {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            $this->HMProtocol = \HMExtended\CCU::BidCos_RF;
            return false;
        }
        $parent = IPS_GetParent($EventID);
        if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] != '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}') {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            $this->HMProtocol = \HMExtended\CCU::BidCos_RF;
            return false;
        }
        $EventIdent = IPS_GetObject($EventID)['ObjectIdent'];
        $PossibleIdent = ['GAS_ENERGY_COUNTER', 'IEC_ENERGY_COUNTER', 'ENERGY_COUNTER'];
        if (in_array($EventIdent, $PossibleIdent)) {
            $this->HMDeviceAddress = IPS_GetProperty($parent, \HMExtended\Device\Property::Address);
            $this->HMDeviceDatapoint = $EventIdent;
            switch (IPS_GetProperty($parent, \HMExtended\Device\Property::Protocol)) {
                case 0:
                    $this->HMProtocol = \HMExtended\CCU::BidCos_RF;
                    break;
                case 1:
                    $this->HMProtocol = \HMExtended\CCU::BidCos_WR;
                    break;
                case 2:
                    $this->HMProtocol = \HMExtended\CCU::HmIP;
                    break;
            }
            return true;
        }
        $this->HMDeviceAddress = '';
        $this->HMDeviceDatapoint = '';
        $this->HMProtocol = \HMExtended\CCU::BidCos_RF;
        return false;
    }

    /**
     * Holt den Wert des Summenzähler per HM-Script aus der CCU.
     *
     * @throws Exception Wenn CCU nicht erreicht wurde.
     */
    private function ReadPowerSysVar()
    {
        $HMDeviceDatapoint = $this->HMDeviceDatapoint;
        switch ($HMDeviceDatapoint) {
            case 'GAS_ENERGY_COUNTER':
                $Suffix = 'Gas';
                $Factor = 1;
                break;
            case 'IEC_ENERGY_COUNTER':
                $Suffix = 'IEC';
                $Factor = 1000;
                break;
            case 'ENERGY_COUNTER':
                $Suffix = '';
                $Factor = 1000;
                break;
        }
        $HMScript = 'object oitemID;' . PHP_EOL
                . 'oitemID = dom.GetObject("svEnergyCounter' . $Suffix . '_" # dom.GetObject("' . $this->HMProtocol . '.' . $this->HMDeviceAddress . '.' . $this->HMDeviceDatapoint . '").Channel() # "_' . $this->HMDeviceAddress . '");' . PHP_EOL
                . 'Value=oitemID.Value();' . PHP_EOL;

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        $this->SendDebug($this->HMDeviceDatapoint, (string) $xml->Value, 0);
        $Value = ((float) $xml->Value) / $Factor;
        $this->SetValue($this->HMDeviceDatapoint . '_TOTAL', $Value);
        return true;
    }
}

/* @} */
