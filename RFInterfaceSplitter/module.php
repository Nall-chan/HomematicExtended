<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.75
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse

/**
 * HomeMaticRFInterfaceSplitter ist die Klasse für das IPS-Modul 'HomeMatic RFInterface-Splitter'.
 * Erweitert HMBase.
 */
class HomeMaticRFInterfaceSplitter extends HMBase
{
    protected const SummaryShowsHost = true;
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, \HMExtended\Device\Address::RFINTERFACE);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Interval, 60);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadRFInterfaces, 0, 'HM_ReadRFInterfaces($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*9999999999.*');
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $this->CheckConfig();
        // Timer und Abfrage nur mit aktivem Socket (z.B. nicht beim Start von Symcon, bevor der Socket verbunden ist).
        // Wird der Socket aktiv, startet IOChangeState über ApplyChanges den Timer.
        if (($this->GetStatus() == IS_ACTIVE) && $this->HasActiveParent()) {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRFInterfaces, $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval) * 1000);
            $this->ReadRFInterfaces();
        } else {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRFInterfaces, 0);
        }
    }
    /**
     * Interne Funktion des SDK.
     *
     * @param string $JSONString Der IPS-Datenstring
     *
     * @return string Die Antwort an den anfragenden Child
     */
    public function ForwardData(string $JSONString): string
    {
        $Result = $this->GetInterfaces();
        return serialize(($Result === false) ? [] : $Result);
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadRFInterfaces'.
     * Liest die Daten der RF-Interfaces und versendet sie an die Children.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function ReadRFInterfaces(): bool
    {
        $Result = $this->GetInterfaces();
        if ($Result === false) {
            return false;
        }
        $ret = false;
        $Present = [];
        foreach ($Result as $ProtocolID => $Protocol) {
            if (!is_array($Protocol)) {
                continue;
            }
            foreach ($Protocol as $InterfaceIndex => $Interface) {
                $this->SendDebug('Proto' . $ProtocolID . ' If' . $InterfaceIndex, $Interface, 0);
                $Present[] = $Interface['ADDRESS'] ?? '';
                $Interface['DataID'] = \HMExtended\GUID::SendToRFInterfaceDevice;
                $Data = json_encode($Interface);
                $this->SendDataToChildren($Data);
                $ret = true;
            }
        }
        if (count($Result) > 0) {
            // Ein Interface, das offline ist, fehlt in der Antwort der CCU. Die Instanzen erfahren so, welche Interfaces gemeldet wurden.
            $this->SendDataToChildren(json_encode([
                'DataID'  => \HMExtended\GUID::SendToRFInterfaceDevice,
                'ADDRESS' => \HMExtended\Device\Address::AllInterfaces,
                'PRESENT' => $Present
            ]));
        }
        return $ret;
    }

    //################# protected

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        parent::KernelReady();
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
        if ($State == IS_ACTIVE) {
            $this->ApplyChanges();
        } else {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadRFInterfaces, 0);
        }
    }

    //################# PRIVATE

    /**
     * Prüft die Konfiguration und setzt den Status der Instanz.
     */
    private function CheckConfig(): void
    {
        $Interval = $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval);
        if ($Interval <= 0) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if ($Interval < 5) {
            $this->SetStatus(IS_EBASE + 2);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
    }

    /**
     * Liest alle Daten der RF-Interfaces aus der CCU aus.
     * Fehler werden per echo an den Aufrufer gemeldet, nach dem ersten Fehler folgt keine weitere Anfrage.
     *
     * @return array|false Die Daten der Interfaces je Protokoll, bei einem Fehler false.
     */
    private function GetInterfaces(): array|false
    {
        if (!$this->HasActiveParent()) {
            echo $this->Translate('Instance has no active parent instance!') . PHP_EOL;
            return false;
        }
        $ParentId = $this->ParentID;
        $Protocol = [];
        if ((bool) IPS_GetProperty($ParentId, 'RFOpen')) {
            $Protocol[] = \HMExtended\CCU::ProtocolBidCosRF;
        }
        if ((bool) IPS_GetProperty($ParentId, 'IPOpen')) {
            $Protocol[] = \HMExtended\CCU::ProtocolHmIP;
        }

        $data = [];
        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => 0,
            'MethodName' => 'listBidcosInterfaces',
            'WaitTime'   => 3,
            'Data'       => $data
        ];
        $ret = [];
        foreach ($Protocol as $ProtocolId) {
            $ParentData['Protocol'] = $ProtocolId;
            $JSON = json_encode($ParentData);
            $ResultJSON = @$this->SendDataToParent($JSON);
            if ($ResultJSON === false) {
                $this->SendDebug('Error JSON', 'false', 0);
                echo $this->Translate('Error on read interfaces:') . $ProtocolId . PHP_EOL;
                return false;
            }
            $Result = json_decode(mb_convert_encoding($ResultJSON, 'UTF-8', 'ISO-8859-1'), true);
            if (($Result === false) || is_null($Result)) {
                $this->SendDebug('Error decode', $ResultJSON, 0);
                echo $this->Translate('Error on read interfaces:') . $ProtocolId . PHP_EOL;
                return false;
            }
            $ret[$ProtocolId] = $Result;
        }
        return $ret;
    }
}

/* @} */
