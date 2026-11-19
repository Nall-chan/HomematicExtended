<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       4.03
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/../libs/HMRadioLevels.php';  // Scripte und Auswertung der Funkpegel
require_once __DIR__ . '/../libs/HMTile.php';  // Listen-Kachel
require_once __DIR__ . '/../libs/HMDeviceInstances.php';  // Symcon-Instanz zu einer Adresse

/**
 * HomeMaticFunkpegel ist die Klasse für das IPS-Modul 'HomeMatic Funkpegel'.
 * Zeigt die Funkpegel (RSSI) aller Geräte von BidCos-RF und HmIP-RF und den Duty Cycle der Funkmodule in einer Kachel.
 * Statusvariablen gibt es nicht, die Werte liefert die Instanz-Funktion HM_GetRSSIValues. Gelesen wird zyklisch (Intervall),
 * beim Öffnen der Kachel (wenn die Daten älter als eine Minute sind) und auf Anforderung.
 * Erweitert HMBase.
 *
 * @property array $Levels Zuletzt gelesene Pegel (siehe \HMExtended\RadioLevels::Build).
 * @property int $ReadTime Uhrzeit der CCU beim letzten Lesen (Unix-Zeit, 0 = noch nicht gelesen).
 * @property int $LastRead Zeitpunkt des letzten Lesens in Symcon (Unix-Zeit).
 * @property array|null $GatewayConfig Eingerichtete Funk-Gateways aus `rfd.conf` (Namen), null = noch nicht gelesen.
 */
class HomeMaticFunkpegel extends HMBase
{
    use HMTileFunctions;
    use HMDeviceInstances;

    protected const SummaryShowsHost = true;

    /**
     * Platzhalter der Adresse (die Instanz gehört zu keinem Gerät; Address/Protocol braucht der Homematic-Konfigurator von Symcon).
     */
    private const AddressPlaceholder = 'XXX9999998';
    /**
     * Mindestwert des Intervalls in Sekunden.
     */
    private const MinInterval = 60;
    /**
     * Daten, die älter sind, werden beim Öffnen der Kachel neu gelesen (Sekunden).
     */
    private const TileMaxAge = 60;
    /**
     * Statuscodes.
     */
    private const StatusIntervalTooSmall = IS_EBASE + 3;
    private const StatusInvalidLevels = IS_EBASE + 5;
    /**
     * Aktionen der Kachel.
     */
    private const TileActionReload = 'TileReload';
    private const TileActionRead = 'TileRead';

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterHMPropertys(self::AddressPlaceholder);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Interval, 300);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::LevelGood, -75);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::LevelWeak, -85);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadRadioLevels, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'' . \HMExtended\Device\Timer::ReadRadioLevels . '\', true);');
        $this->RegisterTile();
        $this->Levels = [];
        $this->ReadTime = 0;
        $this->LastRead = 0;
        $this->GatewayConfig = null;
    }

    /**
     * Interne Funktion des SDK.
     * Mit aktivem Parent wird sofort gelesen, Fehler werden gemeldet.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->Levels = [];
        $this->ReadTime = 0;
        $this->LastRead = 0;
        $this->GatewayConfig = null;
        $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRadioLevels, 0);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if (!$this->CheckConfig()) {
            return;
        }
        if (!$this->HasActiveParent()) {
            return;
        }
        $this->ReadLevels(true);
        $this->StartIntervalTimer();
    }

    /**
     * Interne Funktion des SDK.
     * Das Modul empfängt keine Events.
     */
    public function ReceiveData(string $JSONString): string
    {
        return '';
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_GetRSSIValues'.
     * Liest die Funkpegel aller Geräte von BidCos-RF und HmIP-RF von der CCU.
     *
     * @return array|false ['time' => Uhrzeit der CCU, 'devices' => array[], 'gateways' => array[]], siehe README; je Gerät zusätzlich
     *                     `instanceID` (Symcon-Instanz des Gerätes, 0 = keine). Bei einem Fehler false (die Meldung erhält der Aufrufer).
     */
    public function GetRSSIValues(): array|false
    {
        if (!$this->ReadLevels(true)) {
            return false;
        }
        return $this->LevelsWithInstances();
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert oder die Instanz verbunden bzw. getrennt wird.
     */
    protected function IOChangeState(int $State): void
    {
        $this->ApplyChanges();
    }

    /**
     * Verarbeitet Timer und Aktionen der Kachel.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        switch ($Ident) {
            case \HMExtended\Device\Timer::ReadRadioLevels:
                $done = true;
                $this->ReadLevels(false);
                return;
            case self::TileActionReload:
                $done = true;
                // Beim Öffnen der Kachel: alte Daten zuerst zeigen, dann neu lesen
                $this->PushTile();
                if ((time() - $this->LastRead) > self::TileMaxAge) {
                    $this->ReadLevels(true);
                }
                return;
            case self::TileActionRead:
                $done = true;
                $this->ReadLevels(true);
                return;
        }
    }

    /**
     * Daten der Kachel.
     */
    protected function GetTileData(): array
    {
        return $this->LevelsWithInstances() + [
            'good' => $this->ReadPropertyInteger(\HMExtended\Device\Property::LevelGood),
            'weak' => $this->ReadPropertyInteger(\HMExtended\Device\Property::LevelWeak)
        ];
    }

    /**
     * Texte des Kachelskripts.
     */
    protected function GetTileTextKeys(): array
    {
        return [
            'Weak reception', 'No radio devices', 'As of %s', 'Read CCU', 'Good', 'Medium', 'Weak', 'Unknown',
            'Device receives', 'Central receives', 'Sort', 'Radio modules', 'not connected', 'no longer set up', 'Duty cycle', 'Carrier sense',
            'Device not set up in Symcon', 'Received also by', 'assigned', 'Roaming', 'The CCU chooses the gateway itself', 'All gateways'
        ];
    }

    /**
     * Liest alle Quellen und schickt das Ergebnis an die Kachel.
     *
     * @param bool $Force true: die Kachel erhält die Daten immer (beendet die Ladeanzeige), false: nur bei Änderungen.
     *
     * @return bool true bei Erfolg.
     */
    private function ReadLevels(bool $Force): bool
    {
        $Output = $this->LoadHMScript(\HMExtended\RadioLevels::Script());
        if ($Output === false) {
            return false;
        }
        [$Output, $Xml] = \HMExtended\ScriptParser::SplitOutput($Output);
        // Die CCU liefert immer das XML der Variablen, ohne XML ist die Antwort unvollständig
        if ($Xml === '') {
            echo $this->Translate('Invalid response from CCU.') . PHP_EOL;
            return false;
        }
        $Script = \HMExtended\RadioLevels::ParseScript($Output, date_default_timezone_get());
        $ParentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $RfOpen = ($ParentId > 0) && (bool) @IPS_GetProperty($ParentId, 'RFOpen');
        $IpOpen = ($ParentId > 0) && (bool) @IPS_GetProperty($ParentId, 'IPOpen');
        $RssiInfo = null;
        $RfDevices = null;
        $Interfaces = [];
        if ($RfOpen) {
            $RssiInfo = $this->SendRPC('rssiInfo', \HMExtended\CCU::ProtocolBidCosRF, []);
            if (!is_array($RssiInfo)) {
                return false;
            }
            $RfDevices = $this->SendRPC('listDevices', \HMExtended\CCU::ProtocolBidCosRF, []);
            if (!is_array($RfDevices)) {
                return false;
            }
            // Gateways und Duty Cycle sind eine Zusatzabfrage: Fehler nur im Debug
            $Interfaces[\HMExtended\RadioLevels::InterfaceRF] = $this->SendRPC('listBidcosInterfaces', \HMExtended\CCU::ProtocolBidCosRF, [], null, false, true);
            // Namen der Gateways aus rfd.conf ändern sich selten: nur beim ersten und bei manuellem Lesen
            if (($this->GatewayConfig === null) || $Force) {
                $this->GatewayConfig = $this->LoadGatewayConfig();
            }
        }
        if ($IpOpen) {
            $Interfaces[\HMExtended\RadioLevels::InterfaceIP] = $this->SendRPC('listBidcosInterfaces', \HMExtended\CCU::ProtocolHmIP, [], null, false, true);
        }
        $Levels = \HMExtended\RadioLevels::Build(
            $Script['Devices'],
            $RssiInfo,
            $RfDevices,
            $Interfaces,
            $this->ReadPropertyInteger(\HMExtended\Device\Property::LevelGood),
            $this->ReadPropertyInteger(\HMExtended\Device\Property::LevelWeak),
            $RfOpen ? ($this->GatewayConfig ?? []) : []
        );
        $Changed = ($Levels != $this->Levels);
        $this->Levels = $Levels;
        $this->ReadTime = $Script['Now'];
        $this->LastRead = time();
        $this->SendDebug('Devices', count($Levels['devices']) . ($Changed ? ' (changed)' : ''), 0);
        if ($Changed || $Force) {
            $this->PushTile();
        }
        return true;
    }

    /**
     * Liest die eingerichteten Funk-Gateways aus `rfd.conf` (Namen aus der WebUI). Optionale Zusatzabfrage: Fehler nur per Debug.
     *
     * @return array[] Gateways (siehe ScriptParser::ParseGatewayConfig), leer wenn die Datei nicht lesbar ist.
     */
    private function LoadGatewayConfig(): array
    {
        ob_start();
        $Result = $this->LoadHMScript(\HMExtended\ScriptParser::GatewayConfigScript());
        $Errors = (string) ob_get_clean();
        if ($Result === false) {
            $this->SendDebug('Gateway config', 'not available ' . $Errors, 0);
            return [];
        }
        $Gateways = \HMExtended\ScriptParser::ParseGatewayConfig(\HMExtended\ScriptParser::SplitOutput($Result)[0])['rfd'] ?? [];
        $this->SendDebug('Gateway config', $Gateways, 0);
        return $Gateways;
    }

    /**
     * Pegel mit Symcon-Instanz je Gerät und Uhrzeit der CCU.
     */
    private function LevelsWithInstances(): array
    {
        $Levels = $this->Levels;
        return [
            'time'     => $this->ReadTime,
            'devices'  => $this->AddDeviceInstances($Levels['devices'] ?? []),
            'gateways' => $Levels['gateways'] ?? []
        ];
    }

    /**
     * Startet den Timer mit dem Intervall der Eigenschaft (0 = aus).
     */
    private function StartIntervalTimer(): void
    {
        $Interval = $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval);
        $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRadioLevels, ($Interval >= self::MinInterval) ? $Interval * 1000 : 0);
    }

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     *
     * @return bool true, wenn gelesen werden kann.
     */
    private function CheckConfig(): bool
    {
        $Interval = $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval);
        if (($Interval > 0) && ($Interval < self::MinInterval)) {
            $this->SetStatus(self::StatusIntervalTooSmall);
            return false;
        }
        if ($this->ReadPropertyInteger(\HMExtended\Device\Property::LevelGood) <= $this->ReadPropertyInteger(\HMExtended\Device\Property::LevelWeak)) {
            $this->SetStatus(self::StatusInvalidLevels);
            return false;
        }
        $this->SetStatus(IS_ACTIVE);
        return true;
    }
}

/* @} */
