<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMPowerMeter extends HMBase
{

    public function __construct($InstanceID)
    {
        //Never delete this line!
        parent::__construct($InstanceID);

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterProperty('EventID', 0);
        $this->RegisterVariabeFloat('ENERGY_COUNTER_TOTAL', 'ENERGY_COUNTER_TOTAL', '~Electricity');
    }

    public function ProcessInstanceStatusChange($InstanceID, $Status)
    {
        if ($this->fKernelRunlevel == KR_READY)
        {
            // FIX ME....
            /*
             * @IPS_GetInstanceParentID replace
    protected function GetParentData()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $result = '';
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            $result = IPS_ReadProperty($parent, 'Host');
        }
        $this->SetSummary($result);
        return $result;
    }    */            
            if ($InstanceID == @IPS_GetInstanceParentID($this->InstanceID))
            {
                if ($this->HasActiveParent())
                {
                    if ($this->CheckConfig())
                    {
                        if (!($this->GetParentData() === false ))
                            $this->ReadPowerSysVar();
                    }
                }
            }
        }
        parent::ProcessInstanceStatusChange($InstanceID, $Status);
    }

    public function MessageSink($Msg)
    {
        /*
          if (msg.Message = IPS_KERNELMESSAGE) and (msg.SenderID=0) and (Msg.Data[0] = KR_READY) then
          begin
          if  CheckConfig() then
          begin
          GetParentData();
          if HMAddress <> '' then  ReadPowerSysVar();
          end;
          end;

          if msg.SenderID <>0 then
          if fKernelRunlevel = KR_READY then
          begin
          if msg.Message=DM_CONNECT then
          begin
          if not HasActiveParent then sleep(250);
          if HasActiveParent then
          begin
          if (msg.SenderID = fInstanceID) or (msg.SenderID = fKernel.DataHandlerEx.GetInstanceParentID(fInstanceID)) then
          begin
          GetParentData();
          end;
          end;
          end;
          if msg.Message=DM_DISCONNECT then
          begin
          if (msg.SenderID = fInstanceID) or (msg.SenderID = fKernel.DataHandlerEx.GetInstanceParentID(fInstanceID)) then
          begin
          SetSummary('No parent');
          HMAddress:='';
          end;
          end;
          if msg.SenderID=GetProperty('EventID') then
          begin
          if msg.Message=VM_UPDATE then
          begin
          if HasActiveParent then
          begin
          if HMAddress <> '' then  ReadPowerSysVar;
          end else begin
          LogMessage(KL_WARNING,'EventRefresh Error - Instance has no active Parent Instance.');
          end;
          end else if msg.Message=VM_DELETE then
          begin
          SetProperty('EventID',0);
          ApplyChanges();
          SaveSettings();
          end;
          end;
          end;
         */
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->fKernelRunlevel == KR_READY)
        {
            $this->CheckConfig();
//            if ($this->CheckConfig())
//                $this->GetParentData();
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
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
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
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
        $VarID = @GetObjectIDByIdent('ENERGY_COUNTER_TOTAL', $this->InstanceID);
        if ($VarID === false)
            return;
        SetValueFloat($VarID, ((float)$xml->Value)/1000);
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
###################### protected

    protected function GetParentData()
    {
//        dont do it
//        $HMAddress = parent::GetParentData();
            // FIX ME....
            /*
             * @IPS_GetInstanceParentID replace
    protected function GetParentData()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $result = '';
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            $result = IPS_ReadProperty($parent, 'Host');
        }
        $this->SetSummary($result);
        return $result;
    }    */      
        $result = parent::GetParentData();
        if ($result == '') return false;
/*        $ObjID = @IPS_GetInstanceParentID($this->InstanceID);
        if ($ObjID === false)
            return false;*/

        $HMAddress = IPS_ReadProperty($ObjID, 'Host');
        $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
        $HMDeviceAddress = IPS_GetProperty($parent, 'Address');
        $url = 'GetMeter.exe';
//          $HMScript='Meter=dom.GetObject("BidCos-RF.'.$HMDeviceAddress .'.ENERGY_COUNTER").Device();';
        $HMScript = 'object oitemID;' . PHP_EOL
                . 'oitemID = dom.GetObject("svEnergyCounter_" # dom.GetObject("BidCos-RF.' . $HMDeviceAddress . '.ENERGY_COUNTER").Device() # "_' . $HMDeviceAddress . '");' . PHP_EOL
                . 'SysVar=oitemID.ID();' . PHP_EOL;
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult == '')
            return false;
        $xml = @new SimpleXMLElement($HMScriptResult);
        if (($xml === false) or ( !isset($xml->SysVar)))
        {
            $this->LogMessage(KL_WARNING, 'HM-Script result is not wellformed');
            return false;
        }
        $this->SetSummary($HMDeviceAddress);
        return (string) $xml->SysVar;
    }

}

?>