<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMCCUProgram extends HMBase
{

    public function Create()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger("Protocol", 0);
        $this->RegisterPropertyString("Address", "XXX9999999:2");
        $this->RegisterPropertyBoolean("EmulateStatus", false);
//These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
    }

    public function ApplyChanges()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //            
//Never delete this line!
        parent::ApplyChanges();

        $this->CreateProfil();
        $this->ReadCCUPrograms();
    }

    private function CreateProfil()
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        if (!IPS_VariableProfileExists('Execute.HM'))
        {
            IPS_CreateVariableProfile('Execute.HM', 1);
            IPS_SetVariableProfileAssociation('Execute.HM', 0, 'Start', '', -1);
        }
    }

    protected function GetParentData()
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            
        parent::GetParentData();
        $this->SetSummary($this->HMAddress);
    }

    private function ReadCCUPrograms()
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
        $url = 'SysPrg.exe';
        $HMScript = 'SysPrgs=dom.GetObject(ID_PROGRAMS).EnumUsedIDs();';
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult === false)
            throw new Exception("Error on Read CCU-Programs");
        try
        {
            $xml = new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR, 'HM-Script result is not wellformed');
            throw new Exception("Error on Read CCU-Programs");
        }
                $Result =true;
        foreach (explode(chr(0x09), (string) $xml->SysPrgs) as $SysPrg)
        {
            $HMScript = 'Name=dom.GetObject(' . $SysPrg . ').Name();' . PHP_EOL
                    . 'Info=dom.GetObject(' . $SysPrg . ').PrgInfo();' . PHP_EOL;
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
            if ($HMScript === false)
                throw new Exception("Error on Read CCU-Programs");
            try
            {
                $varXml = new SimpleXMLElement(utf8_encode($HMScriptResult), LIBXML_NOBLANKS + LIBXML_NONET);
            }
            catch (Exception $ex)
            {
                $this->LogMessage(KL_WARNING, 'HM-Script result is not wellformed');
                $Result =false;
//                throw new Exception("Error on Read CCU-Programs");
                continue;
            }
            $var = @IPS_GetObjectIDByIdent($SysPrg, $this->InstanceID);
            $Name = /*utf8_decode(*/(string) $varXml->Name;
            $Info = utf8_decode((string) $varXml->Name);
            if ($var === false)
            {
                $this->MaintainVariable($SysPrg, $Name, 1, 'Execute.HM', 0, true);
                $this->EnableAction($SysPrg);
//                $this->MaintainAction($SysPrg, 'ActionHandler', true);
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

        $var = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($var === false)
            throw new Exception('CCU Program ' . $Ident . ' not found!');

        $url = 'SysPrg.exe';
        $HMScript = 'State=dom.GetObject(' . $Ident . ').ProgramExecute();';
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScript === false)
            throw new Exception("Error on start CCU-Program");
        try
        {
            $xml = new SimpleXMLElement($HMScriptResult, LIBXML_NOBLANKS + LIBXML_NONET);
        }
        catch (Exception $ex)
        {
            $this->LogMessage(KL_ERROR, 'Error on start CCU-Program');
            throw new Exception("Error on start CCU-Program");
        }
        if ((string) $xml->State == 'true')
        {
            SetValueInteger($var, 0);
            return true;
        }
        else
            throw new Exception("Error on start CCU-Program");
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__ . ' Ident:.' . $Ident); //     
        unset($Value);
        $this->StartCCUProgram($Ident);
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function ReadPrograms()
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            

        return $this->ReadCCUPrograms();
    }

    public function StartProgram(string $Parameter)
    {
        //IPS_LogMessage(__CLASS__, __FUNCTION__); //            

        return $this->StartCCUProgram($Parameter);
    }

}

?>