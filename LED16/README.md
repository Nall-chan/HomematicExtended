[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-homematic-extended-systemvariablen-programme-powermeter-u-v-m/38340)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)
[![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)

# HomeMatic LED16  <!-- omit in toc -->

LED-Anzeige `HM-OU-LED16` mit allen 16 Tastenkanälen und dem Wartungskanal in einer Instanz.

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

- Eine Instanz für das ganze Gerät: Die Adresse ist die Geräteadresse ohne Kanal, die Instanz empfängt die Ereignisse aller Kanäle.
- Je Kanal werden die Datenpunkte als Statusvariablen angelegt: LED-Status (bedienbar), Tastendruck und Installationstest der Kanäle 1 bis 16 sowie die Werte des Wartungskanals (Erreichbarkeit, Signalstärke usw.).
- Alle 16 LEDs lassen sich in einem Telegramm setzen, einzelne LEDs können dabei unverändert bleiben.

> [!NOTE]
> Das Modul ist neu und bisher nur an einer HM-OU-LED16 getestet.

## 2. Voraussetzungen

- IP-Symcon ab Version 9.0
- Ein Homematic Socket (`HomeMatic Socket`), an dem die CCU angebunden ist, mit aktivierter Funk-Schnittstelle (BidCos-RF)
- Eine HM-OU-LED16 ist an der CCU angelernt

## 3. Software-Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).

## 4. Einrichten der Instanzen in IP-Symcon

> [!TIP]
> Am einfachsten wird die Instanz über den `HomeMatic Extended Konfigurator` angelegt. Er bietet jede HM-OU-LED16 an und trägt Adresse und System selbstständig ein.

Alternativ unter `Instanz hinzufügen` das Gerät `HomeMatic LED16` unter dem Hersteller `HomeMatic` auswählen.

**Konfigurationsseite:**

| Eigenschaft | Name    | Typ    | Standardwert | Beschreibung                                                  |
| :---------- | :------ | :----- | :----------- | :------------------------------------------------------------ |
| Adresse     | Address | string | (leer)       | Adresse des Gerätes **ohne** Kanal, z.B. `JEQ0087605`.        |

Beim Übernehmen der Einstellungen und beim Start von Symcon liest die Instanz die Kanäle und Datenpunkte des Gerätes von der CCU, legt die Statusvariablen an und liest deren Werte.

## 5. Statusvariablen

Der Ident einer Statusvariable ist `<Datenpunkt>_<Kanal>`, beim Wartungskanal 0 nur `<Datenpunkt>`. Der Name der Variable besteht aus der Beschriftung des Datenpunktes und der Kanalnummer.

| Ident                                        | Name (Beispiel)    | Typ     | Darstellung                                      | Aktion | Beschreibung                                                                                                             |
| :------------------------------------------- | :----------------- | :------ | :----------------------------------------------- | :----: | :----------------------------------------------------------------------------------------------------------------------- |
| LED_STATUS_1 bis LED_STATUS_16               | LED-Status 3       | integer | Aufzählung: Aus, Rot (rot), Grün (grün), Orange  | ja     | Farbe der LED des Kanals, wird bei einem Ereignis des Gerätes aktualisiert.                                              |
| PRESS_SHORT_1 bis PRESS_SHORT_16             | Taste kurz 3       | integer | Aufzählung `Start`                               | ja     | Löst einen Tastendruck des Kanals aus. Ein Tastendruck am Gerät setzt die Variable auf 0 (nützlich als Ereignis `Bei Aktualisierung`). |
| INSTALL_TEST_1 bis INSTALL_TEST_16           | Installationstest 3 | integer | Aufzählung `Start`                              | ja     | Installationstest des Kanals (schaltet die LED um).                                                                      |
| UNREACH, STICKY_UNREACH                      | Nicht erreichbar   | boolean |                                                  | STICKY_UNREACH | Das Gerät ist nicht erreichbar.                                                                                  |
| CONFIG_PENDING, UPDATE_PENDING, DEVICE_IN_BOOTLOADER | Konfiguration steht aus | boolean |                                  |        | Zustände des Gerätes.                                                                                                    |
| RSSI_DEVICE, RSSI_PEER                       | Signalstärke (Gerät) | integer |                                                |        | Empfangsstärke.                                                                                                          |

Welche Variablen angelegt werden, richtet sich nach der Beschreibung der Datenpunkte, welche die CCU liefert. Nicht lesbare Datenpunkte (z.B. `ALL_LEDS`) und der Schlüssel `AES_KEY` werden nicht angelegt.

## 6. Visualisierung

### Kachel Visualisierung

Die Statusvariablen erscheinen in der Kachel-Visualisierung wie die Variablen anderer Module. Die LED-Status haben eine Aufzählung mit den Farben der LEDs.

### WebFront Visualisierung

Die Statusvariablen sind im klassischen WebFront bedienbar.

## 7. PHP-Befehlsreferenz

`boolean HM_SetAllLEDs(integer $InstanzID, string $Farben);`
Setzt die Farbe aller 16 LEDs in einem Telegramm (Datenpunkt `ALL_LEDS` des Kanals 1).
`$Farben` ist ein JSON-Array mit 16 Werten für die LEDs 1 bis 16: `0` = aus, `1` = rot, `2` = grün, `3` = orange, `4` = unverändert (die LED behält den Wert ihrer Statusvariable `LED_STATUS_<n>`).
Bei ungültigen Werten wird nichts gesendet, es wird `false` zurückgegeben und eine Meldung ausgegeben.

**Beispiel:** LED 1 orange, LED 2 unverändert, LED 3 rot, alle anderen aus:

```php
$Farben = array_fill(0, 16, 0);
$Farben[0] = 3;
$Farben[1] = 4;
$Farben[2] = 1;
HM_SetAllLEDs(12345, json_encode($Farben));
```

## 8. Aktionen

**Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit `Auf Wert schalten` angesteuert werden, so dass hier keine speziellen Aktionen benutzt werden müssen.**

Zusätzlich bietet die Library die Aktion `Alle LEDs setzen` für die Tastenkanäle einer HM-OU-LED16 an, siehe [actions.md](../actions.md).

## 9. Anhang

### 1. Changelog

Der vollständige Changelog der Library steht in der [Haupt-README](../README.md#2-changelog).

**Version 3.83:**

- Neues Modul: HM-OU-LED16 mit allen Kanälen in einer Instanz, Funktion `HM_SetAllLEDs`.

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

  [![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)

  [![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)

## 10. Lizenz

[Custom NC-SA](../LICENSE)
