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
 * HomeMaticRFInterface ist die Klasse für das IPS-Modul 'HomeMatic RF-Interface'.
 * Erweitert IPSModuleStrict.
 */
class HomeMaticRFInterface extends IPSModuleStrict
{
    use HMExtended\DebugHelper;
    use HMExtended\VariableHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
    }

    /**
     * Interne Funktion des SDK.
     * Die Instanz wird mit dem RF-Interface Splitter verbunden.
     */
    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type'      => 'connect',
            'moduleIDs' => [\HMExtended\GUID::RF_Interface_Splitter]
        ]);
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
            // Eigene Adresse und die Nachricht des Splitters an alle Interfaces
            $this->SetReceiveDataFilter('.*"ADDRESS":"(' . preg_quote($Address, '/') . '|' . preg_quote(\HMExtended\Device\Address::AllInterfaces, '/') . ')".*');
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
        if (!is_object($Data)) {
            return '';
        }
        unset($Data->DataID);
        if (($Data->ADDRESS ?? '') === \HMExtended\Device\Address::AllInterfaces) {
            $this->CheckInterfacePresent((array) ($Data->PRESENT ?? []));
            return '';
        }
        unset($Data->ADDRESS);
        $this->SendDebug('Receive', $Data, 0);
        foreach ($Data as $Ident => $Value) {
            if ($Value === '') {
                continue;
            }

            switch ($Ident) {
                case 'DUTY_CYCLE':
                    $Profile = '~Intensity.100';
                    break;
                case 'CONNECTED':
                    $Profile = '~Alert.Reversed';
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

    /**
     * Setzt die Variable CONNECTED auf false, wenn das eigene Interface nicht in der Antwort der CCU enthalten war.
     * Andere Werte bleiben unverändert, da ein fehlendes Interface keine neuen Werte liefert.
     *
     * @param array $Present Adressen der Interfaces, welche die CCU gemeldet hat.
     */
    private function CheckInterfacePresent(array $Present): void
    {
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $this->SendDebug('Present', $Present, 0);
        if (($Address === '') || in_array($Address, $Present, true)) {
            return;
        }
        $this->SendDebug('Interface missing', $Address, 0);
        if ($this->FindIDForIdent('CONNECTED') > 0) {
            $this->SetValue('CONNECTED', false);
        }
    }
}

/* @} */
