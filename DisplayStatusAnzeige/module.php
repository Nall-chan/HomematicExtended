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
 * HomeMaticDisWM55 ist die Klasse für das IPS-Modul 'HomeMatic Dis-WM55'.
 * Erweitert HMBase.
 *
 * Die Tasten des Displays (Kanal :1 und :2 der Geräteadresse) werden direkt über den Datenfluss des Homematic Sockets empfangen.
 * Die Ausgabe (SUBMIT) geht immer an die konfigurierte Adresse (inklusive Kanal).
 * Zusätzliche Auslöser (Variablen fremder Instanzen) können in den Experteneinstellungen eingetragen werden.
 *
 * @property int $Page Die aktuelle Seite (0 = noch keine Seite gewählt, sonst 1 bis MaxPage).
 * @property array $Triggers Auslöser: Schlüssel 'Adresse|Datenpunkt' => ['Action' => int, 'Target' => string].
 * @property int[] $ExpertVariables IDs der Variablen aus den Experteneinstellungen (für VM_DELETE).
 */
class HomeMaticDisWM55 extends HMBase
{
    // Aktionen
    public const ActionPageUp = 0;
    public const ActionPageDown = 1;
    public const ActionUp = 2;
    public const ActionDown = 3;

    private const PropertyMaxPage = 'MaxPage';
    private const PropertyTimeout = 'Timeout';
    private const PropertyScriptID = 'ScriptID';
    private const PropertyExpertTriggers = 'ExpertTriggers';
    private const TimerTimeout = 'DisplayTimeout';
    private const ScriptIdent = 'DisplayScript';
    private const ButtonRestoreScript = 'RestoreScript';
    private const ActionRestoreScript = 'RestoreDisplayScript';
    private const PressShort = 'PRESS_SHORT';
    private const PressLong = 'PRESS_LONG';
    private const Placeholder = 'XXX';
    private const SubmitAttempts = 2; // erster Versuch und genau eine Wiederholung ohne Pause

    // Status
    private const StatusNoAddress = IS_INACTIVE;
    private const StatusInvalidTrigger = IS_EBASE + 2;
    private const StatusNoScript = IS_EBASE + 3;
    private const StatusTimeout = IS_EBASE + 4;
    private const StatusMaxPage = IS_EBASE + 5;
    private const StatusNoChannel = IS_EBASE + 6;

    /**
     * Wert für $_IPS['ACTION'] im Display-Script.
     *
     * @var string[]
     */
    private static array $ActionNames = [
        self::ActionPageUp   => 'UP',
        self::ActionPageDown => 'DOWN',
        self::ActionUp       => 'ActionUP',
        self::ActionDown     => 'ActionDOWN'
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        // Adresse (Gerät mit Kanal, an diese Adresse wird ausgegeben) und Protokoll. Der Platzhalter verhindert Fehler im Symcon Homematic Konfigurator.
        $this->RegisterHMPropertys(self::Placeholder . '9999995');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger(self::PropertyMaxPage, 1);
        $this->RegisterPropertyInteger(self::PropertyTimeout, 0);
        $this->RegisterPropertyString(self::PropertyExpertTriggers, '[]');

        $ID = $this->FindIDForIdent(self::ScriptIdent);
        if ($ID == 0) {
            $this->RegisterScript(self::ScriptIdent, 'Display Script', $this->CreateDisplayScript(), -1);
            $ID = $this->FindIDForIdent(self::ScriptIdent);
            IPS_SetHidden($ID, true);
        }
        $this->RegisterPropertyInteger(self::PropertyScriptID, $ID);

        $this->UnregisterVariable('PAGE');
        $this->Page = 0;
        $this->Triggers = [];
        $this->ExpertVariables = [];
        $this->RegisterTimer(self::TimerTimeout, 0, 'HM_ResetTimer($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     * Übernimmt die Adresse und Zusatzauslöser aus den alten Eigenschaften (PageUpID, PageDownID, ActionUpID, ActionDownID).
     * Auslöser des gleichen Gerätes mit Standardzuordnung entfallen, alle anderen kommen in die Experteneinstellungen.
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (!is_object($Data) || !property_exists($Data, 'configuration')) {
            return $JSONData;
        }
        $Old = [
            'PageUpID'     => [self::ActionPageUp, self::PressShort, ':2'],
            'PageDownID'   => [self::ActionPageDown, self::PressShort, ':1'],
            'ActionUpID'   => [self::ActionUp, self::PressLong, ':2'],
            'ActionDownID' => [self::ActionDown, self::PressLong, ':1']
        ];
        // Sind die alten Einstellungen nicht mehr vorhanden (bereits migriert oder neue Instanz), gibt es nichts zu übernehmen.
        if (!array_intersect(array_keys($Old), array_keys(get_object_vars($Data->configuration)))) {
            return $JSONData;
        }
        $Entries = [];
        foreach ($Old as $Property => $Default) {
            if (!property_exists($Data->configuration, $Property)) {
                continue;
            }
            $VariableId = (int) $Data->configuration->{$Property};
            if (($VariableId <= 0) || (!IPS_VariableExists($VariableId))) {
                continue;
            }
            $Source = $this->GetTriggerSource($VariableId);
            if ($Source === false) {
                $Entries[] = [$VariableId, $Default, '', '', 0];
                continue;
            }
            $Entries[] = [$VariableId, $Default, $Source['Address'], $Source['Datapoint'], $Source['Protocol']];
        }
        $Address = '';
        $Expert = [];
        foreach ($Entries as $Entry) {
            if ($Entry[2] != '') {
                $Address = $Entry[2];
                $Data->configuration->{\HMExtended\Device\Property::Protocol} = $Entry[4];
                break;
            }
        }
        foreach ($Entries as [$VariableId, $Default, $EntryAddress, $Datapoint]) {
            $IsStandard = ($Address != '') && ($EntryAddress == $this->GetBaseAddress($Address) . $Default[2]) && ($Datapoint == $Default[1]);
            if (!$IsStandard) {
                $Expert[] = ['VariableID' => $VariableId, 'Action' => $Default[0]];
            }
        }
        if ($Address != '') {
            $Data->configuration->{\HMExtended\Device\Property::Address} = $Address;
        }
        $Data->configuration->{self::PropertyExpertTriggers} = json_encode($Expert);
        $this->SendDebug('Migrate', json_encode($Data), 0);
        $this->LogMessage('Migrated settings:' . json_encode($Data->configuration), KL_MESSAGE);
        return json_encode($Data);
    }

    /**
     * Interne Funktion des SDK.
     * Die Schaltfläche zum Wiederherstellen des Display-Scriptes ist nur aktiv, solange das Script fehlt.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        foreach ($Form['actions'] as &$Action) {
            if (($Action['name'] ?? '') == self::ButtonRestoreScript) {
                $Action['enabled'] = ($this->FindIDForIdent(self::ScriptIdent) == 0);
            }
        }
        return json_encode($Form);
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @param int       $TimeStamp
     * @param int       $SenderID
     * @param int       $Message
     * @param array $Data
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message == VM_DELETE) {
            $this->UnregisterMessage($SenderID, VM_DELETE);
            $this->UnregisterReference($SenderID);
            $this->ApplyChanges();
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->Triggers = [];
        foreach ($this->ExpertVariables as $VariableId) {
            $this->UnregisterMessage($VariableId, VM_DELETE);
        }
        foreach ($this->GetReferenceList() as $ReferenceId) {
            $this->UnregisterReference($ReferenceId);
        }
        $this->ExpertVariables = [];
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->SetSummary('');
        // Die Konsole warnt beim Löschen des Display-Scriptes.
        $ScriptID = $this->ReadPropertyInteger(self::PropertyScriptID);
        if ($ScriptID > 0) {
            $this->RegisterReference($ScriptID);
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetNewConfig();
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        if (!is_array($Data) || !isset($Data['DeviceID'], $Data['VariableName'])) {
            return '';
        }
        $this->SendDebug('Receive', $Data['DeviceID'] . ' ' . $Data['VariableName'], 0);
        $Trigger = $this->Triggers[$Data['DeviceID'] . '|' . $Data['VariableName']] ?? false;
        if ($Trigger === false) {
            return '';
        }
        $this->RunDisplayScript($Trigger['Action'], $Trigger['Target']);
        return '';
    }

    /**
     *  Wird bei einem timeout ausgeführt und setzt die aktuelle Seite wieder auf Null.
     */
    public function ResetTimer(): void
    {
        $this->Page = 0;
        $this->SetTimerInterval(self::TimerTimeout, 0);
    }

    /**
     * Verarbeitet Bedienaktionen (aufgerufen von RequestAction der Basisklasse).
     *
     * @param string $Ident Ident der Aktion, `RestoreDisplayScript` legt das Display-Script neu an.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        if ($Ident == self::ActionRestoreScript) {
            $this->RestoreDisplayScript();
            $done = true;
            return;
        }
        parent::ProcessRequestAction($Ident, $Value, $done);
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
        if ($State == IS_ACTIVE) {
            $this->ApplyChanges();
        }
    }

    /**
     * Konvertiert die Daten in ein für das Display benötigtes Format.
     *
     * @param array $Data Enthält die Daten für das Display (je Zeile 'Text', 'Icon', 'Color').
     *
     * @return string Die konvertierten Daten als String.
     */
    protected function ConvertDisplayData(array $Data): string
    {
        $SendData = '0x02';
        foreach ($Data as $Line) {
            $Text = (string) ($Line['Text'] ?? '');
            if ($Text != '') {
                $SendData .= ',0x12';
                for ($i = 0; $i < strlen($Text); $i++) {
                    $SendData .= ',0x' . dechex(ord($Text[$i]));
                }
                $SendData .= ',0x11';
                $SendData .= ',0x' . dechex((int) ($Line['Color'] ?? 0));
            }
            if ((int) ($Line['Icon'] ?? 0) != 0) {
                $SendData .= ',0x13';
                $SendData .= ',0x' . dechex((int) $Line['Icon']);
            }
            $SendData .= ',0x0A';
        }
        $SendData .= ',0x03';
        return $SendData;
    }

    /**
     * Überträgt die Daten per HM-Script an das SUBMIT des Displays.
     * Lehnt die CCU die Übertragung ab (State false, das Display ist nur kurz empfangsbereit), wird einmal sofort erneut gesendet.
     *
     * @param string $Datapoint Der Datenpunkt SUBMIT mit Interface und Adresse, z.B. 'BidCos-RF.NEQ1234567:1.SUBMIT'.
     * @param string $Data      Die konvertierten Display-Daten.
     */
    private function SendSubmit(string $Datapoint, string $Data): void
    {
        $HMScript = 'string DisplayKeySubmit;' . PHP_EOL;
        $HMScript .= 'DisplayKeySubmit=dom.GetObject("' . $Datapoint . '").ID();' . PHP_EOL;
        $HMScript .= 'State=dom.GetObject(DisplayKeySubmit).State("' . $Data . '");' . PHP_EOL;
        for ($Attempt = 1; $Attempt <= self::SubmitAttempts; $Attempt++) {
            $Result = $this->LoadHMScript($HMScript);
            if ($Result === false) {
                return;
            }
            $Xml = $this->GetScriptXML($Result);
            if (($Xml === false) || ((string) $Xml->State != 'false')) {
                return;
            }
            $this->SendDebug('Submit rejected by CCU', 'Attempt ' . $Attempt . ' of ' . self::SubmitAttempts, 0);
        }
        $this->SendDebug('Submit failed', $Datapoint, 0);
    }
    //################# PRIVATE

    /**
     * Liefert die Geräte-Adresse ohne Kanal.
     */
    private function GetBaseAddress(string $Address): string
    {
        $Pos = strpos($Address, ':');
        return ($Pos === false) ? $Address : substr($Address, 0, $Pos);
    }

    /**
     * Maskiert Sonderzeichen für den Filter des Datenflusses. Doppelpunkt und Bindestrich bleiben unmaskiert.
     */
    private function QuoteFilter(string $Text): string
    {
        return str_replace(['\:', '\-'], [':', '-'], preg_quote($Text, '/'));
    }

    /**
     * Prüft die Konfiguration, baut die Auslöser auf und setzt den Filter für den Datenfluss.
     */
    private function SetNewConfig(): void
    {
        $Status = $this->CheckConfig();
        $this->SetStatus($Status);
        if ($Status != IS_ACTIVE) {
            $this->Page = 0;
            $this->Triggers = [];
            $this->SetReceiveDataFilter('.*9999999999.*');
            return;
        }
        $Triggers = $this->BuildTriggers();
        if ($Triggers === false) {
            $this->SetStatus(self::StatusInvalidTrigger);
            $this->Page = 0;
            $this->Triggers = [];
            $this->SetReceiveDataFilter('.*9999999999.*');
            return;
        }
        $Lines = [];
        foreach (array_keys($Triggers) as $Key) {
            [$DeviceId, $Datapoint] = explode('|', $Key);
            $Lines[] = '.*"DeviceID":"' . $this->QuoteFilter($DeviceId) . '","VariableName":"' . $this->QuoteFilter($Datapoint) . '".*';
        }
        $this->Triggers = $Triggers;
        $this->SetReceiveDataFilter('(' . implode('|', $Lines) . ')');
        $this->SetSummary($this->ReadPropertyString(\HMExtended\Device\Property::Address));
    }

    /**
     * Prüft die Konfiguration.
     *
     * @return int Status der Instanz.
     */
    private function CheckConfig(): int
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if (($Address == '') || str_starts_with($Address, self::Placeholder)) {
            return self::StatusNoAddress;
        }
        if (!str_contains($Address, ':')) {
            return self::StatusNoChannel;
        }
        if ($this->ReadPropertyInteger(self::PropertyScriptID) == 0) {
            return self::StatusNoScript;
        }
        if ($this->ReadPropertyInteger(self::PropertyTimeout) < 0) {
            return self::StatusTimeout;
        }
        if ($this->ReadPropertyInteger(self::PropertyMaxPage) < 1) {
            return self::StatusMaxPage;
        }
        return IS_ACTIVE;
    }

    /**
     * Liefert die Auslöser der Experteneinstellungen.
     *
     * @return array<int, array{VariableID: int, Action: int}>
     */
    private function GetExpertTriggers(): array
    {
        $List = json_decode($this->ReadPropertyString(self::PropertyExpertTriggers), true);
        if (!is_array($List)) {
            return [];
        }
        return array_values(array_filter($List, function ($Entry)
        {
            return is_array($Entry) && isset($Entry['VariableID'], $Entry['Action']) && ((int) $Entry['VariableID'] > 0);
        }));
    }

    /**
     * Baut die Tabelle der Auslöser auf.
     *
     * @return array|false Array 'Adresse|Datenpunkt' => ['Action', 'Target'], false bei ungültigem Auslöser.
     */
    private function BuildTriggers(): array|false
    {
        $Triggers = [];
        $Target = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Base = $this->GetBaseAddress($Target);
        $Standard = [
            [':2', self::PressShort, self::ActionPageUp],
            [':1', self::PressShort, self::ActionPageDown],
            [':2', self::PressLong, self::ActionUp],
            [':1', self::PressLong, self::ActionDown]
        ];
        foreach ($Standard as [$Channel, $Datapoint, $Action]) {
            $Triggers[$Base . $Channel . '|' . $Datapoint] = ['Action' => $Action, 'Target' => $Target];
        }
        $ExpertVariables = [];
        foreach ($this->GetExpertTriggers() as $Entry) {
            $VariableId = (int) $Entry['VariableID'];
            $Action = (int) $Entry['Action'];
            $Source = $this->GetTriggerSource($VariableId);
            if (($Source === false) || (!array_key_exists($Action, self::$ActionNames))) {
                return false;
            }
            $Triggers[$Source['Address'] . '|' . $Source['Datapoint']] = ['Action' => $Action, 'Target' => $Target];
            $ExpertVariables[] = $VariableId;
            $this->RegisterMessage($VariableId, VM_DELETE);
            $this->RegisterReference($VariableId);
        }
        $this->ExpertVariables = $ExpertVariables;
        return $Triggers;
    }

    /**
     * Ermittelt Adresse, Datenpunkt und Protokoll einer Variable einer Homematic-Geräteinstanz.
     *
     * @param int $VariableId IPS-VarID des Datenpunktes, welcher als Auslöser dient.
     *
     * @return array|false Array mit 'Address', 'Datapoint' und 'Protocol'. False im Fehlerfall.
     */
    private function GetTriggerSource(int $VariableId): false|array
    {
        if (!IPS_VariableExists($VariableId)) {
            return false;
        }
        $Parent = IPS_GetParent($VariableId);
        if (!IPS_InstanceExists($Parent) || (IPS_GetInstance($Parent)['ModuleInfo']['ModuleID'] != \HMExtended\GUID::HmDevice)) {
            return false;
        }
        return [
            'Address'   => IPS_GetProperty($Parent, \HMExtended\Device\Property::Address),
            'Datapoint' => IPS_GetObject($VariableId)['ObjectIdent'],
            'Protocol'  => IPS_GetProperty($Parent, \HMExtended\Device\Property::Protocol)
        ];
    }

    /**
     * Führt das User-Script aus und überträgt das Ergebnis an die CCU.
     *
     * @param int    $Action Die auszuführende Aktion (self::Action...).
     * @param string $Target Die konfigurierte Adresse (mit Kanal) des Displays, an die das Ergebnis gesendet wird.
     */
    private function RunDisplayScript(int $Action, string $Target): void
    {
        $Page = $this->Page;
        $MaxPage = $this->ReadPropertyInteger(self::PropertyMaxPage);
        switch ($Action) {
            case self::ActionPageUp: // Seite hoch = zurück blättern
                $Page = ($Page <= 1 ? $MaxPage : $Page - 1);
                $this->Page = $Page;
                break;
            case self::ActionPageDown: // Seite runter = weiter blättern
                $Page = ($Page >= $MaxPage ? 1 : $Page + 1);
                $this->Page = $Page;
                break;
            case self::ActionUp:
            case self::ActionDown:
                break;
            default:
                return;
        }
        $ActionString = self::$ActionNames[$Action];
        $this->SendDebug('Action', $ActionString, 0);
        $ScriptID = $this->ReadPropertyInteger(self::PropertyScriptID);
        if ($ScriptID != 0) {
            // Dem Script wird nie Seite 0 übergeben (0 = Ruhezustand, das Display zeigt dann Seite 1).
            $Result = IPS_RunScriptWaitEx($ScriptID, ['SENDER' => 'HMDisWM55', 'ACTION' => $ActionString, 'PAGE' => max(1, $Page), 'EVENT' => $this->InstanceID]);
            $ResultData = json_decode($Result, true);
            if (!is_array($ResultData)) {
                trigger_error($this->Translate('Error in display-script.'), E_USER_NOTICE);
                return;
            }
            $this->SendDebug('DisplayScript', $ResultData, 0);
            $Data = $this->ConvertDisplayData($ResultData);
            $Interface = \HMExtended\CCU::$Interfaces[$this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol)];
            $this->SendSubmit($Interface . '.' . $Target . '.SUBMIT', $Data);
        }
        $Timeout = $this->ReadPropertyInteger(self::PropertyTimeout);
        if ($Timeout > 0) {
            $this->SetTimerInterval(self::TimerTimeout, 0);
            $this->SetTimerInterval(self::TimerTimeout, $Timeout * 1000);
        }
    }

    /**
     * Legt das Display-Script mit der Vorlage neu an, wenn es gelöscht wurde, und trägt es in die Instanz ein.
     *
     * @return bool true, wenn das Script angelegt wurde.
     */
    private function RestoreDisplayScript(): bool
    {
        if ($this->FindIDForIdent(self::ScriptIdent) != 0) {
            trigger_error($this->Translate('Display script already exists.'), E_USER_NOTICE);
            return false;
        }
        $this->RegisterScript(self::ScriptIdent, 'Display Script', $this->CreateDisplayScript(), -1);
        $ID = $this->FindIDForIdent(self::ScriptIdent);
        IPS_SetHidden($ID, true);
        IPS_SetProperty($this->InstanceID, self::PropertyScriptID, $ID);
        IPS_ApplyChanges($this->InstanceID);
        $this->UpdateFormField(self::ButtonRestoreScript, 'enabled', false);
        $this->UpdateFormField(self::PropertyScriptID, 'value', $ID);
        return true;
    }

    /**
     * Liefert das Script welches im Objektbaum als Vorlage für das DisplayScript angelegt wird.
     *
     * @return string
     */
    private function CreateDisplayScript(): string
    {
        return file_get_contents(__DIR__ . '/Display-Taster-Script-Vorlage.php');
    }
}

/* @} */
