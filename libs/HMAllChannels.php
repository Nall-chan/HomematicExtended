<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMAllChannels.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.90
 */
require_once __DIR__ . '/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/HMWriteValue.php';  // HM_WriteValue… über die Bedienung der Statusvariable
require_once __DIR__ . '/HMReadValue.php';  // Datenpunkt in Statusvariable lesen

/**
 * HMAllChannels ist die Basisklasse für Module, die ein Gerät mit allen Kanälen in einer Instanz abbilden.
 * Die Adresse der Instanz ist die Geräteadresse ohne Kanal, empfangen werden die Ereignisse aller Kanäle.
 *
 * Für jeden lesbaren oder bedienbaren Datenpunkt des Paramsets VALUES wird eine Statusvariable angelegt.
 * Ident der Variable: `<Datenpunkt>_<Kanal>`, beim Kanal 0 (Wartung) nur `<Datenpunkt>`.
 * Datenpunkte vom Typ ACTION (z.B. PRESS_SHORT) sind Ereignisse: Boolean-Variable, jedes Ereignis des Gerätes setzt sie (erneut) auf true.
 * Ob eine Variable bedienbar ist, bestimmt die Beschreibung des Gerätes (OPERATIONS). Bei einer Aktion löst die Bedienung den Datenpunkt aus (true).
 *
 * @property array $Datapoints Ident => [Kanal, Datenpunkt, Typ der CCU, bedienbar] der angelegten Statusvariablen.
 */
abstract class HMAllChannels extends HMBase
{
    use HMWriteValueFunctions;
    use HMReadValueFunctions;

    /**
     * Anzahl der Kanäle (ohne Wartungskanal 0), wenn die CCU die Kanäle nicht liefert.
     */
    protected const DefaultChannelCount = 1;

    /**
     * Datenpunkte, die nicht als Statusvariable angelegt werden.
     */
    protected const IgnoredDatapoints = ['AES_KEY', 'INSTALL_TEST'];

    /**
     * Beschriftungen der Datenpunkte (englischer Text, übersetzt über locale.json). Unbekannte Datenpunkte erhalten den lesbar geschriebenen Namen.
     */
    protected const Captions = [
        'UNREACH'              => 'Unreachable',
        'STICKY_UNREACH'       => 'Unreachable (sticky)',
        'CONFIG_PENDING'       => 'Configuration pending',
        'UPDATE_PENDING'       => 'Update pending',
        'DEVICE_IN_BOOTLOADER' => 'Device in bootloader',
        'LOWBAT'               => 'Battery low',
        'RSSI_DEVICE'          => 'Signal strength (device)',
        'RSSI_PEER'            => 'Signal strength (peer)',
        'PRESS_SHORT'          => 'Key press short',
        'PRESS_LONG'           => 'Key press long',
        'INSTALL_TEST'         => 'Installation test'
    ];

    /**
     * Farben der Werte von Aufzählungen: Datenpunkt => Index => Farbe (-1 = keine).
     */
    protected const EnumColors = [];

    /**
     * Datentypen der CCU, für die Statusvariablen angelegt werden (ACTION ist ein Ereignis und wird als Boolean abgebildet).
     */
    private const HmTypeToIpsType = [
        'BOOL'    => VARIABLETYPE_BOOLEAN,
        'INTEGER' => VARIABLETYPE_INTEGER,
        'FLOAT'   => VARIABLETYPE_FLOAT,
        'ENUM'    => VARIABLETYPE_INTEGER,
        'STRING'  => VARIABLETYPE_STRING,
        'ACTION'  => VARIABLETYPE_BOOLEAN
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        // Address und Protocol müssen vorhanden sein, sonst hängt sich der Symcon-Homematic-Konfigurator auf.
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->Datapoints = [];
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Ohne ausdrücklichen Status bliebe die Instanz nach einem Fehler beim Erstellen (z.B. beim Neuladen) in 101 (wird erstellt).
        $this->SetStatus(IS_ACTIVE);
        $this->Datapoints = [];
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($Address) {
            // Alle Kanäle des Gerätes: DeviceID ist die Geräteadresse mit Kanal
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $Address . ':[0-9]+".*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        $this->SetSummary($Address);
        // Fehler werden gemeldet, nach dem ersten Fehler folgt keine weitere Anfrage.
        if (($Address) && ($this->HasActiveParent())) {
            if ($this->CreateVariables()) {
                $this->ReadValues();
            }
        }
    }

    /**
     * Interne Funktion des SDK.
     * Verarbeitet die Ereignisse aller Kanäle des Gerätes.
     */
    public function ReceiveData(string $JSONString): string
    {
        $Event = json_decode($JSONString, true);
        if (!is_array($Event) || !isset($Event['DeviceID'], $Event['VariableName']) || !array_key_exists('VariableValue', $Event)) {
            $this->SendDebug('Invalid event', $JSONString, 0);
            return '';
        }
        $Channel = (int) substr((string) $Event['DeviceID'], (int) strrpos((string) $Event['DeviceID'], ':') + 1);
        $Ident = $this->IdentOf($Channel, (string) $Event['VariableName']);
        $this->SendDebug('EVENT:' . $Ident, $Event['VariableValue'], 0);
        $Datapoints = $this->Datapoints;
        if (!isset($Datapoints[$Ident])) {
            $this->SendDebug('Unknown variable', $Ident, 0);
            return '';
        }
        if ($Datapoints[$Ident][2] == 'ACTION') {
            // Ereignis (z.B. Tastendruck): jedes Ereignis setzt die Variable erneut auf true
            $this->SetValue($Ident, true);
            return '';
        }
        $this->SetValue($Ident, $this->Convert($Datapoints[$Ident][2], $Event['VariableValue']));
        return '';
    }

    /**
     * IPS-Instanz-Funktion 'HM_RequestState'.
     * Liest einen Datenpunkt von der CCU und schreibt ihn in die Statusvariable.
     *
     * @param string $Ident Ident der Statusvariable (`<Datenpunkt>_<Kanal>`, beim Kanal 0 nur der Datenpunkt).
     *
     * @return bool true, wenn die CCU den Wert geliefert hat (sonst die Meldung für den Aufrufer).
     */
    public function RequestState(string $Ident): bool
    {
        $Known = $this->Datapoints[$Ident] ?? null;
        if ($Known === null) {
            echo sprintf($this->Translate('Ident %s does not exist.'), $Ident) . PHP_EOL;
            return false;
        }
        // Ereignisse (Typ ACTION) haben keinen lesbaren Wert
        if ($Known[2] == 'ACTION') {
            echo sprintf($this->Translate('The value of %s could not be read.'), $Ident) . PHP_EOL;
            return false;
        }
        return $this->ReadDatapointToVariable($Ident, $this->ReadPropertyString(\HMExtended\Device\Property::Address) . ':' . $Known[0], $Known[1]);
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
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
        $this->ApplyChanges();
    }

    /**
     * Verarbeitet die Bedienung einer Statusvariable und schreibt den Datenpunkt in das Gerät.
     *
     * @param string $Ident Ident der Variable.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion vollständig verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        $Datapoints = $this->Datapoints;
        if (!isset($Datapoints[$Ident]) || !$Datapoints[$Ident][3]) {
            return;
        }
        [$Channel, $Datapoint, $Type] = $Datapoints[$Ident];
        if ($Type == 'ACTION') {
            // Die Aktion wird ausgelöst, die Variable setzt das Ereignis des Gerätes
            $this->WriteDatapoint($Channel, $Datapoint, true);
        } elseif ($this->WriteDatapoint($Channel, $Datapoint, $this->Convert($Type, $Value))) {
            $this->SetValue($Ident, $this->Convert($Type, $Value));
        }
        $done = true;
    }

    /**
     * Schreibt einen Datenpunkt eines Kanals (RPC setValue).
     *
     * @param int $Channel Kanal.
     * @param string $Datapoint Name des Datenpunktes.
     * @param mixed $Value Der Wert.
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    protected function WriteDatapoint(int $Channel, string $Datapoint, mixed $Value): bool
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . ':' . $Channel;
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Emulate = $this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus);
        return $this->SendRPC('setValue', $Protocol, [$Address, $Datapoint], $Value, $Emulate) !== null;
    }

    /**
     * Ident der Statusvariable eines Datenpunktes.
     */
    protected function IdentOf(int $Channel, string $Datapoint): string
    {
        return $Channel == 0 ? $Datapoint : $Datapoint . '_' . $Channel;
    }

    /**
     * Darstellung eines Datenpunktes: Aufzählungen mit den Werten der CCU, Schalter und Anzeigen für Boolean, Ereignisse (ACTION) ohne besondere Darstellung.
     *
     * @return array Die Darstellung, leer wenn keine passt.
     */
    protected function Presentation(string $Datapoint, string $Type, bool $HasAction, array $Parameter): array
    {
        $Translate = fn (string $Text): string => $this->Translate($Text);
        switch ($Type) {
            case 'ACTION':
                // Eine Aufzählung einer Boolean-Variable braucht beide Werte. Jede Bedienung löst die Aktion aus, gesetzt wird die Variable vom Ereignis des Gerätes.
                return $HasAction ? ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode([
                    ['Value' => false, 'Caption' => $this->Translate('Waiting'), 'IconActive' => false, 'IconValue' => '', 'Color' => -1],
                    ['Value' => true, 'Caption' => $this->Translate('Pressed'), 'IconActive' => false, 'IconValue' => '', 'Color' => -1]
                ])] : [];
            case 'BOOL':
                return \HMExtended\DatapointPresentation::Get($Datapoint, '', VARIABLETYPE_BOOLEAN, $HasAction, $Translate)
                    ?: ($HasAction ? ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH] : []);
            case 'ENUM':
                $Options = [];
                foreach (array_values((array) ($Parameter['VALUE_LIST'] ?? [])) as $Value => $Name) {
                    $Caption = $this->Translate(ucfirst(strtolower(str_replace('_', ' ', (string) $Name))));
                    $Color = static::EnumColors[$Datapoint][$Value] ?? -1;
                    // Die Wertanzeige für Zahlen kennt keine Optionen, die Texte der Werte sind Intervalle mit genau einem Wert
                    $Options[] = $HasAction
                        ? ['Value' => $Value, 'Caption' => $Caption, 'IconActive' => false, 'IconValue' => '', 'Color' => $Color]
                        : ['IntervalMinValue' => $Value, 'IntervalMaxValue' => $Value, 'ConstantActive' => true, 'ConstantValue' => $Caption, 'ColorActive' => $Color != -1, 'ColorValue' => $Color];
                }
                if (!count($Options)) {
                    return [];
                }
                return $HasAction
                    ? ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($Options)]
                    : ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'INTERVALS_ACTIVE' => true, 'INTERVALS' => json_encode($Options)];
            default:
                return \HMExtended\DatapointPresentation::Get($Datapoint, (string) ($Parameter['UNIT'] ?? ''), self::HmTypeToIpsType[$Type], $HasAction, $Translate);
        }
    }

    /**
     * Name der Statusvariable: Beschriftung des Datenpunktes, bei Kanälen mit Kanalnummer.
     */
    protected function VariableName(int $Channel, string $Datapoint): string
    {
        $Caption = array_key_exists($Datapoint, static::Captions)
            ? $this->Translate(static::Captions[$Datapoint])
            : ucfirst(strtolower(str_replace('_', ' ', $Datapoint)));
        return $Channel == 0 ? $Caption : $Caption . ' ' . $Channel;
    }

    //################# private

    /**
     * Legt für alle Datenpunkte aller Kanäle Statusvariablen an. Die Kanäle liefert die CCU (Geräteliste), sonst gilt die Standardanzahl.
     *
     * @return bool true, wenn die CCU die Beschreibung geliefert hat.
     */
    private function CreateVariables(): bool
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Device = $this->SendRPC('getDeviceDescription', $Protocol, [$Address]);
        if ($Device === null) {
            return false;
        }
        $Channels = [];
        foreach ((array) (is_array($Device) ? ($Device['CHILDREN'] ?? []) : []) as $Child) {
            $Channels[] = (int) substr((string) $Child, (int) strrpos((string) $Child, ':') + 1);
        }
        if (!count($Channels)) {
            $Channels = range(0, static::DefaultChannelCount);
        }
        sort($Channels);
        $Datapoints = [];
        $Position = 0;
        foreach ($Channels as $Channel) {
            $Description = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $Address . ':' . $Channel);
            foreach ($Description as $Name => $Parameter) {
                $Datapoint = (string) ($Parameter['ID'] ?? $Name);
                $Type = (string) ($Parameter['TYPE'] ?? '');
                $Operations = (int) ($Parameter['OPERATIONS'] ?? 0);
                $HasAction = ($Operations & 0b10) != 0;
                $Readable = ($Operations & 0b101) != 0;
                if (in_array($Datapoint, static::IgnoredDatapoints) || !array_key_exists($Type, self::HmTypeToIpsType)) {
                    continue;
                }
                // Nicht lesbare Datenpunkte (z.B. ALL_LEDS) sind keine Statusvariablen, Aktionen (Typ ACTION) nur mit Ereignis
                if (!$Readable || (($Type == 'ACTION') && !($Operations & 0b100))) {
                    continue;
                }
                $Ident = $this->IdentOf($Channel, $Datapoint);
                $Datapoints[$Ident] = [$Channel, $Datapoint, $Type, $HasAction];
                $this->MaintainPresentationVariable($Ident, $this->VariableName($Channel, $Datapoint), self::HmTypeToIpsType[$Type], $this->Presentation($Datapoint, $Type, $HasAction, $Parameter), $Position++);
                if ($HasAction) {
                    $this->EnableAction($Ident);
                }
            }
        }
        $this->Datapoints = $Datapoints;
        return true;
    }

    /**
     * Liest die Werte aller Kanäle (Paramset VALUES) und schreibt sie in die Statusvariablen. Fehler werden nur per Debug ausgegeben.
     */
    private function ReadValues(): void
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Channels = array_unique(array_column($this->Datapoints, 0));
        foreach ($Channels as $Channel) {
            $Values = $this->SendRPC('getParamset', $Protocol, [$Address . ':' . $Channel, \HMExtended\CCU::VALUES], null, false, true);
            if (!is_array($Values)) {
                $this->SendDebug('Channel ' . $Channel, 'no values', 0);
                continue;
            }
            foreach ($Values as $Datapoint => $Value) {
                $Ident = $this->IdentOf((int) $Channel, (string) $Datapoint);
                $Known = $this->Datapoints[$Ident] ?? null;
                if (($Known === null) || ($Known[2] == 'ACTION')) {
                    continue;
                }
                $this->SetValue($Ident, $this->Convert($Known[2], $Value));
            }
        }
    }

    /**
     * Wandelt einen Wert der CCU in den Typ der Statusvariable.
     */
    private function Convert(string $Type, mixed $Value): mixed
    {
        return match (self::HmTypeToIpsType[$Type] ?? VARIABLETYPE_STRING) {
            VARIABLETYPE_BOOLEAN => (bool) $Value,
            VARIABLETYPE_INTEGER => (int) $Value,
            VARIABLETYPE_FLOAT   => (float) $Value,
            default              => (string) $Value
        };
    }
}

/* @} */
