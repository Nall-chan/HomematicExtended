<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMWeeklyProgram.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.83
 */

namespace HMExtended
{
    /**
     * Wandelt das Wochenprogramm (Paramset MASTER) von Kanälen des Typs `*_WEEK_PROFILE` der HomeMatic IP Aktoren
     * in Schaltzeitpunkte um und zurück (ohne Abhängigkeit zu Symcon).
     *
     * Das Paramset enthält bis zu 75 Einträge (`NN_WP_<Feld>`, NN = 01 bis 75). Ein Eintrag ist belegt, wenn `WEEKDAY` ungleich 0 ist.
     * Belegte Einträge stehen lückenlos ab 01. Die Felder hängen vom Kanaltyp ab (Schalter: Dauer, Dimmer: zusätzlich Rampenzeit usw.).
     *
     * Belegt an einem HmIP-Schaltaktor (Vergleich mit der CCU-Oberfläche, 2026-11-01):
     * - WEEKDAY: Bitmaske, Bit 0 = Sonntag, 1 = Montag, 2 = Dienstag, 3 = Mittwoch, 4 = Donnerstag, 5 = Freitag, 6 = Samstag.
     * - TARGET_CHANNELS: Bitmaske, ein Bit je Zielkanal (Bit 0 = erster Schaltkanal des Gerätes).
     * - CONDITION: Index der Bedingung (siehe self::Conditions).
     * - Dauer: DURATION_BASE (Index 0 bis 7) mal DURATION_FACTOR, "dauerhaft" = Basis 7 und Faktor 31.
     * Offen: Bedeutung von LEVEL 1.01 (Maximalwert des Wertebereichs).
     */
    class WeeklyProgram
    {
        public const EntryCount = 75;
        public const Weekday = 'WEEKDAY';
        public const Condition = 'CONDITION';
        public const FixedHour = 'FIXED_HOUR';
        public const FixedMinute = 'FIXED_MINUTE';
        public const AstroType = 'ASTRO_TYPE';
        public const AstroOffset = 'ASTRO_OFFSET';
        public const Level = 'LEVEL';
        public const DurationBase = 'DURATION_BASE';
        public const DurationFactor = 'DURATION_FACTOR';
        public const TargetChannels = 'TARGET_CHANNELS';

        /**
         * Feldname (ohne Präfix NN_WP_) => [Minimum, Maximum, Fließkomma]. Felder, die nur bei einzelnen Kanaltypen vorkommen
         * (RAMP_TIME_*, LEVEL_2, OUTPUT_BEHAVIOUR, HUE_*), werden unverändert durchgereicht und nicht geprüft.
         */
        public const Fields = [
            self::Weekday         => [0, 127, false],
            self::Condition       => [0, 7, false],
            self::FixedHour       => [0, 23, false],
            self::FixedMinute     => [0, 59, false],
            self::AstroType       => [0, 1, false],
            self::AstroOffset     => [-128, 127, false],
            self::Level           => [0.0, 1.01, true],
            self::DurationBase    => [0, 7, false],
            self::DurationFactor  => [0, 31, false],
            self::TargetChannels  => [0, 16777215, false]
        ];

        /**
         * Felder mit Fließkommawerten, die nicht in self::Fields stehen (je nach Kanaltyp vorhanden).
         */
        public const OtherFloatFields = ['LEVEL_2'];

        /**
         * Bedingungen (Index = Wert von CONDITION, die CCU-Oberfläche zeigt Index + 1).
         */
        public const Conditions = [
            'FIXED',
            'ASTRO',
            'FIXED_IF_BEFORE_ASTRO',
            'ASTRO_IF_BEFORE_FIXED',
            'FIXED_IF_AFTER_ASTRO',
            'ASTRO_IF_AFTER_FIXED',
            'EARLIEST_OF_FIXED_AND_ASTRO',
            'LATEST_OF_FIXED_AND_ASTRO'
        ];

        public const AstroTypes = ['SUNRISE', 'SUNSET'];

        /**
         * Modi des Wochenprogramms für `WEEK_PROGRAM_TARGET_CHANNEL_LOCK`.
         */
        public const Modes = ['MANU_MODE', 'AUTO_MODE_WITH_RESET', 'AUTO_MODE_WITHOUT_RESET'];

        /**
         * Basis der Dauer in Sekunden, Index = Wert von DURATION_BASE.
         */
        public const DurationBases = [0.1, 1.0, 5.0, 10.0, 60.0, 300.0, 600.0, 3600.0];

        /**
         * Wochentage in der Reihenfolge der Bits (Bit 0 = Sonntag).
         */
        public const Weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        /**
         * Zerlegt das Paramset MASTER in Schaltzeitpunkte.
         *
         * @param array $Master Das Paramset MASTER (Name => Wert), andere Datenpunkte werden ignoriert.
         *
         * @return array[] Eintrag (1 bis 75) => Felder (Name ohne Präfix => Wert), nur belegte Einträge.
         */
        public static function Decode(array $Master): array
        {
            $Entries = [];
            foreach ($Master as $Name => $Value) {
                if (preg_match('/^(\d{2})_WP_(.+)$/', (string) $Name, $Matches) !== 1) {
                    continue;
                }
                $Entries[(int) $Matches[1]][$Matches[2]] = $Value;
            }
            ksort($Entries);
            return array_filter($Entries, fn (array $Entry): bool => ((int) ($Entry[self::Weekday] ?? 0)) != 0);
        }

        /**
         * Prüft die Schaltzeitpunkte und erzeugt das Paramset zum Schreiben. Alle Einträge ab dem letzten belegten bis
         * $PreviousCount werden geleert, die Einträge stehen immer lückenlos ab 01.
         *
         * @param array[] $Entries Schaltzeitpunkte (Felder ohne Präfix), Reihenfolge entspricht der Nummer ab 01.
         * @param int $PreviousCount Anzahl der bisher belegten Einträge (diese werden bei Bedarf geleert).
         * @param int $MaxEntries Anzahl der Einträge des Gerätes.
         * @param array $Info Beschreibung der Felder des Gerätes (siehe self::ParseDescription), prüft und typisiert Felder, die nicht in self::Fields stehen.
         *
         * @return array|string Das Paramset (Name => Wert), bei ungültigen Werten der Fehlertext (englisch).
         */
        public static function Encode(array $Entries, int $PreviousCount = 0, int $MaxEntries = self::EntryCount, array $Info = []): array|string
        {
            $Entries = array_values($Entries);
            if (count($Entries) > $MaxEntries) {
                return 'Too many weekly program entries.';
            }
            $Set = [];
            foreach ($Entries as $Index => $Entry) {
                if (!is_array($Entry)) {
                    return 'Invalid weekly program entry.';
                }
                $Error = self::Validate($Entry, $Info);
                if ($Error !== '') {
                    return $Error;
                }
                foreach (self::EntryToParams($Index + 1, $Entry, $Info) as $Name => $Value) {
                    $Set[$Name] = $Value;
                }
            }
            // Nicht mehr benötigte Einträge leeren (alle Felder des gelöschten Eintrags auf 0).
            for ($Index = count($Entries); $Index < min($PreviousCount, $MaxEntries); $Index++) {
                foreach (array_keys(self::Fields) as $Field) {
                    $Set[self::ParamName($Index + 1, $Field)] = self::Fields[$Field][2] ? 0.0 : 0;
                }
            }
            return $Set;
        }

        /**
         * Prüft einen Schaltzeitpunkt.
         *
         * @param array $Entry Felder ohne Präfix.
         * @param array $Info Beschreibung der Felder des Gerätes (siehe self::ParseDescription).
         *
         * @return string Leer, wenn alles gültig ist, sonst der Fehlertext (englisch).
         */
        public static function Validate(array $Entry, array $Info = []): string
        {
            if (((int) ($Entry[self::Weekday] ?? 0)) == 0) {
                return 'Weekly program entry without weekday.';
            }
            foreach (self::Fields as $Field => [$Min, $Max, $IsFloat]) {
                if (!array_key_exists($Field, $Entry)) {
                    continue;
                }
                if (!is_numeric($Entry[$Field])) {
                    return 'Invalid value in weekly program entry.';
                }
                $Value = $IsFloat ? (float) $Entry[$Field] : (int) $Entry[$Field];
                if (($Value < $Min) || ($Value > $Max)) {
                    return 'Value out of range in weekly program entry.';
                }
            }
            // Felder, die nur einzelne Kanaltypen kennen, werden nach der Beschreibung des Gerätes geprüft
            foreach ($Entry as $Field => $Value) {
                if (!is_string($Field) || array_key_exists($Field, self::Fields) || !isset($Info[$Field])) {
                    continue;
                }
                if (!is_numeric($Value)) {
                    return 'Invalid value in weekly program entry.';
                }
                [$Min, $Max] = self::InfoRange($Info[$Field]);
                if ((($Min !== null) && ($Value < $Min)) || (($Max !== null) && ($Value > $Max))) {
                    return 'Value out of range in weekly program entry.';
                }
            }
            return '';
        }

        /**
         * Wertebereich eines Feldes nach der Beschreibung des Gerätes.
         *
         * @param array $Info Eintrag aus self::ParseDescription.
         *
         * @return array [Minimum, Maximum], null wenn die Beschreibung keinen Bereich nennt.
         */
        public static function InfoRange(array $Info): array
        {
            if (($Info['Type'] ?? '') == 'ENUM') {
                $Count = count($Info['List'] ?? []);
                return $Count > 0 ? [0, $Count - 1] : [null, null];
            }
            return [
                is_numeric($Info['Min'] ?? null) ? $Info['Min'] + 0 : null,
                is_numeric($Info['Max'] ?? null) ? $Info['Max'] + 0 : null
            ];
        }

        /**
         * Reduziert die Beschreibung des Paramsets MASTER auf die Felder eines Schaltzeitpunktes (Eintrag 01).
         *
         * @param array $Description Ergebnis von getParamsetDescription (Name => Beschreibung).
         *
         * @return array Feld (ohne Präfix) => [Type, Min, Max, List (Namen der Werte bei ENUM), Default, Unit].
         */
        public static function ParseDescription(array $Description): array
        {
            $Info = [];
            foreach ($Description as $Name => $Parameter) {
                if (!is_array($Parameter) || (preg_match('/^01_WP_(.+)$/', (string) $Name, $Matches) !== 1)) {
                    continue;
                }
                $Info[$Matches[1]] = [
                    'Type'    => (string) ($Parameter['TYPE'] ?? ''),
                    'Min'     => $Parameter['MIN'] ?? null,
                    'Max'     => $Parameter['MAX'] ?? null,
                    'List'    => array_values((array) ($Parameter['VALUE_LIST'] ?? [])),
                    'Default' => $Parameter['DEFAULT'] ?? null,
                    'Unit'    => (string) ($Parameter['UNIT'] ?? '')
                ];
            }
            return $Info;
        }

        /**
         * Name des Datenpunktes im Paramset.
         */
        public static function ParamName(int $Index, string $Field): string
        {
            return sprintf('%02d_WP_%s', $Index, $Field);
        }

        /**
         * Datenpunkte eines Schaltzeitpunktes mit den Datentypen, welche die CCU erwartet (Ganzzahl bzw. Fließkomma).
         */
        public static function EntryToParams(int $Index, array $Entry, array $Info = []): array
        {
            $Params = [];
            foreach ($Entry as $Field => $Value) {
                if (!is_string($Field)) {
                    continue;
                }
                $IsFloat = (self::Fields[$Field][2] ?? false) || in_array($Field, self::OtherFloatFields, true) || (($Info[$Field]['Type'] ?? '') == 'FLOAT');
                $Params[self::ParamName($Index, $Field)] = $IsFloat ? (float) $Value : (int) $Value;
            }
            return $Params;
        }

        /**
         * Wochentage aus der Bitmaske.
         *
         * @return string[] Englische Namen der Tage in der Reihenfolge Sonntag bis Samstag.
         */
        public static function WeekdaysFromMask(int $Mask): array
        {
            $Days = [];
            foreach (self::Weekdays as $Bit => $Day) {
                if ($Mask & (1 << $Bit)) {
                    $Days[] = $Day;
                }
            }
            return $Days;
        }

        /**
         * Bitmaske der Wochentage.
         *
         * @param string[] $Days Englische Namen der Tage.
         */
        public static function WeekdaysToMask(array $Days): int
        {
            $Mask = 0;
            foreach ($Days as $Day) {
                $Bit = array_search($Day, self::Weekdays, true);
                if ($Bit !== false) {
                    $Mask |= 1 << $Bit;
                }
            }
            return $Mask;
        }

        /**
         * Dauer eines Schaltzeitpunktes in Sekunden.
         *
         * @return float|null Die Dauer, null bei dauerhaft (Basis 7 und Faktor 31).
         */
        public static function DurationSeconds(int $Base, int $Factor): ?float
        {
            if (($Base == 7) && ($Factor == 31)) {
                return null;
            }
            return (self::DurationBases[$Base] ?? 0.0) * $Factor;
        }

        /**
         * Modus der Schaltkanäle aus `WEEK_PROGRAM_CHANNEL_LOCKS`. Je Kanal gehören 3 Bit zum Wert, Bit 0 der Gruppe gesetzt
         * bedeutet manueller Modus (Belegt am HmIP-Schaltaktor: Kanal 1/2/3 = 1/8/64, Ruhewert 2, Auto = Bit gelöscht).
         *
         * @param int $Locks Der Wert von WEEK_PROGRAM_CHANNEL_LOCKS.
         * @param int $ChannelCount Anzahl der Schaltkanäle.
         *
         * @return bool[] Kanal (0 = erster Schaltkanal) => true bei manuellem Modus.
         */
        public static function ManualChannels(int $Locks, int $ChannelCount): array
        {
            $Result = [];
            for ($Channel = 0; $Channel < $ChannelCount; $Channel++) {
                $Result[$Channel] = ($Locks & (1 << (3 * $Channel))) != 0;
            }
            return $Result;
        }

        /**
         * Wert von `WEEK_PROGRAM_TARGET_CHANNEL_LOCKS` für eine Kanalauswahl (3 Bit je Kanal).
         *
         * @param int $ChannelMask Bitmaske der Kanäle, Bit 0 = erster Schaltkanal (wie TARGET_CHANNELS).
         */
        public static function LockMask(int $ChannelMask): int
        {
            $Mask = 0;
            for ($Channel = 0; $Channel < 8; $Channel++) {
                if ($ChannelMask & (1 << $Channel)) {
                    $Mask |= 1 << (3 * $Channel);
                }
            }
            return $Mask;
        }

        /**
         * Zielkanäle aus der Bitmaske.
         *
         * @return int[] Nummern der Bits (0 = erster Schaltkanal).
         */
        public static function ChannelsFromMask(int $Mask): array
        {
            $Channels = [];
            for ($Bit = 0; $Bit < 24; $Bit++) {
                if ($Mask & (1 << $Bit)) {
                    $Channels[] = $Bit;
                }
            }
            return $Channels;
        }
    }
}

/* @} */
