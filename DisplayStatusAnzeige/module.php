<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMDisWM55 extends HMBase
{

    const PageUP = 0;
    const PageDown = 1;
    const ActionUp = 2;
    const ActionDown = 3;
    private static $PropertysName  = array(
        "PageUpID",
        "PageDownID",
        "ActionUpID",
        "ActionDownID");

    private $HMDeviceAddress;

    public function __construct($InstanceID)
    {
        //Never delete this line!
        parent::__construct($InstanceID);

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        foreach (self::$PropertysName as $Name)
        {
            $this->RegisterPropertyInteger($Name, 0);
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
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //    
/*        if (!$this->GetPowerSysVarAddress())
            return;
        $Data = json_decode($JSONString);
        if ($this->HMDeviceAddress <> (string) $Data->DeviceID)
            return;
        if ((string) $Data->VariableName <> 'ENERGY_COUNTER')
            return;
        $this->ReadPowerSysVar();*/
    }

################## PRIVATE                

    private function CheckConfig()
    {
        return true;
    }

    private function GetDisplayAddress()
    {
        /*$EventID = $this->ReadPropertyInteger("EventID");
        if ($EventID == 0)
            return false;
        $parent = IPS_GetParent($EventID);
        $this->HMDeviceAddress = IPS_GetProperty($parent, 'Address');
        return true;

        $this->RegisterPropertyInteger("PageUpID", 0);
        $this->RegisterPropertyInteger("PageDownID", 0);
        $this->RegisterPropertyInteger("ActionUpID", 0);
        $this->RegisterPropertyInteger("ActionDownID", 0);*/
    }

}

?>