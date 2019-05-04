<?php

declare(strict_types = 1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.00
 */
require_once(__DIR__ . '/../libs/HMBase.php');

/**
 * HomeMaticRemoteScript ist die Klasse für das IPS-Modul 'HomeMatic RemoteScript Interface'.
 * Erweitert HMBase
 */
class HomeMaticRemoteScript extends HMBase
{
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999993');
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->RegisterPropertyBoolean('EmulateStatus', false);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
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
      /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     * @access protected
     */
    protected function IOChangeState($State)
    {
        $this->ApplyChanges();
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @access protected
     */
    protected function RegisterParent()
    {
        parent::RegisterParent();
        $this->SetSummary($this->HMAddress);
    }

    ################## private
    /**
     * Sendet ein HM-Script an die CCU und liefert das Ergebnis.
     *
     * @access private
     * @param string $Script Das HM-Script.
     * @return string das Ergebnis von der CCU als JSON-String.
     * @throws Exception Wenn die CCU nicht erreicht wurde.
     */
    private function SendScript(string $Script)
    {
        if (!$this->HasActiveParent()) {
            throw new Exception('Instance has no active parent instance!', E_USER_NOTICE);
        }
        if ($this->HMAddress == '') {
            $this->RegisterParent();
        }
        $url = 'Script.exe';
        try {
            $HMScriptResult = $this->LoadHMScript($url, $Script);
            $xml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        } catch (Exception $exc) {
            $this->SendDebug($url, $exc->getMessage(), 0);
            throw new Exception($exc->getMessage(), E_USER_NOTICE);
        }
        unset($xml->exec);
        unset($xml->sessionId);
        unset($xml->httpUserAgent);
        return json_encode($xml);
    }

    ################## PUBLIC
    /**
     * IPS-Instanzfunktion HM_RunScript.
     * Startet das übergebene Script auf der CCU und liefert das Ergbnis als JSON-String.
     *
     * @access public
     * @param string $Script
     * @return string|boolean Das Ergebnis als JSON-String oder FALSE im Fehlerfall.
     */
    public function RunScript(string $Script)
    {
        try {
            return $this->SendScript($Script);
        } catch (Exception $exc) {
            trigger_error($this->Translate($exc->getMessage()), $exc->getCode());
            return false;
        }
    }
}

/** @} */
