<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.12
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

        $this->RegisterPropertyBoolean('EmulateStatus', false);
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyInteger('Protocol', 0);
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
     * IPS-Instanz-Funktion 'HM_ReadParamset'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadParamset()
    {
        $Result = $this->GetParamset();
        return $Result;
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
     * IPS-Instanz-Funktion 'HM_WriteParamset'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteParamset(string $Paramset)
    {
        $Data = @json_decode($Paramset, true);
        if ($Data === false) {
            trigger_error('Error in Parameter', E_USER_NOTICE);
            return false;
        }
        $Result = $this->PutParamSet($Data);
        return $Result;
    }

    //################# PRIVATE

    /**
     * Liest alle Parameter des Devices aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    private function GetParamset()
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active Parent Instance!'), E_USER_NOTICE);
            return false;
        }
        $ParentData = [
            'DataID'     => '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}',
            'Protocol'   => $this->ReadPropertyInteger('Protocol'),
            'MethodName' => 'getParamset',
            'WaitTime'   => 5000,
            'Data'       => [$this->ReadPropertyString('Address'), 'MASTER']
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        if ($ResultJSON === false) {
            trigger_error('Error on Read Paramset', E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
            return false;
        }
        $Result = json_decode(utf8_encode($ResultJSON), true);
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }

    /**
     * Liest alle Parameter des Devices aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    private function PutParamSet(array $Parameter)
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active Parent Instance!'), E_USER_NOTICE);
            return false;
        }
        $EmulateStatus = $this->ReadPropertyBoolean('EmulateStatus');
        $ParentData = [
            'DataID'     => '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}',
            'Protocol'   => $this->ReadPropertyInteger('Protocol'),
            'MethodName' => 'PutParamSet',
            'WaitTime'   => ($EmulateStatus ? 1 : 5000),
            'Data'       => [$this->ReadPropertyString('Address'), 'MASTER', json_encode($Parameter)]
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
