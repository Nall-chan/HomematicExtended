<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.71
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticSystemvariablen ist die Klasse für das IPS-Modul 'HomeMatic Systemvariablen'.
 * Erweitert HMBase.
 *
 * @property string $HMDeviceAddress Die Geräte-Adresse welche eine Aktualisierung auslöst.
 * @property string $HMDeviceDatapoint Der zu überwachende Datenpunkt welcher eine Aktualisierung auslöst.
 * @property int $Event Die IPS-ID der Variable des Datenpunkt welcher eine Aktualisierung auslöst.
 * @property int $AlarmScriptID
 * @property array $SystemVars Ein Array mit allen IPS-Var-IDs welche den Namen des IDENT (ID der Systemvariable in der CCU) enthalten.
 */
class HomeMaticSystemvariablen extends HMBase
{
    private static $CcuVarType = [2 => VARIABLETYPE_BOOLEAN, 4 => VARIABLETYPE_FLOAT, 16 => VARIABLETYPE_INTEGER, 20 => VARIABLETYPE_STRING];

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999999');

        $this->RegisterPropertyInteger('EventID', 0);
        $this->RegisterPropertyInteger('Interval', 0);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyBoolean('EnableAlarmDP', true);
        $this->RegisterPropertyInteger('AlarmScriptID', 0);

        $this->RegisterTimer('ReadHMSysVar', 0, 'HM_SystemVariablesTimer($_IPS[\'TARGET\']);');
        $this->HMDeviceAddress = '';
        $this->HMDeviceDatapoint = '';
        $this->SystemVars = [];
        $this->AlarmScriptID = 0;
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->SetSummary('');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('HM.AlReceipt');

            foreach ($this->SystemVars as $Ident) {
                $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $Ident;
                if (IPS_VariableProfileExists($VarProfil)) {
                    IPS_DeleteVariableProfile($VarProfil);
                }
            }
        }

        parent::Destroy();
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @param int       $TimeStamp
     * @param int       $SenderID
     * @param int       $Message
     * @param array $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $OldVars = $this->SystemVars;
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                $this->UnregisterReference($SenderID);
                if ($SenderID == $this->ReadPropertyInteger('EventID')) {
                    $this->SetNewConfig();
                    return;
                }
                if (array_key_exists($SenderID, $OldVars)) {
                    $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $OldVars[$SenderID];
                    if (IPS_VariableProfileExists($VarProfil)) {
                        IPS_DeleteVariableProfile($VarProfil);
                    }
                    unset($OldVars[$SenderID]);
                    $this->SystemVars = $OldVars;
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterProfileIntegerEx('HM.AlReceipt', '', '', '', [
            [0, 'Quittieren', '', 0x00FF00]
        ]);

        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            $this->SystemVars = [];
            $this->SetReceiveDataFilter('.*9999999999.*');
            $this->SetSummary('');
            return;
        }

        $MyVars = IPS_GetChildrenIDs($this->InstanceID);
        $OldVars = $this->SystemVars;
        foreach ($MyVars as $Var) {
            $Object = IPS_GetObject($Var);
            if ($Object['ObjectType'] != 2) {
                continue;
            }
            if (strpos($Object['ObjectIdent'], 'AlDP') !== false) {
                $Object['ObjectIdent'] = substr($Object['ObjectIdent'], 4);
            }
            $OldVars[$Var] = $Object['ObjectIdent'];
            $this->RegisterMessage($Var, VM_DELETE);
        }
        $this->SystemVars = $OldVars;

        $this->SetNewConfig();

        if (!$this->HasActiveParent()) {
            return;
        }

        $this->ReadSysVars();
    }

    //################# ActionHandler

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return;
        }
        if (strpos($Ident, 'AlDP') !== false) {
            if ((bool) $Value === false) {
                $this->AlarmReceipt($Ident);
            }
            return;
        }
        $VarID = @$this->GetIDForIdent($Ident);
        if ($VarID === false) {
            trigger_error(sprintf($this->Translate('Ident %s do not exist.'), (string) $Ident), E_USER_NOTICE);
            return;
        }
        switch (IPS_GetVariable($VarID)['VariableType']) {
            case VARIABLETYPE_BOOLEAN:
                $this->WriteValueBoolean($Ident, (bool) $Value);
                break;
            case VARIABLETYPE_INTEGER:
                $this->WriteValueInteger($Ident, (int) $Value);
                break;
            case VARIABLETYPE_FLOAT:
                $this->WriteValueFloat($Ident, (float) $Value);
                break;
            case VARIABLETYPE_STRING:
                $this->WriteValueString($Ident, (string) $Value);
                break;
        }
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData($JSONString)
    {
        $this->ReadSysVars();
        return '';
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_AlarmReceipt'.
     * Bestätigt einen Alarm auf der CCU.
     *
     * @param string $Ident Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     *
     * @return bool True bei erfolg, sonst false.
     */
    public function AlarmReceipt(string $Ident)
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return false;
        }
        $VarID = @$this->GetIDForIdent($Ident);
        if ($VarID === false) {
            trigger_error(sprintf($this->Translate('Ident %s do not exist.'), (string) $Ident), E_USER_NOTICE);
            return false;
        }
        $HMScript = 'object oitemID = dom.GetObject(' . substr($Ident, 4) . ');
                    var State = 0;
                    if (oitemID.AlState() == asOncoming )
                    {
                        State = oitemID.AlReceipt();
                    }';
        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xmlData = $this->GetScriptXML($HMScriptResult);
        if ($xmlData === false) {
            return false;
        }
        if (isset($xmlData->State)) {
            if ((int) $xmlData->State === 1) {
                if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus) === true) {
                    $this->SetValue($Ident, false);
                }
                return true;
            }
            if ((int) $xmlData->State === 0) {
                return false;
            }
        }

        $this->SendDebug('AlarmVar.' . $Ident, 'error on receipt', 0);
        trigger_error(sprintf($this->Translate('Error on receipt alarm of %s.'), (string) $Ident), E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'HM_SystemVariablesTimer'.
     * Wird durch den Timer ausgeführt und liest alle Systemvariablen von der CCU.
     */
    public function SystemVariablesTimer()
    {
        if (!$this->HasActiveParent()) {
            return;
        }

        $this->ReadSysVars();
    }

    /**
     * IPS-Instanz-Funktion 'HM_ReadSystemVariables'.
     * Liest alle Systemvariablen von der CCU.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function ReadSystemVariables()
    {
        return $this->ReadSysVars();
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean'.
     * Schreibt einen bool-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param bool   $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueBoolean(string $Parameter, bool $Value)
    {
        return $this->WriteValueBoolean2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueBoolean2'.
     * Schreibt einen bool-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param bool   $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueBoolean2(string $Parameter, bool $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false) {
            trigger_error(sprintf($this->Translate('Ident %s do not exist.'), (string) $Parameter), E_USER_NOTICE);
            return false;
        }

        if (IPS_GetVariable($VarID)['VariableType'] != VARIABLETYPE_BOOLEAN) {
            trigger_error(sprintf($this->Translate('Wrong Datatype for %s.'), (string) $VarID), E_USER_NOTICE);
            return false;
        }

        if ($Value) {
            $ValueStr = 'true';
        } else {
            $ValueStr = 'false';
        }

        $Result = $this->WriteSysVar($Parameter, $ValueStr);

        if ($Result === true) {
            if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus) === true) {
                $this->SetValue($Parameter, $Value);
            }
            return true;
        }

        trigger_error($this->Translate('Error on write Data ') . $Parameter, E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger'.
     * Schreibt einen integer-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param int    $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueInteger(string $Parameter, int $Value)
    {
        return $this->WriteValueInteger2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueInteger2'.
     * Schreibt einen integer-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param int    $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueInteger2(string $Parameter, int $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false) {
            trigger_error(sprintf($this->Translate('Ident %s do not exist.'), (string) $Parameter), E_USER_NOTICE);
            return false;
        }

        if (IPS_GetVariable($VarID)['VariableType'] != VARIABLETYPE_INTEGER) {
            trigger_error(sprintf($this->Translate('Wrong Datatype for %s.'), (string) $VarID), E_USER_NOTICE);
            return false;
        }

        $Result = $this->WriteSysVar($Parameter, (string) $Value);
        if ($Result === true) {
            if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus) === true) {
                $this->SetValue($Parameter, $Value);
            }
            return true;
        }
        trigger_error($this->Translate('Error on write Data ') . $Parameter, E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat'.
     * Schreibt einen float-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param float  $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueFloat(string $Parameter, float $Value)
    {
        return $this->WriteValueFloat2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueFloat2'.
     * Schreibt einen float-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param float  $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueFloat2(string $Parameter, float $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false) {
            trigger_error(sprintf($this->Translate('Ident %s do not exist.'), (string) $Parameter), E_USER_NOTICE);
            return false;
        }

        if (IPS_GetVariable($VarID)['VariableType'] != VARIABLETYPE_FLOAT) {
            trigger_error(sprintf($this->Translate('Wrong Datatype for %s.'), (string) $VarID), E_USER_NOTICE);
            return false;
        }

        $Result = $this->WriteSysVar($Parameter, (string) sprintf('%.6F', $Value));

        if ($Result === true) {
            if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus) === true) {
                $this->SetValue($Parameter, $Value);
            }
            return true;
        }

        trigger_error($this->Translate('Error on write Data ') . $Parameter, E_USER_NOTICE);
        return false;
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString'.
     * Schreibt einen string-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param string $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueString(string $Parameter, string $Value)
    {
        return $this->WriteValueString2($Parameter, $Value);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueString2'.
     * Schreibt einen string-Wert in eine Systemvariable der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Alarmvariable in der CCU.
     * @param string $Value     Der zu schreibende Wert.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueString2(string $Parameter, string $Value)
    {
        $VarID = @$this->GetIDForIdent($Parameter);
        if ($VarID === false) {
            trigger_error(sprintf($this->Translate('Ident %s do not exist.'), (string) $Parameter), E_USER_NOTICE);
            return false;
        }

        if (IPS_GetVariable($VarID)['VariableType'] != VARIABLETYPE_STRING) {
            trigger_error(sprintf($this->Translate('Wrong Datatype for %s.'), (string) $VarID), E_USER_NOTICE);
            return false;
        }

        $Result = $this->WriteSysVar($Parameter, (string) $Value);

        if ($Result === true) {
            if ($this->ReadPropertyBoolean(\HMExtended\Device\Property::EmulateStatus) === true) {
                $this->SetValue($Parameter, $Value);
            }
            return true;
        }

        trigger_error($this->Translate('Error on write Data ') . $Parameter, E_USER_NOTICE);
        return false;
    }

    //################# protected

    /**
     * Interne Funktion des SDK.
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        $this->ApplyChanges();
    }

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     *
     * @return bool True wenn Konfig ok, sonst false.
     */
    private function SetNewConfig()
    {
        $this->UnregisterReference($this->AlarmScriptID);
        $this->AlarmScriptID = $this->ReadPropertyInteger('AlarmScriptID');
        $this->RegisterReference($this->AlarmScriptID);

        $OldEvent = $this->Event;
        if ($OldEvent > 0) {
            $this->UnregisterMessage($OldEvent, VM_DELETE);
            $this->UnregisterReference($OldEvent);
            $this->Event = 0;
        }
        $Event = $this->ReadPropertyInteger('EventID');
        $Interval = $this->ReadPropertyInteger('Interval');

        if ($Interval < 0) {
            $this->SetTimerInterval('ReadHMSysVar', 0);
            $this->SetStatus(IS_EBASE + 2); //Error Timer is negativ
            return false;
        }

        if ($Interval == 0) {
            $this->SetTimerInterval('ReadHMSysVar', 0);

            if ($Event == 0) {
                $this->SetStatus(IS_INACTIVE); // kein Trigger und kein Timer aktiv
                return false;
            } else {
                if ($this->GetTriggerVar()) {
                    $this->SetReceiveDataFilter('.*"DeviceID":"' . $this->HMDeviceAddress . '","VariableName":"' . $this->HMDeviceDatapoint . '".*');
                    $this->SendDebug('EventFilter', '.*"DeviceID":"' . $this->HMDeviceAddress . '","VariableName":"' . $this->HMDeviceDatapoint . '".*', 0);
                    $this->RegisterMessage($Event, VM_DELETE);
                    $this->RegisterReference($Event);
                    $this->Event = $Event;
                    $this->SetStatus(IS_ACTIVE); // OK
                    return true;
                }
                $this->SetReceiveDataFilter('.*9999999999.*');
                $this->Event = 0;
                $this->SetTimerInterval('ReadHMSysVar', 0);
                $this->SetStatus(IS_INACTIVE); // kein Trigger und kein Timer aktiv
                return false;
            }
        }

        if ($Interval < 5) {
            $this->SetStatus(IS_EBASE + 3);  //Warnung Trigger zu klein
            $this->SetTimerInterval('ReadHMSysVar', 0);
            return false;
        }

        $this->SetTimerInterval('ReadHMSysVar', $Interval * 1000);
        $this->SetStatus(IS_ACTIVE); //OK
        return true;
    }

    /**
     * Prüft und holt alle Daten zu der Event-Variable und Instanz.
     *
     * @return bool True wenn Quelle gültig ist, sonst false.
     */
    private function GetTriggerVar()
    {
        $EventID = $this->ReadPropertyInteger('EventID');
        if (($EventID == 0) || (!IPS_VariableExists($EventID))) {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            return false;
        }
        $parent = IPS_GetParent($EventID);
        if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] != '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}') {
            $this->HMDeviceAddress = '';
            $this->HMDeviceDatapoint = '';
            return false;
        }
        $this->HMDeviceAddress = IPS_GetProperty($parent, \HMExtended\Device\Property::Address);
        $this->HMDeviceDatapoint = IPS_GetObject($EventID)['ObjectIdent'];
        return true;
    }

    /**
     * Liest alle Systemvariablen aus der CCU und legt diese in IPS mit dem dazugehörigen Profil an.
     *
     * @throws Exception Wenn CUU nicht erreichbar oder Daten nicht auswertbar sind.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    private function ReadSysVars()
    {
        // Systemvariablen
        $HMScript = 'SysVars=dom.GetObject(ID_SYSTEM_VARIABLES).EnumUsedIDs();';

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xmlVars = $this->GetScriptXML($HMScriptResult);
        if ($xmlVars === false) {
            return false;
        }

        //Time & Timezone
        $HMScript = 'Now=system.Date("%F %T%z");' . PHP_EOL
                . 'TimeZone=system.Date("%z");' . PHP_EOL;

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xmlTime = $this->GetScriptXML($HMScriptResult);
        if ($xmlTime === false) {
            return false;
        }
        $Date = new DateTime((string) $xmlTime->Now);
        $CCUTime = $Date->getTimestamp();
        $Date = new DateTime();
        $NowTime = $Date->getTimestamp();
        $TimeDiff = $NowTime - $CCUTime;
        $CCUTimeZone = (string) $xmlTime->TimeZone;
        $Result = true;
        $OldVars = $this->SystemVars;
        $OldVarsChange = false;
        foreach (explode(chr(0x09), (string) $xmlVars->SysVars) as $SysVar) {
            $VarIdent = $SysVar;
            $HMScript = 'Name=dom.GetObject(' . $SysVar . ').Name();' . PHP_EOL
                    . 'ValueType=dom.GetObject(' . $SysVar . ').ValueType();' . PHP_EOL
                    . 'integer Type=dom.GetObject(' . $SysVar . ').Type();' . PHP_EOL
                    . 'WriteLine(dom.GetObject(' . $SysVar . ').Value());' . PHP_EOL
                    . 'Timestamp=dom.GetObject(' . $SysVar . ').Timestamp();' . PHP_EOL;

            $HMScriptResult = $this->LoadHMScript($HMScript);
            if ($HMScriptResult === false) {
                return false;
            }
            $lines = explode("\r\n", $HMScriptResult);
            try {
                $xmlVar = @new SimpleXMLElement(utf8_encode(array_pop($lines)), LIBXML_NOBLANKS + LIBXML_NONET);
            } catch (Throwable $exc) {
                $this->SendDebug($SysVar, $exc->getMessage(), 0);
                trigger_error($this->Translate($exc->getMessage()), E_USER_NOTICE);
                $Result = false;
                continue;
            }
            if ((int) $xmlVar->Type == 2113) {
                if (!$this->ReadPropertyBoolean('EnableAlarmDP')) {
                    continue;
                } else {
                    $VarIdent = 'AlDP' . $SysVar;
                }
            }
            $xmlVar->addChild('Variable', implode("\r\n", $lines));
            $VarID = @$this->GetIDForIdent($VarIdent);
            $VarType = self::$CcuVarType[(int) $xmlVar->ValueType];
            $VarProfil = 'HM.SysVar' . (string) $this->InstanceID . '.' . (string) $SysVar;
            $VarName = (string) $xmlVar->Name;

            if (((int) $xmlVar->ValueType != VARIABLETYPE_STRING) && (!IPS_VariableProfileExists($VarProfil))) { // neu anlegen wenn VAR neu ist oder Profil nicht vorhanden
                $HMScript = 'Name=dom.GetObject(' . $SysVar . ').Name();' . PHP_EOL
                        . 'ValueSubType=dom.GetObject(' . $SysVar . ').ValueSubType();' . PHP_EOL
                        . 'ValueList=dom.GetObject(' . $SysVar . ').ValueList();' . PHP_EOL
                        . 'ValueName0=dom.GetObject(' . $SysVar . ').ValueName0();' . PHP_EOL
                        . 'ValueName1=dom.GetObject(' . $SysVar . ').ValueName1();' . PHP_EOL
                        . 'ValueMin=dom.GetObject(' . $SysVar . ').ValueMin();' . PHP_EOL
                        . 'ValueMax=dom.GetObject(' . $SysVar . ').ValueMax();' . PHP_EOL
                        . 'ValueUnit=dom.GetObject(' . $SysVar . ').ValueUnit();' . PHP_EOL;

                $HMScriptResult = $this->LoadHMScript($HMScript);
                if ($HMScriptResult === false) {
                    return false;
                }
                $xmlVar2 = $this->GetScriptXML($HMScriptResult);
                if ($xmlVar2 === false) {
                    $Result = false;
                    continue;
                }

                if (IPS_VariableProfileExists($VarProfil)) {
                    IPS_DeleteVariableProfile($VarProfil);
                }

                IPS_CreateVariableProfile($VarProfil, $VarType);
                switch ($VarType) {
                    case VARIABLETYPE_BOOLEAN:
                        if (isset($xmlVar2->ValueName0)) {
                            @IPS_SetVariableProfileAssociation($VarProfil, 0, (string) $xmlVar2->ValueName0, '', -1);
                        }
                        if (isset($xmlVar2->ValueName1)) {
                            @IPS_SetVariableProfileAssociation($VarProfil, 1, (string) $xmlVar2->ValueName1, '', -1);
                        }
                        break;
                    case VARIABLETYPE_FLOAT:
                        @IPS_SetVariableProfileDigits($VarProfil, strlen((string) $xmlVar2->ValueMin) - strpos('.', (string) $xmlVar2->ValueMin) - 1);
                        @IPS_SetVariableProfileValues($VarProfil, (float) $xmlVar2->ValueMin, (float) $xmlVar2->ValueMax, 1);
                        break;
                }
                if (isset($xmlVar2->ValueUnit)) {
                    @IPS_SetVariableProfileText($VarProfil, '', ' ' . (string) $xmlVar2->ValueUnit);
                }
                if ((isset($xmlVar2->ValueSubType)) && ((int) $xmlVar2->ValueSubType == 29)) {
                    foreach (explode(';', (string) $xmlVar2->ValueList) as $Index => $ValueList) {
                        @IPS_SetVariableProfileAssociation($VarProfil, $Index, trim($ValueList), '', -1);
                    }
                }
            }
            if ($VarID === false) {
                if ((int) $xmlVar->ValueType == VARIABLETYPE_STRING) {
                    $VarProfil = '';
                }
                $this->MaintainVariable($VarIdent, $VarName, $VarType, $VarProfil, 0, true);
                $this->EnableAction($VarIdent);
                $VarID = @$this->GetIDForIdent($VarIdent);
                if ((int) $xmlVar->ValueType != VARIABLETYPE_STRING) {
                    $OldVars[$VarID] = $SysVar;
                    $OldVarsChange = true;
                    $this->RegisterMessage($VarID, VM_DELETE);
                }
            }
            if (IPS_GetVariable($VarID)['VariableType'] != $VarType) {
                $this->SendDebug($SysVar, 'Type of CCU Systemvariable ' . IPS_GetName($VarID) . ' has changed.', 0);
                trigger_error(sprintf($this->Translate('Type of CCU Systemvariable %s has changed.'), IPS_GetName($VarID)), E_USER_NOTICE);
                $Result = false;
                continue;
            }
            $VarTime = new DateTime((string) $xmlVar->Timestamp . $CCUTimeZone);

            if (!(IPS_GetVariable($VarID)['VariableUpdated'] < ($TimeDiff + $VarTime->getTimestamp()))) {
                continue;
            }
            switch ($VarType) {
                case VARIABLETYPE_BOOLEAN:
                    if ((int) $xmlVar->Type == 2113) {
                        $this->ProcessAlarmVariable($VarIdent, $SysVar, $CCUTimeZone);
                    } else {
                        $this->SetValue($VarIdent, (string) $xmlVar->Variable == 'true');
                    }
                    break;
                case VARIABLETYPE_INTEGER:
                    $this->SetValue($VarIdent, (int) $xmlVar->Variable);
                    break;
                case VARIABLETYPE_FLOAT:
                    $this->SetValue($VarIdent, (float) $xmlVar->Variable);
                    break;
                case VARIABLETYPE_STRING:
                    $this->SetValue($VarIdent, (string) $xmlVar->Variable);
                    break;
            }
        }
        if ($OldVarsChange) {
            $this->SystemVars = $OldVars;
        }
        return $Result;
    }

    /**
     * Liest die Daten einer Systemvariable vom Typ 'Alarm' aus. Visualisiert den Status und startet bei Bedarf ein Script in IPS.
     *
     * @param string $VarIdent    Ident der Alarmvariable.
     * @param string $SysVar      ID der Alarmvariable in der CCU.
     * @param string $CCUTimeZone Die Zeitzone der CCU.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    private function ProcessAlarmVariable(string $VarIdent, string $SysVar, string $CCUTimeZone)
    {
        $HMScript = 'Value = dom.GetObject(' . $SysVar . ').Value();
                        string FirstTime = dom.GetObject(' . $SysVar . ').AlOccurrenceTime();
                        string LastTime = dom.GetObject(' . $SysVar . ').LastTriggerTime();
                        integer LastTriggerID = dom.GetObject(' . $SysVar . ').LastTriggerID();
                        if( LastTriggerID == ID_ERROR )
                        {
                         LastTriggerID = dom.GetObject(' . $SysVar . ').AlTriggerDP();
                        }
                        string ChannelName = "";
                        string Room = "";
                        object oLastTrigger = dom.GetObject( LastTriggerID );
                        if( oLastTrigger )
                        {
                         object oLastTriggerChannel = dom.GetObject( oLastTrigger.Channel() );
                         if( oLastTriggerChannel )
                         {
                          string ChannelName = oLastTriggerChannel.Name();
                          string sRID;
                          foreach( sRID, oLastTriggerChannel.ChnRoom() )
                          {
                           object oRoom = dom.GetObject( sRID );
                           if( oRoom )
                           {
                            Room = oRoom.Name();
                           }
                          }
                         }
                        }
                       }' . PHP_EOL;

        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xmlData = $this->GetScriptXML($HMScriptResult);
        if ($xmlData === false) {
            return false;
        }
        $ParentID = $this->GetIDForIdent($VarIdent);
        $ScriptData = [];
        $ScriptData['SENDER'] = 'AlarmDP';
        $ScriptData['VARIABLE'] = $ParentID;
        $ScriptData['OLDVALUE'] = GetValueBoolean($ParentID);
        $ScriptData['VALUE'] = (string) $xmlData->Value == 'true';
        if ($ScriptData['VALUE']) {
            $Time = new DateTime((string) $xmlData->LastTime . $CCUTimeZone);
            $ScriptData['LastTime'] = $Time->getTimestamp();
            $Time = new DateTime((string) $xmlData->FirstTime . $CCUTimeZone);
            $ScriptData['FirstTime'] = $Time->getTimestamp();
            $ScriptData['Room'] = (string) $xmlData->Room;
            $ScriptData['ChannelName'] = (string) $xmlData->ChannelName;
            $Channel = explode('.', (string) $xmlData->oLastTrigger);
            if (count($Channel) >= 2) {
                $ScriptData['Channel'] = $Channel[1];
                $ScriptData['DP'] = $Channel[2];
            } else {
                $ScriptData['Channel'] = 'unbekannt';
                $ScriptData['DP'] = 'unbekannt';
            }
        } else {
            $ScriptData['LastTime'] = 0;
            $ScriptData['FirstTime'] = 0;
            $ScriptData['Room'] = '';
            $ScriptData['ChannelName'] = '';
            $ScriptData['Channel'] = '';
            $ScriptData['DP'] = '';
        }

        $this->SetValue($VarIdent, $ScriptData['VALUE']);
        $LastTimeID = $this->RegisterSubVariable($ParentID, 'LastTime', 'Letzter Alarm', VARIABLETYPE_INTEGER, '~UnixTimestamp');
        SetValue($LastTimeID, $ScriptData['LastTime']);
        $FirstTimeID = $this->RegisterSubVariable($ParentID, 'FirstTime', 'Erster Alarm', VARIABLETYPE_INTEGER, '~UnixTimestamp');
        SetValue($FirstTimeID, $ScriptData['FirstTime']);
        $RoomID = $this->RegisterSubVariable($ParentID, 'Room', 'Raum', VARIABLETYPE_STRING);
        SetValue($RoomID, $ScriptData['Room']);
        $ChannelNameID = $this->RegisterSubVariable($ParentID, 'ChannelName', 'Name', VARIABLETYPE_STRING);
        SetValue($ChannelNameID, $ScriptData['ChannelName']);

        $ScriptID = $this->ReadPropertyInteger('AlarmScriptID');
        if ($ScriptID > 0) {
            IPS_RunScriptEx($ScriptID, $ScriptData);
        }
        return true;
    }

    /**
     * Schreibt einen Wert in eine Systemvariable auf der CCU.
     *
     * @param string $Parameter Der IDENT der IPS-Statusvariable = Die ID der Systemvariable in der CCU.
     * @param string $ValueStr  Der neue Wert der Systemvariable.
     *
     * @throws Exception Wenn CUU nicht erreichbar oder Daten nicht auswertbar sind.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    private function WriteSysVar(string $Parameter, string $ValueStr)
    {
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return false;
        }
        $HMScript = 'State=dom.GetObject(' . $Parameter . ').State("' . $ValueStr . '");';
        $HMScriptResult = $this->LoadHMScript($HMScript);
        if ($HMScriptResult === false) {
            return false;
        }
        $xml = $this->GetScriptXML($HMScriptResult);
        if ($xml === false) {
            return false;
        }
        if ((string) $xml->State == 'true') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Erstellt eine Untervariable in IPS.
     *
     * @param int    $ParentID IPS-ID der übergeordneten Variable.
     * @param string $Ident    IDENT der neuen Statusvariable.
     * @param string $Name     Name der neuen Statusvariable.
     * @param int    $Type     Der zu erstellende Typ von Variable.
     * @param string $Profile  Das dazugehörige Variabelprofil.
     * @param int    $Position Position der Variable.
     *
     * @throws Exception Wenn Variable nicht erstellt werden konnte.
     *
     * @return int IPS-ID der neuen Variable.
     */
    private function RegisterSubVariable($ParentID, $Ident, $Name, $Type, $Profile = '', $Position = 0)
    {
        if ($Profile != '') {
            if (IPS_VariableProfileExists('~' . $Profile)) {
                $Profile = '~' . $Profile;
            }
            if (!IPS_VariableProfileExists($Profile)) {
                throw new Exception('Profile with name ' . $Profile . ' does not exist', E_USER_NOTICE);
            }
        }

        $vid = @IPS_GetObjectIDByIdent($Ident, $ParentID);

        if ($vid === false) {
            $vid = 0;
        }

        if ($vid > 0) {
            if (!IPS_VariableExists($vid)) {
                throw new Exception('Ident with name ' . $Ident . ' is used for wrong object type', E_USER_NOTICE); //bail out
            }
            if (IPS_GetVariable($vid)['VariableType'] != $Type) {
                IPS_DeleteVariable($vid);
                $vid = 0;
            }
        }

        if ($vid == 0) {
            $vid = IPS_CreateVariable($Type);

            IPS_SetParent($vid, $ParentID);
            IPS_SetIdent($vid, $Ident);
            IPS_SetName($vid, $Name);
            IPS_SetPosition($vid, $Position);
            //IPS_SetReadOnly($vid, true);
        }

        IPS_SetVariableCustomProfile($vid, $Profile);
        if (!in_array($vid, $this->GetReferenceList())) {
            $this->RegisterReference($vid);
        }
        return $vid;
    }
}

/* @} */
