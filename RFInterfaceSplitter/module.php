<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.71
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
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, \HMExtended\Device\Address::RFINTERFACE);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Interval, 0);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadRFInterfaces, 0, 'HM_ReadRFInterfaces($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $this->CheckConfig();
        if ($this->GetStatus() == IS_ACTIVE)
        {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRFInterfaces, $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval) * 1000);            
            if ( $this->HasActiveParent()) {
                @$this->ReadRFInterfaces();
            }
        } else {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRFInterfaces, 0);
        }
    }
    /**
     * Interne Funktion des SDK.
     *
     * @param type $JSONString Der IPS-Datenstring
     *
     * @return string Die Antwort an den anfragenden Child
     */
    public function ForwardData(string $JSONString): string
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
    public function ReadRFInterfaces(): bool
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
    protected function KernelReady(): void
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
        if ($State == IS_ACTIVE) {
            $this->ApplyChanges();
        } else {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRFInterfaces, 0);
        }
    }

    //################# PRIVATE

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     */
    private function CheckConfig(): void
    {
        $Interval = $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval);
        if ($Interval <= 0) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if ($Interval < 5) {
            $this->SetStatus(IS_EBASE + 2);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }
    
    /**
     * Liest alle Daten der RF-Interfaces aus der CCU aus.
     *
     * @return array Ein Array mit den Daten der Interfaces.
     */
    private function GetInterfaces(): array
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
