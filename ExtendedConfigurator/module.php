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
 * @version       3.12
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/../libs/HMTypes.php';  // HMTypes Data
/**
 * HomeMaticExtendedConfigurator ist die Klasse für das IPS-Modul 'HomeMatic Extended Configurator'.
 * Erweitert HMBase.
 *
 * @property array $DeviceNames
 */
class HomeMaticExtendedConfigurator extends HMBase
{
    private $DeviceData = [];
    private $DeviceTyp = '';
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999994');
        $this->RegisterPropertyBoolean('EmulateStatus', false);
        $this->RegisterPropertyInteger('Interval', 0);
        //$this->DeviceNames = [];
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }
    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadRFInterfaces'.
     * Liest die Daten der RF-Interfaces und versendet sie an die Children.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function Test(string $MethodName, int $Protocol, array $Data)
    {
        return $this->SendRPC($MethodName, $Protocol, $Data);
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
        /*
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
                                $Type = 'unknown';
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
                        'type'       => 'unknown',
                        'address'    => $Address,
                        'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true)
                    ];
                    $Liste[] = $AddValue;
                }
         */
        $this->DeviceData = $this->LoadDeviceData();
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::ClimacontrolRegulator));
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(3, \HMExtended\GUID::HeatingGroupHmIP));
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(3, \HMExtended\GUID::HeatingGroup));
        $Form['actions'][0][0]['rowCount'] = count($Form['actions'][0]['values']) + 1;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    //################# protected
    protected function GetInstanceList(string $GUID, string $ConfigParam = null)
    {
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstances']);
        if ($ConfigParam != null) {
            $InstanceIDList = array_flip(array_values($InstanceIDList));
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        return $InstanceIDList;
    }

    protected function FilterInstances(int $InstanceID)
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    protected function GetConfigParam(&$item1, $InstanceID, $ConfigParam)
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }
    private function GetConfigRows(int $Protocol, string $GUID)
    {
        $CreateParams = [
            'moduleID'      => $GUID,
            'configuration' => [],
            'location'      => []
        ];

        $Devices = $this->GetDevices($Protocol, \HMExtended\DeviceType::$GuidToType[$GUID]);
        $IPSDevices = $this->GetInstanceList($GUID, 'Address');
        foreach ($Devices as &$Device) {
            $Device = array_change_key_case($Device);
            unset($Device['parent']);
            $Device['create'] = $CreateParams;
            $Device['create']['configuration'] = [
                'Address'      => $Device['address'],
                'Protocol'     => $Protocol,
                'EmulateStatus'=> false
            ];

            $InstanceID = array_search($Device['address'], $IPSDevices);

            if ($InstanceID !== false) {
                unset($IPSDevices[$InstanceID]);
                $Device['instanceID'] = $InstanceID;
                $Device['name'] = IPS_GetName($InstanceID);
                $Device['longname'] = IPS_GetName($InstanceID);
            } else {
                $Device['instanceID'] = 0;
                $this->GetDeviceData($Protocol, $Device);
            }
        }
        foreach ($IPSDevices as $InstanceID => $Address) {
            $Devices[] = [
                'instanceID'      => $InstanceID,
                'address'         => $Address,
                'name'            => IPS_GetName($InstanceID),
                'longname'        => IPS_GetName($InstanceID),
                'type'            => ''
            ];
        }
        return $Devices;
    }
    private function GetDeviceData(int $Protocol, array &$Device)
    {
        $InterfaceString = \HMExtended\CCU::$Interfaces[$Protocol];
        if (isset($this->DeviceData[$InterfaceString][$Device['address']])) {
            $DeviceData = $this->DeviceData[$InterfaceString][$Device['address']];
            $Device['name'] = $DeviceData['Name'];
            if ($DeviceData['Room'] != '') {
                $Device['create']['location'] = [$DeviceData['Room']];
                $Device['longname'] = $DeviceData['Name'] .
        '(' . $DeviceData['Room'] . ')';
            } else {
                $Device['longname'] = $DeviceData['Name'];
            }
        } else {
            $Device['longname'] = $Device['type'];
            $Device['name'] = $Device['type'];
        }
    }
    private function GetDevices(int $Protocol, string $Type)
    {
        $Result = $this->SendRPC('listDevices', $Protocol, []);
        if ($Result === false) {
            return [];
        }
        $this->DeviceTyp = $Type;
        $Result = array_filter($Result, function ($Device)
        {
            return $Device['TYPE'] == $this->DeviceTyp;
        });
        return array_values($Result);
    }

    private function LoadDeviceData()
    {
        $Values = [];
        $Script = 'string did; string cid; string rid;
		foreach (did, dom.GetObject(ID_DEVICES).EnumUsedIDs())
		{
		  object o = dom.GetObject(did);
          string i = dom.GetObject(o.Interface());
          WriteLine(i # "\t" # o.Address() # "\t" # o.Name());
		  foreach (cid, o.Channels())
		  {
			object c = dom.GetObject(cid);
			Write(i # "\t" # c.Address() # "\t" # c.Name());
			foreach (rid, c.ChnRoom())
			{
			  Write("\t" # dom.GetObject(rid).Name());
			}
			WriteLine("");
		  }
		}
		did = 0; cid = 0; rid = 0; o = 0; c = 0; i = 0;';
        $ret = $this->LoadHMScript($Script);
        if ($ret === false) {
            return $Values;
        }
        $ret = explode('<xml>', $ret);
        $ret = explode("\r\n", array_shift($ret));
        array_pop($ret);

        foreach ($ret as $line) {
            $data = explode("\t", $line);
            $Values[$data[0]][$data[1]] = [
                'Name' => $data[2],
                'Room' => (isset($data[3]) ? $data[3] : '')
            ];
        }
        return $Values;
    }
    private function SendRPC(string $MethodName, int $Protocol, array $Data)
    {
        if (!$this->HasActiveParent()) {
            trigger_error('Instance has no active Parent Instance!', E_USER_NOTICE);
            return false;
        }
        $ParentData = [
            'DataID'     => '{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}',
            'Protocol'   => $Protocol,
            'MethodName' => $MethodName,
            'WaitTime'   => 5000,
            'Data'       => $Data
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $ResultJSON = $this->SendDataToParent(json_encode($ParentData, JSON_PRESERVE_ZERO_FRACTION));
        if ($ResultJSON === false) {
            trigger_error('Error on ' . $MethodName, E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
            return false;
        }
        $Result = json_decode(utf8_encode($ResultJSON), true);
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }
}

/* @} */
