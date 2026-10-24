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
 * HomeMaticRFInterface ist die Klasse für das IPS-Modul 'HomeMatic RF-Interface'.
 * Erweitert IPSModuleStrict.
 */
class HomeMaticRFInterface extends IPSModuleStrict
{
    use HMExtended\DebugHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->ConnectParent(\HMExtended\GUID::RF_Interface_Splitter);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $this->SetSummary($Address);

        if ($Address !== '') {
            $this->SetReceiveDataFilter('.*"ADDRESS":"' . $Address . '".*');
        } else {
            $this->SetReceiveDataFilter('.*9999999999.*');
        }
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString);
        unset($Data->DataID);
        unset($Data->ADDRESS);
        $this->SendDebug('Receive', $Data, 0);
        foreach ($Data as $Ident => $Value) {
            if ($Value === '') {
                continue;
            }
            
            switch ($Ident){
                case 'DUTY_CYCLE':
                    $Profile = '~Intensity.100';
                    break;
                case 'CONNECTED':
                    $Profile='~Alert.Reversed';
                    break;
                default:
                    $Profile = '';
                    break;
            }
            switch (gettype($Value)) {
                case 'boolean':
                    $Typ = VARIABLETYPE_BOOLEAN;
                    break;
                case 'integer':
                    $Typ = VARIABLETYPE_INTEGER;
                    break;
                case 'double':
                case 'float':
                    $Typ = VARIABLETYPE_FLOAT;
                    break;
                case 'string':
                    $Typ = VARIABLETYPE_STRING;
                    break;
                default:
                    continue 2;
            }
            $this->MaintainVariable($Ident, $Ident, $Typ, $Profile, 0, true);
            $this->SetValue($Ident, $Value);
        }
        return '';
    }
}

/* @} */
