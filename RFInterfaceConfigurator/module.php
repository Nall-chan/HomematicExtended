<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.00
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticRFInterfaceConfigurator ist die Klasse für das IPS-Modul 'HomeMatic RF-Interface Konfigurator'.
 * Erweitert IPSModule.
 */
class HomeMaticRFInterfaceConfigurator extends IPSModule
{
    use HMExtended\DebugHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{6EE35B5B-9DD9-4B23-89F6-37589134852F}');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $ParentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ((!$this->HasActiveParent()) || ($ParentId == 0)) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Instance has no active parent instance!'
                    ]]
                ]
            ];
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);

            return json_encode($Form);
        }
        $DevicesIDs = IPS_GetInstanceListByModuleID('{36549B96-FA11-4651-8662-F310EEEC5C7D}');
        $InstanceIDList = [];
        foreach ($DevicesIDs as $DeviceID) {
            if (IPS_GetInstance($DeviceID)['ConnectionID'] == $ParentId) {
                $InstanceIDList[$DeviceID] = IPS_GetProperty($DeviceID, 'Address');
            }
        }
        $Liste = [];
        $Result = $this->GetInterfaces();
        foreach ($Result as $ProtocolID => $Protocol) {
            if (!is_array($Protocol)) {
                continue;
            }
            foreach ($Protocol as $InterfaceIndex => $Interface) {
                switch ($ProtocolID) {
                    case 0:
                        $Type = 'Funk';
                        break;
                    case 2:
                        $Type = 'HmIP';
                        break;
                    default:
                        $Type = 'unknow';
                        break;
                }
                $InstanceID = array_search($Interface['ADDRESS'], $InstanceIDList);
                if ($InstanceID !== false) {
                    $AddValue = [
                        'instanceID' => $InstanceID,
                        'name'       => IPS_GetName($InstanceID),
                        'type'       => $Type,
                        'address'    => $Interface['ADDRESS'],
                        'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
                    ];
                    unset($InstanceIDList[$InstanceID]);
                } else {
                    $AddValue = [
                        'instanceID' => 0,
                        'name'       => $Interface['TYPE'],
                        'type'       => $Type,
                        'address'    => $Interface['ADDRESS'],
                        'location'   => ''
                    ];
                }
                $AddValue['create'] = [
                    'moduleID'      => '{36549B96-FA11-4651-8662-F310EEEC5C7D}',
                    'configuration' => ['Address' => $Interface['ADDRESS']]
                ];
                $Liste[] = $AddValue;
            }
        }
        foreach ($InstanceIDList as $InstanceID => $Address) {
            $AddValue = [
                'instanceID' => $InstanceID,
                'name'       => IPS_GetName($InstanceID),
                'type'       => 'unknow',
                'address'    => $Address,
                'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
            ];
            $Liste[] = $AddValue;
        }
        $Form['actions'][0]['values'] = $Liste;
        $Form['actions'][0][0]['rowCount'] = count($Liste) + 1;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    //################# Datenaustausch
    private function GetInterfaces()
    {
        $Data['DataID'] = '{2F910A05-3607-4070-A6FF-53539E5D3BBB}';
        $this->SendDebug('Request', 'GetInterfaces', 0);
        $ResultString = $this->SendDataToParent(json_encode($Data));
        $this->SendDebug('Response', unserialize($ResultString), 0);
        return unserialize($ResultString);
    }
}

/* @} */
