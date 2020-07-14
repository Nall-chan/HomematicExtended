<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       3.0
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticDisWM55 ist die Klasse für das IPS-Modul 'HomeMatic Dis-WM55'.
 * Erweitert HMBase.
 *
 * @property int $Page Die aktuelle Seite.
 * @property array $HMEventData [self::$PropertysName]
 * ['HMDeviceAddress'] => string $HMDeviceAddress Die Geräte-Adresse des Trigger.
 * ['HMDeviceDatapoint'] => string $HMDeviceDatapoint  Der zu überwachende Datenpunkt vom $HMDeviceAddress
 * @property array $Events [self::$PropertysName]  Die IPS-ID der Variable des Datenpunkt welcher eine Aktualisierung auslöst.
 */
class HomeMaticDisWM55 extends HMBase
{
    private static $EmptyHMEventData = [
        'HMDeviceAddress'   => '',
        'HMDeviceDatapoint' => ''
    ];
    private static $PropertysName = [
        'PageUpID'     => 0,
        'PageDownID'   => 0,
        'ActionUpID'   => 0,
        'ActionDownID' => 0
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterHMPropertys('XXX9999995');
        $this->RegisterPropertyBoolean('EmulateStatus', false);
        $this->RegisterPropertyInteger('PageUpID', 0);
        $this->RegisterPropertyInteger('PageDownID', 0);
        $this->RegisterPropertyInteger('ActionUpID', 0);
        $this->RegisterPropertyInteger('ActionDownID', 0);
        $this->RegisterPropertyInteger('MaxPage', 1);
        $this->RegisterPropertyInteger('Timeout', 0);

        $ID = @$this->GetIDForIdent('DisplayScript');
        if ($ID === false) {
            $ID = $this->RegisterScript('DisplayScript', 'Display Script', $this->CreateDisplayScript(), -1);
        }
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger('ScriptID', $ID);

        $this->UnregisterVariable('PAGE');
        $this->Page = 0;
        $this->Events = self::$PropertysName;
        $this->HMEventData = [
            'PageUpID'     => self::$EmptyHMEventData,
            'PageDownID'   => self::$EmptyHMEventData,
            'ActionUpID'   => self::$EmptyHMEventData,
            'ActionDownID' => self::$EmptyHMEventData
        ];

        $this->RegisterTimer('DisplayTimeout', 0, 'HM_ResetTimer($_IPS[\'TARGET\']);');
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @param int       $TimeStamp
     * @param int       $SenderID
     * @param int       $Message
     * @param array|int $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                $this->UnregisterReference($SenderID);
                foreach (array_keys(self::$PropertysName) as $Name) {
                    if ($SenderID == $this->ReadPropertyInteger($Name)) {
                        $Events = $this->Events;
                        $Events[$Name] = 0;
                        $this->Events = $Events;
                        $HMEventData = $this->HMEventData;
                        $HMEventData[$Name] = self::$EmptyHMEventData;
                        $this->HMEventData = $HMEventData;
                        $this->SetNewConfig();
                    }
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
        $this->SetNewConfig();
    }

    //################# Datenaustausch

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);
        unset($Data->DataID);
        unset($Data->VariableValue);
        $this->SendDebug('Receive', $Data, 0);
        $ReceiveData = ['HMDeviceAddress' => (string) $Data->DeviceID, 'HMDeviceDatapoint' => (string) $Data->VariableName];
        $Action = array_search($ReceiveData, $this->HMEventData);
        if ($Action === false) {
            return;
        }

        try {
            $this->RunDisplayScript($Action);
        } catch (Exception $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            trigger_error($this->Translate($exc->getMessage()), $exc->getCode());
        }
    }

    /**
     *  Wird bei einem timeout ausgeführt und setzt die aktuelle Seite wieder auf Null.
     */
    public function ResetTimer()
    {
        $this->Page = 0;
        $this->SetTimerInterval('DisplayTimeout', 0);
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
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

    //################# PRIVATE

    /**
     * Überführt die Config in die Filter.
     */
    private function SetNewConfig()
    {
        if (IPS_GetKernelRunlevel() == KR_READY) {
            if ($this->CheckConfig()) {
                $Lines = [];
                $Events = $this->Events;
                foreach ($this->HMEventData as $Event => $Trigger) {
                    if ($Events[$Event] != 0) {
                        $Lines[] = '.*"DeviceID":"' . $Trigger['HMDeviceAddress'] . '","VariableName":"' . $Trigger['HMDeviceDatapoint'] . '".*';
                    }
                }
                $Line = implode('|', $Lines);
                $this->SetReceiveDataFilter('(' . $Line . ')');
                $this->SetSummary($Trigger['HMDeviceAddress']);
                return;
            }
        }
        $this->HMEventData = [
            'PageUpID'     => self::$EmptyHMEventData,
            'PageDownID'   => self::$EmptyHMEventData,
            'ActionUpID'   => self::$EmptyHMEventData,
            'ActionDownID' => self::$EmptyHMEventData
        ];
        $this->Page = 0;
        $this->SetReceiveDataFilter('.*9999999999.*');
        $this->SetSummary('');
    }

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     *
     * @return bool True wenn Konfig ok, sonst false.
     */
    private function CheckConfig()
    {
        $Result = true;
        $OldHMEventDatas = $this->HMEventData;
        $OldEvents = $this->Events;
        $Events = [];
        foreach (array_keys(self::$PropertysName) as $Name) {
            $Event = $this->ReadPropertyInteger($Name);
            if ($Event != $OldEvents[$Name]) {
                if ($OldEvents[$Name] > 0) {
                    $this->UnregisterMessage($OldEvents[$Name], VM_DELETE);
                    $this->UnregisterReference($OldEvents[$Name]);
                    $OldEvents[$Name] = 0;
                }

                if ($Event > 0) {
                    if (in_array($Event, $Events)) { //doppelt ?
                        $OldHMEventDatas[$Name] = self::$EmptyHMEventData;
                        $OldEvents[$Name] = 0;
                        $Result = false;
                        continue;
                    }
                    $HMEventData = $this->GetDisplayAddress($Event);
                    if ($HMEventData === false) {
                        $OldHMEventDatas[$Name] = self::$EmptyHMEventData;
                        $OldEvents[$Name] = 0;
                        $Result = false;
                        continue;
                    }
                    $OldHMEventDatas[$Name] = $HMEventData;
                    $OldEvents[$Name] = $Event;
                    $this->RegisterMessage($Event, VM_DELETE);
                    $this->RegisterReference($Event);
                }
            }
            if ($Event > 0) {
                $Events[] = $Event;
            }
        }

        $this->HMEventData = $OldHMEventDatas;
        $this->Events = $OldEvents;

        if ($Result === false) {
            $this->SetStatus(IS_EBASE + 2);
            return false;
        }

        if (count($Events) == 0) {
            $this->SetStatus(IS_INACTIVE);
            return false;
        }

        if ($this->ReadPropertyInteger('ScriptID') == 0) {
            $this->SetStatus(IS_EBASE + 3);
            return false;
        }

        if ($this->ReadPropertyInteger('Timeout') < 0) {
            $this->SetStatus(IS_EBASE + 4);
            return false;
        }

        if ($this->ReadPropertyInteger('MaxPage') < 0) {
            $this->SetStatus(IS_EBASE + 5);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return true;
    }

    /**
     * Prüft und holt alle Daten zu den Quell-Variablen und Instanzen.
     *
     * @param int $EventID IPD-VarID des Datenpunktes, welcher als Event dient.
     *
     * @return array|bool Array mit den Daten zum Datenpunkt. False im Fehlerfall.
     */
    private function GetDisplayAddress(int $EventID)
    {
        if (!IPS_VariableExists($EventID)) {
            return false;
        }
        $parent = IPS_GetParent($EventID);
        if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] != '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}') {
            return false;
        }
        return [
            'HMDeviceAddress'   => IPS_GetProperty($parent, 'Address'),
            'HMDeviceDatapoint' => IPS_GetObject($EventID)['ObjectIdent']
        ];
    }

    /**
     * Führt das User-Script aus und überträgt das Ergebnis an die CCU.
     *
     * @param string $Action Die auszuführende Aktion.
     *
     * @throws Exception Wenn CCU nicht erreicht wurde.
     */
    private function RunDisplayScript($Action)
    {
        if (!$this->HasActiveParent()) {
            throw new Exception('Instance has no active parent instance!', E_USER_NOTICE);
        }

        if ($this->HMAddress == '') {
            $this->RegisterParent();
        }

        $Page = $this->Page;
        $MaxPage = $this->ReadPropertyInteger('MaxPage');
        switch ($Action) {
            case 'PageUpID':
                $Page = ($Page == $MaxPage ? 1 : $Page + 1);
                $ActionString = 'UP';
                $this->Page = $Page;
                break;
            case 'PageDownID':
                $Page = ($Page <= 1 ? $MaxPage : $Page - 1);
                $ActionString = 'DOWN';
                $this->Page = $Page;
                break;
            case 'ActionUpID':
                $ActionString = 'ActionUP';
                break;
            case 'ActionDownID':
                $ActionString = 'ActionDOWN';
                break;
        }
        $this->SendDebug('Action', $ActionString, 0);
        $ScriptID = $this->ReadPropertyInteger('ScriptID');
        if ($ScriptID != 0) {
            $Result = IPS_RunScriptWaitEx($ScriptID, ['SENDER' => 'HMDisWM55', 'ACTION' => $ActionString, 'PAGE' => $Page, 'EVENT' => $this->InstanceID]);
            $ResultData = json_decode($Result);
            if (is_null($ResultData)) {
                throw new Exception('Error in display-script.', E_USER_NOTICE);
            }
            $this->SendDebug('DisplayScript', $ResultData, 0);
            $Data = $this->ConvertDisplayData($ResultData);
            $url = 'GetDisplay.exe';
            $HMScript = 'string DisplayKeySubmit;' . PHP_EOL;
            $HMScript .= 'DisplayKeySubmit=dom.GetObject("BidCos-RF.' . (string) $this->HMEventData[$Action]['HMDeviceAddress'] . '.SUBMIT").ID();' . PHP_EOL;
            $HMScript .= 'State=dom.GetObject(DisplayKeySubmit).State("' . $Data . '");' . PHP_EOL;

            try {
                $this->LoadHMScript($url, $HMScript);
            } catch (Exception $exc) {
                throw new Exception('Error on send data to HM-Dis-WM55.', E_USER_NOTICE);
            }
        }
        $Timeout = $this->ReadPropertyInteger('Timeout');
        if ($Timeout > 0) {
            $this->SetTimerInterval('DisplayTimeout', 0);
            $this->SetTimerInterval('DisplayTimeout', $Timeout * 1000);
        }
    }

    /**
     * Konvertiert die Daten in ein für das Display benötigte Format.
     *
     * @param object $Data Enthält die Daten für das Display
     *
     * @return string Die konvertierten Daten als String.
     */
    private function ConvertDisplayData($Data)
    {
        $SendData = '0x02';
        foreach ($Data as $Line) {
            if ((string) $Line->Text != '') {
                $SendData .= ',0x12';
                for ($i = 0; $i < strlen((string) $Line->Text); $i++) {
                    $SendData .= ',0x' . dechex(ord((string) $Line->Text[$i]));
                }
                $SendData .= ',0x11';
                $SendData .= ',0x' . dechex((int) $Line->Color);
            }
            if ((int) $Line->Icon != 0) {
                $SendData .= ',0x13';
                $SendData .= ',0x' . dechex((int) $Line->Icon);
            }
            $SendData .= ',0x0A';
        }
        $SendData .= ',0x03';
        return $SendData;
    }

    /**
     * Liefert das Script welches im Objektbaum als Vorlage für das DisplayScript angelegt wird.
     *
     * @param type $ID Die IPS-ID des HM_OLED Scriptes mit den Konstanten für das Display-Script.
     *
     * @return string
     */
    private function CreateDisplayScript()
    {
        return file_get_contents(__DIR__ . '/Display-Taster-Script-Vorlage.php');
    }
}

/* @} */
