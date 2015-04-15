<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMPowerMeter extends HMBase
{

    public function __construct($InstanceID)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        //Never delete this line!
        parent::__construct($InstanceID);

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger('EventID', 0);
        $this->RegisterVariabeFloat('ENERGY_COUNTER_TOTAL', 'ENERGY_COUNTER_TOTAL', '~Electricity');
    }

    public function ApplyChanges()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function ReceiveData($JSONString)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //    
        if (!$this->CheckConfig())
        {
            return;
        }
        $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
        $HMDeviceAddress = IPS_GetProperty($parent, 'Address');
        $Data = json_decode($JSONString);
        if ($HMDeviceAddress <> (string) $Data->DeviceID)
            return;
        if ((string) $Data->VariableName <> 'ENERGY_COUNTER')
            return;
        $this->ReadPowerSysVar();
    }

################## PRIVATE                

    private function CheckConfig()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        if ($this->ReadPropertyInteger('EventID') == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            return false;
        }
        else
        {
            // Prüfe Ob HM-Device
            $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
            if ((IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] == '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                    and ( IPS_GetObject($this->ReadPropertyInteger('EventID'))['ObjectIdent'] == 'ENERGY_COUNTER'))
            {
                $this->SetStatus(IS_ACTIVE); //OK
                return true;
            }
            else
            {
                $this->SetStatus(202);
                return false;
            }
        }
    }

    private function ReadPowerSysVar()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        if (!$this->HasActiveParent())
        {
            throw new Exception('Instance has no active Parent Instance!');
        }
        $PowerMeterAddress = $this->GetParentData();
        if ($PowerMeterAddress === false)
        {
            throw new Exception('Error on read PowerMeterAddress');
        }
        $url = 'GetPower.exe';
        $HMScript = 'Value=dom.GetObject(' . $PowerMeterAddress . ').Value();' . PHP_EOL;
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult == '')
            throw new Exception('Error on read PowerMeterData');
        $xml = @new SimpleXMLElement($HMScriptResult);
        if (($xml === false) or ( !isset($xml->Value)))
        {
            $this->LogMessage(KL_WARNING, 'HM-Script result is not wellformed');
            throw new Exception('Error on read PowerMeterData');
        }
        $VarID = @IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->InstanceID);
        if ($VarID === false)
            return;
        SetValueFloat($VarID, ((float) $xml->Value) / 1000);
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
###################### protected

    protected function GetParentData()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        parent::GetParentData();
        if ($this->HMAddress == '')
            return false;

        $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
        $HMDeviceAddress = IPS_GetProperty($parent, 'Address');
        $this->SetSummary($HMDeviceAddress);
        $url = 'GetMeter.exe';
//          $HMScript='Meter=dom.GetObject("BidCos-RF.'.$HMDeviceAddress .'.ENERGY_COUNTER").Device();';
        $HMScript = 'object oitemID;' . PHP_EOL
                . 'oitemID = dom.GetObject("svEnergyCounter_" # dom.GetObject("BidCos-RF.' . $HMDeviceAddress . '.ENERGY_COUNTER").Device() # "_' . $HMDeviceAddress . '");' . PHP_EOL
                . 'SysVar=oitemID.ID();' . PHP_EOL;
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult == '')
            return false;
        try
        {
            $xml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage('HM-Script result is not wellformed');
            return false;
        }
        return (string) $xml->SysVar;
    }

}

?>