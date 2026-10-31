[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-homematic-extended-systemvariablen-programme-powermeter-u-v-m/38340)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FHomematicExtended%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)  
[![License](https://img.shields.io/badge/License-Custom--NC--SA-green.svg)](#10-lizenz)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)
[![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](../README.md#6-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](../README.md#6-spenden)

# HomeMatic RF-Interface Splitter  <!-- omit in toc -->

   Abrufen der Informationen für die Funk-Interfaces.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen](#5-statusvariablen)
- [6. Visualisierung](#6-visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
  - [Datenaustausch](#datenaustausch)
- [8. Aktionen](#8-aktionen)
- [9. Anhang](#9-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

   Diese Instanz liest alle an einer CCU betriebenen Funk-Interfaces aus und stellt diese Daten den Instanzen 'HomeMatic RF-Interface' bereit.  

## 2. Voraussetzungen

- IP-Symcon ab Version 9.0  
- Ein Homematic Socket (`HomeMatic Socket`), an dem die CCU angebunden ist  
- Die Protokolle Funk und/oder HmIP sind im Homematic Socket aktiviert  

## 3. Software-Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).  

## 4. Einrichten der Instanzen in IP-Symcon

![Instanzen](../imgs/HMExtendedInstanzen.png)  
   Unter Instanz hinzufügen ist das Gerät 'HomeMatic RF-Interface Splitter' unter dem Hersteller 'HomeMatic' zu finden.  
   Diese Instanz wird automatisch erzeugt, sobald im [HomeMatic Extended Konfigurator](../ExtendedConfigurator/) ein RF-Interface angelegt wird.
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der HomeMatic Socket ausgewählt sein.  
   Existieren in IPS mehrere Homematic Socket, so ist über 'Schnittstelle ändern' der Homematic Socket auszuwählen, an welcher CCU die Funk-Interfaces ausgelesen werden sollen.  
   ![RFInterfaceSplitterGateway](imgs/change_parent.png)  
   
**Konfigurationsseite:**  

![Konfiguration](imgs/config.png)  

   In dem Splitter ist der Intervall in Sekunden einzustellen, in welchem die Daten von der CCU gelesen werden sollen.  

| Eigenschaft |   Typ   | Standardwert |                 Funktion                 |
| :---------: | :-----: | :----------: | :--------------------------------------: |
|  Interval   | integer |      60      | Intervall in Sekunden für den Datenabruf |
 

## 5. Statusvariablen

   Es werden keine Statusvariablen angelegt.  

## 6. Visualisierung

Die Instanz besitzt keine Statusvariablen und wird nicht visualisiert.  

## 7. PHP-Befehlsreferenz

   Es existieren keine PHP-Befehle für dieses Modul.  

### Datenaustausch

GUID für den Datenaustausch zwischen RF-Interface Splitter und RF-Interface:  

|              Device               |                  GUID                  |
| :-------------------------------: | :------------------------------------: |
| Senden zum RF-Interface Splitter  | {2F910A05-3607-4070-A6FF-53539E5D3BBB} |
| Empfang vom RF-Interface Splitter | {E2966A08-BCE1-4E76-8C4B-7E0136244E1B} |

## 8. Aktionen

**Grundsätzlich können alle bedienbaren Statusvariablen als Ziel einer [`Aktion`](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/) mit `Auf Wert schalten` angesteuert werden, so dass hier keine speziellen Aktionen benutzt werden müssen.**  

Die Instanz besitzt keine Statusvariablen und keine speziellen Aktionen.  

## 9. Anhang

### 1. Changelog

Der vollständige Changelog der Library steht in der [Haupt-README](../README.md#2-changelog).  

**Version 3.75:**

- Meldet den Instanzen `HomeMatic RF-Interface` auch, welche Interfaces die CCU aktuell kennt, damit offline Interfaces erkannt werden (Issue 13).  

### 2. Spenden

Dieses Modul ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:

  [![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)

  [![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)

## 10. Lizenz

[Custom NC-SA](../LICENSE)
