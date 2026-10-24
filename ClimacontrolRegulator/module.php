<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.75
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
    protected const ProtocolId = 0;

    //################# PUBLIC

    /**
     * Interne Funktion des SDK.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        if (array_key_exists($Ident, \HMExtended\ValuesSet::$Variables[static::DeviceTyp])) {
            $ValueDefinition = \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident];
            $Ident = is_string($ValueDefinition[2] ?? null) ? $ValueDefinition[2] : $Ident;
            $this->FixValueType($ValueDefinition[0], $Value);
            $SendValue = $Value;
            switch ($Ident) {
                case \HMExtended\ClimacontrolRegulator::SETPOINT:
                    if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::SetPointBehavior)) {
                        if ($this->GetValue(\HMExtended\ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR) != 0) {
                            $this->PutParamSet([\HMExtended\ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR => 0], true);
                        }
                    }
                    break;
            }
            $this->PutValue($Ident, $SendValue, true);
            return;
        }
        if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
            $ParamDefinition = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident];
            $Ident = is_string($ParamDefinition[2] ?? null) ? $ParamDefinition[2] : $Ident;
            $this->FixValueType($ParamDefinition[0], $Value);
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
                        trigger_error($this->Translate('Time cannot be in the past'), E_USER_NOTICE);
                        return;
                    }
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $d->setTime(0, 0, 0, 0);
                    $days = ((new DateTime())->setTime(0, 0, 0, 0))->diff($d);
                    if ($days->days > 200) {
                        trigger_error($this->Translate('Time too far in the future'), E_USER_NOTICE);
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

    /**
     * Berechnet aus Tag, Stunde und Minute die Zeitpunkte für Party und Entkalkung und schreibt die Parameter in die Statusvariablen.
     *
     * @param array $Params Das Paramset MASTER der CCU.
     */
    protected function SetParamVariables(array $Params): void
    {
        $d = new DateTime();
        $d->setTime(
            (int) $Params[\HMExtended\ClimacontrolRegulator::DECALCIFICATION_HOUR],
            (int) $Params[\HMExtended\ClimacontrolRegulator::DECALCIFICATION_MINUTE],
            0,
            0
        );
        $Params[\HMExtended\ClimacontrolRegulator::DECALCIFICATION_TIME] = $d->getTimestamp();
        $d = new DateTime();
        $d->setTime(
            (int) $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_HOUR],
            ((int) $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_MINUTE] == 0 ? 0 : 30),
            0,
            0
        );
        $i = new DateInterval('P' . $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_DAY] . 'D');
        $Params[\HMExtended\ClimacontrolRegulator::PARTY_END_TIME] = $d->add($i)->getTimestamp();
        parent::SetParamVariables($Params);
    }
}

/* @} */
