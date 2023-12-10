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
require_once __DIR__ . '/../libs/HMDeviceBase.php';  // HMBase Klasse

/**
 * HomeMaticHeatingGroup
 * Erweitert HMDeviceBase Virtuelles Gerät: HM-CC-VG-1
 */
class HomeMaticHeatingGroup extends HMDeviceBase
{
    public const DeviceTyp = \HMExtended\DeviceType::HeatingGroup;
    public const ValuesChannel = \HMExtended\Channels::First;
    public const ParamChannel = \HMExtended\Channels::Device;
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('EmulateStatus', false);
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyInteger('Protocol', 3);
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
            switch (\HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][0]) {
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
            switch ($Ident) {
                case 'PARTY_TIME_START':
                case 'PARTY_TIME_END':
                    $this->SetValue($Ident, $Value);
                    return true;
                case 'PARTY_SET_POINT_TEMPERATURE':
                    $this->SetValue($Ident, $Value);
                    return true;
                case 'CONTROL_MODE':
                    switch ($Value) {
                        case 2:

                            $Start = (new DateTime())->setTimestamp((int) $this->GetValue('PARTY_TIME_START'));
                            $End = (new DateTime())->setTimestamp((int) $this->GetValue('PARTY_TIME_END'));

                            return $this->PutValueSet(
                                [
                                    'SET_POINT_MODE'        => 2,
                                    'SET_POINT_TEMPERATURE' => $this->GetValue('PARTY_SET_POINT_TEMPERATURE'),
                                    'PARTY_TIME_START'      => $Start->format('Y_m_d H:i'),
                                    'PARTY_TIME_END'        => $End->format('Y_m_d H:i')
                                ]
                            );
                    }
                    break;
            }
            return $this->PutValue($Ident, $Value);
        }
        if (array_key_exists($Ident, \HMExtended\ParamSet::$Variables[static::DeviceTyp])) {
            $Ident = is_string(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][2]) ? \HMExtended\ValuesSet::$Variables[static::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(\HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident][0], $Value);
            switch ($Ident) {
                case 'DECALCIFICATION_TIME':
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $Value = ($CalcHour * 2) + ($CalcMin > 30 ? 1 : 0);
                    if ($this->PutParamSet([$Ident=>(int) $Value])) {
                        $this->SetValue($Ident, $Value);
                        return true;
                    }
                    return false;
            }
            if ($this->PutParamSet([$Ident=>$Value])) {
                $this->SetValue($Ident, $Value);
                return true;
            }
        }
        trigger_error('Invalid Ident.', E_USER_NOTICE);
        return false;
    }
    protected function SetParamVariable(array $Params)
    {
        $d = new DateTime();
        $d->setTime(intdiv($Params['DECALCIFICATION_TIME'], 2), ($Params['DECALCIFICATION_TIME'] % 2) == 1 ? 30 : 0, 0, 0);
        $Params['DECALCIFICATION_TIME'] = $d->getTimestamp();

        foreach ($Params as $Ident => $Value) {
            @$this->SetValue($Ident, $Value);
        }
    }

    protected function SetVariable(string $Ident, $Value)
    {
        switch ($Ident) {
            case 'PARTY_TIME_START':
            case 'PARTY_TIME_END':
                $d = DateTime::createFromFormat('Y_m_d H:i', $Value);
                @$this->SetValue($Ident, $d->getTimestamp());
                break;
            default:
                @$this->SetValue($Ident, $Value);
                break;
        }
    }
    //################# PRIVATE
}

/* @} */