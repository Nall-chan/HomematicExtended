<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       4.00
 */
require_once __DIR__ . '/../libs/HMAllChannels.php';  // HMAllChannels Klasse

/**
 * HomeMaticLED16 ist die Klasse für das IPS-Modul 'HomeMatic LED16'.
 * Bildet die LED-Anzeige HM-OU-LED16 mit allen 16 Tastenkanälen und dem Wartungskanal in einer Instanz ab.
 * Erweitert HMAllChannels.
 */
class HomeMaticLED16 extends HMAllChannels
{
    protected const ProtocolId = \HMExtended\CCU::ProtocolBidCosRF;
    protected const DefaultChannelCount = 16;

    protected const Captions = parent::Captions + [
        'LED_STATUS' => 'LED status'
    ];

    /**
     * Farben der Werte von LED_STATUS (0 aus, 1 rot, 2 grün, 3 orange).
     */
    protected const EnumColors = [
        'LED_STATUS' => [0 => -1, 1 => 0xFF0000, 2 => 0x00CC00, 3 => 0xFF9900]
    ];

    /**
     * Anzahl der LEDs.
     */
    private const LedCount = 16;

    /**
     * Wert für „unverändert“ in SetAllLEDs.
     */
    private const Unchanged = 4;

    /**
     * IPS-Instanz-Funktion 'HM_SetAllLEDs'.
     * Setzt die Farbe aller 16 LEDs in einem Telegramm (Datenpunkt ALL_LEDS des Kanals 1).
     *
     * @param string $Colors JSON-Array mit 16 Werten (LED 1 bis 16): 0 = aus, 1 = rot, 2 = grün, 3 = orange, 4 = unverändert (aktueller Wert der Statusvariable).
     *
     * @return bool true, wenn die CCU den Auftrag angenommen hat.
     */
    public function SetAllLEDs(string $Colors): bool
    {
        $List = json_decode($Colors, true);
        if (!is_array($List) || (count($List) != self::LedCount)) {
            echo $this->Translate('Expected a JSON array with 16 values.') . PHP_EOL;
            return false;
        }
        $Value = 0;
        $Leds = [];
        foreach (array_values($List) as $Index => $Color) {
            if (!is_int($Color) || ($Color < 0) || ($Color > self::Unchanged)) {
                echo $this->Translate('Invalid color, allowed are 0 (off), 1 (red), 2 (green), 3 (orange) and 4 (unchanged).') . PHP_EOL;
                return false;
            }
            $Led = $Index + 1;
            if ($Color == self::Unchanged) {
                $Variable = $this->FindIDForIdent($this->IdentOf($Led, 'LED_STATUS'));
                $Color = $Variable > 0 ? (int) GetValueInteger($Variable) : 0;
            }
            $Leds[$Led] = $Color & 3;
            $Value |= ($Color & 3) << (2 * $Index);
        }
        // ALL_LEDS erwartet den Wert als Dezimalzahl in einem String
        if (!$this->WriteDatapoint(1, 'ALL_LEDS', (string) $Value)) {
            return false;
        }
        foreach ($Leds as $Led => $Color) {
            if ($this->FindIDForIdent($this->IdentOf($Led, 'LED_STATUS')) > 0) {
                $this->SetValue($this->IdentOf($Led, 'LED_STATUS'), $Color);
            }
        }
        return true;
    }
}

/* @} */
