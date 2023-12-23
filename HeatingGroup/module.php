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
require_once __DIR__ . '/../libs/HMHeatingDevice.php';  // HMBase Klasse

/**
 * HomeMaticHeatingGroup
 * Erweitert HMHeatingDevice Virtuelles Gerät: HM-CC-VG-1
 */
class HomeMaticHeatingGroup extends HMHeatingDevice
{
    public const DeviceTyp = \HMExtended\DeviceType::HeatingGroup;
    public const ValuesChannel = \HMExtended\Channels::First;
    public const ParamChannel = \HMExtended\Channels::Device;

    protected const NumberOfWeekSchedules = 3;
    protected const SelectedWeekScheduleIdent = \HMExtended\HeatingGroup::WEEK_PROGRAM_POINTER;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, 3);
    }

    //################# PUBLIC

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return;
        }
        if (array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
            $Ident = is_string(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][0], $Value);
            $SendValue = $Value;
            switch ($Ident) {
                case \HMExtended\HeatingGroup::PARTY_START_TIME:
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $Time = ($CalcHour * 60) + ($CalcMin > 30 ? 30 : 0);
                    $d->setTime($CalcHour, ($CalcMin > 30 ? 30 : 0), 0, 0);
                    if ($this->PutValueSet(
                        [
                            \HMExtended\HeatingGroup::PARTY_START_TIME  => $Time,
                            \HMExtended\HeatingGroup::PARTY_START_DAY   => (int) $d->format('j'),
                            \HMExtended\HeatingGroup::PARTY_START_MONTH => (int) $d->format('n'),
                            \HMExtended\HeatingGroup::PARTY_START_YEAR  => (int) $d->format('y'),
                        ]
                    )) {
                        $this->SetValue($Ident, $d->getTimestamp());
                    }
                    return;
                case \HMExtended\HeatingGroup::PARTY_STOP_TIME:
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $Time = ($CalcHour * 60) + ($CalcMin > 30 ? 30 : 0);
                    $d->setTime($CalcHour, ($CalcMin > 30 ? 30 : 0), 0, 0);
                    if ($this->PutValueSet(
                        [
                            \HMExtended\HeatingGroup::PARTY_STOP_TIME  => $Time,
                            \HMExtended\HeatingGroup::PARTY_STOP_DAY   => (int) $d->format('j'),
                            \HMExtended\HeatingGroup::PARTY_STOP_MONTH => (int) $d->format('n'),
                            \HMExtended\HeatingGroup::PARTY_STOP_YEAR  => (int) $d->format('y'),
                        ]
                    )) {
                        $this->SetValue($Ident, $d->getTimestamp());
                    }
                    return;
                case \HMExtended\HeatingGroup::PARTY_TEMPERATURE:
                    if ($this->GetValue(\HMExtended\HeatingGroup::CONTROL_MODE) != 2) {
                        $start = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroup::PARTY_START_TIME));
                        $StartTime = ((int) $start->format('H') * 60) + ((int) $start->format('i') > 30 ? 30 : 0);
                        $stop = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroup::PARTY_STOP_TIME));
                        $StopTime = ((int) $stop->format('H') * 60) + ((int) $stop->format('i') > 30 ? 30 : 0);
                        $this->PutValueSet(
                            [
                                \HMExtended\HeatingGroup::PARTY_START_TIME  => $StartTime,
                                \HMExtended\HeatingGroup::PARTY_START_DAY   => (int) $start->format('j'),
                                \HMExtended\HeatingGroup::PARTY_START_MONTH => (int) $start->format('n'),
                                \HMExtended\HeatingGroup::PARTY_START_YEAR  => (int) $start->format('y'),
                                \HMExtended\HeatingGroup::PARTY_STOP_TIME   => $StopTime,
                                \HMExtended\HeatingGroup::PARTY_STOP_DAY    => (int) $stop->format('j'),
                                \HMExtended\HeatingGroup::PARTY_STOP_MONTH  => (int) $stop->format('n'),
                                \HMExtended\HeatingGroup::PARTY_STOP_YEAR   => (int) $stop->format('y')
                            ]
                        );
                    }
                    break;
                case \HMExtended\HeatingGroup::SET_TEMPERATURE:
                    $Mode = $this->GetValue(\HMExtended\HeatingGroup::CONTROL_MODE);
                    switch ($Mode) {
                        case 0:
                            if (!$this->ReadPropertyBoolean(\HMExtended\Device\Property::SetPointBehavior)) {
                                break;
                            }
                            // SetPoint change from Auto to Manually
                            // No break. Add additional comment above this line if intentional
                        case 3: //Abort Boost, change to Manually
                            $Ident = \HMExtended\HeatingGroup::MANU_MODE;
                            break;
                        case 2:
                            $Ident = \HMExtended\HeatingGroup::PARTY_TEMPERATURE;
                            break;
                    }
                    break;
                case \HMExtended\HeatingGroup::CONTROL_MODE:
                    switch ($Value) {
                        case 0:
                            $Ident = \HMExtended\HeatingGroup::AUTO_MODE;
                            $SendValue = true;
                            break;
                        case 1:
                            $Ident = \HMExtended\HeatingGroup::MANU_MODE;
                            $SendValue = (float) $this->GetValue(\HMExtended\HeatingGroup::SET_TEMPERATURE);
                            break;
                        case 2:
                            if (!$this->ReadPropertyBoolean('enable_PARTY')) {
                                trigger_error('Party is disabled in config.', E_USER_NOTICE);
                                return;
                            }
                            if ($this->GetValue(\HMExtended\HeatingGroup::CONTROL_MODE) == 2) {
                                return;
                            }
                            $Value = (float) $this->GetValue(\HMExtended\HeatingGroup::SET_TEMPERATURE);
                            $start = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroup::PARTY_START_TIME));
                            $StartTime = ((int) $start->format('H') * 60) + ((int) $start->format('i') > 30 ? 30 : 0);
                            $stop = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroup::PARTY_STOP_TIME));
                            $StopTime = ((int) $stop->format('H') * 60) + ((int) $stop->format('i') > 30 ? 30 : 0);
                            $this->PutValueSet(
                                [
                                    \HMExtended\HeatingGroup::PARTY_START_TIME  => $StartTime,
                                    \HMExtended\HeatingGroup::PARTY_START_DAY   => (int) $start->format('j'),
                                    \HMExtended\HeatingGroup::PARTY_START_MONTH => (int) $start->format('n'),
                                    \HMExtended\HeatingGroup::PARTY_START_YEAR  => (int) $start->format('y'),
                                    \HMExtended\HeatingGroup::PARTY_STOP_TIME   => $StopTime,
                                    \HMExtended\HeatingGroup::PARTY_STOP_DAY    => (int) $stop->format('j'),
                                    \HMExtended\HeatingGroup::PARTY_STOP_MONTH  => (int) $stop->format('n'),
                                    \HMExtended\HeatingGroup::PARTY_STOP_YEAR   => (int) $stop->format('y'),
                                    \HMExtended\HeatingGroup::PARTY_TEMPERATURE => $Value
                                ]
                            );
                            return;
                        case 3:
                            $Ident = \HMExtended\HeatingGroup::BOOST_MODE;
                            $SendValue = true;
                            break;
                    }
                    break;
                case \HMExtended\HeatingGroup::COMFORT_MODE:
                case \HMExtended\HeatingGroup::LOWERING_MODE:
                    $SendValue = true;
                    break;
            }
            $this->PutValue($Ident, $SendValue);
            return;
        }
        if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
            $Ident = is_string(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][0], $Value);
            $SendValue = $Value;
            switch ($Ident) {
                case \HMExtended\HeatingGroup::DECALCIFICATION_TIME:
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $SendValue = ($CalcHour * 60) + ($CalcMin > 30 ? 30 : 0);
                    $Value = ($d->setTime($CalcHour, ($CalcMin > 30 ? 30 : 0), 0, 0))->getTimestamp();
                    break;
                case \HMExtended\HeatingGroup::BOOST_TIME_PERIOD:
                    $SendValue = intdiv((int) $Value, 5);
                    break;
                case \HMExtended\HeatingGroup::WEEK_PROGRAM_POINTER:
                    $SendValue--;
                    break;
            }
            if ($this->PutParamSet([$Ident=>$SendValue], true)) {
                $this->SetValue($Ident, $Value);
            }
            if ($Ident == \HMExtended\HeatingGroup::WEEK_PROGRAM_POINTER) {
                $this->RefreshScheduleObject();
            }
            return;
        }
        trigger_error($this->Translate('Invalid Ident.') . ' (' . $Ident . ')', E_USER_NOTICE);
        return;
    }

    protected function SetParamVariables(array $Params)
    {
        $d = new DateTime();
        $d->setTime(
            intdiv((int) $Params[\HMExtended\HeatingGroup::DECALCIFICATION_TIME], 60),
            ((int) $Params[\HMExtended\HeatingGroup::DECALCIFICATION_TIME] % 60),
            0,
            0
        );
        $Params[\HMExtended\HeatingGroup::DECALCIFICATION_TIME] = $d->getTimestamp();
        $Params[\HMExtended\HeatingGroup::BOOST_TIME_PERIOD] =
        $Params[\HMExtended\HeatingGroup::BOOST_TIME_PERIOD] * 5;
        $Params[\HMExtended\HeatingGroup::WEEK_PROGRAM_POINTER]++;

        parent::SetParamVariables($Params);
    }

    protected function SetVariable(string $Ident, $Value)
    {
        if ($this->ReadPropertyBoolean('enable_PARTY')) {
            switch ($Ident) {
                case \HMExtended\HeatingGroup::PARTY_START_DAY:
                case \HMExtended\HeatingGroup::PARTY_STOP_DAY:
                    $Ident = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2];
                    $d = (new DateTime())->setTimestamp((int) $this->GetValue($Ident));
                    $d->setDate((int) $d->format('Y'), (int) $d->format('n'), (int) $Value);
                    $Value = $d->getTimestamp();
                    break;
                case \HMExtended\HeatingGroup::PARTY_START_MONTH:
                case \HMExtended\HeatingGroup::PARTY_STOP_MONTH:
                    $Ident = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2];
                    $d = (new DateTime())->setTimestamp((int) $this->GetValue($Ident));
                    $d->setDate((int) $d->format('Y'), (int) $Value, (int) $d->format('j'));
                    $Value = $d->getTimestamp();
                    break;
                case \HMExtended\HeatingGroup::PARTY_START_YEAR:
                case \HMExtended\HeatingGroup::PARTY_STOP_YEAR:
                    $Ident = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2];
                    $d = (new DateTime())->setTimestamp((int) $this->GetValue($Ident));
                    $d->setDate(2000 + (int) $Value, (int) $d->format('n'), (int) $d->format('j'));
                    $Value = $d->getTimestamp();
                    if ($Value < 946767600) {
                        $d = new DateTime();
                        $d->setTime((int) $d->format('H'), ((int) $d->format('i') > 30 ? 30 : 0), 0, 0);
                        $Value = $d->getTimestamp();
                    }
                    break;
                case \HMExtended\HeatingGroup::PARTY_START_TIME:
                case \HMExtended\HeatingGroup::PARTY_STOP_TIME:
                    $d = (new DateTime())->setTimestamp((int) $this->GetValue($Ident));
                    $d->setTime(intdiv((int) $Value, 60), ((int) $Value % 60), 0, 0);
                    $Value = $d->getTimestamp();
                    break;
            }
        }
        switch ($Ident) {
            case \HMExtended\HeatingGroup::COMFORT_MODE:
            case \HMExtended\HeatingGroup::LOWERING_MODE:
                $Value = 0;
                break;
        }
        parent::SetVariable($Ident, $Value);
    }
    //################# PRIVATE
}

/* @} */