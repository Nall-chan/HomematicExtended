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
 * HomeMaticWRInterface ist die Klasse für das IPS-Modul 'HomeMatic WR-Interface'.
 * Erweitert HMBase.
 */
class HomeMaticWRInterface extends HMBase
{
    protected const SummaryShowsHost = true;
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString(\HMExtended\Device\Property::Address, \HMExtended\Device\Address::WRINTERFACE);
        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Protocol, static::ProtocolId);
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);

        $this->RegisterPropertyInteger(\HMExtended\Device\Property::Interval, 60);
        $this->RegisterTimer(\HMExtended\Device\Timer::ReadWRInterface, 0, 'HM_ReadWRInterface($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     * Ohne aktiven Parent (kein oder inaktiver Homematic Socket) läuft kein Abruf und es entstehen keine Fehlermeldungen.
     * Mit aktivem Parent wird sofort gelesen, Fehler (Wired nicht aktiviert, CCU antwortet nicht) werden gemeldet.
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
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadWRInterface, $this->ReadPropertyInteger(\HMExtended\Device\Property::Interval) * 1000);
            $this->ReadWRInterface();
        } else {
            $this->SetTimerInterval(\HMExtended\Device\Timer::ReadWRInterface, 0);
        }
    }

    //################# PUBLIC

    /**
     * IPS-Instanz-Funktion 'HM_ReadWRInterface'.
     * Liest die Daten des WR-Interface und schreibt sie in die Statusvariablen.
     *
     * @return bool True bei Erfolg, sonst false (die Meldung erhält der Aufrufer).
     */
    public function ReadWRInterface(): bool
    {
        $Result = $this->GetInterface();
        if ($Result === false) {
            return false;
        }
        foreach ($Result as $Ident => $Value) {
            if ($Value === '') {
                continue;
            }
            switch (gettype($Value)) {
                case 'boolean':
                    $Typ = VARIABLETYPE_BOOLEAN;
                    break;
                case 'integer':
                    $Typ = VARIABLETYPE_INTEGER;
                    break;
                case 'double':
                case 'float':
                    $Typ = VARIABLETYPE_FLOAT;
                    break;
                case 'string':
                    $Typ = VARIABLETYPE_STRING;
                    break;
                default:
                    continue 2;
            }
            $this->MaintainPresentationVariable($Ident, $Ident, $Typ, \HMExtended\DatapointPresentation::Get($Ident, '', $Typ, false, fn (string $Text): string => $this->Translate($Text)));
            $this->SetValue($Ident, $Value);
        }
        return true;
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
     * Wird ausgeführt wenn sich der Status vom Parent ändert oder die Instanz verbunden bzw. getrennt wird.
     * Mit aktivem Parent wird neu gestartet und gelesen, ohne aktiven Parent hält der Timer ohne Fehlermeldung an.
     */
    protected function IOChangeState(int $State): void
    {
        $this->ApplyChanges();
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
     * Liest alle Daten des WR-Interfaces aus der CCU aus.
     * Fehler werden per echo an den Aufrufer gemeldet, nach dem ersten Fehler folgt keine weitere Anfrage.
     *
     * @return false|array Die Daten des Interface, bei einem Fehler false.
     */
    private function GetInterface(): false|array
    {
        if (!$this->HasActiveParent()) {
            echo $this->Translate('Instance has no active parent instance!') . PHP_EOL;
            return false;
        }
        if (!(bool) IPS_GetProperty($this->ParentID, 'WROpen')) {
            echo $this->Translate('Use of the HomeMatic Wired interface must be activated in the IO.') . PHP_EOL;
            return false;
        }

        $ParentData = [
            'DataID'     => \HMExtended\GUID::SendRpcToIO,
            'Protocol'   => 1,
            'MethodName' => 'getLGWStatus',
            'WaitTime'   => 3,
            'Data'       => []
        ];
        $this->SendDebug('Send', $ParentData, 0);

        $JSON = json_encode($ParentData);
        $ResultJSON = @$this->SendDataToParent($JSON);
        if ($ResultJSON === false) {
            $this->SendDebug('Error JSON', 'false', 0);
            echo $this->Translate('Error on read WR-Interface.') . PHP_EOL;
            return false;
        }
        $Result = json_decode($ResultJSON, true);
        if (($Result === false) || is_null($Result)) {
            $this->SendDebug('Error decode', $ResultJSON, 0);
            echo $this->Translate('Error on read WR-Interface.') . PHP_EOL;
            return false;
        }
        $this->SendDebug('Receive', $Result, 0);
        return $Result;
    }
}

/* @} */
