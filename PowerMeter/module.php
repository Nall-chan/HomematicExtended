<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015 Michael Tröger
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
     */
    private const EnergyCounters = [
        'ENERGY_COUNTER'         => ['Suffix' => '', 'Factor' => 1000, 'Profile' => '~Electricity'],
        'ENERGY_COUNTER_FEED_IN' => ['Suffix' => 'FeedIn', 'Factor' => 1000, 'Profile' => '~Electricity'],
        'IEC_ENERGY_COUNTER'     => ['Suffix' => 'IEC', 'Factor' => 1000, 'Profile' => '~Electricity'],
        'GAS_ENERGY_COUNTER'     => ['Suffix' => 'Gas', 'Factor' => 1, 'Profile' => '~Gas'],
        'GAS_VOLUME'             => ['Suffix' => '', 'Factor' => 1, 'Profile' => '~Gas']
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
        $this->SetTimerInterval(\HMExtended\Device\Timer::ReadSystemVariables, 0);
        $this->PendingCounters = [];
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($Address) {
            $this->SetReceiveDataFilter('.*"DeviceID":"' . $Address . static::ValuesChannel . '.*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        if (($Address) && ($this->HasActiveParent())) {
            // Die Werte zuerst lesen: sie bestimmen die Betriebsart, bevor die Summenzähler gelesen werden.
            $Values = @$this->GetParamset(\HMExtended\CCU::VALUES, $Address . static::ValuesChannel);
            // Ohne Werte (z.B. CCU nicht erreichbar) bleibt die zuletzt erkannte Betriebsart erhalten.
            if (count($Values) > 0) {
                $this->WriteAttributeBoolean(self::AttributeGasMode, array_key_exists('GAS_VOLUME', $Values) && !array_key_exists('ENERGY_COUNTER', $Values));
            }
            @$this->CreateVariablesFromValues();
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
            $Pending = $this->PendingCounters;
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
            $Pending = $this->PendingCounters;
            $this->PendingCounters = [];
            foreach ($Pending as $Datapoint) {
                $this->ReadPowerSysVar($Datapoint);
            }
            $done = true;
        }
    }

    //################# PRIVATE

    private function CreateVariablesFromValues(): void
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $AddressWithChannel);
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            if (!array_key_exists($Variable['TYPE'], \HMExtended\Variables::$HmTypeToIpsType)) {
                $this->SendDebug('Unsupported type', $Ident . ': ' . $Variable['TYPE'], 0);
                continue;
            }
            $VarType = \HMExtended\Variables::$HmTypeToIpsType[$Variable['TYPE']];
            $Profile = \HMExtended\Profiles::$UnitToProfile[$Variable['UNIT']] ?? '';
            $this->MaintainVariable($Ident, $Ident, $VarType, $Profile, 0, true);
            if ($Variable['OPERATIONS'] & 0b10) {
                $this->EnableAction($Ident);
            }
            if (array_key_exists($Ident, self::EnergyCounters)) {
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
        $this->MaintainVariable($Datapoint . '_TOTAL', $Datapoint . '_TOTAL', VARIABLETYPE_FLOAT, $Counter['Profile'], 0, true);
        $this->SetVariable($Datapoint . '_TOTAL', $Value);
        return true;
    }
}

/* @} */
