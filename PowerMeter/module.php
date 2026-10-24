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
 * @version       3.74
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticPowermeter ist die Klasse für das IPS-Modul 'HomeMatic PowerMeter'.
 * Erweitert HMBase.
 *
 * @property int $Event Die IPS-ID der Variable welche als Trigger dient.
 * @property string $HMDeviceAddress Die Geräte-Adresse des Zählers.
 * @property string $HMDeviceDatapoint Der zu überwachende Datenpunkt.
 * @property string $HMProtocol HmIP-RF oder BidCos-RF BidCos-WR
 */
class HomeMaticPowermeter extends HMBase
{
    protected const ValuesChannel = '';

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Migrate
     *
     * @param  string $JSONData
     * @return string
     */
    public function Migrate(string $JSONData): string
    {
        $Data = json_decode($JSONData);
        if (property_exists($Data->configuration, 'EventID')) {
            $VarId = $Data->configuration->EventID;
            if (($VarId != 0) && (IPS_VariableExists($VarId))) {
                $Parent = IPS_GetParent($VarId);
                if (IPS_GetInstance($Parent)['ModuleInfo']['ModuleID'] == \HMExtended\GUID::HmDevice) {
                    $Data->configuration->Address = IPS_GetProperty($Parent, \HMExtended\Device\Property::Address);
                    $Data->configuration->Protocol = IPS_GetProperty($Parent, \HMExtended\Device\Property::Protocol);
                    $this->SendDebug('Migrate', json_encode($Data), 0);
                    $this->LogMessage('Migrated settings:' . json_encode($Data), KL_MESSAGE);
                }
            }
        }
        return json_encode($Data);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        if ($Address) {
            $this->SetReceiveDataFilter( '.*"DeviceID":"' . $Address . static::ValuesChannel . '.*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
        if (($Address) && ($this->HasActiveParent())) {
            @$this->CreateVariablesFromValues();
            @$this->GetValuesAndSetVariable();
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
        if (strpos('ENERGY_COUNTER',$Event['VariableName']) !== false){
             $this->ReadPowerSysVar($Event['VariableName']);
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

    //################# PRIVATE

    private function CreateVariablesFromValues(): void
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamsetDescription(\HMExtended\CCU::VALUES, $AddressWithChannel);
        foreach ($Result as $Variable) {
            $Ident = $Variable['ID'];
            $VarType = \HMExtended\Variables::$HmTypeToIpsType[$Variable['TYPE']];
            $Profile = \HMExtended\Profiles::$UnitToProfile[$Variable['UNIT']];
            $this->MaintainVariable($Ident, $Ident, $VarType, $Profile, 0, true);
            if ($Variable['OPERATIONS'] & 0b10) {
                $this->EnableAction($Ident);
            }
            if (strpos($Ident,'ENERGY_COUNTER') !== false){
                switch ($Ident) {
                    case 'IEC_ENERGY_COUNTER':
                        case 'ENERGY_COUNTER':
                        $Profile = '~Electricity';
                        break;
                }                
                $this->MaintainVariable($Ident.'_TOTAL', $Ident.'_TOTAL', $VarType, $Profile, 0, true);
                $this->ReadPowerSysVar($Ident);
            }
        }
    }


    /**
     * Holt den Wert des Summenzähler per HM-Script aus der CCU.
     *
     * @throws Exception Wenn CCU nicht erreicht wurde.
     */
    private function ReadPowerSysVar(string $Datapoint)
    {
        switch ($Datapoint) {
            case 'GAS_ENERGY_COUNTER':
                $Suffix = 'Gas';
                $Factor = 1;
                break;
            case 'IEC_ENERGY_COUNTER':
                $Suffix = 'IEC';
                $Factor = 1000;
                break;
            case 'ENERGY_COUNTER':
                $Suffix = '';
                $Factor = 1000;
                break;
        }
        $HMScript = 'object oitemID;' . PHP_EOL .
                'oitemID = dom.GetObject("svEnergyCounter' . $Suffix .
                '_" # dom.GetObject("' . \HMExtended\CCU::$Interfaces[$this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol)] .
                '.' . $this->ReadPropertyString(\HMExtended\Device\Property::Address) .
                '.' . $Datapoint .
                '").Channel() # "_' . $this->ReadPropertyString(\HMExtended\Device\Property::Address) .
                '");' . PHP_EOL .
                'Value=oitemID.Value();' . PHP_EOL;

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        if ($xml === false){
            return false;
        }
        $this->SendDebug($Datapoint . '_TOTAL', (string) $xml->Value, 0);
        $Value = ((float) $xml->Value) / $Factor;
        $this->SetVariable($Datapoint . '_TOTAL', $Value);
        return true;
    }
}

/* @} */
