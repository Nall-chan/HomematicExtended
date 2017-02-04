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
 * @version       2.35
 */
require_once(__DIR__ . "/../HMBase.php");  // HMBase Klasse

/**
 * HMDisEPWM55 ist die Klasse für das IPS-Modul 'HomeMatic Dis-EP-WM55'.
 * Erweitert HMBase 
 *
 */
class HMDisEPWM55 extends HMBase
{

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("Address", "");
        $this->RegisterPropertyInteger("Protocol", 0);
        $this->RegisterPropertyBoolean("EmulateStatus", false);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $Address = $this->ReadPropertyString("Address");
        $this->SetSummary($Address);
        $this->SetReceiveDataFilter(".*9999999999.*");
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

################## PRIVATE                

    /**
     * Sendet die Daten an dden HM-Socket.
     * 
     * @access private
     * @param array $Submit Das Array mit allen Werten, welche an das Display gesendet werden sollen.
     * @return boolean True bei Erfolg, sonst false.
     * @todo Rückgabewerte fehlen!
     */
    private function SendData($Submit)
    {
        if (!$this->HasActiveParent())
        {
            trigger_error("Instance has no active Parent Instance!", E_USER_NOTICE);
            return false;
        }
        $ParentData = Array
            (
            "DataID" => "{75B6B237-A7B0-46B9-BBCE-8DF0CFE6FA52}",
            "Protocol" => 0,
            "MethodName" => "setValue",
            "WaitTime" => 5000,
            "Data" => array($this->ReadPropertyString('Address'), 'SUBMIT', '0x02,' . implode(',', $Submit) . ',0x03')
        );
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        $Result = @json_decode($ResultJSON);
        if ($Result === false)
        {
            trigger_error('Error on send Data', E_USER_NOTICE);
            $this->SendDebug('Error', '', 0);
        }
        $this->SendDebug('Receive', $Result, 0);
        return true;
    }

    /**
     * Erzeugt das Daten-Array aus den übergebenden Parametern.
     * 
     * @access public
     * @param int $Chime Tonfolge 0-6
     * @param int $Repeat Anzahl der Wiederholgungen 0-15
     * @param int $Wait Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int $Color Frabe der LED 0-3
     * @return boolean|array Das Array mit den Daten, oder im Fehlerfall false.
     */
    private function GetSignal(int $Chime, int $Repeat, int $Wait, int $Color)
    {
        try
        {
            if (!is_int($Chime))
                throw new Exception('Chime must be integer.');
            if (!is_int($Repeat))
                throw new Exception('Repeat must be integer.');
            if (!is_int($Wait))
                throw new Exception('Wait must be integer.');
            if (!is_int($Color))
                throw new Exception('Color must be integer.');
            if (($Chime < 0) or ( $Chime > 6))
                throw new Exception('Chime out of range.');
            if (($Repeat < 0) or ( $Repeat > 15))
                throw new Exception('Repeat out of range.');
            if (($Wait < 0) or ( $Wait > 15))
                throw new Exception('Wait out of range.');
            if (($Color < 0) or ( $Color > 3))
                throw new Exception('Color out of range.');
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }
        $Data[] = '0x14';
        $Data[] = '0xC' . dechex($Chime);
        $Data[] = '0x1C';
        $Data[] = '0xD' . dechex($Repeat);
        $Data[] = '0x1D';
        $Data[] = '0xE' . dechex($Wait);
        $Data[] = '0x16';
        $Data[] = '0xF' . dechex($Color);
        return $Data;
    }

    /**
     * Erzeugt aus dem übergebenen Parametern eine Daten-Array für den Text und das Icon.
     * 
     * @access private
     * @param string $Text Der darzustellenden Text (0-12 Zeichen)
     * @param int $Icon Das anzuzeigende Icon (0-9)
     * @return array Das Daten-Array für eine Zeile. 
     */
    private function GetLine(string $Text, int $Icon)
    {
        try
        {
            if (!is_string($Text))
                throw new Exception('Text must be string.');
            if (!is_int($Icon))
                throw new Exception('Icon must be integer.');
            if (($Icon < 0) or ( $Icon > 9))
                throw new Exception('Icon out of range.');
        }
        catch (Exception $exc)
        {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }


        $Data[] = '0x12';
        if ($Text === "")
            $Data[] = '0x20';
        else
        {
            if (strpos($Text, '0x8') === 0)
                $Data[] = substr($Text, 0, 4);
            else
            {
                $Text = $this->hex_encode($Text);
                for ($i = 0; $i < ((strlen($Text) < 12) ? strlen($Text) : 12); $i++)
                {
                    $Data[] = "0x" . dechex(ord($Text[$i]));
                }
            }
        }
        if ($Icon <> 0)
        {
            $Data[] = '0x13';
            $Data[] = '0x8' . dechex($Icon - 1);
        }
        $Data[] = '0x0A';
        return $Data;
    }

    /**
     * Konvertiert die deutschen Sonderzeichen.
     * 
     * @access private
     * @param string $string Der Original-String.
     * @return string Der veränderte String.
     */
    private function hex_encode(string $string)
    {
        $umlaut = array("Ä", "Ö", "Ü", "ä", "ö", "ü", "ß", ":");
        $hex_neu = array(chr(0x5b), chr(0x23), chr(0x24), chr(0x7b), chr(0x7c), chr(0x7d), chr(0x5f), chr(0x3a));
        $return = str_replace($umlaut, $hex_neu, $string);
        return $return;
    }

################## public    

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayNotify'.
     * Steuert den Summer und die LED des Display.
     * 
     * @access public
     * @param int $Chime Tonfolge 0-6
     * @param int $Repeat Anzahl der Wiederholgungen 0-15
     * @param int $Wait Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int $Color Frabe der LED 0-3
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayNotify(int $Chime, int $Repeat, int $Wait, int $Color)
    {

        $Data = $this->GetSignal($Chime, $Repeat, $Wait, $Color);
        if ($Data === false)
            return false;
        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayLine'.
     * Beschreibt eine Zeile vom Display.
     * 
     * @access public
     * @param int $Line Die zu beschreibende Zeile 1-3
     * @param string $Text Der darzustellende Text (bis 12 Zeichen)
     * @param int $Icon Das anzuzeigende Icon (0-9)
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayLine(int $Line, string $Text, int $Icon)
    {
        $Data[] = '0x0A';
        for ($index = 1; $index <= 3; $index++)
        {
            if ($index == $Line)
            {
                $Line = $this->GetLine($Text, $Icon);
                if ($Line === false)
                    return false;
                $Data = array_merge($Data, $Line);
            }
            else
                $Data[] = '0x0A';
        }

        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayLineEx'.
     * Beschreibt eine Zeile vom Display und steuert den Summer sowie die LED des Display an.
     * 
     * @access public
     * @param int $Line Die zu beschreibende Zeile 1-3
     * @param string $Text Der darzustellende Text (bis 12 Zeichen)
     * @param int $Icon Das anzuzeigende Icon (0-9)
     * @param int $Chime Tonfolge 0-6
     * @param int $Repeat Anzahl der Wiederholgungen 0-15
     * @param int $Wait Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int $Color Frabe der LED 0-3
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayLineEx(int $Line, string $Text, int $Icon, int $Chime, int $Repeat, int $Wait, int $Color)
    {
        $Data[] = '0x0A';
        for ($index = 1; $index <= 3; $index++)
        {
            if ($index == $Line)
            {
                $Line = $this->GetLine($Text, $Icon);
                if ($Line === false)
                    return false;
                $Data = array_merge($Data, $Line);
            }
            else
                $Data[] = '0x0A';
        }
        $Notify = $this->GetSignal($Chime, $Repeat, $Wait, $Color);
        if ($Notify === false)
            return false;
        $Data = array_merge($Data, $Notify);
        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplay'.
     * Beschreibt alle Zeilen vom Display.
     * 
     * @access public
     * @param string $Text1 Der darzustellende Text in Zeile 1(bis 12 Zeichen)
     * @param int $Icon1 Das anzuzeigende Icon in Zeile 1(0-9)
     * @param string $Text2 Der darzustellende Text in Zeile 2(bis 12 Zeichen)
     * @param int $Icon2 Das anzuzeigende Icon in Zeile 2(0-9)
     * @param string $Text3 Der darzustellende Text in Zeile 3(bis 12 Zeichen)
     * @param int $Icon3 Das anzuzeigende Icon in Zeile 3(0-9)
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueDisplay(string $Text1, int $Icon1, string $Text2, int $Icon2, string $Text3, int $Icon3)
    {
        $Data[] = '0x0A';
        $Line1 = $this->GetLine($Text1, $Icon1);
        if ($Line1 === false)
            return false;
        $Data = array_merge($Data, $Line1);

        $Line2 = $this->GetLine($Text2, $Icon2);
        if ($Line2 === false)
            return false;
        $Data = array_merge($Data, $Line2);
        $Line3 = $this->GetLine($Text3, $Icon3);
        if ($Line3 === false)
            return false;
        $Data = array_merge($Data, $Line3);

        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayEx'.
     * Beschreibt alle Zeilen vom Display  und steuert den Summer sowie die LED des Display an.
     * 
     * @access public
     * @param string $Text1 Der darzustellende Text in Zeile 1(bis 12 Zeichen)
     * @param int $Icon1 Das anzuzeigende Icon in Zeile 1(0-9)
     * @param string $Text2 Der darzustellende Text in Zeile 2(bis 12 Zeichen)
     * @param int $Icon2 Das anzuzeigende Icon in Zeile 2(0-9)
     * @param string $Text3 Der darzustellende Text in Zeile 3(bis 12 Zeichen)
     * @param int $Icon3 Das anzuzeigende Icon in Zeile 3(0-9)
     * @param int $Chime Tonfolge 0-6
     * @param int $Repeat Anzahl der Wiederholgungen 0-15
     * @param int $Wait Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int $Color Frabe der LED 0-3
     * @return boolean True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayEx(string $Text1, int $Icon1, string $Text2, int $Icon2, string $Text3, int $Icon3, int $Chime, int $Repeat, int $Wait, int $Color)
    {
        $Data[] = '0x0A';
        $Line1 = $this->GetLine($Text1, $Icon1);
        if ($Line1 === false)
            return false;
        $Data = array_merge($Data, $Line1);

        $Line2 = $this->GetLine($Text2, $Icon2);
        if ($Line2 === false)
            return false;
        $Data = array_merge($Data, $Line2);
        $Line3 = $this->GetLine($Text3, $Icon3);
        if ($Line3 === false)
            return false;
        $Data = array_merge($Data, $Line3);

        $Notify = $this->GetSignal($Chime, $Repeat, $Wait, $Color);
        if ($Notify === false)
            return false;
        $Data = array_merge($Data, $Notify);
        return $this->SendData($Data);
    }

}

/** @} */
