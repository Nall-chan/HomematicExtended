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
require_once __DIR__ . '/../libs/HMHeatingDevice.php';  // HMBase Klasse

/**
 * HomeMaticIPHeatingGroup
 * Erweitert HMHeatingDevice Virtuelles Gerät: HmIP-HEATING
 *
 */
class HomeMaticIPHeatingGroup extends HMHeatingDevice
{
    protected const DeviceTyp = \HMExtended\DeviceType::HeatingGroupHmIP;
    protected const ValuesChannel = \HMExtended\Channels::First;
    protected const ParamChannel = \HMExtended\Channels::First;

    protected const NumberOfWeekSchedules = 6;
    protected const SelectedWeekScheduleIdent = \HMExtended\HeatingGroupHmIP::ACTIVE_PROFILE;
    protected const ProtocolId = 3;

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
                            if (!$this->ReadPropertyBoolean(self::EnablePartyProperty)) {
                                echo $this->Translate('Party is disabled in config.') . PHP_EOL;
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
            $ParamDefinition = \HMExtended\ParamSet::$Variables[static::DeviceTyp][$Ident];
            $Ident = is_string($ParamDefinition[2] ?? null) ? $ParamDefinition[2] : $Ident;
            $this->FixValueType($ParamDefinition[0], $Value);
            $SendValue = $Value;
            switch ($Ident) { // Sonderfall Entkalkung
                case \HMExtended\HeatingGroupHmIP::DECALCIFICATION_TIME:
                    $d = (new DateTime())->setTimestamp((int) $Value);
                    $CalcMin = (int) $d->format('i');
                    $CalcHour = (int) $d->format('H');
                    $SendValue = ($CalcHour * 2) + ($CalcMin >= 30 ? 1 : 0);
                    break;
            }
            if ($this->PutParamSet([$Ident=>$SendValue])) {
                $this->SetValue($Ident, $Value);
            }
            return;
        }
        echo $this->Translate('Invalid Ident.') . PHP_EOL;
        return;
    }

    /**
     * Wandelt die Entkalkungszeit (halbe Stunden seit 00:00) in einen Zeitstempel.
     *
     * @param array $Params Das Paramset MASTER der CCU.
     */
    protected function SetParamVariables(array $Params): void
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

    /**
     * Wandelt die Party-Zeiten (Format `Y_m_d H:i`) in Zeitstempel. Nie gesetzte Zeiten werden zur aktuellen Zeit:
     * Die CCU liefert dann den kleinsten Wert des Datenpunktes (`2000_01_01 00:00`, ältere Firmware `1999_11_30 00:00`).
     *
     * @param string $Ident Ident der Statusvariable.
     * @param mixed $Value Der Wert der CCU.
     */
    protected function SetVariable(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case \HMExtended\HeatingGroupHmIP::PARTY_TIME_START:
            case \HMExtended\HeatingGroupHmIP::PARTY_TIME_END:
                $d = DateTime::createFromFormat('Y_m_d H:i', (string) $Value);
                $Value = (($d === false) || ((int) $d->format('Y') <= 2000)) ? time() : $d->getTimestamp();
                break;
        }
        parent::SetVariable($Ident, $Value);
    }
    //################# PRIVATE
}

/* @} */