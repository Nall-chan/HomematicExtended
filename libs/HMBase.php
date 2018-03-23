<?php

/**
 * @addtogroup homematicextended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMBase.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.43
 */
if (!defined("IPS_BASE")) {
    // --- BASE MESSAGE
    define('IPS_BASE', 10000);                             //Base Message
    define('IPS_KERNELSTARTED', IPS_BASE + 1);             //Post Ready Message
    define('IPS_KERNELSHUTDOWN', IPS_BASE + 2);            //Pre Shutdown Message, Runlevel UNINIT Follows
}
if (!defined("IPS_KERNELMESSAGE")) {
    // --- KERNEL
    define('IPS_KERNELMESSAGE', IPS_BASE + 100);           //Kernel Message
    define('KR_CREATE', IPS_KERNELMESSAGE + 1);            //Kernel is beeing created
    define('KR_INIT', IPS_KERNELMESSAGE + 2);              //Kernel Components are beeing initialised, Modules loaded, Settings read
    define('KR_READY', IPS_KERNELMESSAGE + 3);             //Kernel is ready and running
    define('KR_UNINIT', IPS_KERNELMESSAGE + 4);            //Got Shutdown Message, unloading all stuff
    define('KR_SHUTDOWN', IPS_KERNELMESSAGE + 5);          //Uninit Complete, Destroying Kernel Inteface
}
if (!defined("IPS_LOGMESSAGE")) {
    // --- KERNEL LOGMESSAGE
    define('IPS_LOGMESSAGE', IPS_BASE + 200);              //Logmessage Message
    define('KL_MESSAGE', IPS_LOGMESSAGE + 1);              //Normal Message                      | FG: Black | BG: White  | STLYE : NONE
    define('KL_SUCCESS', IPS_LOGMESSAGE + 2);              //Success Message                     | FG: Black | BG: Green  | STYLE : NONE
    define('KL_NOTIFY', IPS_LOGMESSAGE + 3);               //Notiy about Changes                 | FG: Black | BG: Blue   | STLYE : NONE
    define('KL_WARNING', IPS_LOGMESSAGE + 4);              //Warnings                            | FG: Black | BG: Yellow | STLYE : NONE
    define('KL_ERROR', IPS_LOGMESSAGE + 5);                //Error Message                       | FG: Black | BG: Red    | STLYE : BOLD
    define('KL_DEBUG', IPS_LOGMESSAGE + 6);                //Debug Informations + Script Results | FG: Grey  | BG: White  | STLYE : NONE
    define('KL_CUSTOM', IPS_LOGMESSAGE + 7);               //User Message                        | FG: Black | BG: White  | STLYE : NONE
}
if (!defined("IPS_MODULEMESSAGE")) {
    // --- MODULE LOADER
    define('IPS_MODULEMESSAGE', IPS_BASE + 300);           //ModuleLoader Message
    define('ML_LOAD', IPS_MODULEMESSAGE + 1);              //Module loaded
    define('ML_UNLOAD', IPS_MODULEMESSAGE + 2);            //Module unloaded
}
if (!defined("IPS_OBJECTMESSAGE")) {
    // --- OBJECT MANAGER
    define('IPS_OBJECTMESSAGE', IPS_BASE + 400);
    define('OM_REGISTER', IPS_OBJECTMESSAGE + 1);          //Object was registered
    define('OM_UNREGISTER', IPS_OBJECTMESSAGE + 2);        //Object was unregistered
    define('OM_CHANGEPARENT', IPS_OBJECTMESSAGE + 3);      //Parent was Changed
    define('OM_CHANGENAME', IPS_OBJECTMESSAGE + 4);        //Name was Changed
    define('OM_CHANGEINFO', IPS_OBJECTMESSAGE + 5);        //Info was Changed
    define('OM_CHANGETYPE', IPS_OBJECTMESSAGE + 6);        //Type was Changed
    define('OM_CHANGESUMMARY', IPS_OBJECTMESSAGE + 7);     //Summary was Changed
    define('OM_CHANGEPOSITION', IPS_OBJECTMESSAGE + 8);    //Position was Changed
    define('OM_CHANGEREADONLY', IPS_OBJECTMESSAGE + 9);    //ReadOnly was Changed
    define('OM_CHANGEHIDDEN', IPS_OBJECTMESSAGE + 10);     //Hidden was Changed
    define('OM_CHANGEICON', IPS_OBJECTMESSAGE + 11);       //Icon was Changed
    define('OM_CHILDADDED', IPS_OBJECTMESSAGE + 12);       //Child for Object was added
    define('OM_CHILDREMOVED', IPS_OBJECTMESSAGE + 13);     //Child for Object was removed
    define('OM_CHANGEIDENT', IPS_OBJECTMESSAGE + 14);      //Ident was Changed
}
if (!defined("IPS_INSTANCEMESSAGE")) {
    // --- INSTANCE MANAGER
    define('IPS_INSTANCEMESSAGE', IPS_BASE + 500);         //Instance Manager Message
    define('IM_CREATE', IPS_INSTANCEMESSAGE + 1);          //Instance created
    define('IM_DELETE', IPS_INSTANCEMESSAGE + 2);          //Instance deleted
    define('IM_CONNECT', IPS_INSTANCEMESSAGE + 3);         //Instance connectged
    define('IM_DISCONNECT', IPS_INSTANCEMESSAGE + 4);      //Instance disconncted
    define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5);    //Status was Changed
    define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6);  //Settings were Changed
    define('IM_CHANGESEARCH', IPS_INSTANCEMESSAGE + 7);    //Searching was started/stopped
    define('IM_SEARCHUPDATE', IPS_INSTANCEMESSAGE + 8);    //Searching found new results
    define('IM_SEARCHPROGRESS', IPS_INSTANCEMESSAGE + 9);  //Searching progress in %
    define('IM_SEARCHCOMPLETE', IPS_INSTANCEMESSAGE + 10); //Searching is complete
}
if (!defined("IPS_VARIABLEMESSAGE")) {
    // --- VARIABLE MANAGER
    define('IPS_VARIABLEMESSAGE', IPS_BASE + 600);              //Variable Manager Message
    define('VM_CREATE', IPS_VARIABLEMESSAGE + 1);               //Variable Created
    define('VM_DELETE', IPS_VARIABLEMESSAGE + 2);               //Variable Deleted
    define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3);               //On Variable Update
    define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4);    //On Profile Name Change
    define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5);  //On Profile Action Change
}
if (!defined("IPS_SCRIPTMESSAGE")) {
    // --- SCRIPT MANAGER
    define('IPS_SCRIPTMESSAGE', IPS_BASE + 700);           //Script Manager Message
    define('SM_CREATE', IPS_SCRIPTMESSAGE + 1);            //On Script Create
    define('SM_DELETE', IPS_SCRIPTMESSAGE + 2);            //On Script Delete
    define('SM_CHANGEFILE', IPS_SCRIPTMESSAGE + 3);        //On Script File changed
    define('SM_BROKEN', IPS_SCRIPTMESSAGE + 4);            //Script Broken Status changed
}
if (!defined("IPS_EVENTMESSAGE")) {
    // --- EVENT MANAGER
    define('IPS_EVENTMESSAGE', IPS_BASE + 800);             //Event Scripter Message
    define('EM_CREATE', IPS_EVENTMESSAGE + 1);             //On Event Create
    define('EM_DELETE', IPS_EVENTMESSAGE + 2);             //On Event Delete
    define('EM_UPDATE', IPS_EVENTMESSAGE + 3);
    define('EM_CHANGEACTIVE', IPS_EVENTMESSAGE + 4);
    define('EM_CHANGELIMIT', IPS_EVENTMESSAGE + 5);
    define('EM_CHANGESCRIPT', IPS_EVENTMESSAGE + 6);
    define('EM_CHANGETRIGGER', IPS_EVENTMESSAGE + 7);
    define('EM_CHANGETRIGGERVALUE', IPS_EVENTMESSAGE + 8);
    define('EM_CHANGETRIGGEREXECUTION', IPS_EVENTMESSAGE + 9);
    define('EM_CHANGECYCLIC', IPS_EVENTMESSAGE + 10);
    define('EM_CHANGECYCLICDATEFROM', IPS_EVENTMESSAGE + 11);
    define('EM_CHANGECYCLICDATETO', IPS_EVENTMESSAGE + 12);
    define('EM_CHANGECYCLICTIMEFROM', IPS_EVENTMESSAGE + 13);
    define('EM_CHANGECYCLICTIMETO', IPS_EVENTMESSAGE + 14);
}
if (!defined("IPS_MEDIAMESSAGE")) {
    // --- MEDIA MANAGER
    define('IPS_MEDIAMESSAGE', IPS_BASE + 900);           //Media Manager Message
    define('MM_CREATE', IPS_MEDIAMESSAGE + 1);             //On Media Create
    define('MM_DELETE', IPS_MEDIAMESSAGE + 2);             //On Media Delete
    define('MM_CHANGEFILE', IPS_MEDIAMESSAGE + 3);         //On Media File changed
    define('MM_AVAILABLE', IPS_MEDIAMESSAGE + 4);          //Media Available Status changed
    define('MM_UPDATE', IPS_MEDIAMESSAGE + 5);
}
if (!defined("IPS_LINKMESSAGE")) {
    // --- LINK MANAGER
    define('IPS_LINKMESSAGE', IPS_BASE + 1000);           //Link Manager Message
    define('LM_CREATE', IPS_LINKMESSAGE + 1);             //On Link Create
    define('LM_DELETE', IPS_LINKMESSAGE + 2);             //On Link Delete
    define('LM_CHANGETARGET', IPS_LINKMESSAGE + 3);       //On Link TargetID change
}
if (!defined("IPS_FLOWMESSAGE")) {
    // --- DATA HANDLER
    define('IPS_FLOWMESSAGE', IPS_BASE + 1100);             //Data Handler Message
    define('FM_CONNECT', IPS_FLOWMESSAGE + 1);             //On Instance Connect
    define('FM_DISCONNECT', IPS_FLOWMESSAGE + 2);          //On Instance Disconnect
}
if (!defined("IPS_ENGINEMESSAGE")) {
    // --- SCRIPT ENGINE
    define('IPS_ENGINEMESSAGE', IPS_BASE + 1200);           //Script Engine Message
    define('SE_UPDATE', IPS_ENGINEMESSAGE + 1);             //On Library Refresh
    define('SE_EXECUTE', IPS_ENGINEMESSAGE + 2);            //On Script Finished execution
    define('SE_RUNNING', IPS_ENGINEMESSAGE + 3);            //On Script Started execution
}
if (!defined("IPS_PROFILEMESSAGE")) {
    // --- PROFILE POOL
    define('IPS_PROFILEMESSAGE', IPS_BASE + 1300);
    define('PM_CREATE', IPS_PROFILEMESSAGE + 1);
    define('PM_DELETE', IPS_PROFILEMESSAGE + 2);
    define('PM_CHANGETEXT', IPS_PROFILEMESSAGE + 3);
    define('PM_CHANGEVALUES', IPS_PROFILEMESSAGE + 4);
    define('PM_CHANGEDIGITS', IPS_PROFILEMESSAGE + 5);
    define('PM_CHANGEICON', IPS_PROFILEMESSAGE + 6);
    define('PM_ASSOCIATIONADDED', IPS_PROFILEMESSAGE + 7);
    define('PM_ASSOCIATIONREMOVED', IPS_PROFILEMESSAGE + 8);
    define('PM_ASSOCIATIONCHANGED', IPS_PROFILEMESSAGE + 9);
}
if (!defined("IPS_TIMERMESSAGE")) {
    // --- TIMER POOL
    define('IPS_TIMERMESSAGE', IPS_BASE + 1400);            //Timer Pool Message
    define('TM_REGISTER', IPS_TIMERMESSAGE + 1);
    define('TM_UNREGISTER', IPS_TIMERMESSAGE + 2);
    define('TM_SETINTERVAL', IPS_TIMERMESSAGE + 3);
    define('TM_UPDATE', IPS_TIMERMESSAGE + 4);
    define('TM_RUNNING', IPS_TIMERMESSAGE + 5);
}

if (!defined("IS_ACTIVE")) { //Nur wenn Konstanten noch nicht bekannt sind.
    // --- STATUS CODES
    define('IS_SBASE', 100);
    define('IS_CREATING', IS_SBASE + 1); //module is being created
    define('IS_ACTIVE', IS_SBASE + 2); //module created and running
    define('IS_DELETING', IS_SBASE + 3); //module us being deleted
    define('IS_INACTIVE', IS_SBASE + 4); //module is not beeing used
    // --- ERROR CODES
    define('IS_EBASE', 200);          //default errorcode
    define('IS_NOTCREATED', IS_EBASE + 1); //instance could not be created
}

if (!defined("vtBoolean")) { //Nur wenn Konstanten noch nicht bekannt sind.
    define('vtBoolean', 0);
    define('vtInteger', 1);
    define('vtFloat', 2);
    define('vtString', 3);
}

/**
 * HMBase ist die Basis-Klasse für alle Module welche HMScript verwenden.
 * Erweitert ipsmodule
 *
 * @property string $HMAddress Die Adresse der CCU.
 * @property int $ParentId Aktueller IO-Parent.
 */
abstract class HMBase extends IPSModule
{

    use DebugHelper,
        InstanceStatus {
        InstanceStatus::GetParentData as GetInstanceParent;
    }
    /**
     * Wert einer Eigenschaft aus den InstanceBuffer lesen.
     *
     * @access public
     * @param string $name Propertyname
     * @return mixed Value of Name
     */
    public function __get($name)
    {
        return unserialize($this->GetBuffer($name));
    }

    /**
     * Wert einer Eigenschaft in den InstanceBuffer schreiben.
     *
     * @access public
     * @param string $name Propertyname
     * @param mixed Value of Name
     */
    public function __set($name, $value)
    {
        $this->SetBuffer($name, serialize($value));
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent("{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}");
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if (IPS_GetKernelRunlevel() <> KR_READY) {
            return;
        }
        $this->GetParentData();
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @access public
     * @param int $TimeStamp
     * @param int $SenderID
     * @param int $Message
     * @param array|int $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                try {
                    $this->KernelReady();
                } catch (Exception $exc) {
                    return;
                }
                break;
            case FM_CONNECT:
            case FM_DISCONNECT:
            case IM_CHANGESETTINGS:
                $this->ForceRefresh();
                break;
            case IM_CHANGESTATUS:
                if ($this->HMAddress == '') {
                    return;
                }
                if (($SenderID == @IPS_GetInstance($this->InstanceID)['ConnectionID']) and ( $Data[0] == IS_ACTIVE)) {
                    try {
                        $this->ForceRefresh();
                    } catch (Exception $exc) {
                        return;
                    }
                }
                break;
        }
    }

    ################## protected
    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     *
     * @access protected
     */
    abstract protected function KernelReady();
    /**
     * Wird ausgeführt wenn sich der Parent ändert.
     *
     * @access protected
     */
    abstract protected function ForceRefresh();
    /**
     * Setzte alle Eigenschaften, welche Instanzen die mit einem Homematic-Socket verbunden sind, haben müssen.
     *
     * @access protected
     * @param string $Address Die zu verwendene HM-Device Adresse.
     */
    protected function RegisterHMPropertys(string $Address)
    {
        $this->RegisterPropertyInteger("Protocol", 0);
        $count = @IPS_GetInstanceListByModuleID(IPS_GetInstance($this->InstanceID)["ModuleInfo"]["ModuleID"]);
        if (is_array($count)) {
            $this->RegisterPropertyString("Address", $Address . ":" . count($count));
        } else {
            $this->RegisterPropertyString("Address", $Address . ":0");
        }
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @access protected
     * @return int ID des Parent.
     */
    protected function GetParentData()
    {
        $OldParentId = $this->ParentId;
        $ParentId = $this->GetInstanceParent();
        if ($ParentId <> $OldParentId) {

            if ($ParentId > 0) {
                $this->HMAddress = (string) IPS_GetProperty($ParentId, 'Host');
            } else {
                $this->HMAddress = '';
            }
        }
        return $ParentId;
    }

    /**
     * Überträgt das übergeben HM-Script an die CCU und liefert das Ergebnis.
     *
     * @access protected
     * @param string $url Die URL auf der CCU.
     * @param string $HMScript Das zu übertragende HM-Script.
     * @return string Das Ergebnis von der CCU.
     * @throws Exception Wenn die CCU nicht erreicht wurde.
     */
    protected function LoadHMScript(string $url, string $HMScript)
    {
        if ($this->HMAddress <> '') {
            $this->SendDebug($url, $HMScript, 0);
            $header[] = "Accept: text/plain,text/xml,application/xml,application/xhtml+xml,text/html";
            $header[] = "Cache-Control: max-age=0";
            $header[] = "Connection: close";
            $header[] = "Accept-Charset: UTF-8";
            $header[] = "Content-type: text/plain;charset=\"UTF-8\"";
            $ch = curl_init('http://' . (string) $this->HMAddress . ':8181/' . $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $HMScript);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code >= 400) {
                throw new Exception($this->Translate('CCU unreachable:') . $http_code, E_USER_NOTICE);
            }
            if ($result === false) {
                throw new Exception('CCU unreachable', E_USER_NOTICE);
            }
            $this->SendDebug("Result", $result, 0);
            return $result;
        } else {
            throw new Exception('CCU Address not set.', E_USER_NOTICE);
        }
    }

}

/**
 * Trait mit Hilfsfunktionen für Variablenprofile.
 */
trait Profile
{
    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer mit Assoziationen
     *
     * @access protected
     * @param string $Name Name des Profils.
     * @param string $Icon Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     * @param array $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (sizeof($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer
     *
     * @access protected
     * @param string $Name Name des Profils.
     * @param string $Icon Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     * @param int $MinValue Minimaler Wert.
     * @param int $MaxValue Maximaler wert.
     * @param int $StepSize Schrittweite
     */
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != 1) {
                throw new Exception("Variable profile type does not match for profile " . $Name, E_USER_NOTICE);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    /**
     * Löscht ein Variablenprofile, sofern es nicht außerhalb dieser Instanz noch verwendet wird.
     * @param string $Profil Name des zu löschenden Profils.
     */
    protected function UnregisterProfil(string $Profil)
    {
        if (!IPS_VariableProfileExists($Profil)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Profil) {
                return;
            }
        }
        IPS_DeleteVariableProfile($Profil);
    }

}

/**
 * DebugHelper ergänzt SendDebug um die Möglichkeit Array und Objekte auszugeben.
 *
 */
trait DebugHelper
{
    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @access protected
     * @param string $Message Nachricht für Data.
     * @param TXB_API_Data|mixed $Data Daten für die Ausgabe.
     * @return int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {

                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        } elseif (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        } else {
            parent::SendDebug($Message, (string) $Data, $Format);
        }
    }

}

/**
 * Trait mit Hilfsfunktionen für den Datenaustausch.
 */
trait InstanceStatus
{
    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     *
     * @access protected
     * @return int ID des Parent.
     */
    protected function GetParentData()
    {
        $OldParentId = $this->ParentId;
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($ParentId <> $OldParentId) {
            if ($OldParentId > 0) {
                $this->UnregisterMessage($OldParentId, IM_CHANGESETTINGS);
                $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
            }
            if ($ParentId > 0) {
                $this->RegisterMessage($ParentId, IM_CHANGESETTINGS);
                $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            } else {
                $ParentId = 0;
            }
            $this->ParentId = $ParentId;
        }
        return $ParentId;
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     *
     * @access protected
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0) {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            return ($parent['InstanceStatus'] == 102 ? true : false);
        }
        return false;
    }

    /**
     * Setzte eine IPS-Variable auf den Wert von $value
     *
     * @access protected
     * @param string $Ident Ident der Statusvariable.
     * @param bool|int|float|string $value Neuer Wert der Statusvariable.
     */
    protected function SetValue($Ident, $value)
    {
        if (method_exists('IPSModule', 'SetValue')) {
            parent::SetValue($Ident, $value);
        } else {
            $id = @$this->GetIDForIdent($Ident);
            if ($id > 0) {
                SetValue($id, $value);
            }
        }
    }

}

/** @} */
