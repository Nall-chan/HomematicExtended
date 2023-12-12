<?php

declare(strict_types=1);

namespace HMExtended
{
    class GUID
    {
        public const Systemvariablen = '{400F9193-FE79-4086-8D76-958BF9C1B357}';
        public const Powermeter = '{AF50C42B-7183-4992-B04A-FAFB07BB1B90}';
        public const Programme = '{A5010577-C443-4A85-ABF2-3F2D6CDD2465}';
        public const Dis_WM55 = '{271BCAB1-0658-46D9-A164-985AEB641B48}';
        public const Dis_EP_WM55 = '{E64ED916-FA6C-45B2-B8E3-EDC3191BC4C0}';
        public const RF_Interface_Splitter = '{6EE35B5B-9DD9-4B23-89F6-37589134852F}';
        public const RF_Interface_Konfigurator = '{91624C6F-E67E-47DA-ADFE-9A5A1A89AAC3}';
        public const WR_Interface = '{01C66202-7E94-49C4-8D8F-6A75CE944E87}';
        public const HeatingGroup = '{F179857C-DF5A-2CED-F553-CDB4D42815ED}';
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
        public const BidCos_WR = 'BidCos-Wired';
        public const HmIP = 'HmIP-RF';
        public const Groups = 'VirtualDevices';

        public static $Interfaces = [
            self::BidCos_RF,
            self::BidCos_WR,
            self::HmIP,
            self::Groups
        ];
    }
    class DeviceType
    {
        public const HeatingGroup = 'HM-CC-VG-1'; //'CLIMATECONTROL_RT_TRANSCEIVER';
        public const HeatingGroupHmIP = 'HmIP-HEATING'; //'HEATING_CLIMATECONTROL_TRANSCEIVER';
        public const ClimacontrolRegulator = 'HM-CC-TC'; //'CLIMATECONTROL_REGULATOR';

        public static $GuidToType = [
            /*GUID::Systemvariablen                =>
            GUID::Powermeter                     =>
            GUID::Programme                      =>
            GUID::Dis_WM55                       =>
            GUID::Dis_EP_WM55                    =>
            GUID::RF_Interface_Splitter          =>
            GUID::RF_Interface_Konfigurator      =>
            GUID::WR_Interface                   => */
            GUID::HeatingGroup                  => self::HeatingGroup,
            GUID::HeatingGroupHmIP              => self::HeatingGroupHmIP,
            GUID::ClimacontrolRegulator         => self::ClimacontrolRegulator
        ];
    }

    class Channels
    {
        public const Device = '';
        public const First = ':1';
        public const Second = ':2';
    }
    class ClimacontrolRegulator
    {
        public const SETPOINT = 'SETPOINT';
        public const ADJUSTING_DATA = 'ADJUSTING_DATA';
        public const ADJUSTING_COMMAND = 'ADJUSTING_COMMAND';
        public const STATE = 'STATE';
        public const MODE_TEMPERATUR_REGULATOR = 'MODE_TEMPERATUR_REGULATOR';
        public const DECALCIFICATION_DAY = 'DECALCIFICATION_DAY';
        public const DECALCIFICATION_TIME = 'DECALCIFICATION_TIME';
        public const DECALCIFICATION_HOUR = 'DECALCIFICATION_HOUR';
        public const MODE_TEMPERATUR_VALVE = 'MODE_TEMPERATUR_VALVE';
        public const TEMPERATUR_LOWERING_VALUE = 'TEMPERATUR_LOWERING_VALUE';
        public const TEMPERATUR_COMFORT_VALUE = 'TEMPERATUR_COMFORT_VALUE';
        public const PARTY_END_TIME = 'PARTY_END_TIME';
        public const PARTY_END_DAY = 'PARTY_END_DAY';
        public const PARTY_END_HOUR = 'PARTY_END_HOUR';
        public const PARTY_END_MINUTE = 'PARTY_END_MINUTE';
    }
    class HeatingGroupHmIP
    {
    }
    class Variables
    {
        public const VARIABLETYPE_NONE = -1;

        public static $Profiles = [
            'Heating.Control.SetPoint.Temperature.HmIP'=> [
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
                1,
                6,
                1,
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

            'Heating.Control.SetPoint.Mode.HM'=> [
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
        ];
    }

    class ValuesSet
    {
        public static $Variables = [
            DeviceType::ClimacontrolRegulator => [
                ClimacontrolRegulator::SETPOINT => [
                    VARIABLETYPE_FLOAT,
                    'Heating.Control.SetPoint.Temperature.HmIP',
                    true,
                    'Setpoint temperature'
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
                'ACTIVE_PROFILE' => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.Profile.HmIP',
                    true,
                    'Active profile',
                    true
                ],
                'ACTUAL_TEMPERATURE' => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature',
                    false,
                    'Temperature'
                ],
                'ACTUAL_TEMPERATURE_STATUS' => [
                    Variables::VARIABLETYPE_NONE
                ],
                'BOOST_MODE' => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Boost',
                    true
                ],
                'BOOST_TIME' => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.Time.Seconds.HM',
                    false,
                    'Boost remaining time',
                    false
                ],
                'FROST_PROTECTION' => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    false,
                    'Frost protection',
                    false
                ],
                'HEATING_COOLING' => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.Mode.HM',
                    true,
                    'Operation mode',
                    false
                ],
                'HUMIDITY' => [
                    VARIABLETYPE_INTEGER,
                    '~Humidity',
                    false,
                    'Humidity'
                ],
                'HUMIDITY_STATUS' => [
                    Variables::VARIABLETYPE_NONE
                ],
                'LEVEL' => [
                    VARIABLETYPE_FLOAT,
                    '~Intensity.1',
                    true,
                    'Valve opening',
                    true
                ],
                'LEVEL_STATUS' => [
                    Variables::VARIABLETYPE_NONE
                ],
                'PARTY_MODE' => [
                    Variables::VARIABLETYPE_NONE
                ],
                'PARTY_SET_POINT_TEMPERATURE' => [
                    VARIABLETYPE_FLOAT,
                    'Heating.Control.SetPoint.Temperature.HmIP',
                    true,
                    'Holiday/Party set point temperature',
                    true
                ],
                'PARTY_TIME_END' => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
                    true,
                    'Holiday/Party end',
                    true
                ],
                'PARTY_TIME_START' => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
                    true,
                    'Holiday/Party start',
                    true
                ],
                'QUICK_VETO_TIME' => [
                    Variables::VARIABLETYPE_NONE
                ],
                'SET_POINT_MODE' => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.SetPoint.Mode.HM',
                    'CONTROL_MODE',
                    'Target temperature mode',
                    true
                ],
                'CONTROL_MODE'=> [
                    Variables::VARIABLETYPE_NONE
                ],
                'SET_POINT_TEMPERATURE' => [
                    VARIABLETYPE_FLOAT,
                    'Heating.Control.SetPoint.Temperature.HmIP',
                    true,
                    'Setpoint temperature'
                ],
                'SWITCH_POINT_OCCURED' => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    false,
                    'Switch point occurred',
                    false
                ],
                'VALVE_ADAPTION' => [
                    VARIABLETYPE_BOOLEAN,
                    '~Switch',
                    true,
                    'Valve adaption',
                    false
                ],
                'VALVE_STATE' => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Valve.State.HmIP',
                    false,
                    'Valve state',
                    true
                ],
                'WINDOW_STATE' => [
                    VARIABLETYPE_INTEGER,
                    'Window.Open.State.HM.Reversed',
                    true,
                    'Window state',
                    true
                ]
            ],
            DeviceType::HeatingGroup=> [
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
                    'Decalcification day',
                    false
                ],
                ClimacontrolRegulator::DECALCIFICATION_TIME => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestampTime',
                    true,
                    'Decalcification time',
                    false
                ],
                ClimacontrolRegulator::DECALCIFICATION_HOUR => [
                    Variables::VARIABLETYPE_NONE,
                    '',
                    'DECALCIFICATION_TIME'
                ],
                ClimacontrolRegulator::MODE_TEMPERATUR_VALVE => [
                    VARIABLETYPE_INTEGER,
                    'Heating.Control.Valve.Mode.HM',
                    true,
                    'Valve mode',
                    false
                ],
                ClimacontrolRegulator::TEMPERATUR_LOWERING_VALUE=> [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Lowering temperature',
                    true
                ],
                ClimacontrolRegulator::TEMPERATUR_COMFORT_VALUE => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Comfort temperature',
                    true
                ],
                ClimacontrolRegulator::PARTY_END_TIME => [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestamp',
                    true,
                    'Holiday/Party end',
                    true
                ],
                ClimacontrolRegulator::PARTY_END_DAY => [
                    Variables::VARIABLETYPE_NONE,
                    '',
                    'PARTY_END_TIME'
                ],
                ClimacontrolRegulator::PARTY_END_HOUR => [
                    Variables::VARIABLETYPE_NONE
                ],
                'PARTY_END_MINUTE'         => [
                    Variables::VARIABLETYPE_NONE
                ]
            ],
            DeviceType::HeatingGroupHmIP => [
                'DECALCIFICATION_TIME'=> [
                    VARIABLETYPE_INTEGER,
                    '~UnixTimestampTime',
                    true,
                    'Decalcification time',
                    false
                ],
                'DECALCIFICATION_WEEKDAY'=> [
                    VARIABLETYPE_INTEGER,
                    'DateTime.DoW.Sunday',
                    true,
                    'Decalcification day',
                    false
                ],
                'BOOST_POSITION'=> [
                    VARIABLETYPE_INTEGER,
                    '~Intensity.100',
                    true,
                    'Boost valve opening',
                    false
                ],
                'BOOST_TIME_PERIOD' => [
                    VARIABLETYPE_INTEGER,
                    'DateTime.Time.Minutes.30',
                    true,
                    'Boost duration',
                    false
                ],
                'TEMPERATURE_COMFORT'         => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Comfort.HmIP',
                    true,
                    'Comfort temperature',
                    true
                ],
                'TEMPERATURE_COMFORT_COOLING' => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Lowering.HmIP',
                    true,
                    'Comfort temperature cooling',
                    false
                ],
                'TEMPERATURE_LOWERING'        => [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Lowering.HmIP',
                    true,
                    'Lowering temperature',
                    true
                ],
                'TEMPERATURE_LOWERING_COOLING'=> [
                    VARIABLETYPE_FLOAT,
                    'Temperature.Room.Comfort.HmIP',
                    true,
                    'Lowering temperature cooling',
                    false
                ],
                'TEMPERATURE_WINDOW_OPEN'     => [
                    VARIABLETYPE_FLOAT,
                    '~Temperature.HM',
                    true,
                    'Window open temperature',
                    true
                ]
            ],
            DeviceType::HeatingGroup=> [
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
    }

}
