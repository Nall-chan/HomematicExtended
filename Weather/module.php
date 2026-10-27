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
 * @version       3.78
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticWeather ist die Klasse für das IPS-Modul 'HomeMatic Weather'.
 * Liest die Werte des Wetterkanals der HmIP-Wetterstationen und zusätzlich die Summen-, Tages- und Vortageswerte,
 * welche die CCU in versteckten Systemvariablen führt.
 * Erweitert HMBase.
 */
class HomeMaticWeather extends HMBase
{
    protected const ProtocolId = \HMExtended\CCU::ProtocolHmIP;
    protected const ValuesChannel = '';

    /**
     * Datenpunkt des Wetterkanals, über den das HM-Script die Kanal-ID der CCU ermittelt (bei allen HmIP-SWO vorhanden).
     */
    private const AnchorDatapoint = 'ACTUAL_TEMPERATURE';

    /**
     * Ereignisse dieser Datenpunkte lösen auf der CCU die Programme der Zähler aus. Danach werden die Systemvariablen gelesen.
     */
    private const TriggerDatapoints = ['ILLUMINATION', 'SUNSHINEDURATION', 'RAIN_COUNTER'];

    /**
     * Verzögerung in Millisekunden zwischen Ereignis und Lesen der Systemvariablen, damit das Programm der CCU die Zähler bereits aktualisiert hat.
     * Gelesen werden nur Tages- und Summenwerte, der genaue Zeitpunkt ist dafür ohne Bedeutung.
     */
    private const ReadDelay = 5000;

    /**
     * Datentypen der CCU, für die Statusvariablen angelegt werden. ENUM (Status-Werte) und ACTION werden nicht abgebildet.
     */
    private const HmTypeToIpsType = [
        'FLOAT'   => VARIABLETYPE_FLOAT,
        'INTEGER' => VARIABLETYPE_INTEGER,
        'BOOL'    => VARIABLETYPE_BOOLEAN,
        'STRING'  => VARIABLETYPE_STRING
    ];

    /**
     * Profile der Datenpunkte je Variablentyp (Einheiten laut CCU-Firmware: °C, %, km/h, mm, °).
     */
    private const DatapointProfiles = [
        'ACTUAL_TEMPERATURE' => [VARIABLETYPE_FLOAT => '~Temperature'],
        'HUMIDITY'           => [VARIABLETYPE_INTEGER => '~Humidity', VARIABLETYPE_FLOAT => '~Humidity.F'],
        'ILLUMINATION'       => [VARIABLETYPE_INTEGER => '~Illumination', VARIABLETYPE_FLOAT => '~Illumination.F'],
        'WIND_SPEED'         => [VARIABLETYPE_FLOAT => '~WindSpeed.kmh'],
        'WIND_DIR'           => [VARIABLETYPE_INTEGER => '~WindDirection', VARIABLETYPE_FLOAT => '~WindDirection.F'],
        'RAIN_COUNTER'       => [VARIABLETYPE_FLOAT => '~Rainfall'],
        'RAINING'            => [VARIABLETYPE_BOOLEAN => '~Raining']
    ];

    /**
     * Datenpunkte mit Einheit Minuten bzw. Grad, für die es kein passendes Standardprofil gibt (Darstellung Wertanzeige).
     */
    private const DatapointSuffix = [
        'SUNSHINEDURATION' => ' min',
        'WIND_DIR_RANGE'   => '°'
    ];

    /**
     * Systemvariablen, welche die CCU beim Anlernen einer HmIP-SWO anlegt (WebUI newdevices.htm der CCU-Firmware).
     * Name: Anfang des Namens, es folgt die ReGa-ID des Kanals und bei `WithAddress` noch `_<Kanaladresse>`.
     * Die Regen-Variablen gibt es nur bei HmIP-SWO-PL und -PR. `Today` und `Yesterday` wechseln täglich um 07:00 Uhr.
     */
    private const SystemVariables = [
        'SUNSHINEDURATION_TOTAL'     => ['Name' => 'svHmIPSunshineCounter_', 'WithAddress' => true, 'Caption' => 'Sunshine duration total', 'Suffix' => ' min'],
        'SUNSHINEDURATION_TODAY'     => ['Name' => 'svHmIPSunshineCounterToday_', 'WithAddress' => false, 'Caption' => 'Sunshine duration today', 'Suffix' => ' min'],
        'SUNSHINEDURATION_YESTERDAY' => ['Name' => 'svHmIPSunshineCounterYesterday_', 'WithAddress' => false, 'Caption' => 'Sunshine duration yesterday', 'Suffix' => ' min'],
        'RAIN_COUNTER_TOTAL'         => ['Name' => 'svHmIPRainCounter_', 'WithAddress' => true, 'Caption' => 'Rainfall total', 'Profile' => '~Rainfall'],
        'RAIN_COUNTER_TODAY'         => ['Name' => 'svHmIPRainCounterToday_', 'WithAddress' => false, 'Caption' => 'Rainfall today', 'Profile' => '~Rainfall'],
        'RAIN_COUNTER_YESTERDAY'     => ['Name' => 'svHmIPRainCounterYesterday_', 'WithAddress' => false, 'Caption' => 'Rainfall yesterday', 'Profile' => '~Rainfall']
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        // Address und Protocol müssen vorhanden sein, sonst hängt sich der Symcon-Homematic-Konfigurator auf.
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadSystemVariables, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'' . \HMExtended\Device\Timer::ReadSystemVariables . '\', true);');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, 0);
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($Address) {
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $Address . static::ValuesChannel . '".*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        if (($Address) && ($this->HasActiveParent())) {
            @$this->CreateVariablesFromValues();
            @$this->GetValuesAndSetVariable();
            @$this->ReadSystemVariables();
        }
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData(string $JSONString): string
    {
        parent::ReceiveData($JSONString);
        $Event = json_decode($JSONString, true);
        if (!isset($Event['VariableName'])) {
            return '';
        }
        if (in_array($Event['VariableName'], self::TriggerDatapoints)) {
            // Mehrere Ereignisse kurz hintereinander lösen nur ein Lesen aus.
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, self::ReadDelay);
        }
        return '';
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
        $this->ApplyChanges();
    }

    /**
     * Verarbeitet den Timer zum Lesen der Systemvariablen.
     *
     * @param string $Ident Ident der Variable bzw. Aktion.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion vollständig verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        if ($Ident == \HMExtended\Device\Timer::ReadSystemVariables) {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, 0);
            $this->ReadSystemVariables();
            $done = true;
        }
    }

    //################# PRIVATE

    /**
     * Legt für alle Datenpunkte des Kanals (Paramset VALUES) Statusvariablen an.
     */
    private function CreateVariablesFromValues(): void
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $AddressWithChannel);
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            if (!array_key_exists($Variable['TYPE'], self::HmTypeToIpsType)) {
                $this->SendDebug('Unsupported type', $Ident . ': ' . $Variable['TYPE'], 0);
                continue;
            }
            $VarType = self::HmTypeToIpsType[$Variable['TYPE']];
            $this->MaintainVariable($Ident, $Ident, $VarType, $this->GetProfileOrPresentation($Ident, $VarType, $Variable['UNIT'] ?? ''), 0, true);
            if ($Variable['OPERATIONS'] & 0b10) {
                $this->EnableAction($Ident);
            }
        }
    }

    /**
     * Liefert Profil bzw. Darstellung eines Datenpunktes.
     *
     * @param string $Ident Name des Datenpunktes.
     * @param int $VarType Typ der Statusvariable.
     * @param string $Unit Einheit laut CCU.
     *
     * @return string|array Name des Profils oder die Darstellung.
     */
    private function GetProfileOrPresentation(string $Ident, int $VarType, string $Unit): string|array
    {
        if (isset(self::DatapointProfiles[$Ident][$VarType])) {
            return self::DatapointProfiles[$Ident][$VarType];
        }
        if (array_key_exists($Ident, self::DatapointSuffix) && ($VarType != VARIABLETYPE_BOOLEAN)) {
            return $this->ValuePresentation(self::DatapointSuffix[$Ident]);
        }
        return \HMExtended\Profiles::$UnitToProfile[$Unit] ?? '';
    }

    /**
     * Darstellung Wertanzeige mit Suffix und ohne Nachkommastellen.
     *
     * @param string $Suffix Suffix der Anzeige, z.B. ` min`.
     *
     * @return array Die Darstellung.
     */
    private function ValuePresentation(string $Suffix): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => $Suffix,
            'DIGITS'       => 0
        ];
    }

    /**
     * Liest alle Systemvariablen der Wetterstation mit einem HM-Script aus der CCU.
     * Eine Statusvariable wird angelegt, sobald die Systemvariable existiert. Fehlende Systemvariablen (z.B. Regen bei der HmIP-SWO-B)
     * erzeugen nur eine Debug-Ausgabe.
     *
     * @return bool true, wenn die CCU geantwortet hat.
     */
    private function ReadSystemVariables(): bool
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Interface = \HMExtended\CCU::$Interfaces[$this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol)];
        // Die Kanal-ID wird über einen Datenpunkt ermittelt, denn der Name des Kanals kann in der CCU geändert werden.
        $HMScript = 'object oDP;' . PHP_EOL .
                'object oSV;' . PHP_EOL .
                'integer iChannel;' . PHP_EOL .
                'oDP = dom.GetObject("' . $Interface . '.' . $Address . '.' . self::AnchorDatapoint . '");' . PHP_EOL .
                'if (oDP) {' . PHP_EOL .
                'iChannel = oDP.Channel();' . PHP_EOL;
        foreach (self::SystemVariables as $Ident => $SystemVariable) {
            $Name = '"' . $SystemVariable['Name'] . '" # iChannel' . ($SystemVariable['WithAddress'] ? ' # "_' . $Address . '"' : '');
            $HMScript .= 'oSV = dom.GetObject(' . $Name . ');' . PHP_EOL .
                    'if (oSV) { ' . $Ident . ' = oSV.Value(); }' . PHP_EOL;
        }
        $HMScript .= '}' . PHP_EOL;

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        if ($xml === false) {
            return false;
        }
        if ((string) $xml->iChannel === '' || (string) $xml->iChannel === '0') {
            $this->SendDebug('Channel', 'Datapoint ' . $Interface . '.' . $Address . '.' . self::AnchorDatapoint . ' not found', 0);
            return true;
        }
        $this->SendDebug('Channel', (string) $xml->iChannel, 0);
        foreach (self::SystemVariables as $Ident => $SystemVariable) {
            $Raw = (string) $xml->{$Ident};
            if ($Raw === '') {
                $this->SendDebug($Ident, 'System variable ' . $SystemVariable['Name'] . (string) $xml->iChannel . ($SystemVariable['WithAddress'] ? '_' . $Address : '') . ' not found', 0);
                continue;
            }
            $this->SendDebug($Ident, $Raw, 0);
            $Display = isset($SystemVariable['Profile']) ? $SystemVariable['Profile'] : $this->ValuePresentation($SystemVariable['Suffix']);
            $this->MaintainVariable($Ident, $this->Translate($SystemVariable['Caption']), VARIABLETYPE_FLOAT, $Display, 0, true);
            $this->SetVariable($Ident, (float) $Raw);
        }
        return true;
    }
}

/* @} */
