<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.70
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticRFInterfaceSplitter ist die Klasse für das IPS-Modul 'HomeMatic RFInterface-Splitter'.
 * Erweitert HMBase.
 */
class HomeMaticRFInterfaceSplitter extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999994');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger('Interval', 0);
        $this->RegisterTimer('ReadRFInterfaces', 0, '@HM_ReadRFInterfaces($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        if ($this->CheckConfig()) {
            if ($this->ReadPropertyInteger('Interval') >= 5) {
                $this->SetTimerInterval('ReadRFInterfaces', $this->ReadPropertyInteger('Interval') * 1000);
            } else {
                $this->SetTimerInterval('ReadRFInterfaces', 0);
            }
        } else {
            $this->SetTimerInterval('ReadRFInterfaces', 0);
        }

        if (!$this->HasActiveParent()) {
            return;
        }

        try {
            $this->ReadRFInterfaces();
        } catch (Exception $exc) {
            echo $this->Translate($exc->getMessage());
        }
    }
    /**
     * Interne Funktion des SDK.
     *
     * @param type $JSONString Der IPS-Datenstring
     *
     * @return string Die Antwort an den anfragenden Child
     */
    public function ForwardData($JSONString)
    {
        return serialize($this->GetInterfaces());
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadRFInterfaces'.
     * Liest die Daten der RF-Interfaces und versendet sie an die Children.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadRFInterfaces()
    {
        $Result = $this->GetInterfaces();
        $ret = false;
        foreach ($Result as $ProtocolID => $Protocol) {
            if (!is_array($Protocol)) {
                continue;
            }
            foreach ($Protocol as $InterfaceIndex => $Interface) {
                $this->SendDebug('Proto' . $ProtocolID . ' If' . $InterfaceIndex, $Interface, 0);
                $Interface['DataID'] = \HMExtended\GUID::SendToRFInterfaceDevice;
                $Data = json_encode($Interface);
                $this->SendDataToChildren($Data);
                $ret = true;
            }
        }
        return $ret;
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            $this->ApplyChanges();
        } else {
            $this->SetTimerInterval('ReadRFInterfaces', 0);
        }
    }

    //################# PRIVATE

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     *
     * @return bool True wenn Konfig ok, sonst false.
     */
    private function CheckConfig()
    {
        $Interval = $this->ReadPropertyInteger('Interval');
        if ($Interval < 0) {
            $this->SetStatus(IS_EBASE + 2);
            return false;
        }

        if ($Interval == 0) {
            $this->SetStatus(IS_INACTIVE);
            return true;
        }

        if ($Interval < 5) {
            $this->SetStatus(IS_EBASE + 3);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return true;
    }
    /**
     * Liest alle Daten der RF-Interfaces aus der CCU aus.
     *
     * @return array Ein Array mit den Daten der Interfaces.
     */
    private function GetInterfaces()
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active parent instance!'), E_USER_NOTICE);
            return [];
        }
        $ParentId = $this->ParentID;
        $Protocol = [];
        if (IPS_GetProperty($ParentId, 'RFOpen') === true) {
            $Protocol[] = 0;
        }
        if (IPS_GetProperty($ParentId, 'IPOpen') === true) {
            $Protocol[] = 2;
        }

        $data = [];
        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => 0,
            'MethodName' => 'listBidcosInterfaces',
            'WaitTime'   => 3,
            'Data'       => $data
        ];
        $ret = [];
        foreach ($Protocol as $ProtocolId) {
            $ParentData['Protocol'] = $ProtocolId;
            $JSON = json_encode($ParentData);
            $ResultJSON = @$this->SendDataToParent($JSON);
            if ($ResultJSON === false) {
                trigger_error($this->Translate('Error on read interfaces:') . $ProtocolId, E_USER_NOTICE);
                $this->SendDebug('Error JSON', $ResultJSON, 0);
                continue;
            }
            $Result = json_decode(utf8_encode($ResultJSON), true);
            if (($Result === false) || is_null($Result)) {
                $this->SendDebug('Error decode', $Result, 0);
                trigger_error($this->Translate('Error on read interfaces:') . $ProtocolId, E_USER_NOTICE);
            } else {
                $ret[$ProtocolId] = $Result;
            }
        }
        return $ret;
    }
}

/* @} */
