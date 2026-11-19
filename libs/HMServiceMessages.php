<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMServiceMessages.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       4.03
 */

namespace HMExtended
{
    require_once __DIR__ . '/HMSystemVariables.php';

    /**
     * Erzeugt die HM-Scripte für die Servicemeldungen der CCU und zerlegt deren Ausgaben (ohne Abhängigkeit zu Symcon).
     *
     * Die Liste kommt aus der ReGa (`ID_SERVICES`), wie in der WebUI (`serviceMessages.htm`). Nur so sind alle Schnittstellen
     * enthalten: XML-RPC `getServiceMessages` liefert für HmIP-RF keine echten Meldungen (eq-3/occu #54) und fehlt bei BidCos-Wired.
     * Je Datenpunkt mit Service-Flag legt die ReGa ein Objekt `OT_ALARMDP` an, eine Meldung steht an bei `AlState() == asOncoming`.
     * Bestätigen (`AlReceipt()`) geht nur, wenn der auslösende Datenpunkt beschreibbar ist (`Operations() & 2`, z.B. `STICKY_UNREACH`),
     * wie in der WebUI (`functions.fn`, ReceiptAlarm). HmIP-`UNREACH` ist nicht beschreibbar und verschwindet von selbst.
     * Die Systemvariable 41 (`${sysVarServiceMessages}`, im Programm „Systemzustand: Servicemeldungen“) ändert sich mit jeder
     * Meldung; ihr Wert wird nicht übernommen, die Liste wird neu gezählt (Meldungen können sich selbst zurücksetzen).
     */
    class ServiceMessages
    {
        /**
         * ReGa-ID der Systemvariable mit der Anzahl der Servicemeldungen (Auslöser des CCU-Programms).
         */
        public const CounterVariable = 41;
        /**
         * ValueType der Systemvariable 41 (Integer).
         */
        public const CounterValueType = 16;

        /**
         * Gruppen der Meldungen (Filter der Kachel) je Datenpunkt; alle übrigen gehören zu `other`.
         */
        public const Groups = [
            'UNREACH'              => 'unreach',
            'STICKY_UNREACH'       => 'unreach',
            'LOWBAT'               => 'battery',
            'LOW_BAT'              => 'battery',
            'U_SOURCE_FAIL'        => 'battery',
            'CONFIG_PENDING'       => 'config',
            'UPDATE_PENDING'       => 'config',
            'DEVICE_IN_BOOTLOADER' => 'config',
            'SABOTAGE'             => 'sabotage',
            'STICKY_SABOTAGE'      => 'sabotage',
            'ERROR_SABOTAGE'       => 'sabotage'
        ];

        /**
         * Spalten einer Zeile von Script().
         */
        private const Columns = 15;
        private const Header = '@@';
        /**
         * ValueSubType der ReGa für Aufzählungen (Werteliste in `ValueList()`).
         */
        private const SubTypeEnumeration = 29;

        /**
         * Script: liest alle anstehenden Servicemeldungen. Kopfzeile `@@<Uhrzeit der CCU>`, danach je Meldung eine Zeile:
         * ID, Adresse des Kanals, Datenpunkt, Wert, Text des Wertes (bei Aufzählungen aus der Werteliste), Schnittstelle, Gerätetyp,
         * Name des Gerätes, Name des Kanals, Räume, Gewerke, erstes Auftreten, letztes Auftreten, bestätigbar (`1`/`0`), Name des Alarms.
         */
        public static function Script(): string
        {
            return 'WriteLine("' . self::Header . '" # system.Date("%F %T"));' . PHP_EOL .
                'string sId; string sR; object oA; object oDP; object oCh; object oDev; object oIfc; integer iT;' . PHP_EOL .
                'foreach (sId, dom.GetObject(ID_SERVICES).EnumIDs()) {' . PHP_EOL .
                ' oA = dom.GetObject(sId);' . PHP_EOL .
                ' if (oA) { if (oA.IsTypeOf(OT_ALARMDP) && (oA.AlState() == asOncoming)) {' . PHP_EOL .
                '  iT = oA.LastTriggerID(); if (iT == ID_ERROR) { iT = oA.AlTriggerDP(); }' . PHP_EOL .
                '  string sAddr = ""; string sDP = ""; string sText = ""; string sIfc = ""; string sType = ""; string sDev = ""; string sCh = ""; string sRooms = ""; string sFuncs = ""; integer iOp = 0;' . PHP_EOL .
                '  oDP = dom.GetObject(iT);' . PHP_EOL .
                '  if (oDP) {' . PHP_EOL .
                '   sDP = oDP.HSSID(); if (oDP.Operations() & 2) { iOp = 1; }' . PHP_EOL .
                '   if (oDP.ValueSubType() == ' . self::SubTypeEnumeration . ') { sText = oDP.ValueList().StrValueByIndex(";", oA.Value()); }' . PHP_EOL .
                '   oCh = dom.GetObject(oDP.Channel());' . PHP_EOL .
                '   if (oCh) {' . PHP_EOL .
                '    sAddr = oCh.Address(); sCh = oCh.Name();' . PHP_EOL .
                '    foreach (sR, oCh.ChnRoom()) { if (sRooms <> "") { sRooms = sRooms # ", "; } sRooms = sRooms # dom.GetObject(sR).Name(); }' . PHP_EOL .
                '    foreach (sR, oCh.ChnFunction()) { if (sFuncs <> "") { sFuncs = sFuncs # ", "; } sFuncs = sFuncs # dom.GetObject(sR).Name(); }' . PHP_EOL .
                '    oDev = dom.GetObject(oCh.Device());' . PHP_EOL .
                '    if (oDev) { sType = oDev.HssType(); sDev = oDev.Name(); oIfc = dom.GetObject(oDev.Interface()); if (oIfc) { sIfc = oIfc.Name(); } }' . PHP_EOL .
                '   }' . PHP_EOL .
                '  }' . PHP_EOL .
                '  WriteLine(oA.ID() # "\t" # sAddr # "\t" # sDP # "\t" # oA.Value() # "\t" # ' . SystemVariables::Escape('sText') . ' # "\t" # sIfc # "\t" # sType' .
                ' # "\t" # ' . SystemVariables::Escape('sDev') . ' # "\t" # ' . SystemVariables::Escape('sCh') . ' # "\t" # ' . SystemVariables::Escape('sRooms') .
                ' # "\t" # ' . SystemVariables::Escape('sFuncs') . ' # "\t" # oA.AlOccurrenceTime().Format("%F %T") # "\t" # oA.LastTriggerTime().Format("%F %T")' .
                ' # "\t" # iOp # "\t" # ' . SystemVariables::Escape('oA.Name()') . ');' . PHP_EOL .
                ' } }' . PHP_EOL .
                '}' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von Script().
         *
         * @param string $Output   Ausgabe des Scriptes (ohne XML).
         * @param string $TimeZone Zeitzone der CCU für die Zeitstempel.
         *
         * @return array ['Now' => int, 'Messages' => array[]]; je Meldung `id`, `address`, `device`, `channel`, `datapoint`, `value`,
         *               `text`, `group`, `interface`, `type`, `deviceName`, `channelName`, `rooms`, `functions`, `first`, `last` (Unix-Zeit), `confirmable`.
         */
        public static function Parse(string $Output, string $TimeZone): array
        {
            $Now = 0;
            $Messages = [];
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (str_starts_with($Line, self::Header)) {
                    $Now = SystemVariables::ToUnixTime(substr($Line, strlen(self::Header)), $TimeZone);
                    continue;
                }
                if ($Line === '') {
                    continue;
                }
                $Row = explode("\t", $Line);
                if (count($Row) < self::Columns) {
                    continue;
                }
                $Address = $Row[1];
                $Datapoint = $Row[2];
                $Name = SystemVariables::Unescape($Row[14]);
                // Ohne Kanal (z.B. Meldungen des Systems) Adresse und Datapoint aus dem Namen des Alarms: AL-<Adresse>.<Datenpunkt>
                if (($Address === '') && preg_match('/^AL-(.+)\.([^.]+)$/', $Name, $Match)) {
                    $Address = $Match[1];
                    $Datapoint = ($Datapoint === '') ? $Match[2] : $Datapoint;
                }
                $Parts = explode(':', $Address, 2);
                $Messages[] = [
                    'id'          => (int) $Row[0],
                    'address'     => $Address,
                    'device'      => $Parts[0],
                    'channel'     => isset($Parts[1]) ? (int) $Parts[1] : -1,
                    'datapoint'   => $Datapoint,
                    'value'       => self::ConvertValue($Row[3]),
                    'text'        => SystemVariables::Unescape($Row[4]),
                    // BidCos meldet Sabotage auch als Wert einer Aufzählung (z.B. HM-Sec-SCo: ERROR = 7, SABOTAGE)
                    'group'       => self::Groups[$Datapoint] ?? ($Row[4] === 'SABOTAGE' ? 'sabotage' : 'other'),
                    'interface'   => $Row[5],
                    'type'        => $Row[6],
                    'deviceName'  => SystemVariables::Unescape($Row[7]),
                    'channelName' => SystemVariables::Unescape($Row[8]),
                    'rooms'       => SystemVariables::Unescape($Row[9]),
                    'functions'   => SystemVariables::Unescape($Row[10]),
                    'first'       => SystemVariables::ToUnixTime($Row[11], $TimeZone),
                    'last'        => SystemVariables::ToUnixTime($Row[12], $TimeZone),
                    'confirmable' => $Row[13] === '1'
                ];
            }
            // Neueste Meldung zuerst
            usort($Messages, fn (array $A, array $B) => [$B['last'], $A['id']] <=> [$A['last'], $B['id']]);
            return ['Now' => $Now, 'Messages' => $Messages];
        }

        /**
         * Script: bestätigt Servicemeldungen, die anstehen und deren Datenpunkt beschreibbar ist.
         * Ausgabe je ID eine Zeile `<ID>\t<Ergebnis>`: `1` bestätigt, `0` nicht bestätigbar bzw. steht nicht an, `-` unbekannt.
         *
         * @param int[] $Ids ReGa-IDs der Meldungen.
         */
        public static function ConfirmScript(array $Ids): string
        {
            $Script = 'object oA; object oDP;' . PHP_EOL;
            foreach ($Ids as $Id) {
                $Id = (int) $Id;
                $Script .= 'oA = dom.GetObject(' . $Id . ');' . PHP_EOL .
                    'if (oA) { if (oA.IsTypeOf(OT_ALARMDP) && (oA.AlState() == asOncoming)) { oDP = dom.GetObject(oA.AlTriggerDP());' .
                    ' if (oDP) { if (oDP.Operations() & 2) { oA.AlReceipt(); WriteLine("' . $Id . '\t1"); } else { WriteLine("' . $Id . '\t0"); } } else { WriteLine("' . $Id . '\t0"); } }' .
                    ' else { WriteLine("' . $Id . '\t0"); } } else { WriteLine("' . $Id . '\t-"); }' . PHP_EOL;
            }
            return $Script;
        }

        /**
         * Zerlegt die Ausgabe von ConfirmScript().
         *
         * @return array ID => true (bestätigt), false (nicht bestätigbar) oder null (unbekannt).
         */
        public static function ParseConfirm(string $Output): array
        {
            $Result = [];
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                $Row = explode("\t", $Line);
                if (count($Row) != 2) {
                    continue;
                }
                $Result[(int) $Row[0]] = ($Row[1] === '-') ? null : ($Row[1] === '1');
            }
            return $Result;
        }

        /**
         * Wert einer Meldung: Boolean (`true`/`false`), sonst Zahl.
         */
        private static function ConvertValue(string $Raw): bool|int|float
        {
            if (($Raw === 'true') || ($Raw === 'false')) {
                return $Raw === 'true';
            }
            return is_numeric($Raw) ? $Raw + 0 : 0;
        }
    }
}

/* @} */
