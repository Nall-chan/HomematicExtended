<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.73
 */
require_once __DIR__ . '/../HeatingGroup/module.php';  // HMBase Klasse

/**
 * HomeMaticHeatingGroup
 * Identisch zu HMHeatingDevice HM Gerät: HM-CC-...
 */
class HomeMaticHeatingDevice extends HomeMaticHeatingGroup
{
    protected const ValuesChannel = \HMExtended\Channels::Fourth;
    protected const ProtocolId = 0;

    protected const WeekScheduleIndexTemp = 'TEMPERATURE_%2$s_%1$d';
    protected const WeekScheduleIndexEndTime = 'ENDTIME_%2$s_%1$d';
    protected const NumberOfWeekSchedules = 1;
    protected const SelectedWeekScheduleIdent = '';
}

/* @} */