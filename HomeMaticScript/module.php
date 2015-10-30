<?

require_once(__DIR__ . "/../HMBase.php");

class HMScript extends HMBase
{

    public function Create()
    {
//Never delete this line!
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        parent::Create();
        $this->RegisterPropertyInteger("Protocol", 0);
        $this->RegisterPropertyString("Address", "XXX9999999:3");
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        ////These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
    }

    public function ApplyChanges()
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//Never delete this line!
        parent::ApplyChanges();
    }

    private function SendScript($Script)
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_NOTICE);
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_NOTICE);
        }
        $url = 'Script.exe';
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $Script);
        } catch (Exception $exc)
        {
            throw $exc;
        }
        try
        {
            $xml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        } catch (Exception $ex)
        {
//            $this->LogMessage(KL_ERROR, 'HM-Script result is not wellformed');
            throw new Exception("HM-Script result is not wellformed", E_USER_NOTICE);
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

    public function RunScript(string $Script)
    {
        try
        {
            return $this->SendScript($Script);
        } catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
    }

}

?>