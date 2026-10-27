[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-homematic-extended-systemvariablen-programme-powermeter-u-v-m/38340)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)
[![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](../README.md#6-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](../README.md#6-spenden)

# HomeMatic IP Heating Group <!-- omit in toc -->

HmIP Heizgruppe `HmIP-HEATING` mit Statusvariablen und Zeitprofil (Wochenplan).  

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

- Stellt die Werte einer HmIP Heizgruppe (`HmIP-HEATING`) der CCU als Statusvariablen dar.  
- Sechs Zeitprofile (Wochenpläne), das aktive Zeitprofil ist umschaltbar. Der Wochenplan wird in IP-Symcon bearbeitet und in ein wählbares Profil der CCU gespeichert.  
- Urlaub/Party mit Start- und Endzeit sowie optionale Statusvariablen für Boost, Entkalkung, Temperaturen und weitere Parameter.  

## 2. Voraussetzungen

- IP-Symcon ab Version 9.0  
- Ein Homematic Socket (`HomeMatic Socket`), an dem die CCU angebunden ist  
- Eine HmIP Heizgruppe ist auf der CCU angelegt  
- Die Verwendung der HomeMatic Gruppen ist im Homematic Socket aktiviert (`Gruppen`)  

## 3. Software-Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).  

## 4. Einrichten der Instanzen in IP-Symcon

> [!TIP]
> Am einfachsten wird die Instanz über den [`HomeMatic Extended Konfigurator`](../ExtendedConfigurator/) angelegt. Er trägt die Adresse selbstständig ein.  

Alternativ unter `Instanz hinzufügen` das Gerät `HomeMatic IP Heating Group` unter dem Hersteller `HomeMatic` auswählen.  
Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der Homematic Socket ausgewählt sein.  
Existieren in IPS mehrere Homematic Sockets, so ist der auszuwählen, an dem das Gerät bzw. die Gruppe angebunden ist.  

**Konfigurationsseite:**  

![Konfiguration](imgs/config.png)  

| Eigenschaft | Name | Typ | Standardwert | Beschreibung |
| :---------- | :--- | :-- | :----------- | :----------- |
| Adresse | Address | string | leer | Adresse des Gerätes bzw. der Gruppe, z.B. `INT0000007`. Die Adresse der Gruppe beginnt mit `INT` und wird ohne Kanal eingetragen. Der Kanal 1 wird vom Modul ergänzt. |
| System | Protocol | integer | 3 | Gruppen (virtuelle Geräte der CCU). Wird vom Modul gesetzt und nicht im Formular angezeigt. |
| Verändern der Solltemperatur setzt Modus auf Manuell | SetPointBehavior | boolean | `true` | Das Ändern der Solltemperatur schaltet den Modus der Solltemperatur auf Manuell. |
| Aktives Zeitprofil | enable_ACTIVE_PROFILE | boolean | `true` | Variable für das aktive Zeitprofil. |
| Zeitplan | enable_SCHEDULE | boolean | `true` | Legt den Wochenplan (Ereignis) und die Variablen zum Speichern an. |
| Betriebsmodus | enable_HEATING_COOLING | boolean | `false` | Optionale Statusvariable (Bereich „Regelung“) |
| Boost | enable_BOOST_MODE | boolean | `true` | Optionale Statusvariable (Bereich „Boost“) |
| Boost Restlaufzeit | enable_BOOST_TIME | boolean | `false` | Optionale Statusvariable (Bereich „Boost“) |
| Boost Ventilöffnung | enable_BOOST_POSITION | boolean | `false` | Optionale Statusvariable (Bereich „Boost“) |
| Boostdauer | enable_BOOST_TIME_PERIOD | boolean | `false` | Optionale Statusvariable (Bereich „Boost“) |
| Urlaub/Party Steuerung | enable_PARTY | boolean | `true` | Optionale Statusvariable (Bereich „Urlaub/Party“) |
| Komforttemperatur | enable_TEMPERATURE_COMFORT | boolean | `false` | Optionale Statusvariable (Bereich „Temperatur“) |
| Absenktemperatur | enable_TEMPERATURE_LOWERING | boolean | `false` | Optionale Statusvariable (Bereich „Temperatur“) |
| Komforttemperatur kühlen | enable_TEMPERATURE_COMFORT_COOLING | boolean | `false` | Optionale Statusvariable (Bereich „Temperatur“) |
| Absenktemperatur kühlen | enable_TEMPERATURE_LOWERING_COOLING | boolean | `false` | Optionale Statusvariable (Bereich „Temperatur“) |
| Fenster offen Temperatur | enable_TEMPERATURE_WINDOW_OPEN | boolean | `true` | Optionale Statusvariable (Bereich „Temperatur“) |
| Fensterzustand | enable_WINDOW_STATE | boolean | `true` | Optionale Statusvariable (Bereich „Temperatur“) |
| Entkalkung Uhrzeit | enable_DECALCIFICATION_TIME | boolean | `false` | Optionale Statusvariable (Bereich „Entkalkung“) |
| Entkalkung Wochentag | enable_DECALCIFICATION_WEEKDAY | boolean | `false` | Optionale Statusvariable (Bereich „Entkalkung“) |
| Ventilanpassung | enable_VALVE_ADAPTION | boolean | `false` | Optionale Statusvariable (Bereich „Ventil“) |
| Ventilzustand | enable_VALVE_STATE | boolean | `true` | Optionale Statusvariable (Bereich „Ventil“) |
| Ventilöffnung | enable_LEVEL | boolean | `true` | Optionale Statusvariable (Bereich „Ventil“) |
| Frostschutz | enable_FROST_PROTECTION | boolean | `false` | Optionale Statusvariable (Bereich „Ventil“) |
| Schaltpunkt erreicht | enable_SWITCH_POINT_OCCURED | boolean | `false` | Optionale Statusvariable (Bereich „Ventil“) |
| Temperaturen anpassen | ScheduleTemps | string (JSON) | 5, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 30 | Temperaturen, die im Wochenplan immer als Aktion zur Verfügung stehen. Maximal 31 Aktionen. Bearbeitung über die Schaltfläche `Temperaturen anpassen`. |

Beispiel (Optionale Statusvariable aktivieren):  

```php
IPS_SetProperty(12345, 'enable_ACTIVE_PROFILE', true);
IPS_ApplyChanges(12345);
```

## 5. Statusvariablen

Welche Statusvariablen angelegt werden, richtet sich nach dem Gerät und den Optionen der Konfiguration. Die Namen werden in der Systemsprache angezeigt.  

| Name | Ident | Typ | Bedienbar | Optional | Quelle | Profil / Darstellung |
| :--- | :---- | :-- | :-------- | :------- | :----- | :------------------- |
| Aktives Zeitprofil | ACTIVE_PROFILE | Integer | ja | ja (an) | Werte | folgt |
| Temperatur | ACTUAL_TEMPERATURE | Float | nein |  | Werte | folgt |
| Boost | BOOST_MODE | Boolean | ja | ja (an) | Werte | folgt |
| Boost Restlaufzeit | BOOST_TIME | Integer | nein | ja (aus) | Werte | folgt |
| Frostschutz | FROST_PROTECTION | Boolean | nein | ja (aus) | Werte | folgt |
| Betriebsmodus | HEATING_COOLING | Integer | ja | ja (aus) | Werte | folgt |
| Feuchtigkeit | HUMIDITY | Integer | nein |  | Werte | folgt |
| Ventilöffnung | LEVEL | Float | ja | ja (an) | Werte | folgt |
| Urlaub/Party Solltemperatur | PARTY_SET_POINT_TEMPERATURE | Float | ja | ja (an) | Werte | folgt |
| Urlaub/Party Ende | PARTY_TIME_END | Integer | ja | ja (an) | Werte | folgt |
| Urlaub/Party Anfang | PARTY_TIME_START | Integer | ja | ja (an) | Werte | folgt |
| Modus Solltemperatur | SET_POINT_MODE | Integer | ja |  | Werte | folgt |
| Solltemperatur | SET_POINT_TEMPERATURE | Float | ja |  | Werte | folgt |
| Schaltpunkt erreicht | SWITCH_POINT_OCCURED | Boolean | nein | ja (aus) | Werte | folgt |
| Ventilanpassung | VALVE_ADAPTION | Boolean | ja | ja (aus) | Werte | folgt |
| Ventilzustand | VALVE_STATE | Integer | nein | ja (an) | Werte | folgt |
| Fensterzustand | WINDOW_STATE | Integer | ja | ja (an) | Werte | folgt |
| Entkalkung Uhrzeit | DECALCIFICATION_TIME | Integer | ja | ja (aus) | Parameter | folgt |
| Entkalkung Wochentag | DECALCIFICATION_WEEKDAY | Integer | ja | ja (aus) | Parameter | folgt |
| Boost Ventilöffnung | BOOST_POSITION | Integer | ja | ja (aus) | Parameter | folgt |
| Boostdauer | BOOST_TIME_PERIOD | Integer | ja | ja (aus) | Parameter | folgt |
| Komforttemperatur | TEMPERATURE_COMFORT | Float | ja | ja (aus) | Parameter | folgt |
| Komforttemperatur kühlen | TEMPERATURE_COMFORT_COOLING | Float | ja | ja (aus) | Parameter | folgt |
| Absenktemperatur | TEMPERATURE_LOWERING | Float | ja | ja (aus) | Parameter | folgt |
| Absenktemperatur kühlen | TEMPERATURE_LOWERING_COOLING | Float | ja | ja (aus) | Parameter | folgt |
| Fenster offen Temperatur | TEMPERATURE_WINDOW_OPEN | Float | ja | ja (an) | Parameter | folgt |

Zusätzlich werden bei aktivem Wochenplan angelegt:  

| Name | Ident | Typ | Bedienbar | Beschreibung |
| :--- | :---- | :-- | :-------- | :----------- |
| Wochenplan | WEEK_PROFIL | Ereignis | ja | Wochenplan des aktiven Zeitprofils. Die Aktionen sind die Temperaturen. |
| Zeitplan speichern in Profil | SELECT_NEW_WEEK_PROGRAM | Integer | ja | Zeitprofil der CCU (1 bis 6), in das der Wochenplan gespeichert wird. |
| Zeitplan speichern | SUBMIT_WEEK_PROGRAM | Integer | ja | Überträgt den Wochenplan in das gewählte Zeitprofil zur CCU. |

> [!NOTE]
> Die Spalte `Profil / Darstellung` ist noch nicht gefüllt. Die Variablenprofile werden auf Darstellungen umgestellt.  

> [!WARNING]
> Das Ändern einer Option in der Konfiguration löscht die Statusvariable der abgewählten Option.  

## 6. Visualisierung

### Kachel Visualisierung

*Screenshot der Kachel Visualisierung folgt.*  

### WebFront Visualisierung

*Screenshot des WebFront folgt.*  

Das klassische WebFront zeigt Font-Awesome-Symbole nicht an.  

## 7. PHP-Befehlsreferenz

**`bool HM_RequestParams(integer $InstanzID);`**  

Liest alle Parameter (Zeitprofile und Einstellungen des Gerätes) von der CCU und aktualisiert die Statusvariablen sowie den Wochenplan.  
Liefert `true`, wenn die CCU die Parameter geliefert hat, sonst `false`.  

```php
HM_RequestParams(12345);
```

**`bool HM_RequestState(integer $InstanzID, string $Ident);`**  

Liest den Wert eines Datenpunktes (Quelle `Werte` in der Tabelle der Statusvariablen) von der CCU und schreibt ihn in die Statusvariable.  
`$Ident` ist der Ident der Statusvariable, z.B. `SET_POINT_TEMPERATURE`. Ein Ident ohne Datenpunkt der Quelle `Werte` wird mit einer Fehlermeldung abgelehnt.  
Liefert `true`, wenn die CCU den Wert geliefert hat, sonst `false`.  

```php
HM_RequestState(12345, 'SET_POINT_TEMPERATURE');
```

## 8. Aktionen

**Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit `Auf Wert schalten` angesteuert werden, so dass hier keine speziellen Aktionen benutzt werden müssen.**  

Der Wochenplan hat für jede Temperatur eine Aktion, die beim Auslösen die Parameter der CCU neu liest (`HM_RequestParams`). Die Temperaturen des Wochenplans werden in der Konfiguration unter `Temperaturen anpassen` gepflegt.  

## 9. Anhang

### 1. Changelog

Der vollständige Changelog der Library steht in der [Haupt-README](../README.md#2-changelog).  

Version 3.75:

- Das Modul nutzt den Datenfluss des Homematic Sockets (ohne eigene Verbindung).  
- Die Aktions-IDs des Wochenplans beginnen bei 1 (Fehler „Wochenplan-Aktions-ID muss größer als 0 sein“ behoben).  
- Zeiten für Party und Entkalkung werden in 30-Minuten-Schritten übertragen (exakt 30 Minuten wird nicht mehr abgerundet).  
- Texte der CCU mit Umlauten bzw. dem Zeichen `°` werden richtig gelesen.  

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

  [![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)

  [![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)

## 10. Lizenz

[Custom NC-SA](../LICENSE)
