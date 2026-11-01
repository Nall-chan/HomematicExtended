<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.90
 */
require_once __DIR__ . '/../libs/HMAllChannels.php';  // HMAllChannels Klasse

/**
 * HomeMaticRGBW ist die Klasse für das IPS-Modul 'HomeMatic RGBW'.
 * Bildet den Funk-RGBW-Controller HM-LC-RGBW-WM mit den drei Kanälen Helligkeit (1), Farbe (2) und Automatik (3) in einer Instanz ab.
 * Erweitert HMAllChannels.
 *
 * Das Gerät hat keinen Ein/Aus-Datenpunkt: Kanal 1 (`LEVEL`) schaltet und dimmt, Kanal 2 (`COLOR`) ist nur der Farbton
 * (0 bis 199 entspricht 0 bis 360 Grad, 200 ist Weiß) und schaltet nicht ein, Kanal 3 startet Programme (`PROGRAM`).
 * Ein Farbbefehl beendet ein laufendes Programm. Der HSV-Wert der Farbe hat den Farbton in Grad, Sättigung und Helligkeit in Prozent (0 bis 100),
 * so zeigt ihn die Licht-Kachel richtig an. Für die Licht-Kachel von Symcon legt das Modul zusätzlich einen Status (Boolean)
 * und die Farbe als HSV-String an.
 *
 * @property array $LastTimes Zuletzt an das Gerät gesendete Rampenzeit und Einschaltdauer des Kanals 1 (leer, wenn unbekannt).
 */
class HomeMaticRGBW extends HMAllChannels
{
    protected const ProtocolId = \HMExtended\CCU::ProtocolBidCosRF;
    protected const DefaultChannelCount = 3;

    protected const Captions = parent::Captions + [
        'LEVEL'           => 'Brightness',
        'COLOR'           => 'Hue',
        'PROGRAM'         => 'Program',
        'WORKING'         => 'Working',
        'DIRECTION'       => 'Direction',
        'INHIBIT'         => 'Inhibit'
    ];

    /**
     * Datenpunkte ohne Statusvariable: Das Gerät hat keine Batterie, die ACT_-Datenpunkte (Sollwerte der Programme) werden nicht verwendet.
     */
    protected const IgnoredDatapoints = [...parent::IgnoredDatapoints, 'LOWBAT', 'ACT_BRIGHTNESS', 'ACT_MIN_BOARDER', 'ACT_MAX_BOARDER'];

    /**
     * Idents der Variablen, die das Modul zusätzlich zu den Datenpunkten anlegt, und der Datenpunkte mit besonderer Behandlung.
     */
    private const IdentState = 'STATE';
    private const IdentHsvColor = 'COLOR_HSV';
    private const IdentLevel = 'LEVEL_1';
    private const IdentColor = 'COLOR_2';
    private const IdentProgram = 'PROGRAM_3';
    private const IdentRampTime = 'RAMP_TIME_1';
    private const IdentOnTime = 'ON_TIME_1';

    /**
     * Kanäle des Gerätes.
     */
    private const ChannelDimmer = 1;
    private const ChannelColor = 2;
    private const ChannelProgram = 3;

    /**
     * Größter Farbton des Farbkreises (entspricht 360 Grad), 200 ist Weiß.
     */
    private const MaxHue = 199;
    private const White = 200;

    /**
     * Ab dieser Sättigung (Prozent) wird eine gewählte Farbe als Farbton gesendet, darunter als Weiß.
     */
    private const WhiteSaturationLimit = 50;

    /**
     * Zeiten in Sekunden (laut Beschreibung des Gerätes).
     */
    private const MaxTime = 85825945.6;
    private const DefaultRampTime = 0.5;

    /**
     * Programme des Kanals 3 (Werte der CCU-Oberfläche).
     */
    private const Programs = [
        0 => 'Off',
        1 => 'Slow color change',
        2 => 'Normal color change',
        3 => 'Fast color change',
        4 => 'Campfire',
        5 => 'Waterfall',
        6 => 'TV simulation'
    ];

    /**
     * Datenpunkte des Weißabgleichs im Paramset MASTER des Kanals 2 (Prozent).
     */
    private const WhiteAdjustment = [
        'Red'   => 'WHITE_ADJUSTMENT_VALUE_RED',
        'Green' => 'WHITE_ADJUSTMENT_VALUE_GREEN',
        'Blue'  => 'WHITE_ADJUSTMENT_VALUE_BLUE'
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->LastTimes = [];
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->LastTimes = [];
        if (count($this->Datapoints)) {
            $this->MaintainExtraVariables();
            $this->UpdateState();
            $this->UpdateHsvColor();
        }
    }

    /**
     * Interne Funktion des SDK.
     * Verarbeitet die Ereignisse und leitet Status und Farbe daraus ab.
     */
    public function ReceiveData(string $JSONString): string
    {
        parent::ReceiveData($JSONString);
        $Event = json_decode($JSONString, true);
        switch (is_array($Event) ? ($Event['VariableName'] ?? '') : '') {
            case 'LEVEL':
                $this->UpdateState();
                break;
            case 'COLOR':
                $this->UpdateHsvColor();
                break;
        }
        return '';
    }

    /**
     * IPS-Instanz-Funktion 'HM_SetLevel'.
     * Setzt die Helligkeit (Kanal 1) mit Rampenzeit und Einschaltdauer. Der Streifen schaltet nach der Einschaltdauer selbst aus.
     *
     * @param float $Level Helligkeit von 0 (aus) bis 1 (100 %).
     * @param float $RampTime Rampenzeit in Sekunden (0.5 ist der Standard des Gerätes).
     * @param float $OnTime Einschaltdauer in Sekunden, 0 = unbegrenzt.
     *
     * @return bool true, wenn die CCU die Aufträge angenommen hat.
     */
    public function SetLevel(float $Level, float $RampTime, float $OnTime): bool
    {
        if (($Level < 0) || ($Level > 1)) {
            echo $this->Translate('The brightness must be between 0 and 1.') . PHP_EOL;
            return false;
        }
        if (($RampTime < 0) || ($RampTime > self::MaxTime) || ($OnTime < 0) || ($OnTime > self::MaxTime)) {
            echo $this->Translate('The time must be between 0 and 85825945.6 seconds.') . PHP_EOL;
            return false;
        }
        if (!$this->SendTimes($RampTime, $OnTime)) {
            return false;
        }
        if (!$this->WriteDatapoint(self::ChannelDimmer, 'LEVEL', $Level)) {
            return false;
        }
        $this->SetValueIfExists(self::IdentLevel, $Level);
        $this->UpdateState();
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'HM_SetColor'.
     * Setzt die Farbe (Kanal 2). Ein laufendes Programm wird dabei beendet. Die Helligkeit bleibt unverändert, ein ausgeschalteter Streifen schaltet nicht ein.
     *
     * @param int $Hue Farbton von 0 bis 360 Grad.
     * @param int $Saturation Sättigung von 0 bis 100 %, 0 ist Weiß.
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    public function SetColor(int $Hue, int $Saturation): bool
    {
        if (($Hue < 0) || ($Hue > 360) || ($Saturation < 0) || ($Saturation > 100)) {
            echo $this->Translate('The hue must be between 0 and 360 and the saturation between 0 and 100.') . PHP_EOL;
            return false;
        }
        return $this->WriteColor(self::HsvToColor((float) $Hue, (float) $Saturation));
    }

    /**
     * IPS-Instanz-Funktion 'HM_ReadWhiteBalance'.
     * Liest den Weißabgleich der RGB-Ausgänge (Paramset MASTER des Kanals 2).
     *
     * @return string|false JSON-Objekt mit Red, Green und Blue in Prozent, bei einem Fehler false.
     */
    public function ReadWhiteBalance(): string|false
    {
        $Master = $this->SendRPC('getParamset', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), [$this->ChannelAddress(self::ChannelColor), \HMExtended\CCU::MASTER]);
        if ($Master === null) {
            return false;
        }
        $Result = [];
        foreach (self::WhiteAdjustment as $Name => $Datapoint) {
            if (!is_array($Master) || !isset($Master[$Datapoint])) {
                echo $this->Translate('The device did not return the white balance.') . PHP_EOL;
                return false;
            }
            $Result[$Name] = (int) $Master[$Datapoint];
        }
        return json_encode($Result);
    }

    /**
     * IPS-Instanz-Funktion 'HM_SetWhiteBalance'.
     * Stellt den Weißabgleich der RGB-Ausgänge ein (Paramset MASTER des Kanals 2, wie die drei Trimmer am Gerät).
     *
     * @param int $Red Rot in Prozent (0 bis 100).
     * @param int $Green Grün in Prozent (0 bis 100).
     * @param int $Blue Blau in Prozent (0 bis 100).
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    public function SetWhiteBalance(int $Red, int $Green, int $Blue): bool
    {
        foreach ([$Red, $Green, $Blue] as $Value) {
            if (($Value < 0) || ($Value > 100)) {
                echo $this->Translate('The white balance values must be between 0 and 100.') . PHP_EOL;
                return false;
            }
        }
        $Master = [
            self::WhiteAdjustment['Red']   => $Red,
            self::WhiteAdjustment['Green'] => $Green,
            self::WhiteAdjustment['Blue']  => $Blue
        ];
        return $this->SendRPC('putParamset', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), [$this->ChannelAddress(self::ChannelColor), \HMExtended\CCU::MASTER], $Master) !== null;
    }

    //################# protected

    /**
     * Verarbeitet die Bedienung einer Statusvariable.
     *
     * @param string $Ident Ident der Variable.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion vollständig verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        switch ($Ident) {
            case self::IdentState:
                $this->SwitchState((bool) $Value);
                $done = true;
                return;
            case self::IdentHsvColor:
                $this->SetHsvColor((string) $Value);
                $done = true;
                return;
            case self::IdentRampTime:
            case self::IdentOnTime:
                // Vorgabe für den nächsten Schaltbefehl, wird erst mit diesem gesendet
                $this->SetValue($Ident, (float) $Value);
                $done = true;
                return;
            case self::IdentLevel:
                if (!$this->SendTimes()) {
                    $done = true;
                    return;
                }
                break;
            case self::IdentColor:
                $this->WriteColor((int) $Value);
                $done = true;
                return;
        }
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done && ($Ident == self::IdentLevel)) {
            $this->UpdateState();
        }
    }

    /**
     * Darstellung der Datenpunkte: Helligkeit als Schieberegler (Verwendung Intensität), Farbton als Schieberegler mit Farbverlauf,
     * Programm als Aufzählung, Zeiten als Eingabe in Sekunden.
     */
    protected function Presentation(string $Datapoint, string $Type, bool $HasAction, array $Parameter): array
    {
        switch ($Datapoint) {
            case 'LEVEL':
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                    'ICON'         => 'Intensity',
                    'MIN'          => 0,
                    'MAX'          => 1,
                    'STEP_SIZE'    => 0.01,
                    'DIGITS'       => 0,
                    'PERCENTAGE'   => true,
                    'USAGE_TYPE'   => 2
                ];
            case 'COLOR':
                return [
                    'PRESENTATION'    => VARIABLE_PRESENTATION_SLIDER,
                    'MIN'             => 0,
                    'MAX'             => self::White,
                    'STEP_SIZE'       => 1,
                    'DIGITS'          => 0,
                    'GRADIENT_TYPE'   => 3,
                    'CUSTOM_GRADIENT' => json_encode(self::HueGradient()),
                    'USAGE_TYPE'      => 5
                ];
            case 'PROGRAM':
                $Options = [];
                foreach (self::Programs as $Value => $Caption) {
                    $Options[] = ['Value' => $Value, 'Caption' => $this->Translate($Caption), 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
                }
                return ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($Options)];
            case 'ON_TIME':
            case 'RAMP_TIME':
                return $this->TimePresentation();
        }
        return parent::Presentation($Datapoint, $Type, $HasAction, $Parameter);
    }

    /**
     * Name der Statusvariable: Die Sperre gehört zum Kanal (Helligkeit, Farbe, Programm), die Zeiten des Kanals 3 zu den Programmen.
     */
    protected function VariableName(int $Channel, string $Datapoint): string
    {
        if ($Datapoint == 'INHIBIT') {
            $Name = [self::ChannelDimmer => 'Inhibit brightness', self::ChannelColor => 'Inhibit color', self::ChannelProgram => 'Inhibit program'][$Channel] ?? null;
            if ($Name !== null) {
                return $this->Translate($Name);
            }
        }
        if ($Channel == self::ChannelProgram) {
            if ($Datapoint == 'RAMP_TIME') {
                return $this->Translate('Ramp program');
            }
            if ($Datapoint == 'ON_TIME') {
                return $this->Translate('On time program');
            }
        }
        return parent::VariableName($Channel, $Datapoint);
    }

    //################# private

    /**
     * Farbverlauf des Farbkreises für den Schieberegler: Werte 0 bis 199 sind Farbtöne, 200 ist Weiß.
     */
    private static function HueGradient(): array
    {
        $Stops = [[0, 0xFF0000], [33, 0xFFFF00], [66, 0x00FF00], [100, 0x00FFFF], [133, 0x0000FF], [166, 0xFF00FF], [self::MaxHue, 0xFF0000], [self::White, 0xFFFFFF]];
        return array_map(fn (array $Stop): array => ['Value' => $Stop[0], 'Color' => $Stop[1]], $Stops);
    }

    /**
     * Rechnet Farbton und Sättigung in den Wert des Datenpunktes COLOR um (0 bis 199, Weiß 200).
     *
     * @param float $Hue Farbton in Grad (0 bis 360).
     * @param float $Saturation Sättigung in Prozent (0 bis 100).
     */
    private static function HsvToColor(float $Hue, float $Saturation): int
    {
        if ($Saturation < self::WhiteSaturationLimit) {
            return self::White;
        }
        return (int) min(self::MaxHue, max(0, round(fmod($Hue, 360) / 360 * self::MaxHue)));
    }

    /**
     * Rechnet den Wert des Datenpunktes COLOR in Farbton (Grad) und Sättigung (Prozent) um.
     *
     * @return float[] Farbton und Sättigung.
     */
    private static function ColorToHsv(int $Color): array
    {
        if ($Color >= self::White) {
            return [0.0, 0.0];
        }
        return [round(max(0, $Color) / self::MaxHue * 360), 100.0];
    }

    private function TimePresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
            'SUFFIX'       => ' s'
        ];
    }

    /**
     * Adresse eines Kanals des Gerätes.
     */
    private function ChannelAddress(int $Channel): string
    {
        return $this->ReadPropertyString(\HMExtended\Device\Property::Address) . ':' . $Channel;
    }

    /**
     * Setzt eine Statusvariable, wenn es sie gibt.
     */
    private function SetValueIfExists(string $Ident, mixed $Value): void
    {
        if ($this->FindIDForIdent($Ident) > 0) {
            $this->SetValue($Ident, $Value);
        }
    }

    /**
     * Legt die Variablen an, die kein Datenpunkt sind: Status und Farbe (HSV) für die Licht-Kachel und die Vorgaben für Rampenzeit und
     * Einschaltdauer des Kanals 1 (nur schreibbare Datenpunkte, das Gerät liefert sie nicht zurück).
     */
    private function MaintainExtraVariables(): void
    {
        $this->MaintainPresentationVariable(self::IdentState, $this->Translate('State'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH, 'USAGE_TYPE' => 0], 1000);
        $this->EnableAction(self::IdentState);
        $this->MaintainPresentationVariable(self::IdentHsvColor, $this->Translate('Color'), VARIABLETYPE_STRING, ['PRESENTATION' => VARIABLE_PRESENTATION_COLOR, 'ENCODING' => COLOR_ENCODING_HSV], 1001);
        $this->EnableAction(self::IdentHsvColor);
        foreach ([self::IdentRampTime => ['Ramp brightness', self::DefaultRampTime], self::IdentOnTime => ['On time brightness', 0.0]] as $Ident => [$Name, $Default]) {
            $New = $this->FindIDForIdent($Ident) == 0;
            $this->MaintainPresentationVariable($Ident, $this->Translate($Name), VARIABLETYPE_FLOAT, $this->TimePresentation(), 1002);
            $this->EnableAction($Ident);
            if ($New) {
                $this->SetValue($Ident, $Default);
            }
        }
    }

    /**
     * Leitet den Status (an, wenn die Helligkeit größer 0 ist) aus der Helligkeit ab.
     */
    private function UpdateState(): void
    {
        $Level = $this->FindIDForIdent(self::IdentLevel);
        if ($Level > 0) {
            $this->SetValueIfExists(self::IdentState, GetValueFloat($Level) > 0);
        }
    }

    /**
     * Leitet die Farbe (HSV) aus dem Farbton ab. Helligkeit (v) ist immer 1, die Helligkeit stellt Kanal 1 ein.
     */
    private function UpdateHsvColor(): void
    {
        $Color = $this->FindIDForIdent(self::IdentColor);
        if ($Color > 0) {
            [$Hue, $Saturation] = self::ColorToHsv((int) GetValueInteger($Color));
            $this->SetValueIfExists(self::IdentHsvColor, json_encode(['h' => $Hue, 's' => $Saturation, 'v' => 100.0]));
        }
    }

    /**
     * Schaltet ein (vorherige Helligkeit des Gerätes, OLD_LEVEL) oder aus.
     */
    private function SwitchState(bool $State): void
    {
        if (!$this->SendTimes()) {
            return;
        }
        if ($State) {
            if (!$this->WriteDatapoint(self::ChannelDimmer, 'OLD_LEVEL', true)) {
                return;
            }
        } else {
            if (!$this->WriteDatapoint(self::ChannelDimmer, 'LEVEL', 0.0)) {
                return;
            }
            $this->SetValueIfExists(self::IdentLevel, 0.0);
        }
        $this->SetValue(self::IdentState, $State);
    }

    /**
     * Setzt die Farbe aus dem HSV-String der Variable (Farbwähler).
     */
    private function SetHsvColor(string $Value): void
    {
        $Hsv = json_decode($Value, true);
        if (!is_array($Hsv) || !isset($Hsv['h'], $Hsv['s']) || !is_numeric($Hsv['h']) || !is_numeric($Hsv['s'])) {
            echo $this->Translate('Invalid color.') . PHP_EOL;
            return;
        }
        $this->WriteColor(self::HsvToColor((float) $Hsv['h'], (float) $Hsv['s']));
    }

    /**
     * Schreibt den Farbton (Kanal 2). Das Gerät beendet dabei ein laufendes Programm.
     *
     * @param int $Color Wert des Datenpunktes COLOR (0 bis 200).
     */
    private function WriteColor(int $Color): bool
    {
        if (($Color < 0) || ($Color > self::White)) {
            echo $this->Translate('Invalid color.') . PHP_EOL;
            return false;
        }
        if (!$this->WriteDatapoint(self::ChannelColor, 'COLOR', $Color)) {
            return false;
        }
        $this->SetValueIfExists(self::IdentColor, $Color);
        $this->SetValueIfExists(self::IdentProgram, 0);
        $this->UpdateHsvColor();
        return true;
    }

    /**
     * Sendet Rampenzeit und Einschaltdauer des Kanals 1 vor dem Schaltbefehl. Gleiche Werte werden nicht erneut gesendet,
     * außer nach einer Einschaltdauer (das Gerät verbraucht sie mit dem Befehl).
     *
     * @param float|null $RampTime Rampenzeit, ohne Angabe gilt die Vorgabe der Variable.
     * @param float|null $OnTime Einschaltdauer, ohne Angabe gilt die Vorgabe der Variable.
     *
     * @return bool true, wenn die Zeiten beim Gerät gesetzt sind.
     */
    private function SendTimes(?float $RampTime = null, ?float $OnTime = null): bool
    {
        $RampTime ??= $this->PresetTime(self::IdentRampTime, self::DefaultRampTime);
        $OnTime ??= $this->PresetTime(self::IdentOnTime, 0.0);
        $Times = [$RampTime, $OnTime];
        if (($this->LastTimes === $Times) && ($OnTime == 0.0)) {
            return true;
        }
        $this->LastTimes = [];
        if (!$this->WriteDatapoint(self::ChannelDimmer, 'RAMP_TIME', $RampTime) || !$this->WriteDatapoint(self::ChannelDimmer, 'ON_TIME', $OnTime)) {
            return false;
        }
        $this->LastTimes = $Times;
        return true;
    }

    /**
     * Wert einer Vorgabe-Variable.
     */
    private function PresetTime(string $Ident, float $Default): float
    {
        $Variable = $this->FindIDForIdent($Ident);
        return $Variable > 0 ? (float) GetValueFloat($Variable) : $Default;
    }
}

/* @} */
