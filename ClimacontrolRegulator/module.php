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
 * ParaInterface ist die Klasse für das IPS-Modul 'HomeMatic Paraset Interface'.
 * Erweitert HMBase.
 *
 */
class HomeMaticClimateControlRegulator extends HMDeviceBase
{
    const DeviceTyp = 'CLIMATECONTROL_REGULATOR';

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('EmulateStatus', false);
        $this->RegisterPropertyString('Address', '');
        $this->RegisterPropertyInteger('Protocol', 0);
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
        if (array_key_exists($Ident, static::$Variables[static::DeviceTyp])) {
            $Ident = is_string(static::$Variables[static::DeviceTyp][$Ident][2]) ? static::$Variables[self::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(static::$Variables[static::DeviceTyp][$Ident][0], $Value);
            switch ($Ident) {
                case 'SETPOINT':
                    $this->SetValue($Ident, $Value);
                if ($Value == 4.5) {
                    $Value = 0;
                }
                if ($Value == 30.5) {
                    $Value = 100;
                }
            }
            return $this->SendRPC('setValue', $Ident, $Value, true);
        }
        if (array_key_exists($Ident, static::$Parameters[static::DeviceTyp])) {
            $Ident = is_string(static::$Parameters[static::DeviceTyp][$Ident][2]) ? static::$Variables[self::DeviceTyp][$Ident][2] : $Ident;
            $this->FixValueType(static::$Parameters[static::DeviceTyp][$Ident][0], $Value);
            switch ($Ident) {
                case 'DECALCIFICATION_TIME':
                    $d = (new DateTime())->setTimestamp($Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    if ($this->PutParamset(
                        [
                            'DECALCIFICATION_MINUTE'=>($CalcMin > 50) ? 50 : $CalcMin,
                            'DECALCIFICATION_HOUR'=>$CalcHour
                        ]
                    )) {
                        $this->SetValue($Ident, $Value);
                        return true;
                    }
                    return false;
                    case 'PARTY_END_TIME':
                        if ($Value < time()) {
                            trigger_error($this->Translate('Time cannot be in the past'));
                            return false;
                        }

                        $d = (new DateTime())->setTimestamp($Value);
                        $CalcMin = (int) $d->format('i');
                        $CalcHour = (int) $d->format('H');
                        $d->setTime(0, 0, 0, 0);
                        $days = ((new DateTime())->setTime(0, 0, 0, 0))->diff($d);
                        if ($days > 200) {
                            trigger_error($this->Translate('Time too far in the future'));
                            return false;
                        }
                        $d->setTime($CalcHour, ($CalcMin >= 30) ? 1 : 0, 0, 0);

                        if ($this->PutParamset(
                            [
                                'MODE_TEMPERATUR_REGULATOR'=> 3,
                                'PARTY_END_DAY'            => $days->format('%a'),
                                'PARTY_END_MINUTE'         => ($CalcMin >= 30) ? 1 : 0,
                                'PARTY_END_HOUR'           => $CalcHour
                            ]
                        )) {
                            $this->SetValue('MODE_TEMPERATUR_REGULATOR', 3);
                            $this->SetValue('PARTY_END_TIME', $d->getTimestamp());
                            return true;
                        }

            }
            if ($this->PutParamset([$Ident=>$Value])) {
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
        $d->setTime($Params['DECALCIFICATION_HOUR'], $Params['DECALCIFICATION_MINUTE'], 0, 0);
        $Params['DECALCIFICATION_TIME'] = $d->getTimestamp();
        $d = new DateTime();
        $d->setTime($Params['PARTY_END_HOUR'], ($Params['PARTY_END_MINUTE'] == 0 ? 0 : 30), 0, 0);
        $i = new DateInterval('P' . $Params['PARTY_END_DAY'] . 'D');
        $Params['PARTY_END_TIME'] = $d->add($i)->getTimestamp();
        foreach ($Params as $Ident => $Value) {
            @$this->SetValue($Ident, $Value);
        }
    }
    protected function SetVariable(string $Ident, $Value)
    {
        switch ($Ident) {
            case 'SETPOINT':
                if ($Value == 0) {
                    $Value = 4.5;
                }
                if ($Value == 100) {
                    $Value = 30.5;
                }
                @$this->SetValue($Ident, $Value);
                IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"getParam",0);');
                break;
            default:
                @$this->SetValue($Ident, $Value);
            break;
        }
    }
    //################# PRIVATE
}

/* @} */
