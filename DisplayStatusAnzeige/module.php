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
//    private $HMDeviceAddress = '';
    private $HMEventData = array();

    public function __construct($InstanceID)
    {
        //Never delete this line!
        parent::__construct($InstanceID);

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger("PageUpID", 0);
        $this->RegisterPropertyInteger("PageDownID", 0);
        $this->RegisterPropertyInteger("ActionUpID", 0);
        $this->RegisterPropertyInteger("ActionDownID", 0);
        $this->RegisterPropertyInteger("MaxPage", 1);
        $this->RegisterPropertyInteger("Timeout", 0);
        $this->RegisterPropertyInteger("ScriptID", 0);
        $this->RegisterVariableInteger('PAGE', 'PAGE');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->CheckConfig())
        {
            if ($this->GetDisplayAddress())
            {
//                $this->SetSummary($this->HMDeviceAddress);
            }
            else
            {
                $this->SetSummary('');
            }
        }
        else
        {
            $this->SetSummary('');
        }
    }

    public function ReceiveData($JSONString)
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //    
        //FIXME Bei Status inaktiv abbrechen        
        if (!$this->GetDisplayAddress())
            return;
        $Data = json_decode($JSONString);
        $ReceiveData = array("DeviceID" => (string) $Data->DeviceID, "VariableName" => (string) $Data->VariableName);
//        if ($ReceiveData === $this->HMEventData)
//            return;
        $Action = array_search($ReceiveData, $this->HMEventData);
        if ($Action === false)
            return;
        $this->RunDisplayScript($Action);
    }

################## PRIVATE                

    private function CheckConfig()
    {
        foreach (self::$PropertysName as $Name)
        {
//            Alle Pr�fen ob gleich
//            $this->RegisterPropertyInteger($Name, 0);
        }

        return true;
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
//                $this->HMVariableIdents[$Name] = IPS_GetObject($EventID)['ObjectIdent'];
            }
        }
        if (sizeof($this->HMEventData) > 0)
            return true;
        else
            return false;
    }

    private function RunDisplayScript($Action)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__ . 'Action:' . $Action); //            
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
                break;
            case "PageDownID":
                if ($Page == 1)
                    $Page = $MaxPage;
                else
                    $Page--;
                $ActionString = "DOWN";
                break;
            case "ActionUpID":
                $ActionString = "UP";
                break;
            case "ActionDownID":
                $ActionString = "UP";
                break;
        }
        // PHP-Script ausf�hren
        $ScriptID = $this->ReadPropertyInteger('ScriptID');
        if ($ScriptID <> 0)
        {
            $Result = IPS_RunScriptWaitEx($ScriptID, array('ACTION' => $ActionString, 'PAGE' => $Page, 'EVENT' => $this->InstanceID));
            IPS_LogMessage(__CLASS__, __FUNCTION__ . 'ScriptResult:' . $Result); //                    
        }
        SetValueInteger($this->GetIDForIdent('PAGE'), $Page);
        $Timeout = $this->ReadPropertyInteger('Timeout');
        if ($Timeout > 0)        
        {
        //$this->SetTimerInterval('DisplayTimeout',$Timeout);
        }
    }

}

?>