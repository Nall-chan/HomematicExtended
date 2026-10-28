<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMSystemVariableBase.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.80
 */
require_once __DIR__ . '/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/HMSystemVariables.php';  // Scripte und Parser für Systemvariablen

/**
 * Basisklasse der Module Systemvariablen und Alarmvariable.
 *
 * Änderungen auf der CCU meldet ein CCU-Programm: es drückt bei jeder Aktualisierung der Variablen eine Taste der virtuellen
 * Fernbedienung (`BidCoS-RF:n` bzw. `HmIP-RCV-1:n`). Das Event `PRESS_SHORT` kommt über den Homematic Socket, danach wird mit
 * einem einzigen HM-Script gelesen. Mehrere Instanzen können sich eine Taste teilen, jede liest nur ihre eigenen Variablen.
 * Zusätzlich kann zyklisch gelesen werden (Eigenschaft Interval).
 *
 * @property string $LastTimestamp Uhrzeit der CCU beim letzten Lesen (Zeitstempel-Filter für das nächste Lesen).
 */
abstract class HMSystemVariableBase extends HMBase
{
    /**
     * Verzögerung in Millisekunden zwischen Event und Lesen. Mehrere Events kurz hintereinander lösen nur ein Lesen aus.
     */
    protected const ReadDelay = 250;
    /**
     * Mindestwert des Intervalls in Sekunden.
     */
    protected const MinInterval = 5;
    /**
     * Anfang des Namens des CCU-Programms, es folgt die Instanz-ID.
     */
    protected const ProgramPrefix = 'Symcon ';
    /**
     * Platzhalter der Adresse, solange keine Taste gewählt ist.
     */
    protected const AddressPlaceholder = 'XXX9999999';
    /**
     * Name der Aktion (RequestAction) des Timers.
     */
    protected const ActionRead = \HMExtended\Device\Timer::ReadSystemVariables;
    /**
     * Name der Aktion (RequestAction), welche das Formular neu lädt (nach den Schaltflächen des CCU-Programms).
     */
    protected const ActionReloadForm = 'ReloadForm';
    /**
     * Statuscode: Intervall kleiner als MinInterval.
     */
    protected const StatusIntervalTooSmall = IS_EBASE + 3;

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        // Address und Protocol müssen vorhanden sein, sonst hängt sich der Symcon-Homematic-Konfigurator auf.
        $this->RegisterHMPropertys(static::AddressPlaceholder);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Interval, 0);
        $this->RegisterAttributeInteger(\HMExtended\Device\Attribute::ProgramID, 0);
        $this->RegisterAttributeString(\HMExtended\Device\Attribute::ProgramKey, '');
        $this->RegisterTimer(static::ActionRead, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'' . static::ActionRead . '\', true);');
        $this->LastTimestamp = '';
    }

    /**
     * Interne Funktion des SDK.
     * Ohne Parent wird nicht gelesen und nichts gemeldet. Mit Parent wird sofort gelesen, Fehler werden gemeldet.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->LastTimestamp = '';
        $this->SetTimerInterval(static::ActionRead, 0);
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($this->HasKey()) {
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $Address . '".*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        $this->SetSummary($this->HasKey() ? $Address : '');
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->CheckConfig()) {
            return;
        }
        if (!$this->HasParent()) {
            return;
        }
        if ($this->UpdateVariables()) {
            $this->ReadValues(true);
        }
        $this->StartIntervalTimer();
    }

    /**
     * Interne Funktion des SDK.
     * Das Event der Taste startet den Timer zum Lesen.
     */
    public function ReceiveData(string $JSONString): string
    {
        $Event = json_decode($JSONString, true);
        if (!is_array($Event) || (($Event['VariableName'] ?? '') !== \HMExtended\SystemVariables::KeyDatapoint)) {
            return '';
        }
        $this->SendDebug('EVENT', $Event['DeviceID'] . '.' . $Event['VariableName'], 0);
        $this->SetTimerInterval(static::ActionRead, static::ReadDelay);
        return '';
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_CreateCCUProgram'.
     * Legt das CCU-Programm an, welches bei jeder Aktualisierung der Variablen die gewählte Taste drückt. Ein vorhandenes Programm
     * dieser Instanz wird ersetzt. Programme des Nutzers werden nicht verändert.
     * Ist die Taste Bedingung eines Programms der CCU oder direkt verknüpft, wird nichts angelegt: jeder Tastendruck würde diese mit auslösen.
     * Trägt die Taste noch den Werksnamen, erhält sie den Namen des Programms.
     *
     * @return string|false Meldung für den Nutzer, bei einem Fehler false (die Meldung erhält der Aufrufer).
     */
    public function CreateCCUProgram(): string|false
    {
        if (!$this->HasKey()) {
            echo $this->Translate('No virtual key selected.') . PHP_EOL;
            return false;
        }
        $Variables = $this->ProgramVariables();
        if (count($Variables) == 0) {
            echo $this->Translate('No variables selected.') . PHP_EOL;
            return false;
        }
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Keys = $this->LoadKeys(false);
        if ($Keys === false) {
            return false;
        }
        if (!isset($Keys[$Address])) {
            echo $this->Translate('The virtual key was not found on the CCU.') . PHP_EOL;
            return false;
        }
        if (count($Keys[$Address]['Conditions']) || $Keys[$Address]['Links']) {
            $Usage = [];
            if (count($Keys[$Address]['Conditions'])) {
                $Usage[] = sprintf($this->Translate('condition in: %s'), implode(', ', $Keys[$Address]['Conditions']));
            }
            if ($Keys[$Address]['Links']) {
                $Usage[] = sprintf($this->Translate('direct links: %d'), $Keys[$Address]['Links']);
            }
            echo sprintf($this->Translate('The virtual key %s is in use (%s). Every key press would trigger it as well. Please choose another key.'), $Address, implode('; ', $Usage)) . PHP_EOL;
            return false;
        }
        $Script = \HMExtended\SystemVariables::ProgramScript(
            $this->ProgramName(),
            sprintf($this->Translate('Created by Symcon instance %d (HomematicExtended). Changes will be overwritten.'), $this->InstanceID),
            $this->ReadAttributeInteger(\HMExtended\Device\Attribute::ProgramID),
            $this->ReadAttributeString(\HMExtended\Device\Attribute::ProgramKey),
            $Address,
            $Variables
        );
        $Output = $this->RunScript($Script);
        if ($Output === false) {
            return false;
        }
        $ProgramId = (int) \HMExtended\SystemVariables::ParseHeader($Output);
        if ($ProgramId == 0) {
            echo $this->Translate('The virtual key was not found on the CCU.') . PHP_EOL;
            return false;
        }
        $this->WriteAttributeInteger(\HMExtended\Device\Attribute::ProgramID, $ProgramId);
        $this->WriteAttributeString(\HMExtended\Device\Attribute::ProgramKey, $Address);
        $this->SendDebug('Program', $ProgramId, 0);
        return sprintf($this->Translate('CCU program "%s" created.'), $this->ProgramName());
    }

    /**
     * IPS-Instanz-Funktion 'HM_DeleteCCUProgram'.
     * Löscht das CCU-Programm dieser Instanz (nur wenn ID und Name passen). Die Taste erhält ihren Werksnamen zurück, sofern sie noch
     * den vom Modul vergebenen Namen trägt.
     *
     * @return string|false Meldung für den Nutzer, bei einem Fehler false.
     */
    public function DeleteCCUProgram(): string|false
    {
        $Output = $this->RunScript(\HMExtended\SystemVariables::DeleteProgramScript(
            $this->ReadAttributeInteger(\HMExtended\Device\Attribute::ProgramID),
            $this->ProgramName(),
            true,
            $this->ReadAttributeString(\HMExtended\Device\Attribute::ProgramKey)
        ));
        if ($Output === false) {
            return false;
        }
        $this->WriteAttributeInteger(\HMExtended\Device\Attribute::ProgramID, 0);
        $this->WriteAttributeString(\HMExtended\Device\Attribute::ProgramKey, '');
        if (\HMExtended\SystemVariables::ParseHeader($Output) === '1') {
            return sprintf($this->Translate('CCU program "%s" deleted.'), $this->ProgramName());
        }
        return $this->Translate('No CCU program of this instance found.');
    }

    //################# protected

    /**
     * Prüft die Variablen auf der CCU und legt die Statusvariablen an.
     *
     * @return bool true, wenn danach gelesen werden kann.
     */
    abstract protected function UpdateVariables(): bool;

    /**
     * Liest die Werte von der CCU.
     *
     * @param bool $Full true: alle Werte, false: nur seit dem letzten Lesen geänderte.
     *
     * @return bool true bei Erfolg.
     */
    abstract protected function ReadValues(bool $Full): bool;

    /**
     * Liefert die Variablen, auf welche das CCU-Programm reagiert.
     *
     * @return int[] CCU-ID => ValueType.
     */
    abstract protected function ProgramVariables(): array;

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert oder die Instanz verbunden bzw. getrennt wird.
     */
    protected function IOChangeState(int $State): void
    {
        $this->ApplyChanges();
    }

    /**
     * Verarbeitet den Timer zum Lesen.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        switch ($Ident) {
            case static::ActionRead:
                $done = true;
                $this->StartIntervalTimer();
                $this->ReadValues(false);
                return;
            case self::ActionReloadForm:
                $done = true;
                $this->ReloadForm();
                return;
        }
    }

    /**
     * Prüft, ob eine Taste einer virtuellen Fernbedienung als Adresse eingetragen ist.
     */
    protected function HasKey(): bool
    {
        return \HMExtended\SystemVariables::ProtocolOfKey($this->ReadPropertyString(\HMExtended\Device\Property::Address)) !== false;
    }

    /**
     * Prüft, ob die Instanz mit einem Parent (Homematic Socket) verbunden ist.
     *
     * @return bool true, wenn ein Parent eingetragen ist (unabhängig von dessen Status).
     */
    protected function HasParent(): bool
    {
        return IPS_GetInstance($this->InstanceID)['ConnectionID'] > 0;
    }

    /**
     * Name des CCU-Programms dieser Instanz.
     */
    protected function ProgramName(): string
    {
        return static::ProgramPrefix . $this->InstanceID;
    }

    /**
     * Sendet ein HM-Script und liefert dessen Ausgabe (ohne das XML der Variablen). Fehler werden per echo gemeldet.
     *
     * @return string|false Die Ausgabe, bei einem Fehler false.
     */
    protected function RunScript(string $Script): string|false
    {
        $Result = $this->LoadHMScript($Script);
        if ($Result === false) {
            return false;
        }
        [$Output, $Xml] = \HMExtended\ScriptParser::SplitOutput($Result);
        // Die CCU liefert immer das XML der Variablen, ohne XML ist die Antwort unvollständig.
        if ($Xml === '') {
            echo $this->Translate('Invalid response from CCU.') . PHP_EOL;
            return false;
        }
        return $Output;
    }

    /**
     * Sendet ein HM-Script für eine optionale Abfrage (z.B. für das Formular). Meldungen erscheinen nur im Debug.
     *
     * @return string|false Die Ausgabe, bei einem Fehler false.
     */
    protected function RunScriptQuiet(string $Script): string|false
    {
        ob_start();
        $Output = $this->RunScript($Script);
        $Errors = trim((string) ob_get_clean());
        if ($Errors !== '') {
            $this->SendDebug('Error', $Errors, 0);
        }
        return $Output;
    }

    /**
     * Ergänzt das Formular: Auswahl der Taste (Element `Address`) und Zustand des CCU-Programms (Element `ProgramState`).
     *
     * @param array $Form Das Formular.
     *
     * @return array Das ergänzte Formular.
     */
    protected function AddKeyAndProgramToForm(array $Form): array
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Options = [];
        if (!$this->HasKey()) {
            $Options[] = ['caption' => $this->Translate('- not selected -'), 'value' => $Address];
        }
        $Keys = $this->HasActiveParent() ? $this->LoadKeys(true) : false;
        if ($Keys === false) {
            $Keys = [];
        }
        if ($this->HasKey() && !array_key_exists($Address, $Keys)) {
            $Keys[$Address] = null;
        }
        foreach ($Keys as $KeyAddress => $Key) {
            $Options[] = ['caption' => $this->KeyCaption($KeyAddress, $Key), 'value' => $KeyAddress];
        }
        $Form['elements'] = $this->SetFormField($Form['elements'], 'Address', 'options', $Options);
        $Form['actions'] = $this->SetFormField($Form['actions'], 'ProgramState', 'caption', $this->ProgramStateCaption());
        return $Form;
    }

    /**
     * Liest die Tasten der virtuellen Fernbedienungen (aktivierte Interfaces des Sockets) mit ihrer Verwendung:
     * Programme der CCU (Bedingung, Aktivität), Direktverknüpfungen und Instanzen dieser Library.
     * Die Direktverknüpfungen sind eine optionale Abfrage (Fehler nur im Debug).
     *
     * @param bool $Quiet true: Fehler nur im Debug (Formular), false: Fehler an den Aufrufer.
     *
     * @return array|false Adresse => Eigenschaften (siehe SystemVariables::ParseKeyList, zusätzlich `Instances`), bei einem Fehler false.
     */
    protected function LoadKeys(bool $Quiet): array|false
    {
        $ParentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $Protocols = [];
        if ($ParentId > 0) {
            if (@IPS_GetProperty($ParentId, 'RFOpen')) {
                $Protocols[] = \HMExtended\CCU::ProtocolBidCosRF;
            }
            if (@IPS_GetProperty($ParentId, 'IPOpen')) {
                $Protocols[] = \HMExtended\CCU::ProtocolHmIP;
            }
        }
        $Script = \HMExtended\SystemVariables::KeyListScript($Protocols);
        $Output = $Quiet ? $this->RunScriptQuiet($Script) : $this->RunScript($Script);
        if ($Output === false) {
            return false;
        }
        $Keys = \HMExtended\SystemVariables::ParseKeyList($Output);
        foreach ($Protocols as $Protocol) {
            ob_start();
            $Links = $this->SendRPC('getLinks', $Protocol, ['', 0]);
            $Errors = trim((string) ob_get_clean());
            if (!is_array($Links)) {
                $this->SendDebug('getLinks', 'not available ' . $Errors, 0);
                continue;
            }
            foreach (\HMExtended\SystemVariables::CountKeyLinks($Links) as $KeyAddress => $Count) {
                if (isset($Keys[$KeyAddress])) {
                    $Keys[$KeyAddress]['Links'] = $Count;
                }
            }
        }
        foreach ($Keys as &$Key) {
            $Key['Instances'] = [];
        }
        unset($Key);
        foreach ([\HMExtended\GUID::Systemvariablen, \HMExtended\GUID::Alarmvariable] as $ModuleId) {
            foreach (IPS_GetInstanceListByModuleID($ModuleId) as $InstanceId) {
                $KeyAddress = (string) @IPS_GetProperty($InstanceId, \HMExtended\Device\Property::Address);
                if (isset($Keys[$KeyAddress]) && ($InstanceId != $this->InstanceID)) {
                    $Keys[$KeyAddress]['Instances'][] = $InstanceId;
                }
            }
        }
        return $Keys;
    }

    /**
     * Beschriftung einer Taste in der Auswahl: Adresse, Name (nur wenn geändert) und Verwendung bzw. `frei`.
     *
     * @param string $Address Adresse der Taste.
     * @param array|null $Key Eigenschaften aus LoadKeys(), null wenn unbekannt.
     */
    protected function KeyCaption(string $Address, ?array $Key): string
    {
        if ($Key === null) {
            return $Address;
        }
        $Caption = $Address . ($Key['DefaultName'] ? '' : ' - ' . $Key['Name']);
        $Usage = [];
        if (count($Key['Conditions'])) {
            $Usage[] = sprintf($this->Translate('condition in: %s'), implode(', ', $Key['Conditions']));
        }
        if (count($Key['Activities'])) {
            $Usage[] = sprintf($this->Translate('pressed by: %s'), implode(', ', $Key['Activities']));
        }
        if ($Key['Links']) {
            $Usage[] = sprintf($this->Translate('direct links: %d'), $Key['Links']);
        }
        if (count($Key['Instances'])) {
            $Usage[] = sprintf($this->Translate('Symcon instances: %s'), implode(', ', $Key['Instances']));
        }
        if ((count($Usage) == 0) && $Key['DefaultName']) {
            return $Caption . ' (' . $this->Translate('free') . ')';
        }
        return $Caption . (count($Usage) ? ' (' . implode('; ', $Usage) . ')' : '');
    }

    /**
     * Liefert den Zustand des CCU-Programms als Text für das Formular.
     */
    protected function ProgramStateCaption(): string
    {
        $ProgramId = $this->ReadAttributeInteger(\HMExtended\Device\Attribute::ProgramID);
        if ($ProgramId == 0) {
            return $this->Translate('CCU program: not created');
        }
        if (!$this->HasActiveParent()) {
            return sprintf($this->Translate('CCU program: %s (state unknown)'), $this->ProgramName());
        }
        $Output = $this->RunScriptQuiet(\HMExtended\SystemVariables::ProgramStateScript($ProgramId));
        if ($Output === false) {
            return sprintf($this->Translate('CCU program: %s (state unknown)'), $this->ProgramName());
        }
        $State = \HMExtended\SystemVariables::ParseProgramState($Output);
        if (($State === false) || ($State['Name'] !== $this->ProgramName())) {
            return $this->Translate('CCU program: missing on the CCU, please create it again');
        }
        $Expected = array_keys($this->ProgramVariables());
        sort($Expected);
        if ($State['Variables'] != $Expected) {
            return sprintf($this->Translate('CCU program: %s does not match the selected variables, please update it'), $this->ProgramName());
        }
        if (!$State['Active']) {
            return sprintf($this->Translate('CCU program: %s is inactive'), $this->ProgramName());
        }
        return sprintf($this->Translate('CCU program: %s is up to date'), $this->ProgramName());
    }

    /**
     * Setzt ein Feld eines Formular-Elements (auch in Unterelementen).
     *
     * @param array $Items Die Elemente.
     * @param string $Name Name des Elements.
     * @param string $Field Das Feld, z.B. `caption`.
     * @param mixed $Value Der Wert.
     *
     * @return array Die geänderten Elemente.
     */
    protected function SetFormField(array $Items, string $Name, string $Field, mixed $Value): array
    {
        foreach ($Items as &$Item) {
            if (!is_array($Item)) {
                continue;
            }
            if (($Item['name'] ?? '') === $Name) {
                $Item[$Field] = $Value;
            }
            if (isset($Item['items']) && is_array($Item['items'])) {
                $Item['items'] = $this->SetFormField($Item['items'], $Name, $Field, $Value);
            }
        }
        return $Items;
    }

    /**
     * Startet den Timer mit dem Intervall der Eigenschaft (0 = aus).
     */
    protected function StartIntervalTimer(): void
    {
        $Interval = $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval);
        $this->SetTimerInterval(static::ActionRead, ($Interval >= static::MinInterval) ? $Interval * 1000 : 0);
    }

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     * Ohne Taste und ohne Intervall ist die Instanz inaktiv (keine automatische Aktualisierung), gelesen wird trotzdem.
     *
     * @return bool true, wenn gelesen werden kann.
     */
    private function CheckConfig(): bool
    {
        $Interval = $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval);
        if (($Interval > 0) && ($Interval < static::MinInterval)) {
            $this->SetStatus(static::StatusIntervalTooSmall);
            return false;
        }
        $this->SetStatus((!$this->HasKey() && ($Interval == 0)) ? IS_INACTIVE : IS_ACTIVE);
        return true;
    }
}

/* @} */
