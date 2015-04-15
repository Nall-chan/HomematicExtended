<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMCCUProgram extends HMBase
{

    public function __construct($InstanceID)
    {
//Never delete this line!
        parent::__construct($InstanceID);

//These lines are parsed on Symcon Startup or Instance creation
//You cannot use variables here. Just static values.
    }
/* FIX ME
    public function ProcessKernelRunlevelChange($Runlevel)
    {
        if ($Runlevel == KR_READY)
        {
            $this->ReadCCUPrograms();
        }
    }
*/
    
//    public function ProcessInstanceStatusChange($InstanceID, $Status)
//    {
            // FIX ME....
            /*
             * @IPS_GetInstanceParentID replace
    protected function GetParentData()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $result = '';
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            $result = IPS_ReadProperty($parent, 'Host');
        }
        $this->SetSummary($result);
        return $result;
    }    */        
/*        if ($this->fKernelRunlevel == KR_READY)
        {
            if (($InstanceID == @IPS_GetInstanceParentID($this->InstanceID)) or ( $InstanceID == 0))
            {
                if ($this->HasActiveParent())
                {
                    $this->ReadCCUPrograms();
                }
            }
        }
        parent::ProcessInstanceStatusChange($InstanceID, $Status);
    }
*/
//    public function MessageSink($Msg)
//    {
            // FIX ME....
            /*
             * @IPS_GetInstanceParentID replace
    protected function GetParentData()
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__); //           
        $result = '';
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            $result = IPS_ReadProperty($parent, 'Host');
        }
        $this->SetSummary($result);
        return $result;
    }    */
/*        if ($Msg['SenderID'] <> 0)
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
                    $this->ReadCCUPrograms();
            } elseif ($Msg['Message'] == DM_DISCONNECT)
            {
                if (($Msg['SenderID'] == $this->InstanceID) or ( $Msg['SenderID'] == IPS_GetInstanceParentID($this->InstanceID)))
                {
                    $this->SetSummary('No parent');
                }
            }
        }
    }
*/
    public function ApplyChanges()
    {
//Never delete this line!
        parent::ApplyChanges();

// FIXME
/*        $this->fKernelRunlevel = KR_INIT;
        if ($this->fKernelRunlevel == KR_INIT)
        {
            $this->CreateProfil();
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $Child)
            {
                $Objekt = IPS_GetObject($Child);
                if ($Objekt['ObjectType'] <> 2)
                    continue;
                $this->MaintainVariable($Objekt['ObjectIdent'], $Objekt['ObjectName'], 1, 'Execute.HM', $Objekt['ObjectPosition'], true);
//                $this->RegisterAction($Objekt['ObjectIdent'], 'ActionHandler');
                $this->EnableAction($Objekt['ObjectIdent']);                
            }
        $this->fKernelRunlevel = KR_READY;            
        }*/
        $this->ReadCCUPrograms();
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
        $this->CreateProfil();
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        if ($this->GetParentData() == '')
            return;
        $url = 'SysPrg.exe';
        $HMScript = 'SysPrgs=dom.GetObject(ID_PROGRAMS).EnumUsedIDs();';
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScriptResult === false)
            throw new Exception("Error on Read CCU-Programs");
        $xml = @new SimpleXMLElement($HMScriptResult);
        if (($xml === false))
        {
            $this->LogMessage('HM-Script result is not wellformed');
            throw new Exception("Error on Read CCU-Programs");
        }
        foreach (explode(' ', (string) $xml->SysPrgs) as $SysPrg)
        {
            $HMScript = 'Name=dom.GetObject(' . $SysPrg . ').Name();' . PHP_EOL
                    . 'Info=dom.GetObject(' . $SysPrg . ').PrgInfo();' . PHP_EOL;
            $HMScriptResult = $this->LoadHMScript($url, $HMScript);
            if ($HMScript === false)
                throw new Exception("Error on Read CCU-Programs");

            $varXml = @new SimpleXMLElement($HMScriptResult);
            if ($varXml === false)
            {
                $this->LogMessage('HM-Script result is not wellformed');
                throw new Exception("Error on Read CCU-Programs");
            }
            $var = @GetObjectIDByIdent($SysPrg, $this->InstanceID);
            if ($var === false)
            {
                $this->MaintainVariable($SysPrg, (string) $varXml->Name, 1, 'Execute.HM.', 0, true);
                $this->EnableAction($SysPrg);
//                $this->MaintainAction($SysPrg, 'ActionHandler', true);
                IPS_SetInfo($SysPrg, (string) $varXml->Info);
            }
            else
            {
                if (IPS_GetName($SysPrg) <> (string) $varXml->Name)
                    IPS_SetName($SysPrg, (string) $varXml->Name);
                if (IPS_GetInfo($SysPrg) <> (string) $varXml->Info)
                    IPS_SetInfo($SysPrg, (string) $varXml->Info);
            }
        }
    }

    private function StartCCUProgram($Ident)
    {
        if ($this->fKernelRunlevel <> KR_READY)
            return;
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!");
        }
        $var = @GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($var === false)
            throw new Exception('CCU Program ' . $Ident . ' not found!');

        $url = 'SysPrg.exe';
        $HMScript = 'State=dom.GetObject(' . $Ident . ').ProgramExecute();';
        $HMScriptResult = $this->LoadHMScript($url, $HMScript);
        if ($HMScript === false)
            throw new Exception("Error on start CCU-Program");
        $xml = @new SimpleXMLElement($HMScriptResult);
        if ($xml === false)
        {
            $this->LogMessage('HM-Script result is not wellformed');
            throw new Exception("Error on start CCU-Program");
        }
        if ((string) $xml->State == 'True')
            SetValueInteger($var, 0);
        else
            throw new Exception("Error on start CCU-Program");
    }


################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__ . ' Ident:.' . $Ident); //     
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
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!");
        else
            $this->ReadCCUPrograms();
    }

    public function StartProgram($Parameter)
    {
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!");
        else
            $this->StartCCUProgram($Parameter);
    }

}

?>