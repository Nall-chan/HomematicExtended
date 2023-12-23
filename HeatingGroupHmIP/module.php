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
 * HomeMaticIPHeatingGroup
 * Erweitert HMHeatingDevice Virtuelles Gerät: HmIP-HEATING
 */
class HomeMaticIPHeatingGroup extends HMHeatingDevice
{
    protected const DeviceTyp = \HMExtended\DeviceType::HeatingGroupHmIP;
    protected const ValuesChannel = \HMExtended\Channels::First;
    protected const ParamChannel = \HMExtended\Channels::First;

    protected const NumberOfWeekSchedules = 6;
    protected const SelectedWeekScheduleIdent = \HMExtended\HeatingGroupHmIP::ACTIVE_PROFILE;

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
            switch ($Ident) { // Sonderfall Party Variablen
                case \HMExtended\HeatingGroupHmIP::PARTY_TIME_START:
                case \HMExtended\HeatingGroupHmIP::PARTY_TIME_END:
                    $Time = (new DateTime())->setTimestamp((int) $Value);
                    $SendValue = $Time->format('Y_m_d H:i');
                    break;
                case \HMExtended\HeatingGroupHmIP::PARTY_SET_POINT_TEMPERATURE:
                    if ($this->GetValue(\HMExtended\HeatingGroupHmIP::SET_POINT_MODE) == 2) {
                        $Ident = \HMExtended\HeatingGroupHmIP::SET_POINT_TEMPERATURE;
                    } else {
                        $Start = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroupHmIP::PARTY_TIME_START));
                        $End = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroupHmIP::PARTY_TIME_END));
                        $this->PutValueSet(
                            [
                                \HMExtended\HeatingGroupHmIP::PARTY_TIME_START => $Start->format('Y_m_d H:i'),
                                \HMExtended\HeatingGroupHmIP::PARTY_TIME_END   => $End->format('Y_m_d H:i')
                            ]
                        );
                        $Ident = \HMExtended\HeatingGroupHmIP::SET_POINT_TEMPERATURE;
                    }
                    break;
                case \HMExtended\HeatingGroupHmIP::SET_POINT_TEMPERATURE:
                    if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::SetPointBehavior)) {
                        if ($this->GetValue(\HMExtended\HeatingGroupHmIP::SET_POINT_MODE) == 0) {
                            $this->PutValue(\HMExtended\HeatingGroupHmIP::CONTROL_MODE, 1);
                        }
                    }
                    break;
                case \HMExtended\HeatingGroupHmIP::CONTROL_MODE:
                    switch ($Value) {
                        case 2: // Sonderfall Mode Party
                            if (!$this->ReadPropertyBoolean('enable_PARTY')) {
                                trigger_error('Party is disabled in config.', E_USER_NOTICE);
                                return;
                            }
                            $Start = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroupHmIP::PARTY_TIME_START));
                            $End = (new DateTime())->setTimestamp((int) $this->GetValue(\HMExtended\HeatingGroupHmIP::PARTY_TIME_END));
                            $this->PutValueSet(
                                [
                                    \HMExtended\HeatingGroupHmIP::PARTY_TIME_START => $Start->format('Y_m_d H:i'),
                                    \HMExtended\HeatingGroupHmIP::PARTY_TIME_END   => $End->format('Y_m_d H:i')
                                ]
                            );
                            break;
                    }
            }
            $this->PutValue($Ident, $SendValue);
            return;
        }
        if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
            $Ident = is_string(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][0], $Value);
            $SendValue = $Value;
            switch ($Ident) { // Sonderfall Entkalkung
                case \HMExtended\HeatingGroupHmIP::DECALCIFICATION_TIME:
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $SendValue = ($CalcHour * 2) + ($CalcMin > 30 ? 1 : 0);
                    break;
            }
            if ($this->PutParamSet([$Ident=>$SendValue])) {
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
            intdiv((int) $Params[\HMExtended\HeatingGroupHmIP::DECALCIFICATION_TIME], 2),
            (((int) $Params[\HMExtended\HeatingGroupHmIP::DECALCIFICATION_TIME] % 2) == 1 ? 30 : 0),
            0,
            0
        );
        $Params[\HMExtended\HeatingGroupHmIP::DECALCIFICATION_TIME] = $d->getTimestamp();

        parent::SetParamVariables($Params);
    }

    protected function SetVariable(string $Ident, $Value)
    {
        switch ($Ident) {
            case \HMExtended\HeatingGroupHmIP::ACTIVE_PROFILE:
                $OldValue = $this->GetValue($Ident);
                break;
            case \HMExtended\HeatingGroupHmIP::PARTY_TIME_START:
            case \HMExtended\HeatingGroupHmIP::PARTY_TIME_END:
                if ($Value == '1999_11_30 00:00') {
                    $Value = time();
                } else {
                    $d = DateTime::createFromFormat('Y_m_d H:i', $Value);
                    $Value = ($d->getTimestamp() < 7200) ? time() : $d->getTimestamp();
                }
                break;
        }
        parent::SetVariable($Ident, $Value);
        if ($Ident == \HMExtended\HeatingGroupHmIP::ACTIVE_PROFILE) {
            if ($OldValue != $Value) {
                $this->RefreshScheduleObject();
            }
        }
    }
    //################# PRIVATE
}

/* @} */