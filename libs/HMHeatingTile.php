<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMHeatingTile.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       4.01
 */

/**
 * Kachel der Heizungsmodule für die Kachel-Visualisierung (HTML-SDK).
 *
 * Die Kachel ist für alle Heizungsmodule dieselbe (`libs/tile`). Jedes Modul ordnet über die Konstante `TileRoles` den Rollen der Kachel
 * (Soll, Ist, Modus, Boost …) die Idents seiner Statusvariablen zu. Eine Rolle ohne Statusvariable, z.B. weil sie in der Konfiguration
 * abgewählt wurde, fehlt in den Daten, und die Kachel blendet das zugehörige Bedienelement aus und nutzt den Platz anders.
 * Bedient wird über `RequestAction` der Statusvariablen (Aktionen des Moduls), nur der Wochenplan hat eine eigene Aktion (`TileSave`).
 *
 * @property array $WeekSchedules
 * @property int $WeekProfile
 */
trait HMHeatingTileFunctions
{
    /**
     * Standard-Verlauf der Temperatur-Darstellung von Symcon (Material-Farben, ermittelt an der Symcon-Kachel): Position 0 bis 1 => Farbe (RGB).
     */
    protected const TemperatureGradient = [[0.0, 0x03A9F4], [1 / 3, 0x2196F3], [2 / 3, 0xFF5722], [1.0, 0xF44336]];

    /**
     * Aktion der Kachel: Wochenplan des aktiven Zeitprofils schreiben (JSON mit `days`).
     */
    protected const TileActionSave = 'TileSave';

    /**
     * Aktion der Kachel: Werte und Wochenplan neu zur Kachel senden.
     */
    protected const TileActionReload = 'TileReload';

    /**
     * Flags für JSON, das in eine Script-Umgebung der Kachel eingebettet wird.
     */
    private const TileJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Texte der Kachel (Schlüssel = englischer Text, übersetzt über locale.json).
     */
    private const TileTexts = [
        'Automatic', 'Manual', 'Party', 'Boost', 'Window open', 'Window', 'Window state open', 'Window state closed', 'Valve', 'Actual', 'Cooling', 'Heating',
        'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
        'Comfort', 'Lowering', 'Presets', 'Off', 'On', 'min', 'Begin', 'End', 'Temperature', 'Start party', 'End party',
        'Profile', 'Add switching point', 'Remove', 'Apply', 'Discard', 'Saving…', 'until',
        'Copy to all days', 'Copy to weekdays', 'Copy to weekend', 'Weekly program'
    ];

    /**
     * Interne Funktion des SDK.
     * Liefert die Kachel. Die Kachel bekommt die vorhandenen Rollen mit den aktuellen Werten und den Wochenplan.
     */
    public function GetVisualizationTile(): string
    {
        $Config = [
            'instance'  => $this->InstanceID,
            'boostMode' => static::TileBoostMode,
            'texts'     => $this->GetTileTexts()
        ] + $this->GetTileState();
        return strtr((string) file_get_contents(__DIR__ . '/tile/heating.html'), [
            '/*CONFIG*/null' => json_encode($Config, self::TileJsonFlags),
            '/*STYLE*/'      => (string) file_get_contents(__DIR__ . '/tile/heating.css'),
            '/*CORE*/'       => (string) file_get_contents(__DIR__ . '/tile/heating.js')
        ]);
    }

    /**
     * Meldet die Instanz als Kachel an, wenn das Modul Rollen für die Kachel kennt. Normale und maximierte Kachel über das HTML-SDK
     * gibt es erst ab Symcon 9.1, davor nur die normale Kachel (HMBase setzt die Konstante dort auf HTML).
     */
    protected function RegisterHeatingTile(): void
    {
        if (count(static::TileRoles) == 0) {
            return;
        }
        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN);
    }

    /**
     * Bedienaktionen, die nur die Kachel nutzt.
     *
     * @param string $Ident Aktion.
     * @param mixed $Value Wert der Aktion.
     *
     * @return bool true, wenn die Aktion zur Kachel gehörte.
     */
    protected function ProcessTileAction(string $Ident, mixed $Value): bool
    {
        switch ($Ident) {
            case self::TileActionSave:
                // Fehlermeldungen der Prüfung erscheinen in der Kachel
                ob_start();
                $Result = $this->SaveScheduleFromTile((string) $Value);
                $Error = trim((string) ob_get_clean());
                if (!$Result) {
                    $this->UpdateVisualizationValue(json_encode(['type' => 'error', 'text' => $Error !== '' ? $Error : $this->Translate('The weekly program could not be written.')], self::TileJsonFlags));
                }
                return true;
            case self::TileActionReload:
                $this->PushTileValues();
                $this->PushTileSchedule();
                return true;
        }
        return false;
    }

    /**
     * Sendet die aktuellen Werte der Rollen an die Kachel.
     */
    protected function PushTileValues(): void
    {
        if (count(static::TileRoles) == 0) {
            return;
        }
        $this->UpdateVisualizationValue(json_encode(['type' => 'state'] + $this->GetTileState(), self::TileJsonFlags));
    }

    /**
     * Sendet den Wochenplan an die Kachel.
     */
    protected function PushTileSchedule(): void
    {
        if (count(static::TileRoles) == 0) {
            return;
        }
        $this->UpdateVisualizationValue(json_encode(['type' => 'schedule', 'schedule' => $this->GetTileSchedule()], self::TileJsonFlags));
    }

    /**
     * Wandelt die Zeitpunkte des Wochenplans aus der Kachel in die Parameter eines Zeitprofils der CCU.
     * Jeder Eintrag eines Tages ist [Endzeit in Minuten, Temperatur]; der letzte Eintrag eines Tages gilt bis 24:00 (1440).
     * Die Endzeiten werden auf das Raster des Gerätes gerundet (ScheduleTimeStep), die Temperaturen auf 0,5 °C.
     *
     * @param array $Days Sieben Tage von Montag bis Sonntag.
     * @param int $Plan Nummer des Zeitprofils auf der CCU (beginnt bei 1).
     *
     * @return array|string [Parameter, Zeitprofil je Tag (Bit des Wochentags => Nummer => [Zeit, Temperatur])] oder der englische Text des Fehlers.
     */
    protected static function SlotsToParams(array $Days, int $Plan): array|string
    {
        if (count($Days) != 7) {
            return 'Invalid weekly program.';
        }
        $Params = [];
        $Schedule = [];
        foreach (array_values($Days) as $Index => $Slots) {
            $Bit = 1 << $Index;
            if (!is_array($Slots) || (count($Slots) < 1)) {
                return 'Invalid weekly program.';
            }
            if (count($Slots) > static::NumberOfTimeSlot) {
                return 'A day of the schedule has more than %d switching points.';
            }
            $Slots = array_values($Slots);
            $Last = 0;
            foreach ($Slots as $Number => $Slot) {
                if (!is_array($Slot) || !isset($Slot[0], $Slot[1]) || !is_numeric($Slot[0]) || !is_numeric($Slot[1])) {
                    return 'Invalid weekly program.';
                }
                $Minutes = ($Number == count($Slots) - 1) ? 1440 : (int) (round((float) $Slot[0] / static::ScheduleTimeStep) * static::ScheduleTimeStep);
                $Temperature = round((float) $Slot[1] * 2) / 2;
                if (($Minutes <= $Last) || ($Minutes > 1440)) {
                    return 'The end times of a day must be in ascending order.';
                }
                if (($Temperature < static::MinScheduleTemperature) || ($Temperature > static::MaxScheduleTemperature)) {
                    return 'The temperature of a switching point is out of range.';
                }
                $Last = $Minutes;
                $TimeIndex = sprintf(static::WeekScheduleIndexEndTime, $Number + 1, static::$Weekdays[$Bit], $Plan);
                $TempIndex = sprintf(static::WeekScheduleIndexTemp, $Number + 1, static::$Weekdays[$Bit], $Plan);
                $Params[$TimeIndex] = $Minutes;
                $Params[$TempIndex] = $Temperature;
                $Schedule[$Bit][$Number + 1] = [self::TIME => $Minutes, self::TEMP => $Temperature];
            }
        }
        return [$Params, $Schedule];
    }

    /**
     * Merkt sich die Temperaturen der Schnellwahl (Komfort, Absenkung, Fenster offen) aus dem Paramset MASTER. Die Kachel zeigt sie auch dann,
     * wenn die Statusvariable dazu in der Instanz abgewählt ist.
     *
     * @param array $Params Das Paramset MASTER der CCU.
     */
    protected function StoreTileTemperatures(array $Params): void
    {
        $Temperatures = $this->TileTemperatures;
        foreach (['comfort', 'lowering', 'comfortCooling', 'loweringCooling', 'windowTemp'] as $Role) {
            $Ident = static::TileRoles[$Role] ?? '';
            if (($Ident != '') && isset($Params[$Ident]) && is_numeric($Params[$Ident])) {
                $Temperatures[$Role] = (float) $Params[$Ident];
            }
        }
        $this->TileTemperatures = $Temperatures;
        $this->PushTileValues();
    }

    /**
     * true, wenn das Gerät auf Kühlen steht (Statusvariable Betriebsart vorhanden und auf Kühlen).
     */
    protected function GetCoolingActive(): bool
    {
        $Ident = static::TileRoles['cooling'] ?? '';
        return ($Ident != '') && ($this->FindIDForIdent($Ident) != 0) && ((int) $this->GetValue($Ident) === 1);
    }

    /**
     * Liefert die Temperatur für Komfort oder Absenkung: aus der Statusvariable, ist sie abgewählt, aus dem gemerkten Paramset MASTER.
     *
     * @param string $Role `comfort` oder `lowering`.
     * @param bool $Cooling true für die Temperatur im Kühlbetrieb.
     *
     * @return float|null Die Temperatur, null wenn sie noch nicht bekannt ist.
     */
    protected function GetPresetTemperature(string $Role, bool $Cooling): ?float
    {
        $Role .= $Cooling ? 'Cooling' : '';
        $Ident = static::TileRoles[$Role] ?? '';
        if (($Ident != '') && ($this->FindIDForIdent($Ident) != 0)) {
            return (float) $this->GetValue($Ident);
        }
        return $this->TileTemperatures[$Role] ?? null;
    }

    /**
     * Farbe (RGB) einer Temperatur nach dem Farbverlauf der Solltemperatur-Darstellung (ohne Verlauf: der Verlauf der Temperatur-Darstellung).
     * Die Wochenplan-Aktionen des Ereignisses färben sich damit wie die Kachel.
     *
     * @param float $Temperature Die Temperatur.
     *
     * @return int Die Farbe als RGB-Wert.
     */
    protected function GetTemperatureColor(float $Temperature): int
    {
        $Presentation = $this->GetTilePresentation(static::TileRoles['set'] ?? '');
        $Stops = count($Presentation['gradient']) ? $Presentation['gradient'] : static::TemperatureGradient;
        $Range = $Presentation['max'] - $Presentation['min'];
        $Position = ($Range > 0) ? min(1.0, max(0.0, ($Temperature - $Presentation['min']) / $Range)) : 0.0;
        $Count = count($Stops);
        for ($i = 1; $i < $Count; $i++) {
            if ($Position <= $Stops[$i][0] || $i == $Count - 1) {
                $Span = $Stops[$i][0] - $Stops[$i - 1][0];
                $Part = ($Span > 0) ? min(1.0, max(0.0, ($Position - $Stops[$i - 1][0]) / $Span)) : 1.0;
                $Color = 0;
                foreach ([16, 8, 0] as $Shift) {
                    $From = ($Stops[$i - 1][1] >> $Shift) & 0xFF;
                    $To = ($Stops[$i][1] >> $Shift) & 0xFF;
                    $Color |= ((int) round($From + ($To - $From) * $Part)) << $Shift;
                }
                return $Color;
            }
        }
        return (int) ($Stops[0][1] ?? 0);
    }

    /**
     * Schreibt den in der Kachel bearbeiteten Wochenplan in das aktive Zeitprofil des Gerätes.
     *
     * @param string $Json JSON mit `days`: sieben Tage (Montag bis Sonntag) mit Einträgen [Endzeit in Minuten, Temperatur].
     *
     * @return bool true, wenn die CCU den Wochenplan angenommen hat. Fehler werden per echo gemeldet.
     */
    private function SaveScheduleFromTile(string $Json): bool
    {
        $Request = json_decode($Json, true);
        if (!is_array($Request) || !isset($Request['days']) || !is_array($Request['days'])) {
            echo $this->Translate('Invalid weekly program.') . PHP_EOL;
            return false;
        }
        $Plan = static::SelectedWeekScheduleIdent ? $this->WeekProfile : 1;
        $Result = static::SlotsToParams($Request['days'], $Plan);
        if (is_string($Result)) {
            echo sprintf($this->Translate($Result), static::NumberOfTimeSlot) . PHP_EOL;
            return false;
        }
        [$Params, $Schedule] = $Result;
        if (!$this->PutParamSet($Params)) {
            return false;
        }
        $Schedules = $this->WeekSchedules;
        $Schedules[$Plan] = $Schedule;
        $this->WeekSchedules = $Schedules;
        // Auch beim aktiven Zeitprofil neu aufbauen: Die Zeiten wurden auf das Raster des Gerätes gerundet.
        $this->RefreshScheduleObject();
        return true;
    }

    /**
     * Grenzen und Sonderwerte einer Statusvariable aus ihrer Darstellung. Hat der Nutzer eine eigene Darstellung eingestellt
     * (VariableCustomPresentation), gilt sie, fehlen darin Grenzen, die Darstellung der Library.
     *
     * @param string $Ident Ident der Statusvariable.
     *
     * @return array `min`, `max`, `step` und `special` (Liste aus `min`, `max`, `text` für Werte mit festem Text, z.B. Aus/An).
     */
    private function GetTilePresentation(string $Ident): array
    {
        $Result = ['min' => 4.5, 'max' => 30.5, 'step' => 0.5, 'special' => [], 'gradient' => []];
        $VariableId = ($Ident == '') ? 0 : $this->FindIDForIdent($Ident);
        if ($VariableId == 0) {
            return $Result;
        }
        $Variable = IPS_GetVariable($VariableId);
        $Standard = $Variable['VariablePresentation'] ?? [];
        $Custom = $Variable['VariableCustomPresentation'] ?? [];
        foreach (['min' => 'MIN', 'max' => 'MAX', 'step' => 'STEP_SIZE'] as $Key => $Name) {
            $Value = $Custom[$Name] ?? $Standard[$Name] ?? null;
            if (is_numeric($Value)) {
                $Result[$Key] = (float) $Value;
            }
        }
        // Sonderwerte (Intervalle mit festem Text) gelten nach der Darstellung, die gerade wirkt
        $Source = (count($Custom) > 0) ? $Custom : $Standard;
        if (!empty($Source['INTERVALS_ACTIVE'])) {
            $Intervals = json_decode((string) ($Source['INTERVALS'] ?? '[]'), true);
            foreach (is_array($Intervals) ? $Intervals : [] as $Interval) {
                if (!empty($Interval['ConstantActive']) && isset($Interval['IntervalMinValue'], $Interval['IntervalMaxValue'])) {
                    $Result['special'][] = [
                        'min'  => (float) $Interval['IntervalMinValue'],
                        'max'  => (float) $Interval['IntervalMaxValue'],
                        'text' => (string) ($Interval['ConstantValue'] ?? '')
                    ];
                }
            }
        }
        // Farbverlauf nach der Darstellung: 0 = Standard (Akzentfarbe der Visualisierung, leere Liste), 3 = eigener Verlauf,
        // sonst der Verlauf der Temperatur-Darstellung. Position 0 = Minimum, 1 = Maximum.
        $Type = (int) ($Source['GRADIENT_TYPE'] ?? $Standard['GRADIENT_TYPE'] ?? 0);
        if ($Type != 0) {
            $Result['gradient'] = static::TemperatureGradient;
            if ($Type == 3) {
                $Stops = [];
                $List = json_decode((string) ($Source['CUSTOM_GRADIENT'] ?? '[]'), true);
                $Range = $Result['max'] - $Result['min'];
                foreach (is_array($List) ? $List : [] as $Stop) {
                    if (isset($Stop['Value'], $Stop['Color']) && ($Range > 0)) {
                        $Stops[] = [min(1.0, max(0.0, ((float) $Stop['Value'] - $Result['min']) / $Range)), ((int) $Stop['Color']) & 0xFFFFFF];
                    }
                }
                usort($Stops, fn ($a, $b) => $a[0] <=> $b[0]);
                if (count($Stops) >= 2) {
                    $Result['gradient'] = $Stops;
                }
            }
        }
        return $Result;
    }

    /**
     * Werte der vorhandenen Rollen und die Eigenschaften der Kachel.
     *
     * @return array `roles` (Rolle => Ident der Statusvariable), `actions` (Rollen mit Aktion), `values` (Rolle => Wert),
     *               `limits` (Grenzen der Solltemperatur) und `schedule`.
     */
    private function GetTileState(): array
    {
        $Roles = [];
        $Actions = [];
        $Values = [];
        foreach (static::TileRoles as $Role => $Ident) {
            $VariableId = $this->FindIDForIdent($Ident);
            if ($VariableId == 0) {
                continue;
            }
            $Roles[$Role] = $Ident;
            // Bedienbar, wenn die Variable eine Aktion hat und der Nutzer die Standardaktion nicht deaktiviert hat
            if (HasAction($VariableId) && (IPS_GetVariable($VariableId)['VariableCustomAction'] != VARIABLE_ACTION_DISABLED)) {
                $Actions[] = $Role;
            }
            $Value = $this->GetValue($Ident);
            $Values[$Role] = is_bool($Value) ? $Value : (is_numeric($Value) ? $Value * (static::TileFactors[$Role] ?? 1) : $Value);
        }
        // Grenzen der Soll-Temperatur und der Party-Temperatur nach der Darstellung der jeweiligen Variable
        $Limits = $this->GetTilePresentation(static::TileRoles['set'] ?? '');
        $PartyLimits = $this->GetTilePresentation(static::TileRoles['partyTemp'] ?? '');
        return [
            'roles'       => $Roles,
            'actions'     => $Actions,
            'values'      => $Values,
            'limits'      => $Limits,
            'partyLimits' => $PartyLimits,
            // Temperaturen der Schnellwahl ohne Statusvariable (aus dem Paramset MASTER gemerkt)
            'temps'    => (object) $this->TileTemperatures,
            // Geräte mit COMFORT_MODE/LOWERING_MODE: Komfort und Absenkung erscheinen nach diesen Variablen (Regelung), nicht nach den Temperaturen
            'presetModes' => isset(static::TileRoles['comfortMode']),
            // Geräte mit Fensterzustand (WINDOW_STATE): „Fenster offen“ schaltet den Zustand, die Temperatur dahinter ist nur die Anzeige
            'presetWindow' => isset(static::TileRoles['window']),
            'schedule'     => $this->GetTileSchedule()
        ];
    }

    /**
     * Wochenplan des aktiven Zeitprofils für die Kachel. Solange das Paramset MASTER nicht gelesen wurde, null.
     *
     * @return array|null `days` (sieben Tage von Montag bis Sonntag mit [Endzeit in Minuten, Temperatur]), `plan`, `plans`, `step`, `min`, `max`, `slots`.
     */
    private function GetTileSchedule(): ?array
    {
        // Unabhängig von der Eigenschaft „Wochenprogramm als Ereignis anlegen“: Die Instanz kennt den Wochenplan aus dem Paramset MASTER
        $Plan = static::SelectedWeekScheduleIdent ? $this->WeekProfile : 1;
        $Schedules = $this->WeekSchedules;
        if (!isset($Schedules[$Plan])) {
            return null;
        }
        $Days = [];
        foreach (array_keys(static::$Weekdays) as $Bit) {
            $Slots = [];
            foreach ($Schedules[$Plan][$Bit] ?? [] as $Slot) {
                $Slots[] = [$Slot[self::TIME], $Slot[self::TEMP]];
                if ($Slot[self::TIME] >= 1440) {
                    break;
                }
            }
            $Days[] = $Slots;
        }
        return [
            'days'  => $Days,
            'plan'  => $Plan,
            'plans' => static::NumberOfWeekSchedules,
            'step'  => static::ScheduleTimeStep,
            'min'   => static::MinScheduleTemperature,
            'max'   => static::MaxScheduleTemperature,
            'slots' => static::NumberOfTimeSlot
        ];
    }

    /**
     * Übersetzte Texte der Kachel.
     *
     * @return string[] Englischer Text => übersetzter Text.
     */
    private function GetTileTexts(): array
    {
        $Texts = [];
        foreach (self::TileTexts as $Text) {
            $Texts[$Text] = $this->Translate($Text);
        }
        return $Texts;
    }
}

/* @} */
