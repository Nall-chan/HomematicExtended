<?php

declare(strict_types=1);
/**
 * @addtogroup homematicextended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMBase.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       3.0
 */
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace HMExtended {?>' . file_get_contents(__DIR__ . '/helper/VariableProfileHelper.php') . '}');

/**
 * HMBase ist die Basis-Klasse für alle Module welche HMScript verwenden.
 * Erweitert ipsmodule
 *
 * @property string $HMAddress Die Adresse der CCU.
 * @property int $ParentID Aktueller IO-Parent.
 */
abstract class HMBase extends IPSModule
{
    use \HMExtended\DebugHelper,
        \HMExtended\VariableHelper,
        \HMExtended\VariableProfileHelper,
        \HMExtended\BufferHelper,
        \HMExtended\InstanceStatus {
        \HMExtended\InstanceStatus::RegisterParent as IORegisterParent;
        \HMExtended\InstanceStatus::MessageSink as IOMessageSink;
        \HMExtended\InstanceStatus::RequestAction as IORequestAction;
    }
    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->ParentID = 0;
        $this->ConnectParent('{A151ECE9-D733-4FB9-AA15-7F7DD10C58AF}');
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

        $this->RegisterParent();
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
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    ################## protected
    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     * @access protected
     */
    abstract protected function IOChangeState($State);
    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     *
     * @access protected
     */
    abstract protected function KernelReady();
    /**
     * Setzte alle Eigenschaften, welche Instanzen die mit einem Homematic-Socket verbunden sind, haben müssen.
     *
     * @access protected
     * @param string $Address Die zu verwendene HM-Device Adresse.
     */
    protected function RegisterHMPropertys(string $Address)
    {
        $this->RegisterPropertyInteger('Protocol', 0);
        $count = @IPS_GetInstanceListByModuleID(IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID']);
        if (is_array($count)) {
            $this->RegisterPropertyString('Address', $Address . ':' . count($count));
        } else {
            $this->RegisterPropertyString('Address', $Address . ':0');
        }
    }

    /**
     * Registriert Nachrichten des aktuellen Parent und ließt die Adresse der CCU aus dem Parent.
     *
     * @access protected
     * @return int ID des Parent.
     */
    protected function RegisterParent()
    {
        $OldParentId = $this->ParentID;
        $ParentId = $this->IORegisterParent();
        if ($ParentId <> $OldParentId) {
            if ($ParentId > 0) {
                $this->HMAddress = (string) IPS_GetProperty($ParentId, 'Host');
            } else {
                $this->HMAddress = '';
            }
        }
        return $ParentId;
    }

    ################## ActionHandler
    public function RequestAction($Ident, $Value)
    {
        return $this->IORequestAction($Ident, $Value);
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
            $header[] = 'Accept: text/plain,text/xml,application/xml,application/xhtml+xml,text/html';
            $header[] = 'Cache-Control: max-age=0';
            $header[] = 'Connection: close';
            $header[] = 'Accept-Charset: UTF-8';
            $header[] = 'Content-type: text/plain;charset="UTF-8"';
            $ParentConfig = json_decode(IPS_GetConfiguration($this->ParentID), true);
            if (array_key_exists('UseSSL', $ParentConfig)) {
                if ($ParentConfig['UseSSL']) {
                    $this->SendDebug('useSSL', (string) $ParentConfig['HSSSLPort'], 0);
                    $ch = curl_init('https://' . (string) $this->HMAddress . ':' . (string) $ParentConfig['HSSSLPort'] . '/' . $url);
                } else {
                    $this->SendDebug('useNoSSL', (string) $ParentConfig['HSPort'], 0);
                    $ch = curl_init('http://' . (string) $this->HMAddress . ':' . (string) $ParentConfig['HSPort'] . '/' . $url);
                }
            } else {
                if (array_key_exists('HSPort', $ParentConfig)) {
                    $this->SendDebug('useNoSSL', (string) $ParentConfig['HSPort'], 0);
                    $ch = curl_init('http://' . (string) $this->HMAddress . ':' . (string) $ParentConfig['HSPort'] . '/' . $url);
                } else {
                    $this->SendDebug('useNoSSL', '8181', 0);
                    $ch = curl_init('http://' . (string) $this->HMAddress . ':8181/' . $url);
                }
            }
            if (array_key_exists('Username', $ParentConfig)) {
                if ($ParentConfig['Password'] != '') {
                    $this->SendDebug('useAuth', '', 0);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $ParentConfig['Username'] . ':' . $ParentConfig['Password']);
                }
            }
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $HMScript);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 2000);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code >= 400) {
                throw new Exception($this->Translate('CCU unreachable:') . $http_code, E_USER_NOTICE);
            }
            if ($result === false) {
                throw new Exception($this->Translate('CCU unreachable'), E_USER_NOTICE);
            }
            $this->SendDebug('Result', $result, 0);
            return $result;
        } else {
            $this->SendDebug('Error', $this->Translate('CCU Address not set.'), 0);
            throw new Exception($this->Translate('CCU Address not set.'), E_USER_NOTICE);
        }
    }
}

/** @} */
