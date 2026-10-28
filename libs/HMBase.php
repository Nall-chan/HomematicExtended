<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMBase.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       3.75
 */
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/AttributeArrayHelper.php') . '}');
require_once __DIR__ . '/HMTypes.php';  // HMTypes Data
require_once __DIR__ . '/HMScriptParser.php';  // Parser für die Antworten der CCU

/**
 * HMBase ist die Basis-Klasse für alle Module welche HMScript verwenden.
 * Erweitert IPSModuleStrict.
 *
 * @property int $ParentID Aktueller IO-Parent.
 *
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void RegisterProfileEx(int $VarTyp, string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0, int $Digits = 0)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, float $MaxValue = -1, float $StepSize = 0)
 * @method void UnregisterProfile(string $Profile)
 * @method void RegisterAttributeArray(string $name, array $Value, int $Size = 0)
 * @method array ReadAttributeArray(string $name)
 * @method void WriteAttributeArray(string $name, array $value)
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method int IORegisterParent()
 */
abstract class HMBase extends IPSModuleStrict
{
    use \HMExtended\DebugHelper,
        \HMExtended\VariableHelper,
        \HMExtended\VariableProfileHelper,
        \HMExtended\BufferHelper,
        \HMExtended\AttributeArrayHelper,
        \HMExtended\InstanceStatus {
            \HMExtended\InstanceStatus::RegisterParent as IORegisterParent;
            \HMExtended\InstanceStatus::MessageSink as IOMessageSink;
            \HMExtended\InstanceStatus::RequestAction as IORequestAction;
        }

    protected const ProtocolId = 0;
    protected const ValuesChannel = '';
    protected const ParamChannel = '';
    /**
     * True, wenn die Zusammenfassung den Host des Homematic Sockets anzeigt (Konfigurator, Splitter und Singletons).
     */
    protected const SummaryShowsHost = false;
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->ParentID = 0;
        $this->SetReceiveDataFilter('.*9999999999.*');
    }

    /**
     * Interne Funktion des SDK.
     * Alle Instanzen mit Datenfluss zur CCU werden mit einem Homematic Socket verbunden. Mehrere Instanzen teilen sich dabei einen Socket.
     */
    public function GetCompatibleParents(): string
    {
        return json_encode([
            'type'      => 'connect',
            'moduleIDs' => [\HMExtended\GUID::HmIO]
        ]);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->RegisterParent();
    }

    /**
     * Nachrichten aus der Nachrichtenschlange verarbeiten.
     *
     * @param int       $TimeStamp
     * @param int       $SenderID
     * @param int       $Message
     * @param array $Data
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case IM_CHANGESETTINGS:
                if ($SenderID == $this->ParentID) {
                    $this->SetHostSummary();
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     * Die Module melden Fehler per echo. Warnungen von Kernel-Funktionen gibt ModulErrorHandler ohne Dateiname und Zeile aus.
     */
    public function RequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        set_error_handler([$this, 'ModulErrorHandler'], E_USER_NOTICE | E_USER_WARNING);
        try {
            $this->ProcessRequestAction($Ident, $Value, $done);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Gibt Warnungen (z.B. von Kernel-Funktionen) während einer Bedienaktion per echo aus, ohne Dateiname und Zeile.
     *
     * @param int    $ErrLevel
     * @param string $ErrMsg
     *
     * @return bool Immer true, die Meldung gilt damit als behandelt.
     */
    public function ModulErrorHandler(int $ErrLevel, string $ErrMsg): bool
    {
        // Mit @ unterdrückte Meldungen (z.B. @GetIDForIdent in FindIDForIdent) nicht ausgeben: PHP ruft den Handler auch für diese auf.
        // Seit PHP 8 beschränkt @ error_reporting auf die fatalen Fehler (Schnittmenge mit der globalen Einstellung).
        if ((error_reporting() & ~(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) === 0) {
            return true;
        }
        echo $ErrMsg;
        return true;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData(string $JSONString): string
    {
        $Event = json_decode($JSONString, true);
        if (!is_array($Event) || !isset($Event['VariableName']) || !array_key_exists('VariableValue', $Event)) {
            $this->SendDebug('Invalid event', $JSONString, 0);
            return '';
        }
        $this->SendDebug('EVENT:' . $Event['VariableName'], $Event['VariableValue'], 0);
        $this->SetVariable($Event['VariableName'], $Event['VariableValue']);
        return '';
    }

    /**
     * Liest einen Wert von der CCU und schreibt ihn in die Statusvariable. Die Basisklasse bietet das nicht an.
     *
     * @param string $Ident Ident der Statusvariable.
     *
     * @return bool Immer false, abgeleitete Klassen überschreiben die Methode.
     */
    public function RequestState(string $Ident): bool
    {
        return false;
    }
    //################# protected

    /**
     * Verarbeitet Bedienaktionen. Abgeleitete Klassen überschreiben diese Methode und rufen zuerst die Basis auf.
     * Fehler werden per echo gemeldet und erscheinen im Frontend.
     *
     * @param string $Ident Ident der Variable bzw. Aktion.
     * @param mixed  $Value Der Wert.
     * @param bool   $done  Wird true, wenn die Aktion vollständig verarbeitet wurde.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value, bool &$done = false): void
    {
        $done = $this->IORequestAction($Ident, $Value);
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterParent();
    }

    /**
     * Setzte alle Eigenschaften, welche Instanzen die mit einem Homematic-Socket verbunden sind, haben müssen.
     *
     * @param string $Address Die zu nutzende HM-Device Adresse.
     */
    protected function RegisterHMPropertys(string $Address): void
    {
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $count = IPS_GetInstanceListByModuleID(IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID']);
            if (is_array($count)) {
                $this->RegisterPropertyString(\HMExtended\Device\Property::Address, $Address . ':' . count($count));
                return;
            }
        }
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, $Address . ':0');
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @return int ID des Parent.
     */
    protected function RegisterParent(): int
    {
        $OldParentId = $this->ParentID;
        $ParentId = $this->IORegisterParent();
        if (static::SummaryShowsHost) {
            // Ändert der Nutzer den Host im Socket, wird die Zusammenfassung nachgeführt.
            if ($ParentId != $OldParentId) {
                if ($OldParentId > 0) {
                    $this->UnregisterMessage($OldParentId, IM_CHANGESETTINGS);
                }
                if ($ParentId > 0) {
                    $this->RegisterMessage($ParentId, IM_CHANGESETTINGS);
                }
            }
            $this->SetHostSummary();
        }
        return $ParentId;
    }

    /**
     * Zeigt den Host des Homematic Sockets in der Zusammenfassung an, ohne Parent bleibt sie leer.
     */
    protected function SetHostSummary(): void
    {
        if (!static::SummaryShowsHost) {
            return;
        }
        $Host = '';
        if ($this->ParentID > 0) {
            $Host = (string) @IPS_GetProperty($this->ParentID, \HMExtended\Device\IOProperty::Host);
        }
        $this->SetSummary($Host);
    }

    /**
     * Prüft, ob Instanzen der übergebenen Module existieren (z.B. um gemeinsam genutzte Profile nicht zu löschen).
     *
     * @param string[] $ModuleGuids Die GUIDs der Module.
     *
     * @return bool True wenn mindestens eine Instanz existiert.
     */
    protected function ExistInstancesOf(array $ModuleGuids): bool
    {
        foreach ($ModuleGuids as $ModuleGuid) {
            if (count(IPS_GetInstanceListByModuleID($ModuleGuid)) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Liest das Paramset VALUES des Gerätes (Adresse und Kanal der Instanz) und schreibt alle Werte in die Statusvariablen.
     *
     * @return bool true, wenn die CCU Werte geliefert hat.
     */
    protected function GetValuesAndSetVariable(): bool
    {
        $AddressWithChannel = $this->ReadPropertyString(\HMExtended\Device\Property::Address) . static::ValuesChannel;
        $Result = $this->GetParamset(\HMExtended\CCU::VALUES, $AddressWithChannel);
        if (count($Result) != 0) {
            foreach ($Result as $Ident => $Value) {
                $this->SetVariable($Ident, $Value);
            }
            return true;
        }
        return false;
    }

    /**
     * Schreibt einen Wert in die Statusvariable. Unbekannte Idents werden ignoriert (nur Debug-Ausgabe).
     *
     * @param string $Ident Ident der Statusvariable.
     * @param mixed $Value Der Wert.
     */
    protected function SetVariable(string $Ident, mixed $Value): void
    {
        if ($this->FindIDForIdent($Ident) == 0) {
            $this->SendDebug('Unknown variable', $Ident, 0);
            return;
        }
        $this->SetValue($Ident, $Value);
    }
    /**
     * Überträgt das übergeben HM-Script an die CCU und liefert das Ergebnis.
     * Fehler werden per echo an den Aufrufer gemeldet.
     *
     * @param string $HMScript Das zu übertragende HM-Script.
     *
     * @return false|string Das Ergebnis von der CCU, bei einem Fehler false.
     */
    protected function LoadHMScript(string $HMScript): false|string
    {
        if (!$this->HasActiveParent()) {
            echo $this->Translate('Instance has no active parent instance!') . PHP_EOL;
            return false;
        }
        $ParentData = [
            'DataID'        => \HMExtended\GUID::SendScriptToIO,
            'Content'       => $HMScript
        ];
        $this->SendDebug('Send', $ParentData, 0);
        $Result = @$this->SendDataToParent(json_encode($ParentData));
        if ($Result === false || $Result === null) {
            $this->SendDebug('Error', 'no response', 0);
            echo $this->Translate('The CCU did not answer the HM-Script.') . PHP_EOL;
            return false;
        }
        // Die CCU liefert ISO-8859-1.
        $Result = mb_convert_encoding($Result, 'UTF-8', 'ISO-8859-1');
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }

    /**
     * Zerlegt die Antwort eines HM-Scriptes in die Ausgabe und das XML der CCU.
     * Die Ausgabe des Scriptes steht im Element `Output`.
     *
     * @param string $Content Antwort der CCU.
     *
     * @return SimpleXMLElement|false Das XML, bei ungültiger Antwort false (Meldung per echo).
     */
    protected function GetScriptXML(string $Content): SimpleXMLElement|false
    {
        [$Output, $XMLContent] = \HMExtended\ScriptParser::SplitOutput($Content);
        try {
            $xml = @new SimpleXMLElement($XMLContent, LIBXML_NOBLANKS + LIBXML_NONET);
        } catch (Throwable $exc) {
            $this->SendDebug('Error', $exc->getMessage(), 0);
            echo $this->Translate('Invalid response from CCU.') . PHP_EOL;
            return false;
        }
        unset($xml->exec);
        unset($xml->sessionId);
        unset($xml->httpUserAgent);
        $xml->addChild('Output', $Output);
        return $xml;
    }

    /**
     * Liest ein Paramset (z.B. VALUES oder MASTER) eines Gerätes von der CCU.
     *
     * @param string $Paramset Name des Paramsets.
     * @param string $AddressWithChannel Adresse des Gerätes, bei Bedarf mit Kanal.
     * @param int $Protocol Das System der CCU (-1 = Protokoll der Instanz).
     *
     * @return array Die Werte, bei einem Fehler ein leeres Array.
     */
    protected function GetParamset(string $Paramset, string $AddressWithChannel, int $Protocol = -1): array
    {
        if ($Protocol == -1) {
            $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        }
        $Result = $this->SendRPC('getParamset', $Protocol, [$AddressWithChannel, $Paramset]);
        return is_array($Result) ? $Result : [];
    }

    /**
     * Liest die Beschreibung der Datenpunkte eines Paramsets von der CCU.
     *
     * @param string $Paramset Name des Paramsets.
     * @param string $AddressWithChannel Adresse des Gerätes, bei Bedarf mit Kanal.
     * @param int $Protocol Das System der CCU (-1 = Protokoll der Instanz).
     *
     * @return array Die Beschreibung je Datenpunkt, bei einem Fehler ein leeres Array.
     */
    protected function GetParamsetDescription(string $Paramset, string $AddressWithChannel, int $Protocol = -1): array
    {
        if ($Protocol == -1) {
            $Protocol = $this->ReadPropertyInteger(\HMExtended\Device\Property::Protocol);
        }
        $Result = $this->SendRPC('getParamsetDescription', $Protocol, [$AddressWithChannel, $Paramset]);
        return is_array($Result) ? $Result : [];
    }

    /**
     * Sendet eine XML-RPC Anfrage über den Datenfluss an den Homematic Socket.
     * Texte der CCU, die als ISO-8859-1 geliefert werden, werden nach UTF-8 gewandelt.
     *
     * @param string $MethodName Name der RPC-Methode.
     * @param int $Protocol Das System der CCU.
     * @param array $Paramset Parameter der Methode.
     * @param mixed $Values Daten (Arrays werden als JSON übertragen), null = keine Daten.
     * @param bool $EmulateStatus Bei true wird die Antwort der CCU nicht abgewartet.
     *
     * @return mixed Die Antwort der CCU, true ohne Antwortdaten, bei einem Fehler null (Meldung per echo).
     */
    protected function SendRPC(string $MethodName, int $Protocol, array $Paramset, mixed $Values = null, bool $EmulateStatus = false): mixed
    {
        if (!$this->HasActiveParent()) {
            echo $this->Translate('Instance has no active parent instance!') . PHP_EOL;
            return null;
        }
        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => $Protocol,
            'MethodName' => $MethodName,
            'WaitTime'   => 3,
            'Data'       => $Paramset
        ];
        if (is_array($Values)) {
            $ParentData['Data'][] = json_encode($Values, JSON_PRESERVE_ZERO_FRACTION);
        } elseif (!is_null($Values)) {
            $ParentData['Data'][] = $Values;
        }
        $this->SendDebug('Send', $ParentData, 0);
        $ResultJSON = @$this->SendDataToParent(json_encode($ParentData, JSON_PRESERVE_ZERO_FRACTION));
        if ($EmulateStatus) {
            return true;
        }
        if ($ResultJSON === false) {
            $this->SendDebug('Error', '', 0);
            echo sprintf($this->Translate('Error on %s'), $MethodName) . PHP_EOL;
            return null;
        }
        $this->SendDebug('Receive JSON', $ResultJSON, 0);
        if ($ResultJSON === '') {
            return true;
        }
        $Result = json_decode($ResultJSON, true);
        if (($Result === null) && (json_last_error() === JSON_ERROR_UTF8)) {
            // Die CCU liefert Texte (z.B. die Einheit °C) teilweise als ISO-8859-1.
            $Result = json_decode(mb_convert_encoding($ResultJSON, 'UTF-8', 'ISO-8859-1'), true);
        }
        return $Result;
    }
}

/* @} */
