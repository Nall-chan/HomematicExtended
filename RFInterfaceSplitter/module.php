<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMRFInterfaceSplitter extends HMBase
{

    use DebugHelper;

    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999994');
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterTimer("ReadRFInterfaces", 0, 'HM_ReadRFInterfaces($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter(".*9999999999.*");
        if (IPS_GetKernelRunlevel() <> KR_READY)
            return;
        $this->GetParentData();

        if ($this->HMAddress == '')
            return;

        if ($this->CheckConfig())
        {
            if ($this->ReadPropertyInteger("Interval") >= 5)
            {
                $this->SetTimerInterval("ReadRFInterfaces", $this->ReadPropertyInteger("Interval") * 1000);
            }
            else
            {
                $this->SetTimerInterval("ReadRFInterfaces", 0);
            }
        }
        else
        {
            $this->SetTimerInterval("ReadRFInterfaces", 0);
        }


        if ($this->HasActiveParent())
        {
            try
            {
                $this->ReadRFInterfaces();
            }
            catch (Exception $exc)
            {
                trigger_error($exc->getMessage(), $exc->getCode());
            }
        }
    }

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    protected function GetParentData()
    {
        $ParentId = parent::GetParentData();
        $this->SetSummary($this->HMAddress);
        return $ParentId;
    }

    private function CheckConfig()
    {
        $Interval = $this->ReadPropertyInteger("Interval");
        if ($Interval < 0)
        {
            $this->SetStatus(IS_EBASE + 2);
            return false;
        }
        if ($Interval == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            return true;
        }
        if ($Interval < 5)
        {
            $this->SetStatus(IS_EBASE + 3);
            return false;
        }
        $this->SetStatus(IS_ACTIVE);
        return true;
    }

################## PUBLIC

    public function CreateAllInstances()
    {

        $DevicesIDs = IPS_GetInstanceListByModuleID("{36549B96-FA11-4651-8662-F310EEEC5C7D}");
        $CreatedDevices = array();

        foreach ($DevicesIDs as $Device)
        {
            $KnownDevices[] = IPS_GetProperty($Device, 'Address');
        }

        $Result = $this->GetInterfaces();
        foreach ($Result as $Protocol)
        {
            foreach ($Protocol as $Interface)
            {
                if (in_array($Interface->ADDRESS, $KnownDevices))
                    continue;
                $NewDevice = IPS_CreateInstance("{36549B96-FA11-4651-8662-F310EEEC5C7D}");
                IPS_SetName($NewDevice, $Interface->TYPE);
                if (IPS_GetInstance($NewDevice)['ConnectionID'] <> $this->InstanceID)
                {
                    @IPS_DisconnectInstance($NewDevice);
                    IPS_ConnectInstance($NewDevice, $this->InstanceID);
                }
                IPS_SetProperty($NewDevice, 'Address', $Interface->ADDRESS);
                IPS_ApplyChanges($NewDevice);
                $CreatedDevices[] = $NewDevice;
            }
        }
        if (count($CreatedDevices) > 0)
            $this->ReadRFInterfaces();

        return $CreatedDevices;
    }

    public function ReadRFInterfaces()
    {
        $Result = $this->GetInterfaces();
        $ret = false;
        foreach ($Result as $ProtocolID => $Protocol)
        {
            foreach ($Protocol as $InterfaceIndex => $Interface)
            {
                $this->SendDebug("Proto" . $ProtocolID . " If" . $InterfaceIndex, $Interface, 0);
                $Interface->DataID = "E2966A08-BCE1-4E76-8C4B-7E0136244E1B";
                $Data = json_encode($Interface);
                $this->SendDataToChildren($Data);
                $ret = true;
            }
        }
        return $ret;
    }

    private function GetInterfaces()
    {
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return array();
        }
        $ParentId = $this->GetParentData();
        $Protocol = array();
        if (IPS_GetProperty($ParentId, "RFOpen") === true)
            $Protocol[] = 0;
        if (IPS_GetProperty($ParentId, "IPOpen") === true)
            $Protocol[] = 2;

        $data = array();
        $ParentData = Array
            (
            "DataID" => "{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}",
            "Protocol" => 0,
            "MethodName" => "listBidcosInterfaces",
            "WaitTime" => 5000,
            "Data" => $data
        );
        $ret = array();
        foreach ($Protocol as $ProtocolId)
        {
            $ParentData["Protocol"] = $ProtocolId;
            $JSON = json_encode($ParentData);
            $ResultJSON = @$this->SendDataToParent($JSON);
            $Result = @json_decode($ResultJSON);
            if ($Result === false)
                trigger_error('Error on Read Interfaces:' . $ProtocolId, E_USER_NOTICE);
            else
                $ret[$ProtocolId] = $Result;
        }
        return $ret;
    }

}

?>