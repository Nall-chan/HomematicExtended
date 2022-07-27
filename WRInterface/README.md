[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20version-3.12-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://community.symcon.de/t/ip-symcon-6-1-stable-changelog/40276-IP-Symcon-5-1-%28Stable%29-Changelog)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions) [![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#6-spenden) 

# HomeMatic WR-Interface  <!-- omit in toc -->
   Bereitstellen der Informationen zu dem Wired-Interface innerhalb von IPS.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Installation](#2-installation)
- [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
- [4. Statusvariablen und Profile](#4-statusvariablen-und-profile)
- [5. WebFront](#5-webfront)
- [6. PHP-Befehlsreferenz](#6-php-befehlsreferenz)
- [7. Lizenz](#7-lizenz)

## 1. Funktionsumfang

  Dies Instanz stellt alle Daten des an einer CCU betriebenen Wired-Interfaces dar.  

## 2. Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).  


## 3. Einrichten der Instanzen in IP-Symcon


![Instanzen](../docs/HMExtendedInstanzen.png)  
   Unter Instanz hinzufügen ist das Gerät 'HomeMatic WR-Interface' unter dem Hersteller 'HomeMatic' zu finden.  
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der 'HomeMatic Socket' ausgewählt sein.  
   Existieren in IPS mehrere 'HomeMatic Socket', so ist der auszuwählen, an welche CCU dieses Wired-Interface angeschlossen ist.  


**Konfigurationsseite:**  

| Eigenschaft |   Typ   | Standardwert |                 Funktion                 |
| :---------: | :-----: | :----------: | :--------------------------------------: |
|  Interval   | integer |      0       | Intervall in Sekunden für den Datenabruf |
 

## 4. Statusvariablen und Profile  

**Statusvariablen:**  
 ![WRInterface](../docs/WRInterface.png)  
   Alle von der CCU gemeldeten Datenpunkte der Interfaces werden in IPS dargestellt.  
   Die folgenden Statusvariablen können durchaus variieren.  

| Name  /  Ident |   Typ   | Profil |
| :------------: | :-----: | :----: |
|   CONNECTED    | boolean |        |
|     SERIAL     | string  |        |

**Profile:**  

 Es werden keine Profile angelegt.  

## 5. WebFront  

Die direkte Darstellung im WebFront ist möglich, es wird aber empfohlen mit Links zu arbeiten.  


## 6. PHP-Befehlsreferenz

   Es existieren keine PHP-Befehle für dieses Modul.  

## 7. Lizenz

  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
