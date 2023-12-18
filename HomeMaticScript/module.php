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
 * @version       3.71
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
    public function Create(): void
    {
        parent::Create();
        $this->RegisterHMProperties('XXX9999993');
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
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
    public function RunScript(string $Script): false|string
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
     * @return SimpleXMLElement das Ergebnis von der CCU als JSON-String.
     */
    private function SendScript(string $Script): SimpleXMLElement
    {
        $HMScriptResult = $this->LoadHMScript($Script);
        if ($HMScriptResult === false) {
            return false;
        }
        return $this->GetScriptXML($HMScriptResult);
    }
}

/* @} */
