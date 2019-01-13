<?php

declare(strict_types = 1);
/**
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          ConstHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 */

if (!defined("vtBoolean")) { //Nur wenn Konstanten noch nicht bekannt sind.
    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
}
