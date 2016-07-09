<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMSystemVariable extends HMBase
{

    private $CcuVarType = array(2 => vtBoolean, 4 => vtFloat, 16 => vtInteger, 20 => vtString);
    private $HMTriggerAddress;
    private $HMTriggerName;

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger("Protocol", 0);
        $count = @IPS_GetInstanceListByModuleID('{AF50C42B-7183-4992-B04A-FAFB07BB1B90}');
        if (is_array($count))
        {
            $this->RegisterPropertyString("Address", "XXX9999999:" . count($count));
        }
        else
        {
            $this->RegisterPropertyString("Address", "XXX9999999:0");
        }
        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyBoolean("EnableAlarmDP", true);
        $this->RegisterPropertyInteger("AlarmScriptID", 0);

        $this->RegisterTimer("ReadHMSysVar", 0, 'HM_ReadSystemVariables($_IPS[\'TARGET\']);');
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        ob_start();
        var_dump($Data);
        $dump = ob_get_clean();
        IPS_LogMessage('MessageSink:'.$Message .":". $SenderID, $dump);
    }

// FIX ME....
    /*
      if msg.Message=DM_CONNECT then
      begin
      if not HasActiveParent then sleep(250);
      if not HasActiveParent then exit;
      if (msg.SenderID = fInstanceID) or (msg.SenderID = fKernel.DataHandlerEx.GetInstanceParentID(fInstanceID)) then
      GetParentData();
      if HMAddress = '' then exit;
      end;
      if msg.Message=DM_DISCONNECT then
      begin
      if (msg.SenderID = fInstanceID) or (msg.SenderID = fKernel.DataHandlerEx.GetInstanceParentID(fInstanceID)) then
      SetSummary('No parent');
      end;

      if msg.Message=VM_DELETE then
      begin
      SetProperty('EventID',0);
      ApplyChanges();
      SaveSettings();
      end;
     */
//  }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterProfileIntegerEx('HM.AlReceipt', "", "", "", Array(
            Array(0, "Quittieren", "", 0x00FF00)
        ));

        if ($this->CheckConfig())
        {
            if ($this->ReadPropertyInteger("Interval") >= 5)
            {
                $this->SetTimerInterval("ReadHMSysVar", $this->ReadPropertyInteger("Interval") * 1000);
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

        if ($this->fKernelRunlevel <> KR_READY)
            return;

        $this->GetParentData();

        if ($this->HMAddress == '')
            return;

        if ($this->HasActiveParent())
        {
            try
            {
                $this->ReadSysVars();
            }
            catch (Exception $exc)
            {
                trigger_error($exc->getMessage(), $exc->getCode());
            }
        }
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage($this->InstanceID, DM_CONNECT);
        $this->RegisterMessage($this->InstanceID, DM_DISCONNECT);
        $this->RegisterMessage($this->InstanceID, IM_CHANGESTATUS);

        if ($this->GetTriggerVar())
            $this->SetReceiveDataFilter(".*" . $this->HMTriggerAddress . ".*" . $this->HMTriggerName . ".*");
    }

################## PRIVATE                

    private function CheckConfig()
    {
        $Interval = $this->ReadPropertyInteger("Interval");
        $Event = $this->ReadPropertyInteger("EventID");

        if ($Interval < 0)
        {

            $this->SetStatus(202); //Error Timer is negativ
            return false;
        }
        elseif ($Interval > 4)
        {
            if ($Event == 0)
                $this->SetStatus(IS_ACTIVE); //OK
            else
                $this->SetStatus(IS_ACTIVE); //Trigger und Timer aktiv                      
        }
        elseif ($Interval == 0)
        {
            if ($Event == 0)
                $this->SetStatus(IS_INACTIVE); // kein Trigger und Timer aktiv
            else
                $this->SetStatus(IS_ACTIVE); //OK
                /*
                  }
                  else
                  {
                  if ($this->ReadPropertyBoolean("EmulateStatus") == true)
                  {
                  $this->SetStatus(105); //Status emulieren nur empfohlen bei Interval.
                  }
                  else
                  {
                  $parent = IPS_GetParent($Event);
                  if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
                  {
                  $this->SetStatus(107);  //Warnung vermutlich falscher Trigger
                  }
                  else
                  {  //ist HM Device
                  if (strpos(IPS_GetProperty($parent, "Address"), 'BidCoS-RF:') === false)
                  {
                  $this->SetStatus(107);  //Warnung vermutlich falscher Trigger
                  }
                  else
                  {
                  $this->SetStatus(IS_ACTIVE); //OK
                  }
                  }
                  }
                  } */
        }
        elseif ($Interval < 5)
        {
            $this->SetStatus(203);  //Warnung Trigger zu klein                  
            return false;
        }
        $this->RegisterMessage($Event, VM_DELETE);        

        return true;
    }

    public function ReceiveData($JSONString)
    {
        //TODO
//        if (!$this->GetTriggerVar())
//            return;
//        $Data = json_decode($JSONString);
//        if ($this->HMTriggerAddress <> (string) $Data->DeviceID)
//            return;
//        if ($this->HMTriggerName <> (string) $Data->VariableName)
//            return;
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;
        try
        {
            $this->ReadSysVars();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return;
        }
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

        $HMScript = 'SysVars=dom.GetObject(ID_SYSTEM_VARIABLES).EnumUsedIDs();';
        try
        {
            $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            throw new Exception("Error on Read CCU ID_SYSTEM_VARIABLES", E_USER_NOTICE);
        }

        try
        {
            $xmlVars = new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            throw new Exception("HM-Script result is not wellformed", E_USER_NOTICE);
        }
        $HMScript = 'Now=system.Date("%F %T%z");' . PHP_EOL
                . 'TimeZone=system.Date("%z");' . PHP_EOL;
        try
        {
            $HMScriptResult = $this->LoadHMScript('Time.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            throw new Exception("Error on Read CCU Time", E_USER_NOTICE);
        }
        try
        {
            $xmlTime = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            throw new Exception("HM-Script result is not wellformed", E_USER_NOTICE);
        }

        $Date = new DateTime((string) $xmlTime->Now);
        $CCUTime = $Date->getTimestamp();
        $Date = new DateTime();
        $NowTime = $Date->getTimestamp();
        $TimeDiff = $NowTime - $CCUTime;
        $CCUTimeZone = (string) $xmlTime->TimeZone;

        foreach (explode(chr(0x09), (string) $xmlVars->SysVars) as $SysVar)
        {
            $VarIdent = $SysVar;
            $HMScript = 'Name=dom.GetObject(' . $SysVar . ').Name();' . PHP_EOL
                    . 'ValueType=dom.GetObject(' . $SysVar . ').ValueType();' . PHP_EOL
                    . 'integer Type=dom.GetObject(' . $SysVar . ').Type();' . PHP_EOL
                    . 'WriteLine(dom.GetObject(' . $SysVar . ').Value());' . PHP_EOL
                    . 'WriteLine(dom.GetObject(' . $SysVar . ').Variable());' . PHP_EOL
                    . 'WriteLine(dom.GetObject(' . $SysVar . ').LastValue());' . PHP_EOL
                    . 'Timestamp=dom.GetObject(' . $SysVar . ').Timestamp();' . PHP_EOL;

            try
            {
                $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
            }
            catch (Exception $exc)
            {
                trigger_error($exc->getMessage(), E_USER_NOTICE);
                continue;
            }
            $lines = explode("\r\n", $HMScriptResult);
            $xmlVar = @new SimpleXMLElement(utf8_encode(array_pop($lines)), LIBXML_NONET);
            if ($xmlVar === false)
            {
                trigger_error("HM-Script result is not wellformed. SysVar:" . $SysVar, E_USER_NOTICE);
                continue;
            }
            if ((int) $xmlVar->Type == 2113)
                if (!$this->ReadPropertyBoolean('EnableAlarmDP'))
                    continue;
                else
                    $VarIdent = 'AlDP' . $SysVar;
            $xmlVar->addChild('Value', $lines[0]);
            $xmlVar->addChild('Variable', $lines[1]);
            $xmlVar->addChild('LastValue', $lines[2]);
            $VarID = @IPS_GetObjectIDByIdent($VarIdent, $this->InstanceID);
            $VarType = $this->CcuVarType[(int) $xmlVar->ValueType];
            $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $SysVar;
            $VarName = /* utf8_decode( */(string) $xmlVar->Name;

            if (($VarID === false) or ( !IPS_VariableProfileExists($VarProfil)))
            {                 // neu anlegen wenn VAR neu ist oder Profil nicht vorhanden
                $HMScript = 'Name=dom.GetObject(' . $SysVar . ').Name();' . PHP_EOL
                        . 'ValueSubType=dom.GetObject(' . $SysVar . ').ValueSubType();' . PHP_EOL
                        . 'ValueList=dom.GetObject(' . $SysVar . ').ValueList();' . PHP_EOL
                        . 'ValueName0=dom.GetObject(' . $SysVar . ').ValueName0();' . PHP_EOL
                        . 'ValueName1=dom.GetObject(' . $SysVar . ').ValueName1();' . PHP_EOL
                        . 'ValueMin=dom.GetObject(' . $SysVar . ').ValueMin();' . PHP_EOL
                        . 'ValueMax=dom.GetObject(' . $SysVar . ').ValueMax();' . PHP_EOL
                        . 'ValueUnit=dom.GetObject(' . $SysVar . ').ValueUnit();' . PHP_EOL;
                try
                {
                    $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
                }
                catch (Exception $exc)
                {
                    trigger_error($exc->getMessage(), E_USER_NOTICE);
                    continue;
                }
                $xmlVar2 = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
                if ($xmlVar2 === false)
                {
                    trigger_error("HM-Script result is not wellformed. SysVar:" . $SysVar, E_USER_NOTICE);
                    continue;
                }
                if (IPS_VariableProfileExists($VarProfil))
                    IPS_DeleteVariableProfile($VarProfil);

                if ((int) $xmlVar->ValueType == vtString)
                {
                    $VarProfil = '~String';
                }
                else
                {
                    IPS_CreateVariableProfile($VarProfil, $VarType);
                    switch ($VarType)
                    {
                        case vtBoolean:
                            if (isset($xmlVar2->ValueName0))
                                @IPS_SetVariableProfileAssociation($VarProfil, 0, /* utf8_decode( */ (string) $xmlVar2->ValueName0, '', -1);
                            if (isset($xmlVar2->ValueName1))
                                @IPS_SetVariableProfileAssociation($VarProfil, 1, /* utf8_decode( */ (string) $xmlVar2->ValueName1, '', -1);
                            break;
                        case vtFloat:
                            @IPS_SetVariableProfileDigits($VarProfil, strlen((string) $xmlVar2->ValueMin) - strpos('.', (string) $xmlVar2->ValueMin) - 1);
                            @IPS_SetVariableProfileValues($VarProfil, (float) $xmlVar2->ValueMin, (float) $xmlVar2->ValueMax, 1);
                            break;
                    }
                    if (isset($xmlVar2->ValueUnit))
                        @IPS_SetVariableProfileText($VarProfil, '', ' ' . /* utf8_decode( */(string) $xmlVar2->ValueUnit);
                    if ((isset($xmlVar2->ValueSubType)) and ( (int) $xmlVar2->ValueSubType == 29))
                        foreach (explode(';', (string) $xmlVar2->ValueList) as $Index => $ValueList)
                        {
                            @IPS_SetVariableProfileAssociation($VarProfil, $Index, /* utf8_decode( */ trim($ValueList), '', -1);
                        }
                }
            }
            if ($VarID === false)
            {
                $this->MaintainVariable($VarIdent, $VarName, $VarType, $VarProfil, 0, true);
//                if ((int) $xmlVar->Type <> 2113)
                $this->EnableAction($VarIdent);
//                $this->MaintainAction($SysVar, 'ActionHandler', true);
                $VarID = @IPS_GetObjectIDByIdent($VarIdent, $this->InstanceID);
            }
            else
            {
                if (IPS_GetName($VarID) <> $VarName)
                    IPS_SetName($VarID, $VarName);
            }
            if (IPS_GetVariable($VarID)['VariableType'] <> $VarType)
            {
                trigger_error('Type of CCU Systemvariable ' . IPS_GetName($VarID) . ' has changed.', E_USER_NOTICE);
                continue;
            }
            $VarTime = new DateTime((string) $xmlVar->Timestamp . $CCUTimeZone);

            if (!(IPS_GetVariable($VarID)['VariableUpdated'] < ($TimeDiff + $VarTime->getTimestamp())))
                continue;
            switch ($VarType)
            {
                case vtBoolean:
                    if ((int) $xmlVar->Type == 2113)
                    {
                        $this->ProcessAlarmVariable($VarID, $SysVar, $CCUTimeZone);
                    }
                    else
                    {
                        SetValueBoolean($VarID, (string) $xmlVar->Value == 'true');
                    }
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

    private function ProcessAlarmVariable($ParentID, $SysVar, $CCUTimeZone)
    {
        $HMScript = 'Value = dom.GetObject(' . $SysVar . ').Value();
                        string FirstTime = dom.GetObject(' . $SysVar . ').AlOccurrenceTime();
                        string LastTime = dom.GetObject(' . $SysVar . ').LastTriggerTime();
                        integer LastTriggerID = dom.GetObject(' . $SysVar . ').LastTriggerID();
                        if( LastTriggerID == ID_ERROR )
                        {
                         LastTriggerID = dom.GetObject(' . $SysVar . ').AlTriggerDP();
                        }
                        string ChannelName = "";
                        string Room = "";
                        object oLastTrigger = dom.GetObject( LastTriggerID );
                        if( oLastTrigger )
                        {
                         object oLastTriggerChannel = dom.GetObject( oLastTrigger.Channel() );
                         if( oLastTriggerChannel )
                         {
                          string ChannelName = oLastTriggerChannel.Name();
                          string sRID;
                          foreach( sRID, oLastTriggerChannel.ChnRoom() )
                          {
                           object oRoom = dom.GetObject( sRID );
                           if( oRoom )
                           {
                            Room = oRoom.Name();
                           }
                          }
                         }
                        }
                       }' . PHP_EOL;

        try
        {
            $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }
        $xmlData = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
        if ($xmlData === false)
        {
            trigger_error("HM-Script result is not wellformed. SysVar:" . $SysVar, E_USER_NOTICE);
            return false;
        }
        $ScriptData = array();
        $ScriptData['SENDER'] = 'AlarmDP';
        $ScriptData['VARIABLE'] = $ParentID;
        $ScriptData['OLDVALUE'] = GetValueBoolean($ParentID);
        $ScriptData['VALUE'] = (string) $xmlData->Value == 'true';
        if ($ScriptData['VALUE'])
        {
            $Time = new DateTime((string) $xmlData->LastTime . $CCUTimeZone);
            $ScriptData['LastTime'] = $Time->getTimestamp();
            $Time = new DateTime((string) $xmlData->FirstTime . $CCUTimeZone);
            $ScriptData['FirstTime'] = $Time->getTimestamp();
            $ScriptData['Room'] = (string) $xmlData->Room;
            $ScriptData['ChannelName'] = (string) $xmlData->ChannelName;
            $Channel = explode('.', (string) $xmlData->oLastTrigger);
            if (count($Channel) >= 2)
            {
                $ScriptData['Channel'] = $Channel[1];
                $ScriptData['DP'] = $Channel[2];
            }
            else
            {
                $ScriptData['Channel'] = 'unbekannt';
                $ScriptData['DP'] = 'unbekannt';
            }
        }
        else
        {
            $ScriptData['LastTime'] = 0;
            $ScriptData['FirstTime'] = 0;
            $ScriptData['Room'] = '';
            $ScriptData['ChannelName'] = '';
            $ScriptData['Channel'] = '';
            $ScriptData['DP'] = '';
        }

        SetValueBoolean($ParentID, $ScriptData['VALUE']);
        $LastTimeID = $this->RegisterSubVariable($ParentID, 'LastTime', 'Letzter Alarm', vtInteger, '~UnixTimestamp');
        SetValueInteger($LastTimeID, $ScriptData['LastTime']);
        $FirstTimeID = $this->RegisterSubVariable($ParentID, 'FirstTime', 'Erster Alarm', vtInteger, '~UnixTimestamp');
        SetValueInteger($FirstTimeID, $ScriptData['FirstTime']);
        $RoomID = $this->RegisterSubVariable($ParentID, 'Room', 'Raum', vtString);
        SetValueString($RoomID, $ScriptData['Room']);
        $ChannelNameID = $this->RegisterSubVariable($ParentID, 'ChannelName', 'Name', vtString);
        SetValueString($ChannelNameID, $ScriptData['ChannelName']);

        $ScriptID = $this->ReadPropertyString('AlarmScriptID');
        if ($ScriptID > 0)
        {
            IPS_RunScriptEx($ScriptID, $ScriptData);
        }
    }

    private function WriteSysVar($Parameter, $ValueStr)
    {
        if ($this->fKernelRunlevel <> KR_READY)
            return false;
        if (!$this->HasActiveParent())
            return false;
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;
        $url = 'SysVar.exe';
        $HMScript = 'State=dom.GetObject(' . $Parameter . ').State("' . $ValueStr . '");';
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        }
        catch (Exception $exc)
        {
            throw new Exception("Error on write CCU Systemvariable.", E_USER_NOTICE);
        }
        try
        {
            $xml = new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            throw new Exception('HM-Script result is not wellformed', E_USER_NOTICE);
        }
        if ((string) $xml->State == 'true')
            return true;
        else
            return false;
    }

    private function RegisterSubVariable($ParentID, $Ident, $Name, $Type, $Profile = "", $Position = 0)
    {

        if ($Profile != "")
        {
            //prefer system profiles
            if (IPS_VariableProfileExists("~" . $Profile))
            {
                $Profile = "~" . $Profile;
            }
            if (!IPS_VariableProfileExists($Profile))
            {
                throw new Exception("Profile with name " . $Profile . " does not exist");
            }
        }

        //search for already available variables with proper ident
        $vid = @IPS_GetObjectIDByIdent($Ident, $ParentID);

        //properly update variableID
        if ($vid === false)
            $vid = 0;

        //we have a variable with the proper ident. check if it fits
        if ($vid > 0)
        {
            //check if we really have a variable
            if (!IPS_VariableExists($vid))
                throw new Exception("Ident with name " . $Ident . " is used for wrong object type"); //bail out
//check for type mismatch
            if (IPS_GetVariable($vid)["VariableType"] != $Type)
            {
                //mismatch detected. delete this one. we will create a new below
                IPS_DeleteVariable($vid);
                //this will ensure, that a new one is created
                $vid = 0;
            }
        }

        //we need to create one
        if ($vid == 0)
        {
            $vid = IPS_CreateVariable($Type);

            //configure it
            IPS_SetParent($vid, $ParentID);
            IPS_SetIdent($vid, $Ident);
            IPS_SetName($vid, $Name);
            IPS_SetPosition($vid, $Position);
            //IPS_SetReadOnly($vid, true);
        }

        //update variable profile. profiles may be changed in module development.
        //this update does not affect any custom profile choices
        IPS_SetVariableCustomProfile($vid, $Profile);

        return $vid;
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        if (!$this->HasActiveParent())
        {
            trigger_error('Instance has no active Parent Instance!', E_USER_NOTICE);
            return false;
        }
        if (strpos($Ident, 'AlDP') !== false)
        {
            if ((bool) $Value === false)
                $this->AlarmReceipt($Ident);
            return true;
        }
        $VarID = $this->GetStatusVarIDex($Ident);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Ident . ' do not exist.', E_USER_NOTICE);
            return false;
        }
        switch (IPS_GetVariable($VarID)['VariableType'])
        {
            case vtBoolean:
                $this->WriteValueBoolean($Ident, (bool) $Value);
                break;
            case vtInteger:
                $this->WriteValueInteger($Ident, (int) $Value);
                break;
            case vtFloat:
                $this->WriteValueFloat($Ident, (float) $Value);
                break;
            case vtString:
                $this->WriteValueString($Ident, (string) $Value);
                break;
        }
    }

    private function GetStatusVarIDex($Ident)
    {
        $VarID = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        return $VarID;
    }

################## PUBLIC    
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function AlarmReceipt(string $Ident)
    {
        if ($this->fKernelRunlevel <> KR_READY)
            return false;
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;
        $VarID = $this->GetStatusVarIDex($Ident);

        $HMScript = 'object oitemID = dom.GetObject(' . substr($Ident, 4) . ');
                   if (oitemID.AlState() == asOncoming )
                   {
                    var State = oitemID.AlReceipt();
                   }';
        try
        {
            $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }
        $xmlData = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
        if ($xmlData === false)
        {
            trigger_error("HM-Script result is not wellformed. SysVar:" . $Ident, E_USER_NOTICE);
            return false;
        }
        if ((int) $xmlData->State == 1)
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueBoolean($VarID, false);
            return true;
        }

        trigger_error('Error on receipt alarm of ' . $VarID, E_USER_NOTICE);
        return false;
    }

    public function ReadSystemVariables()
    {
        if (!$this->HasActiveParent())
            return false;

        $this->GetParentData();
        try
        {
            return $this->ReadSysVars();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
    }

    public function WriteValueBoolean(string $Parameter, bool $Value)
    {
        return $this->WriteValueBoolean2($Parameter, $Value);
    }

    public function WriteValueBoolean2(string $Parameter, bool $Value)
    {
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtBoolean)
        {
            trigger_error('Wrong Datatype for ' . $VarID, E_USER_NOTICE);
            return false;
        }

        if ($Value)
            $ValueStr = 'true';
        else
            $ValueStr = 'false';

        try
        {
            $Result = $this->WriteSysVar($Parameter, $ValueStr);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }

        if ($Result === true)
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueBoolean($VarID, $Value);
            return true;
        }

        trigger_error('Error on write Data ' . $VarID, E_USER_NOTICE);
        return false;
    }

    public function WriteValueInteger(string $Parameter, int $Value)
    {
        return $this->WriteValueInteger2($Parameter, $Value);
    }

    public function WriteValueInteger2(string $Parameter, int $Value)
    {
        $VarID = $this->GetStatusVarIDex($Parameter);

        if (IPS_GetVariable($VarID)['VariableType'] <> vtInteger)
        {
            trigger_error('Wrong Datatype for ' . $VarID, E_USER_NOTICE);
            return false;
        }

        try
        {
            $Result = $this->WriteSysVar($Parameter, (string) $Value);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
        if ($Result === true)
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueInteger($VarID, $Value);
            return true;
        }
        trigger_error('Error on write Data ' . $VarID, E_USER_NOTICE);
        return false;
    }

    public function WriteValueFloat(string $Parameter, float $Value)
    {
        return $this->WriteValueFloat2($Parameter, $Value);
    }

    public function WriteValueFloat2(string $Parameter, float $Value)
    {
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtFloat)
        {
            trigger_error('Wrong Datatype for ' . $VarID, E_USER_NOTICE);
            return false;
        }

        try
        {
            $Result = $this->WriteSysVar($Parameter, (string) $Value);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }

        if ($Result === true)
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueFloat($VarID, $Value);
            return true;
        }

        trigger_error('Error on write Data ' . $VarID, E_USER_NOTICE);
        return false;
    }

    public function WriteValueString(string $Parameter, string $Value)
    {
        return $this->WriteValueString2($Parameter, $Value);
    }

    public function WriteValueString2(string $Parameter, string $Value)
    {
        $VarID = $this->GetStatusVarIDex($Parameter);
        if (IPS_GetVariable($VarID)['VariableType'] <> vtString)
        {
            trigger_error('Wrong Datatype for ' . $VarID, E_USER_NOTICE);
            return false;
        }
        try
        {
            $Result = $this->WriteSysVar($Parameter, (string) $Value);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }

        if ($Result === true)
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueString($VarID, $Value);
            return true;
        }

        trigger_error('Error on write Data ' . $VarID, E_USER_NOTICE);
        return false;
    }

}

?>
