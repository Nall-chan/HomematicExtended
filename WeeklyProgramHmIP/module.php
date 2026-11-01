<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.83
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/../libs/HMWeeklyProgram.php';  // Wochenprogramm (Paramset MASTER)

/**
 * HomeMaticIPWeeklyProgram ist die Klasse für das IPS-Modul 'HomeMatic IP Weekly Program'.
 * Liest und schreibt das Wochenprogramm (Schaltzeitpunkte im Paramset MASTER) des Kanals `*_WEEK_PROFILE` eines HomeMatic IP Aktors.
 * Das Wochenprogramm wird in einem Attribut gespiegelt, damit es ohne Anfrage an die CCU angezeigt werden kann.
 * Erweitert HMBase.
 */
class HomeMaticIPWeeklyProgram extends HMBase
{
    protected const ProtocolId = \HMExtended\CCU::ProtocolHmIP;
    protected const ValuesChannel = '';

    /**
     * Attribut: Das zuletzt von der CCU gelesene bzw. geschriebene Wochenprogramm (Nummer => Felder).
     */
    private const AttributeProgram = 'Program';

    /**
     * Attribut: Anzahl der Einträge, die das Gerät im Paramset MASTER anbietet.
     */
    private const AttributeEntryCount = 'EntryCount';

    /**
     * Aufruf der Kachel: Wochenprogramm neu von der CCU lesen.
     */
    private const TileActionReload = 'Reload';

    /**
     * Aufruf der Kachel: geänderte Schaltzeitpunkte (JSON) in das Gerät schreiben.
     */
    private const TileActionSave = 'Save';

    /**
     * Eigenschaft: Die Kachel darf das Wochenprogramm ändern. Ist sie aus, ist die Kachel eine reine Anzeige.
     */
    private const PropertyAllowEdit = 'AllowEdit';

    /**
     * Attribut: Felder, die das Gerät je Schaltzeitpunkt kennt (Name ohne Präfix NN_WP_).
     */
    private const AttributeFields = 'Fields';

    /**
     * Attribut: Beschreibung der Felder eines Schaltzeitpunktes (Typ, Bereich, Werteliste), aus getParamsetDescription des Gerätes.
     */
    private const AttributeInfo = 'Info';

    /**
     * Attribut: Wert von `WEEK_PROGRAM_CHANNEL_LOCKS` (Modus der Schaltkanäle).
     */
    private const AttributeLocks = 'Locks';

    /**
     * Aufruf der Kachel: Modus (manuell/automatisch) eines Schaltkanals ändern.
     */
    private const TileActionMode = 'Mode';

    /**
     * Flags für JSON, das in eine Script-Umgebung der Kachel eingebettet wird.
     */
    private const JsonScriptFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Texte der Kachel (Schlüssel = englischer Text, übersetzt über locale.json).
     */
    private const TileTexts = [
        'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun',
        'Sunrise', 'Sunset', 'On', 'Off', 'Permanent', 'Channel', 'Reload', 'Next',
        'No switching times', 'switching time', 'switching times',
        'Condition', 'Time', 'Astro', 'State', 'Duration', 'Weekdays', 'Channels',
        'Mode', 'Manual', 'Automatic', 'Slats', 'Output behaviour', 'Ramp time', 'Color', 'Saturation', 'Color temperature', 'Effect', 'Edit', 'Apply', 'Discard', 'Add switching time', 'Delete', 'All', 'None', 'Value', 'Unsaved changes', 'Saving…',
        'Every switching time needs at least one weekday.',
        'Condition 0', 'Condition 1', 'Condition 2', 'Condition 3', 'Condition 4', 'Condition 5', 'Condition 6', 'Condition 7'
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        // Address und Protocol müssen vorhanden sein, sonst hängt sich der Symcon-Homematic-Konfigurator auf.
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyBoolean(self::PropertyAllowEdit, true);
        $this->RegisterAttributeArray(self::AttributeProgram, []);
        $this->RegisterAttributeArray(self::AttributeFields, []);
        $this->RegisterAttributeArray(self::AttributeInfo, []);
        $this->RegisterAttributeInteger(self::AttributeLocks, 0);
        $this->RegisterAttributeInteger(self::AttributeEntryCount, \HMExtended\WeeklyProgram::EntryCount);
        // Normale Kachel und Vollbild (maximierte Kachel) über das HTML-SDK gibt es erst ab Symcon 9.1, davor nur die normale Kachel
        $this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') ? constant('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') : INSTANCE_VISUALIZATION_TYPE_HTML);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Ohne ausdrücklichen Status bliebe die Instanz nach einem Fehler beim Erstellen (z.B. beim Neuladen) in 101 (wird erstellt).
        $this->SetStatus(IS_ACTIVE);
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($Address) {
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $Address . static::ValuesChannel . '".*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        $this->SetSummary($Address);
        // Fehler werden gemeldet, nach dem ersten Fehler folgt keine weitere Anfrage.
        if (($Address) && ($this->HasActiveParent())) {
            $this->LoadProgram();
        }
    }

    /**
     * Interne Funktion des SDK.
     * Liefert die Kachel mit dem aktuell gespiegelten Wochenprogramm (die Kachel selbst fragt die CCU nicht).
     */
    public function GetVisualizationTile(): string
    {
        $Config = [
            'instance'   => $this->InstanceID,
            'program'    => $this->ReadAttributeArray(self::AttributeProgram),
            'channels'   => $this->GetChannelNames(),
            'fields'     => $this->ReadAttributeArray(self::AttributeFields),
            'info'       => $this->ReadAttributeArray(self::AttributeInfo),
            'modes'      => $this->GetModes(),
            'maxEntries' => $this->ReadAttributeInteger(self::AttributeEntryCount),
            'editable'   => $this->ReadPropertyBoolean(self::PropertyAllowEdit),
            'texts'      => $this->GetTileTexts()
        ];
        return strtr((string) file_get_contents(__DIR__ . '/module.html'), [
            '/*CONFIG*/null' => json_encode($Config, self::JsonScriptFlags),
            '/*STYLE*/'      => (string) file_get_contents(__DIR__ . '/assets/tile.css'),
            '/*CORE*/'       => (string) file_get_contents(__DIR__ . '/assets/core.js')
        ]);
    }

    /**
     * Liest das Wochenprogramm von der CCU.
     *
     * @return string|false Die Schaltzeitpunkte als JSON (Nummer => Felder, nur belegte Einträge), bei einem Fehler false.
     */
    public function ReadWeeklyProgram(): string|false
    {
        $Program = $this->LoadProgram();
        if ($Program === false) {
            return false;
        }
        return json_encode($Program);
    }

    /**
     * Schreibt das Wochenprogramm in das Gerät. Die Schaltzeitpunkte ersetzen das bisherige Programm,
     * die Nummern werden lückenlos ab 1 vergeben.
     *
     * @param string $Program Schaltzeitpunkte als JSON-Array (Felder wie `WEEKDAY`, `FIXED_HOUR`, `LEVEL` ohne Präfix).
     *
     * @return bool true, wenn die CCU das Programm angenommen hat.
     */
    public function WriteWeeklyProgram(string $Program): bool
    {
        $Entries = json_decode($Program, true);
        if (!is_array($Entries)) {
            echo $this->Translate('Invalid weekly program.') . PHP_EOL;
            return false;
        }
        $Set = \HMExtended\WeeklyProgram::Encode(
            $Entries,
            count($this->ReadAttributeArray(self::AttributeProgram)),
            $this->ReadAttributeInteger(self::AttributeEntryCount),
            $this->ReadAttributeArray(self::AttributeInfo)
        );
        if (is_string($Set)) {
            echo $this->Translate($Set) . PHP_EOL;
            return false;
        }
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        if ($this->SendRPC('putParamset', $Protocol, [$Address, \HMExtended\CCU::MASTER], $Set) === null) {
            return false;
        }
        $this->WriteAttributeArray(self::AttributeProgram, array_combine(range(1, count($Entries)), array_values($Entries)) ?: []);
        return true;
    }

    /**
     * Liest den Modus der Schaltkanäle (manuell oder automatisch) von der CCU.
     *
     * @return string|false Je Schaltkanal (Reihenfolge der Kanäle des Gerätes) true bei manuellem Modus, als JSON, bei einem Fehler false.
     */
    public function ReadWeeklyProgramModes(): string|false
    {
        if (!$this->LoadLocks(false)) {
            return false;
        }
        return json_encode($this->GetModes());
    }

    /**
     * Stellt den Modus des Wochenprogramms für Schaltkanäle ein.
     *
     * @param int $Channels Bitmaske der Kanäle, Bit 0 = erster Schaltkanal des Gerätes (wie `TARGET_CHANNELS`).
     * @param string $Mode `MANU_MODE`, `AUTO_MODE_WITHOUT_RESET` oder `AUTO_MODE_WITH_RESET`.
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    public function SetWeeklyProgramMode(int $Channels, string $Mode): bool
    {
        if (!in_array($Mode, \HMExtended\WeeklyProgram::Modes, true)) {
            echo $this->Translate('Invalid mode.') . PHP_EOL;
            return false;
        }
        if (($Channels < 1) || ($Channels > 255)) {
            echo $this->Translate('Invalid channel selection.') . PHP_EOL;
            return false;
        }
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Values = [
            'WEEK_PROGRAM_TARGET_CHANNEL_LOCK'  => $Mode,
            'WEEK_PROGRAM_TARGET_CHANNEL_LOCKS' => \HMExtended\WeeklyProgram::LockMask($Channels)
        ];
        if ($this->SendRPC('putParamset', $Protocol, [$Address, \HMExtended\CCU::VALUES], $Values) === null) {
            return false;
        }
        $this->LoadLocks(true);
        return true;
    }

    /**
     * Interne Funktion des SDK.
     * Das Ereignis `WEEK_PROGRAM_CHANNEL_LOCKS` aktualisiert den Modus in der Kachel.
     */
    public function ReceiveData(string $JSONString): string
    {
        $Event = json_decode($JSONString, true);
        if (is_array($Event) && (($Event['VariableName'] ?? '') == 'WEEK_PROGRAM_CHANNEL_LOCKS') && isset($Event['VariableValue'])) {
            $this->WriteAttributeInteger(self::AttributeLocks, (int) $Event['VariableValue']);
            $this->PushProgram();
            return '';
        }
        return parent::ReceiveData($JSONString);
    }

    //################# protected

    /**
     * Verarbeitet die Aufrufe der Kachel.
     *
     * @param string $Ident Ident der Aktion.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion vollständig verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        if ($Ident == self::TileActionReload) {
            $this->LoadProgram();
            $done = true;
            return;
        }
        if ($Ident == self::TileActionSave) {
            $this->SaveFromTile((string) $Value);
            $done = true;
            return;
        }
        if ($Ident == self::TileActionMode) {
            $this->ModeFromTile((string) $Value);
            $done = true;
        }
    }

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
        $this->ApplyChanges();
    }

    //################# private

    /**
     * Liest das Paramset MASTER und speichert die belegten Einträge im Attribut.
     *
     * @return array|false Nummer => Felder, bei einem Fehler false.
     */
    private function LoadProgram(): array|false
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Master = $this->GetParamset(\HMExtended\CCU::MASTER, $Address);
        if (count($Master) == 0) {
            return false;
        }
        $Program = \HMExtended\WeeklyProgram::Decode($Master);
        $this->SendDebug('Program', $Program, 0);
        $this->WriteAttributeArray(self::AttributeProgram, $Program);
        // Anzahl der Einträge und die Felder eines Eintrags aus den Datenpunkten ableiten
        $Count = 0;
        $Fields = [];
        foreach (array_keys($Master) as $Name) {
            if (preg_match('/^(\d{2})_WP_(.+)$/', (string) $Name, $Matches) === 1) {
                $Count = max($Count, (int) $Matches[1]);
                if ($Matches[1] == '01') {
                    $Fields[] = $Matches[2];
                }
            }
        }
        $this->WriteAttributeArray(self::AttributeFields, $Fields);
        if ($Count > 0) {
            $this->WriteAttributeInteger(self::AttributeEntryCount, $Count);
        }
        if (count($this->ReadAttributeArray(self::AttributeInfo)) == 0) {
            $this->LoadInfo();
        }
        $this->LoadLocks(false);
        $this->PushProgram();
        return $Program;
    }

    /**
     * Sendet Programm, Felder, Kanalnamen und Modi an die Kachel.
     */
    private function PushProgram(): void
    {
        $this->UpdateVisualizationValue(json_encode([
            'type'     => 'program',
            'program'  => $this->ReadAttributeArray(self::AttributeProgram),
            'channels' => $this->GetChannelNames(),
            'fields'   => $this->ReadAttributeArray(self::AttributeFields),
            'info'     => $this->ReadAttributeArray(self::AttributeInfo),
            'modes'    => $this->GetModes()
        ], self::JsonScriptFlags));
    }

    /**
     * Modus der Schaltkanäle (true = manuell). Die Zahl der Kanäle richtet sich nach den gefundenen Schaltkanälen des Gerätes, mindestens 1.
     *
     * @return bool[] Index = Kanal (0 = erster Schaltkanal).
     */
    private function GetModes(): array
    {
        return \HMExtended\WeeklyProgram::ManualChannels($this->ReadAttributeInteger(self::AttributeLocks), max(count($this->GetChannelNames()), 1));
    }

    /**
     * Liest `WEEK_PROGRAM_CHANNEL_LOCKS` aus dem Paramset VALUES und speichert es.
     *
     * @param bool $Push Bei true wird die Kachel aktualisiert.
     *
     * @return bool true, wenn der Wert gelesen wurde (Fehler nur per Debug).
     */
    private function LoadLocks(bool $Push): bool
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Values = $this->SendRPC('getParamset', $Protocol, [$Address, \HMExtended\CCU::VALUES], null, false, true);
        if (!is_array($Values) || !isset($Values['WEEK_PROGRAM_CHANNEL_LOCKS'])) {
            $this->SendDebug('Modes', 'not available', 0);
            return false;
        }
        $this->WriteAttributeInteger(self::AttributeLocks, (int) $Values['WEEK_PROGRAM_CHANNEL_LOCKS']);
        if ($Push) {
            $this->PushProgram();
        }
        return true;
    }

    /**
     * Liest die Beschreibung der Felder eines Schaltzeitpunktes (Typ, Bereich, Werteliste) und speichert sie. Die Antwort enthält
     * alle Einträge, gespeichert werden nur die Felder von Eintrag 01. Fehler nur per Debug (optionale Zusatzabfrage).
     */
    private function LoadInfo(): void
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Description = $this->SendRPC('getParamsetDescription', $Protocol, [$Address, \HMExtended\CCU::MASTER], null, false, true);
        if (!is_array($Description)) {
            $this->SendDebug('Info', 'not available', 0);
            return;
        }
        $Info = \HMExtended\WeeklyProgram::ParseDescription($Description);
        $this->SendDebug('Info', $Info, 0);
        $this->WriteAttributeArray(self::AttributeInfo, $Info);
    }

    /**
     * Ändert den Modus eines Kanals auf Wunsch der Kachel.
     *
     * @param string $Value JSON mit `channel` (0 = erster Schaltkanal) und `mode`.
     */
    private function ModeFromTile(string $Value): void
    {
        if (!$this->ReadPropertyBoolean(self::PropertyAllowEdit)) {
            $Error = $this->Translate('Editing in the tile is not allowed.');
            $this->UpdateVisualizationValue(json_encode(['type' => 'error', 'text' => $Error], self::JsonScriptFlags));
            echo $Error . PHP_EOL;
            return;
        }
        $Request = json_decode($Value, true);
        if (!is_array($Request) || !isset($Request['channel'], $Request['mode']) || !is_int($Request['channel']) || ($Request['channel'] < 0) || ($Request['channel'] > 7)) {
            echo $this->Translate('Invalid channel selection.') . PHP_EOL;
            return;
        }
        ob_start();
        $Result = $this->SetWeeklyProgramMode(1 << $Request['channel'], (string) $Request['mode']);
        $Error = trim((string) ob_get_clean());
        if (!$Result) {
            $this->UpdateVisualizationValue(json_encode(['type' => 'error', 'text' => $Error], self::JsonScriptFlags));
        }
    }

    /**
     * Schreibt die in der Kachel geänderten Schaltzeitpunkte und liest das Programm danach zur Kontrolle neu.
     * Ohne Erlaubnis (Eigenschaft) wird nichts geschrieben, der Fehler geht auch an die Kachel.
     *
     * @param string $Program Schaltzeitpunkte als JSON-Array.
     */
    private function SaveFromTile(string $Program): void
    {
        if (!$this->ReadPropertyBoolean(self::PropertyAllowEdit)) {
            $Error = $this->Translate('Editing in the tile is not allowed.');
            $this->UpdateVisualizationValue(json_encode(['type' => 'error', 'text' => $Error], self::JsonScriptFlags));
            echo $Error . PHP_EOL;
            return;
        }
        // Fehlermeldungen der Prüfung erscheinen in der Kachel
        ob_start();
        $Result = $this->WriteWeeklyProgram($Program);
        $Error = trim((string) ob_get_clean());
        if (!$Result) {
            $this->UpdateVisualizationValue(json_encode(['type' => 'error', 'text' => $Error !== '' ? $Error : $this->Translate('The weekly program could not be written.')], self::JsonScriptFlags));
            return;
        }
        $this->LoadProgram();
    }

    /**
     * Namen der Schaltkanäle des Gerätes für die Zielkanäle des Wochenprogramms (Bit 0 = erster Schaltkanal).
     * Angenommen wird: die Kanäle mit Statusvariable `STATE` oder `LEVEL` und Aktion, in der Reihenfolge der Kanalnummern.
     * Fehlt ein Kanal in Symcon, zeigt die Kachel `Kanal n`. Gelesen wird nur (fremde Instanzen).
     *
     * @return string[] Namen, Index = Bit.
     */
    private function GetChannelNames(): array
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Pos = strpos($Address, ':');
        if ($Pos === false) {
            return [];
        }
        $Device = substr($Address, 0, $Pos);
        $Channels = [];
        foreach (IPS_GetInstanceListByModuleID(\HMExtended\GUID::HmDevice) as $InstanceID) {
            $InstanceAddress = (string) IPS_GetProperty($InstanceID, \HMExtended\Device\Property::Address);
            if (!str_starts_with($InstanceAddress, $Device . ':')) {
                continue;
            }
            foreach (['STATE', 'LEVEL'] as $Ident) {
                $VariableId = @IPS_GetObjectIDByIdent($Ident, $InstanceID);
                if (($VariableId !== false) && HasAction($VariableId)) {
                    $Channels[(int) substr($InstanceAddress, $Pos + 1)] = IPS_GetName($InstanceID);
                    break;
                }
            }
        }
        ksort($Channels);
        return array_values($Channels);
    }

    /**
     * Übersetzte Texte der Kachel.
     *
     * @return string[] Englischer Text => übersetzter Text.
     */
    private function GetTileTexts(): array
    {
        $Texts = [];
        foreach (self::TileTexts as $Text) {
            $Texts[$Text] = $this->Translate($Text);
        }
        return $Texts;
    }
}

/* @} */
