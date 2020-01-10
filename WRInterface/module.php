<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.00
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
    public function Create()
    {
        parent::Create();

        $this->RegisterHMPropertys('XXX9999993');
        $this->RegisterPropertyBoolean('EmulateStatus', false);

        $this->RegisterPropertyInteger('Interval', 0);

        $this->RegisterTimer('ReadWRInterface', 0, 'HM_ReadWRInterface($_IPS[\'TARGET\']);');
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
                $this->SetTimerInterval('ReadWRInterface', $this->ReadPropertyInteger('Interval') * 1000);
            } else {
                $this->SetTimerInterval('ReadWRInterface', 0);
            }
        } else {
            $this->SetTimerInterval('ReadWRInterface', 0);
        }

        if (!$this->HasActiveParent()) {
            return;
        }

        $this->ReadWRInterface();
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadWRInterface'.
     * Liest die Daten des WR-Interface.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadWRInterface()
    {
        $Result = $this->GetInterface();
        if ($Result === false) {
            return false;
        }
        foreach ($Result as $Ident => $Value) {
            if ($Value === '') {
                continue;
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
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid === false) {
                $this->MaintainVariable($Ident, $Ident, $Typ, '', 0, true);
                $vid = $this->GetIDForIdent($Ident);
            }
            if ($Ident == 'CONNECTED') {
                $this->SetValue($Ident, $Value);
                continue;
            }
            if (GetValue($vid) != $Value) {
                $this->SetValue($Ident, $Value);
            }
        }
        return true;
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
            $this->SetTimerInterval('ReadWRInterface', 0);
        }
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @return int ID des Parent.
     */
    protected function RegisterParent()
    {
        $ParentId = parent::RegisterParent();
        $this->SetSummary($this->HMAddress);
        return $ParentId;
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
     * Liest alle Daten des WR-Interfaces aus der CCU aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    private function GetInterface()
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
            'DataID'     => '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}',
            'Protocol'   => 1,
            'MethodName' => 'getLGWStatus',
            'WaitTime'   => 5000,
            'Data'       => []
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        if ($ResultJSON == false) {
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
