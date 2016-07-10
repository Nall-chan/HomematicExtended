<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMPowerMeter extends HMBase
{

    private $HMDeviceAddress;

    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999997');

        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterVariableFloat("ENERGY_COUNTER_TOTAL", "ENERGY_COUNTER_TOTAL", "~Electricity");
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message)
        {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                if ($SenderID == $this->ReadPropertyInteger("EventID"))
                {
                    IPS_SetProperty($this->InstanceID, "EventID", 0);
                    IPS_ApplyChanges($this->InstanceID);
                }
                break;
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (($this->CheckConfig()) and ( $this->GetPowerAddress()))
        {
            $this->SetSummary($this->HMDeviceAddress);
            $this->SetReceiveDataFilter(".*" . $this->HMDeviceAddress . ".*ENERGY_COUNTER.*");
            if ($this->fKernelRunlevel == KR_READY)
                if ($this->HasActiveParent())
                {
                    $this->GetParentData();
                    if ($this->HMAddress <> '')
                        try
                        {
                            $this->ReadPowerSysVar();
                        }
                        catch (Exception $exc)
                        {
                            trigger_error($exc->getMessage(), $exc->getCode());
                            return false;
                        }
                }
            return true;
        }
        $this->SetReceiveDataFilter(".*9999999999.*");
        $this->SetSummary('');
    }

    protected function KernelReady()
    {
        if (!$this->GetPowerAddress())
            return;
        if ($this->HMAddress <> '')
            $this->ReadPowerSysVar();
    }

    protected function ForceRefresh()
    {
        if (!$this->GetPowerAddress())
            return;
        if ($this->HMAddress <> '')
            $this->ReadPowerSysVar();
    }

    public function ReceiveData($JSONString)
    {
        if (!$this->GetPowerAddress())
            return;
        $Data = json_decode($JSONString);
/*        if ($this->HMDeviceAddress <> (string) $Data->DeviceID)
            return;
        if ((string) $Data->VariableName <> 'ENERGY_COUNTER')
            return;*/
        try
        {
            $this->ReadPowerSysVar();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
        $OldEvent = $this->GetBuffer("Event");
        $Event = $this->ReadPropertyInteger("EventID");

        if ($this->ReadPropertyInteger("EventID") == 0)
        {
            if ($OldEvent > 0)
                $this->UnregisterMessage($OldEvent, VM_DELETE);
            $this->SetStatus(IS_INACTIVE);
            return false;
        }
        else
        {
            $parent = IPS_GetParent($this->ReadPropertyInteger("EventID"));
            if ((IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] == '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                    and ( IPS_GetObject($this->ReadPropertyInteger("EventID"))['ObjectIdent'] == 'ENERGY_COUNTER'))
            {
                $this->RegisterMessage($Event, VM_DELETE);
                $this->SetBuffer('Event', $Event);
                $this->SetStatus(IS_ACTIVE);
                return true;
            }
            else
            {
                if ($OldEvent > 0)
                    $this->UnregisterMessage($OldEvent, VM_DELETE);

                $this->SetStatus(IS_EBASE + 2);
                return false;
            }
        }
    }

    private function GetPowerAddress()
    {
        $EventID = $this->ReadPropertyInteger("EventID");
        if ($EventID == 0)
            return false;
        $parent = IPS_GetParent($EventID);
        $this->HMDeviceAddress = IPS_GetProperty($parent, 'Address');
        return true;
    }

    private function ReadPowerSysVar()
    {
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_NOTICE);
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_NOTICE);
        }

        $url = 'GetPowerMeter.exe';
        $HMScript = 'object oitemID;' . PHP_EOL
                . 'oitemID = dom.GetObject("svEnergyCounter_" # dom.GetObject("BidCos-RF.' . $this->HMDeviceAddress . '.ENERGY_COUNTER").Device() # "_' . $this->HMDeviceAddress . '");' . PHP_EOL
                . 'Value=oitemID.Value();' . PHP_EOL;
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('GetPowerMeter', $exc->getMessage(), 0);
            throw new Exception('Error on read PowerMeterData', E_USER_NOTICE);
        }
            $xml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        if ($xml === false)            
        {
            $this->SendDebug('GetPowerMeter', 'XML error', 0);
            throw new Exception('Error on read PowerMeterAddress', E_USER_NOTICE);
        }

        $Value = ((float) $xml->Value) / 1000;
        $VarID = @IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->InstanceID);
        if ($VarID === false)
            return;
        SetValueFloat($VarID, $Value);
    }

################## PUBLIC
}

?>