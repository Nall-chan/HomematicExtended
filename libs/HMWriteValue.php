<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMWriteValue.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.83
 */

/**
 * Instanz-Funktionen `HM_WriteValueBoolean`, `…Integer`, `…Float` und `…String` für Module mit bedienbaren Statusvariablen.
 * Wie beim Standardmodul von Symcon schreiben sie einen Wert, hier aber über den Ident der Statusvariable und nicht über den Namen
 * des Datenpunktes. Die Funktionen prüfen Ident, Bedienbarkeit und Datentyp und rufen dann die Bedienung der Variable auf
 * (`RequestAction`), die den Wert an die CCU sendet. Es gibt dadurch nur einen Weg zum Schreiben.
 *
 * Alle Instanz-Funktionen gleichen Namens müssen in Symcon dieselbe Signatur haben, die Signaturen entsprechen daher denen der
 * Systemvariablen und des Standardmoduls (Ident als string, der Wert in seinem Typ, Rückgabe bool).
 *
 * Alle Symcon-Versionen mit dem C++-Kernel erlauben es nicht, die Funktionen des Standardmoduls zu überschreiben. Dort sind
 * `HM_WriteValue…2` die gültigen Funktionen, sie melden nichts im Log. Erst ab Symcon 10.0 (self::NativeWriteValueVersion) gelten die
 * Namen ohne `2`, dann melden die Funktionen mit `2` im Log, dass sie veraltet sind.
 */
trait HMWriteValueFunctions
{
    /**
     * Symcon-Version, ab der die Funktionen HM_WriteValue… (ohne 2) die gleichnamigen Funktionen des Kernels überschreiben können.
     */
    private const NativeWriteValueVersion = '10.0';

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean'.
     * Schreibt einen Wert in eine bedienbare Statusvariable vom Typ Boolean.
     *
     * @param string $Parameter Ident der Statusvariable.
     * @param bool $Value Der Wert.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function WriteValueBoolean(string $Parameter, bool $Value): bool
    {
        return $this->WriteVariableValue($Parameter, $Value, VARIABLETYPE_BOOLEAN);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger'.
     * Schreibt einen Wert in eine bedienbare Statusvariable vom Typ Integer (auch Aufzählungen).
     *
     * @param string $Parameter Ident der Statusvariable.
     * @param int $Value Der Wert.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function WriteValueInteger(string $Parameter, int $Value): bool
    {
        return $this->WriteVariableValue($Parameter, $Value, VARIABLETYPE_INTEGER);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat'.
     * Schreibt einen Wert in eine bedienbare Statusvariable vom Typ Float.
     *
     * @param string $Parameter Ident der Statusvariable.
     * @param float $Value Der Wert.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function WriteValueFloat(string $Parameter, float $Value): bool
    {
        return $this->WriteVariableValue($Parameter, $Value, VARIABLETYPE_FLOAT);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString'.
     * Schreibt einen Wert in eine bedienbare Statusvariable vom Typ String.
     *
     * @param string $Parameter Ident der Statusvariable.
     * @param string $Value Der Wert.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function WriteValueString(string $Parameter, string $Value): bool
    {
        return $this->WriteVariableValue($Parameter, $Value, VARIABLETYPE_STRING);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean2'. Ruft WriteValueBoolean auf, ab Symcon 10.0 veraltet.
     */
    public function WriteValueBoolean2(string $Parameter, bool $Value): bool
    {
        $this->LogWriteValueAlias('HM_WriteValueBoolean2', 'HM_WriteValueBoolean');
        return $this->WriteValueBoolean($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger2'. Ruft WriteValueInteger auf, ab Symcon 10.0 veraltet.
     */
    public function WriteValueInteger2(string $Parameter, int $Value): bool
    {
        $this->LogWriteValueAlias('HM_WriteValueInteger2', 'HM_WriteValueInteger');
        return $this->WriteValueInteger($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat2'. Ruft WriteValueFloat auf, ab Symcon 10.0 veraltet.
     */
    public function WriteValueFloat2(string $Parameter, float $Value): bool
    {
        $this->LogWriteValueAlias('HM_WriteValueFloat2', 'HM_WriteValueFloat');
        return $this->WriteValueFloat($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString2'. Ruft WriteValueString auf, ab Symcon 10.0 veraltet.
     */
    public function WriteValueString2(string $Parameter, string $Value): bool
    {
        $this->LogWriteValueAlias('HM_WriteValueString2', 'HM_WriteValueString');
        return $this->WriteValueString($Parameter, $Value);
    }

    /**
     * True ab Symcon 10.0: Erst dann können die Funktionen HM_WriteValue… (ohne 2) genutzt werden.
     */
    protected function HasNativeWriteValue(): bool
    {
        return version_compare(IPS_GetKernelVersion(), self::NativeWriteValueVersion, '>=');
    }

    /**
     * Prüft Ident, Bedienbarkeit und Datentyp der Statusvariable und führt die Bedienung der Variable aus.
     *
     * @param string $Ident Ident der Statusvariable.
     * @param mixed $Value Der Wert.
     * @param int $Type Typ der Variable, den die aufgerufene Funktion erwartet.
     *
     * @return bool true bei Erfolg.
     */
    private function WriteVariableValue(string $Ident, mixed $Value, int $Type): bool
    {
        $VariableId = $this->FindIDForIdent($Ident);
        if ($VariableId == 0) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return false;
        }
        if (!HasAction($VariableId)) {
            echo sprintf($this->Translate('Variable %s cannot be operated.'), $Ident) . PHP_EOL;
            return false;
        }
        if (IPS_GetVariable($VariableId)['VariableType'] != $Type) {
            echo sprintf($this->Translate('Wrong data type for %s.'), $Ident) . PHP_EOL;
            return false;
        }
        $this->RequestAction($Ident, $Value);
        return true;
    }

    /**
     * Meldet den Aufruf von HM_WriteValue…2 im Log, aber erst ab Symcon 10.0. Davor gibt es keinen Ersatz.
     */
    private function LogWriteValueAlias(string $Old, string $New): void
    {
        if (!$this->HasNativeWriteValue()) {
            return;
        }
        $this->LogMessage(sprintf($this->Translate('%s is deprecated as of Symcon %s, use %s.'), $Old, self::NativeWriteValueVersion, $New), KL_WARNING);
    }
}

/* @} */
