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
require_once __DIR__ . '/../libs/HMSystemVariableBase.php';  // Basisklasse Systemvariablen (CCU-Programm, Taste)
require_once __DIR__ . '/../libs/HMServiceMessages.php';  // Scripte und Parser der Servicemeldungen
require_once __DIR__ . '/../libs/HMTile.php';  // Listen-Kachel
require_once __DIR__ . '/../libs/HMDeviceInstances.php';  // Symcon-Instanz zu einer Adresse

/**
 * HomeMaticServicemeldungen ist die Klasse für das IPS-Modul 'HomeMatic Servicemeldungen'.
 * Liest die Servicemeldungen aller Schnittstellen der CCU (BidCos-RF, HmIP-RF, BidCos-Wired, System) und zeigt sie in einer
 * Kachel. Statusvariablen gibt es nicht, die Meldungen liefert die Instanz-Funktion HM_GetServiceMessages.
 * Das CCU-Programm reagiert auf die Systemvariable „Servicemeldungen“ (ID 41) und drückt die gewählte Taste.
 * Erweitert HMSystemVariableBase.
 *
 * @property array $Messages Zuletzt gelesene Servicemeldungen (siehe \HMExtended\ServiceMessages::Parse).
 * @property int $ReadTime Uhrzeit der CCU beim letzten Lesen (Unix-Zeit, 0 = noch nicht gelesen).
 * @property int $LastRead Zeitpunkt des letzten Lesens in Symcon (Unix-Zeit, für die Drosselung der Ereignisse).
 */
class HomeMaticServicemeldungen extends HMSystemVariableBase
{
    use HMTileFunctions;
    use HMDeviceInstances;

    protected const ProgramPrefix = 'Symcon Servicemeldungen ';

    /**
     * Mindestabstand in Sekunden zwischen zwei Lesevorgängen, die das CCU-Programm (Tastendruck) auslöst.
     * Die CCU aktualisiert die Systemvariable 41 auch, wenn ein Gerät eine anstehende Meldung erneut sendet (live etwa alle 20–30 s).
     * Manuelles Lesen (Kachel, HM_GetServiceMessages, Bestätigen) ist nicht gedrosselt.
     */
    private const MinEventInterval = 30;

    /**
     * Aktionen der Kachel.
     */
    private const TileActionReload = 'TileReload';
    private const TileActionConfirm = 'TileConfirm';
    private const TileActionRead = 'TileRead';

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterTile();
        $this->Messages = [];
        $this->ReadTime = 0;
        $this->LastRead = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->Messages = [];
        $this->ReadTime = 0;
        $this->LastRead = 0;
        parent::ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     * Das Event der Taste startet das Lesen, frühestens MinEventInterval Sekunden nach dem letzten Lesen.
     * Ohne Drosselung liest ein einmaliger Timer nach ReadDelay (StartDelayedRead). Bei Drosselung wartet der Intervall-Timer
     * bis zum frühesten Zeitpunkt (ein Thread soll nicht bis zu 30 s schlafen); weitere Events bis dahin verschieben den Zeitpunkt nicht
     * (er wird jedes Mal aus dem letzten Lesen berechnet).
     */
    public function ReceiveData(string $JSONString): string
    {
        $Event = json_decode($JSONString, true);
        if (!is_array($Event) || (($Event['VariableName'] ?? '') !== \HMExtended\SystemVariables::KeyDatapoint)) {
            return '';
        }
        $Wait = max(static::ReadDelay, ($this->LastRead + self::MinEventInterval - time()) * 1000);
        $this->SendDebug('EVENT', $Event['DeviceID'] . '.' . $Event['VariableName'] . ' (read in ' . $Wait . ' ms)', 0);
        if ($Wait > static::ReadDelay) {
            $this->SetTimerInterval(static::ActionRead, $Wait);
        } else {
            $this->StartDelayedRead(static::ActionRead, static::ReadDelay);
        }
        return '';
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        return json_encode($this->AddKeyAndProgramToForm($Form));
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_GetServiceMessages'.
     * Liest die anstehenden Servicemeldungen aller Schnittstellen von der CCU.
     *
     * @return array|false Liste der Meldungen, neueste zuerst; je Meldung `id` (ReGa-ID, für HM_ConfirmServiceMessage), `address`,
     *                     `device`, `channel`, `datapoint`, `value`, `text`, `group`, `interface`, `type`, `deviceName`, `channelName`,
     *                     `rooms`, `functions`, `first`, `last` (Unix-Zeit), `confirmable` und `instanceID` (Symcon-Instanz des
     *                     Gerätes, 0 = keine). Bei einem Fehler false (die Meldung erhält der Aufrufer).
     */
    public function GetServiceMessages(): array|false
    {
        if (!$this->ReadValues(true)) {
            return false;
        }
        return $this->MessagesWithInstances();
    }

    /**
     * IPS-Instanz-Funktion 'HM_ConfirmServiceMessage'.
     * Bestätigt eine Servicemeldung auf der CCU. Bestätigen lassen sich nur Meldungen, deren Datenpunkt das erlaubt
     * (z.B. „Gerätekommunikation war gestört“); die übrigen verschwinden von selbst, sobald die Ursache behoben ist.
     *
     * @param int $MessageID ReGa-ID der Meldung (Feld `id` aus HM_GetServiceMessages).
     *
     * @return bool True wenn bestätigt, sonst false (die Meldung erhält der Aufrufer).
     */
    public function ConfirmServiceMessage(int $MessageID): bool
    {
        $Result = $this->Confirm([$MessageID]);
        if ($Result === false) {
            return false;
        }
        switch ($Result[$MessageID] ?? null) {
            case true:
                return true;
            case false:
                echo $this->Translate('This service message cannot be confirmed.') . PHP_EOL;
                return false;
        }
        echo sprintf($this->Translate('Service message %d does not exist on the CCU.'), $MessageID) . PHP_EOL;
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'HM_ConfirmAllServiceMessages'.
     * Bestätigt alle Servicemeldungen, die sich bestätigen lassen.
     *
     * @return int|false Anzahl der bestätigten Meldungen, bei einem Fehler false.
     */
    public function ConfirmAllServiceMessages(): int|false
    {
        if (!$this->ReadValues(true)) {
            return false;
        }
        $Ids = array_column(array_filter($this->Messages, fn (array $Message) => $Message['confirmable']), 'id');
        if (count($Ids) == 0) {
            return 0;
        }
        $Result = $this->Confirm($Ids);
        if ($Result === false) {
            return false;
        }
        return count(array_filter($Result, fn ($Done) => $Done === true));
    }

    //################# protected

    /**
     * Keine Statusvariablen.
     */
    protected function UpdateVariables(): bool
    {
        return true;
    }

    /**
     * Liest die Servicemeldungen und schickt sie an die Kachel.
     * Es wird immer die ganze Liste gelesen.
     *
     * @param bool $Full true: manuell bzw. beim Start, die Kachel erhält die Daten immer (beendet die Ladeanzeige);
     *                   false: Ereignis oder Intervall, die Kachel erhält nur geänderte Listen.
     */
    protected function ReadValues(bool $Full): bool
    {
        $Output = $this->RunScript(\HMExtended\ServiceMessages::Script());
        if ($Output === false) {
            return false;
        }
        $this->LastRead = time();
        $Result = \HMExtended\ServiceMessages::Parse($Output, date_default_timezone_get());
        // Die CCU aktualisiert die Systemvariable 41 auch, wenn ein Gerät eine anstehende Meldung erneut sendet (gleiche Anzahl,
        // nur „Letzte Meldung“ neu). Die Kachel erhält nur geänderte Listen.
        $Changed = ($Result['Messages'] != $this->Messages);
        $this->Messages = $Result['Messages'];
        $this->ReadTime = $Result['Now'];
        $this->SendDebug('Messages', count($Result['Messages']) . ($Changed ? ' (changed)' : ''), 0);
        if ($Changed || $Full) {
            $this->PushTile();
        }
        return true;
    }

    /**
     * Das CCU-Programm reagiert auf die Systemvariable „Servicemeldungen“ (ID 41).
     */
    protected function ProgramVariables(): array
    {
        return [\HMExtended\ServiceMessages::CounterVariable => \HMExtended\ServiceMessages::CounterValueType];
    }

    /**
     * Verarbeitet die Aktionen der Kachel.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        switch ($Ident) {
            case self::TileActionReload:
                $done = true;
                $this->PushTile();
                return;
            case self::TileActionRead:
                $done = true;
                $this->ReadValues(true);
                return;
            case self::TileActionConfirm:
                $done = true;
                if ((int) $Value == 0) {
                    $this->ConfirmAllServiceMessages();
                } else {
                    $this->ConfirmServiceMessage((int) $Value);
                }
                return;
        }
    }

    /**
     * Daten der Kachel.
     */
    protected function GetTileData(): array
    {
        return [
            'time'     => $this->ReadTime,
            'messages' => $this->MessagesWithInstances()
        ];
    }

    /**
     * Texte des Kachelskripts: Beschriftungen, Datenpunkte und Werte der Aufzählungen.
     */
    protected function GetTileTextKeys(): array
    {
        return array_merge([
            'Service messages', 'No service messages', 'As of %s', 'Confirm', 'Confirm all', 'Confirm all service messages that can be confirmed?', 'Read CCU',
            'Reachability', 'Battery', 'Sabotage', 'Configuration', 'Other', 'First', 'Last', 'Device not set up in Symcon', 'confirmable'
        ], array_keys(self::MessageTexts()));
    }

    /**
     * Bekannte Datenpunkte und Werte von Aufzählungen (Schlüssel der locale.json), damit die Kachel sie übersetzen kann.
     *
     * @return string[] Schlüssel => ohne Bedeutung.
     */
    private static function MessageTexts(): array
    {
        return array_fill_keys([
            'UNREACH', 'STICKY_UNREACH', 'LOWBAT', 'LOW_BAT', 'CONFIG_PENDING', 'UPDATE_PENDING', 'DEVICE_IN_BOOTLOADER', 'SABOTAGE', 'STICKY_SABOTAGE',
            'ERROR_SABOTAGE', 'ERROR_CODE', 'ERROR_OVERHEAT', 'ERROR_OVERLOAD', 'ERROR_UPDATE', 'ERROR_DEGRADED_CHAMBER', 'ERROR_UNDERVOLTAGE',
            'ERROR_POWER_SHORT_CIRCUIT_BUS_1', 'ERROR_POWER_SHORT_CIRCUIT_BUS_2', 'ERROR_SHORT_CIRCUIT_DATA_LINE_BUS_1', 'ERROR_SHORT_CIRCUIT_DATA_LINE_BUS_2',
            'ERROR_BUS_CONFIG_MISMATCH', 'DUTYCYCLE', 'DUTY_CYCLE', 'U_SOURCE_FAIL', 'USBH_POWERFAIL', 'EMERGENCY_OPERATION', 'FAULT_REPORTING', 'ERROR',
            'VALVE_TIGHT', 'ADJUSTING_RANGE_TOO_LARGE', 'ADJUSTING_RANGE_TOO_SMALL', 'COMMUNICATION_ERROR', 'VALVE_ERROR_POSITION'
        ], true);
    }

    /**
     * Bestätigt Meldungen auf der CCU und liest die Liste danach neu.
     *
     * @param int[] $Ids ReGa-IDs.
     *
     * @return array|false ID => true/false/null (siehe ParseConfirm), bei einem Fehler false.
     */
    private function Confirm(array $Ids): array|false
    {
        $Output = $this->RunScript(\HMExtended\ServiceMessages::ConfirmScript($Ids));
        if ($Output === false) {
            return false;
        }
        $Result = \HMExtended\ServiceMessages::ParseConfirm($Output);
        $this->SendDebug('Confirm', $Result, 0);
        if (in_array(true, $Result, true)) {
            ob_start();
            $this->ReadValues(true);
            $Errors = trim((string) ob_get_clean());
            if ($Errors !== '') {
                $this->SendDebug('Error', $Errors, 0);
            }
        }
        return $Result;
    }

    /**
     * Ergänzt die Meldungen um die Symcon-Instanz des Gerätes (siehe HMDeviceInstances).
     */
    private function MessagesWithInstances(): array
    {
        return $this->AddDeviceInstances($this->Messages);
    }
}

/* @} */
