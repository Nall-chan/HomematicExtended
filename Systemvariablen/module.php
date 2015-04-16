<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMSystemVariable extends HMBase
{

    private $CcuVarType = array(2 => vtBoolean, 4 => vtFloat, 16 => vtInteger, 20 => vtString);
    private $HMTriggerAddress;
    private $HMTriggerName;

    public function __construct($InstanceID)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//Never delete this line!
        parent::__construct($InstanceID);

//These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterTimer("ReadHMSysVar", 0);
    }

    /*
      public function ProcessInstanceStatusChange($InstanceID, $Status)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //

      if ($this->fKernelRunlevel == KR_READY)
      { */

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
      }
     */
    /*          if (($InstanceID == @IPS_GetInstanceParentID($this->InstanceID)) or ( $InstanceID == 0))
      {
      $HMAdress = $this->GetParentData();
      if ($this->HasActiveParent())
      {
      if ($this->CheckConfig())
      {
      if ($HMAdress <> '')
      {
      if ($this->ReadPropertyInteger('Interval') >= 5)
      $this->SetTimerInterval('ReadHMSysVar', $this->ReadPropertyInteger('Interval'));
      $this->ReadSysVars();
      }
      } else
      {
      if ($this->ReadPropertyInteger('Interval') >= 5)
      $this->SetTimerInterval('ReadHMSysVar', 0);
      }
      } else
      {
      if ($this->ReadPropertyInteger('Interval') >= 5)
      $this->SetTimerInterval('ReadHMSysVar', 0);
      }
      }
      }
      parent::ProcessInstanceStatusChange($InstanceID, $Status);
      }

      public function MessageSink($Msg)
      {
      IPS_LogMessage(__CLASS__, __FUNCTION__); //
     */
    /*
     *   if (msg.Message = IPS_KERNELMESSAGE) and (msg.SenderID=0) and (Msg.Data[0] = KR_READY) then
      begin
      if  CheckConfig() then
      begin
      GetParentData();
      if HMAddress <> '' then
      begin
      ReadSysVars();
      if (GetProperty('Interval') >= 5) then SetTimerInterval('ReadHMSysVar', GetProperty('Interval'));
      end;
      end;
      end;
      if msg.SenderID <> 0 then
      begin
      if msg.Message=VM_DELETE then
      begin
      for I := (SysIdents.Count - 1) downto 0 do
      begin
      if SysIdents.Items[i].IPSVarID = msg.SenderID then
      begin
      if fKernel.ProfilePool.VariableProfileExists('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysIdents.Items[i].HMVarID) then
      fKernel.ProfilePool.DeleteVariableProfile('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysIdents.Items[i].HMVarID);
      SysIdents.Delete(i);
      end;
      end;
      end;
      if msg.Message=DM_CONNECT then
      begin
      if not HasActiveParent then sleep(250);
      if not HasActiveParent then exit;
      if (msg.SenderID = fInstanceID) or (msg.SenderID = fKernel.DataHandlerEx.GetInstanceParentID(fInstanceID)) then
      begin
      GetParentData();
      if HMAddress = '' then exit;
      if (GetProperty('Interval') >= 5) then SetTimerInterval('ReadHMSysVar', GetProperty('Interval'));
      end;
      end;
      if msg.Message=DM_DISCONNECT then
      begin
      if (msg.SenderID = fInstanceID) or (msg.SenderID = fKernel.DataHandlerEx.GetInstanceParentID(fInstanceID)) then
      begin
      SetSummary('No parent');
      if (GetProperty('Interval') >= 5) then SetTimerInterval('ReadHMSysVar', 0);
      HMAddress:='';
      end;
      end;
      if msg.SenderID=GetProperty('EventID') then
      begin
      if msg.Message=VM_UPDATE then
      begin
      if HasActiveParent then
      begin
      ReadSysVars;
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
//  }

    public function ApplyChanges()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
//        IPS_LogMessage('Config', print_r(json_decode(IPS_GetConfiguration($this->InstanceID)), 1));
//Never delete this line!
        parent::ApplyChanges();
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //                   
//        IPS_LogMessage('Config', print_r(json_decode(IPS_GetConfiguration($this->InstanceID)), 1));

        /*
          if ($this->fKernelRunlevel == KR_INIT)
          {
          foreach (IPS_GetChildrenIDs($this->InstanceID) as $Child)
          {
          $Objekt = IPS_GetObject($Child);
          if ($Objekt['ObjectType'] <> 2)
          continue;
          $Var = IPS_GetVariable($Child);
          $this->MaintainVariable($Objekt['ObjectIdent'], $Objekt['ObjectName'], $Var['ValueType'], 'HM.SysVar' . $this->InstanceID . '.' . $Objekt['ObjectIdent'], $Objekt['ObjectPosition'], true);
          $this->EnableAction($Objekt['ObjectIdent']);
          //                $this->MaintainAction($Objekt['ObjectIdent'], 'ActionHandler', true);
          }
          } else
          { */
        if ($this->CheckConfig())
        {
            if ($this->ReadPropertyInteger("Interval") >= 5)
            {
                $this->SetTimerInterval("ReadHMSysVar", $this->ReadPropertyInteger("Interval"));
            }
            else
            {
                $this->SetTimerInterval("ReadHMSysVar", 0);
            }
        }
        else
        {
            $this->SetTimerInterval("ReadHMSysVar", 0);
        }
        $this->GetParentData();
        if ($this->HMAddress <> '')
        {
            if ($this->HasActiveParent())
                $this->ReadSysVars();
        }

//        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        if ($this->ReadPropertyInteger("Interval") < 0)
        {
            $this->SetStatus(202); //Error Timer is Zero
            return false;
        }
        elseif ($this->ReadPropertyInteger("Interval") >= 5)
        {
            if ($this->ReadPropertyInteger("EventID") == 0)
            {
                $this->SetStatus(IS_ACTIVE); //OK
            }
            else
            {
                $this->SetStatus(106); //Trigger und Timer aktiv                      
            }
        }
        elseif ($this->ReadPropertyInteger("Interval") == 0)
        {
            if ($this->ReadPropertyInteger("EventID") == 0)
            {
                $this->SetStatus(IS_INACTIVE); // kein Trigger und Timer aktiv
            }
            else
            {
                if ($this->ReadPropertyBoolean("EmulateStatus") == true)
                {
                    $this->SetStatus(105); //Status emulieren nur empfohlen bei Interval.
                }
                else
                {
                    $parent = IPS_GetParent($this->ReadPropertyInteger("EventID"));
                    if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                    {
                        $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                    }
                    else
                    {  //ist HM Device
                        if (strpos('BidCoS-RF:', IPS_ReadProperty($parent, "Address")) === false)
                        {
                            $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                        }
                        else
                        {
                            $this->SetStatus(IS_ACTIVE); //OK
                        }
                    }
                }
            }
        }
        else
        {
            $this->SetStatus(108);  //Warnung Trigger zu klein                  
        }
        return true;
    }

    private function TimerFire()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;
        $this->ReadSysVars();
    }

    public function ReceiveData($JSONString)
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //    
        if (!$this->GetTriggerVar())
            return;
        $Data = json_decode($JSONString);
        if ($this->HMTriggerAddress <> (string) $Data->DeviceID)
            return;
        if ($this->HMTriggerName <> (string) $Data->VariableName)
            return;
        $this->ReadSysVars();
    }

    protected function GetParentData()
    {
        parent::GetParentData();
        $this->SetSummary($this->HMAddress);
    }

    private function GetTriggerVar()
    {
        $EventID = $this->ReadPropertyInteger("EventID");
        if ($EventID == 0)
            return false;
        $parent = IPS_GetParent($EventID);
        $this->HMTriggerAddress = IPS_GetProperty($parent, 'Address');
        $this->HMTriggerName = IPS_GetObject($EventID)['ObjectIdent'];
        return true;
    }

    private function ReadSysVars()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");

        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        $HMScript = 'SysVars=dom.GetObject(ID_SYSTEM_VARIABLES).EnumUsedIDs();';
        $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
        if ($HMScriptResult === false)
        {
            throw new Exception("Error on Read CCU Systemvariable");
        }
        try
        {
            $xmlVars = new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR, 'HM-Script result is not wellformed');
            throw new Exception("Error on Read CCU Systemvariable");
        }


        $HMScript = 'Now=system.Date("%F %T%z");' . PHP_EOL
                . 'TimeZone=system.Date("%z");' . PHP_EOL;
        $HMScriptResult = $this->LoadHMScript('Time.exe', $HMScript);
        if ($HMScriptResult === false)
        {
            throw new Exception("Error on Read CCU Systemvariable");
        }
        try
        {
            $xmlTime = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR, 'HM-Script result is not wellformed');
            throw new Exception("Error on Read CCU Systemvariable");
        }

        $Date = new DateTime((string) $xmlTime->Now);
        $CCUTime = $Date->getTimestamp();
        $Date = new DateTime();
        $NowTime = $Date->getTimestamp();
        $TimeDiff = $NowTime - $CCUTime;
        $CCUTimeZone = (string) $xmlTime->TimeZone;

        foreach (explode(chr(0x09), (string) $xmlVars->SysVars) as $SysVar)
        {
            $HMScript = 'Name=dom.GetObject(' . $SysVar . ').Name();' . PHP_EOL
                    . 'ValueType=dom.GetObject(' . $SysVar . ').ValueType();' . PHP_EOL
                    . 'ValueSubType=dom.GetObject(' . $SysVar . ').ValueSubType();' . PHP_EOL
                    . 'Value=dom.GetObject(' . $SysVar . ').Value();' . PHP_EOL
                    . 'Variable=dom.GetObject(' . $SysVar . ').Variable();' . PHP_EOL
                    . 'LastValue=dom.GetObject(' . $SysVar . ').LastValue();' . PHP_EOL
                    . 'Timestamp=dom.GetObject(' . $SysVar . ').Timestamp();' . PHP_EOL
                    . 'ValueList=dom.GetObject(' . $SysVar . ').ValueList();' . PHP_EOL
                    . 'ValueName0=dom.GetObject(' . $SysVar . ').ValueName0();' . PHP_EOL
                    . 'ValueName1=dom.GetObject(' . $SysVar . ').ValueName1();' . PHP_EOL
                    . 'ValueMin=dom.GetObject(' . $SysVar . ').ValueMin();' . PHP_EOL
                    . 'ValueMax=dom.GetObject(' . $SysVar . ').ValueMax();' . PHP_EOL
                    . 'ValueUnit=dom.GetObject(' . $SysVar . ').ValueUnit();' . PHP_EOL;
            $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
            if ($HMScriptResult === false)
            {
                $this->LogMessage(KL_WARNING, 'HM-Script result is not wellformed');
                continue;
            }

            try
            {
                $xmlVar = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
            }
            catch (Exception $ex)
            {
                $this->LogMessage(KL_WARNING, 'HM-Script result is not wellformed');
                continue;
            }
            $VarName = utf8_decode((string) $xmlVar->Name);
            $VarID = @IPS_GetObjectIDByIdent($SysVar, $this->InstanceID);
            $VarType = $this->CcuVarType[(int) $xmlVar->ValueType];
            $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $SysVar;
            IPS_LogMessage($VarName, print_r($xmlVar, true));
            if (($VarID === false) or ( !IPS_VariableProfileExists($VarProfil)))
            {                 // neu anlegen wenn VAR neu ist oder Profil nicht vorhanden
// löschen wenn noch vorhanden weil Var neu ist
                if (IPS_VariableProfileExists($VarProfil))
                    IPS_VariableProfilDelete($VarProfil);
                if ((int) $xmlVar->ValueType == vtString)
                {
                    $VarProfil='~String';
                }
                else
                {
                    IPS_CreateVariableProfile($VarProfil, $VarType);
                    switch ($VarType)
                    {
                        case vtBoolean:
                            if (isset($xmlVar->ValueName0))
                                IPS_SetVariableProfileAssociation($VarProfil, 0, utf8_decode((string) $xmlVar->ValueName0), '', -1);
                            if (isset($xmlVar->ValueName1))
                                IPS_SetVariableProfileAssociation($VarProfil, 1, utf8_decode((string) $xmlVar->ValueName1), '', -1);
                            break;
                        case vtFloat:
                            IPS_SetVariableProfileDigits($VarProfil, strlen((string) $xmlVar->ValueMin) - strpos('.', (string) $xmlVar->ValueMin) - 1);
                            IPS_SetVariableProfileValues($VarProfil, (float) $xmlVar->ValueMin, (float) $xmlVar->ValueMax, 1);
                            break;
                    }
                    if (isset($xmlVar->ValueUnit))
                        IPS_SetVariableProfileText($VarProfil, '', ' ' . utf8_decode((string) $xmlVar->ValueUnit));
                    if ((isset($xmlVar->ValueSubType)) and ( (int) $xmlVar->ValueSubType == 29))
                        foreach (explode(';', (string) $xmlVar->ValueList) as $Index => $ValueList)
                        {
                            IPS_SetVariableProfileAssociation($VarProfil, $Index, trim($ValueList), '', -1);
                        }
                }
            }
            if ($VarID === false)
            {
                $this->MaintainVariable($SysVar, $VarName, $VarType, $VarProfil, 0, true);
                $this->EnableAction($SysVar);

//                $this->MaintainAction($SysVar, 'ActionHandler', true);
                $VarID = @IPS_GetObjectIDByIdent($SysVar, $this->InstanceID);
            }
            else
            {
                if (IPS_GetName($VarID) <> $VarName)
                    IPS_SetName($VarID, $VarName);
            }
            if (IPS_GetVariable($VarID)['VariableType'] <> $VarType)
            {
                $this->LogMessage(KL_WARNING, 'Type of CCU Systemvariable ' . $VarName . ' has changed.');
//                throw new Exception('Type of CCU Systemvariable ' . (string) $varXml->Name . ' has changed.');
                continue;
            }
            $VarTime = new DateTime((string) $xmlVar->Timestamp . $CCUTimeZone);

            if (!(IPS_GetVariable($VarID)['VariableUpdated'] < ($TimeDiff + $VarTime->getTimestamp())))
                continue;
            switch ($VarType)
            {
                case vtBoolean:
                    if ((int) $xmlVar->Variable == 1)
                        SetValueBoolean($VarID, true);
                    else
                        SetValueBoolean($VarID, false);
                    break;
                case vtInteger:
                    SetValueInteger($VarID, (int) $xmlVar->Variable);
                    break;
                case vtFloat:
                    SetValueFloat($VarID, (float) $xmlVar->Variable);
                    break;
                case vtString:
                    SetValueString($VarID, utf8_decode((string) $xmlVar->Variable));
                    break;
            }
        }
        return true;
    }

    private function WriteSysVar($Parameter, $ValueStr)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        if ($this->fKernelRunlevel <> KR_READY)
            return false;
        if (!$this->HasActiveParent())
            return false;
        $url = 'SysVar.exe';
        $HMScript = 'State=dom.GetObject(' . $Parameter . ').State("' . $ValueStr . '");';
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult === false)
            return false;
        try
        {
            $xml = new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR, 'HM-Script result is not wellformed');
            return false;
        }

        if ((string) $xml->State == 'true')
            return true;
        else
            return false;
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $VarID = $this->GetStatusVarIDex();
        if (!$this->HasActiveParent())
            throw new Exception('Instance has no active Parent Instance!');
        switch (IPS_GetVariable($Ident)['VariableType'])
        {
            case vtBoolean:
                if (!is_bool($Value))
                    throw new Exception('Wrong Datatype for ' . $VarID);
                $this->WriteValueBoolean($Ident, (bool) $Value);
                break;
            case vtInteger:
                if (!is_numeric($Value))
                    throw new Exception('Wrong Datatype for ' . $VarID);
                $this->WriteValueInteger($Ident, (int) $Value);
                break;
            case vtFloat:
                if ((!is_float($Value)) and ( !is_numeric($Value)))
                    throw new Exception('Wrong Datatype for ' . $VarID);
                $this->WriteValueFloat($Ident, (float) $Value);
                break;
            case vtString:
                $this->WriteValueFloat($Ident, (string) $Value);
                break;
        }
    }

    private function GetStatusVarIDex($Ident)
    {
        $VarID = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($VarID === false)
            throw new Exception('Ident ' . $Ident . ' do not exist.');
        else
            return $VarID;
    }

################## PUBLIC    
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function ReadSystemVariables()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!");
        else
            return $this->ReadSysVars();
    }

    public function WriteValueBoolean($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtBoolean)
            throw new Exception('Wrong Datatype for ' . $VarID);
        else
        {
            if ($Value)
                $ValueStr = 'true';
            else
                $ValueStr = 'false';

            if (!$this->WriteSysVar($Parameter, $ValueStr))
                throw new Exception('Error on write Data ' . $VarID);
            else
            {
                if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                    SetValueBoolean($VarID, $Value);
            }
        }
    }

    public function WriteValueInteger($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtInteger)
            throw new Exception('Wrong Datatype for ' . $VarID);
        else
        {
            if (!$this->WriteSysVar($Parameter, (string) $Value))
                throw new Exception('Error on write Data ' . $VarID);
            else
            {
                if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                    SetValueInteger($VarID, $Value);
            }
        }
    }

    public function WriteValueFloat($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtFloat)
            throw new Exception('Wrong Datatype for ' . $VarID);
        else
        {
            if (!$this->WriteSysVar($Parameter, (string) $Value))
                throw new Exception('Error on write Data ' . $VarID);
            else
            {
                if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                    SetValueFloat($VarID, $Value);
            }
        }
    }

    public function WriteValueString($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtString)
            throw new Exception('Wrong Datatype for ' . $VarID);
        else
        {
            if (!$this->WriteSysVar($Parameter, (string) $Value))
                throw new Exception('Error on write Data ' . $VarID);
            else
            {
                if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                    SetValueString($VarID, $Value);
            }
        }
    }

}

?>