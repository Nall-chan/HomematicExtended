<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMRadioLevels.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       4.03
 */

namespace HMExtended
{
    require_once __DIR__ . '/HMSystemVariables.php';

    /**
     * Funkpegel (RSSI) der Geräte von BidCos-RF und HmIP-RF (ohne Abhängigkeit zu Symcon).
     *
     * Quellen (Recherche und live geprüft 2026-11-19, siehe STATUS):
     * - BidCos-RF: XML-RPC `rssiInfo` des rfd liefert je Gerät und Gegenstelle zwei Pegel in dBm, 65536 = unbekannt.
     *   Laut rfd (`RFManager::GetRSSIInfo`): `info[Empfänger][Sender][0]` und `info[Sender][Empfänger][1]`, also für Gerät D und
     *   Gateway G: `[D][G][0]` = Gerät empfängt das Gateway, `[D][G][1]` = Gateway empfängt das Gerät. Die Matrix enthält auch Paare
     *   zwischen Geräten (Direktverknüpfungen), verwendet werden nur Paare mit Gateways. `listDevices` nennt je Gerät das zugeordnete
     *   Gateway (`INTERFACE`), `listBidcosInterfaces` die Gateways mit Duty Cycle. Kanal 0 der BidCos-Geräte fehlt in der ReGa.
     * - HmIP-RF: kein `rssiInfo` (crRFD), nur `RSSI_DEVICE` (Gerät empfängt) und `RSSI_PEER` (Gegenstelle empfängt) auf Kanal 0, aus
     *   der ReGa gelesen; kein Pegel je Access Point. Rohwerte wie aiohomematic korrigiert (`FixLevel`).
     * - BidCos-Wired hat keine Funkpegel.
     */
    class RadioLevels
    {
        public const InterfaceRF = 'BidCos-RF';
        public const InterfaceIP = 'HmIP-RF';
        /**
         * Typen der HmIP-Funkmodule und Access Points: keine Geräte der Liste, sondern Funkmodule (Duty Cycle, Carrier Sense).
         */
        public const AccessPointTypes = ['RPI-RF-MOD', 'HmIP-CCU3', 'HmIP-RFUSB', 'HmIP-HAP', 'HmIP-HAP-B1', 'HmIP-HAP-JS1', 'HmIPW-DRAP', 'HmIP-DRAP'];
        /**
         * Funkmodule in der CCU (gehören zum Gateway aus listBidcosInterfaces, nicht eigenständig).
         */
        public const CentralModuleTypes = ['RPI-RF-MOD', 'HmIP-CCU3', 'HmIP-RFUSB'];
        /**
         * Virtuelle Fernbedienungen der CCU (keine Funkgeräte).
         */
        public const VirtualTypes = ['HM-RCV-50', 'HmIP-RCV-50', 'HmIP-RCV-1'];

        private const Header = '@@';
        private const Columns = 9;
        private const Unknown = 65536;

        /**
         * Script: liest alle Geräte von BidCos-RF und HmIP-RF aus der ReGa. Kopfzeile `@@<Uhrzeit der CCU>`, danach je Gerät:
         * Adresse, Schnittstelle, Typ, Name, `RSSI_DEVICE`, `RSSI_PEER`, Zeitstempel von `RSSI_DEVICE`, `DUTY_CYCLE_LEVEL`,
         * `CARRIER_SENSE_LEVEL` (Kanal 0, leer wenn nicht vorhanden).
         */
        public static function Script(): string
        {
            return 'WriteLine("' . self::Header . '" # system.Date("%F %T"));' . PHP_EOL .
                'string sId; string sC; object oD; object oI; object oC; object oDP;' . PHP_EOL .
                'foreach (sId, dom.GetObject(ID_DEVICES).EnumIDs()) {' . PHP_EOL .
                ' oD = dom.GetObject(sId);' . PHP_EOL .
                ' if (oD) { oI = dom.GetObject(oD.Interface());' . PHP_EOL .
                ' if (oI) { if ((oI.Name() == "' . self::InterfaceRF . '") || (oI.Name() == "' . self::InterfaceIP . '")) {' . PHP_EOL .
                '  string sDev = ""; string sPeer = ""; string sTs = ""; string sDc = ""; string sCs = "";' . PHP_EOL .
                '  foreach (sC, oD.Channels().EnumIDs()) { oC = dom.GetObject(sC); if (oC) { if (oC.ChnNumber() == 0) {' . PHP_EOL .
                '   oDP = oC.DPByHssDP("RSSI_DEVICE"); if (oDP) { sDev = oDP.Value(); sTs = oDP.Timestamp().Format("%F %T"); }' . PHP_EOL .
                '   oDP = oC.DPByHssDP("RSSI_PEER"); if (oDP) { sPeer = oDP.Value(); }' . PHP_EOL .
                '   oDP = oC.DPByHssDP("DUTY_CYCLE_LEVEL"); if (oDP) { sDc = oDP.Value(); }' . PHP_EOL .
                '   oDP = oC.DPByHssDP("CARRIER_SENSE_LEVEL"); if (oDP) { sCs = oDP.Value(); }' . PHP_EOL .
                '  } } }' . PHP_EOL .
                '  WriteLine(oD.Address() # "\t" # oI.Name() # "\t" # oD.HssType() # "\t" # ' . SystemVariables::Escape('oD.Name()') .
                ' # "\t" # sDev # "\t" # sPeer # "\t" # sTs # "\t" # sDc # "\t" # sCs);' . PHP_EOL .
                ' } } }' . PHP_EOL .
                '}' . PHP_EOL;
        }

        /**
         * Zerlegt die Ausgabe von Script().
         *
         * @return array ['Now' => int, 'Devices' => Adresse => ['address', 'interface', 'type', 'name', 'device', 'peer', 'time', 'dutyCycle', 'carrierSense']]
         */
        public static function ParseScript(string $Output, string $TimeZone): array
        {
            $Now = 0;
            $Devices = [];
            foreach (preg_split('/\r\n|\n/', $Output) as $Line) {
                if (str_starts_with($Line, self::Header)) {
                    $Now = SystemVariables::ToUnixTime(substr($Line, strlen(self::Header)), $TimeZone);
                    continue;
                }
                $Row = explode("\t", $Line);
                if (count($Row) < self::Columns) {
                    continue;
                }
                $Devices[$Row[0]] = [
                    'address'      => $Row[0],
                    'interface'    => $Row[1],
                    'type'         => $Row[2],
                    'name'         => SystemVariables::Unescape($Row[3]),
                    'device'       => self::FixLevel($Row[4]),
                    'peer'         => self::FixLevel($Row[5]),
                    'time'         => ($Row[6] === '') ? 0 : SystemVariables::ToUnixTime($Row[6], $TimeZone),
                    'dutyCycle'    => is_numeric($Row[7]) ? round((float) $Row[7], 1) : null,
                    'carrierSense' => is_numeric($Row[8]) ? round((float) $Row[8], 1) : null
                ];
            }
            return ['Now' => $Now, 'Devices' => $Devices];
        }

        /**
         * Korrigiert einen Pegel der CCU (dBm). 0, ±1, ±128, ±256 und 65536 bedeuten „unbekannt“, 2..127 fehlt das Vorzeichen,
         * 129..255 sind vorzeichenlos gespeichert (Wert − 256). Nach aiohomematic (`docs/user/troubleshooting/rssi_fix.md`).
         *
         * @return int|null Pegel in dBm, null = unbekannt.
         */
        public static function FixLevel(mixed $Raw): ?int
        {
            if (!is_numeric($Raw)) {
                return null;
            }
            $Level = (int) $Raw;
            if (in_array(abs($Level), [0, 1, 128, 256, self::Unknown], true)) {
                return null;
            }
            if (($Level > 1) && ($Level < 128)) {
                return -$Level;
            }
            if (($Level > 128) && ($Level < 256)) {
                return $Level - 256;
            }
            return ($Level < 0) && ($Level > -128) ? $Level : null;
        }

        /**
         * Bewertung eines Pegels.
         *
         * @param int|null $Level Pegel in dBm (der schwächere der beiden Richtungen).
         * @param int $Good Ab diesem Pegel gut.
         * @param int $Weak Unter diesem Pegel schwach.
         *
         * @return string `good`, `medium`, `weak` oder `unknown`.
         */
        public static function Quality(?int $Level, int $Good, int $Weak): string
        {
            if ($Level === null) {
                return 'unknown';
            }
            if ($Level >= $Good) {
                return 'good';
            }
            return ($Level < $Weak) ? 'weak' : 'medium';
        }

        /**
         * Führt die Quellen zusammen.
         *
         * @param array $Devices     Geräte aus ParseScript().
         * @param array|null $RssiInfo Antwort von `rssiInfo` (BidCos-RF), null ohne BidCos-RF.
         * @param array|null $RfDevices Antwort von `listDevices` (BidCos-RF), null ohne BidCos-RF.
         * @param array $Interfaces  Schnittstelle (`BidCos-RF`/`HmIP-RF`) => Antwort von `listBidcosInterfaces`.
         * @param int $Good          Ab diesem Pegel gut.
         * @param int $Weak          Unter diesem Pegel schwach.
         * @param array[] $Configured Eingerichtete Funk-Gateways aus `rfd.conf` (ScriptParser::ParseGatewayConfig, Gruppe `rfd`):
         *                           liefert die Namen aus der WebUI; fehlt ein Gateway dort und in listBidcosInterfaces, ist es nicht
         *                           mehr eingerichtet (Geräte sind ihm in der CCU trotzdem noch zugeordnet).
         *
         * @return array ['devices' => array[], 'gateways' => array[]]; je Gerät `address`, `interface`, `type`, `name`, `gateway`
         *               (zugeordnetes Gateway, nur BidCos-RF), `device`, `peer` (dBm oder null), `level` (schwächere Richtung),
         *               `quality`, `time` (HmIP: Zeitstempel von RSSI_DEVICE), `roaming` (BidCos-RF: Gateway wählt der rfd),
         *               `links` (BidCos-RF: alle Gateways, die das Gerät hören, mit Pegeln, das zugeordnete zuerst);
         *               je Gateway `address`, `interface`, `type`, `name`, `connected`, `configured`, `default`, `dutyCycle` (%),
         *               `carrierSense` (%), `devices`.
         */
        public static function Build(array $Devices, ?array $RssiInfo, ?array $RfDevices, array $Interfaces, int $Good, int $Weak, array $Configured = []): array
        {
            // Gateways aus listBidcosInterfaces
            $Gateways = [];
            foreach ($Interfaces as $Interface => $List) {
                foreach (is_array($List) ? $List : [] as $Gateway) {
                    if (!isset($Gateway['ADDRESS'])) {
                        continue;
                    }
                    $Type = (string) ($Gateway['TYPE'] ?? '');
                    $Description = (string) ($Gateway['DESCRIPTION'] ?? '');
                    $Gateways[$Gateway['ADDRESS']] = [
                        'address'      => (string) $Gateway['ADDRESS'],
                        'interface'    => $Interface,
                        'type'         => $Type,
                        // Die HmIP-Zentrale beschreibt sich nur mit Typ und Seriennummer („HMIP_CCU2 3014…“), das ist kein Name
                        'name'         => in_array($Description, [$Type, $Type . ' ' . $Gateway['ADDRESS']], true) ? '' : $Description,
                        'connected'    => (bool) ($Gateway['CONNECTED'] ?? false),
                        'configured'   => true,
                        'default'      => (bool) ($Gateway['DEFAULT'] ?? false),
                        'dutyCycle'    => isset($Gateway['DUTY_CYCLE']) && is_numeric($Gateway['DUTY_CYCLE']) ? (float) $Gateway['DUTY_CYCLE'] : null,
                        'carrierSense' => null,
                        'devices'      => 0
                    ];
                }
            }
            // Zugeordnetes Gateway der BidCos-Geräte; Gateways, die listBidcosInterfaces nicht kennt, sind nicht verbunden
            $Assigned = [];
            // ROAMING: der rfd wählt das Gateway selbst (die Zuordnung ist dann nur der aktuelle Stand)
            $Roaming = [];
            foreach (is_array($RfDevices) ? $RfDevices : [] as $Description) {
                if (($Description['PARENT'] ?? '') !== '' || !isset($Description['ADDRESS'])) {
                    continue;
                }
                $Roaming[$Description['ADDRESS']] = (bool) ($Description['ROAMING'] ?? false);
                $Interface = (string) ($Description['INTERFACE'] ?? '');
                if ($Interface === '') {
                    continue;
                }
                $Assigned[$Description['ADDRESS']] = $Interface;
                if (!isset($Gateways[$Interface])) {
                    $Gateways[$Interface] = [
                        'address'   => $Interface, 'interface' => self::InterfaceRF, 'type' => '', 'name' => '', 'connected' => false, 'configured' => false,
                        'default'   => false, 'dutyCycle' => null, 'carrierSense' => null, 'devices' => 0
                    ];
                }
            }
            // Namen aus der WebUI und eingerichtete, aber nicht verbundene Gateways aus rfd.conf
            foreach ($Configured as $Config) {
                $Serial = (string) ($Config['Serial Number'] ?? '');
                if ($Serial === '') {
                    continue;
                }
                $Gateways[$Serial] ??= [
                    'address'    => $Serial, 'interface' => self::InterfaceRF, 'type' => (string) ($Config['Type'] ?? ''), 'name' => '', 'connected' => false,
                    'configured' => true, 'default' => false, 'dutyCycle' => null, 'carrierSense' => null, 'devices' => 0
                ];
                $Gateways[$Serial]['configured'] = true;
                $Name = (string) (($Config['Name'] ?? '') !== '' ? $Config['Name'] : ($Config['Description'] ?? ''));
                if ($Name !== '') {
                    $Gateways[$Serial]['name'] = $Name;
                }
            }
            // HmIP: Funkmodul der CCU ergänzt das Gateway der CCU, Access Points (HAP, DRAP) sind eigene Gateways
            foreach ($Devices as $Address => $Device) {
                if (!in_array($Device['type'], self::AccessPointTypes, true)) {
                    continue;
                }
                if (in_array($Device['type'], self::CentralModuleTypes, true)) {
                    foreach ($Gateways as &$Gateway) {
                        if (($Gateway['interface'] === self::InterfaceIP) && $Gateway['default']) {
                            $Gateway['carrierSense'] = $Device['carrierSense'];
                            $Gateway['dutyCycle'] ??= $Device['dutyCycle'];
                        }
                    }
                    unset($Gateway);
                    continue;
                }
                $Gateways[$Address] = [
                    'address'   => $Address, 'interface' => self::InterfaceIP, 'type' => $Device['type'], 'name' => $Device['name'],
                    'connected' => true, 'configured' => true, 'default' => false, 'dutyCycle' => $Device['dutyCycle'], 'carrierSense' => $Device['carrierSense'], 'devices' => 0
                ];
            }
            $Result = [];
            foreach ($Devices as $Address => $Device) {
                if (in_array($Device['type'], self::AccessPointTypes, true) || in_array($Device['type'], self::VirtualTypes, true)) {
                    continue;
                }
                $Entry = [
                    'address'   => $Address,
                    'interface' => $Device['interface'],
                    'type'      => $Device['type'],
                    'name'      => $Device['name'],
                    'gateway'   => '',
                    'device'    => $Device['device'],
                    'peer'      => $Device['peer'],
                    'time'      => $Device['time'],
                    'roaming'   => false,
                    'links'     => []
                ];
                if ($Device['interface'] === self::InterfaceRF) {
                    $Entry['time'] = 0;
                    $Entry['gateway'] = $Assigned[$Address] ?? '';
                    $Entry['roaming'] = $Roaming[$Address] ?? false;
                    foreach ((array) ($RssiInfo[$Address] ?? []) as $Partner => $Pair) {
                        if (!isset($Gateways[$Partner]) || !is_array($Pair)) {
                            continue;
                        }
                        $Link = ['gateway' => (string) $Partner, 'device' => self::FixLevel($Pair[0] ?? null), 'peer' => self::FixLevel($Pair[1] ?? null)];
                        if (($Link['device'] !== null) || ($Link['peer'] !== null)) {
                            $Entry['links'][] = $Link;
                        }
                    }
                    // Pegel zum zugeordneten Gateway, sonst zum besten
                    usort($Entry['links'], fn (array $A, array $B) => [$B['gateway'] === $Entry['gateway'], self::Worst($B) ?? -999] <=> [$A['gateway'] === $Entry['gateway'], self::Worst($A) ?? -999]);
                    $Entry['device'] = $Entry['links'][0]['device'] ?? null;
                    $Entry['peer'] = $Entry['links'][0]['peer'] ?? null;
                    if (($Entry['gateway'] === '') && isset($Entry['links'][0])) {
                        $Entry['gateway'] = $Entry['links'][0]['gateway'];
                    }
                    if (isset($Gateways[$Entry['gateway']])) {
                        $Gateways[$Entry['gateway']]['devices']++;
                    }
                }
                $Entry['level'] = self::Worst($Entry);
                $Entry['quality'] = self::Quality($Entry['level'], $Good, $Weak);
                $Result[] = $Entry;
            }
            // Schwächster Pegel zuerst, unbekannte zuletzt
            usort($Result, fn (array $A, array $B) => [$A['level'] === null, $A['level'] ?? 0, $A['name']] <=> [$B['level'] === null, $B['level'] ?? 0, $B['name']]);
            return ['devices' => $Result, 'gateways' => array_values($Gateways)];
        }

        /**
         * Der schwächere bekannte Pegel beider Richtungen (eine Verbindung ist so gut wie ihre schwächere Richtung).
         */
        private static function Worst(array $Pair): ?int
        {
            $Known = array_filter([$Pair['device'], $Pair['peer']], fn ($Level) => $Level !== null);
            return count($Known) ? min($Known) : null;
        }
    }
}

/* @} */
