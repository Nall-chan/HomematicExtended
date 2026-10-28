<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMSystemVariables.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.80
 */

namespace HMExtended
{
    /**
     * Erzeugt die HM-Scripte für Systemvariablen, Alarmvariablen und das CCU-Programm und zerlegt deren Ausgaben (ohne Abhängigkeit zu Symcon).
     *
     * Alle Scripte geben Tabellen aus: eine Zeile je Objekt, Spalten mit Tabulator getrennt. Texte der CCU werden maskiert
     * (`\\`, `\t`, `\r`, `\n`), damit Tabulatoren und Zeilenumbrüche die Tabelle nicht zerstören. Zeilen mit `@@` am Anfang
     * enthalten Kopfdaten (z.B. die Uhrzeit der CCU oder die ID des angelegten Programms).
     *
     * Erkenntnisse von der CCU (live geprüft, siehe STATUS-Archiv):
     * - `EnumUsedIDs()` liefert keine internen (versteckten bzw. systeminternen) Objekte, daher wird `EnumIDs()` verwendet.
     * - Eine Bedingung „bei Aktualisierung“ löst das Programm aus, die Aktivität folgt aber nur, wenn die gesamte Bedingung erfüllt ist.
     *   Je Systemvariable werden deshalb zwei Bedingungen mit ODER angelegt, die zusammen jeden Wert abdecken (`=` und `≠`).
     * - Das Programm läuft asynchron, ein neu angelegtes Programm wird einmal ausgeführt.
     */
    class SystemVariables
    {
        /** ValueType der CCU */
        public const ValueTypeBool = 2;
        public const ValueTypeFloat = 4;
        public const ValueTypeInteger = 16;
        public const ValueTypeString = 20;
        /** ValueSubType der CCU für Wertelisten */
        public const SubTypeEnum = 29;
        /** Type der CCU für Alarmvariablen */
        public const TypeAlarm = 2113;
        /** Kanal-ID der CCU für Systemvariablen ohne Kanalzuordnung */
        public const NoChannel = 65535;

        /**
         * Virtuelle Fernbedienungen der CCU je Protokoll: Gerät, Interface, Anzahl der Kanäle und Gerätetyp (Anfang des Werksnamens der Kanäle).
         */
        public const VirtualKeys = [
            CCU::ProtocolBidCosRF => ['Device' => 'BidCoS-RF', 'Interface' => 'BidCos-RF', 'Channels' => 50, 'Type' => 'HM-RCV-50'],
            CCU::ProtocolHmIP     => ['Device' => 'HmIP-RCV-1', 'Interface' => 'HmIP-RF', 'Channels' => 50, 'Type' => 'HmIP-RCV-50']
        ];

        /**
         * Datenpunkt der virtuellen Taste, der das Event auslöst.
         */
        public const KeyDatapoint = 'PRESS_SHORT';

        /**
         * Zuordnung des ValueType der CCU zum Variablentyp von Symcon.
         */
        public const VariableTypes = [
            self::ValueTypeBool    => 0, // VARIABLETYPE_BOOLEAN
            self::ValueTypeInteger => 1, // VARIABLETYPE_INTEGER
            self::ValueTypeFloat   => 2, // VARIABLETYPE_FLOAT
            self::ValueTypeString  => 3  // VARIABLETYPE_STRING
        ];

        /** ConditionType der CCU (live ermittelt): gleich bzw. ungleich dem Vergleichswert */
        private const ConditionEqual = 1;
        private const ConditionNotEqual = 12;
        /** ConditionType2 der CCU: bei Aktualisierung auslösen */
        private const TriggerOnUpdate = 13;
        /** LeftValType der CCU: Systemvariable */
        private const LeftValSystemVariable = 19;
        /** CndOperatorType der CCU: ODER */
        private const OperatorOr = 2;
        /** DestinationParam der CCU: Datenpunkt eines Kanals schalten */
        private const DestinationDatapoint = 18;

        private const Header = '@@';

        /**
         * Namensanfänge der Systemvariablen, welche die CCU für Energiezähler (Powermeter) und Wetterstationen (Weather) anlegt.
         */
        private const CounterPrefixes = ['svEnergyCounter', 'svHmIP'];

        /**
         * Liefert das Protokoll (0 = BidCos-RF, 2 = HmIP) zur Adresse eines Kanals einer virtuellen Fernbedienung.
         *
         * @param string $Address Adresse mit Kanal, z.B. `BidCoS-RF:49`.
         *
         * @return int|false Das Protokoll, false wenn die Adresse kein Kanal einer virtuellen Fernbedienung ist.
         */
        public static function ProtocolOfKey(string $Address): int|false
        {
            foreach (self::VirtualKeys as $Protocol => $Key) {
                if (preg_match('/^' . preg_quote($Key['Device'], '/') . ':(\d+)$/', $Address, $Match)
                    && ((int) $Match[1] >= 1) && ((int) $Match[1] <= $Key['Channels'])) {
                    return $Protocol;
                }
            }
            return false;
        }

        /**
         * Liefert den Namen, den die CCU einem Kanal der virtuellen Fernbedienung ab Werk gibt, z.B. `HM-RCV-50 BidCoS-RF:49`.
         *
         * @param string $Address Adresse der Taste.
         *
         * @return string Der Werksname, leer wenn die Adresse keine Taste ist.
         */
        public static function DefaultKeyName(string $Address): string
        {
            $Protocol = self::ProtocolOfKey($Address);
            return ($Protocol === false) ? '' : self::VirtualKeys[$Protocol]['Type'] . ' ' . $Address;
        }

        /**
         * Script: Liste der Kanäle der virtuellen Fernbedienungen und ihre Verwendung in Programmen der CCU.
         * Zeilen `K\t<Adresse>\t<Name>\t<Protokoll>` je Taste und `U\t<Adresse>\t<C|A>\t<Programm-ID>\t<Programmname>` je Verwendung
         * als Bedingung (C) bzw. Aktivität (A), auch in den Zweigen „Sonst, wenn“.
         *
         * @param int[] $Protocols Die Protokolle, deren Fernbedienung gelesen wird.
         */
        public static function KeyListScript(array $Protocols): string
        {
            $Script = 'object oDP;' . PHP_EOL . 'object oChn;' . PHP_EOL . 'integer iKey;' . PHP_EOL;
            $IsKey = [];
            foreach ($Protocols as $Protocol) {
                if (!isset(self::VirtualKeys[$Protocol])) {
                    continue;
                }
                $Key = self::VirtualKeys[$Protocol];
                $IsKey[] = 'oCh.Address().StartsWith("' . $Key['Device'] . ':")';
                $Script .= 'iKey = 1;' . PHP_EOL .
                    'while (iKey <= ' . $Key['Channels'] . ') {' . PHP_EOL .
                    ' oDP = dom.GetObject("' . $Key['Interface'] . '.' . $Key['Device'] . ':" # iKey.ToString() # ".' . self::KeyDatapoint . '");' . PHP_EOL .
                    ' if (oDP) { oChn = dom.GetObject(oDP.Channel()); if (oChn) { WriteLine("K\t' . $Key['Device'] . ':" # iKey.ToString() # "\t" # ' . self::Escape('oChn.Name()') . ' # "\t' . $Protocol . '"); } }' . PHP_EOL .
                    ' iKey = iKey + 1;' . PHP_EOL .
                    '}' . PHP_EOL;
            }
            if (count($IsKey) == 0) {
                return $Script;
            }
            $Condition = implode(' || ', $IsKey);
            $Usage = '"\t" # sP # "\t" # ' . self::Escape('oP.Name()');
            return $Script .
                'string sP; string sC; string sS; object oP; object oR; object oC; object oT; object oCh; integer iR;' . PHP_EOL .
                'foreach (sP, dom.GetObject(ID_PROGRAMS).EnumIDs()) {' . PHP_EOL .
                ' oP = dom.GetObject(sP);' . PHP_EOL .
                ' iR = oP.Rule().ID();' . PHP_EOL .
                ' while (iR > 0) {' . PHP_EOL .
                '  oR = dom.GetObject(iR);' . PHP_EOL .
                '  foreach (sC, oR.RuleConditions().EnumIDs()) { oC = dom.GetObject(sC); foreach (sS, oC.CndSingleConditions().EnumIDs()) {' .
                ' oT = dom.GetObject(dom.GetObject(sS).LeftVal()); if (oT) { if (oT.TypeName() == "HSSDP") { oCh = dom.GetObject(oT.Channel());' .
                ' if (oCh) { if (' . $Condition . ') { WriteLine("U\t" # oCh.Address() # "\tC" # ' . $Usage . '); } } } } } }' . PHP_EOL .
                '  oT = dom.GetObject(oR.RuleDestination().ID());' . PHP_EOL .
                '  foreach (sS, oT.DestSingleDestinations().EnumIDs()) { oCh = dom.GetObject(dom.GetObject(sS).DestinationChannel());' .
                ' if (oCh) { if (oCh.TypeName() == "CHANNEL") { if (' . $Condition . ') { WriteLine("U\t" # oCh.Address() # "\tA" # ' . $Usage . '); } } } }' . PHP_EOL .
                '  iR = 0;' . PHP_EOL .
                '  if (oR.RuleSubRule()) { iR = oR.RuleSubRule().ID(); }' . PHP_EOL .
                ' }' . PHP_EOL .
                '}' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von KeyListScript().
         *
         * @return array[] Adresse => ['Name' => string, 'Protocol' => int, 'DefaultName' => bool,
         *                 'Conditions' => [Programm-ID => Name], 'Activities' => [Programm-ID => Name], 'Links' => 0]
         */
        public static function ParseKeyList(string $Output): array
        {
            $Result = [];
            $Usages = [];
            foreach (self::Rows($Output) as $Row) {
                if (($Row[0] === 'K') && (count($Row) >= 4)) {
                    $Name = self::Unescape($Row[2]);
                    $Result[$Row[1]] = [
                        'Name'        => $Name,
                        'Protocol'    => (int) $Row[3],
                        'DefaultName' => $Name === self::DefaultKeyName($Row[1]),
                        'Conditions'  => [],
                        'Activities'  => [],
                        'Links'       => 0
                    ];
                }
                if (($Row[0] === 'U') && (count($Row) >= 5)) {
                    $Usages[] = $Row;
                }
            }
            foreach ($Usages as [, $Address, $Kind, $ProgramId, $ProgramName]) {
                if (isset($Result[$Address])) {
                    $Result[$Address][($Kind === 'C') ? 'Conditions' : 'Activities'][(int) $ProgramId] = self::Unescape($ProgramName);
                }
            }
            return $Result;
        }

        /**
         * Zählt die Direktverknüpfungen je Taste aus der Antwort von `getLinks` (Sender oder Empfänger ist die Taste).
         *
         * @param array $Links Antwort der CCU (Liste mit `SENDER` und `RECEIVER`).
         *
         * @return int[] Adresse der Taste => Anzahl.
         */
        public static function CountKeyLinks(array $Links): array
        {
            $Result = [];
            foreach ($Links as $Link) {
                if (!is_array($Link)) {
                    continue;
                }
                foreach (array_unique([(string) ($Link['SENDER'] ?? ''), (string) ($Link['RECEIVER'] ?? '')]) as $Address) {
                    if (self::ProtocolOfKey($Address) !== false) {
                        $Result[$Address] = ($Result[$Address] ?? 0) + 1;
                    }
                }
            }
            return $Result;
        }

        /**
         * Script: Liste aller Systemvariablen der CCU mit ihren Eigenschaften, auch der versteckten und der Alarmvariablen.
         */
        public static function ListScript(): string
        {
            return 'string sId;' . PHP_EOL .
                'object o;' . PHP_EOL .
                'foreach (sId, dom.GetObject(ID_SYSTEM_VARIABLES).EnumIDs()) {' . PHP_EOL .
                ' o = dom.GetObject(sId);' . PHP_EOL .
                ' if (o) {' . PHP_EOL .
                '  WriteLine(sId # "\t" # ' . self::Escape('o.Name()') . ' # "\t" # o.Type() # "\t" # o.ValueType() # "\t" # o.ValueSubType() # "\t" # o.Internal() # "\t" # o.Channel()' .
                ' # "\t" # ' . self::Escape('o.ValueUnit()') . ' # "\t" # o.ValueMin() # "\t" # o.ValueMax()' .
                ' # "\t" # ' . self::Escape('o.ValueName0()') . ' # "\t" # ' . self::Escape('o.ValueName1()') . ' # "\t" # ' . self::Escape('o.ValueList()') . ');' . PHP_EOL .
                ' }' . PHP_EOL .
                '}' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von ListScript().
         *
         * @return array[] CCU-ID => ['Name', 'Type', 'ValueType', 'SubType', 'Internal', 'Channel', 'Unit', 'Min', 'Max', 'Name0', 'Name1', 'List']
         */
        public static function ParseList(string $Output): array
        {
            $Result = [];
            foreach (self::Rows($Output) as $Row) {
                if (count($Row) < 13) {
                    continue;
                }
                $Result[(int) $Row[0]] = [
                    'Name'      => self::Unescape($Row[1]),
                    'Type'      => (int) $Row[2],
                    'ValueType' => (int) $Row[3],
                    'SubType'   => (int) $Row[4],
                    'Internal'  => $Row[5] === 'true',
                    'Channel'   => (int) $Row[6],
                    'Unit'      => self::Unescape($Row[7]),
                    'Min'       => (float) $Row[8],
                    'Max'       => (float) $Row[9],
                    'Name0'     => self::Unescape($Row[10]),
                    'Name1'     => self::Unescape($Row[11]),
                    'List'      => self::Unescape($Row[12])
                ];
            }
            return $Result;
        }

        /**
         * Prüft, ob eine Systemvariable der Liste eine Alarmvariable ist.
         */
        public static function IsAlarm(array $Variable): bool
        {
            return $Variable['Type'] == self::TypeAlarm;
        }

        /**
         * Prüft, ob eine Systemvariable in Symcon abgebildet werden kann (bekannter Typ, keine Alarmvariable).
         * Die Zähler der CCU-Programme für Energiezähler und Wetterstationen samt Hilfsvariablen werden ausgeblendet,
         * sie lesen die Module Powermeter und Weather.
         */
        public static function IsSupported(array $Variable): bool
        {
            foreach (self::CounterPrefixes as $Prefix) {
                if (str_starts_with($Variable['Name'], $Prefix)) {
                    return false;
                }
            }
            return !self::IsAlarm($Variable) && array_key_exists($Variable['ValueType'], self::VariableTypes);
        }

        /**
         * Script: liest Typ, Zeitstempel und Wert der übergebenen Systemvariablen mit einer einzigen Anfrage.
         * Mit `$Since` werden nur Variablen geliefert, deren Zeitstempel gleich oder neuer ist. Die erste Zeile enthält die Uhrzeit der CCU,
         * sie wird beim nächsten Lesen als `$Since` übergeben. Fehlende Variablen liefern `-` als Typ.
         *
         * @param int[] $Ids Die CCU-IDs.
         * @param string $Since Zeitstempel der CCU (`Y-m-d H:i:s`), leer = alle.
         */
        public static function ReadScript(array $Ids, string $Since = ''): string
        {
            return 'string sId;' . PHP_EOL .
                'object o;' . PHP_EOL .
                'string sTs;' . PHP_EOL .
                'string sSince = "' . self::Literal($Since) . '";' . PHP_EOL .
                'WriteLine("' . self::Header . '" # system.Date("%F %T"));' . PHP_EOL .
                'foreach (sId, "' . implode('\t', array_map('intval', $Ids)) . '") {' . PHP_EOL .
                ' o = dom.GetObject(sId);' . PHP_EOL .
                ' if (o) {' . PHP_EOL .
                '  sTs = o.Timestamp().Format("%F %T");' . PHP_EOL .
                '  if (sTs >= sSince) { WriteLine(sId # "\t" # o.ValueType() # "\t" # sTs # "\t" # ' . self::Escape('o.Value().ToString()') . '); }' . PHP_EOL .
                ' } else { WriteLine(sId # "\t-"); }' . PHP_EOL .
                '}' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von ReadScript().
         *
         * @return array ['Now' => string, 'Values' => [CCU-ID => ['ValueType' => int, 'Timestamp' => string, 'Value' => mixed]], 'Missing' => int[]]
         */
        public static function ParseRead(string $Output): array
        {
            $Result = ['Now' => '', 'Values' => [], 'Missing' => []];
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (str_starts_with($Line, self::Header)) {
                    $Result['Now'] = substr($Line, strlen(self::Header));
                    continue;
                }
                if ($Line === '') {
                    continue;
                }
                $Row = explode("\t", $Line);
                if ((count($Row) == 2) && ($Row[1] === '-')) {
                    $Result['Missing'][] = (int) $Row[0];
                    continue;
                }
                if (count($Row) < 4) {
                    continue;
                }
                $Result['Values'][(int) $Row[0]] = [
                    'ValueType' => (int) $Row[1],
                    'Timestamp' => $Row[2],
                    'Value'     => self::ConvertValue((int) $Row[1], self::Unescape($Row[3]))
                ];
            }
            return $Result;
        }

        /**
         * Wandelt den Text eines Wertes der CCU in den Typ der Statusvariable.
         */
        public static function ConvertValue(int $ValueType, string $Raw): mixed
        {
            switch ($ValueType) {
                case self::ValueTypeBool:
                    return $Raw === 'true';
                case self::ValueTypeInteger:
                    return (int) $Raw;
                case self::ValueTypeFloat:
                    return (float) $Raw;
                default:
                    return $Raw;
            }
        }

        /**
         * Script: schreibt einen Wert in eine Systemvariable. Die Ausgabe ist `true` bei Erfolg, `-` wenn die Variable fehlt.
         */
        public static function WriteScript(int $Id, mixed $Value): string
        {
            return 'object o = dom.GetObject(' . $Id . ');' . PHP_EOL .
                'if (o) { WriteLine(o.State(' . self::ValueLiteral($Value) . ')); } else { WriteLine("-"); }' . PHP_EOL;
        }

        /**
         * Script: legt das CCU-Programm an, welches bei Aktualisierung der Systemvariablen die virtuelle Taste drückt.
         * Ein vorhandenes Programm mit derselben ID und demselben Namen wird vorher gelöscht (nur das eigene Programm).
         * Trägt die Taste noch den Werksnamen, erhält sie den Namen des Programms. Wurde die Taste gewechselt, erhält die bisherige Taste
         * ihren Werksnamen zurück, sofern sie noch den Namen des Programms trägt.
         * Die Ausgabe ist `@@<ID des Programms>`, `@@0` wenn der Datenpunkt der Taste fehlt.
         *
         * @param string $Name Name des Programms.
         * @param string $Info Beschreibung des Programms.
         * @param int $OldProgramId ID des bisherigen Programms (0 = keines).
         * @param string $OldKeyAddress Adresse der Taste des bisherigen Programms (leer = keine).
         * @param string $KeyAddress Adresse der Taste, z.B. `BidCoS-RF:49`.
         * @param int[] $Variables CCU-ID => ValueType der Systemvariablen.
         */
        public static function ProgramScript(string $Name, string $Info, int $OldProgramId, string $OldKeyAddress, string $KeyAddress, array $Variables): string
        {
            $Protocol = self::ProtocolOfKey($KeyAddress);
            $Interface = ($Protocol === false) ? '' : self::VirtualKeys[$Protocol]['Interface'];
            $Script = self::DeleteProgramScript($OldProgramId, $Name, false);
            if (($OldKeyAddress !== '') && ($OldKeyAddress !== $KeyAddress)) {
                $Script .= self::RestoreKeyNameScript($OldKeyAddress, $Name);
            }
            $Script .= 'object oDP = dom.GetObject("' . $Interface . '.' . self::Literal($KeyAddress) . '.' . self::KeyDatapoint . '");' . PHP_EOL .
                'if (oDP) {' . PHP_EOL .
                ' object oKey = dom.GetObject(oDP.Channel());' . PHP_EOL .
                ' if (oKey) { if (oKey.Name() == "' . self::Literal(self::DefaultKeyName($KeyAddress)) . '") { oKey.Name("' . self::Literal($Name) . '"); } }' . PHP_EOL .
                ' object oPrg = dom.CreateObject(OT_PROGRAM);' . PHP_EOL .
                ' oPrg.Name("' . self::Literal($Name) . '");' . PHP_EOL .
                ' oPrg.PrgInfo("' . self::Literal($Info) . '");' . PHP_EOL .
                ' dom.GetObject(ID_PROGRAMS).Add(oPrg.ID());' . PHP_EOL .
                ' object oRule = dom.GetObject(oPrg.Rule().ID());' . PHP_EOL .
                ' object oCnd = oRule.RuleAddCondition();' . PHP_EOL .
                ' oCnd.CndOperatorType(' . self::OperatorOr . ');' . PHP_EOL .
                ' object oSC;' . PHP_EOL;
            foreach ($Variables as $Id => $ValueType) {
                foreach (self::Conditions((int) $ValueType) as [$ConditionType, $Compare]) {
                    $Script .= ' oSC = oCnd.CndAddSingle(); oSC.OperatorType(' . self::OperatorOr . '); oSC.ConditionType(' . $ConditionType . ');' .
                        ' oSC.ConditionType2(' . self::TriggerOnUpdate . '); oSC.LeftValType(' . self::LeftValSystemVariable . '); oSC.ConditionChannel(' . self::NoChannel . ');' .
                        ' oSC.LeftVal(' . (int) $Id . '); oSC.RightVal1(' . $Compare . ');' . PHP_EOL;
                }
            }
            $Script .= ' object oDest = dom.GetObject(oRule.RuleDestination().ID());' . PHP_EOL .
                ' object oSD = oDest.DestAddSingle();' . PHP_EOL .
                ' oSD.DestinationParam(' . self::DestinationDatapoint . '); oSD.DestinationChannel(oDP.Channel()); oSD.DestinationDP(oDP.ID());' .
                ' oSD.DestinationValueType(ivtBinary); oSD.DestinationValue(1);' . PHP_EOL .
                ' oPrg.Internal(true);' . PHP_EOL .
                ' oPrg.Active(true);' . PHP_EOL .
                ' dom.RTUpdate(0);' . PHP_EOL .
                ' WriteLine("' . self::Header . '" # oPrg.ID());' . PHP_EOL .
                '} else { WriteLine("' . self::Header . '0"); }' . PHP_EOL;
            return $Script;
        }

        /**
         * Script: löscht das eigene CCU-Programm (nur, wenn ID und Name passen).
         *
         * @param bool $WithOutput true: Ausgabe `@@1` wenn gelöscht, sonst `@@0`.
         * @param string $KeyAddress Taste des Programms: erhält ihren Werksnamen zurück, sofern sie noch den Namen des Programms trägt (leer = nicht ändern).
         */
        public static function DeleteProgramScript(int $ProgramId, string $Name, bool $WithOutput = true, string $KeyAddress = ''): string
        {
            $Restore = ($KeyAddress !== '') ? self::RestoreKeyNameScript($KeyAddress, $Name) : '';
            if ($ProgramId <= 0) {
                return $Restore . ($WithOutput ? 'WriteLine("' . self::Header . '0");' . PHP_EOL : '');
            }
            $Script = $Restore .
                'boolean bDeleted = false;' . PHP_EOL .
                'object oOld = dom.GetObject(' . $ProgramId . ');' . PHP_EOL .
                'if (oOld) { if ((oOld.TypeName() == "PROGRAM") && (oOld.Name() == "' . self::Literal($Name) . '")) { dom.DeleteObject(oOld.ID()); bDeleted = true; } }' . PHP_EOL;
            if ($WithOutput) {
                $Script .= 'dom.RTUpdate(0);' . PHP_EOL .
                    'if (bDeleted) { WriteLine("' . self::Header . '1"); } else { WriteLine("' . self::Header . '0"); }' . PHP_EOL;
            }
            return $Script;
        }

        /**
         * Script: gibt einer Taste ihren Werksnamen zurück, wenn sie den übergebenen Namen trägt (vom Modul vergeben).
         *
         * @param string $KeyAddress Adresse der Taste.
         * @param string $Name Vom Modul vergebener Name (Name des Programms).
         */
        public static function RestoreKeyNameScript(string $KeyAddress, string $Name): string
        {
            $Protocol = self::ProtocolOfKey($KeyAddress);
            if ($Protocol === false) {
                return '';
            }
            return 'object oOldDP = dom.GetObject("' . self::VirtualKeys[$Protocol]['Interface'] . '.' . $KeyAddress . '.' . self::KeyDatapoint . '");' . PHP_EOL .
                'if (oOldDP) { object oOldKey = dom.GetObject(oOldDP.Channel()); if (oOldKey) { if (oOldKey.Name() == "' . self::Literal($Name) . '") { oOldKey.Name("' . self::Literal(self::DefaultKeyName($KeyAddress)) . '"); } } }' . PHP_EOL;
        }

        /**
         * Script: Zustand des eigenen CCU-Programms. Erste Zeile `@@<Name>\t<aktiv>`, danach je Bedingung die CCU-ID der Systemvariable.
         * Ohne Ausgabe, wenn das Programm fehlt.
         */
        public static function ProgramStateScript(int $ProgramId): string
        {
            if ($ProgramId <= 0) {
                return '';
            }
            return 'object oPrg = dom.GetObject(' . $ProgramId . ');' . PHP_EOL .
                'if (oPrg) { if (oPrg.TypeName() == "PROGRAM") {' . PHP_EOL .
                ' WriteLine("' . self::Header . '" # ' . self::Escape('oPrg.Name()') . ' # "\t" # oPrg.Active());' . PHP_EOL .
                ' object oRule = dom.GetObject(oPrg.Rule().ID());' . PHP_EOL .
                ' string sC; string sS; object oC;' . PHP_EOL .
                ' foreach (sC, oRule.RuleConditions().EnumIDs()) { oC = dom.GetObject(sC); foreach (sS, oC.CndSingleConditions().EnumIDs()) { WriteLine(dom.GetObject(sS).LeftVal()); } }' . PHP_EOL .
                '} }' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von ProgramStateScript().
         *
         * @return array|false ['Name' => string, 'Active' => bool, 'Variables' => int[]], false wenn das Programm fehlt.
         */
        public static function ParseProgramState(string $Output): array|false
        {
            $Result = false;
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (str_starts_with($Line, self::Header)) {
                    $Row = explode("\t", substr($Line, strlen(self::Header)));
                    $Result = ['Name' => self::Unescape($Row[0]), 'Active' => ($Row[1] ?? '') === 'true', 'Variables' => []];
                    continue;
                }
                if (($Result !== false) && ($Line !== '')) {
                    $Result['Variables'][] = (int) $Line;
                }
            }
            if ($Result !== false) {
                $Result['Variables'] = array_values(array_unique($Result['Variables']));
                sort($Result['Variables']);
            }
            return $Result;
        }

        /**
         * Liefert den Wert einer Kopfzeile (`@@…`) aus der Ausgabe eines Scriptes.
         *
         * @return string|false Der Wert, false ohne Kopfzeile.
         */
        public static function ParseHeader(string $Output): string|false
        {
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (str_starts_with($Line, self::Header)) {
                    return substr($Line, strlen(self::Header));
                }
            }
            return false;
        }

        /**
         * Script: liest Zustand und Auslöser einer Alarmvariable. Kopfzeile `@@<Uhrzeit der CCU>`, danach eine Zeile mit
         * Wert, Zeitstempel, erstem Alarm, letztem Alarm, Name des Kanals, Raum und Name des auslösenden Datenpunktes.
         * Fehlt die Variable, folgt `-`.
         */
        public static function AlarmScript(int $Id): string
        {
            return 'WriteLine("' . self::Header . '" # system.Date("%F %T"));' . PHP_EOL .
                'object o = dom.GetObject(' . $Id . ');' . PHP_EOL .
                'if (o) {' . PHP_EOL .
                ' integer iTrigger = o.LastTriggerID();' . PHP_EOL .
                ' if (iTrigger == ID_ERROR) { iTrigger = o.AlTriggerDP(); }' . PHP_EOL .
                ' string sChannel = ""; string sRoom = ""; string sDP = ""; string sR;' . PHP_EOL .
                ' object oTrigger = dom.GetObject(iTrigger);' . PHP_EOL .
                ' if (oTrigger) {' . PHP_EOL .
                '  sDP = oTrigger.Name();' . PHP_EOL .
                '  object oChannel = dom.GetObject(oTrigger.Channel());' . PHP_EOL .
                '  if (oChannel) { sChannel = oChannel.Name(); foreach (sR, oChannel.ChnRoom()) { object oRoom = dom.GetObject(sR); if (oRoom) { sRoom = oRoom.Name(); } } }' . PHP_EOL .
                ' }' . PHP_EOL .
                ' WriteLine(o.Value() # "\t" # o.Timestamp().Format("%F %T") # "\t" # o.AlOccurrenceTime().Format("%F %T") # "\t" # o.LastTriggerTime().Format("%F %T")' .
                ' # "\t" # ' . self::Escape('sChannel') . ' # "\t" # ' . self::Escape('sRoom') . ' # "\t" # ' . self::Escape('sDP') . ');' . PHP_EOL .
                '} else { WriteLine("-"); }' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von AlarmScript().
         *
         * @return array|false ['Now', 'Value' => bool, 'Timestamp', 'FirstTime', 'LastTime', 'Channel', 'Room', 'Datapoint'], false wenn die Variable fehlt.
         */
        public static function ParseAlarm(string $Output): array|false
        {
            $Now = '';
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (str_starts_with($Line, self::Header)) {
                    $Now = substr($Line, strlen(self::Header));
                    continue;
                }
                if ($Line === '') {
                    continue;
                }
                $Row = explode("\t", $Line);
                if (count($Row) < 7) {
                    return false;
                }
                return [
                    'Now'       => $Now,
                    'Value'     => $Row[0] === 'true',
                    'Timestamp' => $Row[1],
                    'FirstTime' => $Row[2],
                    'LastTime'  => $Row[3],
                    'Channel'   => self::Unescape($Row[4]),
                    'Room'      => self::Unescape($Row[5]),
                    'Datapoint' => self::Unescape($Row[6])
                ];
            }
            return false;
        }

        /**
         * Script: quittiert eine Alarmvariable, wenn der Alarm ansteht. Ausgabe `true` nach dem Quittieren, `false` wenn kein Alarm ansteht, `-` wenn die Variable fehlt.
         */
        public static function AlarmReceiptScript(int $Id): string
        {
            return 'object o = dom.GetObject(' . $Id . ');' . PHP_EOL .
                'if (o) { if (o.AlState() == asOncoming) { WriteLine(o.AlReceipt()); } else { WriteLine("false"); } } else { WriteLine("-"); }' . PHP_EOL;
        }

        /**
         * Wandelt einen Zeitstempel der CCU in einen Unix-Zeitstempel. Ungültige bzw. leere Zeitstempel (z.B. 1970) ergeben 0.
         *
         * @param string $Timestamp Zeitstempel der CCU (`Y-m-d H:i:s`, Ortszeit der CCU).
         * @param string $TimeZone Zeitzone (z.B. `Europe/Berlin`).
         */
        public static function ToUnixTime(string $Timestamp, string $TimeZone): int
        {
            $Date = \DateTime::createFromFormat('Y-m-d H:i:s', $Timestamp, new \DateTimeZone($TimeZone));
            if ($Date === false) {
                return 0;
            }
            $Time = $Date->getTimestamp();
            return ($Time < 86400) ? 0 : $Time;
        }

        /**
         * Liefert die Darstellung einer Systemvariable anhand ihrer Eigenschaften auf der CCU.
         * Bool mit Beschriftungen und Wertelisten: Aufzählung; Bool ohne Beschriftungen: Schalter; Zahl und Text: Werteingabe mit Einheit.
         *
         * @param array $Variable Eintrag aus ParseList().
         * @param string $EnumerationId GUID der Darstellung Aufzählung.
         * @param string $SwitchId GUID der Darstellung Schalter.
         * @param string $InputId GUID der Darstellung Werteingabe.
         *
         * @return array Die Darstellung.
         */
        public static function Presentation(array $Variable, string $EnumerationId, string $SwitchId, string $InputId): array
        {
            switch ($Variable['ValueType']) {
                case self::ValueTypeBool:
                    if (($Variable['Name0'] === '') && ($Variable['Name1'] === '')) {
                        return ['PRESENTATION' => $SwitchId];
                    }
                    return [
                        'PRESENTATION' => $EnumerationId,
                        'OPTIONS'      => json_encode([
                            self::Option(false, $Variable['Name0']),
                            self::Option(true, $Variable['Name1'])
                        ])
                    ];
                case self::ValueTypeInteger:
                    if ($Variable['SubType'] == self::SubTypeEnum) {
                        $Options = [];
                        foreach (explode(';', $Variable['List']) as $Index => $Caption) {
                            $Options[] = self::Option($Index, trim($Caption));
                        }
                        return ['PRESENTATION' => $EnumerationId, 'OPTIONS' => json_encode($Options)];
                    }
                    break;
            }
            $Presentation = ['PRESENTATION' => $InputId];
            if ($Variable['Unit'] !== '') {
                $Presentation['SUFFIX'] = ' ' . $Variable['Unit'];
            }
            return $Presentation;
        }

        /**
         * Maskiert Backslash, Tabulator und Zeilenumbrüche des Ergebnisses eines HM-Script-Ausdrucks.
         *
         * @param string $Expression HM-Script-Ausdruck, der einen Text liefert.
         */
        private static function Escape(string $Expression): string
        {
            return $Expression . '.Replace("\\\\", "\\\\\\\\").Replace("\t", "\\\\t").Replace("\r", "\\\\r").Replace("\n", "\\\\n")';
        }

        /**
         * Hebt die Maskierung von Escape() auf.
         */
        private static function Unescape(string $Text): string
        {
            return preg_replace_callback('/\\\\([\\\\trn])/', function ($Match)
            {
                return ['\\' => '\\', 't' => "\t", 'r' => "\r", 'n' => "\n"][$Match[1]];
            }, $Text);
        }

        /**
         * Maskiert einen Text für ein HM-Script-Literal in Anführungszeichen.
         */
        private static function Literal(string $Text): string
        {
            return addcslashes($Text, "\\\"\t\r\n");
        }

        /**
         * Liefert einen Wert als HM-Script-Literal.
         */
        private static function ValueLiteral(mixed $Value): string
        {
            if (is_bool($Value)) {
                return $Value ? 'true' : 'false';
            }
            if (is_int($Value)) {
                return (string) $Value;
            }
            if (is_float($Value)) {
                return sprintf('%.6F', $Value);
            }
            return '"' . self::Literal((string) $Value) . '"';
        }

        /**
         * Bedingungen je ValueType, die zusammen (ODER) jeden Wert abdecken: ConditionType und Vergleichswert als Literal.
         *
         * @return array[]
         */
        private static function Conditions(int $ValueType): array
        {
            switch ($ValueType) {
                case self::ValueTypeBool:
                    return [[self::ConditionEqual, 'false'], [self::ConditionEqual, 'true']];
                case self::ValueTypeFloat:
                    return [[self::ConditionEqual, '0.0'], [self::ConditionNotEqual, '0.0']];
                case self::ValueTypeString:
                    return [[self::ConditionEqual, '""'], [self::ConditionNotEqual, '""']];
                default:
                    return [[self::ConditionEqual, '0'], [self::ConditionNotEqual, '0']];
            }
        }

        /**
         * Eintrag für OPTIONS der Darstellung Aufzählung.
         */
        private static function Option(mixed $Value, string $Caption): array
        {
            return ['Value' => $Value, 'Caption' => $Caption, 'IconValue' => '', 'IconActive' => false, 'Color' => -1];
        }

        /**
         * Zerlegt eine Ausgabe in Zeilen und Spalten, Kopfzeilen und leere Zeilen werden übersprungen.
         *
         * @return array[]
         */
        private static function Rows(string $Output): array
        {
            $Rows = [];
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (($Line === '') || str_starts_with($Line, self::Header)) {
                    continue;
                }
                $Rows[] = explode("\t", $Line);
            }
            return $Rows;
        }
    }
}

/* @} */
