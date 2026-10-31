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
require_once __DIR__ . '/HMBase.php';  // HMBase Klasse

/**
 * HMHeatingDevice
 *
 * @property array $WeekSchedules
 * @property int $WeekProfile
 */
class HMHeatingDevice extends HMBase
{
    protected const ValuesChannel = '';
    protected const ParamChannel = '';
    protected const DeviceTyp = '';

    protected const WeekScheduleIndexTemp = 'P%3$d_TEMPERATURE_%2$s_%1$d';
    protected const WeekScheduleIndexEndTime = 'P%3$d_ENDTIME_%2$s_%1$d';
    protected const NumberOfWeekSchedules = 1;
    protected const NumberOfTimeSlot = 13;
    /** Raster der Endzeiten im Wochenplan in Minuten und kleinste Temperatur (CCU-Firmware: HM-CC-RT-DN, HmIP 5 min und 5,0 °C). */
    protected const ScheduleTimeStep = 5;
    protected const MinScheduleTemperature = 5.0;
    protected const MaxScheduleTemperature = 30.0;
    protected const SelectedWeekScheduleIdent = '';

    protected const EVENT = 'WEEK_PROFIL';
    protected const EnablePrefix = 'enable_';
    protected const EnablePartyProperty = 'enable_PARTY';
    protected const TIME = 'Time';
    protected const TEMP = 'Temp';

    protected static $Weekdays = [
        1  => 'MONDAY',
        2  => 'TUESDAY',
        4  => 'WEDNESDAY',
        8  => 'THURSDAY',
        16 => 'FRIDAY',
        32 => 'SATURDAY',
        64 => 'SUNDAY',
    ];

    protected static $VariableTypes = [
        'BOOL'    => VARIABLETYPE_BOOLEAN,
        'INTEGER' => VARIABLETYPE_INTEGER,
        'ENUM'    => VARIABLETYPE_INTEGER,
        'FLOAT'   => VARIABLETYPE_FLOAT,
        'STRING'  => VARIABLETYPE_STRING,
        'ACTION'  => \HMExtended\Variables::VARIABLETYPE_NONE,
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);

        foreach (\HMExtended\Property::$Properties[static::DeviceTyp] as $Property => $Value) {
            $this->RegisterPropertyBoolean(self::EnablePrefix . $Property, $Value);
        }
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::Schedule, true);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::SetPointBehavior, true);
        $InitTemps = [static::MinScheduleTemperature, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 30];
        foreach ($InitTemps as &$Temp) {
            $Temp = ['TemperatureValue'=> $Temp];
        }
        $this->RegisterPropertyString(\HMExtended\Device\Property::ScheduleTemps, json_encode($InitTemps));
        /*
        $ScheduleTempsInit = [
            [5, 0x000080],
            [16, 0x333399],
            [17, 0x005EC6],
            [18, 0x008080],
            [19, 0x339966],
            [20, 0x58B306],
            [21, 0xFFCC00],
            [22, 0xFF9900],
            [23, 0xFB7720],
            [24, 0xD20000],
            [25, 0xE60000],
            [30, 0xFF0000],
        ];

        $ScheduleTempColors = [
            [5, 0x000080],
            [15, 0x222289],
            [16, 0x333399],
            [17, 0x005EC6],
            [18, 0x008080],
            [19, 0x339966],
            [20, 0x58B306],
            [21, 0xFFCC00],
            [22, 0xFF9900],
            [23, 0xFB7720],
            [24, 0xD20000],
            [25, 0xE60000],
            [30, 0xFF0000],
        ];
        $this->RegisterAttributeArray('ScheduleColors', $ScheduleTempsInit);
         */
        $this->WeekSchedules = [];
        $this->WeekProfile = 1;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy(): void
    {
        // Die Profile bis Version 3.81 wurden von allen Heizungsmodulen gemeinsam genutzt und werden erst mit der letzten Instanz gelöscht.
        if ((!IPS_InstanceExists($this->InstanceID)) && (!$this->ExistInstancesOf(\HMExtended\DeviceType::$HeatingGuids))) {
            $this->UnregisterLegacyProfiles();
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

        $this->WeekProfile = 1;
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $this->SetReceiveDataFilter($Address == '' ? '.*9999999999.*' : '.*"DeviceID":"' . $Address . static::ValuesChannel . '.*');

        foreach (\HMExtended\ValuesSet::$Variables[static::DeviceTyp] as $Ident => $VarData) {
            $Property = isset($VarData[4]) ? $VarData[4] : $Ident;
            if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                if (!$this->ReadPropertyBoolean(self::EnablePrefix . $Property)) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        foreach (\HMExtended\ParamSet::$Variables[static::DeviceTyp] as $Ident => $ParaData) {
            $Property = isset($ParaData[4]) ? $ParaData[4] : $Ident;
            if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                if (!$this->ReadPropertyBoolean(self::EnablePrefix . $Property)) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::Schedule)) {
            $this->CreateWeekPlan();
            $PresentationSubmitPlan = false;
            switch (static::SelectedWeekScheduleIdent) { //Nur Speichern Button
                case \HMExtended\HeatingGroupHmIP::ACTIVE_PROFILE:
                    $PresentationSubmitPlan = \HMExtended\HeatingPresentation::ProfileHmIP;
                    break;
                case \HMExtended\HeatingGroup::WEEK_PROGRAM_POINTER:
                    $PresentationSubmitPlan = \HMExtended\HeatingPresentation::ProfileHM;
                    break;
            }
            if ($PresentationSubmitPlan) {
                $isOld = $this->FindIDForIdent(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM);
                $this->MaintainHeatingVariable(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM, $this->Translate('Schedule save in profile'), VARIABLETYPE_INTEGER, $PresentationSubmitPlan, true, []);
                $this->EnableAction(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM);
                if (!$isOld) {
                    $this->SetValue(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM, 1);
                }
            }
            $this->MaintainHeatingVariable(\HMExtended\Variables::SUBMIT_WEEK_PROGRAM, $this->Translate('Schedule save'), VARIABLETYPE_INTEGER, \HMExtended\HeatingPresentation::Execute, true, []);
            $this->EnableAction(\HMExtended\Variables::SUBMIT_WEEK_PROGRAM);
        } else {
            $this->UnregisterVariable(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM);
            $this->UnregisterVariable(\HMExtended\Variables::SUBMIT_WEEK_PROGRAM);
            $EventId = $this->FindIDForIdent(self::EVENT);
            if ($EventId != 0) {
                IPS_DeleteEvent($EventId);
            }
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (($Address != '') && ($this->HasActiveParent())) {
            // Nach der ersten fehlgeschlagenen Abfrage werden keine Folgeanfragen mehr gesendet.
            if ($this->CreateVariablesFromValues() && $this->GetValuesAndSetVariable() && $this->CreateVariablesFromParams()) {
                $this->GetParamsAndSetVariable();
            }
        }
        $this->RemoveLegacyProfiles();
    }

    /**
     * Liest einen Datenpunkt (Paramset VALUES) von der CCU und schreibt ihn in die Statusvariable.
     *
     * @param string $Ident Ident der Statusvariable, muss ein Datenpunkt der Werte sein.
     *
     * @return bool true, wenn die CCU den Wert geliefert hat.
     */
    public function RequestState(string $Ident): bool
    {
        if (!array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
            echo $this->Translate('Invalid Ident for VALUE.') . PHP_EOL;
            return false;
        }
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->SendRPC('getValue', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), [$AddressWithChannel, $Ident]);
        if ($Result === null) {
            return false;
        }
        @$this->SetValue($Ident, $Result);
        return true;
    }

    /**
     * Liest das Paramset MASTER (Einstellungen und Zeitprofile) von der CCU und aktualisiert Statusvariablen und Wochenplan.
     *
     * @return bool true, wenn die CCU die Parameter geliefert hat.
     */
    public function RequestParams(): bool
    {
        return $this->GetParamsAndSetVariable();
    }

    /**
     * Interne Funktion des SDK.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        switch ($Ident) {
            case 'EditScheduleTemps':
                $ScheduleTemps = json_decode($Value, true);
                $this->FixScheduleTemps($ScheduleTemps);
                $done = true;
                return;
            case 'getParam':
                $this->GetParamsAndSetVariable();
                $done = true;
                return;
            case \HMExtended\Variables::SUBMIT_WEEK_PROGRAM:
                if (static::SelectedWeekScheduleIdent) {
                    $this->SaveSchedule((int) $this->GetValue(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM));
                } else {
                    $this->SaveSchedule(1);
                }
                $done = true;
                return;
            case \HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM:
                $this->SetValue($Ident, $Value);
                $done = true;
                return;
            case static::SelectedWeekScheduleIdent:
                if ($this->WeekProfile != (int) $Value) {
                    $this->WeekProfile = (int) $Value;
                    $this->SendDebug(__FUNCTION__, '', 0);
                    $this->SendDebug('ActivePlan', $Value, 0);
                    $this->RefreshScheduleObject();
                }
                break;
        }
        $Property = $Ident;
        if (array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
            $Property = isset(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4] : $Ident;
        }
        if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
            $Property = isset(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][4]) ? \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][4] : $Ident;
        }
        if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
            if (!$this->ReadPropertyBoolean(self::EnablePrefix . $Property)) {
                echo $this->Translate('Variable is disabled in config.') . PHP_EOL;
                $done = true;
                return;
            }
        }
        return;
    }

    //################# protected

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
        if ($State == IS_ACTIVE) {
            if (($this->ReadPropertyString(\HMExtended\Device\Property::Address) != '') && ($this->HasActiveParent())) {
                $this->GetValuesAndSetVariable();
                $this->GetParamsAndSetVariable();
            }
        }
    }

    /**
     * Schreibt einen Wert in die Statusvariable. Das aktive Zeitprofil wird zusätzlich gemerkt und der Wochenplan aktualisiert.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param mixed $Value Der Wert.
     */
    protected function SetVariable(string $Ident, mixed $Value): void
    {
        if ($Ident == static::SelectedWeekScheduleIdent) {
            if ($this->WeekProfile != (int) $Value) {
                $this->WeekProfile = (int) $Value;
                $this->SendDebug(__FUNCTION__, '', 0);
                $this->SendDebug('ActivePlan', $Value, 0);
                $this->RefreshScheduleObject();
            }
        }
        // Bekannte Datenpunkte ohne Statusvariable (in der Konfiguration abgewählt oder bewusst nicht abgebildet) sind kein Fehler.
        // HMBase meldet `Unknown variable` nur noch für Datenpunkte, die das Modul nicht kennt.
        if (($this->FindIDForIdent($Ident) == 0) && $this->IsKnownDatapoint($Ident)) {
            return;
        }
        parent::SetVariable($Ident, $Value);
    }

    /**
     * Prüft, ob der Datenpunkt in den Tabellen des Gerätetyps steht (ValuesSet oder ParamSet).
     *
     * @param string $Ident Name des Datenpunktes.
     *
     * @return bool True, wenn das Modul den Datenpunkt kennt.
     */
    protected function IsKnownDatapoint(string $Ident): bool
    {
        return array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp] ?? [])
            || array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp] ?? []);
    }

    /**
     * Zerlegt das Paramset MASTER in die Zeitprofile (Wochenplan) und die übrigen Parameter und schreibt diese in die Statusvariablen.
     *
     * @param array $Params Das Paramset MASTER der CCU.
     */
    protected function SetParamVariables(array $Params): void
    {
        $ScheduleData = [];

        for ($Plan = 1; $Plan <= static::NumberOfWeekSchedules; $Plan++) {
            foreach (static::$Weekdays as $Index => $Day) {
                for ($Slot = 1; $Slot <= static::NumberOfTimeSlot; $Slot++) {
                    $TimeIndex = sprintf(static::WeekScheduleIndexEndTime, $Slot, $Day, $Plan);
                    $TempIndex = sprintf(static::WeekScheduleIndexTemp, $Slot, $Day, $Plan);
                    // Fehlt ein Schaltpunkt in der Antwort der CCU, endet der Tag dort (keine PHP-Warnung).
                    if (!isset($Params[$TimeIndex], $Params[$TempIndex])) {
                        $ScheduleData[$Plan][$Index][$Slot] = [self::TIME => 1440, self::TEMP => (float) ($ScheduleData[$Plan][$Index][$Slot - 1][self::TEMP] ?? static::MinScheduleTemperature)];
                        continue;
                    }
                    $Time = (int) $Params[$TimeIndex];
                    $Temp = (float) $Params[$TempIndex];

                    $ScheduleData[$Plan][$Index][$Slot][self::TIME] = $Time;
                    $ScheduleData[$Plan][$Index][$Slot][self::TEMP] = $Temp;

                    unset($Params[$TimeIndex]);
                    unset($Params[$TempIndex]);
                }
            }
        }
        $this->WeekSchedules = $ScheduleData;
        foreach ($Params as $Ident => $Value) {
            // Nicht jeder Parameter besitzt eine Variable (abgewählt in der Konfiguration oder nicht verwendet).
            if ($this->FindIDForIdent((string) $Ident) > 0) {
                $this->SetValue((string) $Ident, $Value);
            }
        }
        $this->RefreshScheduleObject();
    }

    /**
     * Wandelt einen Wert in den PHP-Typ der Statusvariable.
     *
     * @param int $VarType Variablentyp (VARIABLETYPE_*).
     * @param mixed $Value Der Wert, wird direkt geändert.
     */
    protected function FixValueType(int $VarType, mixed &$Value): void
    {
        switch ($VarType) {
            case VARIABLETYPE_BOOLEAN:
                $Value = (bool) $Value;
                break;
            case VARIABLETYPE_INTEGER:
                $Value = (int) $Value;
                break;
            case VARIABLETYPE_FLOAT:
                $Value = (float) $Value;
                break;
            case VARIABLETYPE_STRING:
                $Value = (string) $Value;
                break;
        }
    }

    /**
     * Schreibt Parameter in das Paramset MASTER des Gerätes.
     *
     * @param array $Parameter Ident => Wert.
     * @param bool $EmulateStatus Bei true wird die Antwort der CCU nicht abgewartet.
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    protected function PutParamSet(array $Parameter, bool $EmulateStatus = false): bool
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ParamChannel, \HMExtended\CCU::MASTER];
        $Result = $this->SendRPC('putParamset', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), $Paramset, $Parameter, $EmulateStatus);
        return ($Result !== null) ? true : false;
    }

    /**
     * Schreibt mehrere Datenpunkte gemeinsam in das Paramset VALUES des Gerätes.
     *
     * @param array $Values Ident => Wert.
     * @param bool $EmulateStatus Bei true wird die Antwort der CCU nicht abgewartet.
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    protected function PutValueSet(array $Values, bool $EmulateStatus = false): bool
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel, \HMExtended\CCU::VALUES];
        $Result = $this->SendRPC('putParamset', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), $Paramset, $Values, $EmulateStatus);
        return ($Result !== null) ? true : false;
    }

    /**
     * Schreibt einen einzelnen Datenpunkt des Paramsets VALUES.
     *
     * @param string $ValueName Ident des Datenpunktes.
     * @param mixed $Value Der Wert.
     * @param bool $EmulateStatus Bei true wird der Wert direkt in die Statusvariable übernommen.
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    protected function PutValue(string $ValueName, $Value, bool $EmulateStatus = false): bool
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel, $ValueName];
        $Result = $this->SendRPC('setValue', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), $Paramset, $Value, $EmulateStatus);
        if (($Result !== null) && $EmulateStatus) {
            $this->SetVariable($ValueName, $Value);
        }
        return ($Result !== null) ? true : false;
    }

    /**
     * Wandelt die Schaltpunkte des Wochenplan-Ereignisses in die Parameter eines Zeitprofils der CCU.
     * Ein Schaltpunkt gilt bis zum Beginn des nächsten, der letzte bis 24:00 (1440). Die Endzeiten werden auf das Raster
     * des Gerätes gerundet (ScheduleTimeStep), da die CCU sonst abschneidet.
     *
     * @param array $ScheduleGroups Gruppen des Ereignisses (IPS_GetEvent, ScheduleGroups).
     * @param array $ActionIdToTemp Aktions-ID => Temperatur.
     * @param int $Plan Nummer des Zeitprofils auf der CCU (beginnt bei 1).
     *
     * @return array|false [Parameter, Zeitprofil je Tag], false wenn ein Tag mehr Schaltpunkte hat als das Gerät (NumberOfTimeSlot).
     */
    protected static function ScheduleToParams(array $ScheduleGroups, array $ActionIdToTemp, int $Plan): array|false
    {
        $Params = [];
        $Schedule = [];
        foreach ($ScheduleGroups as $Group) {
            $Points = array_values($Group['Points']);
            if (count($Points) > static::NumberOfTimeSlot) {
                return false;
            }
            $Day = static::$Weekdays[$Group['Days']];
            for ($i = 1; $i <= count($Points); $i++) {
                $TimeIndex = sprintf(static::WeekScheduleIndexEndTime, $i, $Day, $Plan);
                $TempIndex = sprintf(static::WeekScheduleIndexTemp, $i, $Day, $Plan);
                if ($i < count($Points)) {
                    $Minutes = ($Points[$i]['Start']['Hour'] * 60) + $Points[$i]['Start']['Minute'];
                    $Rounded = (int) (round($Minutes / static::ScheduleTimeStep) * static::ScheduleTimeStep);
                    $Params[$TimeIndex] = min(1440, max(static::ScheduleTimeStep, $Rounded));
                } else {
                    $Params[$TimeIndex] = 1440;
                }
                $Params[$TempIndex] = $ActionIdToTemp[$Points[$i - 1]['ActionID']] ?? static::MinScheduleTemperature;
                $Schedule[$Group['Days']][$i] = [
                    self::TIME => $Params[$TimeIndex],
                    self::TEMP => $Params[$TempIndex]
                ];
            }
        }
        return [$Params, $Schedule];
    }

    //################# PRIVATE

    /**
     * Baut das Wochenplan-Ereignis aus den Daten des aktiven Zeitprofils neu auf. Ohne Wochenplan in der Konfiguration passiert nichts.
     */
    private function RefreshScheduleObject(): void
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        if (!$this->ReadPropertyBoolean(\HMExtended\Device\Property::Schedule)) {
            return;
        }
        if (!count($this->WeekSchedules)) {
            return;
        }
        $EventId = $this->FindIDForIdent(self::EVENT);
        if ($EventId == 0) {
            return;
        }
        $Event = IPS_GetEvent($EventId);
        foreach ($Event['ScheduleGroups'] as $Group) {
            foreach ($Group['Points'] as $Point) {
                IPS_SetEventScheduleGroupPoint($EventId, $Group['ID'], $Point['ID'], -1, -1, -1, 0);
            }
        }

        if (static::SelectedWeekScheduleIdent) {
            $ActivePlan = $this->WeekProfile;
        } else {
            $ActivePlan = 1;
        }
        $this->SendDebug('ActivePlan', $ActivePlan, 0);
        $ScheduleData = $this->WeekSchedules[$ActivePlan];
        $Actions = $this->UpdateScheduleActions($Event, $ScheduleData);
        $this->SendDebug('Actions', $Actions, 0);
        foreach ($Event['ScheduleGroups'] as $Group) {
            $StartHour = 0;
            $StartMinute = 0;
            foreach ($ScheduleData[$Group['Days']] as $PointId => $Slot) {
                $ActionId = array_search($Slot[self::TEMP], $Actions);
                if ($ActionId === false) {
                    $this->SendDebug('not found', $Slot[self::TEMP], 0);
                    continue;
                }
                IPS_SetEventScheduleGroupPoint($EventId, $Group['ID'], $PointId, $StartHour, $StartMinute, 0, $ActionId);
                if ($Slot[self::TIME] == 1440) {
                    break;
                }
                $StartHour = intdiv($Slot[self::TIME], 60);
                $StartMinute = $Slot[self::TIME] % 60;
            }
        }
    }

    /**
     * Überträgt den Wochenplan zur CCU und schreibt ihn dort in das gewählte Zeitprofil.
     *
     * @param int $Plan Nummer des Zeitprofils auf der CCU (beginnt bei 1).
     */
    private function SaveSchedule(int $Plan): void
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $EventId = $this->FindIDForIdent(self::EVENT);
        if ($EventId == 0) {
            return;
        }
        $Event = IPS_GetEvent($EventId);

        $ActionIdToTemp = [];
        foreach ($Event['ScheduleActions'] as $Action) {
            $ActionIdToTemp[$Action['ID']] = (float) str_replace(',', '.', $Action['Name']);
        }
        $Result = static::ScheduleToParams($Event['ScheduleGroups'], $ActionIdToTemp, $Plan);
        if ($Result === false) {
            echo sprintf($this->Translate('A day of the schedule has more than %d switching points.'), static::NumberOfTimeSlot) . PHP_EOL;
            return;
        }
        [$Params, $Schedule] = $Result;
        if (!$this->PutParamSet($Params)) {
            return;
        }
        $Schedules = $this->WeekSchedules;
        $Schedules[$Plan] = $Schedule;
        $this->WeekSchedules = $Schedules;
        // Auch beim aktiven Zeitprofil neu aufbauen: Die Zeiten wurden auf das Raster des Gerätes gerundet.
        $this->RefreshScheduleObject();
    }

    private function CreateVariablesFromValues(): bool
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $AddressWithChannel);
        if (count($Result) == 0) {
            return false;
        }
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            $Name = $Variable['ID'];
            $Action = '';
            if (!array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
                continue;
            }
            $Property = isset(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4] : $Ident;
            if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                if (!$this->ReadPropertyBoolean(self::EnablePrefix . $Property)) {
                    continue;
                }
            }
            $VarType = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][0];
            if ($VarType == \HMExtended\Variables::VARIABLETYPE_NONE) {
                continue;
            }
            $Presentation = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][1];
            $Action = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2];
            if (isset(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][3])) {
                $Name = $this->Translate(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][3]);
            }
            $HasAction = ($Action === '') ? (bool) (($Variable['OPERATIONS'] ?? 0) & 0b10) : (bool) $Action;
            $this->MaintainHeatingVariable($Ident, $Name, $VarType, $Presentation, $HasAction, $Variable);
            if ($HasAction) {
                $this->EnableAction($Ident);
            }
        }
        return true;
    }

    private function CreateVariablesFromParams(): bool
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ParamChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::MASTER, $AddressWithChannel);
        if (count($Result) == 0) {
            return false;
        }
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            $Profile = '';
            $Name = $Variable['ID'];
            $Action = '';
            if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
                $VarType = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][0];
                if ($VarType == \HMExtended\Variables::VARIABLETYPE_NONE) {
                    if (isset(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2])) {
                        if (is_string(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2])) {
                            $Ident = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2];
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    $VarType = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][0];
                }

                $Property = isset(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][4]) ? \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][4] : $Ident;
                if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                    if (!$this->ReadPropertyBoolean(self::EnablePrefix . $Property)) {
                        continue;
                    }
                }
                $Presentation = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][1];
                $Action = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2];
                if (isset(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][3])) {
                    $Name = $this->Translate(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][3]);
                }
                $HasAction = ($Action === '') ? (bool) (($Variable['OPERATIONS'] ?? 0) & 0b10) : (bool) $Action;
                // Bei zusammengesetzten Werten (z.B. Party-Ende aus Tag, Stunde, Minute) gehört die Beschreibung zu einem Teilwert.
                $this->MaintainHeatingVariable($Ident, $Name, $VarType, $Presentation, $HasAction, ($Ident == $Variable['ID']) ? $Variable : []);
                if ($HasAction) {
                    $this->EnableAction($Ident);
                }
            }
        }
        return true;
    }

    /**
     * Liest das Paramset MASTER von der CCU und schreibt die Werte in die Statusvariablen.
     *
     * @return bool true, wenn die CCU Parameter geliefert hat.
     */
    private function GetParamsAndSetVariable(): bool
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ParamChannel;
        $Result = $this->GetParamset(\HMExtended\CCU::MASTER, $AddressWithChannel);
        if (count($Result) != 0) {
            $this->SetParamVariables($Result);
            return true;
        }
        return false;
    }

    /**
     * Legt eine Statusvariable mit Darstellung an bzw. aktualisiert deren Darstellung.
     *
     * @param string $Presentation Schlüssel der Darstellung (\HMExtended\HeatingPresentation).
     * @param array $Description Beschreibung des Datenpunktes laut CCU, leer wenn unbekannt.
     */
    private function MaintainHeatingVariable(string $Ident, string $Name, int $VarType, string $Presentation, bool $HasAction, array $Description): void
    {
        $this->MaintainPresentationVariable($Ident, $Name, $VarType, \HMExtended\HeatingPresentation::Get(
            $Presentation,
            $VarType,
            $HasAction,
            $Description,
            fn (string $Text): string => $this->Translate($Text)
        ));
    }

    /**
     * Löscht die Profile bis Version 3.81, sobald keine Statusvariable dieser Instanz sie mehr nutzt.
     * UnregisterProfile lässt Profile stehen, die Variablen anderer Instanzen oder des Nutzers verwenden.
     */
    private function RemoveLegacyProfiles(): void
    {
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildId) {
            if (IPS_VariableExists($ChildId) && in_array(IPS_GetVariable($ChildId)['VariableProfile'], \HMExtended\Variables::LegacyProfiles)) {
                return;
            }
        }
        $this->UnregisterLegacyProfiles();
    }

    /**
     * Löscht die Profile bis Version 3.81. `Execute.HM` nutzt auch das Modul Programme, solange dessen Variablen es tragen, bleibt es stehen.
     */
    private function UnregisterLegacyProfiles(): void
    {
        foreach (\HMExtended\Variables::LegacyProfiles as $Profile) {
            $this->UnregisterProfile($Profile);
        }
    }

    /**
     * FixScheduleTemps
     *
     * Cleanup illegal Temps.
     * NumberSpinner hat keine StepSize :(
     *
     * @return bool
     */
    private function FixScheduleTemps(array $ScheduleTemps)
    {
        $HasFixed = false;
        $NewScheduleTemps = [];
        foreach ($ScheduleTemps as $Temp) {
            // Grenzen der Wochenplan-Temperaturen laut CCU-Firmware je Gerät.
            if ($Temp['TemperatureValue'] < static::MinScheduleTemperature) {
                $HasFixed = true;
                continue;
            }
            if ($Temp['TemperatureValue'] > static::MaxScheduleTemperature) {
                $HasFixed = true;
                continue;
            }
            if (fmod($Temp['TemperatureValue'], 0.5) > 0) {
                if (($Temp['TemperatureValue'] - floor($Temp['TemperatureValue'])) < 0.5) {
                    $Temp['TemperatureValue'] = floor($Temp['TemperatureValue']);
                } else {
                    $Temp['TemperatureValue'] = floor($Temp['TemperatureValue']) + 0.5;
                }
                $HasFixed = true;
            }
            if (in_array($Temp, $NewScheduleTemps)) {
                $HasFixed = true;
                continue;
            }

            $NewScheduleTemps[] = $Temp;
        }
        if ($HasFixed) {
            $this->UpdateFormField('ScheduleTemps', 'values', json_encode($NewScheduleTemps));
        }
    }

    /**
     * Legt die Aktionen des Wochenplans an, eine je Temperatur. Die Aktions-IDs beginnen bei 1.
     * Es werden höchstens 31 Aktionen angelegt.
     *
     * @param array $Event Das Wochenplan-Ereignis.
     * @param array $ScheduleData Die Zeitprofile der CCU.
     *
     * @return array Aktions-ID => Temperatur.
     */
    private function UpdateScheduleActions(array $Event, array $ScheduleData): array
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $ScheduleTemps = [];
        foreach (static::$Weekdays as $Index => $Day) {
            $ScheduleTemps = array_merge($ScheduleTemps, array_column($ScheduleData[$Index], self::TEMP));
        }
        $ScheduleTemps = array_unique($ScheduleTemps);
        if (count($ScheduleTemps) > 31) {
            $this->LogMessage($this->Translate('Too many temperatures on schedule. Crop to 31 actions.'), KL_WARNING);
            $ScheduleTemps = array_slice($ScheduleTemps, 0, 31);
        }
        $ScheduleTempsProperty = array_column(json_decode($this->ReadPropertyString(\HMExtended\Device\Property::ScheduleTemps), true), 'TemperatureValue');
        $ScheduleTemps = array_unique(array_merge($ScheduleTemps, $ScheduleTempsProperty), SORT_NUMERIC);
        if (count($ScheduleTemps) > 31) {
            $this->LogMessage($this->Translate('Too many temperatures on schedule. Crop to 31 actions.'), KL_WARNING);
            $ScheduleTemps = array_slice($ScheduleTemps, 0, 31);
        }
        sort($ScheduleTemps);
        if (count($ScheduleTemps) == 0) {
            return [];
        }
        $StepSize = 260 / count($ScheduleTemps);

        foreach ($Event['ScheduleActions'] as $Action) {
            IPS_SetEventScheduleAction($Event['EventID'], $Action['ID'], '', 0, '');
        }
        // Aktions-IDs müssen größer als 0 sein (0 = 'keine Aktion')
        $ActionId = 1;
        $Actions = [];
        foreach ($ScheduleTemps as $Temp) {
            $Color = self::TempToRGB($ActionId - 1, $StepSize);
            $this->SendDebug((string) $Temp, $Color, 0);
            $Actions[$ActionId] = $Temp;
            IPS_SetEventScheduleAction($Event['EventID'], $ActionId++, sprintf('%0.1f °C', $Temp), $Color, "<?php\r\n/** Do not edit this code,\r\n    It will be automatically overwritten.\r\n*/\r\n\r\n" . 'HM_RequestParams($_IPS[\'TARGET\']);');
        }
        $this->SendDebug(__FUNCTION__, $Actions, 0);
        return $Actions;
    }

    private function CreateWeekPlan(): void
    {
        $this->WeekSchedules = [];
        $EventId = $this->FindIDForIdent(self::EVENT);
        if ($EventId == 0) {
            $EventId = IPS_CreateEvent(2);
            IPS_SetParent($EventId, $this->InstanceID);
            IPS_SetIdent($EventId, self::EVENT);
            IPS_SetName($EventId, $this->Translate('Schedule'));
            //$Event = IPS_GetEvent($EventId);
            //$this->UpdateScheduleActions($Event, /*true*/);
            for ($i = 0; $i < 7; $i++) {
                IPS_SetEventScheduleGroup($EventId, $i, (1 << $i));
            }
            IPS_SetEventActive($EventId, true);
        }
    }
    /*
        private function SetTempColorsAttribute(array $Values)
    {
            foreach ($Values as $Color => $Temp) {
            $ScheduleTemps[] = [$Temp, $Color];
        }
            $this->WriteAttributeArray('ScheduleColors', $ScheduleTemps);
    }

        private function GetTempColorsAttribute()
    {
        $Values = [];
            $ScheduleTemps = $this->ReadAttributeArray('ScheduleColors');
        foreach ($ScheduleTemps as $TempColor) {
                $Values[$TempColor[1]] = $TempColor[0];
        }
            asort($Values);
        return $Values;
    }
     */
    /*
    private function GetNextColor(float $Temp, array $Colors)
    {
        $Found = 0x000080;
        foreach ($Colors as $Color => $TempColor) {
            if ($TempColor < $Temp) {
                if ($Color > 0x010101) {
                    $Found = $Color - 0x010101;
                }
            } else {
                break;
            }
        }
        return $Found;
    }
    private function GetColors(array $Temps)
    {
        $Temps = static::$ScheduleTempsInit;
        //$Temps = array_unique($Temps, SORT_NUMERIC);
        $Colors = [];
        $TempMax = 30; //end($Temps);
        $TempMin = 5; //reset($Temps);
        $rgb1 = 0x000080; //$this->ReadPropertyInteger('ScheduleMinColor');
        $rgb2 = 0xff0000; //$this->ReadPropertyInteger('ScheduleMaxColor');
        $r1 = $rgb1 >> 16;
        $g1 = ($rgb1 & 0x00FF00) >> 8;
        $b1 = $rgb1 & 0x0000FF;
        $r2 = $rgb2 >> 16;
        $g2 = ($rgb2 & 0x00FF00) >> 8;
        $b2 = $rgb2 & 0x0000FF;
        $Counts = (($TempMax - $TempMin) / 0.5) + 1;
        $dr = ($r2 - $r1) / $Counts;
        $dg = ($g2 - $g1) / $Counts;
        $db = ($b2 - $b1) / $Counts;
        for ($i = 0; $i < $Counts; $i++) {
            $rgb =
                (round($r1 + $dr * $i) << 16) |
                (round($g1 + $dg * $i) << 8) |
                (round($b1 + $db * $i));
            $Temp = ($TempMin + ($i * 0.5));
            if (in_array($Temp, $Temps)) {
                $Colors[] = [
                    'Temp' => $Temp,
                    'Color'=> $rgb
                ];
            }
        }
        return $Colors;
    }
     */

    /**
     * HSVtoRGB
     *
     * not static, falls wir doch auf Statusvariablen zurückgreifen müssen
     *
     * @param  array $Values
     * @return int
     */
    private static function TempToRGB(int $Index, float $StepSize): int
    {
        $Index++;
        $hue = (260 - ($Index * $StepSize)) / 360;
        $saturation = 0.75;
        $value = 1;
        if ($saturation == 0) {
            $red = $value * 255;
            $green = $value * 255;
            $blue = $value * 255;
        } else {
            $var_h = $hue * 6;
            $var_i = floor($var_h);
            $var_1 = $value * (1 - $saturation);
            $var_2 = $value * (1 - $saturation * ($var_h - $var_i));
            $var_3 = $value * (1 - $saturation * (1 - ($var_h - $var_i)));

            switch ($var_i) {
                case 0:
                    $var_r = $value;
                    $var_g = $var_3;
                    $var_b = $var_1;
                    break;
                case 1:
                    $var_r = $var_2;
                    $var_g = $value;
                    $var_b = $var_1;
                    break;
                case 2:
                    $var_r = $var_1;
                    $var_g = $value;
                    $var_b = $var_3;
                    break;
                case 3:
                    $var_r = $var_1;
                    $var_g = $var_2;
                    $var_b = $value;
                    break;
                case 4:
                    $var_r = $var_3;
                    $var_g = $var_1;
                    $var_b = $value;
                    break;
                default:
                    $var_r = $value;
                    $var_g = $var_1;
                    $var_b = $var_2;
                    break;
            }

            $red = (int) round($var_r * 255);
            $green = (int) round($var_g * 255);
            $blue = (int) round($var_b * 255);
        }

        return ($red << 16) ^ ($green << 8) ^ $blue;
    }
}

/* @} */
