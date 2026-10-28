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
 * HomeMaticAlarmvariable ist die Klasse für das IPS-Modul 'HomeMatic Alarmvariable'.
 * Bildet eine Alarmvariable der CCU mit Zustand, erstem und letztem Alarm und dem Auslöser ab. Der Alarm kann quittiert werden.
 * Erweitert HMSystemVariableBase.
 */
class HomeMaticAlarmvariable extends HMSystemVariableBase
{
    protected const ProgramPrefix = 'Symcon Alarmvariable ';

    /**
     * Statuscode: keine Alarmvariable gewählt.
     */
    private const StatusNoAlarm = IS_EBASE + 4;

    /**
     * Idents der Statusvariablen.
     */
    private const State = 'STATE';
    private const FirstTime = 'FIRST_TIME';
    private const LastTime = 'LAST_TIME';
    private const TriggerChannel = 'TRIGGER_CHANNEL';
    private const TriggerRoom = 'TRIGGER_ROOM';
    private const TriggerDatapoint = 'TRIGGER_DATAPOINT';

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::AlarmID, 0);
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
        $Form = $this->AddKeyAndProgramToForm($Form);
        $AlarmId = $this->ReadPropertyInteger(\HMExtended\Device\Property::AlarmID);
        $Options = [];
        if ($this->HasActiveParent()) {
            $Output = $this->RunScriptQuiet(\HMExtended\SystemVariables::ListScript());
            if ($Output !== false) {
                foreach (\HMExtended\SystemVariables::ParseList($Output) as $Id => $Variable) {
                    if (\HMExtended\SystemVariables::IsAlarm($Variable)) {
                        $Options[$Id] = ['caption' => $Variable['Name'], 'value' => $Id];
                    }
                }
            }
        }
        if (!isset($Options[$AlarmId])) {
            $Options[$AlarmId] = ['caption' => ($AlarmId == 0) ? $this->Translate('- not selected -') : (string) $AlarmId, 'value' => $AlarmId];
        }
        uasort($Options, function (array $A, array $B)
        {
            return mb_strtolower($A['caption']) <=> mb_strtolower($B['caption']);
        });
        $Form['elements'] = $this->SetFormField($Form['elements'], \HMExtended\Device\Property::AlarmID, 'options', array_values($Options));
        return json_encode($Form);
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadAlarm'.
     * Liest Zustand und Auslöser der Alarmvariable von der CCU.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function ReadAlarm(): bool
    {
        if (!$this->UpdateVariables()) {
            return false;
        }
        return $this->ReadValues(true);
    }

    /**
     * IPS-Instanz-Funktion 'HM_AlarmReceipt'.
     * Quittiert den Alarm auf der CCU.
     *
     * @return bool True wenn quittiert wurde, false wenn kein Alarm anstand oder bei einem Fehler.
     */
    public function AlarmReceipt(): bool
    {
        $AlarmId = $this->ReadPropertyInteger(\HMExtended\Device\Property::AlarmID);
        if ($AlarmId == 0) {
            echo $this->Translate('No alarm variable selected.') . PHP_EOL;
            return false;
        }
        $Output = $this->RunScript(\HMExtended\SystemVariables::AlarmReceiptScript($AlarmId));
        if ($Output === false) {
            return false;
        }
        switch (trim($Output)) {
            case 'true':
                if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus)) {
                    $this->SetValue(self::State, false);
                }
                return true;
            case '-':
                echo sprintf($this->Translate('Alarm variable %d does not exist on the CCU.'), $AlarmId) . PHP_EOL;
                return false;
        }
        return false;
    }

    //################# protected

    /**
     * Verarbeitet die Bedienaktion der Statusvariable STATE (nur Quittieren).
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        $done = true;
        if ($Ident != self::State) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return;
        }
        if ((bool) $Value) {
            echo $this->Translate('An alarm can only be acknowledged.') . PHP_EOL;
            return;
        }
        $this->AlarmReceipt();
    }

    /**
     * Legt die Statusvariablen an.
     *
     * @return bool true, wenn eine Alarmvariable gewählt ist.
     */
    protected function UpdateVariables(): bool
    {
        if ($this->ReadPropertyInteger(\HMExtended\Device\Property::AlarmID) == 0) {
            $this->SetStatus(self::StatusNoAlarm);
            return false;
        }
        $this->MaintainVariable(self::State, $this->Translate('State'), VARIABLETYPE_BOOLEAN, [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode([
                ['Value' => false, 'Caption' => $this->Translate('No alarm'), 'IconValue' => '', 'IconActive' => false, 'Color' => -1],
                ['Value' => true, 'Caption' => $this->Translate('Alarm'), 'IconValue' => '', 'IconActive' => false, 'Color' => 0xFF0000]
            ])
        ], 1, true);
        $this->EnableAction(self::State);
        // Datum mit Jahr, Uhrzeit mit Sekunden.
        $DateTime = ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME, 'DATE' => 1, 'TIME' => 2];
        $this->MaintainVariable(self::FirstTime, $this->Translate('First alarm'), VARIABLETYPE_INTEGER, $DateTime, 2, true);
        $this->MaintainVariable(self::LastTime, $this->Translate('Last alarm'), VARIABLETYPE_INTEGER, $DateTime, 3, true);
        return true;
    }

    /**
     * Liest Zustand und Auslöser der Alarmvariable mit einem HM-Script.
     *
     * @param bool $Full false: die Statusvariablen werden nur geschrieben, wenn sich der Zeitstempel der Alarmvariable geändert hat
     *                   (andere Variablen auf derselben Taste lösen ebenfalls ein Lesen aus).
     *
     * @return bool true bei Erfolg.
     */
    protected function ReadValues(bool $Full): bool
    {
        $AlarmId = $this->ReadPropertyInteger(\HMExtended\Device\Property::AlarmID);
        if ($AlarmId == 0) {
            return true;
        }
        $Output = $this->RunScript(\HMExtended\SystemVariables::AlarmScript($AlarmId));
        if ($Output === false) {
            return false;
        }
        $Alarm = \HMExtended\SystemVariables::ParseAlarm($Output);
        if ($Alarm === false) {
            echo sprintf($this->Translate('Alarm variable %d does not exist on the CCU.'), $AlarmId) . PHP_EOL;
            return false;
        }
        if (!$Full && ($Alarm['Timestamp'] === $this->LastTimestamp)) {
            $this->SendDebug('Alarm', 'unchanged', 0);
            return true;
        }
        $this->LastTimestamp = $Alarm['Timestamp'];
        $this->SendDebug('Alarm', $Alarm, 0);
        $TimeZone = date_default_timezone_get();
        $this->SetValue(self::State, $Alarm['Value']);
        $this->SetValue(self::FirstTime, \HMExtended\SystemVariables::ToUnixTime($Alarm['FirstTime'], $TimeZone));
        $this->SetValue(self::LastTime, \HMExtended\SystemVariables::ToUnixTime($Alarm['LastTime'], $TimeZone));
        // Die CCU liefert den letzten Auslöser auch nach dem Ende des Alarms. Die Variablen des Auslösers zeigen nur einen anstehenden Alarm,
        // erster und letzter Alarm bleiben als Verlauf erhalten.
        if (!$Alarm['Value']) {
            $Alarm['Channel'] = $Alarm['Room'] = $Alarm['Datapoint'] = '';
        }
        if (($Alarm['Channel'] !== '') || ($Alarm['Datapoint'] !== '')) {
            $this->CreateTriggerVariables();
        }
        // Einmal angelegte Variablen des Auslösers werden weiter aktualisiert.
        $this->SetVariable(self::TriggerChannel, $Alarm['Channel']);
        $this->SetVariable(self::TriggerRoom, $Alarm['Room']);
        $this->SetVariable(self::TriggerDatapoint, $Alarm['Datapoint']);
        return true;
    }

    /**
     * Liefert die Alarmvariable für das CCU-Programm.
     *
     * @return int[] CCU-ID => ValueType.
     */
    protected function ProgramVariables(): array
    {
        $AlarmId = $this->ReadPropertyInteger(\HMExtended\Device\Property::AlarmID);
        return ($AlarmId == 0) ? [] : [$AlarmId => \HMExtended\SystemVariables::ValueTypeBool];
    }

    /**
     * Legt die Statusvariablen des Auslösers an. Sie entstehen erst, wenn die CCU einen Auslöser liefert: interne Alarmvariablen
     * (z.B. die WatchDog-Alarme von RaspberryMatic) haben weder Kanal noch Raum.
     */
    private function CreateTriggerVariables(): void
    {
        $Presentation = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION];
        $this->MaintainVariable(self::TriggerChannel, $this->Translate('Triggering channel'), VARIABLETYPE_STRING, $Presentation, 4, true);
        $this->MaintainVariable(self::TriggerRoom, $this->Translate('Room of the triggering channel'), VARIABLETYPE_STRING, $Presentation, 5, true);
        $this->MaintainVariable(self::TriggerDatapoint, $this->Translate('Triggering datapoint'), VARIABLETYPE_STRING, $Presentation, 6, true);
    }
}

/* @} */
