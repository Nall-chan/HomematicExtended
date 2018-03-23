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
 * @version       2.40
 */
require_once(__DIR__ . "/../libs/HMBase.php");  // HMBase Klasse

/**
 * HomeMaticRFInterface ist die Klasse für das IPS-Modul 'HomeMatic RF-Interface'.
 * Erweitert IPSModule
 */
class HomeMaticRFInterface extends IPSModule
{

    use DebugHelper;
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString("Address", "");
        $this->ConnectParent("{6EE35B5B-9DD9-4B23-89F6-37589134852F}");
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $Address = $this->ReadPropertyString("Address");
        $this->SetSummary($Address);

        if ($Address !== "") {
            $this->SetReceiveDataFilter('.*"ADDRESS":"' . $Address . '".*');
        } else {
            $this->SetReceiveDataFilter(".*9999999999.*");
        }
    }

    ################## Datenaustausch
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);
        unset($Data->DataID);
        unset($Data->ADDRESS);
        $this->SendDebug('Receive', $Data, 0);
        foreach ($Data as $Ident => $Value) {
            if ($Value === "") {
                continue;
            }
            $Profil = "";
            if ($Ident == "DUTY_CYCLE") {
                $Profil = "~Intensity.100";
            }
            switch (gettype($Value)) {
                case "boolean":
                    $Typ = vtBoolean;
                    break;
                case "integer":
                    $Typ = vtInteger;
                    break;
                case "double":
                case "float":
                    $Typ = vtFloat;
                    break;
                case "string":
                    $Typ = vtString;
                    break;
                default:
                    continue;
            }
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid === false) {
                $this->MaintainVariable($Ident, $Ident, $Typ, $Profil, 0, true);
                $vid = $this->GetIDForIdent($Ident);
            }
            if ($Ident == 'CONNECTED') {
                $this->SetValue($Ident, $Value);
                continue;
            }
            if (GetValue($vid) <> $Value) {
                $this->SetValue($Ident, $Value);
            }
        }
    }

}

/** @} */