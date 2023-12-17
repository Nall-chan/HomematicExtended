<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.70
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticProgramme ist die Klasse für das IPS-Modul 'HomeMatic Programme'.
 * Erweitert HMBase.
 */
class HomeMaticProgramme extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999998');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
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
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');

        $this->RegisterProfileIntegerEx('Execute.HM', '', '', '', [
            0, 'Start', '', -1
        ]);

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->HasActiveParent()) {
            return;
        }

        $this->ReadCCUPrograms();
    }

    //################# ActionHandler

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return;
        }

        $this->StartCCUProgram($Ident);
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadPrograms'.
     * Liest die Programme aus der CCU aus.
     *
     * @return bool True bei erfolg, sonst false.
     */
    public function ReadPrograms()
    {
        return $this->ReadCCUPrograms();
    }

    /**
     * IPS-Instanz-Funktion 'HM_StartProgram'.
     * Startet ein auf der CCU hinterlegtes Programme.
     *
     * @return bool True bei erfolg, sonst false.
     */
    public function StartProgram(string $Parameter)
    {
        return $this->StartCCUProgram($Parameter);
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
        if ($State != IS_ACTIVE) {
            return;
        }
        $this->ReadCCUPrograms();
    }

    //################# PRIVATE

    /**
     * Liest alle vorhandenen Programme aus der CCU aus und stellt diese als Variablen mit Aktionen da.
     *
     * @throws Exception Wenn CCU nicht erreicht wurde.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    private function ReadCCUPrograms()
    {
        $HMScript = 'SysPrgs=dom.GetObject(ID_PROGRAMS).EnumUsedIDs();';
        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        if ($xml === false) {
            return false;
        }
        $Result = true;
        foreach (explode(chr(0x09), (string) $xml->SysPrgs) as $SysPrg) {
            $HMScript = 'Name=dom.GetObject(' . $SysPrg . ').Name();' . PHP_EOL
                    . 'Info=dom.GetObject(' . $SysPrg . ').PrgInfo();' . PHP_EOL;
            $HMScriptResult = $this->LoadHMScript($HMScript);
            if ($HMScriptResult === false) {
                return false;
            }
            $varXml = $this->GetScriptXML($HMScriptResult);
            if ($varXml === false) {
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
     * @param string $Ident Der Ident des Programmes.
     *
     * @throws Exception Wenn CCU nicht erreicht wurde oder diese eine Fehler meldet.
     *
     * @return bool True bei Erfolg sonst Exception.
     */
    private function StartCCUProgram($Ident)
    {
        $HMScript = 'State=dom.GetObject(' . $Ident . ').ProgramExecute();';

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        if ($xml === false) {
            return false;
        }
        $this->SendDebug('Result', (string) $xml->State, 0);
        if ((string) $xml->State == 'true') {
            $this->SetValue($Ident, 0);
            return true;
        }
    }
}

/* @} */
