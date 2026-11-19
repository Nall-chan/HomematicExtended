<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMDeviceInstances.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       4.03
 */

/**
 * Findet die Symcon-Instanz zu einer Homematic-Adresse (nur lesend), für Kacheln, die Geräte der CCU auflisten
 * (Servicemeldungen, Funkpegel). Berücksichtigt werden die Homematic-Geräte von Symcon und die Geräte-Instanzen dieser Library
 * (z.B. RGBW, Powermeter); die Instanzen von Symcon haben Vorrang. Je Gerät gilt die Instanz mit der kleinsten Adresse (meist Kanal 1).
 */
trait HMDeviceInstances
{
    /**
     * Module dieser Library, die ein Gerät der CCU mit echter Adresse abbilden.
     * Nur diese werden gefragt: `IPS_GetProperty` wartet, solange die andere Instanz beschäftigt ist. Fragen sich zwei
     * Kachel-Instanzen (Servicemeldungen, Funkpegel) gegenseitig, während beide lesen, blockieren sie sich bis zur
     * Zeitüberschreitung (live 2026-11-19, Aufrufkette [15486] ↔ [58386]). Die Gerätemodule fragen nie zurück.
     */
    private static array $DeviceModuleIds = [
        \HMExtended\GUID::Powermeter, \HMExtended\GUID::Weather, \HMExtended\GUID::WeeklyProgramHmIP, \HMExtended\GUID::LED16,
        \HMExtended\GUID::RGBW, \HMExtended\GUID::Dis_WM55, \HMExtended\GUID::Dis_EP_WM55, \HMExtended\GUID::HeatingGroup,
        \HMExtended\GUID::HeatingDevice, \HMExtended\GUID::HeatingGroupHmIP, \HMExtended\GUID::ClimacontrolRegulator
    ];

    /**
     * Liefert die Zuordnung Adresse => Instanz.
     *
     * @return array ['address' => Adresse mit Kanal => Instanz-ID, 'device' => Geräteadresse => Instanz-ID]
     */
    protected function DeviceInstanceMap(): array
    {
        // Zuletzt die Homematic-Geräte von Symcon: sie überschreiben, haben also Vorrang
        $ModuleIds = array_merge(self::$DeviceModuleIds, [\HMExtended\GUID::HmDevice]);
        $ByAddress = [];
        $ByDevice = [];
        foreach ($ModuleIds as $ModuleId) {
            $Devices = [];
            foreach (IPS_GetInstanceListByModuleID($ModuleId) as $InstanceId) {
                $Address = (string) @IPS_GetProperty($InstanceId, \HMExtended\Device\Property::Address);
                // Platzhalter (XXX…) gehören zu keinem Gerät
                if (($Address === '') || str_starts_with($Address, 'XXX')) {
                    continue;
                }
                $ByAddress[$Address] = $InstanceId;
                $Device = explode(':', $Address)[0];
                if (!isset($Devices[$Device]) || (strnatcmp($Address, $Devices[$Device][0]) < 0)) {
                    $Devices[$Device] = [$Address, $InstanceId];
                }
            }
            foreach ($Devices as $Device => [, $InstanceId]) {
                $ByDevice[$Device] = $InstanceId;
            }
        }
        return ['address' => $ByAddress, 'device' => $ByDevice];
    }

    /**
     * Ergänzt Einträge um `instanceID` und `instanceName` (0 bzw. leer ohne Instanz).
     *
     * @param array[] $Items Einträge mit `address` (mit oder ohne Kanal).
     */
    protected function AddDeviceInstances(array $Items): array
    {
        if (count($Items) == 0) {
            return [];
        }
        $Map = $this->DeviceInstanceMap();
        foreach ($Items as &$Item) {
            $Device = explode(':', $Item['address'])[0];
            $InstanceId = $Map['address'][$Item['address']] ?? $Map['device'][$Device] ?? 0;
            $Item['instanceID'] = $InstanceId;
            $Item['instanceName'] = ($InstanceId > 0) ? IPS_GetName($InstanceId) : '';
        }
        return $Items;
    }
}

/* @} */
