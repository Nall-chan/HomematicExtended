<?

require_once(__DIR__ . "/../HMBase.php");

class HMScript extends HMBase
{

    use DebugHelper;

    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999993');
        $this->RegisterPropertyBoolean("EmulateStatus", false);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    protected function KernelReady()
    {
        
    }

    protected function ForceRefresh()
    {
        
    }

    protected function GetParentData()
    {
        parent::GetParentData();
        $this->SetSummary($this->HMAddress);
    }

    private function SendScript($Script)
    {
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
        }
        catch (Exception $exc)
        {
            $this->SendDebug($url, $exc->getMessage(), 0);
            throw $exc;
        }
        $this->SendDebug('Result', $HMScriptResult, 0);
        $xml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
        if ($xml === false)
        {
            $this->SendDebug($url, 'XML error', 0);
            throw new Exception("HM-Script result is not wellformed", E_USER_NOTICE);
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

    public function RunScript(string $Script)
    {
        try
        {
            return $this->SendScript($Script);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

}

?>