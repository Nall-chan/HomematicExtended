<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.12
 */
require_once __DIR__ . '/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/HMTypes.php';  // HMTypes Data

abstract class HMDeviceBase extends HMBase
{
    use \HMExtended\HMTypes;

    protected const DeviceTyp = '';

    protected static $VariableTypes = [
        'BOOL'    => VARIABLETYPE_BOOLEAN,
        'INTEGER' => VARIABLETYPE_INTEGER,
        'ENUM'    => VARIABLETYPE_INTEGER,
        'FLOAT'   => VARIABLETYPE_FLOAT,
        'STRING'  => VARIABLETYPE_STRING,
    ];
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        foreach (static::$Variables[static::DeviceTyp] as $Ident => $VarData) {
            if (isset($VarData[4])) {
                $this->RegisterPropertyBoolean('enable_' . $Ident, $VarData[4]);
            }
        }
        foreach (static::$Parameters[static::DeviceTyp] as $Ident => $VarData) {
            if (isset($VarData[4])) {
                $this->RegisterPropertyBoolean('enable_' . $Ident, $VarData[4]);
            }
        }
        $this->RegisterPropertyBoolean('enable_SCHEDULE', true);
        $this->RegisterPropertyFloat('ScheduleMinTemp', 5);
        $this->RegisterPropertyFloat('ScheduleMaxTemp', 30);
        $this->RegisterPropertyInteger('ScheduleStepsTemp', 2);
        $this->RegisterPropertyInteger('ScheduleMinColor', 0x0000ff);
        $this->RegisterPropertyInteger('ScheduleMaxColor', 0xff0000);
        $ScheduleTemps = [
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
        $this->RegisterAttributeString('ScheduleColors', json_encode($ScheduleTemps));
    }
    /**
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            foreach (static::$VariabeProfiles as $ProfileName => $ProfileData) {
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
        $Address = $this->ReadPropertyString('Address');
        $this->SetReceiveDataFilter($Address == '' ? '.*9999999999.*' : '.*"DeviceID":"' . $Address . '".*');

        foreach (static::$Variables[static::DeviceTyp] as $Ident => $VarData) {
            if (isset($VarData[4])) {
                if (!$this->ReadPropertyBoolean('enable_' . $Ident)) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        foreach (static::$Parameters[static::DeviceTyp] as $Ident => $VarData) {
            if (isset($VarData[4])) {
                if (!$this->ReadPropertyBoolean('enable_' . $Ident)) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }

        $this->CreateWeekPlan($this->ReadPropertyBoolean('enable_SCHEDULE'));
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($Address != '') {
            $this->createValues();
            $this->getValues();
            $this->createParam();
            $this->getParam();
        }
    }

    /*
    public function ReadParamset(string $Paramset)
    {
        $Result = $this->GetParamset($Paramset);
        return $Result;
    }


    public function WriteParameterBoolean(string $Parameter, bool $Data)
    {
        $Result = $this->PutParamset([$Parameter=> $Data]);
        return $Result;
    }


    public function WriteParameterInteger(string $Parameter, int $Data)
    {
        $Result = $this->PutParamset([$Parameter=> $Data]);
        return $Result;
    }


    public function WriteParameterFloat(string $Parameter, float $Data)
    {
        $Result = $this->PutParamset([$Parameter=> $Data]);
        return $Result;
    }


    public function WriteParameterString(string $Parameter, string $Data)
    {
        $Result = $this->PutParamset([$Parameter=> $Data]);
        return $Result;
    }

    public function WriteParamset(string $Paramset)
    {
        $Data = @json_decode($Paramset, true);
        if ($Data === false) {
            trigger_error('Error in Parameter', E_USER_NOTICE);
            return false;
        }
        $Result = $this->PutParamset($Data);
        return $Result;
    }
     */
    /**
     * Interne Funktion des SDK.
     */
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
                $this->getParam();
                return true;
        }

        if (isset(static::$Variables[static::DeviceTyp][$Ident][4])) {
            if (!$this->ReadPropertyBoolean('enable_' . $Ident)) {
                trigger_error('Variable is disabled in config.', E_USER_NOTICE);
                return true;
            }
        }
        if (isset(static::$Parameters[static::DeviceTyp][$Ident][4])) {
            if (!$this->ReadPropertyBoolean('enable_' . $Ident)) {
                trigger_error('Parameter is disabled in config.', E_USER_NOTICE);
                return true;
            }
        }
        return false;
    }
    public function ReceiveData($JSONString)
    {
        $Event = json_decode($JSONString, true);
        $this->SendDebug('EVENT:' . $Event['VariableName'], $Event['VariableValue'], 0);
        $this->SetVariable($Event['VariableName'], $Event['VariableValue']);
    }

    //################# PUBLIC

    protected function createValues()
    {
        $Result = $this->getParamsetDescription('VALUES');
        foreach ($Result as $Variable) {
            if ($Variable['OPERATIONS'] & 0b101) {
                $Ident = $Variable['ID'];
                $VarType = self::$VariableTypes[$Variable['TYPE']];
                $Profile = '';
                $Name = $Variable['ID'];
                $Action = '';
                if (array_key_exists($Ident, static::$Variables[static::DeviceTyp])) {
                    if (isset(static::$Variables[static::DeviceTyp][$Ident][4])) {
                        if (!$this->ReadPropertyBoolean('enable_' . $Ident)) {
                            continue;
                        }
                    }
                    $VarType = static::$Variables[static::DeviceTyp][$Ident][0];
                    if ($VarType == parent::VARIABLETYPE_NONE) {
                        continue;
                    }
                    $Profile = static::$Variables[static::DeviceTyp][$Ident][1];
                    $Action = static::$Variables[static::DeviceTyp][$Ident][2];
                    if (isset(static::$Variables[static::DeviceTyp][$Ident][3])) {
                        $Name = $this->Translate(static::$Variables[static::DeviceTyp][$Ident][3]);
                    }
                    $this->CreateProfile($Profile);
                }

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
    protected function getValues()
    {
        $Result = $this->getParamset('VALUES');
        foreach ($Result as $Ident => $Value) {
            $this->SetVariable($Ident, $Value);
        }
    }
    protected function createParam()
    {
        $Result = $this->getParamsetDescription('MASTER');
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            $Profile = '';
            $Name = $Variable['ID'];
            $Action = '';
            if (array_key_exists($Ident, static::$Parameters[static::DeviceTyp])) {
                $VarType = static::$Parameters[static::DeviceTyp][$Ident][0];
                if ($VarType == parent::VARIABLETYPE_NONE) {
                    if (isset(static::$Parameters[static::DeviceTyp][$Ident][2])) {
                        if (is_string(static::$Parameters[static::DeviceTyp][$Ident][2])) {
                            $Ident = static::$Parameters[static::DeviceTyp][$Ident][2];
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    $VarType = static::$Parameters[static::DeviceTyp][$Ident][0];
                }
                if (isset(static::$Parameters[static::DeviceTyp][$Ident][4])) {
                    if (!$this->ReadPropertyBoolean('enable_' . $Ident)) {
                        continue;
                    }
                }
                $Profile = static::$Parameters[static::DeviceTyp][$Ident][1];
                $Action = static::$Parameters[static::DeviceTyp][$Ident][2];
                if (isset(static::$Parameters[static::DeviceTyp][$Ident][3])) {
                    $Name = $this->Translate(static::$Parameters[static::DeviceTyp][$Ident][3]);
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

    protected function getParam()
    {
        $Result = $this->getParamset('MASTER');
        $this->SendDebug(__FUNCTION__, $Result, 0);
        $this->SetParamVariable($Result);
    }

    protected function CreateProfile($Profile)
    {
        if ($Profile) {
            if (substr($Profile, 0, 1) != '~') {
                if (array_key_exists($Profile, static::$VariabeProfiles)) {
                    $this->RegisterProfileEx(
                    static::$VariabeProfiles[$Profile][0],
                     $Profile,
                     static::$VariabeProfiles[$Profile][1],
                     static::$VariabeProfiles[$Profile][2],
                     static::$VariabeProfiles[$Profile][3],
                     static::$VariabeProfiles[$Profile][4],
                     static::$VariabeProfiles[$Profile][5],
                     static::$VariabeProfiles[$Profile][6],
                     static::$VariabeProfiles[$Profile][7]
                    );
                }
            }
        }
    }

    abstract protected function SetVariable(string $Ident, $Value);
    abstract protected function SetParamVariable(array $Params);

    protected function CreateWeekPlan(bool $Active)
    {
        $Event = @IPS_GetObjectIDByIdent('WEEK_PROFIL', $this->InstanceID);
        if ($Event === false) {
            if (!$Active) {
                return false;
            }
            $Event = IPS_CreateEvent(2);
            IPS_SetParent($Event, $this->InstanceID);
            IPS_SetIdent($Event, 'WEEK_PROFIL');
            IPS_SetName($Event, $this->Translate('Schedule'));
        } else {
            if (!$Active) {
                IPS_DeleteEvent($Event);
                return false;
            }
        }
        /*
        $TempMin = $this->ReadPropertyFloat('ScheduleMinTemp');
        $TempMax = $this->ReadPropertyFloat('ScheduleMaxTemp');
        $Steps = $this->ReadPropertyInteger('ScheduleStepsTemp');
        $rgb1 = $this->ReadPropertyInteger('ScheduleMinColor');
        $rgb2 = $this->ReadPropertyInteger('ScheduleMaxColor');
        $r1 = $rgb1 >> 16;
        $g1 = ($rgb1 & 0x00FF00) >> 8;
        $b1 = $rgb1 & 0x0000FF;
        $r2 = $rgb2 >> 16;
        $g2 = ($rgb2 & 0x00FF00) >> 8;
        $b2 = $rgb2 & 0x0000FF;
        $Counts = ($TempMax - $TempMin) / ($Steps / 2) + 1;
        $dr = ($r2 - $r1) / $Counts;
        $dg = ($g2 - $g1) / $Counts;
        $db = ($b2 - $b1) / $Counts;
        for ($i = 32; $i > 0; $i--) {
            @IPS_SetEventScheduleAction($Event, $i, '', 0, '');
        }
        for ($i = 0; $i < $Counts; $i++) {
            $rgb =
                (round($r1 + $dr * $i) << 16) |
                (round($g1 + $dg * $i) << 8) |
                (round($b1 + $db * $i));

            $Temp = ($TempMin + ($i * ($Steps / 2)));
            if ($Temp > $TempMax) {
                break;
            }
            IPS_SetEventScheduleAction($Event, $i + 1, sprintf('%0.1f °C', $Temp), $rgb, '');
            if ($i == 31) {
                break;
            }
        }
         */
        $ScheduleActionColors = $this->GetColorAttribute();
        $i = 0;
        foreach ($ScheduleActionColors as $Temp => $Color) {
            $i++;
            IPS_SetEventScheduleAction($Event, $i + 1, sprintf('%0.1f °C', $Temp), $Color, '');
            if ($i == 31) {
                break;
            }
        }
        for ($i = 0; $i < 7; $i++) {
            IPS_SetEventScheduleGroup($Event, $i, (1 << $i));
        }
        return $Event;
    }
    //################# PRIVATE
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
    /**
     * Liest alle Parameter des Devices aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    protected function GetParamset(string $Paramset)
    {
        return $this->SendRPC('getParamset', $Paramset);
    }

    /**
     * Liest alle Parameter des Devices aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    protected function GetParamsetDescription(string $Paramset)
    {
        return $this->SendRPC('getParamsetDescription', $Paramset);
    }

    /**
     * Liest alle Parameter des Devices aus.
     *
     * @return array Ein Array mit den Daten des Interface.
     */
    protected function PutParamset(array $Parameter)
    {
        $Result = $this->SendRPC('putParamset', 'MASTER', $Parameter, $this->ReadPropertyBoolean('EmulateStatus'));
        return ($Result) ? true : false;
    }

    protected function PutValueset($Value)
    {
        $Result = $this->SendRPC('putParamset', 'VALUES', $Value, $this->ReadPropertyBoolean('EmulateStatus'));
        return ($Result) ? true : false;
    }

    protected function PutValue(string $ValueName, $Value)
    {
        $Result = $this->SendRPC('setValue', $ValueName, $Value, $this->ReadPropertyBoolean('EmulateStatus'));
        return ($Result) ? true : false;
    }

    protected function SendRPC(string $MethodName, string $Paramset, $Data = null, bool $EmulateStatus = false)
    {
        if (!$this->HasActiveParent()) {
            trigger_error('Instance has no active Parent Instance!', E_USER_NOTICE);
            return false;
        }
        $ParentData = [
            'DataID'     => '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}',
            'Protocol'   => $this->ReadPropertyInteger('Protocol'),
            'MethodName' => $MethodName,
            'WaitTime'   => ($EmulateStatus ? 1 : 5000),
            'Data'       => [$this->ReadPropertyString('Address'), $Paramset]
        ];
        if (is_array($Data)) {
            $ParentData['Data'][] = json_encode($Data);
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
            return false;
        }
        $this->SendDebug('Receive', utf8_encode($ResultJSON), 0);
        if ($ResultJSON === '') {
            return true;
        }
        $Result = json_decode(utf8_encode($ResultJSON), true);
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }
    private function SetColorAttribute(array $Values)
    {
        ksort($Values);
        foreach ($Values as $Temp => $Color) {
            $ScheduleTemps[] = [$Temp, $Color];
        }
        $this->WriteAttributeString('ScheduleColors', json_encode($ScheduleTemps));
    }
    private function GetColorAttribute()
    {
        $Values = [];
        $ScheduleTemps = json_decode($this->ReadAttributeString('ScheduleColors'), true);
        foreach ($ScheduleTemps as $TempColor) {
            $Values[$TempColor[0]] = $TempColor[1];
        }
        ksort($Values);
        return $Values;
    }
}

/* @} */
