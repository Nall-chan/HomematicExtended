<?

require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

class HMDisWM55 extends HMBase
{

    use DebugHelper;

    const PageUP = 0;
    const PageDown = 1;
    const ActionUp = 2;
    const ActionDown = 3;

    private static $PropertysName = array(
        "PageUpID",
        "PageDownID",
        "ActionUpID",
        "ActionDownID");
    private $HMEventData = array();

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

        $ID = $this->RegisterScript('HM_OLED', 'HM_OLED.inc.php', $this->CreateHM_OLEDScript(), -2);
        IPS_SetHidden($ID, true);
        $ID = $this->RegisterScript('DisplayScript', 'Display Script', $this->CreateDisplayScript($ID), -1);
        IPS_SetHidden($ID, true);
        $this->RegisterPropertyInteger("ScriptID", $ID);

//        $this->RegisterVariableInteger('PAGE', 'PAGE');
        $this->SetBuffer("PAGE", (string) 0);
        $this->RegisterTimer('DisplayTimeout', 0, 'HM_ResetTimer($_IPS[\'TARGET\']);');
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message)
        {
            case VM_DELETE:
                $this->UnregisterMessage($SenderID, VM_DELETE);
                foreach (self::$PropertysName as $Name)
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

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() == KR_READY)
        {
            if (($this->CheckConfig()) and ( $this->GetDisplayAddress()))
            {
                $Lines = array();
                foreach ($this->HMEventData as $Trigger)
                {
                    $Lines[] = '.*"DeviceID":"' . $Trigger['DeviceID'] . '","VariableName":"' . $Trigger['VariableName'] . '".*';
                }
                $Line = implode('|', $Lines);
                $this->SetReceiveDataFilter("(" . $Line . ")");
                $this->SetSummary($Trigger['DeviceID']);
                return;
            }
        }
        $this->SetSummary('');
        $this->SetReceiveDataFilter(".*9999999999.*");
    }

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    public function ReceiveData($JSONString)
    {


        if (!$this->GetDisplayAddress())
            return;
        $Data = json_decode($JSONString);
        $this->SendDebug('Receive', $Data, 0);
        $ReceiveData = array("DeviceID" => (string) $Data->DeviceID, "VariableName" => (string) $Data->VariableName);
        $Action = array_search($ReceiveData, $this->HMEventData);
        if ($Action === false)
            return;
        try
        {
            $this->RunDisplayScript($Action);
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), $exc->getCode());
        }
    }

################## PRIVATE                

    private function CheckConfig()
    {
        $Events = array();
        $Result = true;

        foreach (self::$PropertysName as $Name)
        {
            $Event = $this->ReadPropertyInteger($Name);
            $OldEvent = $this->GetBuffer($Name);
            if ($Event <> $OldEvent)
            {
                if ($OldEvent > 0)
                    $this->UnregisterMessage($OldEvent, VM_DELETE);

                if ($Event > 0)
                    if (in_array($Event, $Events))
                    {
                        $this->SetStatus(IS_EBASE + 2);
                        $Result = false;
                    }
                    else
                    {
                        $Events[] = $Event;
                        $this->RegisterMessage($Event, VM_DELETE);
                        $this->SetBuffer('Event', $Event);
                    }
            }
        }

        if (count($Events) == 0)
        {
            $this->SetStatus(IS_INACTIVE);
            $Result = false;
        }

        if ($Result)
            if ($this->ReadPropertyInteger('ScriptID') == 0)
            {
                $this->SetStatus(IS_EBASE + 3);
                $Result = false;
            }

        if ($Result)
            if ($this->ReadPropertyInteger('Timeout') < 0)
            {
                $this->SetStatus(IS_EBASE + 4);
                $Result = false;
            }

        if ($Result)
            if ($this->ReadPropertyInteger('MaxPage') < 0)
            {
                $this->SetStatus(IS_EBASE + 5);
                $Result = false;
            }

        if ($Result)
            $this->SetStatus(IS_ACTIVE);

        return $Result;
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
            }
        }
        if (sizeof($this->HMEventData) > 0)
            return true;
        else
            return false;
    }

    private function RunDisplayScript($Action)
    {
        if (!$this->HasActiveParent())
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_WARNING);
        }
        $this->GetParentData();
        if ($this->HMAddress == '')
        {
            throw new Exception("Instance has no active Parent Instance!", E_USER_WARNING);
        }
//        $Page = GetValueInteger($this->GetIDForIdent('PAGE'));
        $Page = (int) GetBuffer('PAGE');
        $MaxPage = $this->ReadPropertyInteger('MaxPage');
        switch ($Action)
        {
            case "PageUpID":
                if ($Page == $MaxPage)
                    $Page = 1;
                else
                    $Page++;
                $ActionString = "UP";
                //SetValueInteger($this->GetIDForIdent('PAGE'), $Page);
                $this->SetBuffer('PAGE', (string) $Page);
                break;
            case "PageDownID":
                if ($Page <= 1)
                    $Page = $MaxPage;
                else
                    $Page--;
                $ActionString = "DOWN";
//                SetValueInteger($this->GetIDForIdent('PAGE'), $Page);
                $this->SetBuffer('PAGE', (string) $Page);
                break;
            case "ActionUpID":
                $ActionString = "ActionUP";
                break;
            case "ActionDownID":
                $ActionString = "ActionDOWN";
                break;
        }

        $ScriptID = $this->ReadPropertyInteger('ScriptID');
        if ($ScriptID <> 0)
        {
            $Result = IPS_RunScriptWaitEx($ScriptID, array('SENDER' => 'HMDisWM55', 'ACTION' => $ActionString, 'PAGE' => $Page, 'EVENT' => $this->InstanceID));

            $Data = $this->ConvertDisplayData(json_decode($Result));
            if ($Data === false)
            {
                trigger_error("Error in Display Script.", E_USER_NOTICE);
                return false;
            }
            $url = 'GetDisplay.exe';
            $HMScript = 'string DisplayKeySubmit;' . PHP_EOL;
            $HMScript.='DisplayKeySubmit=dom.GetObject("BidCos-RF.' . (string) $this->HMEventData[$Action]['DeviceID'] . '.SUBMIT").ID();' . PHP_EOL;
            $HMScript .= 'State=dom.GetObject(DisplayKeySubmit).State("' . $Data . '");' . PHP_EOL;
            try
            {
                $this->LoadHMScript($url, $HMScript);
            }
            catch (Exception $exc)
            {
                trigger_error('Error on send Data to HM-Dis-WM55.', E_USER_NOTICE);
                return false;
            }
        }
        $Timeout = $this->ReadPropertyInteger('Timeout');
        if ($Timeout > 0)
        {
            $this->SetTimerInterval('DisplayTimeout', 0);
            $this->SetTimerInterval('DisplayTimeout', $Timeout * 1000);
        }
    }

    private function ConvertDisplayData($Data)
    {
        if ($Data === null)
        {
            return false;
        }
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

    public function ResetTimer()
    {
//        SetValueInteger($this->GetIDForIdent('PAGE'), 0);
        $this->SetBuffer('PAGE', (string) 0);

        $this->SetTimerInterval('DisplayTimeout', 0);
    }

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
define ("Color_white"       ,0x82);
define ("Color_red"         ,0x81);
define ("Color_orange"      ,0x82);
define ("Color_yellow"      ,0x83);
define ("Color_green"       ,0x84);
define ("Color_blue"        ,0x85);
?>';
        return $Script;
    }

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
übergeben werden, wenn Umlaute in der Zeile verwendet werden.
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
	Die Instanz-ID der HMDis-WM55 Instanz.


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
                "Icon" => Icon_no);

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
    $display_line[1] = array("Text" => hex_encode("Führe"),
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
    $display_line[1] = array("Text" => hex_encode("Führe"),
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

function hex_encode ($string)
{
   $umlaut =  array("Ä"   ,"Ö"   ,"Ü"   ,"ä"   ,"ö"   ,"ü"   ,"ß"   ,":"   );
   $hex_neu = array(chr(0x5b),chr(0x23),chr(0x24),chr(0x7b),chr(0x7c),chr(0x7d),chr(0x5f),chr(0x3a));
   $return = str_replace($umlaut, $hex_neu, $string);
   return $return;
}

?>';
        return $Script;
    }

}

?>