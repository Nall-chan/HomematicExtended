<?php

declare(strict_types=1);

namespace HMExtended
{
    class GUID
    {
        public const Powermeter = '{400F9193-FE79-4086-8D76-958BF9C1B357}';
        public const Systemvariablen = '{AF50C42B-7183-4992-B04A-FAFB07BB1B90}';
        public const Programme = '{A5010577-C443-4A85-ABF2-3F2D6CDD2465}';
        public const Dis_WM55 = '{271BCAB1-0658-46D9-A164-985AEB641B48}';
        public const Dis_EP_WM55 = '{E64ED916-FA6C-45B2-B8E3-EDC3191BC4C0}';
        public const RF_Interface_Splitter = '{6EE35B5B-9DD9-4B23-89F6-37589134852F}';
        public const RF_Interface_Konfigurator = '{91624C6F-E67E-47DA-ADFE-9A5A1A89AAC3}';
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
    }

    class CCU
    {
        public const BidCos_RF = 'BidCos-RF';
        public const BidCos_WR = 'BidCos-WR';
        public const HmIP = 'HmIP-RF';
        public const Groups = 'VirtualDevices';
        public const MASTER = 'MASTER';
        public const VALUES = 'VALUES';
        public static $Interfaces = [
            self::BidCos_RF,
            self::BidCos_WR,
            self::HmIP,
            self::Groups
        ];
    }

    class DeviceType
    {
        public const Powermeter = 'POWERMETER'; // HM-ES-PMSw1-Pl
        public const Powermeter_IEC = 'POWERMETER_IEC2'; // HM-ES-TX-WM
        public const Dis_WM55 = 'HM-Dis-WM55'; // HM-Dis-WM55

        public const HeatingDevice = 'HM-CC-RT-DN';
        public const HeatingGroup = 'HM-CC-VG-1'; //'CLIMATECONTROL_RT_TRANSCEIVER';
        public const HeatingGroupHmIP = 'HmIP-HEATING'; //'HEATING_CLIMATECONTROL_TRANSCEIVER';
        public const ClimacontrolRegulator = 'HM-CC-TC'; //'CLIMATECONTROL_REGULATOR';

        public static $GuidToType = [
            // Konfigurator muss noch ergänzt werden um:
            //GUID::Systemvariablen                =>
            //GUID::Programme                      =>
            //GUID::WR_Interface                   =>
            // Vorhandener Konfigurator muss noch migriert werden:
            //GUID::RF_Interface_Splitter          =>
            //GUID::RF_Interface_Konfigurator      =>
            // Konfigurator 'fertig' => Aber die Instanzen sind noch nicht so weit
            GUID::Powermeter                     => [self::Powermeter, self::Powermeter_IEC],
            GUID::Dis_WM55                       => [self::Dis_WM55],
            //GUID::Dis_EP_WM55                    =>
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

        public static $Profiles = [
            'Heating.Control.SetPoint.Temperature.HM'=> [
                VARIABLETYPE_FLOAT,
                'Temperature',
                '',
                '',
                [
                    [4.5, 'Off', '', -1],
                    [5, '%.1f °C', '', -1],
                    [30.5, 'On', '', -1]
                ],
                -1,
                0.5,
                1
            ],
            'Heating.Control.Profile.HmIP'=> [
                VARIABLETYPE_INTEGER,
                'Clock',
                '',
                '',
                [
                    [1, '1', '', -1],
                    [2, '2', '', -1],
                    [3, '3', '', -1],
                    [4, '4', '', -1],
                    [5, '5', '', -1],
                    [6, '6', '', -1]
                ],
                -1,
                0,
                0
            ],
            'Heating.Control.Profile.HM'=> [
                VARIABLETYPE_INTEGER,
                'Clock',
                '',
                '',
                [
                    [1, '1', '', -1],
                    [2, '2', '', -1],
                    [3, '3', '', -1]
                ],
                -1,
                0,
                0
            ],
            'DateTime.Time.Seconds.HM'        => [
                VARIABLETYPE_INTEGER,
                'Clock',
                '',
                ' seconds',
                0,
                0,
                0,
                0

            ],
            'Heating.Control.Mode.HM'=> [
                VARIABLETYPE_INTEGER,
                '',
                '',
                '',
                [
                    [0, 'Heat', 'Flame', 0xFF0000],
                    [1, 'Cool', 'Snowflake', 0x0000FF],
                ],
                -1,
                0,
                0
            ],
            'Heating.Control.SetPoint.Mode.HmIP'=> [
                VARIABLETYPE_INTEGER,
                '',
                '',
                '',
                [
                    [0, 'Automatic', 'Clock', 0x339966],
                    [1, 'Manually', 'Execute', 0xFF0000],
                    [2, 'Holiday/Party', 'Party', 0x3366FF]
                ],
                -1,
                0,
                0
            ],
            'Heating.Control.SetPoint.Mode.HM'=> [
                VARIABLETYPE_INTEGER,
                '',
                '',
                '',
                [
                    [0, 'Automatic', 'Clock', 0x339966],
                    [1, 'Manually', 'Execute', 0xFF0000],
                    [2, 'Holiday/Party', 'Party', 0x3366FF],
                    [3, 'Boost', 'Flame', 0xFFFF99]
                ],
                -1,
                0,
                0
            ],
            'Heating.Control.SetPoint.Mode.HM-CC-TC'=> [
                VARIABLETYPE_INTEGER,
                '',
                '',
                '',
                [
                    [0, 'Manually', 'Execute', 0xFF0000],
                    [1, 'Automatic', 'Clock', 0x339966],
                    [2, 'Central', 'Remote', 0xff9900],
                    [3, 'Holiday/Party', 'Party', 0x3366FF]
                ],
                -1,
                0,
                0
            ],
            'Heating.Control.Boost.State.HM'=> [
                VARIABLETYPE_BOOLEAN,
                '',
                '',
                '',
                [
                    [false, 'Off', '', -1],
                    [true, 'On', 'Flame', 0xFFFF99]
                ],
                -1,
                0,
                0
            ],
            'Window.Open.State.HM.Reversed'=> [
                VARIABLETYPE_INTEGER,
                'Window',
                '',
                '',
                [
                    [0, 'Closed', '', -1],
                    [1, 'Open', '', 0x0000FF]
                ],
                -1,
                0,
                0
            ],
            'Heating.Valve.State.HmIP'=> [
                VARIABLETYPE_INTEGER,
                'Gear',
                '',
                '',
                [
                    [0, 'Status unknown', '', -1],
                    [1, 'not installed', '', -1],
                    [2, 'wait for adaptation', '', -1],
                    [3, 'Adaptation in progress', '', -1],
                    [4, 'Adaptation done', '', -1],
                    [5, 'Valve too tight', '', -1],
                    [6, 'Range too big', '', -1],
                    [7, 'Range too small', '', -1],
                    [8, 'Error position', '', -1]
                ],
                -1,
                0,
                0
            ],
            'DateTime.DoW.Saturday'=> [
                VARIABLETYPE_INTEGER,
                'Calendar',
                '',
                '',
                [
                    [0, 'Saturday', '', -1],
                    [1, 'Sunday', '', -1],
                    [2, 'Monday', '', -1],
                    [3, 'Tuesday', '', -1],
                    [4, 'Wednesday', '', -1],
                    [5, 'Thursday', '', -1],
                    [6, 'Friday', '', -1]
                ],
                -1,
                0,
                0
            ],
            'DateTime.DoW.Sunday'=> [
                VARIABLETYPE_INTEGER,
                'Calendar',
                '',
                '',
                [
                    [0, 'Sunday', '', -1],
                    [1, 'Monday', '', -1],
                    [2, 'Tuesday', '', -1],
                    [3, 'Wednesday', '', -1],
                    [4, 'Thursday', '', -1],
                    [5, 'Friday', '', -1],
                    [6, 'Saturday', '', -1]
                ],
                -1,
                0,
                0
            ],
            'Heating.Control.Valve.Mode.HM'=> [
                VARIABLETYPE_INTEGER,
                'Gear',
                '',
                '',
                [
                    [0, 'Automatic', 'Clock', 0x339966],
                    [1, 'Closed', 'Execute', 0x0000FF],
                    [2, 'Open', 'Party', 0xFF0000]
                ],
                -1,
                0,
                0
            ],
            'DateTime.Time.Minutes.30'=> [
                VARIABLETYPE_INTEGER,
                'Clock',
                '',
                ' minutes',
                0,
                30,
                1,
                0
            ],
            'DateTime.Time.Minutes.30.Steps'=> [
                VARIABLETYPE_INTEGER,
                'Clock',
                '',
                ' minutes',
                0,
                30,
                5,
                0
            ],
            'DateTime.Time.Minutes.60.Steps'=> [
                VARIABLETYPE_INTEGER,
                'Clock',
                '',
                ' minutes',
                0,
                60,
                5,
                0
            ],
            'Temperature.Room.Lowering.HmIP'=> [
                VARIABLETYPE_FLOAT,
                'Temperature',
                '',
                ' °C',
                5,
                25,
                0.5,
                1
            ],
            'Temperature.Room.Comfort.HmIP'=> [
                VARIABLETYPE_FLOAT,
                'Temperature',
                '',
                ' °C',
                15,
                30,
                0.5,
                1
            ],
            'Execute.HM' => [
                VARIABLETYPE_INTEGER,
                '',
                '',
                '',
                [
                    [0, 'Start', '', -1]
                ],
                -1,
                0,
                0
            ]
        ];
    }

    class ValuesSet
    {
        public static $Variables = [
            DeviceType::ClimacontrolRegulator => [
                ClimacontrolRegulator::SETPOINT => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
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
            ],
            DeviceType::HeatingGroupHmIP => [
                HeatingGroupHmIP::ACTIVE_PROFILE => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.Profile.HmIP',
                    true,
                    'Active profile'
                ],
                HeatingGroupHmIP::ACTUAL_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature',
                    false,
                    'Temperature'
                ],
                HeatingGroupHmIP::ACTUAL_TEMPERATURE_STATUS => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::BOOST_MODE => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Boost'
                ],
                HeatingGroupHmIP::BOOST_TIME => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.Time.Seconds.HM',
                    false,
                    'Boost remaining time'
                ],
                HeatingGroupHmIP::FROST_PROTECTION => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    false,
                    'Frost protection'
                ],
                HeatingGroupHmIP::HEATING_COOLING => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.Mode.HM',
                    true,
                    'Operation mode'
                ],
                HeatingGroupHmIP::HUMIDITY => [
                    VARIABLETYPE_INTEGER,
                    '~Humidity',
                    false,
                    'Humidity'
                ],
                HeatingGroupHmIP::HUMIDITY_STATUS => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::LEVEL => [
                    VARIABLETYPE_FLOAT,
                    '~Intensity.1',
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
                    '~Temperature.HM',
                    true,
                    'Holiday/Party set point temperature',
                    HeatingGroupHmIP::PARTY
                ],
                HeatingGroupHmIP::PARTY_TIME_END => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
                    true,
                    'Holiday/Party end',
                    HeatingGroupHmIP::PARTY
                ],
                HeatingGroupHmIP::PARTY_TIME_START => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
                    true,
                    'Holiday/Party start',
                    HeatingGroupHmIP::PARTY
                ],
                HeatingGroupHmIP::QUICK_VETO_TIME => [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::SET_POINT_MODE => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.SetPoint.Mode.HmIP',
                    HeatingGroupHmIP::CONTROL_MODE,
                    'Target temperature mode'
                ],
                HeatingGroupHmIP::CONTROL_MODE=> [
                    Variables::VARIABLETYPE_NONE
                ],
                HeatingGroupHmIP::SET_POINT_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    'Heating.Control.SetPoint.Temperature.HM',
                    true,
                    'Target temperature'
                ],
                HeatingGroupHmIP::SWITCH_POINT_OCCURED => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    false,
                    'Switch point occurred'
                ],
                HeatingGroupHmIP::VALVE_ADAPTION => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Valve adaption'
                ],
                HeatingGroupHmIP::VALVE_STATE => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Valve.State.HmIP',
                    false,
                    'Valve state'
                ],
                HeatingGroupHmIP::WINDOW_STATE => [
                    VARIABLETYPE_INTEGER,
                    'Window.Open.State.HM.Reversed',
                    true,
                    'Window state'
                ]
            ],
            DeviceType::HeatingGroup => [
                HeatingGroup::ACTUAL_HUMIDITY => [
                    VARIABLETYPE_INTEGER,
                    '~Humidity',
                    false,
                    'Humidity'
                ],
                HeatingGroup::ACTUAL_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature',
                    false,
                    'Temperature'
                ],
                HeatingGroup::SET_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    'Heating.Control.SetPoint.Temperature.HM',
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
                    'Heating.Control.SetPoint.Mode.HM',
                    true,
                    'Target temperature mode'
                ],
                HeatingGroup::PARTY_TEMPERATURE => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Holiday/Party set point temperature',
                    HeatingGroup::PARTY
                ],

                HeatingGroup::PARTY_START_TIME => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
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
                    '~UnixTimestamp',
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
                    'Execute.HM',
                    true,
                    'Set to comfort temperature'
                ],
                HeatingGroup::LOWERING_MODE => [
                    VARIABLETYPE_INTEGER,
                    'Execute.HM',
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
                    'Heating.Control.SetPoint.Mode.HM-CC-TC',
                    true,
                    'Operation mode'
                ],
                ClimacontrolRegulator::DECALCIFICATION_DAY => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.DoW.Saturday',
                    true,
                    'Decalcification day'
                ],
                ClimacontrolRegulator::DECALCIFICATION_TIME => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestampTime',
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
                    'Heating.Control.Valve.Mode.HM',
                    true,
                    'Valve mode'
                ],
                ClimacontrolRegulator::TEMPERATUR_LOWERING_VALUE=> [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Lowering temperature'
                ],
                ClimacontrolRegulator::TEMPERATUR_COMFORT_VALUE => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Comfort temperature'
                ],
                ClimacontrolRegulator::PARTY_END_TIME => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
                    true,
                    'Holiday/Party end',
                    true
                ],
                ClimacontrolRegulator::TEMPERATUR_PARTY_VALUE => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Holiday/Party set point temperature',
                    true
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
                    '~UnixTimestampTime',
                    true,
                    'Decalcification time'
                ],
                HeatingGroupHmIP::DECALCIFICATION_WEEKDAY=> [
                    VARIABLETYPE_INTEGER,
                    'DateTime.DoW.Sunday',
                    true,
                    'Decalcification day'
                ],
                HeatingGroupHmIP::BOOST_POSITION=> [
                    VARIABLETYPE_INTEGER,
                    '~Intensity.100',
                    true,
                    'Boost valve opening'
                ],
                HeatingGroupHmIP::BOOST_TIME_PERIOD => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.Time.Minutes.30',
                    true,
                    'Boost duration'
                ],
                HeatingGroupHmIP::TEMPERATURE_COMFORT => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Comfort.HmIP',
                    true,
                    'Comfort temperature'
                ],
                HeatingGroupHmIP::TEMPERATURE_COMFORT_COOLING => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Lowering.HmIP',
                    true,
                    'Comfort temperature cooling'
                ],
                HeatingGroupHmIP::TEMPERATURE_LOWERING => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Lowering.HmIP',
                    true,
                    'Lowering temperature'
                ],
                HeatingGroupHmIP::TEMPERATURE_LOWERING_COOLING => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Comfort.HmIP',
                    true,
                    'Lowering temperature cooling'
                ],
                HeatingGroupHmIP::TEMPERATURE_WINDOW_OPEN => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Window open temperature'
                ]
            ],
            DeviceType::HeatingGroup => [
                HeatingGroup::WEEK_PROGRAM_POINTER => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.Profile.HM',
                    true,
                    'Active profile'
                ],
                HeatingGroup::DECALCIFICATION_TIME=> [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestampTime',
                    true,
                    'Decalcification time'
                ],
                HeatingGroup::DECALCIFICATION_WEEKDAY=> [
                    VARIABLETYPE_INTEGER,
                    'DateTime.DoW.Sunday',
                    true,
                    'Decalcification day'
                ],
                HeatingGroup::BOOST_POSITION=> [
                    VARIABLETYPE_INTEGER,
                    '~Intensity.100',
                    true,
                    'Boost valve opening'
                ],
                HeatingGroup::BOOST_TIME_PERIOD => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.Time.Minutes.30.Steps',
                    true,
                    'Boost duration'
                ],
                HeatingGroup::TEMPERATURE_COMFORT => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Comfort.HmIP',
                    true,
                    'Comfort temperature'
                ],
                HeatingGroup::TEMPERATURE_LOWERING => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Lowering.HmIP',
                    true,
                    'Lowering temperature'
                ],
                HeatingGroup::BUTTON_LOCK => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Button lock'
                ],
                HeatingGroup::GLOBAL_BUTTON_LOCK => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Device lock'
                ],
                HeatingGroup::MODUS_BUTTON_LOCK => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Mode locked'
                ],
                HeatingGroup::BOOST_AFTER_WINDOW_OPEN => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Boost when window was closed'
                ],
                HeatingGroup::TEMPERATUREFALL_WINDOW_OPEN => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Window open temperature'
                ],
                HeatingGroup::TEMPERATUREFALL_WINDOW_OPEN_TIME_PERIOD => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.Time.Minutes.60.Steps',
                    true,
                    'Window open duration'
                ]
            ]
        ];
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
    }
}