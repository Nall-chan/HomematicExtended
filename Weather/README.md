[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-homematic-extended-systemvariablen-programme-powermeter-u-v-m/38340)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)
[![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)

# HomeMatic Weather  <!-- omit in toc -->

Werte der HmIP-Wetterstationen sowie Summe, heute und gestern von Sonnenscheindauer und Regenmenge aus den Systemvariablen der CCU.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
  - [Unterstützte Geräte](#unterstützte-geräte)
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

Beim Anlernen einer HmIP-Wetterstation legt die CCU versteckte Systemvariablen und Programme an. Sie zählen die Sonnenscheindauer und die Regenmenge seit dem Anlernen auf und führen zusätzlich einen Tages- und einen Vortageswert. Diese Systemvariablen erscheinen nicht in der Übersicht der Systemvariablen der CCU und sind über die normale Homematic-Instanz von Symcon nicht erreichbar.  

Das Modul liest

- alle Werte des Wetterkanals (Temperatur, Luftfeuchte, Helligkeit, Wind, Regen, Sonnenscheindauer) über den Homematic Socket und  
- die Systemvariablen der CCU für Sonnenscheindauer und Regenmenge (Summe, heute, gestern).  

> [!NOTE]
> Das Modul ist neu und noch mit keiner Wetterstation getestet. Rückmeldungen mit dem Debug der Instanz (siehe [Einrichten](#4-einrichten-der-instanzen-in-ip-symcon)) sind willkommen.  

### Unterstützte Geräte

Geräte mit gleichen Datenpunkten und gleichen Systemvariablen der CCU sind zu einer Zeile zusammengefasst. Kanäle und Systemvariablen stammen aus der CCU-Firmware ([eq-3/occu](https://github.com/eq-3/occu)).  

| Geräte                     | Kanal | Kanaltyp           | Systemvariablen der CCU → Statusvariablen                                   | Getestet |
| -------------------------- | ----- | ------------------ | --------------------------------------------------------------------------- | -------- |
| HmIP-SWO-B                 | :1    | `WEATHER_TRANSMIT` | Sonnenscheindauer (Summe, heute, gestern)                                    | ❌       |
| HmIP-SWO-PL, HmIP-SWO-PR   | :1    | `WEATHER_TRANSMIT` | Sonnenscheindauer und Regenmenge (je Summe, heute, gestern)                  | ❌       |

Die HmIP-SWO-PR liefert zusätzlich die Windrichtung (`WIND_DIR`, `WIND_DIR_RANGE`), die SWO-PL und SWO-PR zusätzlich Regen (`RAINING`, `RAIN_COUNTER`).  
Die BidCos-Wetterstationen (HM-WDS100-C6-O, KS550) werden nicht angeboten: Die CCU legt für sie keine Systemvariablen an, ihre Werte stehen bereits in der Homematic-Instanz von Symcon zur Verfügung.  

## 2. Voraussetzungen

- IP-Symcon ab Version 9.0  
- Ein Homematic Socket (`HomeMatic Socket`), an dem die CCU angebunden ist, mit aktiviertem HmIP  
- Eine HmIP-Wetterstation ist an der CCU angelernt  
- Die Firewall der CCU erlaubt den Zugriff auf die `Remote HomeMatic-Script API` (siehe [Haupt-README](../README.md#2-voraussetzungen))  

## 3. Software-Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).  

## 4. Einrichten der Instanzen in IP-Symcon

> [!TIP]
> Am einfachsten wird die Instanz über den `HomeMatic Extended Konfigurator` angelegt. Er bietet den Wetterkanal aller HmIP-Wetterstationen an und trägt Adresse und System selbstständig ein.  

Alternativ unter `Instanz hinzufügen` das Gerät `HomeMatic Weather` unter dem Hersteller `HomeMatic` auswählen.  
Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der Homematic Socket ausgewählt sein.  
Existieren in IPS mehrere Homematic Sockets, so ist der auszuwählen, an welcher CCU die Wetterstation angelernt ist.  

**Konfigurationsseite:**  

| Eigenschaft | Name     | Typ     | Standardwert | Beschreibung                                                                                    |
| :---------- | :------- | :------ | :----------- | :---------------------------------------------------------------------------------------------- |
| Adresse     | Address  | string  | (leer)       | Adresse des Wetterkanals mit Kanal 1, z.B. `0001D3C99C1234:1`.                                    |
| System      | Protocol | integer | `2`          | Schnittstelle der CCU, an der das Gerät angelernt ist: `2` = IP (`0` = Funk, `1` = Kabelgebunden). |

Beim Übernehmen der Einstellungen und beim Start von Symcon liest die Instanz alle Datenpunkte des Kanals (Paramset `VALUES`), legt dafür Statusvariablen an und liest die Systemvariablen der CCU. Danach aktualisieren die Ereignisse der CCU die Werte. Meldet die Wetterstation `ILLUMINATION`, `SUNSHINEDURATION` oder `RAIN_COUNTER`, liest die Instanz die Systemvariablen 5 Sekunden später erneut. Bis dahin hat das Programm der CCU die Zähler aktualisiert, mehrere Meldungen kurz hintereinander lösen nur ein Lesen aus.  

**Beispiel für das Setzen der Adresse per PHP:**  

```php
IPS_SetProperty(12345, 'Address', '0001D3C99C1234:1');
IPS_SetProperty(12345, 'Protocol', 2);
IPS_ApplyChanges(12345);
```

**Debug für Rückmeldungen:**  
Im Debug der Instanz erscheint nach `Übernehmen` je Systemvariable eine Zeile mit dem gelesenen Wert, z.B. `RAIN_COUNTER_TOTAL | 512.300000`. Fehlt eine Systemvariable auf der CCU, steht dort ihr vollständiger Name mit `not found`. Die Zeile `Channel` zeigt die ReGa-ID des Kanals, die Teil der Namen ist.  

## 5. Statusvariablen

**Werte des Wetterkanals:**  
Für jeden Datenpunkt des Kanals wird eine Statusvariable mit dem Namen des Datenpunktes angelegt. Status-Werte (`…_STATUS`, Typ `ENUM`) werden nicht abgebildet.  

| Ident / Name                 | Typ     | Darstellung                                   | Geräte    |
| :--------------------------- | :------ | :-------------------------------------------- | :-------- |
| ACTUAL_TEMPERATURE           | float   | Wertanzeige °C, Verwendung Temperatur         | alle      |
| HUMIDITY                     | integer | Wertanzeige %                                 | alle      |
| ILLUMINATION                 | float   | Wertanzeige lx                                | alle      |
| SUNSHINEDURATION             | integer | Wertanzeige min                               | alle      |
| SUNSHINEDURATION_OVERFLOW    | boolean |                                               | alle      |
| SUNSHINE_THRESHOLD_OVERRUN   | boolean |                                               | alle      |
| WIND_SPEED                   | float   | Wertanzeige km/h                              | alle      |
| WIND_THRESHOLD_OVERRUN       | boolean |                                               | alle      |
| RAINING                      | boolean | Wertanzeige (`Kein Regen`, `Regen`)           | PL, PR    |
| RAIN_COUNTER                 | float   | Wertanzeige mm                                | PL, PR    |
| RAIN_COUNTER_OVERFLOW        | boolean |                                               | PL, PR    |
| WIND_DIR                     | integer | Wertanzeige °                                 | PR        |
| WIND_DIR_RANGE               | integer | Wertanzeige °                                 | PR        |

Die Variablentypen richten sich nach der Beschreibung der Datenpunkte, welche die CCU liefert. Als float erhalten `HUMIDITY`, `ILLUMINATION` und `WIND_DIR` eine Nachkommastelle.  

**Systemvariablen der CCU:**  
Eine Statusvariable wird angelegt, sobald die zugehörige Systemvariable auf der CCU existiert.  

| Ident                      | Name                        | Typ   | Darstellung      | Systemvariable der CCU                      |
| :------------------------- | :-------------------------- | :---- | :--------------- | :------------------------------------------ |
| SUNSHINEDURATION_TOTAL     | Sonnenscheindauer gesamt    | float | Wertanzeige min  | `svHmIPSunshineCounter_<ID>_<Adresse>`      |
| SUNSHINEDURATION_TODAY     | Sonnenscheindauer heute     | float | Wertanzeige min  | `svHmIPSunshineCounterToday_<ID>`           |
| SUNSHINEDURATION_YESTERDAY | Sonnenscheindauer gestern   | float | Wertanzeige min  | `svHmIPSunshineCounterYesterday_<ID>`       |
| RAIN_COUNTER_TOTAL         | Regenmenge gesamt           | float | Wertanzeige mm   | `svHmIPRainCounter_<ID>_<Adresse>`          |
| RAIN_COUNTER_TODAY         | Regenmenge heute            | float | Wertanzeige mm   | `svHmIPRainCounterToday_<ID>`               |
| RAIN_COUNTER_YESTERDAY     | Regenmenge gestern          | float | Wertanzeige mm   | `svHmIPRainCounterYesterday_<ID>`           |

`<ID>` ist die ReGa-ID des Wetterkanals, `<Adresse>` die Adresse des Kanals.  

> [!IMPORTANT]
> Der Tag der CCU wechselt um 07:00 Uhr: Dann übernimmt die CCU den Wert von `heute` nach `gestern` und setzt `heute` auf 0. Die Instanz zeigt den neuen Stand mit der nächsten Meldung der Wetterstation.  

Weitere Eigenheiten der CCU-Programme: Die Summen werden nur fortgeschrieben, wenn der Gerätezähler nicht 0 ist. Der Tageswert wird bei 1440 oder mehr auf 0 gesetzt. Das passt für die Sonnenscheindauer (1440 min = 24 h), gilt in der CCU aber auch für die Regenmenge in mm.  

## 6. Visualisierung

### Kachel Visualisierung

*Screenshot der Kachel Visualisierung folgt.*  

### WebFront Visualisierung

Die direkte Darstellung im WebFront ist möglich, es wird aber empfohlen mit Links zu arbeiten.  

## 7. PHP-Befehlsreferenz

Es existieren keine PHP-Befehle für dieses Modul.  

## 8. Aktionen

**Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit `Auf Wert schalten` angesteuert werden, so dass hier keine speziellen Aktionen benutzt werden müssen.**  

Die Statusvariablen der Wetterstation sind nicht bedienbar.  

## 9. Anhang

### 1. Changelog

Der vollständige Changelog der Library steht in der [Haupt-README](../README.md#2-changelog).  

**Version 3.82:**

- Die Statusvariablen nutzen Darstellungen statt Variablenprofilen. Variablen und Archivdaten bleiben erhalten. Alle Werte erhalten einheitliche Darstellungen mit Symbol und Einheit.  

**Version 3.81:**

- Datenpunkte, bei denen die CCU einzelne Angaben (Einheit, Typ, Bedienbarkeit) nicht mitliefert, führten beim Übernehmen zu einem Fehler.  

**Version 3.78:**

- Neues Modul.  

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

  [![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)

  [![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)

## 10. Lizenz

[Custom NC-SA](../LICENSE)
