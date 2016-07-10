<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMDisWM55 extends HMBase
{

    const PageUP = 0;
    const PageDown = 1;
    const ActionUp = 2;
    const ActionDown = 3;

    private static $PropertysName = array(
        "PageUpID",
        "PageDownID",
        "ActionUpID",
        "ActionDownID");
    private $HMEventData = array();

    public function Create()
    {
//Never delete this line!
        parent::Create();

        $this->RegisterHMPropertys('XXX9999995');
        $this->RegisterPropertyBoolean("EmulateStatus", false);

        $this->RegisterPropertyInteger("PageUpID", 0);
        $this->RegisterPropertyInteger("PageDownID", 0);
        $this->RegisterPropertyInteger("ActionUpID", 0);
        $this->RegisterPropertyInteger("ActionDownID", 0);
        $this->RegisterPropertyInteger("MaxPage", 1);
        $this->RegisterPropertyInteger("Timeout", 0);
        $this->RegisterPropertyInteger("ScriptID", 0);

        $this->RegisterVariableInteger('PAGE', 'PAGE');
        $this->RegisterTimer('DisplayTimeout', 0, 'HM_ResetTimer($_IPS[\'TARGET\']);');
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message)
        {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                foreach (self::$PropertysName as $Name)
                {
                    if ($SenderID == $this->ReadPropertyInteger($Name))
                    {
                        IPS_SetProperty($this->InstanceID, $Name, 0);
                        IPS_ApplyChanges($this->InstanceID);
                    }
                }
                break;
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (($this->CheckConfig()) and ( $this->GetDisplayAddress()))
        {
            $Lines = array();
            foreach ($this->HMEventData as $Trigger)
            {
                $Lines[] = '.*"DeviceID":"' . $Trigger['DeviceID'] . '","VariableName":"' . $Trigger['VariableName'] . '".*';
            }
            $Line = implode('|', $Lines);
            $this->SetReceiveDataFilter("(" . $Line . ")");
            $this->SetSummary($this->HMEventData[0]['DeviceID']);
        }
        else
        {
            $this->SetSummary('');
            $this->SetReceiveDataFilter(".*9999999999.*");
        }
    }

    protected function KernelReady()
    {
        
    }

    protected function ForceRefresh()
    {
        
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('Receive', $JSONString, 0);

        if (!$this->GetDisplayAddress())
            return;
        $Data = json_decode($JSONString);
        $ReceiveData = array("DeviceID" => (string) $Data->DeviceID, "VariableName" => (string) $Data->VariableName);
        $Action = array_search($ReceiveData, $this->HMEventData);
        if ($Action === false)
            return;
        try
        {
            $this->RunDisplayScript($Action);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
        $Events = array();
        $Result = true;

        foreach (self::$PropertysName as $Name)
        {
            $Event = $this->ReadPropertyInteger($Name);
            $OldEvent = $this->GetBuffer($Name);
            if ($Event <> $OldEvent)
            {
                if ($OldEvent > 0)
                    $this->UnregisterMessage($OldEvent, VM_DELETE);

                if ($Event > 0)
                    if (in_array($Event, $Events))
                    {
                        $this->SetStatus(IS_EBASE + 2);
                        $Result = false;
                    }
                    else
                    {
                        $Events[] = $Event;
                        $this->RegisterMessage($Event, VM_DELETE);
                        $this->SetBuffer('Event', $Event);
                    }
            }
        }

        if (count($Events) == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            $Result = false;
        }

        if ($Result)
            if ($this->ReadPropertyInteger('ScriptID') == 0)
            {
                $this->SetStatus(IS_EBASE + 3);
                $Result = false;
            }

        if ($Result)
            if ($this->ReadPropertyInteger('Timeout') < 0)
            {
                $this->SetStatus(IS_EBASE + 4);
                $Result = false;
            }

        if ($Result)
            if ($this->ReadPropertyInteger('MaxPage') < 0)
            {
                $this->SetStatus(IS_EBASE + 5);
                $Result = false;
            }

        return $Result;
    }

    private function GetDisplayAddress()
    {
        foreach (self::$PropertysName as $Name)
        {
            $EventID = $this->ReadPropertyInteger((string) $Name);
            if ($EventID <> 0)
            {
                $parent = IPS_GetParent($EventID);
                $this->HMEventData[$Name] = array(
                    "DeviceID" => IPS_GetProperty($parent, 'Address'),
                    "VariableName" => IPS_GetObject($EventID)['ObjectIdent']
                );
            }
        }
        if (sizeof($this->HMEventData) > 0)
            return true;
        else
            return false;
    }

    private function RunDisplayScript($Action)
    {
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_WARNING);
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_WARNING);
        }
        $Page = GetValueInteger($this->GetIDForIdent('PAGE'));
        $MaxPage = $this->ReadPropertyInteger('MaxPage');
        switch ($Action)
        {
            case "PageUpID":
                if ($Page == $MaxPage)
                    $Page = 1;
                else
                    $Page++;
                $ActionString = "UP";
                SetValueInteger($this->GetIDForIdent('PAGE'), $Page);

                break;
            case "PageDownID":
                if ($Page <= 1)
                    $Page = $MaxPage;
                else
                    $Page--;
                $ActionString = "DOWN";
                SetValueInteger($this->GetIDForIdent('PAGE'), $Page);

                break;
            case "ActionUpID":
                $ActionString = "ActionUP";
                break;
            case "ActionDownID":
                $ActionString = "ActionDOWN";
                break;
        }
// PHP-Script ausführen
        $ScriptID = $this->ReadPropertyInteger('ScriptID');
        if ($ScriptID <> 0)
        {
            $Result = IPS_RunScriptWaitEx($ScriptID, array('SENDER' => 'HMDisWM55', 'ACTION' => $ActionString, 'PAGE' => $Page, 'EVENT' => $this->InstanceID));
//IPS_LogMessage(__CLASS__, __FUNCTION__ . 'ScriptResult:' . $Result); //                    
//Weiter geht es ab hier mit 
            $Data = $this->ConvertDisplayData(json_decode($Result));
            if ($Data === false)
            {
                throw new Exception("Error in Display Script.", E_USER_NOTICE);
                return;
            }
            $url = 'GetDisplay.exe';
            $HMScript = 'string DisplayKeySubmit;' . PHP_EOL;
            $HMScript.='DisplayKeySubmit=dom.GetObject("BidCos-RF.' . (string) $this->HMEventData[$Action]['DeviceID'] . '.SUBMIT").ID();' . PHP_EOL;
            $HMScript .= 'State=dom.GetObject(DisplayKeySubmit).State("' . $Data . '");' . PHP_EOL;
            try
            {
                $this->LoadHMScript($url, $HMScript);
            }
            catch (Exception $exc)
            {
                throw new Exception('Error on send Data to HM-Dis-WM55.', E_USER_NOTICE);
            }


            /*            if ($HMScriptResult == '')
              throw new Exception('Error on send Data to HM-Dis-WM55.');
              try
              {
              $xml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
              $State = (string) $xml->State;
              }
              catch (Exception $ex)
              {
              $this->LogMessage(KL_ERROR, 'Error on send Data to HM-Dis-WM55.');
              throw new Exception('Error on send Data to HM-Dis-WM55.');
              }
              IPS_LogMessage(__CLASS__, "Value:" . $State); */
        }
        $Timeout = $this->ReadPropertyInteger('Timeout');
        if ($Timeout > 0)
        {
            $this->SetTimerInterval('DisplayTimeout', $Timeout * 1000);
        }
    }

    private function ConvertDisplayData($Data)
    {
        if ($Data === null)
        {
            return false;
        }
        //IPS_LogMessage(__CLASS__, "Data:" . print_r($Data, true));
        $SendData = "0x02";
        foreach ($Data as $Line)
        {
            if ((string) $Line->Text <> "")
            {
                $SendData.=",0x12";
                for ($i = 0; $i < strlen((string) $Line->Text); $i++)
                {
                    $SendData .= ",0x" . dechex(ord((string) $Line->Text[$i]));
                }
                $SendData.=",0x11";
                $SendData .= ",0x" . dechex((int) $Line->Color);
            }
            if ((int) $Line->Icon <> 0)
            {
                $SendData.=",0x13";
                $SendData .= ",0x" . dechex((int) $Line->Icon);
            }
            $SendData.=",0x0A";
        }
        $SendData.=",0x03";
//        IPS_LogMessage(__CLASS__, "Data:" . $SendData);
        return $SendData;
    }

    public function ResetTimer()
    {
        SetValueInteger($this->GetIDForIdent('PAGE'), 0);
        $this->SetTimerInterval('DisplayTimeout', 0);
    }

}

?>