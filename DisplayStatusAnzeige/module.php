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
    private $HMDeviceAddress = '';
    private $HMVariableIdents = array();

    public function __construct($InstanceID)
    {
        //Never delete this line!
        parent::__construct($InstanceID);

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        foreach (self::$PropertysName as $Name)
        {
            IPS_LogMessage(__CLASS__, $Name);
            $this->RegisterPropertyInteger((string)$Name, 0);
        }

        $this->RegisterPropertyInteger("MaxPage", 1);
        $this->RegisterPropertyInteger("Timeout", 0);
        $this->RegisterPropertyInteger("ScriptID", 0);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        if ($this->CheckConfig())
        {
            if ($this->GetDisplayAddress())
            {
                $this->SetSummary($this->HMDeviceAddress);
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
        return;
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //    
        //FIXME Bei Status inaktiv abbrechen        
        if (!$this->GetDisplayAddress())
            return;
        $Data = json_decode($JSONString);
        if ($this->HMDeviceAddress <> (string) $Data->DeviceID)
            return;
        $Action = array_search((string) $Data->VariableName, $this->HMVariableIdents);
        if ($Action === false)
            return;
        $this->RunDisplayScript($Action);
    }

################## PRIVATE                

    private function CheckConfig()
    {
        foreach (self::$PropertysName as $Name)
        {
//            Alle Prüfen ob gleich
//            $this->RegisterPropertyInteger($Name, 0);
        }

        return true;
    }

    private function GetDisplayAddress()
    {
        foreach (self::$PropertysName as $Name)
        {
            IPS_LogMessage(__CLASS__, __FUNCTION__.'Proper:'.IPS_GetProperty($this->InstanceID, $Name)); //                        
            $EventID = $this->ReadPropertyInteger((string)$Name);
            if ($EventID <> 0)
            {
                $parent = IPS_GetParent($EventID);
                $this->HMDeviceAddress = IPS_GetProperty($parent, 'Address');
                $this->HMVariableIdents[$Name] = IPS_GetObject($EventID)['ObjectIdent'];
            }
        }
        if (sizeof($this->HMVariableIdents) > 0)
            return true;
        else
            return false;
    }

    private function RunDisplayScript($Action)
    {
        IPS_LogMessage(__CLASS__, __FUNCTION__.'Action:'.$Action); //            
    }
}
?>