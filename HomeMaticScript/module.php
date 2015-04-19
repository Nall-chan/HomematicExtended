<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMScript extends HMBase
{

    public function __construct($InstanceID)
    {
//Never delete this line!
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        parent::__construct($InstanceID);

        ////These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
    }

    public function ApplyChanges()
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//Never delete this line!
        parent::ApplyChanges();
        $this->RegisterPropertyInteger("Protocol", 0);
                $self = "XXX9".(string)$this->InstanceID.":5";
        $this->RegisterPropertyString("Address",$self);      
        $this->RegisterPropertyBoolean("EmulateStatus", false);        
    }

    private function SendScript($Script)
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        $url = 'Script.exe';
        $HMScriptResult = $this->LoadHMScript($url, $Script);
        if ($HMScriptResult === false)
            throw new Exception("Error on write CCU-Script");
        try
        {
            $xml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR,'HM-Script result is not wellformed');
            throw new Exception("Error on write CCU-Script");
        }
        unset($xml->exec);
        unset($xml->sessionId);
        unset($xml->httpUserAgent);
        return json_encode($xml);
    }

    protected function GetParentData()
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        parent::GetParentData();
        $this->SetSummary($this->HMAddress);
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function RunScript($Script)
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        return $this->SendScript($Script);
    }

}

?>