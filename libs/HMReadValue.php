<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMReadValue.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.90
 */

/**
 * Lesen eines einzelnen Datenpunktes in eine Statusvariable für die Instanz-Funktion `HM_RequestState` der Module,
 * deren Statusvariablen Datenpunkte (Paramset VALUES) der CCU sind.
 */
trait HMReadValueFunctions
{
    /**
     * Liest einen Datenpunkt von der CCU (RPC getValue) und schreibt ihn in die Statusvariable.
     * Fehler werden per echo gemeldet.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param string $AddressWithChannel Adresse des Kanals.
     * @param string $Datapoint Name des Datenpunktes.
     *
     * @return bool true, wenn die CCU den Wert geliefert hat.
     */
    protected function ReadDatapointToVariable(string $Ident, string $AddressWithChannel, string $Datapoint): bool
    {
        $VariableId = $this->FindIDForIdent($Ident);
        if ($VariableId == 0) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return false;
        }
        $Value = $this->SendRPC('getValue', $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol), [$AddressWithChannel, $Datapoint]);
        if ($Value === null) {
            return false;
        }
        // Ohne Antwortdaten liefert SendRPC true: der Datenpunkt ist nicht lesbar
        if (($Value === true) && (IPS_GetVariable($VariableId)['VariableType'] != VARIABLETYPE_BOOLEAN)) {
            echo sprintf($this->Translate('The value of %s could not be read.'), $Ident) . PHP_EOL;
            return false;
        }
        $this->SetValue($Ident, match (IPS_GetVariable($VariableId)['VariableType']) {
            VARIABLETYPE_BOOLEAN => (bool) $Value,
            VARIABLETYPE_INTEGER => (int) $Value,
            VARIABLETYPE_FLOAT   => (float) $Value,
            default              => (string) $Value
        });
        return true;
    }
}

/* @} */
