[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-homematic-extended-systemvariablen-programme-powermeter-u-v-m/38340)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)
[![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)

# HomeMatic IP Weekly Program  <!-- omit in toc -->

Wochenprogramm (Schaltzeitpunkte) der HomeMatic IP Aktoren, das in der CCU im Kanal `*_WEEK_PROFILE` gespeichert wird (z.B. Kanal 6 der Schaltaktoren).

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen](#5-statusvariablen)
- [6. Visualisierung](#6-visualisierung)
  - [Kachel Visualisierung](#kachel-visualisierung)
  - [WebFront Visualisierung](#webfront-visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

> [!NOTE]
> Das Modul ist neu und bisher an einem HmIP-Schaltaktor (HmIP-FSI16), einem HmIP-Dimmaktor (HmIP-BDT) und von einem Nutzer an einem Jalousieaktor (HmIP-BBL) getestet. Welche Typen von Wochenprogrammen geprüft sind, zeigt die Tabelle unten.

Das Modul

- liest das Wochenprogramm (Paramset `MASTER`, bis zu 75 Schaltzeitpunkte) über den Homematic Socket,
- schreibt ein geändertes Wochenprogramm in das Gerät und
- hält das zuletzt gelesene Programm in der Instanz, so dass es ohne Anfrage an die CCU angezeigt werden kann.

Ein Schaltzeitpunkt besteht aus Wochentagen, einer festen Uhrzeit oder einer Astro-Zeit (Sonnenaufgang bzw. -untergang mit Versatz), der Bedingung, die beides verknüpft, dem Schaltzustand (`LEVEL`), der Dauer und den Zielkanälen.

### Unterstützte Geräte

Das Wochenprogramm liegt bei allen HmIP-Aktoren im Kanal mit einem der Typen:

| Kanaltyp                               | Geräte (Anzahl laut Dokumentation)  | Zusätzliche Felder                                          | Getestet |
| -------------------------------------- | ----------------------------------- | ----------------------------------------------------------- | -------- |
| `SWITCH_WEEK_PROFILE`                  | Schaltaktoren (51)                  | Dauer                                                       | ✅ (HmIP-FSI16, Kanal 6) |
| `DIMMER_WEEK_PROFILE`                  | Dimmer (22)                         | Dauer, Rampenzeit                                           | ✅ (HmIP-BDT, Kanal 7) |
| `BLIND_WEEK_PROFILE`                   | Rollladen- und Jalousieaktoren (26) | `LEVEL_2` (Lamellen)                                        | ✅ (HmIP-BBL, Kanal 7, Rückmeldung eines Nutzers) |
| `UNIVERSAL_LIGHT_WEEK_PROFILE`         | Universal-Lichtaktoren (4)          | Dauer, Rampenzeit, Farbe, Sättigung, Farbtemperatur, Effekt | ❌ |
| `DIMMER_OUTPUT_BEHAVIOUR_WEEK_PROFILE` | Dimmer mit Ausgangsverhalten (3)    | Dauer, Rampenzeit, `OUTPUT_BEHAVIOUR`                       | ❌ |
| `SERVO_WEEK_PROFILE`                   | HmIP-WSC (1)                        | Dauer, Rampenzeit                                           | ❌ |

Getestet ist das Lesen und Schreiben bisher an einem HmIP-Schaltaktor (Kanal 6), einem HmIP-Dimmaktor (HmIP-BDT, Kanal 7, mit Rampenzeit, Dauer und Astro-Zeit) und von einem Nutzer an einem Jalousieaktor (HmIP-BBL, Kanal 7, mit Lamellenposition). Die Felder der übrigen Typen werden nach der Beschreibung gelesen, die das Gerät selbst liefert (Typ, Bereich, Werteliste), und in der Kachel und beim Schreiben danach geprüft. Rückmeldungen mit dem Debug der Instanz sind willkommen.

### Felder eines Schaltzeitpunktes

Belegte Schaltzeitpunkte stehen lückenlos ab Nummer 1, ein Eintrag ist belegt, wenn `WEEKDAY` ungleich 0 ist. Die Bedeutung der Werte stammt aus dem Vergleich mit der CCU-Oberfläche an einem Schaltaktor.

| Feld                         | Bereich        | Bedeutung                                                                                                                                                             |
| :--------------------------- | :------------- | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `WEEKDAY`                    | 0 - 127        | Bitmaske der Wochentage: 1 = Sonntag, 2 = Montag, 4 = Dienstag, 8 = Mittwoch, 16 = Donnerstag, 32 = Freitag, 64 = Samstag                                              |
| `CONDITION`                  | 0 - 7          | Legt fest, welche Zeit gilt: 0 = feste Uhrzeit, 1 = Astro-Zeit, 2 bis 7 = Kombination aus beiden. Siehe [Zeitpunkt: feste Uhrzeit, Astro und Bedingung](#zeitpunkt-feste-uhrzeit-astro-und-bedingung) |
| `FIXED_HOUR`, `FIXED_MINUTE` | 0 - 23, 0 - 59 | Feste Uhrzeit (wird bei `CONDITION` 1 nicht beachtet)                                                                                                                 |
| `ASTRO_TYPE`                 | 0 - 1          | 0 Sonnenaufgang, 1 Sonnenuntergang (wird bei `CONDITION` 0 nicht beachtet)                                                                                            |
| `ASTRO_OFFSET`               | -128 - 127     | Versatz zur Astro-Zeit in Minuten, negativ = vorher, positiv = danach (wird bei `CONDITION` 0 nicht beachtet)                                                         |
| `LEVEL`                      | 0.0 - 1.01     | Schaltzustand: 1.0 = Ein, 0.0 = Aus (bei Dimmern der Wert). 1.01 ist der Maximalwert des Wertebereichs, die Bedeutung ist nicht belegt                                |
| `DURATION_BASE`              | 0 - 7          | Einheit der Dauer: 0 = 100 ms, 1 = 1 s, 2 = 5 s, 3 = 10 s, 4 = 1 min, 5 = 5 min, 6 = 10 min, 7 = 1 h                                                                  |
| `DURATION_FACTOR`            | 0 - 31         | Faktor der Dauer. Basis 7 mit Faktor 31 bedeutet dauerhaft                                                                                                            |
| `TARGET_CHANNELS`            | 0 - 16777215   | Bitmaske der Zielkanäle, Bit 0 = erster Schaltkanal des Gerätes (bei den Kanälen 3 bis 5: 1 = Kanal 3, 2 = Kanal 4, 4 = Kanal 5)                                       |

### Zeitpunkt: feste Uhrzeit, Astro und Bedingung

Jeder Schaltzeitpunkt kann eine feste Uhrzeit (`FIXED_HOUR`, `FIXED_MINUTE`) und eine Astro-Zeit (`ASTRO_TYPE`, `ASTRO_OFFSET`) enthalten. Welche davon gilt, bestimmt allein `CONDITION`. Die Astro-Zeit ist der Sonnenaufgang (`ASTRO_TYPE` 0) oder Sonnenuntergang (`ASTRO_TYPE` 1) des Tages plus `ASTRO_OFFSET` Minuten, z.B. `ASTRO_TYPE` 1 mit `ASTRO_OFFSET` -30 = eine halbe Stunde vor Sonnenuntergang. Der Versatz reicht von gut zwei Stunden vorher bis gut zwei Stunden danach.

> [!IMPORTANT]
> Ein Astro-Schaltzeitpunkt braucht `CONDITION` 1 (oder eine der Kombinationen 2 bis 7). Mit `CONDITION` 0 schaltet der Aktor zur festen Uhrzeit, die Astro-Felder werden nicht beachtet. Fehlen dann `FIXED_HOUR` und `FIXED_MINUTE`, schaltet der Eintrag um 00:00 Uhr bzw. zur Uhrzeit, die vorher an dieser Nummer im Gerät stand.

| `CONDITION` | Bezeichnung in der CCU          | Benötigte Felder        | Geschaltet wird                                                                    |
| :---------- | :------------------------------ | :---------------------- | :--------------------------------------------------------------------------------- |
| 0           | `FIXED_TIME`                    | feste Uhrzeit           | zur festen Uhrzeit                                                                 |
| 1           | `ASTRO`                         | Astro                   | zur Astro-Zeit                                                                     |
| 2           | `FIXED_IF_BEFORE_ASTRO`         | feste Uhrzeit und Astro | zur festen Uhrzeit, aber nur, wenn sie vor der Astro-Zeit liegt, sonst gar nicht   |
| 3           | `ASTRO_IF_BEFORE_FIXED`         | feste Uhrzeit und Astro | zur Astro-Zeit, aber nur, wenn sie vor der festen Uhrzeit liegt, sonst gar nicht   |
| 4           | `FIXED_IF_AFTER_ASTRO`          | feste Uhrzeit und Astro | zur festen Uhrzeit, aber nur, wenn sie nach der Astro-Zeit liegt, sonst gar nicht  |
| 5           | `ASTRO_IF_AFTER_FIXED`          | feste Uhrzeit und Astro | zur Astro-Zeit, aber nur, wenn sie nach der festen Uhrzeit liegt, sonst gar nicht  |
| 6           | `EARLIEST_OF_FIXED_AND_ASTRO`   | feste Uhrzeit und Astro | zur früheren der beiden Zeiten (jeden Tag)                                         |
| 7           | `LATEST_OF_FIXED_AND_ASTRO`     | feste Uhrzeit und Astro | zur späteren der beiden Zeiten (jeden Tag)                                         |

Wozu die Kombinationen dienen: Soll eine Lampe morgens um 06:00 Uhr ein- und eine Stunde nach Sonnenaufgang ausgeschaltet werden, liegt die Astro-Zeit im Sommer vor 06:00 Uhr. Mit `CONDITION` 0 für das Einschalten wäre die Lampe dann ab 06:00 Uhr bis zum nächsten Ausschaltpunkt am Abend an. Mit `CONDITION` 2 (feste Uhrzeit 06:00, Astro Sonnenaufgang +60) schaltet sie nur ein, solange 06:00 Uhr vor dieser Astro-Zeit liegt. Ebenso abends: `CONDITION` 3 (Astro Sonnenuntergang -30, feste Uhrzeit 22:30) schaltet nur ein, wenn die Astro-Zeit vor dem Ausschalten um 22:30 Uhr liegt.

> [!NOTE]
> Die Wirkung der Werte 2 bis 7 ist aus den Bezeichnungen der CCU abgeleitet und am Gerät nicht geprüft. Am Dimmaktor geprüft sind feste Uhrzeit und Astro-Zeit (0 und 1).

## 2. Voraussetzungen

- IP-Symcon ab Version 9.0
- Ein Homematic Socket (`HomeMatic Socket`), an dem die CCU angebunden ist, mit aktiviertem HmIP
- Ein HmIP-Aktor mit Wochenprogramm ist an der CCU angelernt

## 3. Software-Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).

## 4. Einrichten der Instanzen in IP-Symcon

> [!TIP]
> Am einfachsten wird die Instanz über den `HomeMatic Extended Konfigurator` angelegt. Er erkennt die Kanäle `*_WEEK_PROFILE` und trägt Adresse und System selbstständig ein.

Alternativ unter `Instanz hinzufügen` das Gerät `HomeMatic IP Weekly Program` unter dem Hersteller `HomeMatic` auswählen.

**Konfigurationsseite:**

| Eigenschaft | Name     | Typ     | Standardwert | Beschreibung                                                           |
| :---------- | :------- | :------ | :----------- | :--------------------------------------------------------------------- |
| Adresse     | Address  | string  | (leer)       | Adresse des Wochenprogramm-Kanals mit Kanal, z.B. `002560C99B959A:6`. |
| System      | Protocol | integer | `2`          | Schnittstelle der CCU: `2` = IP.                                       |
| Bearbeiten in der Kachel erlauben | AllowEdit | boolean | `true` | Ist die Option aus, ist die Kachel eine reine Anzeige und `Übernehmen` wird von der Instanz abgelehnt. Gilt für alle Benutzer der Visualisierung. |

![Konfiguration](imgs/config.png)  

Beim Übernehmen der Einstellungen und beim Start von Symcon liest die Instanz das Wochenprogramm.

## 5. Statusvariablen

Das Modul legt keine Statusvariablen an. Das Wochenprogramm wird in der Instanz gehalten und über die Kachel und die Funktionen bedient.

## 6. Visualisierung

### Kachel Visualisierung

Die Kachel passt sich der Größe an und übernimmt Farben und Schrift der Visualisierung. Ist die Überschrift der Kachel aktiv, rückt der Inhalt unter die Überschrift, sonst bleibt oben rechts Platz für den Maximieren-Pfeil.  

| Größe der Kachel                        | Anzeige                                                                                     |
| :-------------------------------------- | :------------------------------------------------------------------------------------------ |
| klein (Höhe unter 150 px oder Breite unter 250 px) | Anzahl der Schaltzeitpunkte und die nächste Schaltung                                       |
| mittel                                  | Liste der Schaltzeitpunkte, ein Klick zeigt die Einzelheiten                                |
| groß (ab 560 × 360 px)                  | Wochenübersicht (Montag bis Sonntag, 0 bis 24 Uhr, Astro-Zeiten in einer eigenen Zeile) und die Liste |

Ein Klick auf einen Balken der Wochenübersicht öffnet den zugehörigen Eintrag in der Liste. Dauerhaft eingeschaltete Zeiten zeigt die Übersicht als dünnen Balken mit blasser Fläche bis zum Tagesende. Die nächste Schaltung berücksichtigt nur Zeitpunkte mit fester Uhrzeit, Astro-Zeiten kennt die Kachel nicht.  
**Bearbeiten:** Der Stift in der Kopfzeile schaltet den Bearbeitungsmodus ein (nur wenn die Eigenschaft `Bearbeiten in der Kachel erlauben` aktiv ist). Die Einträge zeigen dann ihr Formular:

- Bedingung (Feste Uhrzeit, Astro, die Mischformen), Uhrzeit, Astro mit Versatz,
- Schaltzustand (Ein/Aus, bei Dimmern und Rollläden ein Wert in Prozent),
- Dauer nur bei Ein (dauerhaft oder Wert mit Einheit, wie in der CCU-Oberfläche), bei Aus gibt es keine Dauer,
- Wochentage und Zielkanäle (mit den Namen der Kanal-Instanzen, `Alle`/`Keine`),
- Löschen eines Eintrags und `Schaltzeitpunkt hinzufügen`.

Geändert wird nur ein Entwurf, die Kopfzeile zeigt `Nicht übernommene Änderungen` sowie `Übernehmen` und `Verwerfen`. Erst `Übernehmen` schreibt das Programm in das Gerät: Die Einträge werden nach Uhrzeit geordnet (Astro-Zeiten nach 6 Uhr bzw. 18 Uhr plus Versatz), die Instanz prüft alle Werte und liest das Programm danach zur Kontrolle neu von der CCU. Fehler erscheinen in der Kopfzeile der Kachel. Felder, die nur einzelne Gerätetypen kennen (Rampenzeit, Lamellen, Farbe, Effekt, Ausgangsverhalten), zeigt das Formular nach der Beschreibung des Gerätes: Auswahlen aus der Werteliste, Prozentwerte, Zeiten als Wert mit Einheit. Sie sind bisher nur mit simulierten Daten geprüft.  

**Modus der Schaltkanäle:** Unter der Kopfzeile zeigt die Kachel je Schaltkanal den Modus (`Manuell` oder `Automatisch`). Welche Kanäle des Gerätes Schaltkanäle sind, liest die Instanz einmalig von der CCU. Als Name erscheint der Name der Symcon-Instanz des Kanals, ohne Instanz `Kanal <Nummer>`. Mit der Berechtigung schaltet ein Klick auf den Kanal den Modus um (Wochenprogramm für diesen Kanal an oder aus). Die Kachel folgt auch Änderungen am Gerät oder in der CCU, weil die Instanz das Ereignis `WEEK_PROGRAM_CHANNEL_LOCKS` auswertet.

Die Kachel steht nur in der Kachel-Visualisierung zur Verfügung, nicht im klassischen WebFront.

Die Kachel im Raster (mit Modus der Kanäle und Liste) in den Größen 1 × 1, 3 × 2 und 7 × 4:

![Kachel 1×1](imgs/tile_instance_1x1.png)
![Kachel 3×2](imgs/tile_instance_3x2.png)  

![Kachel 7×4](imgs/tile_instance_7x4.png)

Maximiert, mit Wochenübersicht:

![Kachel maximiert](imgs/tile_full.png)

Im Bearbeitungsmodus mit dem Formular eines Schaltzeitpunktes:

![Kachel Bearbeiten](imgs/tile_edit.png)

### WebFront Visualisierung

Das klassische WebFront wird nicht unterstützt, die Instanz bietet nur die Kachel-Visualisierung.  

## 7. PHP-Befehlsreferenz

`string|false HM_ReadWeeklyProgram(integer $InstanzID);`
Liest das Wochenprogramm von der CCU und liefert die belegten Schaltzeitpunkte als JSON (Nummer => Felder).
Bei einem Fehler wird `false` zurückgegeben und eine Meldung ausgegeben.

**Beispiel:**

```php
$Program = json_decode(HM_ReadWeeklyProgram(12345), true);
```

`boolean HM_WriteWeeklyProgram(integer $InstanzID, string $Program);`
Schreibt das Wochenprogramm in das Gerät. `$Program` ist ein JSON-Array mit Schaltzeitpunkten, die Felder entsprechen der Tabelle oben.
**Jeder Aufruf ersetzt das gesamte Programm im Gerät.** Alle Schaltzeitpunkte müssen daher in einem Aufruf übergeben werden. Die Einträge des Arrays werden in ihrer Reihenfolge lückenlos ab Nummer 1 abgelegt, alle übrigen Einträge im Gerät werden geleert. Mehrere Aufrufe nacheinander ergänzen das Programm nicht, im Gerät steht danach nur das zuletzt übergebene Array. Um ein bestehendes Programm zu ergänzen oder zu ändern, wird es vorher mit `HM_ReadWeeklyProgram` gelesen (siehe Beispiel 3).
Ungültige Werte werden vor dem Senden abgelehnt (Rückgabe `false`, Meldung).
Geschrieben werden nur die Felder, die ein Eintrag enthält. Ein fehlendes Feld behält im Gerät den Wert, der dort vorher an dieser Nummer stand. Daher immer alle Felder angeben, die `CONDITION` benötigt, sowie `LEVEL`, Dauer und `TARGET_CHANNELS`.

**Beispiel 1:** Montag und Mittwoch um 12:34 Uhr für 3 Minuten, nur der zweite Zielkanal:

```php
$Program = [[
    'WEEKDAY' => 2 + 8,
    'CONDITION' => 0,
    'FIXED_HOUR' => 12,
    'FIXED_MINUTE' => 34,
    'LEVEL' => 1.0,
    'DURATION_BASE' => 4,
    'DURATION_FACTOR' => 3,
    'TARGET_CHANNELS' => 2
]];
HM_WriteWeeklyProgram(12345, json_encode($Program));
```

**Beispiel 2:** Täglich um 06:00 Uhr ein und um 10:00 Uhr aus, erster Zielkanal. Beide Schaltzeitpunkte stehen in einem Array, die Dauer ist jeweils dauerhaft (Basis 7, Faktor 31):

```php
$EveryDay = 1 + 2 + 4 + 8 + 16 + 32 + 64;
$Program = [
    [
        'WEEKDAY' => $EveryDay,
        'CONDITION' => 0,
        'FIXED_HOUR' => 6,
        'FIXED_MINUTE' => 0,
        'LEVEL' => 1.0,
        'DURATION_BASE' => 7,
        'DURATION_FACTOR' => 31,
        'TARGET_CHANNELS' => 1
    ],
    [
        'WEEKDAY' => $EveryDay,
        'CONDITION' => 0,
        'FIXED_HOUR' => 10,
        'FIXED_MINUTE' => 0,
        'LEVEL' => 0.0,
        'DURATION_BASE' => 7,
        'DURATION_FACTOR' => 31,
        'TARGET_CHANNELS' => 1
    ]
];
HM_WriteWeeklyProgram(12345, json_encode($Program));
```

**Beispiel 3:** Das vorhandene Programm um einen Schaltzeitpunkt erweitern (samstags und sonntags um 22:30 Uhr aus):

```php
$Program = json_decode(HM_ReadWeeklyProgram(12345), true);
$Program[] = [
    'WEEKDAY' => 1 + 64,
    'CONDITION' => 0,
    'FIXED_HOUR' => 22,
    'FIXED_MINUTE' => 30,
    'LEVEL' => 0.0,
    'DURATION_BASE' => 7,
    'DURATION_FACTOR' => 31,
    'TARGET_CHANNELS' => 1
];
HM_WriteWeeklyProgram(12345, json_encode($Program));
```

Ebenso lassen sich Einträge des gelesenen Arrays ändern oder mit `unset` entfernen, bevor es zurückgeschrieben wird.

**Beispiel 4:** Astro-Zeiten: Täglich eine halbe Stunde vor Sonnenuntergang ein und um 22:30 Uhr aus. Der Astro-Eintrag hat `CONDITION` 1, die feste Uhrzeit wird dann nicht benötigt:

```php
$EveryDay = 1 + 2 + 4 + 8 + 16 + 32 + 64;
$Program = [
    [
        'WEEKDAY' => $EveryDay,
        'CONDITION' => 1,
        'ASTRO_TYPE' => 1,
        'ASTRO_OFFSET' => -30,
        'LEVEL' => 1.0,
        'DURATION_BASE' => 7,
        'DURATION_FACTOR' => 31,
        'TARGET_CHANNELS' => 1
    ],
    [
        'WEEKDAY' => $EveryDay,
        'CONDITION' => 0,
        'FIXED_HOUR' => 22,
        'FIXED_MINUTE' => 30,
        'LEVEL' => 0.0,
        'DURATION_BASE' => 7,
        'DURATION_FACTOR' => 31,
        'TARGET_CHANNELS' => 1
    ]
];
HM_WriteWeeklyProgram(12345, json_encode($Program));
```

Kommen morgens Einträge wie „06:00 Uhr ein“ und „Sonnenaufgang +60 Minuten aus“ hinzu, liegt die Astro-Zeit im Sommer vor 06:00 Uhr und die Lampe bliebe ab 06:00 Uhr bis zum Abend an. Für solche Fälle gibt es die Kombinationen, siehe [Zeitpunkt: feste Uhrzeit, Astro und Bedingung](#zeitpunkt-feste-uhrzeit-astro-und-bedingung) (Einschalten mit `CONDITION` 2).

`string|false HM_ReadWeeklyProgramModes(integer $InstanzID);`
Liest den Modus der Schaltkanäle von der CCU und liefert je Schaltkanal (Reihenfolge der Kanäle des Gerätes) `true` bei manuellem Modus als JSON.

`boolean HM_SetWeeklyProgramMode(integer $InstanzID, integer $Kanaele, string $Modus);`
Stellt den Modus des Wochenprogramms für Schaltkanäle ein. `$Kanaele` ist eine Bitmaske der Kanäle (1 = erster Schaltkanal, 2 = zweiter, 4 = dritter), `$Modus` ist `MANU_MODE`, `AUTO_MODE_WITHOUT_RESET` oder `AUTO_MODE_WITH_RESET`.

**Beispiel:** Wochenprogramm für den zweiten Schaltkanal ausschalten (manueller Modus):

```php
HM_SetWeeklyProgramMode(12345, 2, 'MANU_MODE');
```

`boolean HM_SetWeeklyProgramModes(integer $InstanzID, integer $ManuelleKanaele, string $AutoModus);`
Stellt genau die Kanäle der Bitmaske `$ManuelleKanaele` auf manuell (`MANU_MODE`) und alle übrigen Schaltkanäle in die Automatik. `$AutoModus` ist `AUTO_MODE_WITHOUT_RESET` oder `AUTO_MODE_WITH_RESET` und gilt für die Kanäle, die in die Automatik wechseln. `0` stellt alle Kanäle auf Automatik.
Die Funktion liest vorher den aktuellen Modus von der CCU und sendet nur an Kanäle, deren Modus sich ändert. Da die CCU je Auftrag nur einen Modus annimmt, sind das höchstens zwei Aufträge, keiner, wenn alle Kanäle schon passen. Kanäle, die bereits in der Automatik laufen, werden auch mit `AUTO_MODE_WITH_RESET` nicht zurückgesetzt.

**Beispiel:** Nur den ersten und dritten Schaltkanal manuell betreiben, den zweiten (und alle weiteren) in der Automatik:

```php
HM_SetWeeklyProgramModes(12345, 5, 'AUTO_MODE_WITHOUT_RESET');
```

## 8. Aktionen

**Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit `Auf Wert schalten` angesteuert werden, so dass hier keine speziellen Aktionen benutzt werden müssen.**  

Die Schaltzeitpunkte und der Modus werden über die Funktionen aus der PHP-Befehlsreferenz bzw. in der Kachel geändert.

## 9. Anhang

### 1. Changelog

Der vollständige Changelog der Library steht in der [Haupt-README](../README.md#2-changelog).

**Version 4.12:**

- Neue Funktion `HM_SetWeeklyProgramModes`: genau die gewählten Kanäle manuell, alle übrigen in der Automatik, mit höchstens zwei Aufträgen an die CCU.

**Version 4.11:**

- Bugfix: `HM_SetWeeklyProgramMode`, der Klick auf den Modus in der Kachel und die Aktion „Wochenprogramm-Modus einstellen“ wirkten nur auf den ersten Schaltkanal. Der Modus der übrigen Kanäle wurde zudem falsch angezeigt.

**Version 4.10:**

- Kachel: In kleinen und schmalen Kacheln (z.B. 1 × 1 oder 1 × 4) überlagerte der Text die Überschrift. Dort stehen jetzt nur die Anzahl der Schaltzeitpunkte und die nächste Schaltung, zeilenweise und ohne Knöpfe.  

**Version 4.01:**

- Fehler beim Schreiben eines leeren Programms behoben.
- Zielkanäle von der CCU: Kachel und `HM_ReadWeeklyProgramModes` zeigen alle Schaltkanäle, auch ohne Symcon-Instanz je Kanal.
- Am Dimmaktor HmIP-BDT getestet, Dokumentation von `HM_WriteWeeklyProgram` mit neuen Beispielen.

**Version 3.90:**

- Neues Modul: Wochenprogramm der HomeMatic IP Aktoren lesen und schreiben, Kachel mit Wochenübersicht, Liste, Modus der Schaltkanäle und Bearbeiten (in der Instanz abschaltbar).

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

  [![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)

  [![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)

## 10. Lizenz

[Custom NC-SA](../LICENSE)
