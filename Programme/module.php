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
 * @version       3.74
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
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, \HMExtended\Device\Address::PROGRAMS);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy(): void
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('Execute.HM');
        }

        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');

        $this->RegisterProfileIntegerEx('Execute.HM', '', '', '', [
            [0, 'Start', '', -1]
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
    public function RequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::RequestAction($Ident, $Value, $done);
        if ($done) {
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
    public function ReadPrograms(): bool
    {
        return $this->ReadCCUPrograms();
    }

    /**
     * IPS-Instanz-Funktion 'HM_StartProgram'.
     * Startet ein auf der CCU hinterlegtes Programme.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function StartProgram(string $Parameter): bool
    {
        return $this->StartCCUProgram($Parameter);
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
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
    private function ReadCCUPrograms(): bool
    {
        $Script = 'string pid;
        foreach (pid, dom.GetObject(ID_PROGRAMS).EnumUsedIDs())
        {
          WriteLine(pid # "\t" # dom.GetObject(pid).Name());
        }';
        $ret = $this->LoadHMScript($Script);
        if ($ret === false) {
            return false;
        }
        $ret = explode('<xml>', $ret);
        $ret = explode("\r\n", array_shift($ret));
        array_pop($ret);
        foreach ($ret as $line) {
            $data = explode("\t", $line);
            $this->SendDebug($data[0], $data[1], 0);
            $this->MaintainVariable($data[0], $data[1], 1, 'Execute.HM', 0, true);
            $this->EnableAction($data[0]);
        }
        return true;
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
    private function StartCCUProgram(string $Ident): bool
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
