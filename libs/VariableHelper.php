<?php

/*
 * @addtogroup generic
 * @{
 *
 * @package       generic
 * @file          VariableHelper.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       5.0
 *
 */

/**
 * Ein Trait welcher es ermöglicht über einen Ident Variablen zu beschreiben.
 */
trait VariableHelper
{
    /**
     * Setzte eine IPS-Variable auf den Wert von $value
     *
     * @access protected
     * @param string $Ident Ident der Statusvariable.
     * @param bool|int|float|string $value Neuer Wert der Statusvariable.
     */
    protected function SetValue($Ident, $value)
    {
        $id = @$this->GetIDForIdent($Ident);
        if ($id > 0) {
            SetValue($id, $value);
        }
    }

}

/** @} */
