<?

/**
 * @addtogroup homematicextended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2017 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.40
 */
require_once(__DIR__ . "/../libs/HMBase.php");  // HMBase Klasse

/**
 * HomeMaticRFInterfaceSplitter ist die Klasse für das IPS-Modul 'HomeMatic RFInterface-Splitter'.
 * Erweitert HMBase 
 */
class HomeMaticRFInterfaceSplitter extends HMBase
{

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999994');
        $this->RegisterPropertyBoolean("EmulateStatus", false);
        $this->RegisterPropertyInteger("Interval", 0);
        $this->RegisterTimer("ReadRFInterfaces", 0, '@HM_ReadRFInterfaces($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter(".*9999999999.*");
        if (IPS_GetKernelRunlevel() <> KR_READY)
            return;

        if ($this->CheckConfig())
        {
            if ($this->ReadPropertyInteger("Interval") >= 5)
                $this->SetTimerInterval("ReadRFInterfaces", $this->ReadPropertyInteger("Interval") * 1000);
            else
                $this->SetTimerInterval("ReadRFInterfaces", 0);
        }
        else
            $this->SetTimerInterval("ReadRFInterfaces", 0);


        if (!$this->HasActiveParent())
            return;
        try
        {
            $this->ReadRFInterfaces();
        }
        catch (Exception $exc)
        {
            echo $this->Translate($exc->getMessage());
        }
    }

    ################## protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     * 
     * @access protected
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Parent ändert.
     * 
     * @access protected
     */
    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     * 
     * @access protected
     * @return int ID des Parent.
     */
    protected function GetParentData()
    {
        $ParentId = parent::GetParentData();
        $this->SetSummary($this->HMAddress);
        return $ParentId;
    }

################## PRIVATE                

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     * 
     * @access privat
     * @return boolean True wenn Konfig ok, sonst false.
     */
    private function CheckConfig()
    {
        $Interval = $this->ReadPropertyInteger("Interval");
        if ($Interval < 0)
        {
            $this->SetStatus(IS_EBASE + 2);
            return false;
        }
        
        if ($Interval == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            return true;
        }
        
        if ($Interval < 5)
        {
            $this->SetStatus(IS_EBASE + 3);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return true;
    }

    /**
     * Liest alle Daten der RF-Interfaces aus der CCU aus.
     * 
     * @access privat
     * @return array Ein Array mit den Daten der Interfaces.
     */
    private function GetInterfaces()
    {
        if (!$this->HasActiveParent())
        {
            trigger_error($this->Translate("Instance has no active parent instance!"), E_USER_NOTICE);
            return array();
        }
        $ParentId = $this->ParentId;
        $Protocol = array();
        if (IPS_GetProperty($ParentId, "RFOpen") === true)
            $Protocol[] = 0;
        if (IPS_GetProperty($ParentId, "IPOpen") === true)
            $Protocol[] = 2;

        $data = array();
        $ParentData = Array
            (
            "DataID" => "{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}",
            "Protocol" => 0,
            "MethodName" => "listBidcosInterfaces",
            "WaitTime" => 5000,
            "Data" => $data
        );
        $ret = array();
        foreach ($Protocol as $ProtocolId)
        {
            $ParentData["Protocol"] = $ProtocolId;
            $JSON = json_encode($ParentData);
            $ResultJSON = @$this->SendDataToParent($JSON);
            $Result = @json_decode($ResultJSON);
            $this->SendDebug ('List:' . $ProtocolId , $Result ,0 );
            if ($Result === false)
                trigger_error($this->Translate('Error on read interfaces:') . $ProtocolId, E_USER_NOTICE);
            else
                $ret[$ProtocolId] = $Result;
        }
        return $ret;
    }

################## PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_CreateAllRFInstances'.
     * Erzeugt alle Instanzen vom Typ RF-Interface, wenn diese noch nicht angelegt sind.
     * 
     * @access public
     * @return array Array von IPS-IDs von den angelegten Instanzen.
     */
    public function CreateAllRFInstances()
    {

        $DevicesIDs = IPS_GetInstanceListByModuleID("{36549B96-FA11-4651-8662-F310EEEC5C7D}");
        $CreatedDevices = array();
        $KnownDevices = array();
        foreach ($DevicesIDs as $Device)
        {
            $KnownDevices[] = IPS_GetProperty($Device, 'Address');
        }

        $Result = $this->GetInterfaces();
        foreach ($Result as $Protocol)
        {
            foreach ($Protocol as $Interface)
            {
                if (in_array($Interface->ADDRESS, $KnownDevices))
                    continue;
                $NewDevice = IPS_CreateInstance("{36549B96-FA11-4651-8662-F310EEEC5C7D}");
                IPS_SetName($NewDevice, $Interface->TYPE);
                if (IPS_GetInstance($NewDevice)['ConnectionID'] <> $this->InstanceID)
                {
                    @IPS_DisconnectInstance($NewDevice);
                    IPS_ConnectInstance($NewDevice, $this->InstanceID);
                }
                IPS_SetProperty($NewDevice, 'Address', $Interface->ADDRESS);
                IPS_ApplyChanges($NewDevice);
                $CreatedDevices[] = $NewDevice;
            }
        }
        if (count($CreatedDevices) > 0)
            $this->ReadRFInterfaces();

        return $CreatedDevices;
    }

    /**
     * IPS-Instanz-Funktion 'HM_ReadRFInterfaces'.
     * Liest die Daten der RF-Interfaces und versendet sie an die Childs.
     * 
     * @access public
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadRFInterfaces()
    {
        $Result = $this->GetInterfaces();
        $ret = false;
        foreach ($Result as $ProtocolID => $Protocol)
        {
            if (!is_array($Protocol))
                continue;
            foreach ($Protocol as $InterfaceIndex => $Interface)
            {
                $this->SendDebug("Proto" . $ProtocolID . " If" . $InterfaceIndex, $Interface, 0);
                $Interface->DataID = "{E2966A08-BCE1-4E76-8C4B-7E0136244E1B}";
                $Data = json_encode($Interface);
                $this->SendDataToChildren($Data);
                $ret = true;
            }
        }
        return $ret;
    }

}

/** @} */
