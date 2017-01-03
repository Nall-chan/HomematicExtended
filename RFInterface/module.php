<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMRFInterface extends IPSModule
{

    use DebugHelper;

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString("Address", "");
        $this->ConnectParent("{6EE35B5B-9DD9-4B23-89F6-37589134852F}");
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        //$this->CreateProfil();
        $Address = $this->ReadPropertyString("Address");
        $this->SetSummary($Address);

        if ($Address !== "")
            $this->SetReceiveDataFilter('.*"ADDRESS":"' . $Address . '".*');
        else
            $this->SetReceiveDataFilter(".*9999999999.*");
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);
        unset($Data->DataID);
        unset($Data->ADDRESS);
        $this->SendDebug('Receive', $Data, 0);
        foreach ($Data as $Ident => $Value)
        {
            if ($Value === "")
                continue;
            $Profil = "";
            if ($Ident == "DUTY_CYCLE")
                $Profil = "~Intensity.100";
            switch (gettype($Value))
            {
                case "boolean":
                    $Typ = vtBoolean;
                    break;
                case "integer":
                    $Typ = vtInteger;
                    break;
                case "double":
                case "float":
                    $Typ = vtFloat;
                    break;
                case "string":
                    $Typ = vtString;
                    break;
                default:
                    continue;
            }
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid === false)
            {
                $this->MaintainVariable($Ident, $Ident, $Typ, $Profil, 0, true);
                $vid = @$this->GetIDForIdent($Ident);
            }
            if (GetValue($vid) <> $Value)
                SetValue($vid, $Value);
        }
    }

}
