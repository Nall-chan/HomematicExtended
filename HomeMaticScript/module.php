<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMScript extends HMBase
{

    public function __construct($InstanceID)
    {
//Never delete this line!
        parent::__construct($InstanceID);

//These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
    }

    public function ProcessKernelRunlevelChange($Runlevel)
    {
        if ($Runlevel == KR_READY)
        {
            $this->GetParentData();
        }
    }

    public function ProcessInstanceStatusChange($InstanceID, $Status)
    {
        if ($this->fKernelRunlevel == KR_READY)
        {
            if (($InstanceID == @IPS_GetInstanceParentID($this->InstanceID)) or ( $InstanceID == 0))
            {
                $this->GetParentData();
            }
        }
        parent::ProcessInstanceStatusChange($InstanceID, $Status);
    }

    public function MessageSink($Msg)
    {

        if ($Msg['SenderID'] <> 0)
        {
            if ($Msg['Message'] == DM_CONNECT)
            {
                if (!$this->HasActiveParent())
                {
                    IPS_Sleep(250);
                    if (!$this->HasActiveParent())
                        return;
                }
                if (($Msg['SenderID'] == $this->InstanceID) or ( $Msg['SenderID'] == IPS_GetInstanceParentID($this->InstanceID)))
                    $this->GetParentData();
            } elseif ($Msg['Message'] == DM_DISCONNECT)
            {
                if (($Msg['SenderID'] == $this->InstanceID) or ( $Msg['SenderID'] == IPS_GetInstanceParentID($this->InstanceID)))
                {
                    $this->SetSummary('No parent');
                }
            }
        }
    }

    public function ApplyChanges()
    {
//Never delete this line!
        parent::ApplyChanges();
        if ($this->fKernelRunlevel == KR_INIT)
        {
            $this->GetParentData();
        }
    }

    private function SendScript($Script)
    {
        if ($this->fKernelRunlevel <> KR_READY)
            return;
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        $url = 'Script.exe';
        $HMScriptResult = $this->LoadHMScript($url, $Script);
        if ($HMScriptResult===false)
            throw new Exception("Error on write CCU-Script");        
        $xml = new SimpleXMLElement($HMScriptResult);
        if ($xml === false)
        {
            $this->LogMessage('HM-Script result is not wellformed');
            throw new Exception("Error on write CCU-Script");
        }
        unset($xml->exec);
        unset($xml->sessionId);        
        unset($xml->httpUserAgent);                
        return json_encode($xml); 
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function RunScript($Script)
    {
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!");
        else
            return $this->SendScript($Script);
    }

}

?>