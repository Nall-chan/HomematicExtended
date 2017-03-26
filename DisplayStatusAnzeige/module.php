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
 * @version       2.38
 */
require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

/**
 * HMDisWM55 ist die Klasse für das IPS-Modul 'HomeMatic Dis-WM55'.
 * Erweitert HMBase 
 *
 * @property int $Page Die aktuelle Seite.
 * @property array $HMEventData [self::$PropertysName]  
  ["HMDeviceAddress"] => string $HMDeviceAddress Die Geräte-Adresse des Trigger.
  ["HMDeviceDatapoint"] => string $HMDeviceDatapoint  Der zu überwachende Datenpunkt vom $HMDeviceAddress
 * @property array $Events [self::$PropertysName]  Die IPS-ID der Variable des Datenpunkt welcher eine Aktualisierung auslöst.
 */
class HMDisWM55 extends HMBase
{

    private static $EmptyHMEventData = array(
        "HMDeviceAddress" => "",
        "HMDeviceDatapoint" => ""
    );
    private static $PropertysName = array(
        "PageUpID" => 0,
        "PageDownID" => 0,
        "ActionUpID" => 0,
        "ActionDownID" => 0
    );

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterHMPropertys('XXX9999995');
        $this->RegisterPropertyBoolean("EmulateStatus", false);

        $this->RegisterPropertyInteger("PageUpID", 0);
        $this->RegisterPropertyInteger("PageDownID", 0);
        $this->RegisterPropertyInteger("ActionUpID", 0);
        $this->RegisterPropertyInteger("ActionDownID", 0);
        $this->RegisterPropertyInteger("MaxPage", 1);
        $this->RegisterPropertyInteger("Timeout", 0);

        $ID_OLED = $this->RegisterScript('HM_OLED', 'HM_OLED.inc.php', $this->CreateHM_OLEDScript(), -2);
        IPS_SetHidden($ID, true);
        $ID = @$this->GetIDForIdent('DisplayScript');
        if ($ID === false)
            $ID = $this->RegisterScript('DisplayScript', 'Display Script', $this->CreateDisplayScript($ID_OLED), -1);
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger("ScriptID", $ID);

        $this->UnregisterVariable('PAGE');
        $this->Page = 0;
        $this->Events = self::$PropertysName;
        $this->HMEventData = array(
            "PageUpID" => self::$EmptyHMEventData,
            "PageDownID" => self::$EmptyHMEventData,
            "ActionUpID" => self::$EmptyHMEventData,
            "ActionDownID" => self::$EmptyHMEventData
        );

        $this->RegisterTimer('DisplayTimeout', 0, 'HM_ResetTimer($_IPS[\'TARGET\']);');
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
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message)
        {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                foreach (array_keys(self::$PropertysName) as $Name)
                {
                    if ($SenderID == $this->ReadPropertyInteger($Name))
                    {
                        IPS_SetProperty($this->InstanceID, $Name, 0);
                        IPS_ApplyChanges($this->InstanceID);
                    }
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() == KR_READY)
        {
            if ($this->CheckConfig())
            {
                $Lines = array();
                $Events = $this->Events;
                foreach ($this->HMEventData as $Event => $Trigger)
                {
                    if ($Events[$Event] != 0)
                        $Lines[] = '.*"DeviceID":"' . $Trigger['HMDeviceAddress'] . '","VariableName":"' . $Trigger['HMDeviceDatapoint'] . '".*';
                }
                $Line = implode('|', $Lines);

                $this->SetReceiveDataFilter("(" . $Line . ")");
                $this->SetSummary($Trigger['HMDeviceAddress']);
                return;
            }
        }
        $this->HMEventData = array(
            "PageUpID" => self::$EmptyHMEventData,
            "PageDownID" => self::$EmptyHMEventData,
            "ActionUpID" => self::$EmptyHMEventData,
            "ActionDownID" => self::$EmptyHMEventData
        );
        $this->Page = 0;
        $this->SetReceiveDataFilter(".*9999999999.*");
        $this->SetSummary('');
        return;
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

################## Datenaustausch

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);
        unset($Data->DataID);
        unset($Data->VariableValue);
        $this->SendDebug('Receive', $Data, 0);
        $ReceiveData = array("HMDeviceAddress" => (string) $Data->DeviceID, "HMDeviceDatapoint" => (string) $Data->VariableName);
        $Action = array_search($ReceiveData, $this->HMEventData);
        if ($Action === false)
            return;
        try
        {
            $this->RunDisplayScript($Action);
        }
        catch (Exception $exc)
        {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            trigger_error($exc->getMessage(), $exc->getCode());
        }
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
        $Result = true;
        $OldHMEventDatas = $this->HMEventData;
        $OldEvents = $this->Events;
        $Events = array();
        foreach (array_keys(self::$PropertysName) as $Name)
        {
            $Event = $this->ReadPropertyInteger($Name);
            if ($Event <> $OldEvents[$Name])
            {

                if ($OldEvents[$Name] > 0)
                {
                    $this->UnregisterMessage($OldEvents[$Name], VM_DELETE);
                    $OldEvents[$Name] = 0;
                }

                if ($Event > 0)
                {
                    if (in_array($Event, $Events)) //doppelt ?
                    {
                        $OldHMEventDatas[$Name] = self::$EmptyHMEventData;
                        $OldEvents[$Name] = 0;
                        $Result = false;
                        continue;
                    }
                    $HMEventData = $this->GetDisplayAddress($Event);
                    if ($HMEventData === false)
                    {
                        $OldHMEventDatas[$Name] = self::$EmptyHMEventData;
                        $OldEvents[$Name] = 0;
                        $Result = false;
                        continue;
                    }
                    $OldHMEventDatas[$Name] = $HMEventData;
                    $OldEvents[$Name] = $Event;
                    $this->RegisterMessage($Event, VM_DELETE);
                }
            }
            if ($Event > 0)
                $Events[] = $Event;
        }

        $this->HMEventData = $OldHMEventDatas;
        $this->Events = $OldEvents;

        if ($Result === false)
        {
            $this->SetStatus(IS_EBASE + 2);
            return false;
        }

        if (count($Events) == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            return false;
        }

        if ($this->ReadPropertyInteger('ScriptID') == 0)
        {
            $this->SetStatus(IS_EBASE + 3);
            return false;
        }

        if ($this->ReadPropertyInteger('Timeout') < 0)
        {
            $this->SetStatus(IS_EBASE + 4);
            return false;
        }

        if ($this->ReadPropertyInteger('MaxPage') < 0)
        {
            $this->SetStatus(IS_EBASE + 5);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);

        return true;
    }

    /**
     * Prüft und holt alle Daten zu den Quell-Variablen und Instanzen.
     * 
     * @access private
     * @param int $EventID IPD-VarID des Datenpunktes, welcher als Event dient.
     * @return array|boolean Array mit den Daten zum Datenpunkt. False im Fehlerfall.
     */
    private function GetDisplayAddress(int $EventID)
    {
        $parent = IPS_GetParent($EventID);
        if (IPS_GetInstance($parent)['ModuleInfo']['ModuleID'] <> '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}')
            return false;
        return array(
            "HMDeviceAddress" => IPS_GetProperty($parent, 'Address'),
            "HMDeviceDatapoint" => IPS_GetObject($EventID)['ObjectIdent']
        );
    }

    /**
     * Führt das User-Script aus und überträgt das Ergebnis an die CCU.
     * 
     * @access private
     * @param string $Action Die auszuführende Aktion.
     * @throws Exception Wenn CCU nicht erreicht wurde.
     */
    private function RunDisplayScript($Action)
    {
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent Instance!", E_USER_WARNING);

        if ($this->HMAddress == '')
            throw new Exception("Instance has no active Parent Instance!", E_USER_WARNING);

        $Page = $this->Page;
        $MaxPage = $this->ReadPropertyInteger('MaxPage');
        switch ($Action)
        {
            case "PageUpID":
                if ($Page == $MaxPage)
                    $Page = 1;
                else
                    $Page++;
                $ActionString = "UP";
                $this->Page = $Page;
                break;
            case "PageDownID":
                if ($Page <= 1)
                    $Page = $MaxPage;
                else
                    $Page--;
                $ActionString = "DOWN";
                $this->Page = $Page;
                break;
            case "ActionUpID":
                $ActionString = "ActionUP";
                break;
            case "ActionDownID":
                $ActionString = "ActionDOWN";
                break;
        }
            $this->SendDebug('Action', $ActionString, 0);
        $ScriptID = $this->ReadPropertyInteger('ScriptID');
        if ($ScriptID <> 0)
        {
            $Result = IPS_RunScriptWaitEx($ScriptID, array('SENDER' => 'HMDisWM55', 'ACTION' => $ActionString, 'PAGE' => $Page, 'EVENT' => $this->InstanceID));
            $ResultData = json_decode($Result);
            if (is_null($ResultData))
                throw new Exception("Error in Display Script.", E_USER_NOTICE);
            $this->SendDebug('DisplayScript', $ResultData, 0);            
            $Data = $this->ConvertDisplayData($ResultData);
            $url = 'GetDisplay.exe';
            $HMScript = 'string DisplayKeySubmit;' . PHP_EOL;
            $HMScript.='DisplayKeySubmit=dom.GetObject("BidCos-RF.' . (string) $this->HMEventData[$Action]['HMDeviceAddress'] . '.SUBMIT").ID();' . PHP_EOL;
            $HMScript .= 'State=dom.GetObject(DisplayKeySubmit).State("' . $Data . '");' . PHP_EOL;
            try
            {
                $this->LoadHMScript($url, $HMScript);
            }
            catch (Exception $exc)
            {
                throw new Exception('Error on send Data to HM-Dis-WM55.', E_USER_NOTICE);
            }
        }
        $Timeout = $this->ReadPropertyInteger('Timeout');
        if ($Timeout > 0)
        {
            $this->SetTimerInterval('DisplayTimeout', 0);
            $this->SetTimerInterval('DisplayTimeout', $Timeout * 1000);
        }
    }

    /**
     * Konvertiert die Daten in ein für das Display benötigte Format.
     * @param object $Data Enthält die Daten für das Display
     * @return string Die konvertierten Daten als String.
     */
    private function ConvertDisplayData($Data)
    {
        $SendData = "0x02";
        foreach ($Data as $Line)
        {
            if ((string) $Line->Text <> "")
            {
                $SendData.=",0x12";
                for ($i = 0; $i < strlen((string) $Line->Text); $i++)
                {
                    $SendData .= ",0x" . dechex(ord((string) $Line->Text[$i]));
                }
                $SendData.=",0x11";
                $SendData .= ",0x" . dechex((int) $Line->Color);
            }
            if ((int) $Line->Icon <> 0)
            {
                $SendData.=",0x13";
                $SendData .= ",0x" . dechex((int) $Line->Icon);
            }
            $SendData.=",0x0A";
        }
        $SendData.=",0x03";
        return $SendData;
    }

    /**
     * Liefert das Script welches im Objektbaum als HM_OLED-Script mit den Kostanten für das DisplayScript angelegt wird.
     * 
     * @access private
     * @return string
     */
    private function CreateHM_OLEDScript()
    {
        $Script = '<?
//-----------------------------------
// Definition der Werte für die Icons
//-----------------------------------
// 0x80 AUS                 Icon_on
// 0x81 EIN                 Icon_off
// 0x82 OFFEN               Icon_open
// 0x83 geschlossen         Icon_closed
// 0x84 fehler              Icon_error
// 0x85 alles ok            Icon_ok
// 0x86 information         Icon_information
// 0x87 neue nachricht      Icon_message
// 0x88 servicemeldung      Icon_service
// 0x89 Signal grün         Icon_green
// 0x8A Signal gelb         Icon_yellow
// 0x8B Signal rot          Icon_red
//      ohne Icon           Icon_no
define ("Icon_on"           ,0x80);
define ("Icon_off"          ,0x81);
define ("Icon_open"         ,0x82);
define ("Icon_closed"       ,0x83);
define ("Icon_error"        ,0x84);
define ("Icon_ok"           ,0x85);
define ("Icon_information"  ,0x86);
define ("Icon_message"      ,0x87);
define ("Icon_service"      ,0x88);
define ("Icon_signal_green" ,0x89);
define ("Icon_signal_yellow",0x8A);
define ("Icon_signal_red"   ,0x8B);
define ("Icon_no"           ,0);

//------------------------------------
// Definition der Werte für die Farben
//------------------------------------
// 0x80 weiss               Color_white
// 0x81 rot                 Color_red
// 0x82 orange              Color_orange
// 0x83 gelb                Color_yellow
// 0x84 grün                Color_green
// 0x85 blau                Color_blue
define ("Color_white"       ,0x80);
define ("Color_red"         ,0x81);
define ("Color_orange"      ,0x82);
define ("Color_yellow"      ,0x83);
define ("Color_green"       ,0x84);
define ("Color_blue"        ,0x85);

function text_encode ($string)
{
   $umlaut =  array("Ä"   ,"Ö"   ,"Ü"   ,"ä"   ,"ö"   ,"ü"   ,"ß"   ,":"   );
   $hex_neu = array(chr(0x5b),chr(0x23),chr(0x24),chr(0x7b),chr(0x7c),chr(0x7d),chr(0x5f),chr(0x3a));
   $return = str_replace($umlaut, $hex_neu, $string);
   return $return;
}

?>';
        return $Script;
    }

    /**
     * Liefert das Script welches im Objektbaum als Vorlage für das DisplayScript angelegt wird.
     * 
     * @access private
     * @param type $ID Die IPS-ID des HM_OLED Scriptes mit den Konstanten für das Display-Script.
     * @return string
     */
    private function CreateDisplayScript($ID)
    {
        $Script = '<?
### GRUNDFUNKTION
/*
Beispiel für das Zusammenstellern der Daten für die Dis-WM55 Instanz.
Das Script wird als "Display-Script" in der dazugehörigen Dis-WM55 Instanze eingetragen.
Die vorbereiteten Daten für das Display werden als JSON kodierter String an die
Dis-WM55 Instanz als Rückgabewert "Script-Result" übergeben.
Beispiel der erzeugten Daten:
{"1":{"Text":"SEITE 1","Icon":130,"Color":129},"2":{"Text":"Zeile2","Icon":0,"Color":129},"3":{"Text":"Zeile3","Icon":130,"Color":130},"4":{"Text":"Zeile4","Icon":0,"Color":130},"5":{"Text":"Zeile5","Icon":131,"Color":132},"6":{"Text":"Zeile6","Icon":0,"Color":132}}

Der JSON-String wird aus einem Array erzeugt, welches folgendem Aufbau haben
__MUSS__, damit die Dis-WM55 Instanz die Daten verarbeiten und an das Display
senden kann.
Zeile[1]["Text"]  = Text Zeile 1
Zeile[1]["Icon"]  = Icon Zeile 1
Zeile[1]["Color"]  = Farbe Zeile 1
Zeile[2]["Text"]  = Text Zeile 2
Zeile[2]["Icon"]  = Icon Zeile 2
Zeile[2]["Color"]  = Farbe Zeile 2
.
.
.
Zeile[6]["Text"]  = Text Zeile 6
Zeile[6]["Icon"]  = Icon Zeile 6
Zeile[6]["Color"]  = Farbe Zeile 6

Um nicht immer die Zahlen für die Icons und Farben eintragen zu müssen wurden
Konstanten definiert.
Des weiteresn müssen Textzeilen mit der Funktion text_encode("Zeile mit Umlaut")
übergeben werden, wenn deutsche Umlaute in der Zeile verwendet werden.

Folgende Zeichen werden von der Anzeige zur Darstellung umgewandelt:
 \' => "="
 ] => "&"
 ; => Sanduhr
 < => Pfeil nach links oben
 = => Pfeil nach links unten
 @ => Pfeil nach unten (großes "V")
 > => Pfeil nach oben ("V" im Kopfstand)
*/

### VERWENDUNG VON $_IPS

/*
Die Dis-WM55 Instanz stellt über die IPS-Systemvariable $_IPS folgende Daten zur Verfügung:

(string) $_IPS["ACTION"]
	"UP"				=>  Trigger für Taste-Hoch wurde ausgelößt
	"DOWN"			=>  Trigger für Taste-Runter wurde ausgelößt
	"ActionUP"		=>  Trigger für Aktion-Hoch wurde ausgelößt
	"ActionDOWN"	=>  Trigger für Aktion-Runter wurde ausgelößt

(int) $_IPS["PAGE"]
	Die "Seite" welche dargestellt oder deren Aktion ausgeführt werden soll.

(string) $_IPS["SENDER"] => "HMDisWM55"
	Fester Wert
	
(int) $_IPS["EVENT"]
	Die Instanz-ID der HMDis-WM55 Instanz, welche dieses Script ausführt.


Auf der Basis der Variable $_IPS["PAGE"] ist es nun möglich verschiedene Daten
je nach "Seite" zu berechnen und übergeben.
Ebenso ist es möglich (z.B. durch langen und kurzen Tastendruck) zwischen UP/DOWN
und ActionUP/ActionDOWN zu unterscheiden und so Aktionen wie das Schalten von Licht ausführen zu lassen.

Natürlich kann man auch nur kurze Tastendrücke verwenden und z.B. Kanal:2 als ActionUP und Kanal:1 als DOWN zu definieren.

*/
if ($_IPS["SENDER"] <> "HMDisWM55")
{
    echo "Dieses Skript wird automatisch über die Homematic Dis-WM55 Instanz ausgeführt";
    return;
}
include IPS_GetScriptFile(' . $ID . '); // Konstanten für die Icons und Farben

if (($_IPS["ACTION"] == "UP") or ( $_IPS["ACTION"] == "DOWN"))
{
    switch ($_IPS["PAGE"])
    {
        case 1:  // Seite 1

            $display_line[1] = array("Text" => "SEITE 1",
                "Icon" => Icon_open,
                "Color" => Color_red);

            $display_line[2] = array("Text" => "Zeile2",
                "Icon" => Icon_no,
                "Color" => Color_red);

            $display_line[3] = array("Text" => "Zeile3",
                "Icon" => Icon_open,
                "Color" => Color_orange);

            $display_line[4] = array("Text" => "Zeile4",
                "Icon" => Icon_no,
                "Color" => Color_orange);

            $display_line[5] = array("Text" => "Zeile5",
                "Icon" => Icon_closed,
                "Color" => Color_green);

            $display_line[6] = array("Text" => "Zeile6",
                "Icon" => Icon_no,
                "Color" => Color_green);
            break;
        case 2:  // Seite 2
            $display_line[1] = array("Text" => ":",
                "Icon" => Icon_no,
                "Color" => Color_white);

            $display_line[2] = array("Text" => "SEITE 2",
                "Icon" => Icon_open,
                "Color" => Color_orange);

            $display_line[3] = array("Text" => "",
                "Icon" => Icon_no);

            $display_line[4] = array("Text" => "Uhrzeit",
                "Icon" => Icon_no,
                "Color" => Color_white);


            $display_line[5] = array("Text" => date("H:i:s",time()),
                "Icon" => Icon_no,
                "Color" => Color_white);

            $display_line[6] = array("Text" => "",
                "Icon" => Icon_no);

            break;
        case 3:  // Seite 3
            $display_line[1] = array("Text" => "",
                "Icon" => Icon_no);

            $display_line[4] = array("Text" => "SEITE 3",
                "Icon" => Icon_open,
                "Color" => Color_orange);

            $display_line[2] = array("Text" => "",  // GetValueFormatted(12345 /*[Objekt #12345 existiert nicht]*/);
                "Icon" => Icon_no);

            $display_line[3] = array("Text" => "",
                "Icon" => Icon_no);

            $display_line[5] = array("Text" => "",
                "Icon" => Icon_no);

            $display_line[6] = array("Text" => "",
                "Icon" => Icon_no);

            break;
    }
}

if ($_IPS["ACTION"] == "ActionUP")
{
    $display_line[1] = array("Text" => text_encode("Führe"),
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[2] = array("Text" => "Aktion",
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[3] = array("Text" => "OBEN ",
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[4] = array("Text" => "Seite " . $_IPS["PAGE"],
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[5] = array("Text" => "aus",
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[6] = array("Text" => "",
        "Icon" => Icon_no);
}

if ($_IPS["ACTION"] == "ActionDOWN")
{
    $display_line[1] = array("Text" => text_encode("Führe"),
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[2] = array("Text" => "Aktion",
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[3] = array("Text" => "UNTEN",
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[4] = array("Text" => "Seite " . $_IPS["PAGE"],
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[5] = array("Text" => "aus",
        "Icon" => Icon_no,
        "Color" => Color_orange);

    $display_line[6] = array("Text" => "",
        "Icon" => Icon_no);
}

$data = json_encode($display_line);
echo $data; //Daten zurückgeben an Dis-WM55-Instanz

?>';
        return $Script;
    }

    /**
     *  Wird bei einem timeout ausgeführt und setzt die aktuelle Seite wieder auf Null.
     * 
     * @access public
     */
    public function ResetTimer()
    {
        $this->Page = 0;
        $this->SetTimerInterval('DisplayTimeout', 0);
    }

}

/** @} */