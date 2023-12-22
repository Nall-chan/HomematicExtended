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
 * @version       3.71
 */
require_once __DIR__ . '/HMBase.php';  // HMBase Klasse

/**
 * HMHeatingDevice
 *
 * @property array $WeekSchedules
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
        foreach (\HMExtended\Property::$Properties[static::DeviceTyp] as $Property => $Value) {
            $this->RegisterPropertyBoolean('enable_' . $Property, $Value);
        }
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::Schedule, true);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::SetPointBehavior, true);

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
        $this->RegisterAttributeString('ScheduleColors', json_encode($ScheduleTempsInit));

        $this->WeekSchedules = [];
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
        $Result = $this->SendRPC('getValue', [$AddressWithChannel, $Ident]);
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

    public function ReceiveData($JSONString)
    {
        $Event = json_decode($JSONString, true);
        $this->SetVariable($Event['VariableName'], $Event['VariableValue']);
        return '';
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
        @$this->SetValue($Ident, $Value);
    }

    protected function SetParamVariables(array $Params)
    {
        $ScheduleData = [];
        $ScheduleTemps = $this->GetTempColorsAttribute();
        $ScheduleActionHasChanged = false;
        for ($Plan = 1; $Plan <= static::NumberOfWeekSchedules; $Plan++) {
            foreach (static::$Weekdays as $Index => $Day) {
                for ($Slot = 1; $Slot <= static::NumberOfTimeSlot; $Slot++) {
                    $TimeIndex = sprintf(static::WeekScheduleIndexEndTime, $Slot, $Day, $Plan);
                    $TempIndex = sprintf(static::WeekScheduleIndexTemp, $Slot, $Day, $Plan);
                    $Time = $Params[$TimeIndex];
                    $Temp = $Params[$TempIndex];
                    $this->SendDebug($TimeIndex, $Time, 0);
                    $this->SendDebug($TempIndex, $Temp, 0);
                    $this->SendDebug($TempIndex, gettype($Temp), 0);
                    $ScheduleData[$Plan][$Index][$Slot][self::TIME] = $Time;
                    $ScheduleData[$Plan][$Index][$Slot][self::TEMP] = $Temp;
                    if (!array_key_exists((int)$Temp, $ScheduleTemps)) {
                        $Color = $this->GetNextColor((float)$Temp, $ScheduleData);
                        $ScheduleTemps[$Params[$TempIndex]] = $Color;
                        $ScheduleActionHasChanged = true;
                    }
                    unset($Params[$TimeIndex]);
                    unset($Params[$TempIndex]);
                }
            }
        }
        if ($ScheduleActionHasChanged) {
            // todo wenn array zu groß war, Meldung ausgeben.
            ksort($ScheduleTemps);
            $ScheduleTemps = array_slice($ScheduleTemps, 0, 32, true);
            $this->SetTempColorsAttribute($ScheduleTemps);
        }
        $this->WeekSchedules = $ScheduleData;
        foreach ($Params as $Ident => $Value) {
            @$this->SetValue($Ident, $Value);
        }
        $this->RefreshScheduleObject($ScheduleActionHasChanged);
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
        $Result = $this->SendRPC('putParamset', $Paramset, $Parameter, $EmulateStatus);
        return ($Result !== null) ? true : false;
    }

    protected function PutValueSet(array $Values, bool $EmulateStatus = false)
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel, \HMExtended\CCU::VALUES];
        $Result = $this->SendRPC('putParamset', $Paramset, $Values, $EmulateStatus);
        return ($Result !== null) ? true : false;
    }

    protected function PutValue(string $ValueName, $Value, bool $EmulateStatus = false)
    {
        $Paramset = [$this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel, $ValueName];
        $Result = $this->SendRPC('setValue', $Paramset, $Value, $EmulateStatus);
        if (($Result !== null) && $EmulateStatus) {
            $this->SetVariable($ValueName, $Value);
        }
        return ($Result !== null) ? true : false;
    }

    protected function RefreshScheduleObject(bool $ScheduleActionHasChanged = false)
    {
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
        $Actions = $this->UpdateScheduleActions($Event, $ScheduleActionHasChanged);
        if (static::SelectedWeekScheduleIdent) {
            $ActivePlan = $this->GetValue(static::SelectedWeekScheduleIdent);
        } else {
            $ActivePlan = 1;
        }
        $ScheduleData = $this->WeekSchedules[$ActivePlan];
        foreach ($Event['ScheduleGroups'] as $Group) {
            $StartHour = 0;
            $StartMinute = 0;
            foreach ($ScheduleData[$Group['Days']] as $PointId => $Slot) {
                $ActionId = array_search($Slot[self::TEMP], $Actions);
                IPS_SetEventScheduleGroupPoint($EventId, $Group['ID'], $PointId, $StartHour, $StartMinute, 0, $ActionId);
                if ($Slot[self::TIME] == 1440) {
                    break;
                }
                $StartHour = intdiv($Slot[self::TIME], 60);
                $StartMinute = $Slot[self::TIME] % 60;
            }
        }
    }

    protected function SaveSchedule(int $Plan)
    {
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
            $ActivePlan = $this->GetValue(static::SelectedWeekScheduleIdent);
        } else {
            $ActivePlan = 1;
        }
        if ($ActivePlan != $Plan) {
            $this->RefreshScheduleObject();
        }
    }

    //################# PRIVATE

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

    private function GetParamset(string $Paramset, string $AddressWithChannel)
    {
        $Result = $this->SendRPC('getParamset', [$AddressWithChannel, $Paramset]);
        return is_array($Result) ? $Result : [];
    }

    private function GetParamsetDescription(string $Paramset, string $AddressWithChannel)
    {
        $Result = $this->SendRPC('getParamsetDescription', [$AddressWithChannel, $Paramset]);
        return is_array($Result) ? $Result : [];
    }

    private function SendRPC(string $MethodName, array $Paramset, $Data = null, bool $EmulateStatus = false)
    {
        if (!$this->HasActiveParent()) {
            trigger_error($this->Translate('Instance has no active Parent Instance!'), E_USER_NOTICE);
            return null;
        }
        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol),
            'MethodName' => $MethodName,
            'WaitTime'   => 3,
            'Data'       => $Paramset
        ];
        if (is_array($Data)) {
            $ParentData['Data'][] = json_encode($Data, JSON_PRESERVE_ZERO_FRACTION);
        } elseif (!is_null($Data)) {
            $ParentData['Data'][] = $Data;
        }
        $this->SendDebug('Send', $ParentData, 0);
        $ResultJSON = @$this->SendDataToParent(json_encode($ParentData, JSON_PRESERVE_ZERO_FRACTION));
        if ($EmulateStatus) {
            return true;
        }
        if ($ResultJSON === false) {
            trigger_error('Error on ' . $MethodName, E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
            return null;
        }
        $this->SendDebug('Receive JSON', $ResultJSON, 0);
        if ($ResultJSON === '') {
            return true;
        }
        $Result = json_decode($ResultJSON, true);
        return $Result;
    }

    private function UpdateScheduleActions(array $Event, bool $ScheduleActionHasChanged): array
    {
        $ScheduleActionColors = $this->GetTempColorsAttribute();
        if ($ScheduleActionHasChanged) {
            foreach ($Event['ScheduleActions'] as $Action) {
                IPS_SetEventScheduleAction($Event['EventID'], $Action['ID'], '', 0, '');
            }
            $i = 0;
            foreach ($ScheduleActionColors as $Temp => $Color) {
                IPS_SetEventScheduleAction($Event['EventID'], $i++, sprintf('%0.1f °C', $Temp), $Color, 'HM_RequestParams($_IPS[\'TARGET\']);');
                if ($i == 32) {
                    break;
                }
            }
        }
        return array_keys($ScheduleActionColors);
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
            $Event = IPS_GetEvent($EventId);
            $this->UpdateScheduleActions($Event, true);
            for ($i = 0; $i < 7; $i++) {
                IPS_SetEventScheduleGroup($EventId, $i, (1 << $i));
            }
            IPS_SetEventActive($EventId, true);
        }
    }

    private function SetTempColorsAttribute(array $Values)
    {
        ksort($Values);
        foreach ($Values as $Temp => $Color) {
            $ScheduleTemps[] = [$Temp, $Color];
        }
        $this->WriteAttributeString('ScheduleColors', json_encode($ScheduleTemps));
    }

    private function GetTempColorsAttribute()
    {
        $Values = [];
        $ScheduleTemps = json_decode($this->ReadAttributeString('ScheduleColors'), true);
        foreach ($ScheduleTemps as $TempColor) {
            $Values[$TempColor[0]] = $TempColor[1];
        }
        ksort($Values);
        return $Values;
    }

    private function GetNextColor(float $Temp, $Colors)
    {
        $Found = 0x000080;
        foreach ($Colors as $TempColor => $Color) {
            if ($TempColor < $Temp) {
                $Found = $Color;
            } else {
                break;
            }
        }
        return $Found;
    }
}

/* @} */
