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
 * @version       3.74
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticWRInterface ist die Klasse für das IPS-Modul 'HomeMatic WR-Interface'.
 * Erweitert HMBase.
 */
class HomeMaticWRInterface extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, \HMExtended\Device\Address::WRINTERFACE);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);

        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Interval, 0);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadWRInterface, 0, 'HM_ReadWRInterface($_IPS[\'TARGET\']);');
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
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadWRInterface, $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval) * 1000);            
            if ( $this->HasActiveParent()) {
                @$this->ReadWRInterface();
            }
        } else {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadWRInterface, 0);
        }

   }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadWRInterface'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadWRInterface(): bool
    {
        $Result = $this->GetInterface();
        if ($Result === false) {
            return false;
        }
        foreach ($Result as $Ident => $Value) {
            if ($Value === '') {
                continue;
            }
            switch ($Ident){
                case 'CONNECTED':
                    $Profile='~Alert.Reversed';
                    break;
                default:
                    $Profile = '';
                    break;
            }
            switch (gettype($Value)) {
                case 'boolean':
                    $Typ = VARIABLETYPE_BOOLEAN;
                    break;
                case 'integer':
                    $Typ = VARIABLETYPE_INTEGER;
                    break;
                case 'double':
                case 'float':
                    $Typ = VARIABLETYPE_FLOAT;
                    break;
                case 'string':
                    $Typ = VARIABLETYPE_STRING;
                    break;
                default:
                    continue 2;
            }
            $this->MaintainVariable($Ident, $Ident, $Typ, $Profile, 0, true);
            $this->SetValue($Ident, $Value);
        }
        return true;
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
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadWRInterface, 0);
        }
    }

    //################# PRIVATE

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     */
    private function CheckConfig():void
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
     * Liest alle Daten des WR-Interfaces aus der CCU aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    private function GetInterface(): false|array
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active parent instance!'), E_USER_NOTICE);
            return false;
        }
        if (IPS_GetProperty($this->ParentID, 'WROpen') !== true) {
            trigger_error($this->Translate('Instance has no active parent instance!'), E_USER_NOTICE);
            return false;
        }

        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => 1,
            'MethodName' => 'getLGWStatus',
            'WaitTime'   => 3,
            'Data'       => []
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        if ($ResultJSON === false) {
            trigger_error($this->Translate('Error on read WR-Interface.'), E_USER_NOTICE);
            $this->SendDebug('Error JSON', $ResultJSON, 0);
            return false;
        }
        $Result = json_decode($ResultJSON, true);
        if (($Result === false) || is_null($Result)) {
            trigger_error($this->Translate('Error on read WR-Interface.'), E_USER_NOTICE);
            $this->SendDebug('Error decode', $Result, 0);
            return false;
        }
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }
}

/* @} */
