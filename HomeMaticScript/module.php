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
require_once __DIR__ . '/../libs/HMBase.php';

/**
 * HomeMaticRemoteScript ist die Klasse für das IPS-Modul 'HomeMatic RemoteScript Interface'.
 * Erweitert HMBase.
 */
class HomeMaticRemoteScript extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999993');
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    //################# PUBLIC

    /**
     * IPS-Instanzfunktion HM_RunScript.
     * Startet das übergebene Script auf der CCU und liefert das Ergebnis als JSON-String.
     *
     * @param string $Script
     *
     * @return string|bool Das Ergebnis als JSON-String oder FALSE im Fehlerfall.
     */
    public function RunScript(string $Script)
    {
        return  $this->LoadHMScript($Script);
        $xml = $this->SendScript($Script);
        if ($xml === false) {
            return false;
        }
        return json_encode($xml);
    }

    //################# private

    /**
     * Sendet ein HM-Script an die CCU und liefert das Ergebnis.
     *
     * @param string $Script Das HM-Script.
     *
     * @throws Exception Wenn die CCU nicht erreicht wurde.
     *
     * @return string das Ergebnis von der CCU als JSON-String.
     */
    private function SendScript(string $Script)
    {
        $HMScriptResult = $this->LoadHMScript($Script);
        if ($HMScriptResult === false) {
            return false;
        }
        return $this->GetScriptXML($HMScriptResult);
    }
}

/* @} */
