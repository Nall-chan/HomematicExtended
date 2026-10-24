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
 * @version       3.75
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
        // Feste Adresse, die Instanz gehört zu keinem Gerät. Address und Protocol müssen vorhanden sein, sonst hängt sich der Symcon Homematic Konfigurator auf.
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, \HMExtended\Device\Address::REMOTESCRIPT);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
     * Ersetzt die bisherige Platzhalter-Adresse (XXX9999993:n) durch die feste Adresse.
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (is_object($Data) && property_exists($Data, 'configuration') && property_exists($Data->configuration, \HMExtended\Device\Property::Address)) {
            if (str_starts_with((string) $Data->configuration->{\HMExtended\Device\Property::Address}, 'XXX')) {
                $Data->configuration->{\HMExtended\Device\Property::Address} = \HMExtended\Device\Address::REMOTESCRIPT;
                $this->SendDebug('Migrate', json_encode($Data->configuration), 0);
                return json_encode($Data);
            }
        }
        return $JSONData;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');
    }

    //################# PUBLIC

    /**
     * IPS-Instanzfunktion HM_RunScript.
     * Startet das übergebene Script auf der CCU und liefert das Ergebnis als JSON-String.
     *
     * @param string $Script
     *
     * @return array|false Das Ergebnis als array oder FALSE im Fehlerfall.
     */
    public function RunScript(string $Script): false|array
    {
        $xml = $this->SendScript($Script);
        if ($xml === false) {
            return false;
        }
        return json_decode(json_encode($xml), true);
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
    private function SendScript(string $Script): SimpleXMLElement|false
    {
        $HMScriptResult = $this->LoadHMScript($Script);
        if ($HMScriptResult === false) {
            return false;
        }
        return $this->GetScriptXML($HMScriptResult);
    }
}

/* @} */
