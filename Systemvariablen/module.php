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
 * @version       3.80
 */
require_once __DIR__ . '/../libs/HMSystemVariableBase.php';  // Basisklasse Systemvariablen

/**
 * HomeMaticSystemvariablen ist die Klasse für das IPS-Modul 'HomeMatic Systemvariablen'.
 * Bildet die in der Konfiguration gewählten Systemvariablen der CCU als Statusvariablen ab.
 * Die Statusvariablen tragen die ID der Systemvariable auf der CCU als Ident.
 * Alarmvariablen bildet das Modul 'HomeMatic Alarmvariable' ab.
 * Erweitert HMSystemVariableBase.
 *
 * @property bool $ShowDeselectHint true, solange der Hinweis zum Abwählen im geöffneten Formular noch nicht gezeigt wurde.
 *
 * @method void RegisterAttributeArray(string $name, array $Value, int $Size = 0)
 * @method array ReadAttributeArray(string $name)
 * @method void WriteAttributeArray(string $name, array $value)
 */
class HomeMaticSystemvariablen extends HMSystemVariableBase
{
    protected const ProgramPrefix = 'Symcon Systemvariablen ';

    /**
     * Ident-Präfix der Alarmvariablen bis Version 3.78. Sie und ihre Untervariablen werden nicht mehr aktualisiert.
     */
    private const LegacyAlarmPrefix = 'AlDP';
    /**
     * Profile bis Version 3.78: `HM.SysVar<Instanz-ID>.<CCU-ID>` und das Profil der Alarmvariablen.
     */
    private const LegacyProfilePrefix = 'HM.SysVar';
    private const LegacyAlarmProfile = 'HM.AlReceipt';
    /**
     * Aktionen der Schaltflächen im Formular.
     */
    private const ActionDeleteUnused = 'DeleteUnusedVariables';
    private const ActionDeleteLegacyAlarms = 'DeleteLegacyAlarmVariables';
    private const ActionVariableEdited = 'VariableEdited';
    /**
     * Version, mit der die veralteten Funktionsnamen entfernt werden.
     */
    private const AliasRemovedIn = '3.90';

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Variables, '[]');
        $this->RegisterAttributeArray(\HMExtended\Device\Attribute::Metadata, []);
        $this->ShowDeselectHint = true;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->ShowDeselectHint = true;
        parent::ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     * Beim Löschen der Instanz werden die Profile bis Version 3.78 entfernt, die nur diese Instanz genutzt hat.
     */
    public function Destroy(): void
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            foreach (IPS_GetVariableProfileList() as $Profile) {
                if (str_starts_with($Profile, self::LegacyProfilePrefix . $this->InstanceID . '.')) {
                    IPS_DeleteVariableProfile($Profile);
                }
            }
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     * Migration der Instanzen bis Version 3.78: Der Auslöser (EventID, Variable PRESS_SHORT einer Homematic-Instanz der virtuellen
     * Fernbedienung) wird zur Adresse der Taste, alle vorhandenen Statusvariablen werden in der Auswahlliste angehakt.
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (!is_object($Data) || !property_exists($Data, 'configuration') || !property_exists($Data->configuration, 'EventID')
            || property_exists($Data->configuration, \HMExtended\Device\Property::Variables)) {
            return $JSONData;
        }
        $EventId = (int) $Data->configuration->EventID;
        if (($EventId > 0) && IPS_VariableExists($EventId)) {
            $Parent = IPS_GetParent($EventId);
            if (IPS_InstanceExists($Parent) && (IPS_GetInstance($Parent)['ModuleInfo']['ModuleID'] == \HMExtended\GUID::HmDevice)) {
                $Address = (string) IPS_GetProperty($Parent, \HMExtended\Device\Property::Address);
                $Protocol = \HMExtended\SystemVariables::ProtocolOfKey($Address);
                if ($Protocol !== false) {
                    $Data->configuration->{\HMExtended\Device\Property::Address} = $Address;
                    $Data->configuration->{\HMExtended\Device\Property::Protocol} = $Protocol;
                }
            }
        }
        $Data->configuration->{\HMExtended\Device\Property::Variables} = json_encode($this->RowsOfExistingVariables());
        $this->SendDebug('Migrate', json_encode($Data->configuration), 0);
        return json_encode($Data);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        // Der Hinweis zum Abwählen erscheint einmal je Öffnen des Formulars.
        $this->ShowDeselectHint = true;
        $Form = $this->AddKeyAndProgramToForm($Form);
        $Form['elements'] = $this->SetFormField($Form['elements'], \HMExtended\Device\Property::Variables, 'values', $this->VariableListValues());
        $Unused = $this->UnusedIdents();
        // Immer sichtbar (der Hinweis zum Abwählen verweist darauf), bedienbar nur wenn es etwas zu löschen gibt.
        $Form['actions'] = $this->SetFormField($Form['actions'], self::ActionDeleteUnused, 'enabled', count($Unused) > 0);
        $Form['actions'] = $this->SetFormField($Form['actions'], self::ActionDeleteUnused, 'caption', sprintf($this->Translate('Delete %d status variables without selection'), count($Unused)));
        $Legacy = count($this->LegacyAlarmIdents()) > 0;
        $Form['actions'] = $this->SetFormField($Form['actions'], 'LegacyAlarmHint', 'visible', $Legacy);
        $Form['actions'] = $this->SetFormField($Form['actions'], self::ActionDeleteLegacyAlarms, 'visible', $Legacy);
        return json_encode($Form);
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadSystemVariables'.
     * Liest alle gewählten Systemvariablen von der CCU.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function ReadSystemVariables(): bool
    {
        if (!$this->UpdateVariables()) {
            return false;
        }
        return $this->ReadValues(true);
    }

    /**
     * IPS-Instanz-Funktion 'HM_SystemVariablesTimer'.
     * Veraltet, ruft ReadSystemVariables auf.
     */
    public function SystemVariablesTimer(): bool
    {
        $this->LogAlias('HM_SystemVariablesTimer', 'HM_ReadSystemVariables');
        return $this->ReadSystemVariables();
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean'.
     * Schreibt einen bool-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der Ident der Statusvariable (= ID der Systemvariable auf der CCU).
     * @param bool   $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueBoolean(string $Parameter, bool $Value): bool
    {
        return $this->WriteValue($Parameter, $Value, VARIABLETYPE_BOOLEAN);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger'.
     * Schreibt einen integer-Wert in eine Systemvariable der CCU (Wertelisten und Zähler).
     *
     * @param string $Parameter Der Ident der Statusvariable (= ID der Systemvariable auf der CCU).
     * @param int    $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueInteger(string $Parameter, int $Value): bool
    {
        return $this->WriteValue($Parameter, $Value, VARIABLETYPE_INTEGER);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat'.
     * Schreibt einen float-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der Ident der Statusvariable (= ID der Systemvariable auf der CCU).
     * @param float  $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueFloat(string $Parameter, float $Value): bool
    {
        return $this->WriteValue($Parameter, $Value, VARIABLETYPE_FLOAT);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString'.
     * Schreibt einen string-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der Ident der Statusvariable (= ID der Systemvariable auf der CCU).
     * @param string $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueString(string $Parameter, string $Value): bool
    {
        return $this->WriteValue($Parameter, $Value, VARIABLETYPE_STRING);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean2'. Veraltet, ruft WriteValueBoolean auf.
     */
    public function WriteValueBoolean2(string $Parameter, bool $Value): bool
    {
        $this->LogAlias('HM_WriteValueBoolean2', 'HM_WriteValueBoolean');
        return $this->WriteValueBoolean($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger2'. Veraltet, ruft WriteValueInteger auf.
     */
    public function WriteValueInteger2(string $Parameter, int $Value): bool
    {
        $this->LogAlias('HM_WriteValueInteger2', 'HM_WriteValueInteger');
        return $this->WriteValueInteger($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat2'. Veraltet, ruft WriteValueFloat auf.
     */
    public function WriteValueFloat2(string $Parameter, float $Value): bool
    {
        $this->LogAlias('HM_WriteValueFloat2', 'HM_WriteValueFloat');
        return $this->WriteValueFloat($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString2'. Veraltet, ruft WriteValueString auf.
     */
    public function WriteValueString2(string $Parameter, string $Value): bool
    {
        $this->LogAlias('HM_WriteValueString2', 'HM_WriteValueString');
        return $this->WriteValueString($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_AlarmReceipt'.
     * Veraltet: Alarmvariablen bildet das Modul 'HomeMatic Alarmvariable' ab. Quittiert den Alarm einer alten Alarmvariable (Ident `AlDP<CCU-ID>`).
     *
     * @param string $Ident Ident der alten Alarmvariable.
     *
     * @return bool True wenn quittiert wurde, sonst false.
     */
    public function AlarmReceipt(string $Ident): bool
    {
        $this->LogAlias('HM_AlarmReceipt', 'HM_AlarmReceipt (HomeMatic Alarmvariable)');
        if (!str_starts_with($Ident, self::LegacyAlarmPrefix) || !ctype_digit(substr($Ident, strlen(self::LegacyAlarmPrefix)))) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return false;
        }
        $Output = $this->RunScript(\HMExtended\SystemVariables::AlarmReceiptScript((int) substr($Ident, strlen(self::LegacyAlarmPrefix))));
        if ($Output === false) {
            return false;
        }
        return trim($Output) === 'true';
    }

    //################# protected

    /**
     * Verarbeitet Bedienaktionen der Statusvariablen und die Schaltflächen des Formulars.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        $done = true;
        switch ($Ident) {
            case self::ActionDeleteUnused:
                foreach ($this->UnusedIdents() as $UnusedIdent) {
                    $this->UnregisterVariable($UnusedIdent);
                }
                $this->ReloadForm();
                return;
            case self::ActionDeleteLegacyAlarms:
                $this->DeleteLegacyAlarmVariables();
                $this->ReloadForm();
                return;
            case self::ActionVariableEdited:
                // Hinweis beim ersten Abwählen einer Systemvariable, zu der eine Statusvariable existiert.
                [$Id, $Use] = (json_decode((string) $Value, true) ?? []) + [0, true];
                if (!$Use && $this->ShowDeselectHint && ($this->FindIDForIdent((string) $Id) > 0)) {
                    $this->ShowDeselectHint = false;
                    $this->UpdateFormField('DeselectHint', 'visible', true);
                }
                return;
        }
        if (!ctype_digit($Ident)) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return;
        }
        $VariableId = $this->FindIDForIdent($Ident);
        if ($VariableId == 0) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return;
        }
        $Type = IPS_GetVariable($VariableId)['VariableType'];
        switch ($Type) {
            case VARIABLETYPE_BOOLEAN:
                $Value = (bool) $Value;
                break;
            case VARIABLETYPE_INTEGER:
                $Value = (int) $Value;
                break;
            case VARIABLETYPE_FLOAT:
                $Value = (float) $Value;
                break;
            default:
                $Value = (string) $Value;
                break;
        }
        $this->WriteValue($Ident, $Value, $Type);
    }

    /**
     * Liest die Eigenschaften aller Systemvariablen mit einem HM-Script und legt die Statusvariablen der gewählten Systemvariablen an.
     * Vorhandene Statusvariablen werden nicht umbenannt. Fehlt eine Systemvariable auf der CCU oder hat sich ihr Typ geändert, wird das gemeldet.
     *
     * @return bool true, wenn die CCU geantwortet hat.
     */
    protected function UpdateVariables(): bool
    {
        $Selected = $this->SelectedIds();
        $this->UnregisterProfile(self::LegacyAlarmProfile);
        if (count($Selected) == 0) {
            $this->WriteAttributeArray(\HMExtended\Device\Attribute::Metadata, []);
            return true;
        }
        $Output = $this->RunScript(\HMExtended\SystemVariables::ListScript());
        if ($Output === false) {
            return false;
        }
        $List = \HMExtended\SystemVariables::ParseList($Output);
        $Metadata = [];
        foreach ($Selected as $Id) {
            // Hinweise ohne Abbruch: die übrigen Systemvariablen werden gelesen, das Formular markiert die Zeile.
            if (!isset($List[$Id]) || !\HMExtended\SystemVariables::IsSupported($List[$Id])) {
                $this->LogMessage(sprintf($this->Translate('System variable %d does not exist on the CCU.'), $Id), KL_WARNING);
                continue;
            }
            $Variable = $List[$Id];
            $Ident = (string) $Id;
            $Type = \HMExtended\SystemVariables::VariableTypes[$Variable['ValueType']];
            $VariableId = $this->FindIDForIdent($Ident);
            if (($VariableId > 0) && (IPS_GetVariable($VariableId)['VariableType'] != $Type)) {
                // MaintainVariable würde die Statusvariable samt Archivdaten löschen und neu anlegen.
                $this->LogMessage(sprintf($this->Translate('Type of CCU system variable %s has changed.'), $Variable['Name']), KL_WARNING);
                continue;
            }
            $LegacyProfile = self::LegacyProfilePrefix . $this->InstanceID . '.' . $Ident;
            if (($VariableId > 0) && (IPS_GetVariable($VariableId)['VariableProfile'] == $LegacyProfile)) {
                // Profil bis Version 3.78 lösen, danach gilt die Darstellung.
                $this->MaintainVariable($Ident, $Variable['Name'], $Type, '', 0, true);
            }
            $this->MaintainVariable($Ident, $Variable['Name'], $Type, \HMExtended\SystemVariables::Presentation(
                $Variable,
                VARIABLE_PRESENTATION_ENUMERATION,
                VARIABLE_PRESENTATION_SWITCH,
                VARIABLE_PRESENTATION_VALUE_INPUT
            ), 0, true);
            $this->EnableAction($Ident);
            if (IPS_VariableProfileExists($LegacyProfile)) {
                IPS_DeleteVariableProfile($LegacyProfile);
            }
            $Metadata[$Id] = $Variable;
        }
        $this->WriteAttributeArray(\HMExtended\Device\Attribute::Metadata, $Metadata);
        return true;
    }

    /**
     * Liest die Werte der gewählten Systemvariablen mit einem HM-Script.
     *
     * @param bool $Full true: alle Werte, false: nur seit dem letzten Lesen aktualisierte (Zeitstempel der CCU).
     *
     * @return bool true bei Erfolg.
     */
    protected function ReadValues(bool $Full): bool
    {
        $Ids = array_keys($this->ReadAttributeArray(\HMExtended\Device\Attribute::Metadata));
        if (count($Ids) == 0) {
            return true;
        }
        $Output = $this->RunScript(\HMExtended\SystemVariables::ReadScript($Ids, $Full ? '' : $this->LastTimestamp));
        if ($Output === false) {
            return false;
        }
        $Result = \HMExtended\SystemVariables::ParseRead($Output);
        if ($Result['Now'] === '') {
            echo $this->Translate('Invalid response from CCU.') . PHP_EOL;
            return false;
        }
        $this->LastTimestamp = $Result['Now'];
        foreach ($Result['Missing'] as $Id) {
            $this->SendDebug((string) $Id, 'missing on CCU', 0);
        }
        foreach ($Result['Values'] as $Id => $Value) {
            $Ident = (string) $Id;
            $VariableId = $this->FindIDForIdent($Ident);
            if ($VariableId == 0) {
                continue;
            }
            $Type = \HMExtended\SystemVariables::VariableTypes[$Value['ValueType']] ?? -1;
            if (IPS_GetVariable($VariableId)['VariableType'] != $Type) {
                $this->SendDebug($Ident, 'type changed', 0);
                continue;
            }
            $this->SendDebug($Ident, $Value['Value'], 0);
            $this->SetValue($Ident, $Value['Value']);
        }
        return true;
    }

    /**
     * Liefert die gewählten Systemvariablen mit ihrem Typ für das CCU-Programm.
     *
     * @return int[] CCU-ID => ValueType.
     */
    protected function ProgramVariables(): array
    {
        return array_map(function (array $Variable)
        {
            return $Variable['ValueType'];
        }, $this->ReadAttributeArray(\HMExtended\Device\Attribute::Metadata));
    }

    //################# PRIVATE

    /**
     * Schreibt einen Wert in eine Systemvariable der CCU.
     *
     * @param string $Ident Ident der Statusvariable (= CCU-ID).
     * @param mixed $Value Der Wert.
     * @param int $Type Erwarteter Typ der Statusvariable.
     *
     * @return bool true bei Erfolg.
     */
    private function WriteValue(string $Ident, mixed $Value, int $Type): bool
    {
        $VariableId = ctype_digit($Ident) ? $this->FindIDForIdent($Ident) : 0;
        if ($VariableId == 0) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return false;
        }
        if (IPS_GetVariable($VariableId)['VariableType'] != $Type) {
            echo sprintf($this->Translate('Wrong data type for %s.'), $Ident) . PHP_EOL;
            return false;
        }
        $Output = $this->RunScript(\HMExtended\SystemVariables::WriteScript((int) $Ident, $Value));
        if ($Output === false) {
            return false;
        }
        if (trim($Output) !== 'true') {
            $this->SendDebug('Write ' . $Ident, $Output, 0);
            echo sprintf($this->Translate('Error on write CCU system variable %s.'), $Ident) . PHP_EOL;
            return false;
        }
        if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus)) {
            $this->SetValue($Ident, $Value);
        }
        return true;
    }

    /**
     * Liefert die CCU-IDs der in der Konfiguration gewählten Systemvariablen.
     *
     * @return int[]
     */
    private function SelectedIds(): array
    {
        $Ids = [];
        foreach (json_decode($this->ReadPropertyString(\HMExtended\Device\Property::Variables), true) ?? [] as $Row) {
            if (($Row['Use'] ?? false) && isset($Row['Id'])) {
                $Ids[] = (int) $Row['Id'];
            }
        }
        return array_values(array_unique($Ids));
    }

    /**
     * Liefert die Zeilen der Auswahl für alle vorhandenen Statusvariablen (alle gewählt).
     *
     * @return array[]
     */
    private function RowsOfExistingVariables(): array
    {
        $Rows = [];
        foreach ($this->SystemVariableIdents() as $Ident) {
            $Rows[] = ['Id' => (int) $Ident, 'Use' => true];
        }
        return $Rows;
    }

    /**
     * Liefert die Idents aller Statusvariablen dieser Instanz, die eine Systemvariable abbilden (Ident = CCU-ID).
     *
     * @return string[]
     */
    private function SystemVariableIdents(): array
    {
        $Idents = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildId) {
            if (!IPS_VariableExists($ChildId)) {
                continue;
            }
            $Ident = IPS_GetObject($ChildId)['ObjectIdent'];
            if (ctype_digit($Ident)) {
                $Idents[] = $Ident;
            }
        }
        return $Idents;
    }

    /**
     * Liefert die Idents der Statusvariablen, deren Systemvariable nicht mehr gewählt ist.
     *
     * @return string[]
     */
    private function UnusedIdents(): array
    {
        $Selected = array_map('strval', $this->SelectedIds());
        return array_values(array_diff($this->SystemVariableIdents(), $Selected));
    }

    /**
     * Liefert die Idents der Alarmvariablen bis Version 3.78.
     *
     * @return string[]
     */
    private function LegacyAlarmIdents(): array
    {
        $Idents = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildId) {
            $Ident = IPS_GetObject($ChildId)['ObjectIdent'];
            if (str_starts_with($Ident, self::LegacyAlarmPrefix)) {
                $Idents[] = $Ident;
            }
        }
        return $Idents;
    }

    /**
     * Löscht die Alarmvariablen bis Version 3.78 samt ihrer Untervariablen (Erster/Letzter Alarm, Raum, Name) und deren Profile.
     * Wird nur über die Schaltfläche im Formular (mit Bestätigung) ausgeführt.
     */
    private function DeleteLegacyAlarmVariables(): void
    {
        foreach ($this->LegacyAlarmIdents() as $Ident) {
            $VariableId = $this->FindIDForIdent($Ident);
            if ($VariableId == 0) {
                continue;
            }
            foreach (IPS_GetChildrenIDs($VariableId) as $ChildId) {
                if (IPS_VariableExists($ChildId)) {
                    IPS_DeleteVariable($ChildId);
                }
            }
            $Profile = self::LegacyProfilePrefix . $this->InstanceID . '.' . substr($Ident, strlen(self::LegacyAlarmPrefix));
            $this->UnregisterVariable($Ident);
            if (IPS_VariableProfileExists($Profile)) {
                IPS_DeleteVariableProfile($Profile);
            }
        }
    }

    /**
     * Liefert die Zeilen der Auswahlliste: alle unterstützten Systemvariablen der CCU, ergänzt um gewählte, die auf der CCU fehlen (rot, Typ `fehlt`).
     * Ohne Verbindung zur CCU werden nur die gewählten Variablen mit den zuletzt gelesenen Eigenschaften angezeigt, ohne sie als fehlend zu markieren.
     *
     * @return array[]
     */
    private function VariableListValues(): array
    {
        $Selected = $this->SelectedIds();
        $List = false;
        if ($this->HasActiveParent()) {
            $Output = $this->RunScriptQuiet(\HMExtended\SystemVariables::ListScript());
            if ($Output !== false) {
                $List = array_filter(\HMExtended\SystemVariables::ParseList($Output), [\HMExtended\SystemVariables::class, 'IsSupported']);
            }
        }
        $Metadata = $this->ReadAttributeArray(\HMExtended\Device\Attribute::Metadata);
        $Values = [];
        foreach ($List ?: [] as $Id => $Variable) {
            $Values[] = $this->VariableListRow($Id, $Variable, in_array($Id, $Selected));
        }
        foreach ($Selected as $Id) {
            if (isset($List[$Id])) {
                continue;
            }
            if ($List === false) {
                // CCU nicht erreichbar: zuletzt gelesene Eigenschaften, keine Aussage über fehlende Variablen.
                $Values[] = $this->VariableListRow($Id, $Metadata[$Id] ?? null, true);
                continue;
            }
            $Row = $this->VariableListRow($Id, null, true);
            $Row['Type'] = $this->Translate('missing');
            $Row['rowColor'] = '#FFC0C0';
            $Values[] = $Row;
        }
        usort($Values, function (array $A, array $B)
        {
            return [$A['Internal'] !== '', mb_strtolower($A['Name'])] <=> [$B['Internal'] !== '', mb_strtolower($B['Name'])];
        });
        return $Values;
    }

    /**
     * Liefert eine Zeile der Auswahlliste. Als Name wird der Objektname der Statusvariable angezeigt, sofern sie existiert,
     * sonst der Name der Systemvariable auf der CCU (nur Anzeige).
     *
     * @param int $Id CCU-ID.
     * @param array|null $Variable Eigenschaften der Systemvariable, null wenn unbekannt.
     * @param bool $Use Gewählt.
     */
    private function VariableListRow(int $Id, ?array $Variable, bool $Use): array
    {
        $TypeCaptions = [
            \HMExtended\SystemVariables::ValueTypeBool    => 'Boolean',
            \HMExtended\SystemVariables::ValueTypeInteger => 'Integer',
            \HMExtended\SystemVariables::ValueTypeFloat   => 'Number',
            \HMExtended\SystemVariables::ValueTypeString  => 'String'
        ];
        $Type = '';
        if ($Variable !== null) {
            $Type = ($Variable['ValueType'] == \HMExtended\SystemVariables::ValueTypeInteger) && ($Variable['SubType'] == \HMExtended\SystemVariables::SubTypeEnum)
                ? 'Value list'
                : ($TypeCaptions[$Variable['ValueType']] ?? '');
        }
        $VariableId = $this->FindIDForIdent((string) $Id);
        return [
            'Use'      => $Use,
            'Id'       => $Id,
            'Name'     => ($VariableId > 0) ? IPS_GetName($VariableId) : (($Variable !== null) ? $Variable['Name'] : ''),
            'Type'     => ($Type !== '') ? $this->Translate($Type) : '',
            // Anzeige in der Liste: Ja bzw. leer
            'Internal' => (($Variable !== null) && $Variable['Internal']) ? $this->Translate('Yes') : ''
        ];
    }

    /**
     * Meldet den Aufruf einer veralteten Funktion im Log. Das Ergebnis der Funktion kommt trotzdem beim Aufrufer an.
     */
    private function LogAlias(string $Old, string $New): void
    {
        $this->LogMessage(sprintf($this->Translate('%s is deprecated and will be removed in version %s, use %s.'), $Old, self::AliasRemovedIn, $New), KL_WARNING);
    }
}

/* @} */
