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
 * @version       4.02
 */

namespace HMExtended
{
    /**
     * Versionsgrenze der Funktionen HM_WriteValue… (ohne 2).
     */
    final class NativeWriteValue
    {
        /**
         * Symcon-Version, ab der die Funktionen HM_WriteValue… (ohne 2) die gleichnamigen Funktionen des Kernels überschreiben können.
         * Bis dahin (alle C++-Versionen) sind HM_WriteValue…2 die gültigen Funktionen und erzeugen keine Warnung (Vorgabe des Nutzers).
         */
        public const Version = '10.0';

        /**
         * True, wenn die Funktionen ohne 2 deklariert werden: ab Symcon 10.0 und in den Unit-Tests (PHPUNIT_TESTSUITE), damit die Tests
         * alle Instanz-Funktionen prüfen.
         */
        public static function Declare(): bool
        {
            return defined('PHPUNIT_TESTSUITE') || version_compare(IPS_GetKernelVersion(), self::Version, '>=');
        }
    }
}

namespace {
    /*
     * Der C++-Kernel (alle Versionen vor 10.0) kann die Funktionen HM_WriteValue… des Standardmoduls nicht durch ein Modul ersetzen
     * und meldet beim Laden jeder Library je Modul und Funktion „Override of native function … is not implemented“. Der Trait
     * HMNativeWriteValueFunctions enthält die Funktionen daher nur ab Symcon 10.0, davor ist er leer. Die Entscheidung fällt beim
     * Laden der Datei.
     */
    if (\HMExtended\NativeWriteValue::Declare()) {
        /**
         * Instanz-Funktionen `HM_WriteValueBoolean`, `…Integer`, `…Float` und `…String` (ab Symcon 10.0).
         * Sie rufen WriteValueOfType der nutzenden Klasse auf.
         */
        trait HMNativeWriteValueFunctions
        {
            /**
             * IPS-Instanz-Funktion 'HM_WriteValueBoolean'.
             * Schreibt einen Wert vom Typ Boolean.
             *
             * @param string $Parameter Ident der Statusvariable.
             * @param bool $Value Der Wert.
             *
             * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
             */
            public function WriteValueBoolean(string $Parameter, bool $Value): bool
            {
                return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_BOOLEAN);
            }

            /**
             * IPS-Instanz-Funktion 'HM_WriteValueInteger'.
             * Schreibt einen Wert vom Typ Integer (auch Aufzählungen und Wertelisten).
             *
             * @param string $Parameter Ident der Statusvariable.
             * @param int $Value Der Wert.
             *
             * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
             */
            public function WriteValueInteger(string $Parameter, int $Value): bool
            {
                return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_INTEGER);
            }

            /**
             * IPS-Instanz-Funktion 'HM_WriteValueFloat'.
             * Schreibt einen Wert vom Typ Float.
             *
             * @param string $Parameter Ident der Statusvariable.
             * @param float $Value Der Wert.
             *
             * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
             */
            public function WriteValueFloat(string $Parameter, float $Value): bool
            {
                return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_FLOAT);
            }

            /**
             * IPS-Instanz-Funktion 'HM_WriteValueString'.
             * Schreibt einen Wert vom Typ String.
             *
             * @param string $Parameter Ident der Statusvariable.
             * @param string $Value Der Wert.
             *
             * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
             */
            public function WriteValueString(string $Parameter, string $Value): bool
            {
                return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_STRING);
            }
        }
    } else {
        /**
         * Vor Symcon 10.0 leer, siehe oben.
         */
        trait HMNativeWriteValueFunctions
        {
        }
    }

    /**
     * Instanz-Funktionen `HM_WriteValueBoolean2`, `…Integer2`, `…Float2` und `…String2` und ab Symcon 10.0 die Funktionen ohne `2`.
     * Die nutzende Klasse schreibt den Wert in WriteValueOfType. Genutzt von HMWriteValueFunctions und den Systemvariablen.
     *
     * Alle Instanz-Funktionen gleichen Namens müssen in Symcon dieselbe Signatur haben, die Signaturen entsprechen daher denen des
     * Standardmoduls (Ident als string, der Wert in seinem Typ, Rückgabe bool).
     *
     * Vor Symcon 10.0 sind `HM_WriteValue…2` die gültigen Funktionen, sie melden nichts im Log. Ab 10.0 gelten die Namen ohne `2`,
     * dann melden die Funktionen mit `2` im Log, dass sie veraltet sind.
     */
    trait HMWriteValueAliasFunctions
    {
        use HMNativeWriteValueFunctions;

        /**
         * IPS-Instanz-Funktion 'HM_WriteValueBoolean2'. Ab Symcon 10.0 veraltet, dann gilt HM_WriteValueBoolean.
         */
        public function WriteValueBoolean2(string $Parameter, bool $Value): bool
        {
            $this->LogWriteValueAlias('HM_WriteValueBoolean2', 'HM_WriteValueBoolean');
            return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_BOOLEAN);
        }

        /**
         * IPS-Instanz-Funktion 'HM_WriteValueInteger2'. Ab Symcon 10.0 veraltet, dann gilt HM_WriteValueInteger.
         */
        public function WriteValueInteger2(string $Parameter, int $Value): bool
        {
            $this->LogWriteValueAlias('HM_WriteValueInteger2', 'HM_WriteValueInteger');
            return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_INTEGER);
        }

        /**
         * IPS-Instanz-Funktion 'HM_WriteValueFloat2'. Ab Symcon 10.0 veraltet, dann gilt HM_WriteValueFloat.
         */
        public function WriteValueFloat2(string $Parameter, float $Value): bool
        {
            $this->LogWriteValueAlias('HM_WriteValueFloat2', 'HM_WriteValueFloat');
            return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_FLOAT);
        }

        /**
         * IPS-Instanz-Funktion 'HM_WriteValueString2'. Ab Symcon 10.0 veraltet, dann gilt HM_WriteValueString.
         */
        public function WriteValueString2(string $Parameter, string $Value): bool
        {
            $this->LogWriteValueAlias('HM_WriteValueString2', 'HM_WriteValueString');
            return $this->WriteValueOfType($Parameter, $Value, VARIABLETYPE_STRING);
        }

        /**
         * Schreibt einen Wert, nachdem die Instanz-Funktion den Typ festgelegt hat.
         *
         * @param string $Ident Ident der Statusvariable.
         * @param mixed $Value Der Wert.
         * @param int $Type Typ der Variable, den die aufgerufene Funktion erwartet.
         *
         * @return bool true bei Erfolg (Fehler meldet die Funktion per echo).
         */
        abstract protected function WriteValueOfType(string $Ident, mixed $Value, int $Type): bool;

        /**
         * True ab Symcon 10.0: Erst dann können die Funktionen HM_WriteValue… (ohne 2) genutzt werden.
         */
        protected function HasNativeWriteValue(): bool
        {
            return version_compare(IPS_GetKernelVersion(), \HMExtended\NativeWriteValue::Version, '>=');
        }

        /**
         * Meldet den Aufruf von HM_WriteValue…2 im Log, aber erst ab Symcon 10.0. Davor gibt es keinen Ersatz.
         */
        private function LogWriteValueAlias(string $Old, string $New): void
        {
            if (!$this->HasNativeWriteValue()) {
                return;
            }
            $this->LogMessage(sprintf($this->Translate('%s is deprecated as of Symcon %s, use %s.'), $Old, \HMExtended\NativeWriteValue::Version, $New), KL_WARNING);
        }
    }

    /**
     * Instanz-Funktionen `HM_WriteValue…` für Module mit bedienbaren Statusvariablen.
     * Wie beim Standardmodul von Symcon schreiben sie einen Wert, hier aber über den Ident der Statusvariable und nicht über den Namen
     * des Datenpunktes. Die Funktionen prüfen Ident, Bedienbarkeit und Datentyp und rufen dann die Bedienung der Variable auf
     * (`RequestAction`), die den Wert an die CCU sendet. Es gibt dadurch nur einen Weg zum Schreiben.
     */
    trait HMWriteValueFunctions
    {
        use HMWriteValueAliasFunctions;

        /**
         * Prüft Ident, Bedienbarkeit und Datentyp der Statusvariable und führt die Bedienung der Variable aus.
         *
         * @param string $Ident Ident der Statusvariable.
         * @param mixed $Value Der Wert.
         * @param int $Type Typ der Variable, den die aufgerufene Funktion erwartet.
         *
         * @return bool true bei Erfolg.
         */
        protected function WriteValueOfType(string $Ident, mixed $Value, int $Type): bool
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
    }
}

/* @} */
