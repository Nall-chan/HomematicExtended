<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMScriptParser.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.75
 */

namespace HMExtended
{
    /**
     * Zerlegt die Antwort der CCU auf ein HM-Script (ohne Abhängigkeit zu Symcon).
     *
     * Die CCU liefert zuerst die Ausgaben des Scripts (Write/WriteLine) und danach ein XML mit den Variablen des Scripts:
     * `Zeile 1\r\nZeile 2\r\n<xml><exec>...</exec><sessionId></sessionId>...<Variable>Wert</Variable></xml>`
     */
    class ScriptParser
    {
        private const XmlStart = '<xml><exec>';

        /**
         * Trennt die Ausgabe des Scripts vom XML mit den Variablen.
         *
         * @param string $Content Die Antwort der CCU.
         *
         * @return string[] Index 0: Ausgabe des Scripts, Index 1: XML (leer, wenn die Antwort kein XML enthält).
         */
        public static function SplitOutput(string $Content): array
        {
            $Position = strpos($Content, self::XmlStart);
            if ($Position === false) {
                return [$Content, ''];
            }
            return [substr($Content, 0, $Position), substr($Content, $Position)];
        }

        /**
         * Zerlegt die Ausgabe des Scripts, das die Konfigurationsdateien der Gateways (`rfd.conf`, `hs485d.conf`) liest.
         *
         * Das Script gibt je Datei eine Zeile `@@<Name>` aus, danach die Zeilen `[Interface n]`, `Type`, `Name`, `Serial Number`,
         * `IP Address` und `Description`. Nur diese Felder werden übernommen (die Dateien enthalten auch den Sicherheitsschlüssel).
         * Einträge ohne Seriennummer (z.B. der Coprozessor der CCU) werden ignoriert.
         *
         * @param string $Output Die Ausgabe des Scripts.
         *
         * @return array[] Dateiname => Liste der Gateways, je Gateway die Felder `Type`, `Name`, `Serial Number`, `IP Address`, `Description`.
         */
        public static function ParseGatewayConfig(string $Output): array
        {
            $Allowed = ['Type', 'Name', 'Serial Number', 'IP Address', 'Description'];
            $Result = [];
            $Group = '';
            $Entry = null;
            $Flush = function () use (&$Result, &$Group, &$Entry)
            {
                if (is_array($Entry) && (($Entry['Serial Number'] ?? '') !== '')) {
                    $Result[$Group][] = $Entry;
                }
                $Entry = null;
            };
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                $Line = trim($Line);
                if (str_starts_with($Line, '@@')) {
                    $Flush();
                    $Group = substr($Line, 2);
                    $Result[$Group] ??= [];
                    continue;
                }
                if (preg_match('/^\[Interface \d+\]$/', $Line)) {
                    $Flush();
                    $Entry = ['Type' => '', 'Name' => '', 'Serial Number' => '', 'IP Address' => '', 'Description' => ''];
                    continue;
                }
                if (($Entry !== null) && preg_match('/^([A-Za-z ]+?)\s*=\s*(.*)$/', $Line, $Match) && in_array($Match[1], $Allowed, true)) {
                    $Entry[$Match[1]] = $Match[2];
                }
            }
            $Flush();
            return $Result;
        }

        /**
         * Zerlegt die Ausgabe eines Scripts in Zeilen und Spalten (Spalten mit Tabulator getrennt).
         * Leere Zeilen werden ignoriert.
         *
         * @param string $Output Die Ausgabe des Scripts.
         *
         * @return array[] Eine Liste mit den Spalten je Zeile.
         */
        public static function ParseTable(string $Output): array
        {
            $Rows = [];
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if ($Line === '') {
                    continue;
                }
                $Rows[] = explode("\t", $Line);
            }
            return $Rows;
        }
    }
}

/* @} */
