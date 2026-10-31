<?php

declare(strict_types=1);

namespace HMExtended
{
    class GUID
    {
        public const Powermeter = '{400F9193-FE79-4086-8D76-958BF9C1B357}';
        public const Weather = '{0877F1DB-2277-4B74-BCD9-E10D81015C31}';
        public const Systemvariablen = '{AF50C42B-7183-4992-B04A-FAFB07BB1B90}';
        public const Alarmvariable = '{72A9F016-A988-430D-B678-E787CC106449}';
        public const Programme = '{A5010577-C443-4A85-ABF2-3F2D6CDD2465}';
        public const Dis_WM55 = '{271BCAB1-0658-46D9-A164-985AEB641B48}';
        public const Dis_EP_WM55 = '{E64ED916-FA6C-45B2-B8E3-EDC3191BC4C0}';
        public const RemoteScript = '{246EDB89-70BC-403B-A1FA-3B3B1B540401}';
        public const ExtendedConfigurator = '{7B8F83C6-E3BC-AC8C-3FFC-D81CAC96D93B}';
        public const RF_Interface = '{36549B96-FA11-4651-8662-F310EEEC5C7D}';
        public const RF_Interface_Splitter = '{6EE35B5B-9DD9-4B23-89F6-37589134852F}';
        public const WR_Interface = '{01C66202-7E94-49C4-8D8F-6A75CE944E87}';
        public const HeatingGroup = '{F179857C-DF5A-2CED-F553-CDB4D42815ED}';
        public const HeatingDevice = '{E2674369-5272-44FE-905A-DCBF0E5126C6}';
        public const HeatingGroupHmIP = '{05CD9BAE-5A3B-E10B-79D6-48CB45A02C6A}';
        public const ClimacontrolRegulator = '{AA29D32D-A00D-EC8F-4987-5EB071F77011}';
        public const SendRpcToIO = '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}';
        public const SendScriptToIO = '{F4D2A45B-D513-3507-871B-36F01309D885}';
        public const SendToRFInterfaceDevice = '{E2966A08-BCE1-4E76-8C4B-7E0136244E1B}';
        public const SendToRFSplitter = '{2F910A05-3607-4070-A6FF-53539E5D3BBB}';
        public const HmIO = '{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}';
        public const HmDevice = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';
    }

    class CCU
    {
        public const BidCos_RF = 'BidCos-RF';
        public const BidCos_WR = 'BidCos-WR';
        public const HmIP = 'HmIP-RF';
        public const Groups = 'VirtualDevices';
        public const MASTER = 'MASTER';
        public const VALUES = 'VALUES';
        public const ProtocolBidCosRF = 0;
        public const ProtocolBidCosWR = 1;
        public const ProtocolHmIP = 2;
        public const ProtocolGroups = 3;
        public static $Interfaces = [
            self::BidCos_RF,
            self::BidCos_WR,
            self::HmIP,
            self::Groups
        ];
    }

    class DeviceType
    {
        // suche nach Datenpunkt *ENERGY_COUNTER
        public const Powermeter = 'POWERMETER';
        public const Powermeter_IEC1 = 'POWERMETER_IEC1';
        public const Powermeter_IEC2 = 'POWERMETER_IEC2';
        public const Powermeter_IGL = 'POWERMETER_IGL';
        public const Powermeter_HmIP = 'ENERGIE_METER_TRANSMITTER';

        // Wetterkanal der HmIP-Wetterstationen HmIP-SWO-B/-PL/-PR (nur diese führen Systemvariablen auf der CCU)
        public const Weather_HmIP = 'WEATHER_TRANSMIT';

        public const Dis_WM55 = 'HM-Dis-WM55'; // HM-Dis-WM55
        public const Dis_EP_WM55 = 'HM-Dis-EP-WM55'; // HM-Dis-EP-WM55

        public const HeatingDevice = 'HM-CC-RT-DN'; // CLIMATECONTROL_RECEIVER
        public const HeatingGroup = 'HM-CC-VG-1'; //'CLIMATECONTROL_RT_TRANSCEIVER';
        public const HeatingGroupHmIP = 'HmIP-HEATING'; //'HEATING_CLIMATECONTROL_TRANSCEIVER';
        public const ClimacontrolRegulator = 'HM-CC-TC'; //'CLIMATECONTROL_REGULATOR';

        /**
         * Module, deren Adresse einen Kanal enthält (Ausgabe-Kanal). Der Konfigurator trägt diesen Kanal ein.
         */
        public static $GuidToDefaultChannel = [
            GUID::Dis_WM55    => Channels::First,
            GUID::Dis_EP_WM55 => Channels::Third
        ];

        /**
         * Module, welche die gemeinsamen Profile der Heizungsgeräte und das Profil 'Execute.HM' nutzen.
         */
        public static $HeatingGuids = [
            GUID::HeatingDevice,
            GUID::HeatingGroup,
            GUID::HeatingGroupHmIP,
            GUID::ClimacontrolRegulator
        ];

        public static $GuidToType = [
            // Konfigurator muss noch ergänzt werden um:
            //GUID::Systemvariablen                =>
            //GUID::Programme                      =>
            //GUID::WR_Interface                   =>
            // Vorhandener Konfigurator muss noch migriert werden:
            //GUID::RF_Interface_Splitter          =>
            // Konfigurator 'fertig' => Aber die Instanzen sind noch nicht so weit
            GUID::Powermeter                     => [self::Powermeter, self::Powermeter_IEC1, self::Powermeter_IEC2, self::Powermeter_IGL, self::Powermeter_HmIP],
            GUID::Weather                        => [self::Weather_HmIP],
            GUID::Dis_WM55                       => [self::Dis_WM55],
            GUID::Dis_EP_WM55                    => [self::Dis_EP_WM55],
            // Konfigurator fertig
            GUID::HeatingDevice                  => [self::HeatingDevice],
            GUID::HeatingGroup                   => [self::HeatingGroup],
            GUID::HeatingGroupHmIP               => [self::HeatingGroupHmIP],
            GUID::ClimacontrolRegulator          => [self::ClimacontrolRegulator]
        ];

    }

    class Channels
    {
        public const Device = '';
        public const First = ':1';
        public const Second = ':2';
        public const Third = ':3';
        public const Fourth = ':4';
    }

    class ClimacontrolRegulator
    {
        //Values
        public const SETPOINT = 'SETPOINT';
        public const ADJUSTING_DATA = 'ADJUSTING_DATA';
        public const ADJUSTING_COMMAND = 'ADJUSTING_COMMAND';
        public const STATE = 'STATE';
        //Values Kanal :1 (WEATHER)
        public const TEMPERATURE = 'TEMPERATURE';
        public const HUMIDITY = 'HUMIDITY';
        //Params
        public const MODE_TEMPERATUR_REGULATOR = 'MODE_TEMPERATUR_REGULATOR';
        public const DECALCIFICATION_DAY = 'DECALCIFICATION_DAY';
        public const DECALCIFICATION_TIME = 'DECALCIFICATION_TIME';
        public const DECALCIFICATION_HOUR = 'DECALCIFICATION_HOUR';
        public const DECALCIFICATION_MINUTE = 'DECALCIFICATION_MINUTE';
        public const MODE_TEMPERATUR_VALVE = 'MODE_TEMPERATUR_VALVE';
        public const TEMPERATUR_LOWERING_VALUE = 'TEMPERATUR_LOWERING_VALUE';
        public const TEMPERATUR_COMFORT_VALUE = 'TEMPERATUR_COMFORT_VALUE';
        public const TEMPERATUR_PARTY_VALUE = 'TEMPERATUR_PARTY_VALUE';
        public const PARTY_END_TIME = 'PARTY_END_TIME';
        public const PARTY_END_DAY = 'PARTY_END_DAY';
        public const PARTY_END_HOUR = 'PARTY_END_HOUR';
        public const PARTY_END_MINUTE = 'PARTY_END_MINUTE';
    }

    class HeatingGroupHmIP
    {
        //Values
        public const ACTIVE_PROFILE = 'ACTIVE_PROFILE';
        public const ACTUAL_TEMPERATURE = 'ACTUAL_TEMPERATURE';
        public const ACTUAL_TEMPERATURE_STATUS = 'ACTUAL_TEMPERATURE_STATUS';
        public const BOOST_MODE = 'BOOST_MODE';
        public const BOOST_TIME = 'BOOST_TIME';
        public const FROST_PROTECTION = 'FROST_PROTECTION';
        public const HEATING_COOLING = 'HEATING_COOLING';
        public const HUMIDITY = 'HUMIDITY';
        public const HUMIDITY_STATUS = 'HUMIDITY_STATUS';
        public const LEVEL = 'LEVEL';
        public const LEVEL_STATUS = 'LEVEL_STATUS';
        public const PARTY_MODE = 'PARTY_MODE';
        public const PARTY_SET_POINT_TEMPERATURE = 'PARTY_SET_POINT_TEMPERATURE';
        public const PARTY_TIME_END = 'PARTY_TIME_END';
        public const PARTY_TIME_START = 'PARTY_TIME_START';
        public const QUICK_VETO_TIME = 'QUICK_VETO_TIME';
        public const SET_POINT_MODE = 'SET_POINT_MODE';
        public const CONTROL_MODE = 'CONTROL_MODE';
        public const SET_POINT_TEMPERATURE = 'SET_POINT_TEMPERATURE';
        public const SWITCH_POINT_OCCURED = 'SWITCH_POINT_OCCURED';
        public const VALVE_ADAPTION = 'VALVE_ADAPTION';
        public const VALVE_STATE = 'VALVE_STATE';
        public const WINDOW_STATE = 'WINDOW_STATE';
        //Params
        public const DECALCIFICATION_TIME = 'DECALCIFICATION_TIME';
        public const DECALCIFICATION_WEEKDAY = 'DECALCIFICATION_WEEKDAY';
        public const BOOST_POSITION = 'BOOST_POSITION';
        public const BOOST_TIME_PERIOD = 'BOOST_TIME_PERIOD';
        public const TEMPERATURE_COMFORT = 'TEMPERATURE_COMFORT';
        public const TEMPERATURE_COMFORT_COOLING = 'TEMPERATURE_COMFORT_COOLING';
        public const TEMPERATURE_LOWERING = 'TEMPERATURE_LOWERING';
        public const TEMPERATURE_LOWERING_COOLING = 'TEMPERATURE_LOWERING_COOLING';
        public const TEMPERATURE_WINDOW_OPEN = 'TEMPERATURE_WINDOW_OPEN';
        // only Property
        public const PARTY = 'PARTY';
    }

    class HeatingGroup
    {
        //Values
        public const ACTUAL_HUMIDITY = 'ACTUAL_HUMIDITY';
        public const ACTUAL_TEMPERATURE = 'ACTUAL_TEMPERATURE';
        public const AUTO_MODE = 'AUTO_MODE';
        public const BOOST_MODE = 'BOOST_MODE';
        public const COMFORT_MODE = 'COMFORT_MODE';
        public const CONTROL_MODE = 'CONTROL_MODE';
        public const LOWERING_MODE = 'LOWERING_MODE';
        public const MANU_MODE = 'MANU_MODE';
        public const PARTY_MODE_SUBMIT = 'PARTY_MODE_SUBMIT';
        public const PARTY_START_DAY = 'PARTY_START_DAY';
        public const PARTY_START_MONTH = 'PARTY_START_MONTH';
        public const PARTY_START_TIME = 'PARTY_START_TIME';
        public const PARTY_START_YEAR = 'PARTY_START_YEAR';
        public const PARTY_STOP_DAY = 'PARTY_STOP_DAY';
        public const PARTY_STOP_MONTH = 'PARTY_STOP_MONTH';
        public const PARTY_STOP_TIME = 'PARTY_STOP_TIME';
        public const PARTY_STOP_YEAR = 'PARTY_STOP_YEAR';
        public const PARTY_TEMPERATURE = 'PARTY_TEMPERATURE';
        public const SET_TEMPERATURE = 'SET_TEMPERATURE';
        //Params
        public const DECALCIFICATION_TIME = 'DECALCIFICATION_TIME';
        public const DECALCIFICATION_WEEKDAY = 'DECALCIFICATION_WEEKDAY';
        public const BOOST_POSITION = 'BOOST_POSITION';
        public const BOOST_TIME_PERIOD = 'BOOST_TIME_PERIOD';
        public const BUTTON_LOCK = 'BUTTON_LOCK';
        public const GLOBAL_BUTTON_LOCK = 'GLOBAL_BUTTON_LOCK';
        public const MODUS_BUTTON_LOCK = 'MODUS_BUTTON_LOCK';
        public const BOOST_AFTER_WINDOW_OPEN = 'BOOST_AFTER_WINDOW_OPEN';
        public const TEMPERATUREFALL_WINDOW_OPEN = 'TEMPERATUREFALL_WINDOW_OPEN';
        public const TEMPERATUREFALL_WINDOW_OPEN_TIME_PERIOD = 'TEMPERATUREFALL_WINDOW_OPEN_TIME_PERIOD';
        public const TEMPERATURE_COMFORT = 'TEMPERATURE_COMFORT';
        public const TEMPERATURE_LOWERING = 'TEMPERATURE_LOWERING';
        public const WEEK_PROGRAM_POINTER = 'WEEK_PROGRAM_POINTER';

        // only Property
        public const PARTY = 'PARTY';
    }

    class Property
    {
        public static $Properties = [
            DeviceType::ClimacontrolRegulator => [
                ClimacontrolRegulator::TEMPERATUR_PARTY_VALUE    => true,
                ClimacontrolRegulator::PARTY_END_TIME            => true,
                ClimacontrolRegulator::TEMPERATUR_COMFORT_VALUE  => false,
                ClimacontrolRegulator::TEMPERATUR_LOWERING_VALUE => false,
                ClimacontrolRegulator::DECALCIFICATION_TIME      => false,
                ClimacontrolRegulator::DECALCIFICATION_DAY       => false,
                ClimacontrolRegulator::MODE_TEMPERATUR_VALVE     => false
            ],
            DeviceType::HeatingGroupHmIP=> [
                HeatingGroupHmIP::ACTIVE_PROFILE               => true,
                HeatingGroupHmIP::BOOST_MODE                   => true,
                HeatingGroupHmIP::BOOST_TIME                   => false,
                HeatingGroupHmIP::FROST_PROTECTION             => false,
                HeatingGroupHmIP::HEATING_COOLING              => false,
                HeatingGroupHmIP::LEVEL                        => true,
                HeatingGroupHmIP::PARTY                        => true,
                HeatingGroupHmIP::SWITCH_POINT_OCCURED         => false,
                HeatingGroupHmIP::VALVE_ADAPTION               => false,
                HeatingGroupHmIP::VALVE_STATE                  => true,
                HeatingGroupHmIP::WINDOW_STATE                 => true,
                HeatingGroupHmIP::DECALCIFICATION_TIME         => false,
                HeatingGroupHmIP::DECALCIFICATION_WEEKDAY      => false,
                HeatingGroupHmIP::BOOST_POSITION               => false,
                HeatingGroupHmIP::BOOST_TIME_PERIOD            => false,
                HeatingGroupHmIP::TEMPERATURE_COMFORT          => false,
                HeatingGroupHmIP::TEMPERATURE_COMFORT_COOLING  => false,
                HeatingGroupHmIP::TEMPERATURE_LOWERING         => false,
                HeatingGroupHmIP::TEMPERATURE_LOWERING_COOLING => false,
                HeatingGroupHmIP::TEMPERATURE_WINDOW_OPEN      => true
            ],
            DeviceType::HeatingGroup=> [
                HeatingGroup::COMFORT_MODE                            => true,
                HeatingGroup::LOWERING_MODE                           => true,
                HeatingGroup::PARTY                                   => true,
                HeatingGroup::DECALCIFICATION_TIME                    => false,
                HeatingGroup::DECALCIFICATION_WEEKDAY                 => false,
                HeatingGroup::BOOST_POSITION                          => false,
                HeatingGroup::BOOST_TIME_PERIOD                       => false,
                HeatingGroup::BUTTON_LOCK                             => false,
                HeatingGroup::GLOBAL_BUTTON_LOCK                      => false,
                HeatingGroup::MODUS_BUTTON_LOCK                       => false,
                HeatingGroup::BOOST_AFTER_WINDOW_OPEN                 => false,
                HeatingGroup::TEMPERATUREFALL_WINDOW_OPEN             => true,
                HeatingGroup::TEMPERATUREFALL_WINDOW_OPEN_TIME_PERIOD => false,
                HeatingGroup::TEMPERATURE_COMFORT                     => false,
                HeatingGroup::TEMPERATURE_LOWERING                    => false,
                HeatingGroup::WEEK_PROGRAM_POINTER                    => true,
            ]
        ];
    }

    class Variables
    {
        public const VARIABLETYPE_NONE = -1;
        public const SUBMIT_WEEK_PROGRAM = 'SUBMIT_WEEK_PROGRAM';
        public const SELECT_NEW_WEEK_PROGRAM = 'SELECT_NEW_WEEK_PROGRAM';

        /**
         * Variablenprofile der Heizungsmodule bis Version 3.81. Seit 3.82 nutzen die Statusvariablen Darstellungen (HeatingPresentation),
         * die Profile werden beim Übernehmen gelöst und gelöscht, sobald keine Variable sie mehr nutzt.
         */
        public const LegacyProfiles = [
            'Heating.Control.SetPoint.Temperature.HM',
            // Aus einer älteren Version, wird seitdem nicht mehr angelegt.
            'Heating.Control.SetPoint.Temperature.HmIP',
            'Heating.Control.Profile.HmIP',
            'Heating.Control.Profile.HM',
            'DateTime.Time.Seconds.HM',
            'Heating.Control.Mode.HM',
            'Heating.Control.SetPoint.Mode.HmIP',
            'Heating.Control.SetPoint.Mode.HM',
            'Heating.Control.SetPoint.Mode.HM-CC-TC',
            'Heating.Control.Boost.State.HM',
            'Window.Open.State.HM.Reversed',
            'Heating.Valve.State.HmIP',
            'DateTime.DoW.Saturday',
            'DateTime.DoW.Sunday',
            'Heating.Control.Valve.Mode.HM',
            'DateTime.Time.Minutes.30',
            'DateTime.Time.Minutes.30.Steps',
            'DateTime.Time.Minutes.60.Steps',
            'Temperature.Room.Lowering.HmIP',
            'Temperature.Room.Comfort.HmIP',
            'Execute.HM'
        ];

        public static $HmTypeToIpsType = [
            'FLOAT' => VARIABLETYPE_FLOAT,
            'BOOL'  => VARIABLETYPE_BOOLEAN,
            'UINT'  => VARIABLETYPE_INTEGER,
            'STRING'=> VARIABLETYPE_STRING
        ];
    }

    /**
     * Darstellungen der Statusvariablen der Heizungsmodule (seit Version 3.82 statt Variablenprofilen).
     *
     * Variablen mit Aktion erhalten eine bedienbare Darstellung (Schieberegler, Aufzählung, Schalter),
     * Variablen ohne Aktion eine Wertanzeige. Soll-Temperaturen übernehmen Grenzen und Sonderwerte
     * aus der Paramset-Beschreibung der CCU (MIN, MAX, SPECIAL), da sie je Gerät verschieden sind.
     */
    class HeatingPresentation
    {
        public const SetPointTemperature = 'SetPointTemperature';
        public const Temperature = 'Temperature';
        public const Humidity = 'Humidity';
        public const Switch = 'Switch';
        public const Level = 'Level';
        public const Percent = 'Percent';
        public const DateTime = 'DateTime';
        public const Time = 'Time';
        public const Seconds = 'Seconds';
        public const Minutes30 = 'Minutes30';
        public const Minutes30Steps = 'Minutes30Steps';
        public const Minutes60Steps = 'Minutes60Steps';
        public const ProfileHmIP = 'ProfileHmIP';
        public const ProfileHM = 'ProfileHM';
        public const HeatingCooling = 'HeatingCooling';
        public const SetPointModeHmIP = 'SetPointModeHmIP';
        public const SetPointModeHM = 'SetPointModeHM';
        public const OperationModeCCTC = 'OperationModeCCTC';
        public const WindowState = 'WindowState';
        public const ValveStateHmIP = 'ValveStateHmIP';
        public const WeekdayFromSaturday = 'WeekdayFromSaturday';
        public const WeekdayFromSunday = 'WeekdayFromSunday';
        public const ValveMode = 'ValveMode';
        public const Execute = 'Execute';

        /** Soll-Temperatur der CCU für „Aus“ bzw. „An“ (HM-CC-RT-DN, HmIP). */
        public const TemperatureOff = 4.5;
        public const TemperatureOn = 30.5;

        /** Schieberegler: USAGE_TYPE 0 = Temperatur (Standard 5 = keine), GRADIENT_TYPE 1 = Temperatur. */
        public const SliderUsageTemperature = 0;
        public const SliderGradientTemperature = 1;
        /** Wertanzeige: USAGE_TYPE 1 = Temperatur (Standard 0 = keine). */
        public const ValueUsageTemperature = 1;

        /**
         * Zahlenwerte: Symbol, Suffix, Nachkommastellen, Minimum, Maximum, Schrittweite, prozentual.
         * Minimum und Maximum der Soll-Temperatur gelten nur, wenn die CCU keine Grenzen liefert.
         */
        public const Numbers = [
            self::SetPointTemperature => ['Temperature', ' °C', 1, self::TemperatureOff, self::TemperatureOn, 0.5, false],
            self::Temperature         => ['Temperature', ' °C', 1, 0, 0, 0, false],
            self::Humidity            => ['Drops', ' %', 0, 0, 100, 1, false],
            self::Level               => ['Intensity', ' %', 0, 0, 1, 0.05, true],
            self::Percent             => ['Intensity', ' %', 0, 0, 100, 1, false],
            self::Minutes30           => ['Clock', ' min', 0, 0, 30, 1, false],
            self::Minutes30Steps      => ['Clock', ' min', 0, 0, 30, 5, false],
            self::Minutes60Steps      => ['Clock', ' min', 0, 0, 60, 5, false]
        ];

        /**
         * Aufzählungen: Symbol und Einträge (Wert, Beschriftung, Symbol, Farbe).
         * Die Beschriftungen werden vom Modul übersetzt (locale.json der Heizungsmodule).
         */
        public const Options = [
            self::ProfileHmIP => ['Clock', [
                [1, '1', '', -1],
                [2, '2', '', -1],
                [3, '3', '', -1],
                [4, '4', '', -1],
                [5, '5', '', -1],
                [6, '6', '', -1]
            ]],
            self::ProfileHM => ['Clock', [
                [1, '1', '', -1],
                [2, '2', '', -1],
                [3, '3', '', -1]
            ]],
            self::HeatingCooling => ['', [
                [0, 'Heat', 'Flame', 0xFF0000],
                [1, 'Cool', 'Snowflake', 0x0000FF]
            ]],
            self::SetPointModeHmIP => ['', [
                [0, 'Automatic', 'Clock', 0x339966],
                [1, 'Manually', 'Execute', 0xFF0000],
                [2, 'Holiday/Party', 'Party', 0x3366FF]
            ]],
            self::SetPointModeHM => ['', [
                [0, 'Automatic', 'Clock', 0x339966],
                [1, 'Manually', 'Execute', 0xFF0000],
                [2, 'Holiday/Party', 'Party', 0x3366FF],
                [3, 'Boost', 'Flame', 0xFFFF99]
            ]],
            self::OperationModeCCTC => ['', [
                [0, 'Manually', 'Execute', 0xFF0000],
                [1, 'Automatic', 'Clock', 0x339966],
                [2, 'Central', 'Remote', 0xFF9900],
                [3, 'Holiday/Party', 'Party', 0x3366FF]
            ]],
            self::WindowState => ['Window', [
                [0, 'Closed', '', -1],
                [1, 'Open', '', 0x0000FF]
            ]],
            self::ValveStateHmIP => ['Gear', [
                [0, 'Status unknown', '', -1],
                [1, 'Run to start position', '', -1],
                [2, 'wait for adaptation', '', -1],
                [3, 'Adaptation in progress', '', -1],
                [4, 'Adaptation done', '', -1],
                [5, 'Valve too tight', '', -1],
                [6, 'Range too big', '', -1],
                [7, 'Range too small', '', -1],
                [8, 'Error position', '', -1]
            ]],
            self::WeekdayFromSaturday => ['Calendar', [
                [0, 'Saturday', '', -1],
                [1, 'Sunday', '', -1],
                [2, 'Monday', '', -1],
                [3, 'Tuesday', '', -1],
                [4, 'Wednesday', '', -1],
                [5, 'Thursday', '', -1],
                [6, 'Friday', '', -1]
            ]],
            self::WeekdayFromSunday => ['Calendar', [
                [0, 'Sunday', '', -1],
                [1, 'Monday', '', -1],
                [2, 'Tuesday', '', -1],
                [3, 'Wednesday', '', -1],
                [4, 'Thursday', '', -1],
                [5, 'Friday', '', -1],
                [6, 'Saturday', '', -1]
            ]],
            self::ValveMode => ['Gear', [
                [0, 'Automatic', 'Clock', 0x339966],
                [1, 'Closed', 'Execute', 0x0000FF],
                [2, 'Open', 'Party', 0xFF0000]
            ]],
            self::Execute => ['', [
                [0, 'Start', '', -1]
            ]]
        ];

        /** Beschriftungen der Sonderwerte (SPECIAL) aus der Paramset-Beschreibung, z.B. HM-CC-TC SETPOINT 0 und 100. */
        public const SpecialCaptions = [
            'VENT_CLOSED' => 'Valve closed',
            'VENT_OPEN'   => 'Valve open'
        ];

        /** Weitere Beschriftungen, die die Darstellungen verwenden. */
        public const Captions = ['Off', 'On'];

        /**
         * Liefert die Darstellung einer Statusvariable.
         *
         * @param string $Key Schlüssel der Darstellung (Konstante dieser Klasse).
         * @param int $VariableType Typ der Statusvariable.
         * @param bool $HasAction true, wenn die Variable bedienbar ist.
         * @param array $Description Beschreibung des Datenpunktes laut getParamsetDescription (MIN, MAX, SPECIAL), leer wenn unbekannt.
         * @param callable $Translate Übersetzt eine Beschriftung (string => string).
         *
         * @return array Die Darstellung für MaintainVariable.
         */
        public static function Get(string $Key, int $VariableType, bool $HasAction, array $Description, callable $Translate): array
        {
            switch ($Key) {
                case self::Switch:
                    if ($HasAction) {
                        return ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH];
                    }
                    return [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'OPTIONS'      => json_encode([
                            self::ValueOption(false, $Translate('Off'), '', -1),
                            self::ValueOption(true, $Translate('On'), '', -1)
                        ])
                    ];
                case self::DateTime:
                    return ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME, 'DATE' => 1, 'MONTH_TEXT' => false, 'DAY_OF_THE_WEEK' => false, 'TIME' => 1];
                case self::Time:
                    return ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME, 'DATE' => 0, 'DAY_OF_THE_WEEK' => false, 'TIME' => 1];
                case self::Seconds:
                    // Restzeit in Sekunden als Minuten und Sekunden.
                    return ['PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'COUNTDOWN_TYPE' => 0, 'FORMAT' => 1];
            }
            if (array_key_exists($Key, self::Options)) {
                return self::Enumeration($Key, $HasAction, $Description, $Translate);
            }
            return self::Number($Key, $VariableType, $HasAction, $Description, $Translate);
        }

        /**
         * Zahlenwert als Schieberegler (mit Aktion) oder Wertanzeige (ohne Aktion).
         */
        private static function Number(string $Key, int $VariableType, bool $HasAction, array $Description, callable $Translate): array
        {
            [$Icon, $Suffix, $Digits, $Min, $Max, $Step, $Percentage] = self::Numbers[$Key];
            $Intervals = [];
            if ($Key == self::SetPointTemperature) {
                // Die CCU liefert MIN/MAX je nach Schnittstelle als Zahl oder Text (z.B. '5.0' bei der virtuellen Heizgruppe).
                if (is_numeric($Description['MIN'] ?? null) && is_numeric($Description['MAX'] ?? null)) {
                    $Min = (float) $Description['MIN'];
                    $Max = (float) $Description['MAX'];
                }
                if ($Min == self::TemperatureOff) {
                    $Intervals[] = self::Interval($Min, $Translate('Off'));
                }
                if ($Max == self::TemperatureOn) {
                    $Intervals[] = self::Interval($Max, $Translate('On'));
                }
                foreach ($Description['SPECIAL'] ?? [] as $Special) {
                    if (is_array($Special) && is_numeric($Special['VALUE'] ?? null)) {
                        $Caption = self::SpecialCaptions[$Special['ID'] ?? ''] ?? (string) ($Special['ID'] ?? $Special['VALUE']);
                        $Intervals[] = self::Interval((float) $Special['VALUE'], $Translate($Caption));
                    }
                }
            }
            if ($VariableType == VARIABLETYPE_INTEGER) {
                $Min = (int) $Min;
                $Max = (int) $Max;
            }
            $Presentation = [
                'PRESENTATION' => $HasAction ? VARIABLE_PRESENTATION_SLIDER : VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON'         => $Icon,
                'SUFFIX'       => $Suffix,
                'DIGITS'       => $Digits,
                'PERCENTAGE'   => $Percentage
            ];
            if ($HasAction || $Percentage) {
                $Presentation['MIN'] = $Min;
                $Presentation['MAX'] = $Max;
            }
            if ($HasAction) {
                $Presentation['STEP_SIZE'] = ($VariableType == VARIABLETYPE_INTEGER) ? (int) $Step : $Step;
            }
            if (count($Intervals)) {
                $Presentation['INTERVALS_ACTIVE'] = true;
                $Presentation['INTERVALS'] = json_encode($Intervals);
            }
            if (in_array($Key, [self::SetPointTemperature, self::Temperature])) {
                // Verwendung Temperatur, die Werte unterscheiden sich je Darstellung (sliderForm.php, valuePresentationForm.php).
                if ($HasAction) {
                    $Presentation['USAGE_TYPE'] = self::SliderUsageTemperature;
                    $Presentation['GRADIENT_TYPE'] = self::SliderGradientTemperature;
                } else {
                    $Presentation['USAGE_TYPE'] = self::ValueUsageTemperature;
                }
            }
            return $Presentation;
        }

        /**
         * Aufzählung (mit Aktion) oder Wertanzeige mit festen Texten je Wert (ohne Aktion).
         */
        private static function Enumeration(string $Key, bool $HasAction, array $Description, callable $Translate): array
        {
            [$Icon, $Entries] = self::Options[$Key];
            if (($Key == self::ProfileHmIP) && is_numeric($Description['MIN'] ?? null) && is_numeric($Description['MAX'] ?? null)) {
                // Anzahl der Profile laut CCU (z.B. 1..3 bei einer Heizgruppe, 1..6 im MASTER-Paramset).
                $Entries = array_values(array_filter($Entries, fn ($Entry) => ($Entry[0] >= (int) $Description['MIN']) && ($Entry[0] <= (int) $Description['MAX'])));
            }
            if ($HasAction) {
                $Options = [];
                foreach ($Entries as [$Value, $Caption, $EntryIcon, $Color]) {
                    $Options[] = ['Value' => $Value, 'Caption' => $Translate($Caption), 'IconActive' => $EntryIcon !== '', 'IconValue' => $EntryIcon, 'Color' => $Color];
                }
                return ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => $Icon, 'OPTIONS' => json_encode($Options)];
            }
            $Intervals = [];
            foreach ($Entries as [$Value, $Caption, $EntryIcon, $Color]) {
                $Interval = self::Interval($Value, $Translate($Caption));
                $Interval['IconActive'] = $EntryIcon !== '';
                $Interval['IconValue'] = $EntryIcon;
                $Interval['ColorActive'] = $Color != -1;
                $Interval['ColorValue'] = $Color;
                $Intervals[] = $Interval;
            }
            return [
                'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON'             => $Icon,
                'DIGITS'           => 0,
                'INTERVALS_ACTIVE' => true,
                'INTERVALS'        => json_encode($Intervals)
            ];
        }

        /**
         * Intervall für genau einen Wert mit festem Text.
         */
        private static function Interval(int|float $Value, string $Caption): array
        {
            return ['IntervalMinValue' => $Value, 'IntervalMaxValue' => $Value, 'ConstantActive' => true, 'ConstantValue' => $Caption];
        }

        /**
         * Eintrag der Wertanzeige für Boolean.
         */
        private static function ValueOption(bool $Value, string $Caption, string $Icon, int $Color): array
        {
            return [
                'Value'              => $Value,
                'Caption'            => $Caption,
                'IconActive'         => $Icon !== '',
                'IconValue'          => $Icon,
                'ColorActive'        => $Color != -1,
                'ColorValue'         => $Color,
                'ContentColorActive' => false,
                'ContentColorValue'  => -1
            ];
        }
    }

    class ValuesSet
    {
        public static $Variables = [
            DeviceType::ClimacontrolRegulator => [
                ClimacontrolRegulator::SETPOINT => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Target temperature'
                ],
                ClimacontrolRegulator::ADJUSTING_DATA => [
                    \HMExtended\Variables::VARIABLETYPE_NONE
                ],
                ClimacontrolRegulator::ADJUSTING_COMMAND=> [
                    \HMExtended\Variables::VARIABLETYPE_NONE
                ],
                ClimacontrolRegulator::STATE => [
                    \HMExtended\Variables::VARIABLETYPE_NONE
                ],
                // Kanal :1 (rf_cc_tc.xml, cc_tc_ch_weather_values): TEMPERATURE -40..80 °C, HUMIDITY 0..99 %.
                ClimacontrolRegulator::TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::Temperature,
                    false,
                    'Temperature'
                ],
                ClimacontrolRegulator::HUMIDITY => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Humidity,
                    false,
                    'Humidity'
                ],
            ],
            DeviceType::HeatingGroupHmIP => [
                HeatingGroupHmIP::ACTIVE_PROFILE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::ProfileHmIP,
                    true,
                    'Active profile'
                ],
                HeatingGroupHmIP::ACTUAL_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::Temperature,
                    false,
                    'Temperature'
                ],
                HeatingGroupHmIP::ACTUAL_TEMPERATURE_STATUS => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::BOOST_MODE => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    true,
                    'Boost'
                ],
                HeatingGroupHmIP::BOOST_TIME => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Seconds,
                    false,
                    'Boost remaining time'
                ],
                HeatingGroupHmIP::FROST_PROTECTION => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    false,
                    'Frost protection'
                ],
                HeatingGroupHmIP::HEATING_COOLING => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::HeatingCooling,
                    true,
                    'Operation mode'
                ],
                HeatingGroupHmIP::HUMIDITY => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Humidity,
                    false,
                    'Humidity'
                ],
                HeatingGroupHmIP::HUMIDITY_STATUS => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::LEVEL => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::Level,
                    true,
                    'Valve opening'
                ],
                HeatingGroupHmIP::LEVEL_STATUS => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::PARTY_MODE => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::PARTY_SET_POINT_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Holiday/Party set point temperature',
                    HeatingGroupHmIP::PARTY
                ],
                HeatingGroupHmIP::PARTY_TIME_END => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::DateTime,
                    true,
                    'Holiday/Party end',
                    HeatingGroupHmIP::PARTY
                ],
                HeatingGroupHmIP::PARTY_TIME_START => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::DateTime,
                    true,
                    'Holiday/Party start',
                    HeatingGroupHmIP::PARTY
                ],
                HeatingGroupHmIP::QUICK_VETO_TIME => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::SET_POINT_MODE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::SetPointModeHmIP,
                    HeatingGroupHmIP::CONTROL_MODE,
                    'Target temperature mode'
                ],
                HeatingGroupHmIP::CONTROL_MODE=> [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::SET_POINT_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Target temperature'
                ],
                HeatingGroupHmIP::SWITCH_POINT_OCCURED => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    false,
                    'Switch point occurred'
                ],
                HeatingGroupHmIP::VALVE_ADAPTION => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    true,
                    'Valve adaption'
                ],
                HeatingGroupHmIP::VALVE_STATE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::ValveStateHmIP,
                    false,
                    'Valve state'
                ],
                HeatingGroupHmIP::WINDOW_STATE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::WindowState,
                    true,
                    'Window state'
                ]
            ],
            DeviceType::HeatingGroup => [
                HeatingGroup::ACTUAL_HUMIDITY => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Humidity,
                    false,
                    'Humidity'
                ],
                HeatingGroup::ACTUAL_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::Temperature,
                    false,
                    'Temperature'
                ],
                HeatingGroup::SET_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Target temperature'
                ],
                HeatingGroup::MANU_MODE => [
                    \HMExtended\Variables::VARIABLETYPE_NONE
                ],
                HeatingGroup::AUTO_MODE => [
                    \HMExtended\Variables::VARIABLETYPE_NONE
                ],
                HeatingGroup::BOOST_MODE => [
                    \HMExtended\Variables::VARIABLETYPE_NONE
                ],
                HeatingGroup::CONTROL_MODE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::SetPointModeHM,
                    true,
                    'Target temperature mode'
                ],
                HeatingGroup::PARTY_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Holiday/Party set point temperature',
                    HeatingGroup::PARTY
                ],

                HeatingGroup::PARTY_START_TIME => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::DateTime,
                    true,
                    'Holiday/Party start',
                    HeatingGroup::PARTY
                ],
                HeatingGroup::PARTY_START_DAY => [
                    \HMExtended\Variables::VARIABLETYPE_NONE,
                    '',
                    HeatingGroup::PARTY_START_TIME
                ],
                HeatingGroup::PARTY_START_MONTH => [
                    \HMExtended\Variables::VARIABLETYPE_NONE,
                    '',
                    HeatingGroup::PARTY_START_TIME
                ],
                HeatingGroup::PARTY_START_YEAR => [
                    \HMExtended\Variables::VARIABLETYPE_NONE,
                    '',
                    HeatingGroup::PARTY_START_TIME
                ],
                HeatingGroup::PARTY_STOP_TIME => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::DateTime,
                    true,
                    'Holiday/Party end',
                    HeatingGroup::PARTY
                ],
                HeatingGroup::PARTY_STOP_DAY => [
                    \HMExtended\Variables::VARIABLETYPE_NONE,
                    '',
                    HeatingGroup::PARTY_STOP_TIME
                ],
                HeatingGroup::PARTY_STOP_MONTH => [
                    \HMExtended\Variables::VARIABLETYPE_NONE,
                    '',
                    HeatingGroup::PARTY_STOP_TIME
                ],
                HeatingGroup::PARTY_STOP_YEAR => [
                    \HMExtended\Variables::VARIABLETYPE_NONE,
                    '',
                    HeatingGroup::PARTY_STOP_TIME
                ],
                HeatingGroup::COMFORT_MODE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Execute,
                    true,
                    'Set to comfort temperature'
                ],
                HeatingGroup::LOWERING_MODE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Execute,
                    true,
                    'Set to lowering temperature'
                ],
            ]
        ];
    }

    class ParamSet
    {
        public static $Variables = [
            DeviceType::ClimacontrolRegulator => [
                ClimacontrolRegulator::MODE_TEMPERATUR_REGULATOR => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::OperationModeCCTC,
                    true,
                    'Operation mode'
                ],
                ClimacontrolRegulator::DECALCIFICATION_DAY => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::WeekdayFromSaturday,
                    true,
                    'Decalcification day'
                ],
                ClimacontrolRegulator::DECALCIFICATION_TIME => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Time,
                    true,
                    'Decalcification time'
                ],
                ClimacontrolRegulator::DECALCIFICATION_HOUR => [
                    Variables::VARIABLETYPE_NONE,
                    '',
                    ClimacontrolRegulator::DECALCIFICATION_TIME
                ],
                ClimacontrolRegulator::MODE_TEMPERATUR_VALVE => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::ValveMode,
                    true,
                    'Valve mode'
                ],
                ClimacontrolRegulator::TEMPERATUR_LOWERING_VALUE=> [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Lowering temperature'
                ],
                ClimacontrolRegulator::TEMPERATUR_COMFORT_VALUE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Comfort temperature'
                ],
                ClimacontrolRegulator::PARTY_END_TIME => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::DateTime,
                    true,
                    'Holiday/Party end'
                ],
                ClimacontrolRegulator::TEMPERATUR_PARTY_VALUE => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Holiday/Party set point temperature'
                ],
                ClimacontrolRegulator::PARTY_END_DAY => [
                    Variables::VARIABLETYPE_NONE,
                    '',
                    ClimacontrolRegulator::PARTY_END_TIME
                ],
                ClimacontrolRegulator::PARTY_END_HOUR => [
                    Variables::VARIABLETYPE_NONE
                ],
                ClimacontrolRegulator::PARTY_END_MINUTE => [
                    Variables::VARIABLETYPE_NONE
                ]
            ],
            DeviceType::HeatingGroupHmIP => [
                HeatingGroupHmIP::DECALCIFICATION_TIME=> [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Time,
                    true,
                    'Decalcification time'
                ],
                HeatingGroupHmIP::DECALCIFICATION_WEEKDAY=> [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::WeekdayFromSunday,
                    true,
                    'Decalcification day'
                ],
                HeatingGroupHmIP::BOOST_POSITION=> [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Percent,
                    true,
                    'Boost valve opening'
                ],
                HeatingGroupHmIP::BOOST_TIME_PERIOD => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Minutes30,
                    true,
                    'Boost duration'
                ],
                HeatingGroupHmIP::TEMPERATURE_COMFORT => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Comfort temperature'
                ],
                HeatingGroupHmIP::TEMPERATURE_COMFORT_COOLING => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Comfort temperature cooling'
                ],
                HeatingGroupHmIP::TEMPERATURE_LOWERING => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Lowering temperature'
                ],
                HeatingGroupHmIP::TEMPERATURE_LOWERING_COOLING => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Lowering temperature cooling'
                ],
                HeatingGroupHmIP::TEMPERATURE_WINDOW_OPEN => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Window open temperature'
                ]
            ],
            DeviceType::HeatingGroup => [
                HeatingGroup::WEEK_PROGRAM_POINTER => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::ProfileHM,
                    true,
                    'Active profile'
                ],
                HeatingGroup::DECALCIFICATION_TIME=> [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Time,
                    true,
                    'Decalcification time'
                ],
                // Die Werteliste der CCU beginnt bei HM-CC-RT-DN und HM-CC-VG-1 mit SATURDAY (bei HmIP mit SUNDAY).
                HeatingGroup::DECALCIFICATION_WEEKDAY=> [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::WeekdayFromSaturday,
                    true,
                    'Decalcification day'
                ],
                HeatingGroup::BOOST_POSITION=> [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Percent,
                    true,
                    'Boost valve opening'
                ],
                HeatingGroup::BOOST_TIME_PERIOD => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Minutes30Steps,
                    true,
                    'Boost duration'
                ],
                HeatingGroup::TEMPERATURE_COMFORT => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Comfort temperature'
                ],
                HeatingGroup::TEMPERATURE_LOWERING => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Lowering temperature'
                ],
                HeatingGroup::BUTTON_LOCK => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    true,
                    'Button lock'
                ],
                HeatingGroup::GLOBAL_BUTTON_LOCK => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    true,
                    'Device lock'
                ],
                HeatingGroup::MODUS_BUTTON_LOCK => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    true,
                    'Mode locked'
                ],
                HeatingGroup::BOOST_AFTER_WINDOW_OPEN => [
                    VARIABLETYPE_BOOLEAN,
                    HeatingPresentation::Switch,
                    true,
                    'Boost when window was closed'
                ],
                HeatingGroup::TEMPERATUREFALL_WINDOW_OPEN => [
                    VARIABLETYPE_FLOAT,
                    HeatingPresentation::SetPointTemperature,
                    true,
                    'Window open temperature'
                ],
                HeatingGroup::TEMPERATUREFALL_WINDOW_OPEN_TIME_PERIOD => [
                    VARIABLETYPE_INTEGER,
                    HeatingPresentation::Minutes60Steps,
                    true,
                    'Window open duration'
                ]
            ]
        ];
    }

    /**
     * Darstellungen der Messwerte von PowerMeter, Weather, RF- und WR-Interface (seit Version 3.82 statt der Systemprofile `~…`).
     * Symbol, Suffix und Nachkommastellen entsprechen den bisherigen Systemprofilen. Variablen mit Aktion erhalten keine Darstellung
     * (bisher ebenfalls kein Profil), Zahlen werden als Wertanzeige dargestellt.
     */
    class DatapointPresentation
    {
        /** Einheit der CCU (UNIT der Paramset-Beschreibung): Symbol, Suffix, Nachkommastellen. */
        public const Units = [
            'Wh'  => ['Electricity', ' Wh', 2],
            'kWh' => ['Electricity', ' kWh', 2],
            'm3'  => ['Flame', ' m³', 2],
            'W'   => ['Electricity', ' W', 1],
            'mA'  => ['Electricity', ' mA', 0],
            'A'   => ['Electricity', ' A', 1],
            'V'   => ['Electricity', ' V', 1],
            'Hz'  => ['Electricity', ' Hz', 2]
        ];

        /**
         * Datenpunkte mit eigener Darstellung, unabhängig von der Einheit der CCU: Symbol, Suffix, Nachkommastellen (bei Integer immer 0).
         * Wetterwerte laut CCU-Firmware in °C, %, lx, km/h, °, mm und Minuten.
         */
        public const Datapoints = [
            'ACTUAL_TEMPERATURE' => ['Temperature', ' °C', 1],
            'HUMIDITY'           => ['Gauge', ' %', 1],
            'ILLUMINATION'       => ['Sun', ' lx', 1],
            'WIND_SPEED'         => ['WindSpeed', ' km/h', 1],
            'WIND_DIR'           => ['WindDirection', '°', 1],
            'WIND_DIR_RANGE'     => ['WindDirection', '°', 0],
            'RAIN_COUNTER'       => ['Rainfall', ' mm', 1],
            'SUNSHINEDURATION'   => ['Sun', ' min', 0],
            'DUTY_CYCLE'         => ['Intensity', ' %', 0]
        ];

        /** Boolean-Datenpunkte: Symbol und je Wert Beschriftung und Farbe (-1 = keine). */
        public const Booleans = [
            'RAINING'   => ['Rainfall', [[false, 'No rain', -1], [true, 'Rain', 0x0000FF]]],
            'CONNECTED' => ['Warning', [[false, 'Alarm', 0xFF0000], [true, 'OK', -1]]]
        ];

        /** Datenpunkte mit Verwendung Temperatur (Wertanzeige: USAGE_TYPE 1). */
        public const TemperatureDatapoints = ['ACTUAL_TEMPERATURE'];

        /**
         * Liefert die Darstellung einer Statusvariable.
         *
         * @param string $Ident Name des Datenpunktes.
         * @param string $Unit Einheit laut CCU, leer wenn unbekannt.
         * @param int $VariableType Typ der Statusvariable.
         * @param bool $HasAction true, wenn die Variable bedienbar ist.
         * @param callable $Translate Übersetzt eine Beschriftung (string => string).
         *
         * @return array Die Darstellung, leer wenn keine passt.
         */
        public static function Get(string $Ident, string $Unit, int $VariableType, bool $HasAction, callable $Translate): array
        {
            if ($HasAction) {
                return [];
            }
            if ($VariableType == VARIABLETYPE_BOOLEAN) {
                if (!array_key_exists($Ident, self::Booleans)) {
                    return [];
                }
                [$Icon, $Entries] = self::Booleans[$Ident];
                $Options = [];
                foreach ($Entries as [$Value, $Caption, $Color]) {
                    $Options[] = [
                        'Value'              => $Value,
                        'Caption'            => $Translate($Caption),
                        'IconActive'         => false,
                        'IconValue'          => '',
                        'ColorActive'        => $Color != -1,
                        'ColorValue'         => $Color,
                        'ContentColorActive' => false,
                        'ContentColorValue'  => -1
                    ];
                }
                return ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => $Icon, 'OPTIONS' => json_encode($Options)];
            }
            if (!in_array($VariableType, [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT])) {
                return [];
            }
            $Format = self::Datapoints[$Ident] ?? self::Units[$Unit] ?? null;
            if ($Format === null) {
                return [];
            }
            [$Icon, $Suffix, $Digits] = $Format;
            $Presentation = [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'ICON'         => $Icon,
                'SUFFIX'       => $Suffix,
                'DIGITS'       => ($VariableType == VARIABLETYPE_INTEGER) ? 0 : $Digits
            ];
            if (in_array($Ident, self::TemperatureDatapoints)) {
                $Presentation['USAGE_TYPE'] = HeatingPresentation::ValueUsageTemperature;
            }
            return $Presentation;
        }

        /**
         * Beschriftungen, die übersetzt werden müssen.
         *
         * @return string[]
         */
        public static function Captions(): array
        {
            $Captions = [];
            foreach (self::Booleans as [, $Entries]) {
                foreach ($Entries as $Entry) {
                    $Captions[] = $Entry[1];
                }
            }
            return $Captions;
        }
    }
}

namespace HMExtended\Device
{
    class Property
    {
        public const EmulateStatus = 'EmulateStatus';
        public const Address = 'Address';
        public const Protocol = 'Protocol';
        public const SetPointBehavior = 'SetPointBehavior';
        public const Schedule = 'enable_SCHEDULE';
        public const ScheduleTemps = 'ScheduleTemps';
        public const Interval = 'Interval';
        /** Systemvariablen: Auswahlliste der CCU-Systemvariablen (JSON). */
        public const Variables = 'Variables';
        /** Alarmvariable: CCU-ID der Alarmvariable. */
        public const AlarmID = 'AlarmID';
    }
    /**
     * Attribute der Module (dauerhaft gespeicherter Zustand).
     */
    class Attribute
    {
        /** ReGa-ID des vom Modul angelegten CCU-Programms. */
        public const ProgramID = 'ProgramID';
        /** Adresse der Taste, die das CCU-Programm drückt (für das Zurücksetzen des Tastennamens). */
        public const ProgramKey = 'ProgramKey';
        /** Eigenschaften der gewählten Systemvariablen auf der CCU (Typ, Werteliste, Einheit). */
        public const Metadata = 'Metadata';
    }
    /**
     * Eigenschaften des Homematic Sockets (IO), welche die Module nur lesen.
     */
    class IOProperty
    {
        public const Host = 'Host';
    }
    class Timer
    {
        public const ReadRFInterfaces = 'ReadRFInterfaces';
        public const ReadWRInterface = 'ReadWRInterface';
        public const ReadSystemVariables = 'ReadSystemVariables';
    }
    class Address
    {
        public const PROGRAMS = 'PROGRAMS';
        public const RFINTERFACE = 'RFINTERFACE';
        public const WRINTERFACE = 'WRINTERFACE';
        public const REMOTESCRIPT = 'REMOTESCRIPT';
        /**
         * Pseudo-Adresse für die Nachricht des Splitters an alle RF-Interfaces. Sie enthält die Adressen aller gemeldeten Interfaces.
         */
        public const AllInterfaces = '*ALL*';
        public static $GuidToStaticAddress = [
            \HMExtended\GUID::Programme    => self::PROGRAMS,
            \HMExtended\GUID::RF_Interface => self::RFINTERFACE,
            \HMExtended\GUID::WR_Interface => self::WRINTERFACE,
            \HMExtended\GUID::RemoteScript => self::REMOTESCRIPT
        ];
    }
}