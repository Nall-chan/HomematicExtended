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
 * @version       3.75
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticPowermeter ist die Klasse für das IPS-Modul 'HomeMatic PowerMeter'.
 * Erweitert HMBase.
 *
 * @property string[] $PendingCounters Datenpunkte, deren Summenzähler nach Ablauf des Timers gelesen wird.
 */
class HomeMaticPowermeter extends HMBase
{
    protected const ValuesChannel = '';

    /**
     * Attribut: Gerät mit GAS_VOLUME (HmIP-ESI) arbeitet als Gaszähler, die Systemvariable svEnergyCounter_… zählt dann m³.
     * Bleibt über Neustarts erhalten und wird nur geändert, wenn Werte oder Events die Betriebsart zeigen.
     */
    private const AttributeGasMode = 'GasMode';

    /**
     * Verzögerung in Millisekunden zwischen dem Ereignis eines Zählers und dem Lesen der Systemvariable.
     * Das Ereignis löst auf der CCU zeitgleich das Zählerprogramm aus. Die Zeit ist kurz gehalten, damit der Zeitstempel
     * des Summenzählers im Archiv zur Messung passt (Vorgabe des Nutzers: 50 ms).
     */
    private const ReadDelay = 50;

    /**
     * Datenpunkte, zu denen die CCU einen Summenzähler (Systemvariable `svEnergyCounter<Suffix>_<Kanal-ID>_<Adresse>`) führt.
     * Factor: Umrechnung des Systemvariablen-Wertes auf kWh bzw. m³.
     * Belegt am HM-ES-TX-WM (live, Vergleich mit der CCU): ENERGY_COUNTER Wh -> kWh (Faktor 1000), GAS_ENERGY_COUNTER m³ (Faktor 1).
     * Belegt am HmIP-ESI im Gasmodus (Debug eines Forennutzers): die CCU führt nur svEnergyCounter_… (kein Suffix Gas),
     * der Wert wächst mit GAS_VOLUME in m³ (Faktor 1). Daher nutzen ENERGY_COUNTER und GAS_VOLUME dieselbe Systemvariable,
     * ausgewertet wird je nach Betriebsart (GasMode) nur einer der beiden Datenpunkte.
     * Belegt aus der CCU-Firmware (eq-3/occu, WebUI newdevices.htm): HmIP-PSMCO und ELV-SH-PSMCI führen den Einspeisezähler
     * ENERGY_COUNTER_FEED_IN in svEnergyCounterFeedIn_…, das CCU-Programm summiert die Gerätewerte in Wh (Faktor 1000, nicht live geprüft).
     * Annahme (nicht belegt): IEC_ENERGY_COUNTER liefert die Systemvariable in Wh.
     * Unit: Einheit der Statusvariable nach der Umrechnung (Schlüssel von \HMExtended\DatapointPresentation::Units).
     */
    private const EnergyCounters = [
        'ENERGY_COUNTER'         => ['Suffix' => '', 'Factor' => 1000, 'Unit' => 'kWh'],
        'ENERGY_COUNTER_FEED_IN' => ['Suffix' => 'FeedIn', 'Factor' => 1000, 'Unit' => 'kWh'],
        'IEC_ENERGY_COUNTER'     => ['Suffix' => 'IEC', 'Factor' => 1000, 'Unit' => 'kWh'],
        'GAS_ENERGY_COUNTER'     => ['Suffix' => 'Gas', 'Factor' => 1, 'Unit' => 'm3'],
        'GAS_VOLUME'             => ['Suffix' => '', 'Factor' => 1, 'Unit' => 'm3']
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
        $this->RegisterAttributeBoolean(self::AttributeGasMode, false);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadSystemVariables, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'' . \HMExtended\Device\Timer::ReadSystemVariables . '\', true);');
        $this->PendingCounters = [];
    }

    /**
     * Interne Funktion des SDK.
     * Übernimmt Adresse (mit Kanal) und Protokoll aus der alten Eigenschaft EventID (Variable ENERGY_COUNTER einer Homematic Geräteinstanz).
     *
     * @param string $JSONData
     *
     * @return string
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (!is_object($Data) || !property_exists($Data, 'configuration') || !property_exists($Data->configuration, 'EventID')) {
            return $JSONData;
        }
        $VarId = (int) $Data->configuration->EventID;
        if (($VarId <= 0) || (!IPS_VariableExists($VarId))) {
            return $JSONData;
        }
        $Parent = IPS_GetParent($VarId);
        if ((!IPS_InstanceExists($Parent)) || (IPS_GetInstance($Parent)['ModuleInfo']['ModuleID'] != \HMExtended\GUID::HmDevice)) {
            return $JSONData;
        }
        $Data->configuration->{\HMExtended\Device\Property::Address} = IPS_GetProperty($Parent, \HMExtended\Device\Property::Address);
        $Data->configuration->{\HMExtended\Device\Property::Protocol} = IPS_GetProperty($Parent, \HMExtended\Device\Property::Protocol);
        $this->SendDebug('Migrate', json_encode($Data), 0);
        $this->LogMessage('Migrated settings:' . json_encode($Data->configuration), KL_MESSAGE);
        return json_encode($Data);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Ohne ausdrücklichen Status bliebe die Instanz nach einem Fehler beim Erstellen (z.B. beim Neuladen) in 101 (wird erstellt).
        $this->SetStatus(IS_ACTIVE);
        $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, 0);
        $this->PendingCounters = [];
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($Address) {
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $Address . static::ValuesChannel . '.*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        if (($Address) && ($this->HasActiveParent())) {
            // Ohne Beschreibung ist die CCU oder das Gerät nicht erreichbar: Meldung, danach folgt keine weitere Anfrage.
            $Description = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $Address . static::ValuesChannel);
            if (count($Description) == 0) {
                return;
            }
            // Die Werte vor den Summenzählern lesen: sie bestimmen die Betriebsart. Ohne Werte bleibt die zuletzt erkannte Betriebsart.
            $Values = $this->ReadValues($Description);
            if (count($Values)) {
                $this->WriteAttributeBoolean(self::AttributeGasMode, array_key_exists('GAS_VOLUME', $Values) && !array_key_exists('ENERGY_COUNTER', $Values));
            }
            $this->CreateVariablesFromValues($Description);
            foreach ($Values as $Ident => $Value) {
                $this->SetVariable($Ident, $Value);
            }
        }
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData(string $JSONString): string
    {
        parent::ReceiveData($JSONString);
        $Event = json_decode($JSONString, true);
        if (!isset($Event['VariableName'])) {
            return '';
        }
        // Ein Gerät mit GAS_VOLUME meldet im Gasmodus nur GAS_VOLUME, sonst ENERGY_COUNTER.
        if ($Event['VariableName'] == 'GAS_VOLUME') {
            $this->WriteAttributeBoolean(self::AttributeGasMode, true);
        } elseif ($Event['VariableName'] == 'ENERGY_COUNTER') {
            $this->WriteAttributeBoolean(self::AttributeGasMode, false);
        }
        if (array_key_exists($Event['VariableName'], self::EnergyCounters)) {
            // Das Ereignis löst auf der CCU zeitgleich das Zählerprogramm aus. Die Systemvariable wird daher erst nach Ablauf des Timers gelesen,
            // mehrere Ereignisse kurz hintereinander lösen nur ein Lesen aus.
            // Der Zwischenspeicher ist noch nicht initialisiert, wenn ein Ereignis vor dem Ende von ApplyChanges eintrifft (z.B. nach einem Update).
            $Pending = $this->PendingCounters ?: [];
            if (!in_array($Event['VariableName'], $Pending)) {
                $Pending[] = $Event['VariableName'];
                $this->PendingCounters = $Pending;
            }
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, self::ReadDelay);
        }
        return '';
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
     * Verarbeitet den Timer zum Lesen der Summenzähler.
     *
     * @param string $Ident Ident der Variable bzw. Aktion.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion vollständig verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        parent::ProcessRequestAction($Ident, $Value, $done);
        if ($done) {
            return;
        }
        if ($Ident == \HMExtended\Device\Timer::ReadSystemVariables) {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, 0);
            $Pending = $this->PendingCounters ?: [];
            $this->PendingCounters = [];
            foreach ($Pending as $Datapoint) {
                $this->ReadPowerSysVar($Datapoint);
            }
            $done = true;
        }
    }

    //################# PRIVATE

    /**
     * Liest die Werte des Kanals.
     * Die CCU beantwortet getParamset mit einem Fehler, solange das Gerät einen Datenpunkt noch nie gesendet hat
     * (z.B. HM-ES-TX-WM: im Strommodus die Gas- und IEC-Werte, live geprüft). Dann wird jeder lesbare Datenpunkt einzeln gelesen,
     * fehlende Werte erzeugen nur eine Debug-Ausgabe.
     *
     * @param array $Description Beschreibung der Datenpunkte laut getParamsetDescription.
     *
     * @return array Die Werte je Datenpunkt.
     */
    private function ReadValues(array $Description): array
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        $Values = $this->SendRPC('getParamset', $Protocol, [$AddressWithChannel, \HMExtended\CCU::VALUES], null, false, true);
        if (is_array($Values)) {
            return $Values;
        }
        $Values = [];
        foreach ($Description as $Variable) {
            $Ident = $Variable['ID'] ?? '';
            if (($Ident === '') || !(($Variable['OPERATIONS'] ?? 0) & 0b1)) {
                continue;
            }
            $Value = $this->SendRPC('getValue', $Protocol, [$AddressWithChannel, $Ident], null, false, true);
            if (($Value === null) || ($Value === true)) {
                $this->SendDebug('No value', $Ident, 0);
                continue;
            }
            $Values[$Ident] = $Value;
        }
        return $Values;
    }

    /**
     * Legt für alle Datenpunkte des Kanals Statusvariablen an.
     *
     * @param array $Description Beschreibung der Datenpunkte laut getParamsetDescription.
     */
    private function CreateVariablesFromValues(array $Description): void
    {
        foreach ($Description as $Variable) {
            $Ident = $Variable['ID'];
            $HmType = $Variable['TYPE'] ?? '';
            if (!array_key_exists($HmType, \HMExtended\Variables::$HmTypeToIpsType)) {
                $this->SendDebug('Unsupported type', $Ident . ': ' . $HmType, 0);
                continue;
            }
            $VarType = \HMExtended\Variables::$HmTypeToIpsType[$HmType];
            $HasAction = (bool) (($Variable['OPERATIONS'] ?? 0) & 0b10);
            $this->MaintainPresentationVariable($Ident, $Ident, $VarType, \HMExtended\DatapointPresentation::Get($Ident, $Variable['UNIT'] ?? '', $VarType, $HasAction, fn (string $Text): string => $this->Translate($Text)));
            if ($HasAction) {
                $this->EnableAction($Ident);
            }
            if (array_key_exists($Ident, self::EnergyCounters)) {
                // Eine vorhandene Variable des Summenzählers erhält die Darstellung auch dann, wenn die Systemvariable (nicht mehr) existiert.
                if ($this->FindIDForIdent($Ident . '_TOTAL') > 0) {
                    $this->MaintainTotalVariable($Ident);
                }
                // Die Variable des Summenzählers wird erst angelegt, wenn die CCU einen Wert ungleich 0 liefert.
                $this->ReadPowerSysVar($Ident);
            }
        }
    }

    /**
     * Holt den Wert des Summenzählers per HM-Script aus der CCU.
     * Die Statusvariable `<Datenpunkt>_TOTAL` wird erst angelegt, wenn die Systemvariable existiert und einen Wert ungleich 0 hat.
     * Bereits vorhandene Variablen bleiben unverändert erhalten und werden weiter aktualisiert.
     *
     * @param string $Datapoint Datenpunkt des Zählers, ein Schlüssel von EnergyCounters.
     *
     * @return bool true, wenn ein Wert ungleich 0 gelesen und gesetzt wurde.
     */
    private function ReadPowerSysVar(string $Datapoint): bool
    {
        if (!array_key_exists($Datapoint, self::EnergyCounters)) {
            $this->SendDebug('Unknown counter', $Datapoint, 0);
            return false;
        }
        // ENERGY_COUNTER und GAS_VOLUME teilen sich die Systemvariable, je nach Betriebsart gilt nur einer.
        $GasMode = $this->ReadAttributeBoolean(self::AttributeGasMode);
        if ((($Datapoint == 'ENERGY_COUNTER') && $GasMode) || (($Datapoint == 'GAS_VOLUME') && !$GasMode)) {
            $this->SendDebug($Datapoint . '_TOTAL', 'Skipped, GasMode: ' . ($GasMode ? 'true' : 'false'), 0);
            return false;
        }
        $Counter = self::EnergyCounters[$Datapoint];
        // Die Abfrage von Value() nur, wenn die Systemvariable existiert (sonst Skriptfehler auf der CCU).
        $HMScript = 'object oitemID;' . PHP_EOL .
                'oitemID = dom.GetObject("svEnergyCounter' . $Counter['Suffix'] .
                '_" # dom.GetObject("' . \HMExtended\CCU::$Interfaces[$this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol)] .
                '.' . $this->ReadPropertyString(\HMExtended\Device\Property::Address) .
                '.' . $Datapoint .
                '").Channel() # "_' . $this->ReadPropertyString(\HMExtended\Device\Property::Address) .
                '");' . PHP_EOL .
                'if (oitemID) { Value=oitemID.Value(); }' . PHP_EOL;

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        if ($xml === false) {
            return false;
        }
        $Raw = (string) $xml->Value;
        if ($Raw === '') {
            $this->SendDebug($Datapoint . '_TOTAL', 'System variable not found', 0);
            return false;
        }
        $this->SendDebug($Datapoint . '_TOTAL', $Raw, 0);
        $Value = ((float) $Raw) / $Counter['Factor'];
        // Neu angelegt wird erst bei einem Wert ungleich 0, eine vorhandene Variable folgt auch einem Zurücksetzen auf 0.
        if (($Value == 0.0) && ($this->FindIDForIdent($Datapoint . '_TOTAL') == 0)) {
            return false;
        }
        $this->MaintainTotalVariable($Datapoint);
        $this->SetVariable($Datapoint . '_TOTAL', $Value);
        return true;
    }

    /**
     * Legt die Variable des Summenzählers an bzw. aktualisiert deren Darstellung.
     *
     * @param string $Datapoint Datenpunkt des Zählers, ein Schlüssel von EnergyCounters.
     */
    private function MaintainTotalVariable(string $Datapoint): void
    {
        $Ident = $Datapoint . '_TOTAL';
        $this->MaintainPresentationVariable($Ident, $Ident, VARIABLETYPE_FLOAT, \HMExtended\DatapointPresentation::Get($Ident, self::EnergyCounters[$Datapoint]['Unit'], VARIABLETYPE_FLOAT, false, fn (string $Text): string => $this->Translate($Text)));
    }
}

/* @} */
