<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMSysVar extends HMBase
{

//    private $THMSysVarsList;
//    private $HMAddress;
//Dummy
    private $fKernelRunlevel;
    private $CcuVarType = array(2 => vtBoolean, 4 => vtFloat, 16 => vtInteger, 20 => vtString);

    public function __construct($InstanceID)
    {
//Never delete this line!
        parent::__construct($InstanceID);
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
//These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterTimer("ReadHMSysVar", 0);
        $this->fKernelRunlevel = KR_READY;
    }

    public function __destruct()
    {
//        $this->SetTimerInterval('ReadHMSysVar', 0);
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
//unnötig ?                    parent::__destruct();                    
    }

    public function ProcessInstanceStatusChange($InstanceID, $Status)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        if ($this->fKernelRunlevel == KR_READY)
        {
            if (($InstanceID == @IPS_GetInstanceParentID($this->InstanceID)) or ( $InstanceID == 0))
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
    }

    public function ApplyChanges()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        IPS_LogMessage('Config', print_r(json_decode(IPS_GetConfiguration($this->InstanceID)), 1));
//Never delete this line!
        parent::ApplyChanges();
        IPS_LogMessage(__CLASS__, __FUNCTION__); //                   
        IPS_LogMessage('Config', print_r(json_decode(IPS_GetConfiguration($this->InstanceID)), 1));
        if ($this->fKernelRunlevel == KR_INIT)
        {
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $Child)
            {
                $Objekt = IPS_GetObject($Child);
                if ($Objekt['ObjectType'] <> 2)
                    continue;
                $Var = IPS_GetVariable($Child);
                $this->MaintainVariable($Objekt['ObjectIdent'], $Objekt['ObjectName'], $Var['ValueType'], 'HM.SysVar' . $this->InstanceID . '.' . $Objekt['ObjectIdent'], $Objekt['ObjectPosition'], true);
                $this->MaintainAction($Objekt['ObjectIdent'], 'ActionHandler', true);
//        MaintainVariable(true,Ident,Name,cVariable.VariableValue.ValueType,'HM.SysVar'+ IntToStr(fInstanceID) +'.'+Ident,ActionHandler);
//                $this->THMSysVarsList[$Child] = $Objekt['ObjectIdent'];
            }
        } else
        {
            if ($this->CheckConfig())
            {
                if ($this->ReadPropertyInteger('Interval') >= 5)
                {
                    $this->SetTimerInterval('ReadHMSysVar', $this->ReadPropertyInteger('Interval'));
                }
                else
                {
                    $this->SetTimerInterval('ReadHMSysVar', 0);
                }
            }
            else
            {
                $this->SetTimerInterval('ReadHMSysVar', 0);
            }
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        if ($this->ReadPropertyInteger('Interval') < 0)
        {
            $this->SetStatus(202); //Error Timer is Zero
            return false;
        }
        elseif ($this->ReadPropertyInteger('Interval') >= 5)
        {
            if ($this->ReadPropertyInteger('EventID') == 0)
            {
                $this->SetStatus(IS_ACTIVE); //OK
            }
            else
            {
                $this->SetStatus(106); //Trigger und Timer aktiv                      
            }
        }
        elseif ($this->ReadPropertyInteger('Interval') == 0)
        {
            if ($this->ReadPropertyInteger('EventID') == 0)
            {
                $this->SetStatus(IS_INACTIVE); // kein Trigger und Timer aktiv
            }
            else
            {
                if ($this->ReadPropertyBoolean('EmulateStatus') == true)
                {
                    $this->SetStatus(105); //Status emulieren nur empfohlen bei Interval.
                }
                else
                {
                    $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
                    if (IPS_GetInstance($parent)['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                    {
                        $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                    }
                    else
                    {  //ist HM Device
                        if (strpos('BidCoS-RF:', IPS_ReadProperty($parent, 'Address')) === false)
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

        if ($this->HasActiveParent())
            $this->ReadSysVars();
    }

    private function ReadSysVars()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");

        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        if ($this->GetParentData() == '')
            return;
        $HMScript = 'SysVars=dom.GetObject(ID_SYSTEM_VARIABLES).EnumUsedIDs();';
        $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
        if ($HMScriptResult === false)
            return;
        $xmlVars = @new SimpleXMLElement($HMScriptResult);
        if (($xmlVars === false))
        {
            $this->LogMessage('HM-Script result is not wellformed');
            throw new Exception("Error on Read CCU-Programs");
        }


        $HMScript = 'Now=system.Date("%F %T%z");' . PHP_EOL
                . 'TimeZone=system.Date("%z");' . PHP_EOL;
        $HMScriptResult = $this->LoadHMScript('Time.exe', $HMScript);
        if ($HMScriptResult === false)
            return;
        $xmlTime = @new SimpleXMLElement($HMScriptResult);
        if (($xmlTime === false))
        {
            $this->LogMessage('HM-Script result is not wellformed');
            throw new Exception("Error on Read CCU-Programs");
        }
        $Date = new DateTime((string) $xmlTime->Now);
        $CCUTime = $Date->getTimestamp();
        $Date = new DateTime();
        $NowTime = $Date->getTimestamp();
        $TimeDiff = $NowTime - $CCUTime;
        $CCUTimeZone = (string) $xmlTime->TimeZone;

        foreach (explode(' ', (string) $xmlVars->SysVars) as $SysVar)
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

            $xmlVar = @new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
            if (($xmlVar === false))
            {
                $this->LogMessage('HM-Script result is not wellformed');
                continue;
//                throw new Exception("Error on Read CCU-Programs");
            }
            $VarID = @GetObjectIDByIdent($SysVar, $this->InstanceID);
            $VarType = $this->CcuVarType[(int) $xmlVar->ValueType];
            $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $SysVar;
            if (($VarID === false) or ( !IPS_VariableProfilExists($VarProfil)))
            {                 // neu anlegen wenn VAR neu ist oder Profil nicht vorhanden
// löschen wenn noch vorhanden weil Var neu ist
                if (IPS_VariableProfilExists($VarProfil))
                    IPS_VariableProfilDelete($VarProfil);
                if ((int) $xmlVar->ValueType <> vtString)
                    IPS_CreateVariableProfil($VarProfil, $VarType);
                switch ($VarType)
                {
                    case vtBoolean:
                        if (isset($xmlVar->ValueName0))
                            IPS_SetVariableProfilAssociation($VarProfil, 0, (string) $xmlVar->ValueName0, '', -1);
                        if (isset($xmlVar->ValueName1))
                            IPS_SetVariableProfilAssociation($VarProfil, 1, (string) $xmlVar->ValueName1, '', -1);
                        break;
                    case vtFloat:
                        IPS_SetVariableProfileDigits($VarProfil, strlen((string) $xmlVar->ValueMin) - strpos('.', (string) $xmlVar->ValueMin) - 1);
                        IPS_SetVariableProfileValues($VarProfil, (float) $xmlVar->ValueMin, (float) $xmlVar->ValueMax, 1);
                        break;
                }
                if (isset($xmlVar->ValueUnit))
                    IPS_SetVariableProfileText($VarProfil, ' ' . (string) $xmlVar->ValueUnit);
                if (isset($xmlVar->ValueSubType) and ( (int) $this->ValueSubType == 29))
                {
                    foreach (explode(';', (string) $xmlVar->ValueList) as $Index => $ValueList)
                    {
                        IPS_SetVariableProfileAssociation($VarProfil, $Index, trim($ValueList), '', -1);
                    }
                }
            }
            if ($VarID === false)
            {
                $this->MaintainVariable($SysVar, (string) $xmlVar->Name, $VarType, $VarProfil, 0, true);
                $this->MaintainAction($SysVar, 'ActionHandler', true);
                $VarID = @GetObjectIDByIdent($SysVar, $this->InstanceID);
            }
            else
            {
                if (IPS_GetName($VarID) <> (string) $xmlVar->Name)
                    IPS_SetName($VarID, (string) $xmlVar->Name);
            }
            if (IPS_GetVariable($VarID)['VariableValue']['ValueType'] <> $VarType)
            {
                $this->LogMessage(KL_WARNING, 'Type of CCU Systemvariable ' . (string) $xmlVar->Name . ' has changed.');
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
                    SetValueString($VarID, (string) $xmlVar->Variable);
                    break;
            }
        }
    }

    private function WriteSysVar($Parameter, $ValueStr)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        /*
         *      Result:=false;
          if fKernelRunlevel <> KR_READY then exit;
          if  not HasActiveParent then exit;
          if HMAddress = '' then exit;
          url:='SysVar.exe';
          HMScript:='State=dom.GetObject('+Parameter+').State("'+ ValueStr +'");';
          HMScriptResult := LoadHMScript(url,HMScript);
          CoInitialize(nil);
          // xmlDoc := newXMLDocument;
          xmlDoc := TXMLDocument.Create(nil);
          xmlDoc.Active := false;
          //     xmlDoc.XML.Add(HMScriptResult);
          try
          xmlDoc.LoadFromXml(HMScriptResult);
          //    xmlDoc.Encoding :='UTF-8';
          //       xmlDoc.Encoding :='ISO-8859-1';
          //       xmlDoc.Active := true;
          except
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          xmlDoc := nil;
          //    xmlDoc._Release;
          freeandnil(xmlDoc);
          CoUninitialize;
          Result:=false;
          exit;
          end;
          if xmlDoc.DocumentElement.ChildNodes['State'].Text = 'true' then
          begin
          Result:=true;
          end else begin
          Result:=false;
          end;
          xmlDoc:=nil;
          freeandnil(xmlDoc);
          CoUninitialize;

         */
    }

################## ActionHandler

    public function ActionHandler($StatusVariable, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        /*
         *    begin
          try
          IPSVarID := GetStatusVariableID(StatusVariable);
          except
          IPSVarID := 0;
          end;
          if IPSVarID = 0 then exit;
          if  not HasActiveParent then
          begin
          raise EIPSModuleObject.Create('Instance has no active Parent Instance!');
          exit;
          end;
          cVariable:=fKernel.VariableManager.GetVariable(IPSVarID);
          case cVariable.VariableValue.ValueType of
          vtBoolean:
          begin
          if not VarIsType(Value,varBoolean) then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          cVariable.Free;
          exit;
          end;
          WriteValueBoolean(StatusVariable,Variants.VarAsType(Value,varBoolean));
          end;
          vtInteger:
          begin
          if not  VarIsNumeric(Value) then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          cVariable.Free;
          exit;
          end;
          WriteValueInteger(StatusVariable,Variants.VarAsType(Value,varInteger));
          end;
          vtFloat:
          begin
          if (not VarIsFloat(Value)) and (not VarIsNumeric(Value))  then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          cVariable.Free;
          exit;
          end;
          WriteValueFloat(StatusVariable,Variants.VarAsType(Value,varDouble));
          end;
          vtString:
          begin
          if not VarIsStr(Value) then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          cVariable.Free;
          exit;
          end;
          WriteValueString(StatusVariable,Variants.VarAsType(Value,varString));
          end;
          end;
          cVariable.Free;

         */
    }

    private function GetStatusVarIDex($Ident)
    {
        $VarID = @GetObjectIDByIdent($Ident, $this->InstanceID);
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
            $this->ReadSysVars();
    }

    public function WriteValueBoolean($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $VarID = GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableValue']['ValueType'] <> vtBoolean)
            throw new Exception('Wrong Datatype for ' . $VarID);
        else
        {
            if ($Value)
                $ValueStr = 'true';
            else
                $ValueStr = 'false';
        }
        if (!$this->WriteSysVar($Parameter, $ValueStr))
            throw new Exception('Error on write Data ' . $VarID);
        else
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueBoolean($VarID, $Value);
        }
    }

    public function WriteValueInteger($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        /*
          //------------------------------------------------------------------------------
          procedure TIPSHMSysVar.WriteValueInteger(Parameter: String; Value: Integer); stdcall;
          var IPSVarID       : word;
          cVariable      : TIPSVariable;
          begin
          IPSVarID := GetStatusVarIDex(Parameter);
          if IPSVarID<> 0 then
          begin
          cVariable :=fKernel.VariableManager.GetVariable(IPSVarID);
          if cVariable.VariableValue.ValueType <> vtInteger then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          end else begin
          if not WriteSysVar(Parameter,IntToStr(Value)) then
          raise EIPSModuleObject.Create('Error on write Data '+ IntToStr(IPSVarID))
          else if GetProperty('EmulateStatus') = true then fKernel.VariableManager.WriteVariableInteger(IPSVarID,Value);
          end;
          cVariable.Free;
          end;
          end; */
    }

    public function WriteValueFloat($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        /*
          //------------------------------------------------------------------------------
          procedure TIPSHMSysVar.WriteValueFloat(Parameter: String; Value: Double); stdcall;
          var IPSVarID       : word;
          ValueStr       : String;
          cVariable      : TIPSVariable;
          begin
          IPSVarID := GetStatusVarIDex(Parameter);
          if IPSVarID<> 0 then
          begin
          cVariable :=fKernel.VariableManager.GetVariable(IPSVarID);
          if cVariable.VariableValue.ValueType <> vtFloat then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          end else begin
          /// format gem. VarProfile
          ValueStr:= Format('%f',[Value]);
          ValueStr:= StringReplace(ValueStr,DecimalSeparator,'.', [rfIgnoreCase, rfReplaceAll]);
          if not WriteSysVar(Parameter,ValueStr) then
          raise EIPSModuleObject.Create('Error on write Data '+ IntToStr(IPSVarID))
          else if GetProperty('EmulateStatus') = true then fKernel.VariableManager.WriteVariableFloat(IPSVarID,Value);
          end;
          cVariable.Free;
          end;
          end; */
    }

    public function WriteValueString($Parameter, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           

        /*
          //------------------------------------------------------------------------------
          procedure TIPSHMSysVar.WriteValueString(Parameter: String; Value: String); stdcall;
          var IPSVarID       : word;
          cVariable      : TIPSVariable;
          begin
          IPSVarID := GetStatusVarIDex(Parameter);
          if IPSVarID<> 0 then
          begin
          cVariable :=fKernel.VariableManager.GetVariable(IPSVarID);
          if cVariable.VariableValue.ValueType <> vtString then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          end else begin
          if not WriteSysVar(Parameter,Value) then
          raise EIPSModuleObject.Create('Error on write Data '+ IntToStr(IPSVarID))
          else if GetProperty('EmulateStatus') = true then fKernel.VariableManager.WriteVariableString(IPSVarID,Value);
          end;
          cVariable.Free;
          end;
          end; */
    }

}

?>