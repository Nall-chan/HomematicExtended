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
 * @version       3.73
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticParasetInterface ist die Klasse für das IPS-Modul 'HomeMatic Paraset Interface'.
 * Erweitert HMBase.
 */
class HomeMaticParasetInterface extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, 0);
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
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadParamSet'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadParamSet()
    {
        return $this->GetParamSet($this->ReadPropertyString(\HMExtended\Device\Property::Address), \HMExtended\CCU::MASTER);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteParameterBoolean'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteParameterBoolean(string $Parameter, bool $Data)
    {
        $Result = $this->PutParamSet([$Parameter=> $Data]);
        return $Result;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteParameterInteger'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteParameterInteger(string $Parameter, int $Data)
    {
        $Result = $this->PutParamSet([$Parameter=> $Data]);
        return $Result;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteParameterFloat'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteParameterFloat(string $Parameter, float $Data)
    {
        $Result = $this->PutParamSet([$Parameter=> $Data]);
        return $Result;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteParameterString'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteParameterString(string $Parameter, string $Data)
    {
        $Result = $this->PutParamSet([$Parameter=> $Data]);
        return $Result;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteParamSet'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteParamSet(string $ParamSet)
    {
        $Data = @json_decode($ParamSet, true);
        if ($Data === false) {
            trigger_error('Error in Parameter', E_USER_NOTICE);
            return false;
        }
        $Result = $this->PutParamSet($Data);
        return $Result;
    }

    //################# PRIVATE

    /**
     * Schreibt Parameter zu einem Devices.
     *
     * @return bool
     */
    private function PutParamSet(array $Parameter)
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active Parent Instance!'), E_USER_NOTICE);
            return false;
        }
        $EmulateStatus = $this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus);
        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol),
            'MethodName' => 'PutParamSet',
            'WaitTime'   => 3,
            'Data'       => [$this->ReadPropertyString(\HMExtended\Device\Property::Address), \HMExtended\CCU::MASTER, json_encode($Parameter)]
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $Result = @$this->SendDataToParent(json_encode($ParentData));
        if ($EmulateStatus) {
            return true;
        }
        if ($Result === false) {
            trigger_error('Error on Write Paramset', E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
            return false;
        }
        return true;
    }
}

/* @} */
