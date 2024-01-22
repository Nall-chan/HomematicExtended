<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.74
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
    protected const SelectedWeekScheduleIdent = '';

    protected const EVENT = 'WEEK_PROFIL';
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
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);

        foreach (\HMExtended\Property::$Properties[static::DeviceTyp] as $Property => $Value) {
            $this->RegisterPropertyBoolean('enable_' . $Property, $Value);
        }
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::Schedule, true);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::SetPointBehavior, true);
        $InitTemps = [5, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 30];
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
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            foreach (\HMExtended\Variables::$Profiles as $ProfileName => $ProfileData) {
                $this->UnregisterProfile($ProfileName);
            }
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->WeekProfile = 1;
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $this->SetReceiveDataFilter($Address == '' ? '.*9999999999.*' : '.*"DeviceID":"' . $Address . static::ValuesChannel . '.*');

        foreach (\HMExtended\ValuesSet::$Variables[static::DeviceTyp] as $Ident => $VarData) {
            $Property = isset($VarData[4]) ? $VarData[4] : $Ident;
            if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                if (!$this->ReadPropertyBoolean('enable_' . $Property)) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        foreach (\HMExtended\ParamSet::$Variables[static::DeviceTyp] as $Ident => $ParaData) {
            $Property = isset($ParaData[4]) ? $ParaData[4] : $Ident;
            if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                if (!$this->ReadPropertyBoolean('enable_' . $Property)) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::Schedule)) {
            $this->CreateWeekPlan();
            $ProfileSubmitPlan = false;
            switch (static::SelectedWeekScheduleIdent) { //Nur Speichern Button
                case \HMExtended\HeatingGroupHmIP::ACTIVE_PROFILE:
                    $ProfileSubmitPlan = 'Heating.Control.Profile.HmIP';
                    break;
                case \HMExtended\HeatingGroup::WEEK_PROGRAM_POINTER:
                    $ProfileSubmitPlan = 'Heating.Control.Profile.HM';
                    break;
            }
            if ($ProfileSubmitPlan) {
                $this->CreateProfile($ProfileSubmitPlan);
                $isOld = @$this->GetIDForIdent(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM);
                $this->RegisterVariableInteger(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM, $this->Translate('Schedule save in profile'), $ProfileSubmitPlan);
                $this->EnableAction(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM);
                if (!$isOld) {
                    $this->SetValue(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM, 1);
                }
            }
            $this->CreateProfile('Execute.HM');
            $this->RegisterVariableInteger(\HMExtended\Variables::SUBMIT_WEEK_PROGRAM, $this->Translate('Schedule save'), 'Execute.HM');
            $this->EnableAction(\HMExtended\Variables::SUBMIT_WEEK_PROGRAM);
        } else {
            $this->UnregisterVariable(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM);
            $this->UnregisterVariable(\HMExtended\Variables::SUBMIT_WEEK_PROGRAM);
            $EventId = @IPS_GetObjectIDByIdent(self::EVENT, $this->InstanceID);
            if ($EventId != false) {
                IPS_DeleteEvent($EventId);
            }
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (($Address != '') && ($this->HasActiveParent())) {
            $this->CreateVariablesFromValues();
            $this->GetValuesAndSetVariable();
            $this->CreateVariablesFromParams();
            $this->GetParamsAndSetVariable();
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'EditScheduleTemps':
                $ScheduleTemps = json_decode($Value, true);
                $this->FixScheduleTemps($ScheduleTemps);
                return true;
            case 'getParam':
                $this->GetParamsAndSetVariable();
                return true;
            case \HMExtended\Variables::SUBMIT_WEEK_PROGRAM:
                if (static::SelectedWeekScheduleIdent) {
                    $this->SaveSchedule((int) $this->GetValue(\HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM));
                } else {
                    $this->SaveSchedule(1);
                }
                return true;
            case \HMExtended\Variables::SELECT_NEW_WEEK_PROGRAM:
                $this->SetValue($Ident, $Value);
                return true;
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
            if (!$this->ReadPropertyBoolean('enable_' . $Property)) {
                trigger_error('Variable is disabled in config.', E_USER_NOTICE);
                return true;
            }
        }
        return false;
    }

    public function RequestState(string $Ident)
    {
        if (!array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
            trigger_error($this->Translate('Invalid Ident for VALUE.'), E_USER_NOTICE);
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

    public function RequestParams()
    {
        return $this->GetParamsAndSetVariable();
    }

    //################# protected

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            if (($this->ReadPropertyString(\HMExtended\Device\Property::Address) != '') && ($this->HasActiveParent())) {
                $this->GetValuesAndSetVariable();
                $this->GetParamsAndSetVariable();
            }
        }
    }

    protected function SetVariable(string $Ident, $Value)
    {
        if ($Ident == static::SelectedWeekScheduleIdent) {
            if ($this->WeekProfile != (int) $Value) {
                $this->WeekProfile = (int) $Value;
                $this->SendDebug(__FUNCTION__, '', 0);
                $this->SendDebug('ActivePlan', $Value, 0);
                $this->RefreshScheduleObject();
            }
        }
        parent::SetVariable($Ident, $Value);
    }

    protected function SetParamVariables(array $Params)
    {
        $ScheduleData = [];

        for ($Plan = 1; $Plan <= static::NumberOfWeekSchedules; $Plan++) {
            foreach (static::$Weekdays as $Index => $Day) {
                for ($Slot = 1; $Slot <= static::NumberOfTimeSlot; $Slot++) {
                    $TimeIndex = sprintf(static::WeekScheduleIndexEndTime, $Slot, $Day, $Plan);
                    $TempIndex = sprintf(static::WeekScheduleIndexTemp, $Slot, $Day, $Plan);
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
            @$this->SetValue($Ident, $Value);
        }
        $this->RefreshScheduleObject();
    }

    protected function FixValueType($VarType, &$Value)
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

    protected function PutParamSet(array $Parameter, bool $EmulateStatus = false)
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ParamChannel, \HMExtended\CCU::MASTER];
        $Result = $this->SendRPC('putParamset', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), $Paramset, $Parameter, $EmulateStatus);
        return ($Result !== null) ? true : false;
    }

    protected function PutValueSet(array $Values, bool $EmulateStatus = false)
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel, \HMExtended\CCU::VALUES];
        $Result = $this->SendRPC('putParamset', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), $Paramset, $Values, $EmulateStatus);
        return ($Result !== null) ? true : false;
    }

    protected function PutValue(string $ValueName, $Value, bool $EmulateStatus = false)
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel, $ValueName];
        $Result = $this->SendRPC('setValue', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), $Paramset, $Value, $EmulateStatus);
        if (($Result !== null) && $EmulateStatus) {
            $this->SetVariable($ValueName, $Value);
        }
        return ($Result !== null) ? true : false;
    }

    //################# PRIVATE

    private function RefreshScheduleObject()
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        if (!$this->ReadPropertyBoolean(\HMExtended\Device\Property::Schedule)) {
            return;
        }
        if (!count($this->WeekSchedules)) {
            return;
        }
        $EventId = @IPS_GetObjectIDByIdent(self::EVENT, $this->InstanceID);
        if ($EventId === false) {
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

    private function SaveSchedule(int $Plan)
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $EventId = @IPS_GetObjectIDByIdent(self::EVENT, $this->InstanceID);
        if ($EventId === false) {
            return;
        }
        $Event = IPS_GetEvent($EventId);

        $ActionIdToTemp = [];
        $Params = [];
        $Schedule = [];
        foreach ($Event['ScheduleActions'] as $Action) {
            $ActionIdToTemp[$Action['ID']] = (float) str_replace(',', '.', $Action['Name']);
        }
        foreach ($Event['ScheduleGroups'] as $Group) {
            $Day = static::$Weekdays[$Group['Days']];
            $Slot = 1;
            for ($i = 1; $i <= count($Group['Points']); $i++) {
                $TimeIndex = sprintf(static::WeekScheduleIndexEndTime, $Slot, $Day, $Plan);
                $TempIndex = sprintf(static::WeekScheduleIndexTemp, $Slot, $Day, $Plan);
                if ($i < count($Group['Points'])) {
                    $Params[$TimeIndex] = ($Group['Points'][$i]['Start']['Hour'] * 60) + $Group['Points'][$i]['Start']['Minute'];
                } else {
                    $Params[$TimeIndex] = 1440;
                }
                $Params[$TempIndex] = $ActionIdToTemp[$Group['Points'][$i - 1]['ActionID']];
                $Schedule[$Group['Days']][$Slot] = [
                    self::TIME => $Params[$TimeIndex],
                    self::TEMP => $Params[$TempIndex]
                ];
                $Slot++;
            }
        }
        $Schedules = $this->WeekSchedules;
        $Schedules[$Plan] = $Schedule;
        $this->WeekSchedules = $Schedules;
        $this->PutParamSet($Params);

        if (static::SelectedWeekScheduleIdent) {
            $ActivePlan = $this->WeekProfile;
        } else {
            $ActivePlan = 1;
        }
        $this->SendDebug('ActivePlan', $ActivePlan, 0);
        if ($ActivePlan != $Plan) {
            $this->RefreshScheduleObject();
        }
    }

    private function CreateVariablesFromValues()
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $AddressWithChannel);
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            $Name = $Variable['ID'];
            $Action = '';
            if (!array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
                continue;
            }
            $Property = isset(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4] : $Ident;
            if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                if (!$this->ReadPropertyBoolean('enable_' . $Property)) {
                    continue;
                }
            }
            $VarType = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][0];
            if ($VarType == \HMExtended\Variables::VARIABLETYPE_NONE) {
                continue;
            }
            $Profile = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][1];
            $Action = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2];
            if (isset(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][3])) {
                $Name = $this->Translate(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][3]);
            }
            $this->CreateProfile($Profile);
            $this->MaintainVariable($Ident, $Name, $VarType, $Profile, 0, true);
            if ($Action === '') {
                if ($Variable['OPERATIONS'] & 0b10) {
                    $this->EnableAction($Ident);
                }
            } elseif ($Action) {
                $this->EnableAction($Ident);
            }
        }
    }

    private function GetValuesAndSetVariable(): bool
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamset(\HMExtended\CCU::VALUES, $AddressWithChannel);
        if (count($Result) != 0) {
            foreach ($Result as $Ident => $Value) {
                $this->SetVariable($Ident, $Value);
            }
            return true;
        }
        return false;
    }

    private function CreateVariablesFromParams()
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ParamChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::MASTER, $AddressWithChannel);
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

                $Property = isset(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][4] : $Ident;
                if (array_key_exists($Property, \HMExtended\Property::$Properties[static::DeviceTyp])) {
                    if (!$this->ReadPropertyBoolean('enable_' . $Property)) {
                        continue;
                    }
                }
                $Profile = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][1];
                $Action = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2];
                if (isset(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][3])) {
                    $Name = $this->Translate(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][3]);
                }
                $this->CreateProfile($Profile);

                $this->MaintainVariable($Ident, $Name, $VarType, $Profile, 0, true);
                if ($Action === '') {
                    if ($Variable['OPERATIONS'] & 0b10) {
                        $this->EnableAction($Ident);
                    }
                } elseif ($Action) {
                    $this->EnableAction($Ident);
                }
            }
        }
    }

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

    private function CreateProfile($Profile)
    {
        if ($Profile) {
            if (substr($Profile, 0, 1) != '~') {
                if (array_key_exists($Profile, \HMExtended\Variables::$Profiles)) {
                    $this->RegisterProfileEx(
                        \HMExtended\Variables::$Profiles[$Profile][0],
                        $Profile,
                        \HMExtended\Variables::$Profiles[$Profile][1],
                        \HMExtended\Variables::$Profiles[$Profile][2],
                        \HMExtended\Variables::$Profiles[$Profile][3],
                        \HMExtended\Variables::$Profiles[$Profile][4],
                        \HMExtended\Variables::$Profiles[$Profile][5],
                        \HMExtended\Variables::$Profiles[$Profile][6],
                        \HMExtended\Variables::$Profiles[$Profile][7]
                    );
                }
            }
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
            if ($Temp['TemperatureValue'] < 5) {
                $HasFixed = true;
                continue;
            }
            if ($Temp['TemperatureValue'] > 30) {
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
            $ScheduleTemps = array_slice($ScheduleTemps, 0, 32);
        }
        $ScheduleTempsProperty = array_column(json_decode($this->ReadPropertyString(\HMExtended\Device\Property::ScheduleTemps), true), 'TemperatureValue');
        $ScheduleTemps = array_unique(array_merge($ScheduleTemps, $ScheduleTempsProperty), SORT_NUMERIC);
        if (count($ScheduleTemps) > 31) {
            $this->LogMessage($this->Translate('Too many temperatures on schedule. Crop to 31 actions.'), KL_WARNING);
            $ScheduleTemps = array_slice($ScheduleTemps, 0, 32);
        }
        sort($ScheduleTemps);
        $StepSize = 260 / count($ScheduleTemps);

        foreach ($Event['ScheduleActions'] as $Action) {
            IPS_SetEventScheduleAction($Event['EventID'], $Action['ID'], '', 0, '');
        }
        $i = 0;
        foreach ($ScheduleTemps as $Temp) {
            $Color = self::TempToRGB($i, $StepSize);
            $this->SendDebug($Temp, $Color, 0);
            IPS_SetEventScheduleAction($Event['EventID'], $i++, sprintf('%0.1f °C', $Temp), $Color, "<?php\r\n/** Do not edit this code,\r\n    It will be automatically overwritten.\r\n*/\r\n\r\n" . 'HM_RequestParams($_IPS[\'TARGET\']);');
            if ($i == 32) {
                break;
            }
        }
        $this->SendDebug(__FUNCTION__, $ScheduleTemps, 0);
        return $ScheduleTemps;
    }

    private function CreateWeekPlan()
    {
        $this->WeekSchedules = [];
        $EventId = @IPS_GetObjectIDByIdent(self::EVENT, $this->InstanceID);
        if ($EventId === false) {
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
