<?

/**
 * @addtogroup homematicextended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.2
 */
require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

/**
 * HMSystemVariable ist die Klasse für das IPS-Modul 'HomeMatic Systemvariablen'.
 * Erweitert HMBase 
 *
 * @property string $HMDeviceAddress Die Geräte-Adresse welche eine Aktualisierung auslöst.
 * @property string $HMDeviceDatapoint Der zu überwachende Datenpunkt welcher eine Aktualisierung auslöst.
 * @property int $Event Die IPS-ID der Variable des Datenpunkt welcher eine Aktualisierung auslöst.
 * @property array $SystemVars Ein Array mit allen IPS-Var-IDs welche den Namen des IDENT (ID der Systemvariable in der CCU) enthalten.
 */
class HMSystemVariable extends HMBase
{

    use Profile;

    static private $CcuVarType = array(2 => vtBoolean, 4 => vtFloat, 16 => vtInteger, 20 => vtString);

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999999');

        $this->RegisterPropertyInteger("EventID", 0);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyBoolean("EnableAlarmDP", true);
        $this->RegisterPropertyInteger("AlarmScriptID", 0);

        $this->RegisterTimer("ReadHMSysVar", 0, 'HM_SystemVariablesTimer($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID))
            return;
        $this->UnregisterProfil("HM.AlReceipt");

        foreach ($this->SystemVars as $Ident)
        {
            $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $this->SystemVars[$SenderID];
            if (IPS_VariableProfileExists($VarProfil))
                IPS_DeleteVariableProfile($VarProfil);
        }

        parent::Destroy();
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @access public
     * @param int $TimeStamp
     * @param int $SenderID
     * @param int $Message
     * @param array|int $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $OldVars = $this->SystemVars;
        if (!IPS_InstanceExists($this->InstanceID))
            return;
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
                if (array_key_exists($SenderID, $OldVars))
                {
                    $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $OldVars[$SenderID];
                    if (IPS_VariableProfileExists($VarProfil))
                        IPS_DeleteVariableProfile($VarProfil);
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterProfileIntegerEx('HM.AlReceipt', "", "", "", Array(
            Array(0, "Quittieren", "", 0x00FF00)
        ));

        if (IPS_GetKernelRunlevel() <> KR_READY)
        {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            $this->SystemVars = array();
            $this->SetReceiveDataFilter(".*9999999999.*");
            $this->SetSummary('');
            return;
        }

        $MyVars = IPS_GetChildrenIDs($this->InstanceID);
        $OldVars = $this->SystemVars;
        foreach ($MyVars as $Var)
        {
            $Object = IPS_GetObject($Var);
            if ($Object["ObjectType"] <> 2)
                continue;
            if (strpos($Object["ObjectIdent"], 'AlDP') !== false)
                $Object["ObjectIdent"] = substr($Object["ObjectIdent"], 4);
            $OldVars[$Var] = $Object["ObjectIdent"];
            $this->RegisterMessage($Var, VM_DELETE);
        }
        $this->SystemVars = $OldVars;

        if ($this->CheckConfig())
        {
            if ($this->ReadPropertyInteger("Interval") >= 5)
                $this->SetTimerInterval("ReadHMSysVar", $this->ReadPropertyInteger("Interval") * 1000);
            else
                $this->SetTimerInterval("ReadHMSysVar", 0);
        }
        else
            $this->SetTimerInterval("ReadHMSysVar", 0);

        if ($this->GetTriggerVar())
            $this->SetReceiveDataFilter(".*" . $this->HMDeviceAddress . ".*" . $this->HMDeviceDatapoint . ".*");
        else
            $this->SetReceiveDataFilter(".*9999999999.*");

        if (!$this->HasActiveParent())
            return;
        try
        {
            $this->ReadSysVars();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
        return;
    }

    ################## protected

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     * 
     * @access protected
     * @return int ID des Parent.
     */
    protected function GetParentData()
    {
        parent::GetParentData();
        $this->SetSummary($this->HMAddress);
    }

################## PRIVATE                

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     * 
     * @access privat
     * @return boolean True wenn Konfig ok, sonst false.
     */
    private function CheckConfig()
    {
        $OldEvent = $this->Event;
        $Event = $this->ReadPropertyInteger("EventID");

        if ($Event <> $OldEvent)
        {
            if ($OldEvent > 0)
                $this->UnregisterMessage($OldEvent, VM_DELETE);
            if ($Event > 0)
                $this->RegisterMessage($Event, VM_DELETE);
            $this->Event = $Event;
        }

        $Interval = $this->ReadPropertyInteger("Interval");

        if ($Interval < 0)
        {
            $this->SetStatus(IS_EBASE + 2); //Error Timer is negativ
            return false;
        }

        if ($Interval == 0)
        {
            if ($Event == 0)
            {
                $this->SetStatus(IS_INACTIVE); // kein Trigger und kein Timer aktiv
                return true;
            }
            else
            {
                $this->SetStatus(IS_ACTIVE); // OK
                return true;
            }
        }

        if ($Interval < 5)
        {
            $this->SetStatus(IS_EBASE + 3);  //Warnung Trigger zu klein                  
            return false;
        }


        $this->SetStatus(IS_ACTIVE); //OK
        return true;
    }

    /**
     * Prüft und holt alle Daten zu der Event-Variable und Instanz.
     * 
     * @access private
     * @return boolean True wenn Quelle gültig ist, sonst false.
     */
    private function GetTriggerVar()
    {
        $EventID = $this->ReadPropertyInteger("EventID");
        if ($EventID == 0)
        {
            $this->HMDeviceAddress = "";
            $this->HMDeviceDatapoint = "";
            return false;
        }
        $parent = IPS_GetParent($EventID);
        if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
        {
            $this->HMDeviceAddress = "";
            $this->HMDeviceDatapoint = "";
            return false;
        }
        $this->HMDeviceAddress = IPS_GetProperty($parent, 'Address');
        $this->HMDeviceDatapoint = IPS_GetObject($EventID)['ObjectIdent'];
        return true;
    }

    /**
     * Liest alle Systemvariablen aus der CCU und legt diese in IPS mit dem dazugehörigen Profil an.
     * @return boolean True bei Erfolg, sonst false.
     * @throws Exception Wenn CUU nicht erreichbar oder Daten nicht auswertbar sind.
     */
    private function ReadSysVars()
    {
        // Sysvars
        $HMScript = 'SysVars=dom.GetObject(ID_SYSTEM_VARIABLES).EnumUsedIDs();';
        try
        {
            $HMScriptResult = $this->LoadHMScript('SysVar.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('ID_SYSTEM_VARIABLES', $exc->getMessage(), 0);
            throw new Exception("Error on Read CCU ID_SYSTEM_VARIABLES", E_USER_NOTICE);
        }
        $this->SendDebug('ID_SYSTEM_VARIABLES', $HMScriptResult, 0);
        $xmlVars = @new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        if ($xmlVars === false)
        {
            $this->SendDebug('ID_SYSTEM_VARIABLES', 'XML error', 0);
            throw new Exception("HM-Script result is not wellformed", E_USER_NOTICE);
        }

        //Time & Timezone
        $HMScript = 'Now=system.Date("%F %T%z");' . PHP_EOL
                . 'TimeZone=system.Date("%z");' . PHP_EOL;
        try
        {
            $HMScriptResult = $this->LoadHMScript('Time.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('Time', $exc->getMessage(), 0);
            throw new Exception("Error on Read CCU Time", E_USER_NOTICE);
        }
        $this->SendDebug('Time', $HMScriptResult, 0);

        $xmlTime = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        if ($xmlTime === false)
        {
            $this->SendDebug('Time', 'XML error', 0);
            throw new Exception("HM-Script result is not wellformed", E_USER_NOTICE);
        }
        $Date = new DateTime((string) $xmlTime->Now);
        $CCUTime = $Date->getTimestamp();
        $Date = new DateTime();
        $NowTime = $Date->getTimestamp();
        $TimeDiff = $NowTime - $CCUTime;
        $CCUTimeZone = (string) $xmlTime->TimeZone;
        $Result = true;
        $OldVars = $this->SystemVars;
        $OldVarsChange = false;
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
                $this->SendDebug($SysVar, $exc->getMessage(), 0);
                trigger_error($exc->getMessage(), E_USER_NOTICE);
                $Result = false;
                continue;
            }
            $this->SendDebug($SysVar, $HMScriptResult, 0);

            $lines = explode("\r\n", $HMScriptResult);
            $xmlVar = @new SimpleXMLElement(utf8_encode(array_pop($lines)), LIBXML_NONET);
            if ($xmlVar === false)
            {
                $this->SendDebug($SysVar, $exc->getMessage(), 0);
                trigger_error("HM-Script result is not wellformed. SysVar:" . $SysVar, E_USER_NOTICE);
                $Result = false;
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
            $VarID = @$this->GetIDForIdent($VarIdent);
            $VarType = self::$CcuVarType[(int) $xmlVar->ValueType];
            $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $SysVar;
            $VarName = /* utf8_decode( */(string) $xmlVar->Name;

            if (((int) $xmlVar->ValueType != vtString) and ( !IPS_VariableProfileExists($VarProfil)))
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
                    $this->SendDebug($SysVar, $exc->getMessage(), 0);
                    trigger_error($exc->getMessage(), E_USER_NOTICE);
                    $Result = false;
                    continue;
                }
                $this->SendDebug($SysVar, $HMScriptResult, 0);

                $xmlVar2 = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
                if ($xmlVar2 === false)
                {
                    $this->SendDebug($SysVar, 'XML error', 0);

                    trigger_error("HM-Script result is not wellformed. SysVar:" . $SysVar, E_USER_NOTICE);
                    $Result = false;
                    continue;
                }
                if (IPS_VariableProfileExists($VarProfil))
                    IPS_DeleteVariableProfile($VarProfil);

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
            if ($VarID === false)
            {
                if ((int) $xmlVar->ValueType == vtString)
                    $VarProfil = "~String";
                $this->MaintainVariable($VarIdent, $VarName, $VarType, $VarProfil, 0, true);
                $this->EnableAction($VarIdent);
                $VarID = @$this->GetIDForIdent($VarIdent);
                if ((int) $xmlVar->ValueType <> vtString)
                {
                    $OldVars[$VarID] = $SysVar;
                    $OldVarsChange = true;
                    $this->RegisterMessage($VarID, VM_DELETE);
                    $this->SendDebug($VarID, IPS_GetObject($VarID), 0);
                }
            }
            else
            {
                if (IPS_GetName($VarID) <> $VarName)
                    IPS_SetName($VarID, $VarName);
            }
            if (IPS_GetVariable($VarID)['VariableType'] <> $VarType)
            {
                $this->SendDebug($SysVar, 'Type of CCU Systemvariable ' . IPS_GetName($VarID) . ' has changed.', 0);
                trigger_error('Type of CCU Systemvariable ' . IPS_GetName($VarID) . ' has changed.', E_USER_NOTICE);
                $Result = false;
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
        if ($OldVarsChange)
            $this->SystemVars = $OldVars;
        return $Result;
    }

    /**
     * Liest die Daten einer Systemvariable vom Typ 'Alarm' aus. Visualisiert den Status und startet bei Bedarf ein Script in IPS.
     * 
     * @param int $ParentID IPS-ID der Alarmvariable.
     * @param string $SysVar ID der Alarmvariable in der CCU.
     * @param string $CCUTimeZone Die Zeitzone der CCU.
     * @return boolean True bei Erfolg, sonst false.
     */
    private function ProcessAlarmVariable(int $ParentID, string $SysVar, string $CCUTimeZone)
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
            $HMScriptResult = $this->LoadHMScript('AlarmVar.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('AlarmVar.exe', $exc->getMessage(), 0);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }
        $xmlData = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
        if ($xmlData === false)
        {
            $this->SendDebug('AlarmVar.' . $SysVar, 'XML error', 0);

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
        return true;
    }

    /**
     * Schreibt einen Wert in eine Systemvariable auf der CCU.
     * 
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Systemvariable in der CCU.
     * @param string $ValueStr Der neue Wert der Systemvariable.
     * @return boolean True bei Erfolg, sonst false.
     * @throws Exception Wenn CUU nicht erreichbar oder Daten nicht auswertbar sind.
     */
    private function WriteSysVar(string $Parameter, string $ValueStr)
    {
        if (IPS_GetKernelRunlevel() <> KR_READY)
            return false;
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!", E_USER_NOTICE);
        $url = 'SysVar.exe';
        $HMScript = 'State=dom.GetObject(' . $Parameter . ').State("' . $ValueStr . '");';
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('SysVar.exe', $exc->getMessage(), 0);
            throw new Exception("Error on write CCU Systemvariable.", E_USER_NOTICE);
        }

        $xml = @new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);

        if ($xml === false)
        {
            $this->SendDebug('SysVar.exe', 'XML error', 0);
            throw new Exception('HM-Script result is not wellformed', E_USER_NOTICE);
        }
        if ((string) $xml->State == 'true')
            return true;
        else
            return false;
    }

    /**
     * Erstellt eine Untervariable in IPS.
     * 
     * @param int $ParentID IPS-ID der übergeordneten Variable.
     * @param string $Ident IDENT der neuen Statusvariable.
     * @param string $Name Name der neuen Statusvariable.
     * @param int $Type Der zu erstellende Typ von Variable.
     * @param string $Profile Das dazugehörige Variabelprofil.
     * @param int $Position Position der Variable.
     * @return int IPS-ID der neuen Variable.
     * @throws Exception Wenn Variable nicht erstellt werden konnte.
     */
    private function RegisterSubVariable($ParentID, $Ident, $Name, $Type, $Profile = "", $Position = 0)
    {

        if ($Profile != "")
        {
            if (IPS_VariableProfileExists("~" . $Profile))
            {
                $Profile = "~" . $Profile;
            }
            if (!IPS_VariableProfileExists($Profile))
            {
                throw new Exception("Profile with name " . $Profile . " does not exist");
            }
        }

        $vid = @IPS_GetObjectIDByIdent($Ident, $ParentID);

        if ($vid === false)
            $vid = 0;

        if ($vid > 0)
        {
            if (!IPS_VariableExists($vid))
                throw new Exception("Ident with name " . $Ident . " is used for wrong object type"); //bail out
            if (IPS_GetVariable($vid)["VariableType"] != $Type)
            {
                IPS_DeleteVariable($vid);
                $vid = 0;
            }
        }

        if ($vid == 0)
        {
            $vid = IPS_CreateVariable($Type);

            IPS_SetParent($vid, $ParentID);
            IPS_SetIdent($vid, $Ident);
            IPS_SetName($vid, $Name);
            IPS_SetPosition($vid, $Position);
            //IPS_SetReadOnly($vid, true);
        }

        IPS_SetVariableCustomProfile($vid, $Profile);

        return $vid;
    }

################## ActionHandler

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function RequestAction($Ident, $Value)
    {
        if (!$this->HasActiveParent())
        {
            trigger_error('Instance has no active Parent Instance!', E_USER_NOTICE);
            return;
        }
        if (strpos($Ident, 'AlDP') !== false)
        {
            if ((bool) $Value === false)
                $this->AlarmReceipt($Ident);
            return;
        }
        $VarID = @$this->GetIDForIdent($Ident);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Ident . ' do not exist.', E_USER_NOTICE);
            return;
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

################## Datenaustausch

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ReceiveData($JSONString)
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

################## PUBLIC    

    /**
     * IPS-Instanz-Funktion 'HM_AlarmReceipt'.
     * Bestätigt einen Alarm auf der CCU
     * 
     * @param string $Ident Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @return boolean True bei erfolg, sonst false.
     */
    public function AlarmReceipt(string $Ident)
    {
        if (IPS_GetKernelRunlevel() <> KR_READY)
            return false;
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $VarID = @$this->GetIDForIdent($Ident);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Ident . ' do not exist.', E_USER_NOTICE);
            return false;
        }
        $HMScript = 'object oitemID = dom.GetObject(' . substr($Ident, 4) . ');
                   if (oitemID.AlState() == asOncoming )
                   {
                    var State = oitemID.AlReceipt();
                   }';
        try
        {
            $HMScriptResult = $this->LoadHMScript('AlarmVar.exe', $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('AlarmVar.exe', $exc->getMessage(), 0);
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }
        $xmlData = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NONET);
        if ($xmlData === false)
        {
            $this->SendDebug('AlarmVar.' . $Ident, 'XML error', 0);
            trigger_error("HM-Script result is not wellformed. SysVar:" . $Ident, E_USER_NOTICE);
            return false;
        }
        if ((int) $xmlData->State == 1)
        {
            if ($this->ReadPropertyBoolean('EmulateStatus') === true)
                SetValueBoolean($VarID, false);
            return true;
        }

        $this->SendDebug('AlarmVar.' . $Ident, 'error on receipt', 0);
        trigger_error('Error on receipt alarm of ' . $VarID, E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'HM_SystemVariablesTimer'.
     * Wird durch den Timer ausgeführt und liest alle Systemvariablen von der CCU.
     * 
     * @access public
     */
    public function SystemVariablesTimer()
    {
        if (!$this->HasActiveParent())
            return;
        try
        {
            $this->ReadSysVars();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
    }

    /**
     * IPS-Instanz-Funktion 'HM_ReadSystemVariables'.
     * Liest alle Systemvariablen von der CCU.
     * 
     * @access public
     * @return boolean True bei Erfolg, sonst false.
     */
    public function ReadSystemVariables()
    {
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        try
        {
            return $this->ReadSysVars();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean'.
     * Schreibt einen bool-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param bool $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueBoolean(string $Parameter, bool $Value)
    {
        return $this->WriteValueBoolean2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean2'.
     * Schreibt einen bool-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param bool $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueBoolean2(string $Parameter, bool $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Parameter . ' do not exist.', E_USER_NOTICE);
            return false;
        }

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

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger'.
     * Schreibt einen integer-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param int $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueInteger(string $Parameter, int $Value)
    {
        return $this->WriteValueInteger2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger2'.
     * Schreibt einen integer-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param int $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueInteger2(string $Parameter, int $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Parameter . ' do not exist.', E_USER_NOTICE);
            return false;
        }


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

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat'.
     * Schreibt einen float-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param float $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueFloat(string $Parameter, float $Value)
    {
        return $this->WriteValueFloat2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat2'.
     * Schreibt einen float-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param float $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueFloat2(string $Parameter, float $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Parameter . ' do not exist.', E_USER_NOTICE);
            return false;
        }

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

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString'.
     * Schreibt einen string-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param string $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueString(string $Parameter, string $Value)
    {
        return $this->WriteValueString2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString2'.
     * Schreibt einen string-Wert in eine Systemvariable der CCU.
     * 
     * @access public
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param string $Value Der zu schreibende Wert.
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueString2(string $Parameter, string $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false)
        {
            trigger_error('Ident ' . $Parameter . ' do not exist.', E_USER_NOTICE);
            return false;
        }

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

/** @} */
