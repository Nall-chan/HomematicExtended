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
 * @version       2.60
 */
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once(__DIR__ . "/../libs/HMBase.php");  // HMBase Klasse

/**
 * HomeMaticProgramme ist die Klasse für das IPS-Modul 'HomeMatic Programme'.
 * Erweitert HMBase
 */
class HomeMaticProgramme extends HMBase
{

    use DebugHelper,
        VariableProfileHelper;
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999998');
        $this->RegisterPropertyBoolean("EmulateStatus", false);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('Execute.HM');
        }

        parent::Destroy();
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

        if (!IPS_VariableProfileExists('Execute.HM')) {
            IPS_CreateVariableProfile('Execute.HM', 1);
            IPS_SetVariableProfileAssociation('Execute.HM', 0, 'Start', '', -1);
        }

        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return;
        }
        if (!$this->HasActiveParent()) {
            return;
        }
        try {
            $this->ReadCCUPrograms();
        } catch (Exception $exc) {
            echo $this->Translate($exc->getMessage());
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
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     * @access protected
     */
    protected function IOChangeState($State)
    {
        if ($State != IS_ACTIVE) {
            return;
        }
        try {
            $this->ReadCCUPrograms();
        } catch (Exception $exc) {
            echo $this->Translate($exc->getMessage());
        }
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

    ################## PRIVATE
    /**
     * Liest alle vorhandenen Programme aus der CCU aus und stellt diese als Variablen mit Aktionen da.
     *
     * @access private
     * @return boolean True bei Erfolg, sonst false.
     * @throws Exception Wenn CCU nicht erreicht wurde.
     */
    private function ReadCCUPrograms()
    {
        if (!$this->HasActiveParent()) {
            throw new Exception("Instance has no active parent instance!", E_USER_NOTICE);
        }
        if ($this->HMAddress == '') {
            throw new Exception("Instance has no active parent instance!", E_USER_NOTICE);
        }
        $url = 'SysPrg.exe';
        $HMScript = 'SysPrgs=dom.GetObject(ID_PROGRAMS).EnumUsedIDs();';
        try {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
            $xml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        } catch (Exception $exc) {
            $this->SendDebug('SysPrg', $exc->getMessage(), 0);
            throw new Exception("Error on read all CCU-Programs.", E_USER_NOTICE);
        }

        $Result = true;
        foreach (explode(chr(0x09), (string) $xml->SysPrgs) as $SysPrg) {
            $HMScript = 'Name=dom.GetObject(' . $SysPrg . ').Name();' . PHP_EOL
                    . 'Info=dom.GetObject(' . $SysPrg . ').PrgInfo();' . PHP_EOL;
            try {
                $HMScriptResult = $this->LoadHMScript($url, $HMScript);
                $varXml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
            } catch (Exception $exc) {
                $Result = false;
                $this->SendDebug($SysPrg, $exc->getMessage(), 0);
                trigger_error(sprintf($this->Translate("Error on read info of CCU-Program %s."), (string) $SysPrg), E_USER_NOTICE);
                continue;
            }

            $this->SendDebug($SysPrg, (string) $varXml->Name, 0);
            $var = @IPS_GetObjectIDByIdent($SysPrg, $this->InstanceID);
            $Name = (string) $varXml->Name;
            $Info = (string) $varXml->Name;
            if ($var === false) {
                $this->MaintainVariable($SysPrg, $Name, 1, 'Execute.HM', 0, true);
                $this->EnableAction($SysPrg);
                $var = IPS_GetObjectIDByIdent($SysPrg, $this->InstanceID);
            }
        }
        return $Result;
    }

    /**
     * Startet ein auf der CCU hinterlegtes Programm.
     *
     * @access private
     * @param string $Ident Der Ident des Programmes.
     * @return boolean True bei erfolg sonst Exception.
     * @throws Exception Wenn CCU nicht erreicht wurde oder diese eine Fehler meldet.
     */
    private function StartCCUProgram($Ident)
    {
        if (!$this->HasActiveParent()) {
            throw new Exception("Instance has no active parent instance!", E_USER_NOTICE);
        }
        if ($this->HMAddress == '') {
            throw new Exception("Instance has no active parent instance!", E_USER_NOTICE);
        }
        $url = 'SysPrg.exe';
        $HMScript = 'State=dom.GetObject(' . $Ident . ').ProgramExecute();';
        try {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
            $xml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        } catch (Exception $exc) {
            $this->SendDebug($Ident, $exc->getMessage(), 0);
            throw new Exception("Error on start CCU-Program.", E_USER_NOTICE);
        }

        $this->SendDebug('Result', (string) $xml->State, 0);
        if ((string) $xml->State == 'true') {
            $this->SetValue($Ident, 0);
            return true;
        } else {
            throw new Exception("Error on start CCU-Program", E_USER_NOTICE);
        }
    }

    ################## ActionHandler
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function RequestAction($Ident, $Value)
    {
        try {
            $this->StartCCUProgram($Ident);
        } catch (Exception $exc) {
            trigger_error($this->Translate($exc->getMessage()), $exc->getCode());
        }
    }

    ################## PUBLIC
    /**
     * IPS-Instanz-Funktion 'HM_ReadPrograms'.
     * Liest die Programme aus der CCU aus.
     *
     * @access public
     * @return boolean True bei erfolg, sonst false.
     */
    public function ReadPrograms()
    {
        try {
            return $this->ReadCCUPrograms();
        } catch (Exception $exc) {
            trigger_error($this->Translate($exc->getMessage()), $exc->getCode());
            return false;
        }
    }

    /**
     * IPS-Instanz-Funktion 'HM_StartProgram'.
     * Startet ein auf der CCU hinterlegtes Programme.
     *
     * @access public
     * @return boolean True bei erfolg, sonst false.
     */
    public function StartProgram(string $Parameter)
    {
        try {
            return $this->StartCCUProgram($Parameter);
        } catch (Exception $exc) {
            trigger_error($this->Translate($exc->getMessage()), $exc->getCode());
            return false;
        }
    }

}

/** @} */
