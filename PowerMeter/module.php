<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMPowerMeter extends HMBase
{

    private $HMDeviceAddress;

    public function Create()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger("Protocol", 0);
        $this->RegisterPropertyString("Address", "XXX9999999:4");
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterVariableFloat("ENERGY_COUNTER_TOTAL", "ENERGY_COUNTER_TOTAL", "~Electricity");
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
        if (($this->CheckConfig()) and ( $this->GetPowerAddress()))
        {
            $this->SetSummary($this->HMDeviceAddress);
            if ($this->fKernelRunlevel == KR_READY)
                if ($this->HasActiveParent())
                {
                    $this->GetParentData();
                    if ($this->HMAddress <> '')
                        try
                        {
                            $this->ReadPowerSysVar();
                        } catch (Exception $exc)
                        {
                            trigger_error($exc->getMessage(), $exc->getCode());
                            return false;
                        }
                }
            return true;
        }
        $this->SetSummary('');
    }

    public function ReceiveData($JSONString)
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); // 
        //FIXME Bei Status inaktiv abbrechen
        if (!$this->GetPowerAddress())
            return;
        $Data = json_decode($JSONString);
        if ($this->HMDeviceAddress <> (string) $Data->DeviceID)
            return;
        if ((string) $Data->VariableName <> 'ENERGY_COUNTER')
            return;
        try
        {
            $this->ReadPowerSysVar();
        } catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        if ($this->ReadPropertyInteger("EventID") == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            return false;
        } else
        {
            // Prüfe Ob HM-Device
            $parent = IPS_GetParent($this->ReadPropertyInteger("EventID"));
            if ((IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] == '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                    and ( IPS_GetObject($this->ReadPropertyInteger("EventID"))['ObjectIdent'] == 'ENERGY_COUNTER'))
            {
                $this->SetStatus(IS_ACTIVE); //OK
                return true;
            } else
            {
                $this->SetStatus(202);
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
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
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
//          $HMScript='Meter=dom.GetObject("BidCos-RF.'.$HMDeviceAddress .'.ENERGY_COUNTER").Device();';
        $HMScript = 'object oitemID;' . PHP_EOL
                . 'oitemID = dom.GetObject("svEnergyCounter_" # dom.GetObject("BidCos-RF.' . $this->HMDeviceAddress . '.ENERGY_COUNTER").Device() # "_' . $this->HMDeviceAddress . '");' . PHP_EOL
                . 'Value=oitemID.Value();' . PHP_EOL;
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        } catch (Exception $exc)
        {
            throw new Exception('Error on read PowerMeterData', E_USER_NOTICE);
        }
        try
        {
            $xml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
            $Value = ((float) $xml->Value) / 1000;
        } catch (Exception $ex)
        {
            throw new Exception('Error on read PowerMeterAddress', E_USER_NOTICE);
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