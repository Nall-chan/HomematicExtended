<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.70
 */
require_once __DIR__ . '/../libs/HMHeatingDevice.php';  // HMBase Klasse

/**
 * HomeMaticClimateControlRegulator
 * Erweitert HMHeatingDevice. Gerät: HM-CC-TC
 */
class HomeMaticClimateControlRegulator extends HMHeatingDevice
{
    public const DeviceTyp = \HMExtended\DeviceType::ClimacontrolRegulator;
    public const ValuesChannel = \HMExtended\Channels::Second;
    public const ParamChannel = \HMExtended\Channels::Second;

    protected const WeekScheduleIndexTemp = 'TEMPERATUR_%2$s_%1$d';
    protected const WeekScheduleIndexEndTime = 'TIMEOUT_%2$s_%1$d';
    protected const NumberOfTimeSlot = 24;
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, 0);
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
                case \HMExtended\ClimacontrolRegulator::SETPOINT:
                    if ($this->GetValue(\HMExtended\ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR) != 0) {
                        $this->PutParamSet([\HMExtended\ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR => 0], true);
                    }
                    break;
            }
            $this->PutValue($Ident, $SendValue, true);
            return;
        }
        if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
            $Ident = is_string(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][0], $Value);
            $SendValue = $Value;
            switch ($Ident) {
                case \HMExtended\ClimacontrolRegulator::DECALCIFICATION_TIME: // Sonderfall Entkalkung
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    if ($this->PutParamSet(
                        [
                            \HMExtended\ClimacontrolRegulator::DECALCIFICATION_MINUTE => ($CalcMin > 50) ? 50 : $CalcMin,
                            \HMExtended\ClimacontrolRegulator::DECALCIFICATION_HOUR   => $CalcHour
                        ],
                        true
                    )) {
                        $this->SetValue($Ident, $Value);
                    }
                    return;
                case \HMExtended\ClimacontrolRegulator::PARTY_END_TIME: // Sonderfall Party Variablen
                    if ($Value < time()) {
                        trigger_error($this->Translate('Time cannot be in the past'));
                        return;
                    }
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $d->setTime(0, 0, 0, 0);
                    $days = ((new DateTime())->setTime(0, 0, 0, 0))->diff($d);
                    if ($days->days > 200) {
                        trigger_error($this->Translate('Time too far in the future'));
                        return;
                    }
                    $d->setTime($CalcHour, ($CalcMin >= 30) ? 30 : 0, 0, 0);
                    if ($this->PutParamSet(
                        [
                            \HMExtended\ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR => 3,
                            \HMExtended\ClimacontrolRegulator::PARTY_END_DAY             => $days->format('%a'),
                            \HMExtended\ClimacontrolRegulator::PARTY_END_MINUTE          => ($CalcMin >= 30) ? 1 : 0,
                            \HMExtended\ClimacontrolRegulator::PARTY_END_HOUR            => $CalcHour
                        ],
                        true
                    )) {
                        $this->SetValue(\HMExtended\ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR, 3);
                        $this->SetValue(\HMExtended\ClimacontrolRegulator::PARTY_END_TIME, $d->getTimestamp());
                    }
                    return;
            }
            if ($this->PutParamSet([$Ident => $SendValue], true)) {
                $this->SetValue($Ident, $Value);
            }
            return;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return;
    }

    protected function SetParamVariables(array $Params)
    {
        $d = new DateTime();
        $d->setTime(
            $Params[\HMExtended\ClimacontrolRegulator::DECALCIFICATION_HOUR],
            $Params[\HMExtended\ClimacontrolRegulator::DECALCIFICATION_MINUTE],
            0,
            0
        );
        $Params[\HMExtended\ClimacontrolRegulator::DECALCIFICATION_TIME] = $d->getTimestamp();
        $d = new DateTime();
        $d->setTime(
            $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_HOUR],
            ($Params[\HMExtended\ClimacontrolRegulator::PARTY_END_MINUTE] == 0 ? 0 : 30),
            0,
            0
        );
        $i = new DateInterval('P' . $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_DAY] . 'D');
        $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_TIME] = $d->add($i)->getTimestamp();
        parent::SetParamVariables($Params);
    }

    protected function SetVariable(string $Ident, $Value)
    {
        parent::SetVariable($Ident, $Value);
        switch ($Ident) {
            case \HMExtended\ClimacontrolRegulator::SETPOINT:
                IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"getParam",0);');
                break;
        }
    }
    //################# PRIVATE
}

/* @} */
