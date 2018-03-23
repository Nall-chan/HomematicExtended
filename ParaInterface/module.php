<?php

/**
 * @addtogroup homematicextended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.10
 */
require_once(__DIR__ . "/../libs/HMBase.php");  // HMBase Klasse

/**
 * ParaInterface ist die Klasse für das IPS-Modul 'HomeMatic Paraset Interface'.
 * Erweitert HMBase
 */
class ParaInterface extends HMBase
{
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Protocol", 0);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter(".*9999999999.*");
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return;
        }
    }

    ################## protected
    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     *
     * @access protected
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Parent ändert.
     *
     * @access protected
     */
    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @access protected
     * @return int ID des Parent.
     */
    protected function GetParentData()
    {
        $ParentId = parent::GetParentData();
        $this->SetSummary($this->ReadPropertyString("Address"));
        return $ParentId;
    }

    ################## PRIVATE
    /**
     * Liest alle Parameter des Devices aus.
     *
     * @access privat
     * @return array Ein Array mit den Daten des Interface.
     */
    private function GetParamset()
    {
        if (!$this->HasActiveParent()) {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $ParentData = array(
            "DataID"     => "{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}",
            "Protocol"   => $this->ReadPropertyInteger('Protocol'),
            "MethodName" => "getParamset",
            "WaitTime"   => 5000,
            "Data"       => array($this->ReadPropertyString('Address'), 'MASTER')
        );
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        $Result = @json_decode($ResultJSON, true);
        if ($Result === false) {
            trigger_error('Error on Read Paramset', E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
        }
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }

    /**
     * Liest alle Parameter des Devices aus.
     *
     * @access privat
     * @return array Ein Array mit den Daten des Interface.
     */
    private function PutParamset($Parameter)
    {
        if (!$this->HasActiveParent()) {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $ParentData = array(
            "DataID"     => "{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}",
            "Protocol"   => $this->ReadPropertyInteger('Protocol'),
            "MethodName" => "putParamset",
            "WaitTime"   => 5000,
            "Data"       => $Parameter //array($this->ReadPropertyString('Address'),'MASTER','TEXT1','1234')
        );
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        $Result = @json_decode($ResultJSON, true);
        if ($Result === false) {
            trigger_error('Error on Write Paramset', E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
        }
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }

################## PUBLIC
    /**
     * IPS-Instanz-Funktion 'HM_ReadPara'.
     * Liest die Daten des WR-Interface.
     *
     * @access public
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadPara()
    {
        $Result = $this->GetParamset();
        return $Result;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WritePara'.
     * Liest die Daten des WR-Interface.
     *
     * @access public
     * @return bool True bei Erfolg, sonst false.
     */
    public function WritePara(string $Parameter)
    {
        $Data = @json_decode($Parameter, true);
        if ($Data === false) {
            trigger_error('Error in Parameter', E_USER_NOTICE);
            return false;
        }
        $Result = $this->PutParamset($Data);
        return $Result;
    }

}

/** @} */
