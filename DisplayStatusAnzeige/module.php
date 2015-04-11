<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMSysVar extends HMBase {

    private $THMSysVarsList;
    private $HMAddress;
    //Dummy
    private $fKernelRunlevel;

    public function __construct($InstanceID) {
        //Never delete this line!
        parent::__construct($InstanceID);

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterTimer("ReadHMSysVar", 0);
        $this->fKernelRunlevel = KR_READY;
    }

    public function __destruct() {
        $this->SetTimerInterval('ReadHMSysVar', 0);
//unnötig ?                    parent::__destruct();                    
    }

    public function ProcessInstanceStatusChange($InstanceID, $Status) {
        if ($this->fKernelRunlevel == KR_READY) {
            if (($InstanceID == @IPS_GetInstanceParentID($this->InstanceID)) or ( $InstanceID == 0)) {
                if ($this->HasActiveParent()) {
                    if ($this->CheckConfig()) {
                        $this->GetParentData();
                        if ($this->HMAddress <> '') {
                            if ($this->ReadPropertyInteger('Interval') >= 5)
                                $this->SetTimerInterval('ReadHMSysVar', $this->ReadPropertyInteger('Interval'));
                            $this->ReadSysVars();
                        }
                    }
                } else {
                    $this->HMAddress = '';
                    if ($this->ReadPropertyInteger('Interval') >= 5)
                        $this->SetTimerInterval('ReadHMSysVar', 0);
                }
            }
        }
        parent::ProcessInstanceStatusChange($InstanceID, $Status);
    }

    public function MessageSink($Msg) {
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

    public function ApplyChanges() {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->fKernelRunlevel == KR_INIT) {
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $Child) {
                $Objekt = IPS_GetObject($Child);
                if ($Objekt['ObjectType'] <> 2)
                    continue;
                $Var = IPS_GetVariable($Child);
                $this->MaintainVariable($Objekt['ObjectIdent'], $Objekt['ObjectName'], $Var['ValueType'], 'HM.SysVar' . $this->InstanceID . '.' . $Objekt['ObjectIdent'], $Objekt['ObjectPosition'], true);
                //        MaintainVariable(true,Ident,Name,cVariable.VariableValue.ValueType,'HM.SysVar'+ IntToStr(fInstanceID) +'.'+Ident,ActionHandler);
                $this->THMSysVarsList[$Child] = $Objekt['ObjectIdent'];
            }
        } else {
            if ($this->CheckConfig()) {
                if ($this->ReadPropertyInteger('Interval') >= 5) {
                    $this->SetTimerInterval('ReadHMSysVar', $this->ReadPropertyInteger('Interval'));
                } else {
                    $this->SetTimerInterval('ReadHMSysVar', 0);
                }
            } else {
                $this->SetTimerInterval('ReadHMSysVar', 0);
            }
        }
    }

################## PRIVATE                

    private function CheckConfig() {
        if ($this->ReadPropertyInteger('Interval') < 0) {
            $this->SetStatus(202); //Error Timer is Zero
            return false;
        } elseif ($this->ReadPropertyInteger('Interval') >= 5) {
            if ($this->ReadPropertyInteger('EventID') == 0) {
                $this->SetStatus(IS_ACTIVE); //OK
            } else {
                $this->SetStatus(106); //Trigger und Timer aktiv                      
            }
        } elseif ($this->ReadPropertyInteger('Interval') == 0) {
            if ($this->ReadPropertyInteger('EventID') == 0) {
                $this->SetStatus(IS_INACTIVE); // kein Trigger und Timer aktiv
            } else {
                if ($this->ReadPropertyBoolean('EmulateStatus') == true) {
                    $this->SetStatus(105); //Status emulieren nur empfohlen bei Interval.
                } else {
                    $parent = IPS_GetParent($this->ReadPropertyInteger('EventID'));
                    if (IPS_GetInstance($parent)['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}') {
                        $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                    } else {  //ist HM Device
                        if (strpos('BidCoS-RF:', IPS_ReadProperty($parent, 'Address')) === false) {
                            $this->SetStatus(107);  //Warnung vermutlich falscher Trigger                        
                        } else {
                            $this->SetStatus(IS_ACTIVE); //OK
                        }
                    }
                }
            }
        } else {
            $this->SetStatus(108);  //Warnung Trigger zu klein                  
        }
        return true;
    }

    private function TimerFire() {
        if ($this->HasActiveParent())
            $this->ReadSysVars();
    }

    private function GetParentData() {
        $ObjID = @IPS_GetInstanceParentID($this->InstanceID);
        if ($ObjID <> 0) {
            $this->HMAddress = IPS_ReadProperty($ObjID, 'Host');
            $this->SetSummary(HMAddress);
        } else {
            $this->HMAddress = '';
            $this->SetSummary('');
        }
    }

    private function LoadHMScript($url, $HMScript) {
        if ($this->HMAddress == '') {
            $this->SendData('Error', 'CCU Address not set.');
            $this->LogMessage(KL_ERROR, 'CCU Address not set.');
            return false;
        }
        $ch = curl_init('http://' . $this->HMAddress . ':8181/' . $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $HMScript);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        $this->SendData('Request', 'http://' + $this->HMAddress + ':8181/' + $url);
        $this->SendData('Request', $HMScript);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) {
            $this->SendData('Response', 'Failed');
            $this->LogMessage(KL_WARNING, 'CCU unreachable');
            return false;
        } else {
            $this->SendData('Response', $result);
            return $result;
        }
    }

    private function ReadSysVars() {
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
        /*
         *   SysVarType:=vtBoolean;
          HMScriptResult:='';
          if  not HasActiveParent then
          begin
          LogMessage(KL_WARNING,'ReadSysVars Error - Instance has no active Parent Instance.');
          exit;
          end;
          if HMAddress = '' then exit;
          url:='SysVar.exe';
          HMScript:='SysVars=dom.GetObject(ID_SYSTEM_VARIABLES).EnumUsedIDs();';
          HMScriptResult := LoadHMScript(url,HMScript);
          if HMScriptResult = '' then exit;
          CoInitialize(nil);
          //  xmlDoc := newXMLDocument;
          xmlDoc := TXMLDocument.Create(nil);
          xmlDoc.Active := false;
          //  xmlDoc.XML.Add(HMScriptResult);
          try
          xmlDoc.LoadFromXml(HMScriptResult);
          //    xmlDoc.Encoding :='UTF-8';
          //    xmlDoc.Active := true;
          except
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          EIPSModuleObject.Create('Error on Read SystemVariables');
          exit;
          end;

          if (not xmlDoc.DocumentElement.ChildNodes['SysVars'].IsTextElement) or
          (xmlDoc.DocumentElement.ChildNodes['SysVars'].Text = 'null') or
          (xmlDoc.DocumentElement.ChildNodes['SysVars'].Text = 'DOM') then
          begin
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          EIPSModuleObject.Create('Error on Read SystemVariables');
          exit;
          end;

          ListOfStrings := TStringList.Create;
          ListOfStrings.Clear;
          ListOfStrings.Delimiter     := ' ';
          ListOfStrings.DelimitedText := xmlDoc.DocumentElement.ChildNodes['SysVars'].Text;
          ArrayOfSysVarIDstr := ListOfStrings.toArray;
          ListOfStrings.Free;
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;

          url:='Time.exe';
          HMScript:='Now=system.Date("%F %T");' + sLineBreak
          + 'TimeZone=system.Date("%z");'+sLineBreak;
          HMScriptResult := LoadHMScript(url,HMScript);
          if HMScriptResult = '' then exit;
          CoInitialize(nil);
          //xmlDoc := newXMLDocument;
          xmlDoc := TXMLDocument.Create(nil);
          xmlDoc.Active := false;
          //    xmlDoc.XML.Add(HMScriptResult);
          try
          xmlDoc.LoadFromXml(HMScriptResult);
          //    xmlDoc.Encoding :='UTF-8';
          //      xmlDoc.Encoding :='ISO-8859-1';
          //      xmlDoc.Active := true;
          except
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          EIPSModuleObject.Create('Error on Read SystemVariables');
          exit;
          end;
          CCUTimeStr := xmlDoc.DocumentElement.ChildNodes['Now'].Text;
          CCUTimeZone := xmlDoc.DocumentElement.ChildNodes['TimeZone'].Text;
          CCUTimeZone:=copy(CCUTimeZone,1,3)+':'+copy(CCUTimeZone,4,2);
          //----------------------jetz Zeitdiff IPS <> CCU ermitteln
          CCUUnixTime:=CCUTimeToUnixTime(CCUTimeStr,CCUTimeZone);
          GetSystemTime(UTC);
          NowTime  := DateTimeToUnix(SystemTimeToDateTime(UTC));
          SendData('IPS-UTC Time',DateTimeToStr(UnixToDateTime(NowTime)));
          SendData('CCU-UTC Time',DateTimeToStr(UnixToDateTime(CCUUnixTime)));
          DiffTime := NowTime - CCUUnixTime;
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;


          for SysVarIDstr in ArrayOfSysVarIDstr do
          begin
          if SysVarIDstr = '' then continue;
          if StrToIntDef(SysVarIDstr,0) <> StrToIntDef(SysVarIDstr,1) then continue;
          url:='SysVar.exe';
          HMScript:=
          'Name=dom.GetObject('+SysVarIDstr+').Name();' + sLineBreak
          + 'ValueType=dom.GetObject('+SysVarIDstr+').ValueType();' + sLineBreak
          + 'ValueSubType=dom.GetObject('+SysVarIDstr+').ValueSubType();' + sLineBreak
          + 'Value=dom.GetObject('+SysVarIDstr+').Value();' + sLineBreak
          + 'Variable=dom.GetObject('+SysVarIDstr+').Variable();' + sLineBreak
          + 'LastValue=dom.GetObject('+SysVarIDstr+').LastValue();' + sLineBreak
          + 'Timestamp=dom.GetObject('+SysVarIDstr+').Timestamp();' + sLineBreak
          + 'ValueList=dom.GetObject('+SysVarIDstr+').ValueList();' + sLineBreak
          + 'ValueName0=dom.GetObject('+SysVarIDstr+').ValueName0();' + sLineBreak
          + 'ValueName1=dom.GetObject('+SysVarIDstr+').ValueName1();' + sLineBreak
          + 'ValueMin=dom.GetObject('+SysVarIDstr+').ValueMin();' + sLineBreak
          + 'ValueMax=dom.GetObject('+SysVarIDstr+').ValueMax();' + sLineBreak
          + 'ValueUnit=dom.GetObject('+SysVarIDstr+').ValueUnit();' + sLineBreak;
          HMScriptResult := LoadHMScript(url,HMScript);
          if HMScriptResult = '' then continue;
          CoInitialize(nil);
          xmlDoc := TXMLDocument.Create(nil);
          xmlDoc.Active := false;
          //     xmlDoc.XML.Add(HMScriptResult);
          try
          //    xmlDoc.Encoding :='UTF-8';
          xmlDoc.LoadFromXml(HMScriptResult);

          //         xmlDoc.Encoding :='ISO-8859-1';
          //         xmlDoc.Active := true;
          except
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          EIPSModuleObject.Create('Error on Read SystemVariables');
          continue;
          end;
          if not xmlDoc.DocumentElement.ChildNodes['Timestamp'].IsTextElement then
          begin
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          continue;
          end;
          try
          IPSVarID := GetStatusVariableID(SysVarIDstr);
          except
          IPSVarID := 0;
          end;
          CCUUnixTime:=DiffTime + CCUTimeToUnixTime(xmlDoc.DocumentElement.ChildNodes['Timestamp'].Text,CCUTimeZone);

          case StrToInt(xmlDoc.DocumentElement.ChildNodes['ValueType'].Text) of
          2: SysVarType:=vtBoolean;
          4: SysVarType:=vtFloat;
          16: SysVarType:=vtInteger;
          20: SysVarType:=vtString;
          end;

          if (IPSVarID = 0) or (not fKernel.ProfilePool.VariableProfileExists('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr)) then
          begin
          // neu anlegen wenn in IPS unbekannt oder Profil nicht vorhanden
          if fKernel.ProfilePool.VariableProfileExists('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr) then
          fKernel.ProfilePool.DeleteVariableProfile('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr);
          if SysVarType <> vtString then fKernel.ProfilePool.CreateVariableProfile('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr,SysVarType);
          if SysVarType = vtFloat then
          begin
          fKernel.ProfilePool.SetVariableProfileDigits('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr, length(xmlDoc.DocumentElement.ChildNodes['ValueMin'].Text) - pos('.',xmlDoc.DocumentElement.ChildNodes['ValueMin'].Text)-1);
          xmlDoc.DocumentElement.ChildNodes['ValueMin'].Text := StringReplace(xmlDoc.DocumentElement.ChildNodes['ValueMin'].Text, '.', DecimalSeparator, [rfIgnoreCase, rfReplaceAll]);
          xmlDoc.DocumentElement.ChildNodes['ValueMax'].Text := StringReplace(xmlDoc.DocumentElement.ChildNodes['ValueMax'].Text, '.', DecimalSeparator, [rfIgnoreCase, rfReplaceAll]);
          fKernel.ProfilePool.SetVariableProfileValues('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr, StrToFloat(xmlDoc.DocumentElement.ChildNodes['ValueMin'].Text ),StrToFloat(xmlDoc.DocumentElement.ChildNodes['ValueMax'].Text),1);
          end;
          if xmlDoc.DocumentElement.ChildNodes['ValueUnit'].IsTextElement then
          fkernel.ProfilePool.SetVariableProfileText('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr,'',' ' + xmlDoc.DocumentElement.ChildNodes['ValueUnit'].Text);
          if SysVarType = vtBoolean then
          begin
          if xmlDoc.DocumentElement.ChildNodes['ValueName0'].IsTextElement then
          fKernel.ProfilePool.SetVariableProfileAssociation('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr, 0,xmlDoc.DocumentElement.ChildNodes['ValueName0'].Text,'',-1);
          if xmlDoc.DocumentElement.ChildNodes['ValueName1'].IsTextElement then
          fKernel.ProfilePool.SetVariableProfileAssociation('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr, 1,xmlDoc.DocumentElement.ChildNodes['ValueName1'].Text,'',-1);
          end;
          if StrtoInt(xmlDoc.DocumentElement.ChildNodes['ValueSubType'].Text) = 29 then
          begin  // Werteliste
          ListOfStrings := TStringList.Create;
          ListOfStrings.Clear;
          ListOfStrings.Delimiter     := ';';
          ListOfStrings.StrictDelimiter:=true;
          ListOfStrings.DelimitedText :=      xmlDoc.DocumentElement.ChildNodes['ValueList'].Text;
          ArrayOfValueList := ListOfStrings.toArray;
          ListOfStrings.Free;
          for ValueListID:=0 to high(ArrayOfValueList)  do
          begin
          fKernel.ProfilePool.SetVariableProfileAssociation('HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr, ValueListID, trim(ArrayOfValueList[ValueListID]),'',-1);
          end;
          end;
          end;

          if IPSVarID = 0 then
          begin
          MaintainVariable(true,SysVarIDstr,xmlDoc.DocumentElement.ChildNodes['Name'].Text,SysVarType,'HM.SysVar'+ IntToStr(fInstanceID) +'.'+SysVarIDstr,ActionHandler);
          IPSVarID := GetStatusVariableID(SysVarIDstr);
          SysIdents.Add(THMSysVars.create);
          SysIdents.Last.IPSVarID:= IPSVarID;
          SysIdents.Last.HMVarID:=SysVarIDstr;
          end else
          begin
          if GetName(IPSVarID) <>  xmlDoc.DocumentElement.ChildNodes['Name'].Text then
          fKernel.ObjectManager.SetName(IPSVarID,xmlDoc.DocumentElement.ChildNodes['Name'].Text);
          end;
          cVariable:=fKernel.VariableManager.GetVariable(IPSVarID);
          if cVariable.VariableValue.ValueType <> SysVarType then
          begin
          HMScript:=xmlDoc.DocumentElement.ChildNodes['Name'].Text;
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          LogMessage(KL_WARNING,'Type of CCU Systemvariable '+ HMScript + ' has changed.');
          EIPSModuleObject.Create('Type of CCU Systemvariable '+ HMScript + ' has changed.');
          cVariable.Free;
          continue;
          end;

          if not (cVariable.VariableUpdated < CCUUnixTime) then
          begin
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          //    xmlDoc._Release;
          cVariable.Free;
          continue;
          end;
          cVariable.Free;
          case SysVarType of
          vtBoolean:
          begin
          if StrToInt(xmlDoc.DocumentElement.ChildNodes['Variable'].Text) = 1 then
          begin
          fKernel.VariableManager.WriteVariableBoolean(IPSVarID,true);
          end else begin
          fKernel.VariableManager.WriteVariableBoolean(IPSVarID,false);
          end;
          end;
          vtInteger:
          begin
          fKernel.VariableManager.WriteVariableInteger(IPSVarID,strtoint(xmlDoc.DocumentElement.ChildNodes['Variable'].Text));
          end;
          vtFloat:
          begin
          xmlDoc.DocumentElement.ChildNodes['Value'].Text := StringReplace(xmlDoc.DocumentElement.ChildNodes['Value'].Text, '.', DecimalSeparator, [rfIgnoreCase, rfReplaceAll]);
          fKernel.VariableManager.WriteVariableFloat(IPSVarID,StrToFloat(xmlDoc.DocumentElement.ChildNodes['Value'].Text));
          end;
          vtString:
          begin
          fKernel.VariableManager.WriteVariableString(IPSVarID,xmlDoc.DocumentElement.ChildNodes['Value'].Text);
          end;
          end;
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          end;


         */
    }

    private function WriteSysVar($param, $value) {
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

    public function ActionHandler($StatusVariable, $Value) {
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

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function ReadSystemVariables() {
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!");
        else
            $this->ReadSysVars();
    }

    public function WriteValueBoolean($Parameter, $Value) {
        /*
          var IPSVarID       : word;
          ValueStr       : String;
          cVariable      : TIPSVariable;
          begin
          IPSVarID := GetStatusVarIDex(Parameter);
          if IPSVarID<> 0 then
          begin
          cVariable :=fKernel.VariableManager.GetVariable(IPSVarID);
          if cVariable.VariableValue.ValueType <> vtBoolean then
          begin
          raise EIPSModuleObject.Create('Wrong Datatype for '+ IntToStr(IPSVarID));
          end else begin
          if Value then ValueStr:='true'
          else ValueStr:='false';
          if not WriteSysVar(Parameter,ValueStr) then
          raise EIPSModuleObject.Create('Error on write Data '+ IntToStr(IPSVarID))
          else if GetProperty('EmulateStatus') = true then fKernel.VariableManager.WriteVariableBoolean(IPSVarID,Value);
          end;
          cVariable.free;
          end;
          end; */
    }

    public function WriteValueInteger($Parameter, $Value) {
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

    public function WriteValueFloat($Parameter, $Value) {
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

    public function WriteValueString($Parameter, $Value) {
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

    /*

      $deviceID = $this->CreateInstanceByIdent($this->InstanceID, $this->ReduceGUIDToIdent($_POST['device']), "Device");
      SetValue($this->CreateVariableByIdent($deviceID, "Latitude", "Latitude", 2), floatval($_POST['latitude']));
      SetValue($this->CreateVariableByIdent($deviceID, "Longitude", "Longitude", 2), floatval($_POST['longitude']));
      SetValue($this->CreateVariableByIdent($deviceID, "Timestamp", "Timestamp", 1, "~UnixTimestamp"), intval(strtotime($_POST['date'])));
      SetValue($this->CreateVariableByIdent($deviceID, $this->ReduceGUIDToIdent($_POST['id']), utf8_decode($_POST['name']), 0, "~Presence"), intval($_POST['entry']) > 0);

      } */
    /*
      private function ReduceGUIDToIdent($guid) {
      return str_replace(Array("{", "-", "}"), "", $guid);
      }

      private function CreateCategoryByIdent($id, $ident, $name)
      {
      $cid = @IPS_GetObjectIDByIdent($ident, $id);
      if($cid === false)
      {
      $cid = IPS_CreateCategory();
      IPS_SetParent($cid, $id);
      IPS_SetName($cid, $name);
      IPS_SetIdent($cid, $ident);
      }
      return $cid;
      }

      private function CreateVariableByIdent($id, $ident, $name, $type, $profile = "")
      {
      $vid = @IPS_GetObjectIDByIdent($ident, $id);
      if($vid === false)
      {
      $vid = IPS_CreateVariable($type);
      IPS_SetParent($vid, $id);
      IPS_SetName($vid, $name);
      IPS_SetIdent($vid, $ident);
      if($profile != "")
      IPS_SetVariableCustomProfile($vid, $profile);
      }
      return $vid;
      }

      private function CreateInstanceByIdent($id, $ident, $name, $moduleid = "{485D0419-BE97-4548-AA9C-C083EB82E61E}")
      {
      $iid = @IPS_GetObjectIDByIdent($ident, $id);
      if($iid === false)
      {
      $iid = IPS_CreateInstance($moduleid);
      IPS_SetParent($iid, $id);
      IPS_SetName($iid, $name);
      IPS_SetIdent($iid, $ident);
      }
      return $iid;
      }
     */


################## DUMMYS / WOARKAROUNDS - PRIVATE

    private function HasActiveParent() {
        $id = @IPS_GetInstanceParentID($this->InstanceID);
        if ($id > 0) {
            if (IPS_GetInstance($id)['InstanceStatus'] == 102)
                return true;
            else
                return false;
        }
    }

    private function SetStatus($data) {
        
    }

    private function RegisterTimer($data, $cata) {
        
    }

    private function SetTimerInterval($data, $cata) {
        
    }

    private function LogMessage($data, $cata) {
        
    }

}

?>