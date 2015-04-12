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
            if ($InstanceID == @IPS_GetInstanceParentID($this->InstanceID))
            {
                if ($this->HasActiveParent())
                {
                    if ($this->CheckConfig())
                    {
                        if ($this->GetParentData() <> '')
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
            if ($this->CheckConfig())
                $this->GetParentData();
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
        /*
          begin
          temp := true;
          pObjekt:=nil;
          pInstanz:=nil;
          try
          if GetProperty('EventID') = 0 then
          begin
          SetStatus(IS_INACTIVE); // kein Trigger und Timer aktiv
          temp:=false;
          end else begin
          // Prüfe Ob HM-Device
          parent := fkernel.ObjectManager.GetParent(GetProperty('EventID'));
          pInstanz:=fKernel.InstanceManager.GetInstance(parent);
          pObjekt := fKernel.ObjectManager.GetObject(GetProperty('EventID'));
          if (pInstanz.ModuleInfo.ModuleID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
          and (pObjekt.ObjectIdent = 'ENERGY_COUNTER') then
          begin
          SetStatus(IS_ACTIVE); //OK
          end else begin
          SetStatus(202);
          end;
          end;
          finally
          pObjekt.free;
          pInstanz.free;
          end;
          Result:=temp;
         *  */
        return true;
    }

    private function ReadPowerSysVar()
    {
//                    IPS_LogMessage("HomeMaticSystemvariablen", "Dummy-Module");
        /*
          var HMScriptResult     : wideString;
          Url                : String;
          IPSVarID           : word;
          xmlDoc             : IXMLDocument;
          HMScript           : wideString;
          begin
          HMScriptResult := '';
          IPSVarID := 0;
          if not HasActiveParent then
          begin
          //    raise EIPSModuleObject.Create('Instance has no active Parent Instance!');
          LogMessage(KL_WARNING,'ReadSysPowerVar Error - Instance has no active Parent Instance.');
          exit;
          end;
          url:='GetPower.exe';
          HMScript:='Value=dom.GetObject('+PowerMeterAddress+').Value();';
          HMScriptResult := LoadHMScript(url,HMScript);
          if HMScriptResult = '' then exit;
          CoInitialize(nil);
          xmlDoc := TXMLDocument.Create(nil);
          //  xmlDoc := newXMLDocument;
          //  xmlDoc.XML.Add(HMScriptResult);
          try
          xmlDoc.LoadFromXml(HMScriptResult);
          except
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          //    xmlDoc._Release;
          CoUninitialize;

          //   xmlDoc.Resync;
          exit;
          end;
          try
          if xmlDoc.DocumentElement.ChildNodes['Value'].IsTextElement then
          if xmlDoc.DocumentElement.ChildNodes['Value'].Text <> 'null' then
          if xmlDoc.DocumentElement.ChildNodes['Value'].Text <> 'DOM' then
          try
          IPSVarID := GetStatusVariableID('ENERGY_COUNTER_TOTAL');
          except
          IPSVarID := 0;
          end;
          if IPSVarID <> 0 then
          begin
          xmlDoc.DocumentElement.ChildNodes['Value'].Text := StringReplace(xmlDoc.DocumentElement.ChildNodes['Value'].Text, '.', DecimalSeparator, [rfIgnoreCase, rfReplaceAll]);
          fKernel.VariableManager.WriteVariableFloat(IPSVarID,StrToFloat(xmlDoc.DocumentElement.ChildNodes['Value'].Text)/1000);
          end;
          finally
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          //    xmlDoc._Release;
          freeandnil(xmlDoc);
          CoUninitialize;

          //    xmlDoc.Resync;
          end;
         */
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */
###################### protected

    protected function GetParentData()
    {
        $HMAddress = parent::GetParentData();
        /*
          var parent          : word;
          url             : String;
          HMScriptResult  : wideString;
          xmlDoc          : IXMLDocument;
          HMDeviceAddress : String;
          HMScript        : wideString;
          pInstanz        : TIPSInstance;
          pObjekt         : TIPSObject;
          begin
          inherited;
          HMScriptResult := '';
          pObjekt:=nil;
          pInstanz:=nil;
          if (GetProperty('EventID') <> 0) and (HMAddress <> '') then
          begin
          // Prüfe Ob HM-Device
          try
          parent := fkernel.ObjectManager.GetParent(GetProperty('EventID'));
          pInstanz := fKernel.InstanceManager.GetInstance(parent);
          pObjekt := fKernel.ObjectManager.GetObject(GetProperty('EventID'));
          if (pInstanz.ModuleInfo.ModuleID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
          and (pObjekt.ObjectIdent = 'ENERGY_COUNTER') then
          begin
          HMDeviceAddress:= pInstanz.InstanceInterface.GetProperty('Address');

          url:='GetMeter.exe';
          HMScript:='Meter=dom.GetObject("BidCos-RF.'+HMDeviceAddress +'.ENERGY_COUNTER").Device();';

          HMScript:='object oitemID;' + sLineBreak
          + 'oitemID = dom.GetObject("svEnergyCounter_" # dom.GetObject("BidCos-RF.'+ HMDeviceAddress + '.ENERGY_COUNTER").Device() # "_'+ HMDeviceAddress + '");' +sLineBreak
          + 'SysVar=oitemID.ID();';
          HMScriptResult := LoadHMScript(url,HMScript);
          if HMScriptResult <> '' then
          begin
          CoInitialize(nil);
          xmlDoc := TXMLDocument.Create(nil);
          //xmlDoc := newXMLDocument;
          //  xmlDoc.XML.Add(HMScriptResult);
          try
          xmlDoc.LoadFromXml(HMScriptResult);
          except
          LogMessage(KL_WARNING,'HM-Script result is not wellformed');
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          pObjekt.free;
          pInstanz.free;
          exit;
          end;
          try
          if xmlDoc.DocumentElement.ChildNodes['SysVar'].IsTextElement then
          if xmlDoc.DocumentElement.ChildNodes['SysVar'].Text <> 'null' then
          if xmlDoc.DocumentElement.ChildNodes['SysVar'].Text <> 'DOM' then
          begin
          PowerMeterAddress:=xmlDoc.DocumentElement.ChildNodes['SysVar'].Text;
          SetSummary(HMDeviceAddress);
          end;
          finally
          xmlDoc.Active := false;
          xmlDoc := nil;
          freeandnil(xmlDoc);
          CoUninitialize;
          end;
          end;
          end;
          finally
          pObjekt.free;
          pInstanz.free;
          end;
          end;

         * 
         */
    }

}

?>