<?php

declare(strict_types=1);

include_once __DIR__ . '/HomematicModuleTestCase.php';

/**
 * Basisklasse für Tests der Heizungsmodule (Heizgruppe, Klimaregler).
 */
abstract class HeatingTestCase extends HomematicModuleTestCase
{
    protected const WeekDays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];

    protected int $IoId;
    protected int $DeviceId;

    /**
     * Erzeugt eine Beschreibung von Datenpunkten, wie sie die CCU mit getParamsetDescription liefert.
     *
     * @param array<string, string> $Types Ident => Typ (FLOAT, INTEGER, BOOL), Operationen 7 (lesen, schreiben, Event).
     */
    protected function description(array $Types): array
    {
        $Result = [];
        foreach ($Types as $Ident => $Type) {
            $Result[$Ident] = ['ID' => $Ident, 'TYPE' => $Type, 'OPERATIONS' => 7, 'UNIT' => ''];
        }
        return $Result;
    }

    /**
     * Wochenplan-Parameter, wie sie die CCU im MASTER-Paramset liefert.
     *
     * @param string $TimeFormat Format des Parameters für die Endzeit.
     * @param string $TempFormat Format des Parameters für die Temperatur.
     */
    protected function schedule(string $TimeFormat, string $TempFormat, int $Plans, int $Slots): array
    {
        $Result = [];
        for ($Plan = 1; $Plan <= $Plans; $Plan++) {
            foreach (self::WeekDays as $Day) {
                for ($Slot = 1; $Slot <= $Slots; $Slot++) {
                    $Result[sprintf($TimeFormat, $Slot, $Day, $Plan)] = 1440;
                    $Result[sprintf($TempFormat, $Slot, $Day, $Plan)] = 17.0;
                }
            }
        }
        return $Result;
    }

    /**
     * Liefert alle an die CCU gesendeten Anfragen einer Methode und eines Paramsets.
     *
     * @return array[]
     */
    protected function requests(string $Method, string $Paramset = ''): array
    {
        return array_values(array_filter(FakeHomematicIO::$Requests, function ($Request) use ($Method, $Paramset)
        {
            return ($Request['MethodName'] ?? '') == $Method && (($Paramset === '') || (($Request['Data'][1] ?? '') == $Paramset));
        }));
    }

    /**
     * Werte der letzten putParamset-Anfrage.
     */
    protected function lastPut(string $Paramset): array
    {
        $Requests = $this->requests('putParamset', $Paramset);
        $this->assertNotEmpty($Requests, 'No putParamset ' . $Paramset . ' sent');
        return json_decode(end($Requests)['Data'][2], true);
    }

    protected function device(): object
    {
        return IPS\InstanceManager::getInstanceInterface($this->DeviceId);
    }

    protected function variable(string $Ident): int
    {
        return IPS_GetObjectIDByIdent($Ident, $this->DeviceId);
    }

    /**
     * Führt eine Bedienaktion aus und liefert die Ausgabe (Fehlermeldungen werden per echo ausgegeben).
     */
    protected function action(string $Ident, mixed $Value): string
    {
        ob_start();
        $this->device()->RequestAction($Ident, $Value);
        return (string) ob_get_clean();
    }
}
