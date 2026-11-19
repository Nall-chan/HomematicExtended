<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @package       HomematicExtended
 * @file          HMTile.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       4.03
 */

/**
 * Instanz-Kachel für Listen (HTML-SDK), gemeinsamer Unterbau der Kacheln ohne Statusvariablen (Servicemeldungen, Funkpegel).
 * Aufbau nach der FritzBox-Library (gleicher Autor): `libs/tile/tile.html` (Gerüst), `tile.css` und `tile.js` (Bausteine)
 * sowie das Skript des Moduls (`tile.js` im Modulordner).
 *
 * Das Modul liefert seine Daten über `GetTileData()`. Die Kachel fordert sie beim Start per `TileReload` an und erhält sie per
 * `UpdateVisualizationValue` (`PushTile()`); eingebettet sind sie nur für die Vorschau ohne Visu (translateHTML der Visu wirkt nur
 * beim ersten Laden, siehe `.claude-rules/kachel-html-sdk.md`).
 */
trait HMTileFunctions
{
    /**
     * Flags für JSON, das in eine Script-Umgebung der Kachel eingebettet wird.
     */
    private static int $TileJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Texte der gemeinsamen Bausteine (tile.js).
     *
     * @var string[]
     */
    private static array $TileCoreTexts = ['Entries', 'All', 'Cancel', 'OK', 'Search'];

    /**
     * Interne Funktion des SDK.
     * Liefert die Kachel mit den aktuellen Daten.
     */
    public function GetVisualizationTile(): string
    {
        $Config = [
            'instance' => $this->InstanceID,
            'texts'    => $this->GetTileTexts(),
            'data'     => $this->GetTileData()
        ];
        $Dir = __DIR__ . '/tile/';
        return strtr((string) file_get_contents($Dir . 'tile.html'), [
            '/*CONFIG*/null' => json_encode($Config, self::$TileJsonFlags),
            '/*STYLE*/'      => (string) file_get_contents($Dir . 'tile.css'),
            '/*CORE*/'       => (string) file_get_contents($Dir . 'tile.js'),
            '/*MODULE*/'     => (string) file_get_contents($this->GetTileScriptFile())
        ]);
    }

    /**
     * Daten des Moduls für die Kachel.
     */
    abstract protected function GetTileData(): array;

    /**
     * Englische Texte des Modulskripts (Schlüssel für translate() der Kachel, Rückfall ohne Visu).
     *
     * @return string[]
     */
    abstract protected function GetTileTextKeys(): array;

    /**
     * Meldet die Instanz als Kachel an (normal und maximiert).
     */
    protected function RegisterTile(): void
    {
        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN);
    }

    /**
     * Sendet die aktuellen Daten an die Kachel.
     */
    protected function PushTile(): void
    {
        $this->UpdateVisualizationValue(json_encode(['type' => 'state', 'data' => $this->GetTileData()], self::$TileJsonFlags));
    }

    /**
     * Zeigt einen Hinweis in der Kachel (verschwindet nach einigen Sekunden).
     *
     * @param string $Text Übersetzter Text.
     */
    protected function PushTileNotice(string $Text): void
    {
        $this->UpdateVisualizationValue(json_encode(['type' => 'notice', 'text' => $Text], self::$TileJsonFlags));
    }

    /**
     * Pfad zum Skript des Moduls (tile.js im Ordner des Moduls).
     */
    protected function GetTileScriptFile(): string
    {
        return dirname((new \ReflectionClass($this))->getFileName()) . '/tile.js';
    }

    /**
     * Übersetzte Texte der Kachel (Rückfall, wenn translate() der Visu fehlt).
     *
     * @return string[] Englischer Text => übersetzter Text.
     */
    private function GetTileTexts(): array
    {
        $Texts = [];
        foreach (array_merge(self::$TileCoreTexts, $this->GetTileTextKeys()) as $Text) {
            $Texts[$Text] = $this->Translate($Text);
        }
        return $Texts;
    }
}

/* @} */
