[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.60-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-0-%28Stable%29-Changelog)
[![StyleCI](https://styleci.io/repos/34275278/shield?style=flat)](https://styleci.io/repos/34275278)  

# HomeMatic RF-Interface (ab IPS 4.3)  
   Bereitstellen der Informationen zu den Funk-Interfaces innerhalb von IPS.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Installation](#2-installation)
3. [Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)  
4. [Statusvariablen und Profile](#4-statusvariablen-und-profile)  
5. [WebFront](#5-webfront) 
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)   
7. [Lizenz](#7-lizenz)

## 1. Funktionsumfang

   Dies Instanz stellt alle Daten eines an einer CCU betriebenen Funk-Interfaces dar.  

## 2. Installation

Dieses Modul ist Bestandteil der HomeMaticExtended-Library.  


## 3. Einrichten der Instanzen in IP-Symcon


![Instanzen](../docs/HMExtendedInstanzen.png)  
   Unter Instanz hinzufügen ist das Gerät 'HomeMatic RF-Interface' unter dem Hersteller 'HomeMatic' zu finden.  
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der 'HomeMatic RF-Interface Splitter' ausgewählt sein.  
   Existieren in IPS mehrere 'HomeMatic RF-Interface Splitter', so ist der auszuwählen, an welche CCU dieses Funk-Interface angeschlossen ist.  

   Da das auslesen der Adresse der Funk-Interfaces über die WebUI der CCU relativ schwierig ist, ist es einfach diese Instanzen automatisch durch den Splitter anlegen zu lassen (siehe [HomeMatic RF-Interface Splitter](../RFInterfaceSplitter/) ).  


**Konfigurationsseite:**  

![RFInterface](../docs/RFInterface.png)  

| Eigenschaft     | Typ     | Standardwert | Funktion                                      |
| :-------------: | :-----: | :----------: | :-------------------------------------------: |
| Address         | string  |              | Adresse des Interfaces                        |


## 4. Statusvariablen und Profile  

   Alle von der CCU gemeldeten Datenpunkte der Interfaces werden in IPS dargestellt.  
   Die folgenden Statusvariablen können durchaus varieren.  

| Name  /  Ident   | Typ     | Profil         |
| :--------------: | :-----: | :------------: |
| CONNECTED        | boolean |                |
| DEFAULT          | boolean |                |
| DESCRIPTION      | string  |                |
| DUTY_CYCLE       | integer | ~Intensity.100 |
| FIRMWARE_VERSION | string  |                |
| TYPE             | string  |                |

## 5. WebFront  

Die direkte Darstellung im WebFront ist möglich, es wird aber empfohlen mit Links zu arbeiten.  
![RFInterface](../docs/RFInterface_WF.png)  


## 6. PHP-Befehlsreferenz

   Es existieren keine PHP-Befehle für dieses Modul.  

## 7. Lizenz

  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
