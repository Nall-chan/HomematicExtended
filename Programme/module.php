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
 * Welche Programme als Variable angelegt werden, steht im Attribut `Selected` (CCU-IDs), das die Liste im Aktionsbereich pflegt.
 *
 * @method void RegisterAttributeArray(string $name, array $Value, int $Size = 0)
 * @method array ReadAttributeArray(string $name)
 * @method void WriteAttributeArray(string $name, array $value)
 */
class HomeMaticProgramme extends HMBase
{
    protected const SummaryShowsHost = true;
    /** Attribut: CCU-IDs der Programme, zu denen eine Variable angelegt wird. */
    private const AttributeSelected = 'Selected';
    private const ActionProgramEdited = 'ProgramEdited';
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
        $this->RegisterAttributeArray(self::AttributeSelected, []);
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

    /**
     * Interne Funktion des SDK.
     * Migration der Instanzen bis Version 3.90: Ist das Attribut `Selected` leer, werden alle vorhandenen Variablen der
     * Programme (Ident = CCU-ID) als gewählt übernommen. Objekte ohne Ident oder mit anderem Ident stammen nicht von diesem Modul.
     *
     * @param string $JSONData Die Daten der Instanz.
     *
     * @return string Die geänderten Daten, ein leerer String bedeutet keine Änderung.
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (!is_object($Data)) {
            return '';
        }
        $Attributes = (isset($Data->attributes) && is_object($Data->attributes)) ? $Data->attributes : new stdClass();
        $Current = json_decode((string) ($Attributes->{self::AttributeSelected} ?? '[]'), true);
        if (!empty($Current)) {
            return '';
        }
        $Ids = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildId) {
            $Ident = IPS_GetObject($ChildId)['ObjectIdent'];
            if (IPS_VariableExists($ChildId) && ($Ident !== '') && ctype_digit($Ident)) {
                $Ids[] = (int) $Ident;
            }
        }
        if (count($Ids) == 0) {
            return '';
        }
        $Attributes->{self::AttributeSelected} = json_encode($Ids);
        $Data->attributes = $Attributes;
        $this->SendDebug('Migrate', json_encode($Data), 0);
        return json_encode($Data);
    }

    /**
     * Interne Funktion des SDK.
     * Die Liste der Programme steht im Aktionsbereich, ihre Werte kommen aus der CCU und dem Attribut `Selected`.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        foreach ($Form['actions'] as &$Action) {
            if (($Action['name'] ?? '') === 'Programs') {
                $Action['values'] = $this->ProgramListValues();
            }
        }
        return json_encode($Form);
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
        if ($Ident === self::ActionProgramEdited) {
            [$Id, $Use] = (json_decode((string) $Value, true) ?? []) + [0, false];
            $this->SelectProgram((int) $Id, (bool) $Use);
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
     * Liest die Namen aller Programme der CCU.
     *
     * @return array<int,string>|false CCU-ID => Name, bei einem Fehler false (die Meldung erhält der Aufrufer).
     */
    private function FetchPrograms(): array|false
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
        $Programs = [];
        // Spalten: ID des Programmes, Name
        foreach (\HMExtended\ScriptParser::ParseTable(\HMExtended\ScriptParser::SplitOutput($ret)[0]) as $data) {
            if ((count($data) < 2) || !ctype_digit($data[0])) {
                continue;
            }
            $Programs[(int) $data[0]] = $data[1];
        }
        return $Programs;
    }

    /**
     * Liest alle Programme aus der CCU und legt zu den gewählten Programmen eine Variable mit Aktion an.
     * Vorhandene Variablen werden nicht umbenannt. Nicht gewählte Programme erhalten keine Variable.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    private function ReadCCUPrograms(): bool
    {
        $Programs = $this->FetchPrograms();
        if ($Programs === false) {
            return false;
        }
        $Selected = $this->SelectedIds();
        foreach ($Programs as $Id => $Name) {
            if (in_array($Id, $Selected, true)) {
                $this->CreateProgramVariable((string) $Id, $Name);
            }
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
     * Legt die Variable eines Programmes an bzw. pflegt ihre Darstellung und Aktion.
     */
    private function CreateProgramVariable(string $Ident, string $Name): void
    {
        $Presentation = \HMExtended\HeatingPresentation::Get(\HMExtended\HeatingPresentation::Execute, VARIABLETYPE_INTEGER, true, [], fn (string $Text): string => $this->Translate($Text));
        $this->SendDebug($Ident, $Name, 0);
        $this->MaintainPresentationVariable($Ident, $Name, VARIABLETYPE_INTEGER, $Presentation);
        $this->EnableAction($Ident);
    }

    /**
     * Wählt ein Programm ab oder an (Bedienung der Liste im Formular).
     * Abwählen ändert das Attribut und löscht die Variable, der Hinweis steht im Formular.
     * Anwählen legt die Variable an, wenn das Programm auf der CCU existiert.
     *
     * @param int $Id CCU-ID des Programmes.
     * @param bool $Use true: Variable anlegen, false: Variable löschen.
     */
    private function SelectProgram(int $Id, bool $Use): void
    {
        $Selected = $this->SelectedIds();
        if (!$Use) {
            $this->WriteAttributeArray(self::AttributeSelected, array_values(array_diff($Selected, [$Id])));
            $this->UnregisterVariable((string) $Id);
            return;
        }
        $Programs = $this->FetchPrograms();
        if ($Programs === false) {
            return;
        }
        if (!isset($Programs[$Id])) {
            echo sprintf($this->Translate('CCU program %d does not exist.'), $Id) . PHP_EOL;
            return;
        }
        $Selected[] = $Id;
        $this->WriteAttributeArray(self::AttributeSelected, array_values(array_unique($Selected)));
        $this->CreateProgramVariable((string) $Id, $Programs[$Id]);
    }

    /**
     * Liefert die CCU-IDs der gewählten Programme.
     *
     * @return int[]
     */
    private function SelectedIds(): array
    {
        return array_map('intval', $this->ReadAttributeArray(self::AttributeSelected));
    }

    /**
     * Liefert die Zeilen der Liste: alle Programme der CCU, ergänzt um gewählte, die auf der CCU fehlen (rot).
     * Ohne Verbindung zur CCU werden nur die gewählten Programme angezeigt.
     *
     * @return array[]
     */
    private function ProgramListValues(): array
    {
        $Selected = $this->SelectedIds();
        $Programs = false;
        if ($this->HasActiveParent()) {
            // Fehler beim Öffnen des Formulars nur als Debug, nicht als Meldung.
            ob_start();
            $Programs = $this->FetchPrograms();
            $Errors = trim((string) ob_get_clean());
            if ($Errors !== '') {
                $this->SendDebug('Error', $Errors, 0);
            }
        }
        $Values = [];
        foreach ($Programs ?: [] as $Id => $Name) {
            $VariableId = $this->FindIDForIdent((string) $Id);
            $Values[] = ['Use' => in_array($Id, $Selected, true), 'Name' => ($VariableId > 0) ? IPS_GetName($VariableId) : $Name, 'Id' => $Id];
        }
        foreach ($Selected as $Id) {
            if (isset($Programs[$Id])) {
                continue;
            }
            $VariableId = $this->FindIDForIdent((string) $Id);
            $Row = ['Use' => true, 'Name' => ($VariableId > 0) ? IPS_GetName($VariableId) : '', 'Id' => $Id];
            if ($Programs !== false) {
                $Row['rowColor'] = '#FFC0C0';
            }
            $Values[] = $Row;
        }
        usort($Values, fn (array $A, array $B): int => mb_strtolower($A['Name']) <=> mb_strtolower($B['Name']));
        return $Values;
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
