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
 * HomeMaticDisEPWM55 ist die Klasse für das IPS-Modul 'HomeMatic Dis-EP-WM55'.
 * Erweitert HMBase.
 */
class HomeMaticDisEPWM55 extends HMBase
{
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, '');
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, 0);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $Address = $this->ReadPropertyString(\HMExtended\Device\Property::Address);
        $this->SetSummary($Address);
        $this->SetReceiveDataFilter('.*9999999999.*');
    }

    //################# public

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayNotify'.
     * Steuert den Summer und die LED des Display.
     *
     * @param int $Chime  Tonfolge 0-6
     * @param int $Repeat Anzahl der Wiederholungen 0-15
     * @param int $Wait   Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int $Color  Farbe der LED 0-3
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayNotify(int $Chime, int $Repeat, int $Wait, int $Color)
    {
        $Data = $this->GetSignal($Chime, $Repeat, $Wait, $Color);
        if ($Data === false) {
            return false;
        }
        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayLine'.
     * Beschreibt eine Zeile vom Display.
     *
     * @param int    $Line Die zu beschreibende Zeile 1-3
     * @param string $Text Der darzustellende Text (bis 12 Zeichen)
     * @param int    $Icon Das anzuzeigende Icon (0-9)
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayLine(int $Line, string $Text, int $Icon)
    {
        $Data[] = '0x0A';
        for ($index = 1; $index <= 3; $index++) {
            if ($index == $Line) {
                $Line = $this->GetLine($Text, $Icon);
                if ($Line === false) {
                    return false;
                }
                $Data = array_merge($Data, $Line);
            } else {
                $Data[] = '0x0A';
            }
        }
        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayLineEx'.
     * Beschreibt eine Zeile vom Display und steuert den Summer sowie die LED des Display an.
     *
     * @param int    $Line   Die zu beschreibende Zeile 1-3
     * @param string $Text   Der darzustellende Text (bis 12 Zeichen)
     * @param int    $Icon   Das anzuzeigende Icon (0-9)
     * @param int    $Chime  Tonfolge 0-6
     * @param int    $Repeat Anzahl der Wiederholungen 0-15
     * @param int    $Wait   Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int    $Color  Farbe der LED 0-3
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayLineEx(int $Line, string $Text, int $Icon, int $Chime, int $Repeat, int $Wait, int $Color)
    {
        $Data[] = '0x0A';
        for ($index = 1; $index <= 3; $index++) {
            if ($index == $Line) {
                $Line = $this->GetLine($Text, $Icon);
                if ($Line === false) {
                    return false;
                }
                $Data = array_merge($Data, $Line);
            } else {
                $Data[] = '0x0A';
            }
        }
        $Notify = $this->GetSignal($Chime, $Repeat, $Wait, $Color);
        if ($Notify === false) {
            return false;
        }
        $Data = array_merge($Data, $Notify);
        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplay'.
     * Beschreibt alle Zeilen vom Display.
     *
     * @param string $Text1 Der darzustellende Text in Zeile 1(bis 12 Zeichen)
     * @param int    $Icon1 Das anzuzeigende Icon in Zeile 1(0-9)
     * @param string $Text2 Der darzustellende Text in Zeile 2(bis 12 Zeichen)
     * @param int    $Icon2 Das anzuzeigende Icon in Zeile 2(0-9)
     * @param string $Text3 Der darzustellende Text in Zeile 3(bis 12 Zeichen)
     * @param int    $Icon3 Das anzuzeigende Icon in Zeile 3(0-9)
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueDisplay(string $Text1, int $Icon1, string $Text2, int $Icon2, string $Text3, int $Icon3)
    {
        $Data[] = '0x0A';
        $Line1 = $this->GetLine($Text1, $Icon1);
        if ($Line1 === false) {
            return false;
        }
        $Data = array_merge($Data, $Line1);
        $Line2 = $this->GetLine($Text2, $Icon2);
        if ($Line2 === false) {
            return false;
        }
        $Data = array_merge($Data, $Line2);
        $Line3 = $this->GetLine($Text3, $Icon3);
        if ($Line3 === false) {
            return false;
        }
        $Data = array_merge($Data, $Line3);
        return $this->SendData($Data);
    }

    /**
     * IPS-Instanz-Funktion 'HM_WriteValueDisplayEx'.
     * Beschreibt alle Zeilen vom Display  und steuert den Summer sowie die LED des Display an.
     *
     * @param string $Text1  Der darzustellende Text in Zeile 1(bis 12 Zeichen)
     * @param int    $Icon1  Das anzuzeigende Icon in Zeile 1(0-9)
     * @param string $Text2  Der darzustellende Text in Zeile 2(bis 12 Zeichen)
     * @param int    $Icon2  Das anzuzeigende Icon in Zeile 2(0-9)
     * @param string $Text3  Der darzustellende Text in Zeile 3(bis 12 Zeichen)
     * @param int    $Icon3  Das anzuzeigende Icon in Zeile 3(0-9)
     * @param int    $Chime  Tonfolge 0-6
     * @param int    $Repeat Anzahl der Wiederholungen 0-15
     * @param int    $Wait   Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int    $Color  Farbe der LED 0-3
     *
     * @return bool True bei Erfolg, sonst false.
     */
    public function WriteValueDisplayEx(string $Text1, int $Icon1, string $Text2, int $Icon2, string $Text3, int $Icon3, int $Chime, int $Repeat, int $Wait, int $Color)
    {
        $Data[] = '0x0A';
        $Line1 = $this->GetLine($Text1, $Icon1);
        if ($Line1 === false) {
            return false;
        }
        $Data = array_merge($Data, $Line1);

        $Line2 = $this->GetLine($Text2, $Icon2);
        if ($Line2 === false) {
            return false;
        }
        $Data = array_merge($Data, $Line2);
        $Line3 = $this->GetLine($Text3, $Icon3);
        if ($Line3 === false) {
            return false;
        }
        $Data = array_merge($Data, $Line3);

        $Notify = $this->GetSignal($Chime, $Repeat, $Wait, $Color);
        if ($Notify === false) {
            return false;
        }
        $Data = array_merge($Data, $Notify);
        return $this->SendData($Data);
    }

    //################# PRIVATE

    /**
     * Sendet die Daten an den HM-Socket.
     *
     * @param array $Submit Das Array mit allen Werten, welche an das Display gesendet werden sollen.
     *
     * @return bool True bei Erfolg, sonst false.
     */
    private function SendData($Submit)
    {
        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => 0,
            'MethodName' => 'setValue',
            'WaitTime'   => 3,
            'Data'       => [$this->ReadPropertyString(\HMExtended\Device\Property::Address), 'SUBMIT', '0x02,' . implode(',', $Submit) . ',0x03']
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        if ($ResultJSON === false) {
            trigger_error($this->Translate('Error on send Data.'), E_USER_NOTICE);
            $this->SendDebug('Error JSON', $ResultJSON, 0);
            return false;
        }
        $Result = json_decode(utf8_encode($ResultJSON));
        if ($Result === false) {
            trigger_error($this->Translate('Error on send Data.'), E_USER_NOTICE);
            $this->SendDebug('Error decode', $Result, 0);
            return false;
        }
        $this->SendDebug('Receive', $Result, 0);
        return true;
    }

    /**
     * Erzeugt das Daten-Array aus den übergebenden Parametern.
     *
     * @param int $Chime  Tonfolge 0-6
     * @param int $Repeat Anzahl der Wiederholungen 0-15
     * @param int $Wait   Wartezeit in 10 Sekunden zwischen den Wiederholungen.
     * @param int $Color  Farbe der LED 0-3
     *
     * @return false|array Das Array mit den Daten, oder im Fehlerfall false.
     */
    private function GetSignal(int $Chime, int $Repeat, int $Wait, int $Color)
    {
        try {
            if (!is_int($Chime)) {
                throw new Exception(sprintf($this->Translate('Parameter %s must be type of integer.'), 'Chime'));
            }
            if (!is_int($Repeat)) {
                throw new Exception(sprintf($this->Translate('Parameter %s must be type of integer.'), 'Repeat'));
            }
            if (!is_int($Wait)) {
                throw new Exception(sprintf($this->Translate('Parameter %s must be type of integer.'), 'Wait'));
            }
            if (!is_int($Color)) {
                throw new Exception(sprintf($this->Translate('Parameter %s must be type of integer.'), 'Color'));
            }
            if (($Chime < 0) || ($Chime > 6)) {
                throw new Exception(sprintf($this->Translate('Parameter %s out of range.'), 'Chime'));
            }
            if (($Repeat < 0) || ($Repeat > 15)) {
                throw new Exception(sprintf($this->Translate('Parameter %s out of range.'), 'Repeat'));
            }
            if (($Wait < 0) || ($Wait > 15)) {
                throw new Exception(sprintf($this->Translate('Parameter %s out of range.'), 'Wait'));
            }
            if (($Color < 0) || ($Color > 3)) {
                throw new Exception(sprintf($this->Translate('Parameter %s out of range.'), 'Color'));
            }
        } catch (Exception $exc) {
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
     * @param string $Text Der darzustellenden Text (0-12 Zeichen)
     * @param int    $Icon Das anzuzeigende Icon (0-9)
     *
     * @return false|array Das Daten-Array für eine Zeile.
     */
    private function GetLine(string $Text, int $Icon)
    {
        try {
            if (!is_string($Text)) {
                throw new Exception(sprintf($this->Translate('Parameter %s must be type of string.'), 'Text'));
            }
            if (!is_int($Icon)) {
                throw new Exception(sprintf($this->Translate('Parameter %s must be type of integer.'), 'Icon'));
            }
            if (($Icon < 0) || ($Icon > 9)) {
                throw new Exception(sprintf($this->Translate('Parameter %s out of range.'), 'Icon'));
            }
        } catch (Exception $exc) {
            trigger_error($exc->getMessage(), E_USER_NOTICE);
            return false;
        }

        $Data[] = '0x12';
        if ($Text === '') {
            $Data[] = '0x20';
        } else {
            if (strpos($Text, '0x8') === 0) {
                $Data[] = substr($Text, 0, 4);
            } else {
                $Text = $this->hex_encode($Text);
                for ($i = 0; $i < ((strlen($Text) < 12) ? strlen($Text) : 12); $i++) {
                    $Data[] = '0x' . dechex(ord($Text[$i]));
                }
            }
        }
        if ($Icon != 0) {
            $Data[] = '0x13';
            $Data[] = '0x8' . dechex($Icon - 1);
        }
        $Data[] = '0x0A';
        return $Data;
    }

    /**
     * Konvertiert die deutschen Sonderzeichen.
     *
     * @param string $string Der Original-String.
     *
     * @return string Der veränderte String.
     */
    private function hex_encode(string $string)
    {
        $umlaut = ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', ':'];
        $hex_neu = [chr(0x5b), chr(0x23), chr(0x24), chr(0x7b), chr(0x7c), chr(0x7d), chr(0x5f), chr(0x3a)];
        $return = str_replace($umlaut, $hex_neu, $string);
        return $return;
    }
}

/* @} */
