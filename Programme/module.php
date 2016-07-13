<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMCCUProgram extends HMBase
{

    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999998');
        $this->RegisterPropertyBoolean("EmulateStatus", false);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->CreateProfil();
        if (IPS_GetKernelRunlevel() <> KR_READY)
            return;        
        $this->GetParentData();

        if ($this->HMAddress == '')
            return;

        if ($this->HasActiveParent())
        {
            try
            {
                $this->ReadCCUPrograms();
            }
            catch (Exception $exc)
            {
                trigger_error($exc->getMessage(), $exc->getCode());
            }
        }
    }

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    protected function GetParentData()
    {
        parent::GetParentData();
        $this->SetSummary($this->HMAddress);
    }

    private function CreateProfil()
    {
        if (!IPS_VariableProfileExists('Execute.HM'))
        {
            IPS_CreateVariableProfile('Execute.HM', 1);
            IPS_SetVariableProfileAssociation('Execute.HM', 0, 'Start', '', -1);
        }
    }

    private function ReadCCUPrograms()
    {
        $url = 'SysPrg.exe';
        $HMScript = 'SysPrgs=dom.GetObject(ID_PROGRAMS).EnumUsedIDs();';
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('SysPrg', $exc->getMessage(), 0);
            throw new Exception("Error on Read CCU-Programs", E_USER_NOTICE);
        }

        $xml = @new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        if ($xml === false)
        {
            $this->SendDebug('SysPrg', 'XML error', 0);
            throw new Exception("Error on Read CCU-Programs", E_USER_NOTICE);
        }

        $Result = true;
        foreach (explode(chr(0x09), (string) $xml->SysPrgs) as $SysPrg)
        {
            $HMScript = 'Name=dom.GetObject(' . $SysPrg . ').Name();' . PHP_EOL
                    . 'Info=dom.GetObject(' . $SysPrg . ').PrgInfo();' . PHP_EOL;
            try
            {
                $HMScriptResult = $this->LoadHMScript($url, $HMScript);
            }
            catch (Exception $exc)
            {
                $Result = false;
                $this->SendDebug($SysPrg, $exc->getMessage(), 0);
                trigger_error("Error on read info of CCU-Program " . $SysPrg, E_USER_NOTICE);
                continue;
            }

            $varXml = @new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
            if ($varXml === false)
            {
                $Result = false;
                $this->SendDebug($SysPrg, 'XML error', 0);
                trigger_error("Error on read info of CCU-Program " . $SysPrg, E_USER_NOTICE);
                continue;
            }

            $var = @IPS_GetObjectIDByIdent($SysPrg, $this->InstanceID);
            $Name = /* utf8_decode( */(string) $varXml->Name;
            $Info = utf8_decode((string) $varXml->Name);
            if ($var === false)
            {
                $this->MaintainVariable($SysPrg, $Name, 1, 'Execute.HM', 0, true);
                $this->EnableAction($SysPrg);
                $var = IPS_GetObjectIDByIdent($SysPrg, $this->InstanceID);
                IPS_SetInfo($var, $Info);
            }
            else
            {
                if (IPS_GetName($var) <> $Name)
                    IPS_SetName($var, $Name);
                if (IPS_GetObject($var)['ObjectInfo'] <> $Info)
                    IPS_SetInfo($var, $Info);
            }
        }
        return $Result;
    }

    private function StartCCUProgram($Ident)
    {
        $var = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($var === false)
            throw new Exception('CCU Program ' . $Ident . ' not found!', E_USER_NOTICE);

        $url = 'SysPrg.exe';
        $HMScript = 'State=dom.GetObject(' . $Ident . ').ProgramExecute();';
        try
        {
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        }
        catch (Exception $exc)
        {
            $this->SendDebug($Ident, $exc->getMessage(), 0);
            throw new Exception("Error on start CCU-Program", E_USER_NOTICE);
        }

        $xml = @new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);

        if ($xml === FALSE)
        {
            $this->SendDebug($Ident, 'XML error', 0);
            throw new Exception("Error on start CCU-Program", E_USER_NOTICE);
        }
        $this->SendDebug('Result', (string) $xml->State, 0);
        if ((string) $xml->State == 'true')
        {
            SetValueInteger($var, 0);
            return true;
        }
        else
        {
            throw new Exception("Error on start CCU-Program", E_USER_NOTICE);
        }
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        unset($Value);
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;
        try
        {
            $this->StartCCUProgram($Ident);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
    }

################## PUBLIC

    public function ReadPrograms()
    {
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;

        try
        {
            return $this->ReadCCUPrograms();
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

    public function StartProgram(string $Parameter)
    {
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
            return;

        try
        {
            return $this->StartCCUProgram($Parameter);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
            return false;
        }
    }

}

?>