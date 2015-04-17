<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMPowerMeter extends HMBase
{

    private $HMDeviceAddress;

    public function __construct($InstanceID)
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        //Never delete this line!
        parent::__construct($InstanceID);
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterVariabeFloat("ENERGY_COUNTER_TOTAL", "ENERGY_COUNTER_TOTAL", "~Electricity");
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
    }

    public function ApplyChanges()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        //Never delete this line!
        parent::ApplyChanges();

//        $this->ReadPropertyInteger("EventID");
//        IPS_Sleep(500);
        if ($this->CheckConfig())
        {
            if ($this->GetPowerSysVarAddress())
            {
                $this->SetSummary($this->HMDeviceAddress);
                if ($this->HasActiveParent())
                {
                    $this->GetParentData();
                    if ($this->HMAddress <> '')
                        $this->ReadPowerSysVar();
                }
            }
            else
            {
                $this->SetSummary('');
            }
        }
        else
        {
            $this->SetSummary('');
        }
    }

    public function ReceiveData($JSONString)
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //    
        if (!$this->GetPowerSysVarAddress())
            return;
        $Data = json_decode($JSONString);
        if ($this->HMDeviceAddress <> (string) $Data->DeviceID)
            return;
        if ((string) $Data->VariableName <> 'ENERGY_COUNTER')
            return;
        $this->ReadPowerSysVar();
    }

################## PRIVATE                

    private function CheckConfig()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        if ($this->ReadPropertyInteger("EventID") == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            return false;
        }
        else
        {
            // Prüfe Ob HM-Device
            $parent = IPS_GetParent($this->ReadPropertyInteger("EventID"));
            if ((IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] == '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                    and ( IPS_GetObject($this->ReadPropertyInteger("EventID"))['ObjectIdent'] == 'ENERGY_COUNTER'))
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

    private function GetPowerSysVarAddress()
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
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!");
        }

        $url = 'GetPowerMeter.exe';
//          $HMScript='Meter=dom.GetObject("BidCos-RF.'.$HMDeviceAddress .'.ENERGY_COUNTER").Device();';
        $HMScript = 'object oitemID;' . PHP_EOL
                . 'oitemID = dom.GetObject("svEnergyCounter_" # dom.GetObject("BidCos-RF.' . $this->HMDeviceAddress . '.ENERGY_COUNTER").Device() # "_' . $this->HMDeviceAddress . '");' . PHP_EOL
                . 'Value=oitemID.Value();' . PHP_EOL;
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult == '')
            throw new Exception('Error on read PowerMeterData');
        try
        {
            $xml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
            $Value = ((float) $xml->Value) / 1000;
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR, 'Error on read PowerMeterAddress');
            throw new Exception(KL_ERROR, 'Error on read PowerMeterAddress');
        }


        /*        $url = 'GetPower.exe';
          $HMScript = 'Value=dom.GetObject(' . $PowerMeterAddress . ').Value();' . PHP_EOL;
          $HMScriptResult = $this->LoadHMScript($url, $HMScript);
          if ($HMScriptResult == '')
          throw new Exception('Error on read PowerMeterData');
          $xml = @new SimpleXMLElement($HMScriptResult);
          if (($xml === false) or ( !isset($xml->Value)))
          {
          $this->LogMessage(KL_ERROR, 'HM-Script result is not wellformed');
          throw new Exception('Error on read PowerMeterData');
          } */
        $VarID = @IPS_GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->InstanceID);
        if ($VarID === false)
            return;
        SetValueFloat($VarID, $Value);
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
###################### protected

    protected function GetParentData()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        parent::GetParentData();
    }

}

?>