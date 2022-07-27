<?php

declare(strict_types=1);

namespace HMExtended;

trait HMTypes
{
    protected static $VariabeProfiles = [
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

    protected static $Variables = [
        'CLIMATECONTROL_REGULATOR'=> [
            'SETPOINT' => [
                VARIABLETYPE_FLOAT,
                'Heating.Control.SetPoint.Temperature.HmIP',
                true,
                'Setpoint temperature'
            ],
            'ADJUSTING_DATA'=> [
                parent::VARIABLETYPE_NONE
            ],
            'ADJUSTING_COMMAND'=> [
                parent::VARIABLETYPE_NONE
            ],
            'STATE' => [
                parent::VARIABLETYPE_NONE
            ],
        ],
        'HEATING_CLIMATECONTROL_TRANSCEIVER' => [
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
                parent::VARIABLETYPE_NONE
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
                parent::VARIABLETYPE_NONE
            ],
            'LEVEL' => [
                VARIABLETYPE_FLOAT,
                '~Intensity.1',
                true,
                'Valve opening',
                true
            ],
            'LEVEL_STATUS' => [
                parent::VARIABLETYPE_NONE
            ],
            'PARTY_MODE' => [
                parent::VARIABLETYPE_NONE
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
                parent::VARIABLETYPE_NONE
            ],
            'SET_POINT_MODE' => [
                VARIABLETYPE_INTEGER,
                'Heating.Control.SetPoint.Mode.HM',
                'CONTROL_MODE',
                'Target temperature mode',
                true

            ],
            'CONTROL_MODE'=> [
                parent::VARIABLETYPE_NONE
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
            ]]
    ];
    protected static $Parameters = [
        'CLIMATECONTROL_REGULATOR'=> [
            'MODE_TEMPERATUR_REGULATOR' => [
                VARIABLETYPE_INTEGER,
                'Heating.Control.SetPoint.Mode.HM-CC-TC',
                true,
                'Operation mode'
            ],
            'DECALCIFICATION_DAY'=> [
                VARIABLETYPE_INTEGER,
                'DateTime.DoW.Saturday',
                true,
                'Decalcification day',
                false
            ],
            'DECALCIFICATION_TIME'=> [
                VARIABLETYPE_INTEGER,
                '~UnixTimestampTime',
                true,
                'Decalcification time',
                false
            ],
            'DECALCIFICATION_HOUR'=> [
                parent::VARIABLETYPE_NONE,
                '',
                'DECALCIFICATION_TIME'
            ],
            'MODE_TEMPERATUR_VALVE'=> [
                VARIABLETYPE_INTEGER,
                'Heating.Control.Valve.Mode.HM',
                true,
                'Valve mode',
                false
            ],
            'TEMPERATUR_LOWERING_VALUE'=> [
                VARIABLETYPE_FLOAT,
                '~Temperature.HM',
                true,
                'Lowering temperature',
                true
            ],
            'TEMPERATUR_COMFORT_VALUE' => [
                VARIABLETYPE_FLOAT,
                '~Temperature.HM',
                true,
                'Comfort temperature',
                true
            ],
            'PARTY_END_TIME'=> [
                VARIABLETYPE_INTEGER,
                '~UnixTimestamp',
                true,
                'Holiday/Party end',
                true
            ],
            'PARTY_END_DAY'            => [
                parent::VARIABLETYPE_NONE,
                '',
                'PARTY_END_TIME'
            ],
            'PARTY_END_HOUR'           => [
                parent::VARIABLETYPE_NONE
            ],
            'PARTY_END_MINUTE'         => [
                parent::VARIABLETYPE_NONE
            ]
        ],
        'HEATING_CLIMATECONTROL_TRANSCEIVER' => [
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

        ]
    ];
}