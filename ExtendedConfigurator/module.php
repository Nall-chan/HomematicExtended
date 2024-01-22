<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.74
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/../libs/HMTypes.php';  // HMTypes Data

/**
 * HomeMaticExtendedConfigurator ist die Klasse für das IPS-Modul 'HomeMatic Extended Configurator'.
 * Erweitert HMBase.
 */
class HomeMaticExtendedConfigurator extends HMBase
{
    private $DeviceData = [];
    private $listDevices = [];
    private $DeviceTyp = '';
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999994');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger('Interval', 0);
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
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
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
        $this->DeviceData = $this->LoadDeviceData();
        if (IPS_GetProperty($ParentId, 'RFOpen')) {
            //$Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::Powermeter));
            //$Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::Dis_WM55));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::HeatingDevice));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::ClimacontrolRegulator));
        }
        if (IPS_GetProperty($ParentId, 'GROpen')) {
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(3, \HMExtended\GUID::HeatingGroupHmIP));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(3, \HMExtended\GUID::HeatingGroup));
        } else {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Use of the HomeMatic groups must be activated in the IO.'
                    ]]
                ]
            ];
        }
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
        $this->SendDebug('Devices', $Devices, 0);
        $IPSDevices = $this->GetInstanceList($GUID, \HMExtended\Device\Property::Address);
        foreach ($Devices as &$Device) {
            $Device = array_change_key_case($Device);
            unset($Device['parent']);
            $Device['create'] = $CreateParams;
            $Device['create']['configuration'] = [
                \HMExtended\Device\Property::Address      => $Device['address'],
                \HMExtended\Device\Property::Protocol     => $Protocol,
                \HMExtended\Device\Property::EmulateStatus=> false
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

    private function GetDevices(int $Protocol, array $Types)
    {
        if (!array_key_exists($Protocol, $this->listDevices)) {
            $Devices = $this->SendRPC('listDevices', $Protocol, []);
            if ($Devices === null) {
                return [];
            }
            $this->listDevices[$Protocol] = $Devices;
        } else {
            $Devices = $this->listDevices[$Protocol];
        }
        $Result = [];
        foreach ($Types as $Type) {
            $this->DeviceTyp = $Type;
            $Result = array_merge(
                $Result,
                array_filter($Devices, function ($Device)
                {
                    return $Device['TYPE'] == $this->DeviceTyp;
                })
            );
        }
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
}

/* @} */
