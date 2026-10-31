<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.75
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticProgramme ist die Klasse für das IPS-Modul 'HomeMatic Programme'.
 * Erweitert HMBase.
 */
class HomeMaticProgramme extends HMBase
{
    protected const SummaryShowsHost = true;
    /** Profil der Programme bis Version 3.81, auch von den Heizungsmodulen genutzt. */
    private const LegacyProfile = 'Execute.HM';

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
        // Profil bis Version 3.81, UnregisterProfile lässt es stehen, solange andere Variablen es nutzen.
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile(self::LegacyProfile);
        }

        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Ohne ausdrücklichen Status bliebe die Instanz nach einem Fehler beim Erstellen (z.B. beim Neuladen) in 101 (wird erstellt).
        $this->SetStatus(IS_ACTIVE);
        $this->SetReceiveDataFilter('.*9999999999.*');

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->HasActiveParent()) {
            return;
        }

        $this->ReadCCUPrograms();
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

    //################# ActionHandler

    /**
     * Interne Funktion des SDK.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }

        $this->StartCCUProgram($Ident);
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
        $Presentation = \HMExtended\HeatingPresentation::Get(\HMExtended\HeatingPresentation::Execute, VARIABLETYPE_INTEGER, true, [], fn (string $Text): string => $this->Translate($Text));
        // Spalten: ID des Programmes, Name
        foreach (\HMExtended\ScriptParser::ParseTable(\HMExtended\ScriptParser::SplitOutput($ret)[0]) as $data) {
            if ((count($data) < 2) || !ctype_digit($data[0])) {
                continue;
            }
            $this->SendDebug($data[0], $data[1], 0);
            $this->MaintainPresentationVariable($data[0], $data[1], VARIABLETYPE_INTEGER, $Presentation);
            $this->EnableAction($data[0]);
        }
        // Variablen gelöschter Programme behalten ihr Profil, es bleibt dann stehen (UnregisterProfile prüft nur fremde Variablen).
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildId) {
            if (IPS_VariableExists($ChildId) && (IPS_GetVariable($ChildId)['VariableProfile'] == self::LegacyProfile)) {
                return true;
            }
        }
        $this->UnregisterProfile(self::LegacyProfile);
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
        // Der Ident ist die ID des Programmes und wird in das HM-Script eingesetzt.
        if (!ctype_digit($Ident)) {
            echo $this->Translate('Invalid Ident.') . PHP_EOL;
            return false;
        }
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
        echo $this->Translate('Error on start program.') . PHP_EOL;
        return false;
    }
}

/* @} */
